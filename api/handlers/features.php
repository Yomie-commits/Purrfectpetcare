<?php
/**
 * Attachments (photos & documents), vet schedules, itemised invoices,
 * inventory and backups. Demo data lives in api/lib/demo.php.
 */
defined('APP_LOADED') or exit;

// ---------------------------------------------------------------------------
// Attachments
// ---------------------------------------------------------------------------
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const UPLOAD_TYPES = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf',
];

function upload_dir(): string
{
    return ROOT . '/storage/uploads';
}

/**
 * Check what the user may do with an entity's files.
 * Returns ['view' => bool, 'upload' => bool, 'owner_id' => ?int].
 */
function attachment_access(string $type, int $id, array $u): array
{
    switch ($type) {
        case 'pet':
            $p = q_one('SELECT owner_id FROM pets WHERE id = ?', [$id]);
            if (!$p) {
                fail('Pet not found', 404);
            }
            $mine = (int)$p['owner_id'] === $u['id'];
            return ['view' => $mine || is_staff($u), 'upload' => $mine || is_staff($u), 'owner_id' => (int)$p['owner_id']];
        case 'record':
            $r = q_one('SELECT p.owner_id FROM health_records r JOIN pets p ON p.id = r.pet_id WHERE r.id = ?', [$id]);
            if (!$r) {
                fail('Record not found', 404);
            }
            return ['view' => is_staff($u) || (int)$r['owner_id'] === $u['id'], 'upload' => is_staff($u), 'owner_id' => (int)$r['owner_id']];
        case 'lostfound':
            $r = q_one('SELECT user_id FROM lost_found WHERE id = ?', [$id]);
            if (!$r) {
                fail('Report not found', 404);
            }
            return ['view' => true, 'upload' => is_staff($u) || (int)$r['user_id'] === $u['id'], 'owner_id' => (int)$r['user_id']];
        case 'adoption':
            if (!q_val('SELECT COUNT(*) FROM adoption_listings WHERE id = ?', [$id])) {
                fail('Listing not found', 404);
            }
            return ['view' => true, 'upload' => is_staff($u), 'owner_id' => null];
    }
    fail('Unknown attachment type');
}

function detect_mime(string $path): string
{
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $m = finfo_file($f, $path);
        finfo_close($f);
        if ($m) {
            return $m;
        }
    }
    $img = @getimagesize($path);
    if ($img && !empty($img['mime'])) {
        return $img['mime'];
    }
    $head = (string)file_get_contents($path, false, null, 0, 5);
    return $head === '%PDF-' ? 'application/pdf' : 'application/octet-stream';
}

/** Shrink very large photos to save the hosting account's space. */
function shrink_image(string $path, string $mime): void
{
    if (!function_exists('imagecreatefromstring') || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return;
    }
    $info = @getimagesize($path);
    if (!$info || max($info[0], $info[1]) <= 1600) {
        return;
    }
    $src = @imagecreatefromstring((string)file_get_contents($path));
    if (!$src) {
        return;
    }
    $scale = 1600 / max($info[0], $info[1]);
    $w = (int)round($info[0] * $scale);
    $h = (int)round($info[1] * $scale);
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
    if ($mime === 'image/png') {
        imagepng($dst, $path, 7);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        imagewebp($dst, $path, 82);
    } else {
        imagejpeg($dst, $path, 85);
    }
    imagedestroy($src);
    imagedestroy($dst);
}

function upload_file(): array
{
    $u = require_login();
    $type = v_enum(opt('entity_type'), ['pet', 'record', 'lostfound', 'adoption'], 'attachment type');
    $id = req_id('entity_id');
    $kind = v_enum(opt('kind', 'document'), ['photo', 'document'], 'kind');
    $access = attachment_access($type, $id, $u);
    if (!$access['upload']) {
        fail('You cannot add files here', 403);
    }
    $f = $_FILES['file'] ?? null;
    if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        fail('Please choose a file');
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > UPLOAD_MAX_BYTES) {
        fail('File is too large. The limit is 5 MB.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        fail('Upload failed. Please try again.');
    }
    $mime = detect_mime($f['tmp_name']);
    if (!isset(UPLOAD_TYPES[$mime])) {
        fail('Only photos (JPG, PNG, WEBP, GIF) and PDF documents can be uploaded');
    }
    if ($kind === 'photo' && $mime === 'application/pdf') {
        fail('Please choose a photo (JPG, PNG or WEBP)');
    }
    $sub = date('Y/m');
    $dir = upload_dir() . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        fail('The server could not save the file (storage folder not writable)', 500);
    }
    $stored = $sub . '/' . bin2hex(random_bytes(16)) . '.' . UPLOAD_TYPES[$mime];
    if (!move_uploaded_file($f['tmp_name'], upload_dir() . '/' . $stored)) {
        fail('The server could not save the file', 500);
    }
    shrink_image(upload_dir() . '/' . $stored, $mime);
    clearstatcache();
    if ($kind === 'photo') {
        foreach (q_all("SELECT id FROM attachments WHERE entity_type = ? AND entity_id = ? AND kind = 'photo'", [$type, $id]) as $old) {
            remove_attachment((int)$old['id']);
        }
    }
    $name = preg_replace('/[^\w.\- ()]+/u', '_', basename((string)$f['name'])) ?: 'file';
    $aid = insert('attachments', [
        'entity_type' => $type,
        'entity_id' => $id,
        'kind' => $kind,
        'original_name' => mb_substr($name, 0, 200),
        'stored_name' => $stored,
        'mime' => $mime,
        'size_bytes' => (int)filesize(upload_dir() . '/' . $stored),
        'uploaded_by' => $u['id'],
        'created_at' => now(),
    ]);
    if ($type === 'record' && $access['owner_id'] && $access['owner_id'] !== $u['id']) {
        notify($access['owner_id'], 'New document added', 'A document was added to your pet\'s health record.', 'records');
    }
    return q_one('SELECT * FROM attachments WHERE id = ?', [$aid]);
}

function attachments_list(): array
{
    $u = require_login();
    $type = v_enum(opt('entity_type'), ['pet', 'record', 'lostfound', 'adoption'], 'attachment type');
    $id = req_id('entity_id');
    if (!attachment_access($type, $id, $u)['view']) {
        fail('Not found', 404);
    }
    return q_all('SELECT a.id, a.kind, a.original_name, a.mime, a.size_bytes, a.created_at, a.uploaded_by, u.name AS uploaded_by_name
                  FROM attachments a LEFT JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = ? AND a.entity_id = ? ORDER BY a.created_at DESC, a.id DESC', [$type, $id]);
}

function remove_attachment(int $id): void
{
    $a = q_one('SELECT stored_name FROM attachments WHERE id = ?', [$id]);
    if ($a) {
        $path = upload_dir() . '/' . $a['stored_name'];
        if (is_file($path)) {
            @unlink($path);
        }
        q('DELETE FROM attachments WHERE id = ?', [$id]);
    }
}

/** Remove all files of some entities (used before deleting them). */
function purge_attachments(string $type, array $ids): void
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (q_all("SELECT id FROM attachments WHERE entity_type = ? AND entity_id IN ($in)", array_merge([$type], $ids)) as $a) {
        remove_attachment((int)$a['id']);
    }
}

/** Remove the files belonging to a pet and its health records. */
function purge_pet_files(array $petIds): void
{
    $petIds = array_values(array_filter(array_map('intval', $petIds)));
    if (!$petIds) {
        return;
    }
    $in = implode(',', array_fill(0, count($petIds), '?'));
    $records = array_column(q_all("SELECT id FROM health_records WHERE pet_id IN ($in)", $petIds), 'id');
    purge_attachments('record', $records);
    purge_attachments('pet', $petIds);
}

function purge_user_files(int $userId): void
{
    purge_pet_files(array_column(q_all('SELECT id FROM pets WHERE owner_id = ?', [$userId]), 'id'));
    purge_attachments('lostfound', array_column(q_all('SELECT id FROM lost_found WHERE user_id = ?', [$userId]), 'id'));
}

function attachment_delete(): array
{
    $u = require_login();
    $a = q_one('SELECT * FROM attachments WHERE id = ?', [req_id()]);
    if (!$a) {
        fail('File not found', 404);
    }
    $access = attachment_access($a['entity_type'], (int)$a['entity_id'], $u);
    if (!$access['upload'] && (int)$a['uploaded_by'] !== $u['id']) {
        fail('You cannot delete this file', 403);
    }
    remove_attachment((int)$a['id']);
    return ['deleted' => (int)$a['id']];
}

// ---------------------------------------------------------------------------
// Vet schedules
// ---------------------------------------------------------------------------
function schedule_vet_id(array $u): int
{
    if ($u['role'] === 'vet') {
        return $u['id'];
    }
    $id = req_id('vet_id');
    if (!q_val("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'vet'", [$id])) {
        fail('Vet not found', 404);
    }
    return $id;
}

function schedule_get(): array
{
    $u = require_staff();
    // Vets see their own hours unless they ask for a colleague's; other staff pick a vet.
    if ($u['role'] === 'vet' && opt_id('vet_id') === null) {
        return schedule_data($u['id']);
    }
    $id = req_id('vet_id');
    if (!q_val("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'vet'", [$id])) {
        fail('Vet not found', 404);
    }
    return schedule_data($id);
}

function schedule_data(int $vetId): array
{
    return [
        'vet' => q_one('SELECT id, name, specialization FROM users WHERE id = ?', [$vetId]),
        'weekly' => array_map(function ($r) {
            return ['weekday' => (int)$r['weekday'], 'start' => substr($r['start_time'], 0, 5), 'end' => substr($r['end_time'], 0, 5)];
        }, q_all('SELECT weekday, start_time, end_time FROM vet_schedules WHERE vet_id = ? ORDER BY weekday, start_time', [$vetId])),
        'time_off' => q_all('SELECT id, date_from, date_to, reason FROM vet_time_off WHERE vet_id = ? AND date_to >= ? ORDER BY date_from', [$vetId, today()]),
        'clinic' => ['open' => setting('open_time', '08:00'), 'close' => setting('close_time', '19:00'), 'closed_days' => setting('closed_days', '0')],
    ];
}

function schedule_save(): array
{
    $u = require_role('vet', 'admin');
    $vetId = schedule_vet_id($u);
    $weekly = input()['weekly'] ?? [];
    if (!is_array($weekly)) {
        fail('Invalid schedule');
    }
    $rows = [];
    foreach ($weekly as $d) {
        $wd = (int)($d['weekday'] ?? -1);
        if ($wd < 0 || $wd > 6) {
            fail('Invalid day');
        }
        $start = v_time((string)($d['start'] ?? ''));
        $end = v_time((string)($d['end'] ?? ''));
        if ($start >= $end) {
            fail('On each working day the finishing time must be after the starting time');
        }
        $rows[] = [$wd, $start, $end];
    }
    q('DELETE FROM vet_schedules WHERE vet_id = ?', [$vetId]);
    foreach ($rows as [$wd, $s, $e]) {
        insert('vet_schedules', ['vet_id' => $vetId, 'weekday' => $wd, 'start_time' => $s, 'end_time' => $e]);
    }
    return schedule_data($vetId);
}


function time_off_add(): array
{
    $u = require_role('vet', 'admin');
    $vetId = schedule_vet_id($u);
    $from = v_date(opt('date_from'), 'start date');
    $to = v_date(opt('date_to'), 'end date');
    if ($to < $from) {
        fail('The end date must be on or after the start date');
    }
    if ($to < today()) {
        fail('Time off must be today or later');
    }
    insert('vet_time_off', ['vet_id' => $vetId, 'date_from' => $from, 'date_to' => $to, 'reason' => opt('reason'), 'created_at' => now()]);
    $clashes = (int)q_val("SELECT COUNT(*) FROM appointments WHERE vet_id = ? AND appt_date BETWEEN ? AND ? AND status IN ('pending','confirmed')", [$vetId, $from, $to]);
    $r = schedule_data($vetId);
    $r['clashes'] = $clashes;
    return $r;
}

function time_off_delete(): array
{
    $u = require_role('vet', 'admin');
    $row = q_one('SELECT * FROM vet_time_off WHERE id = ?', [req_id()]);
    if (!$row || ($u['role'] === 'vet' && (int)$row['vet_id'] !== $u['id'])) {
        fail('Not found', 404);
    }
    q('DELETE FROM vet_time_off WHERE id = ?', [$row['id']]);
    return schedule_data((int)$row['vet_id']);
}

// ---------------------------------------------------------------------------
// Itemised invoices
// ---------------------------------------------------------------------------
function recalc_appointment_total(int $apptId): float
{
    $total = round((float)q_val('SELECT COALESCE(SUM(quantity * unit_price - discount), 0) FROM appointment_items WHERE appointment_id = ?', [$apptId]), 2);
    q('UPDATE appointments SET price = ? WHERE id = ?', [$total, $apptId]);
    return $total;
}

function invoice_get(): array
{
    $u = require_login();
    return invoice_data(req_id('appointment_id'), $u);
}

function invoice_data(int $apptId, array $u): array
{
    $a = appointment_for_user($apptId, $u);
    $items = array_map(function ($i) {
        $i['quantity'] = (float)$i['quantity'];
        $i['unit_price'] = (float)$i['unit_price'];
        $i['discount'] = (float)$i['discount'];
        $i['line_total'] = round($i['quantity'] * $i['unit_price'] - $i['discount'], 2);
        return $i;
    }, q_all('SELECT i.*, p.unit FROM appointment_items i LEFT JOIN products p ON p.id = i.product_id WHERE i.appointment_id = ? ORDER BY i.id', [$a['id']]));
    return ['appointment' => $a, 'items' => $items];
}

function invoice_item_add(): array
{
    $u = require_perm('billing');
    $a = appointment_for_user(req_id('appointment_id'), $u);
    if (in_array($a['status'], ['cancelled', 'no_show'], true)) {
        fail('You cannot add charges to a cancelled appointment');
    }
    $qty = v_decimal(opt('quantity', '1'), 'quantity') ?? 1;
    if ($qty <= 0) {
        fail('Quantity must be more than zero');
    }
    $productId = opt_id('product_id');
    $data = ['appointment_id' => (int)$a['id'], 'quantity' => $qty, 'created_by' => $u['id'], 'created_at' => now()];
    if ($productId) {
        $p = q_one('SELECT * FROM products WHERE id = ? AND active = 1', [$productId]);
        if (!$p) {
            fail('Item not found in inventory');
        }
        if ((float)$p['stock_qty'] < $qty) {
            fail("Not enough {$p['name']} in stock (only " . (float)$p['stock_qty'] . " {$p['unit']} left)");
        }
        $data['item_type'] = $p['category'] === 'supply' ? 'other' : 'medicine';
        $data['product_id'] = (int)$p['id'];
        $data['description'] = $p['name'] . (opt('note') ? ' — ' . opt('note') : '');
        $data['unit_price'] = opt('unit_price') !== null ? v_money(opt('unit_price'), 'price', true) : (float)$p['price'];
        q('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?', [$qty, $p['id']]);
        insert('stock_movements', ['product_id' => (int)$p['id'], 'change_qty' => -$qty, 'reason' => 'dispensed', 'appointment_id' => (int)$a['id'], 'user_id' => $u['id'], 'note' => $a['pet_name'], 'created_at' => now()]);
    } else {
        $data['item_type'] = v_enum(opt('item_type', 'other'), ['service', 'medicine', 'lab', 'other'], 'item type');
        $data['description'] = req('description', 'Description', 200);
        $data['unit_price'] = v_money(opt('unit_price'), 'price', true);
    }
    $data['description'] = mb_substr($data['description'], 0, 200);
    $data['discount'] = v_money(opt('discount', '0'), 'discount', true);
    if ($data['discount'] > $qty * $data['unit_price'] + 0.001) {
        fail('The discount cannot be more than the line total');
    }
    insert('appointment_items', $data);
    recalc_appointment_total((int)$a['id']);
    return invoice_data((int)$a['id'], $u);
}


/** Give a discount on one invoice line (amount off, or a percentage). */
function invoice_item_discount(): array
{
    $u = require_perm('billing');
    $item = q_one('SELECT * FROM appointment_items WHERE id = ?', [req_id()]);
    if (!$item) {
        fail('Item not found', 404);
    }
    $a = appointment_for_user((int)$item['appointment_id'], $u);
    $gross = (float)$item['quantity'] * (float)$item['unit_price'];
    $pct = opt('percent');
    $discount = $pct !== null && $pct !== '' ? round($gross * min(100, max(0, (float)$pct)) / 100, 2) : v_money(opt('discount', '0'), 'discount', true);
    if ($discount > $gross + 0.001) {
        fail('The discount cannot be more than the line total');
    }
    $newTotal = round($a['price'] - ($gross - (float)$item['discount']) + ($gross - $discount), 2);
    if ($newTotal + 0.001 < $a['amount_paid']) {
        fail('The invoice has already been paid, so this discount is too large.');
    }
    q('UPDATE appointment_items SET discount = ? WHERE id = ?', [$discount, $item['id']]);
    recalc_appointment_total((int)$a['id']);
    return invoice_data((int)$a['id'], $u);
}

function invoice_item_delete(): array
{
    $u = require_perm('billing');
    $item = q_one('SELECT * FROM appointment_items WHERE id = ?', [req_id()]);
    if (!$item) {
        fail('Item not found', 404);
    }
    $a = appointment_for_user((int)$item['appointment_id'], $u);
    $newTotal = round($a['price'] - ((float)$item['quantity'] * (float)$item['unit_price'] - (float)$item['discount']), 2);
    if ($newTotal + 0.001 < $a['amount_paid']) {
        fail('This charge has already been paid, so it cannot be removed.');
    }
    if ($item['product_id']) {
        q('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [$item['quantity'], $item['product_id']]);
        insert('stock_movements', ['product_id' => (int)$item['product_id'], 'change_qty' => (float)$item['quantity'], 'reason' => 'returned', 'appointment_id' => (int)$a['id'], 'user_id' => $u['id'], 'note' => 'Removed from invoice', 'created_at' => now()]);
    }
    q('DELETE FROM appointment_items WHERE id = ?', [$item['id']]);
    recalc_appointment_total((int)$a['id']);
    return invoice_data((int)$a['id'], $u);
}

// ---------------------------------------------------------------------------
// Inventory
// ---------------------------------------------------------------------------
function products_list(): array
{
    require_staff();
    $sql = 'SELECT p.*, (SELECT COUNT(*) FROM appointment_items i WHERE i.product_id = p.id) AS times_used FROM products p WHERE 1=1';
    $params = [];
    if (opt('active') === '1') {
        $sql .= ' AND p.active = 1';
    }
    if (opt('low') === '1') {
        $sql .= ' AND p.active = 1 AND p.stock_qty <= p.reorder_level';
    }
    if ($s = opt('q')) {
        $sql .= ' AND p.name LIKE ?';
        $params[] = "%$s%";
    }
    return array_map(function ($p) {
        foreach (['price', 'stock_qty', 'reorder_level'] as $k) {
            $p[$k] = (float)$p[$k];
        }
        $p['cost_price'] = $p['cost_price'] === null ? null : (float)$p['cost_price'];
        $p['low'] = $p['active'] && $p['stock_qty'] <= $p['reorder_level'];
        $p['is_demo'] = is_demo_product((int)$p['id']);
        return $p;
    }, q_all($sql . ' ORDER BY p.active DESC, p.category, p.name', $params));
}

function product_save(): array
{
    $u = require_perm('stock.manage');
    $id = opt_id('id');
    $data = [
        'name' => req('name', 'Name', 150),
        'category' => v_enum(opt('category', 'medicine'), ['medicine', 'vaccine', 'supply', 'food', 'grooming'], 'category'),
        'unit' => opt('unit', 'unit'),
        'price' => v_money(opt('price', '0'), 'price', true),
        'cost_price' => opt('cost_price') === null || opt('cost_price') === '' ? null : v_money(opt('cost_price'), 'cost price', true),
        'reorder_level' => v_decimal(opt('reorder_level', '0'), 'reorder level') ?? 0,
        'active' => opt('active', '1') === '1' ? 1 : 0,
    ];
    if ($id) {
        if (!q_val('SELECT COUNT(*) FROM products WHERE id = ?', [$id])) {
            fail('Item not found', 404);
        }
        update('products', $data, $id);
    } else {
        $data['stock_qty'] = v_decimal(opt('stock_qty', '0'), 'opening stock') ?? 0;
        $data['created_at'] = now();
        $id = insert('products', $data);
        if ($data['stock_qty'] > 0) {
            insert('stock_movements', ['product_id' => $id, 'change_qty' => $data['stock_qty'], 'reason' => 'opening', 'user_id' => $u['id'], 'created_at' => now()]);
        }
    }
    return q_one('SELECT * FROM products WHERE id = ?', [$id]);
}

function product_delete(): array
{
    require_role('admin');
    $id = req_id();
    if (q_val('SELECT COUNT(*) FROM appointment_items WHERE product_id = ?', [$id])) {
        q('UPDATE products SET active = 0 WHERE id = ?', [$id]);
        return ['deactivated' => $id, 'message' => 'This item has been used on invoices, so it was hidden instead of deleted.'];
    }
    q('DELETE FROM products WHERE id = ?', [$id]);
    return ['deleted' => $id];
}

function stock_adjust(): array
{
    $u = require_perm('stock.manage');
    $p = q_one('SELECT * FROM products WHERE id = ?', [req_id('product_id')]);
    if (!$p) {
        fail('Item not found', 404);
    }
    $reason = v_enum(opt('reason'), ['restock', 'adjustment', 'expired', 'damaged'], 'reason');
    $change = (float)(opt('change') ?? 0);
    if (!is_numeric(opt('change')) || $change == 0.0) {
        fail('Enter how many units to add or remove');
    }
    if ($reason === 'restock' && $change < 0) {
        fail('Restock must add stock');
    }
    if (in_array($reason, ['expired', 'damaged'], true) && $change > 0) {
        $change = -$change;
    }
    if ((float)$p['stock_qty'] + $change < 0) {
        fail('Stock cannot go below zero');
    }
    q('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [$change, $p['id']]);
    insert('stock_movements', ['product_id' => (int)$p['id'], 'change_qty' => $change, 'reason' => $reason, 'user_id' => $u['id'], 'note' => opt('note'), 'created_at' => now()]);
    return q_one('SELECT * FROM products WHERE id = ?', [$p['id']]);
}

function stock_movements(): array
{
    require_staff();
    return q_all('SELECT m.*, u.name AS user_name FROM stock_movements m LEFT JOIN users u ON u.id = m.user_id WHERE m.product_id = ? ORDER BY m.created_at DESC, m.id DESC LIMIT 100', [req_id('product_id')]);
}

// ---------------------------------------------------------------------------
// Backups
// ---------------------------------------------------------------------------
const BACKUP_TABLES = [
    'users', 'settings', 'services', 'pets', 'appointments', 'health_records', 'vaccinations', 'payments',
    'forum_posts', 'forum_replies', 'adoption_listings', 'adoption_requests', 'lost_found', 'notifications',
    'attachments', 'vet_schedules', 'vet_time_off', 'products', 'stock_movements', 'appointment_items',
    'password_resets', 'login_attempts',
];

function sql_value($v): string
{
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v) || is_float($v)) {
        return (string)$v;
    }
    return "'" . str_replace(["\\", "'", "\0", "\n", "\r", "\x1a"], ["\\\\", "\\'", "\\0", "\\n", "\\r", "\\Z"], (string)$v) . "'";
}

function backup_download(): void
{
    require_role('admin');
    log_activity('backup_download');
    $name = 'petcare-backup-' . date('Y-m-d-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "-- Purrfect Pet Care database backup\n-- Created " . now() . "\n-- Restore: in phpMyAdmin open your database, choose Import and select this file.\n\n");
    $mysql = db_driver() === 'mysql';
    if ($mysql) {
        fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    }
    foreach (BACKUP_TABLES as $t) {
        if ($mysql) {
            $create = q_one("SHOW CREATE TABLE `$t`");
            if (!$create) {
                continue;
            }
            fwrite($out, "DROP TABLE IF EXISTS `$t`;\n" . array_values($create)[1] . ";\n\n");
        } else {
            $sql = q_val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$t]);
            if (!$sql) {
                continue;
            }
            fwrite($out, "DROP TABLE IF EXISTS $t;\n$sql;\n\n");
        }
        $st = db()->query("SELECT * FROM $t");
        while ($row = $st->fetch()) {
            $cols = implode(', ', array_map(fn($c) => $mysql ? "`$c`" : $c, array_keys($row)));
            fwrite($out, "INSERT INTO " . ($mysql ? "`$t`" : $t) . " ($cols) VALUES (" . implode(', ', array_map('sql_value', array_values($row))) . ");\n");
        }
        fwrite($out, "\n");
    }
    if ($mysql) {
        fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
    }
    fclose($out);
    set_setting('last_backup_at', now());
    exit;
}

function backup_files(): void
{
    require_role('admin');
    log_activity('backup_files');
    if (!class_exists('ZipArchive')) {
        fail('This server cannot create zip files. Download the storage/uploads folder with the File Manager instead.');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'ppc');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        fail('Could not create the zip file', 500);
    }
    $count = 0;
    $base = upload_dir();
    if (is_dir($base)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), 'uploads/' . substr($file->getPathname(), strlen($base) + 1));
                $count++;
            }
        }
    }
    if (!$count) {
        $zip->addFromString('README.txt', "No files have been uploaded yet.\n");
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="petcare-files-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}
