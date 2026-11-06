<?php
/** Forum, adoption and lost & found. */
defined('APP_LOADED') or exit;

// ---------------------------------------------------------------------------
// Forum
// ---------------------------------------------------------------------------
function forum_list(): array
{
    $u = require_login();
    $sql = 'SELECT f.id, f.user_id, f.title, f.content, f.status, f.created_at, u.name AS author, u.role AS author_role,
                   (SELECT COUNT(*) FROM forum_replies r WHERE r.post_id = f.id) AS replies
            FROM forum_posts f JOIN users u ON u.id = f.user_id WHERE ';
    $params = [];
    $status = opt('status');
    if ($u['role'] === 'admin' && $status) {
        $sql .= 'f.status = ?';
        $params[] = v_enum($status, ['pending', 'approved', 'rejected'], 'status');
    } elseif ($u['role'] === 'admin' && opt('all') === '1') {
        $sql .= '1=1';
    } else {
        $sql .= "(f.status = 'approved' OR f.user_id = ?)";
        $params[] = $u['id'];
    }
    if ($s = opt('q')) {
        $sql .= ' AND (f.title LIKE ? OR f.content LIKE ?)';
        array_push($params, "%$s%", "%$s%");
    }
    return q_all($sql . ' ORDER BY f.created_at DESC, f.id DESC LIMIT 200', $params);
}

function forum_post_for_user(int $id, array $u): array
{
    $p = q_one('SELECT f.*, u.name AS author, u.role AS author_role FROM forum_posts f JOIN users u ON u.id = f.user_id WHERE f.id = ?', [$id]);
    if (!$p || ($p['status'] !== 'approved' && (int)$p['user_id'] !== $u['id'] && $u['role'] !== 'admin')) {
        fail('Post not found', 404);
    }
    return $p;
}

function forum_get(): array
{
    $u = require_login();
    $p = forum_post_for_user(req_id(), $u);
    $p['replies'] = q_all('SELECT r.*, u.name AS author, u.role AS author_role FROM forum_replies r JOIN users u ON u.id = r.user_id WHERE r.post_id = ? ORDER BY r.created_at, r.id', [$p['id']]);
    return $p;
}

function forum_create(): array
{
    $u = require_login();
    $status = is_staff($u) ? 'approved' : 'pending';
    $id = insert('forum_posts', [
        'user_id' => $u['id'],
        'title' => req('title', 'Title', 200),
        'content' => req('content', 'Message', 10000),
        'status' => $status,
        'created_at' => now(),
    ]);
    if ($status === 'pending') {
        notify_role('admin', 'Forum post awaiting approval', "{$u['name']} posted: " . mb_substr(opt('title'), 0, 80), 'forum');
    }
    return q_one('SELECT * FROM forum_posts WHERE id = ?', [$id]);
}

function forum_reply(): array
{
    $u = require_login();
    $p = forum_post_for_user(req_id('post_id'), $u);
    if ($p['status'] !== 'approved') {
        fail('You can reply once the post is approved');
    }
    $id = insert('forum_replies', [
        'post_id' => (int)$p['id'],
        'user_id' => $u['id'],
        'content' => req('content', 'Reply', 5000),
        'created_at' => now(),
    ]);
    if ((int)$p['user_id'] !== $u['id']) {
        notify((int)$p['user_id'], 'New reply to your post', "{$u['name']} replied to \"{$p['title']}\".", 'forum');
    }
    return q_one('SELECT * FROM forum_replies WHERE id = ?', [$id]);
}

function forum_moderate(): array
{
    require_role('admin');
    $id = req_id();
    $status = v_enum(opt('status'), ['approved', 'rejected', 'pending'], 'status');
    $p = q_one('SELECT * FROM forum_posts WHERE id = ?', [$id]);
    if (!$p) {
        fail('Post not found', 404);
    }
    q('UPDATE forum_posts SET status = ? WHERE id = ?', [$status, $id]);
    if ($status !== 'pending') {
        notify((int)$p['user_id'], 'Your forum post was ' . $status, "\"{$p['title']}\"", 'forum');
    }
    return ['id' => $id, 'status' => $status];
}

function forum_delete(): array
{
    $u = require_login();
    $p = q_one('SELECT * FROM forum_posts WHERE id = ?', [req_id()]);
    if (!$p || ((int)$p['user_id'] !== $u['id'] && $u['role'] !== 'admin')) {
        fail('Post not found', 404);
    }
    q('DELETE FROM forum_posts WHERE id = ?', [$p['id']]);
    return ['deleted' => (int)$p['id']];
}

function forum_reply_delete(): array
{
    $u = require_login();
    $r = q_one('SELECT * FROM forum_replies WHERE id = ?', [req_id()]);
    if (!$r || ((int)$r['user_id'] !== $u['id'] && $u['role'] !== 'admin')) {
        fail('Reply not found', 404);
    }
    q('DELETE FROM forum_replies WHERE id = ?', [$r['id']]);
    return ['deleted' => (int)$r['id']];
}

// ---------------------------------------------------------------------------
// Adoption
// ---------------------------------------------------------------------------
function adoption_list(): array
{
    $u = require_login();
    $sql = "SELECT l.*, (SELECT COUNT(*) FROM adoption_requests r WHERE r.listing_id = l.id AND r.status = ?) AS pending_requests,
                   (SELECT r2.status FROM adoption_requests r2 WHERE r2.listing_id = l.id AND r2.user_id = ? ORDER BY r2.id DESC LIMIT 1) AS my_request,
                   (SELECT MAX(a.id) FROM attachments a WHERE a.entity_type = 'adoption' AND a.entity_id = l.id AND a.kind = 'photo') AS photo_id
            FROM adoption_listings l";
    $params = ['pending', $u['id']];
    if (!is_staff($u)) {
        $sql .= " WHERE l.status IN ('available','pending')";
    }
    return q_all($sql . ' ORDER BY l.status, l.created_at DESC', $params);
}

function adoption_save(): array
{
    $u = require_perm('community.manage');
    $id = opt_id('id');
    $data = [
        'name' => req('name', 'Name', 100),
        'species' => v_enum(opt('species'), SPECIES, 'species'),
        'breed' => opt('breed'),
        'age_text' => opt('age_text'),
        'sex' => opt('sex') ? v_enum(opt('sex'), ['Male', 'Female', 'Unknown'], 'sex') : null,
        'description' => opt('description'),
        'status' => v_enum(opt('status', 'available'), ['available', 'pending', 'adopted'], 'status'),
    ];
    if ($id) {
        if (!q_val('SELECT COUNT(*) FROM adoption_listings WHERE id = ?', [$id])) {
            fail('Listing not found', 404);
        }
        update('adoption_listings', $data, $id);
    } else {
        $data['created_by'] = $u['id'];
        $data['created_at'] = now();
        $id = insert('adoption_listings', $data);
    }
    return q_one('SELECT * FROM adoption_listings WHERE id = ?', [$id]);
}

function adoption_delete(): array
{
    require_perm('community.manage');
    $id = req_id();
    purge_attachments('adoption', [$id]);
    q('DELETE FROM adoption_listings WHERE id = ?', [$id]);
    return ['deleted' => $id];
}

function adoption_request(): array
{
    $u = require_login();
    $l = q_one("SELECT * FROM adoption_listings WHERE id = ? AND status IN ('available','pending')", [req_id('listing_id')]);
    if (!$l) {
        fail('This pet is no longer available for adoption');
    }
    if (q_val("SELECT COUNT(*) FROM adoption_requests WHERE listing_id = ? AND user_id = ? AND status <> 'rejected'", [$l['id'], $u['id']])) {
        fail('You have already applied to adopt ' . $l['name']);
    }
    $id = insert('adoption_requests', [
        'listing_id' => (int)$l['id'],
        'user_id' => $u['id'],
        'message' => req('message', 'Message', 3000),
        'status' => 'pending',
        'created_at' => now(),
    ]);
    if ($l['status'] === 'available') {
        q("UPDATE adoption_listings SET status = 'pending' WHERE id = ?", [$l['id']]);
    }
    notify_role('admin', 'New adoption request', "{$u['name']} wants to adopt {$l['name']}.", 'adoption');
    notify_role('vet', 'New adoption request', "{$u['name']} wants to adopt {$l['name']}.", 'adoption');
    return q_one('SELECT * FROM adoption_requests WHERE id = ?', [$id]);
}

function adoption_requests(): array
{
    $u = require_login();
    $sql = 'SELECT r.*, l.name AS pet_name, l.species, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
            FROM adoption_requests r JOIN adoption_listings l ON l.id = r.listing_id JOIN users u ON u.id = r.user_id';
    $params = [];
    if (!is_staff($u)) {
        $sql .= ' WHERE r.user_id = ?';
        $params[] = $u['id'];
    }
    return q_all($sql . ' ORDER BY r.status, r.created_at DESC', $params);
}

function adoption_decide(): array
{
    require_perm('community.manage');
    $r = q_one('SELECT r.*, l.name AS pet_name FROM adoption_requests r JOIN adoption_listings l ON l.id = r.listing_id WHERE r.id = ?', [req_id()]);
    if (!$r) {
        fail('Request not found', 404);
    }
    if ($r['status'] !== 'pending') {
        fail('This request has already been decided');
    }
    $status = v_enum(opt('status'), ['approved', 'rejected'], 'decision');
    q('UPDATE adoption_requests SET status = ? WHERE id = ?', [$status, $r['id']]);
    if ($status === 'approved') {
        q("UPDATE adoption_listings SET status = 'adopted' WHERE id = ?", [$r['listing_id']]);
        foreach (q_all("SELECT id, user_id FROM adoption_requests WHERE listing_id = ? AND status = 'pending'", [$r['listing_id']]) as $o) {
            q("UPDATE adoption_requests SET status = 'rejected' WHERE id = ?", [$o['id']]);
            notify((int)$o['user_id'], 'Adoption update', "{$r['pet_name']} has been adopted by another family. Thank you for your interest.", 'adoption');
        }
        notify((int)$r['user_id'], 'Adoption approved!', "Your request to adopt {$r['pet_name']} was approved. The clinic will contact you to arrange pick-up.", 'adoption');
    } else {
        $left = q_val("SELECT COUNT(*) FROM adoption_requests WHERE listing_id = ? AND status = 'pending'", [$r['listing_id']]);
        if (!$left) {
            q("UPDATE adoption_listings SET status = 'available' WHERE id = ? AND status = 'pending'", [$r['listing_id']]);
        }
        notify((int)$r['user_id'], 'Adoption update', "Your request to adopt {$r['pet_name']} was not approved this time.", 'adoption');
    }
    return ['id' => (int)$r['id'], 'status' => $status];
}

// ---------------------------------------------------------------------------
// Lost & Found
// ---------------------------------------------------------------------------
function lostfound_list(): array
{
    $u = require_login();
    $sql = "SELECT lf.*, u.name AS reporter, u.email AS reporter_email,
                   (SELECT MAX(a.id) FROM attachments a WHERE a.entity_type = 'lostfound' AND a.entity_id = lf.id AND a.kind = 'photo') AS photo_id
            FROM lost_found lf JOIN users u ON u.id = lf.user_id WHERE 1=1";
    $params = [];
    if ($t = opt('type')) {
        $sql .= ' AND lf.report_type = ?';
        $params[] = v_enum($t, ['lost', 'found'], 'type');
    }
    if ($s = opt('status')) {
        $sql .= ' AND lf.status = ?';
        $params[] = v_enum($s, ['open', 'resolved'], 'status');
    }
    $rows = q_all($sql . ' ORDER BY lf.status, lf.event_date DESC, lf.id DESC LIMIT 300', $params);
    foreach ($rows as &$r) {
        $r['mine'] = (int)$r['user_id'] === $u['id'];
        if (!is_staff($u)) {
            unset($r['reporter_email']);
        }
    }
    return $rows;
}

function lostfound_for_user(int $id, array $u): array
{
    $r = q_one('SELECT * FROM lost_found WHERE id = ?', [$id]);
    if (!$r || ((int)$r['user_id'] !== $u['id'] && !is_staff($u))) {
        fail('Report not found', 404);
    }
    return $r;
}

function lostfound_save(): array
{
    $u = require_login();
    $id = opt_id('id');
    $data = [
        'report_type' => v_enum(opt('report_type'), ['lost', 'found'], 'report type'),
        'species' => v_enum(opt('species'), SPECIES, 'species'),
        'pet_name' => opt('pet_name'),
        'description' => req('description', 'Description', 3000),
        'location' => req('location', 'Location', 200),
        'event_date' => v_date(opt('event_date'), 'date'),
        'contact_phone' => v_phone(opt('contact_phone')) ?? $u['phone'],
    ];
    if ($data['event_date'] > today()) {
        fail('Date cannot be in the future');
    }
    if ($id) {
        lostfound_for_user($id, $u);
        update('lost_found', $data, $id);
    } else {
        $data['user_id'] = $u['id'];
        $data['status'] = 'open';
        $data['created_at'] = now();
        $id = insert('lost_found', $data);
    }
    return q_one('SELECT * FROM lost_found WHERE id = ?', [$id]);
}

function lostfound_resolve(): array
{
    $u = require_login();
    $r = lostfound_for_user(req_id(), $u);
    $status = $r['status'] === 'open' ? 'resolved' : 'open';
    q('UPDATE lost_found SET status = ? WHERE id = ?', [$status, $r['id']]);
    return ['id' => (int)$r['id'], 'status' => $status];
}

function lostfound_delete(): array
{
    $u = require_login();
    $r = lostfound_for_user(req_id(), $u);
    if ((int)$r['user_id'] !== $u['id'] && $u['role'] !== 'admin') {
        fail('Only the reporter or an admin can delete this report', 403);
    }
    purge_attachments('lostfound', [(int)$r['id']]);
    q('DELETE FROM lost_found WHERE id = ?', [$r['id']]);
    return ['deleted' => (int)$r['id']];
}
