<?php
/** Health records and vaccinations. */
defined('APP_LOADED') or exit;

const RECORD_TYPES = ['checkup', 'vaccination', 'surgery', 'dental', 'illness', 'injury', 'lab', 'grooming', 'other'];
const TREATMENT_KINDS = ['vaccine', 'deworming', 'flea_tick'];
const RECORD_LOCK_HOURS = 24;

function records_list(): array
{
    $u = require_login();
    $sql = "SELECT r.*, p.name AS pet_name, p.species, o.name AS owner_name, v.name AS vet_name,
                   (SELECT COUNT(*) FROM attachments a WHERE a.entity_type = 'record' AND a.entity_id = r.id) AS file_count,
                   (SELECT COUNT(*) FROM record_versions rv WHERE rv.record_id = r.id) AS version_count
            FROM health_records r JOIN pets p ON p.id = r.pet_id JOIN users o ON o.id = p.owner_id
            LEFT JOIN users v ON v.id = r.vet_id WHERE 1=1";
    $params = [];
    if (!is_staff($u)) {
        $sql .= ' AND p.owner_id = ?';
        $params[] = $u['id'];
    } elseif (!can($u, 'medical.view')) {
        fail('Health records are only visible to vets and vet assistants', 403);
    }
    if ($pid = opt_id('pet_id')) {
        $sql .= ' AND r.pet_id = ?';
        $params[] = $pid;
    }
    if ($s = opt('q')) {
        $sql .= ' AND (p.name LIKE ? OR r.title LIKE ? OR r.diagnosis LIKE ?)';
        $like = "%$s%";
        array_push($params, $like, $like, $like);
    }
    $sql .= ' ORDER BY r.record_date DESC, r.id DESC LIMIT 500';
    return array_map('record_decorate', q_all($sql, $params));
}

function record_save(): array
{
    $u = require_perm('medical.write');
    $id = opt_id('id');
    $pet = pet_for_user(req_id('pet_id'), $u);
    $data = [
        'pet_id' => (int)$pet['id'],
        'record_date' => v_date(opt('record_date'), 'record date'),
        'record_type' => v_enum(opt('record_type'), RECORD_TYPES, 'record type'),
        'title' => req('title', 'Title', 200),
        'diagnosis' => opt('diagnosis'),
        'treatment' => opt('treatment'),
        'prescription' => opt('prescription'),
        'weight_kg' => v_decimal(opt('weight_kg'), 'weight'),
        'appointment_id' => opt_id('appointment_id'),
    ];
    if ($data['record_date'] > today()) {
        fail('Record date cannot be in the future');
    }
    if ($data['appointment_id'] && !q_val('SELECT COUNT(*) FROM appointments WHERE id = ? AND pet_id = ?', [$data['appointment_id'], $pet['id']])) {
        fail('Appointment does not belong to this pet');
    }
    if ($id) {
        $old = q_one('SELECT * FROM health_records WHERE id = ?', [$id]);
        if (!$old) {
            fail('Record not found', 404);
        }
        record_check_editable($old, $u);
        // Keep the previous version so every change can be traced.
        insert('record_versions', ['record_id' => $id, 'data' => json_encode($old, JSON_UNESCAPED_UNICODE), 'changed_by' => $u['id'], 'reason' => opt('change_reason'), 'changed_at' => now()]);
        update('health_records', $data, $id);
    } else {
        $data['vet_id'] = $u['role'] === 'vet' ? $u['id'] : null;
        $data['created_at'] = now();
        $id = insert('health_records', $data);
        notify((int)$pet['owner_id'], 'New health record', "A new record \"{$data['title']}\" was added for {$pet['name']}.", 'records');
    }
    if ($data['weight_kg']) {
        log_pet_weight((int)$pet['id'], (float)$data['weight_kg'], $data['record_date'], $u['id'], $data['title'], (int)$id);
    } else {
        q('DELETE FROM pet_weights WHERE record_id = ?', [$id]);
    }
    return q_one('SELECT * FROM health_records WHERE id = ?', [$id]);
}

/** Records can be changed freely for 24 hours; after that only an administrator can correct them. */
function record_is_locked(array $r): bool
{
    return strtotime($r['created_at']) < time() - RECORD_LOCK_HOURS * 3600;
}

function record_check_editable(array $r, array $u): void
{
    if ($u['role'] !== 'admin' && record_is_locked($r)) {
        fail('This record is locked because it is more than ' . RECORD_LOCK_HOURS . ' hours old. Add a new record, or ask an administrator to correct it.', 403);
    }
}

function record_history(): array
{
    $u = require_perm('medical.view');
    $id = req_id();
    $r = q_one('SELECT * FROM health_records WHERE id = ?', [$id]);
    if (!$r) {
        fail('Record not found', 404);
    }
    $versions = array_map(function ($v) {
        $v['data'] = json_decode($v['data'], true);
        return $v;
    }, q_all('SELECT v.*, u.name AS changed_by_name FROM record_versions v LEFT JOIN users u ON u.id = v.changed_by WHERE v.record_id = ? ORDER BY v.id DESC', [$id]));
    return ['record' => $r, 'versions' => $versions];
}

function record_delete(): array
{
    $u = require_perm('medical.write');
    $id = req_id();
    $r = q_one('SELECT * FROM health_records WHERE id = ?', [$id]);
    if (!$r) {
        fail('Record not found', 404);
    }
    record_check_editable($r, $u);
    purge_attachments('record', [$id]);
    q('DELETE FROM health_records WHERE id = ?', [$id]);
    return ['deleted' => $id];
}

function record_decorate(array $r): array
{
    $r['locked'] = record_is_locked($r);
    return $r;
}

function vaccination_with_status(array $v): array
{
    if (!$v['next_due']) {
        $v['due_status'] = 'complete';
    } elseif ($v['next_due'] < today()) {
        $v['due_status'] = 'overdue';
    } elseif ($v['next_due'] <= date('Y-m-d', strtotime('+30 days'))) {
        $v['due_status'] = 'due_soon';
    } else {
        $v['due_status'] = 'up_to_date';
    }
    return $v;
}

function vaccinations_list(): array
{
    $u = require_login();
    $sql = 'SELECT v.*, p.name AS pet_name, p.species, o.name AS owner_name, o.phone AS owner_phone, s.name AS vet_name
            FROM vaccinations v JOIN pets p ON p.id = v.pet_id JOIN users o ON o.id = p.owner_id
            LEFT JOIN users s ON s.id = v.vet_id WHERE 1=1';
    $params = [];
    if (!is_staff($u)) {
        $sql .= ' AND p.owner_id = ?';
        $params[] = $u['id'];
    }
    if ($pid = opt_id('pet_id')) {
        $sql .= ' AND v.pet_id = ?';
        $params[] = $pid;
    }
    if (opt('due') === '1') {
        // Only the latest dose of each vaccine per pet, due within 30 days or overdue.
        $sql .= ' AND v.next_due IS NOT NULL AND v.next_due <= ? AND p.status = ?
                  AND NOT EXISTS (SELECT 1 FROM vaccinations v2 WHERE v2.pet_id = v.pet_id AND v2.vaccine = v.vaccine AND v2.date_given > v.date_given)';
        $params[] = date('Y-m-d', strtotime('+30 days'));
        $params[] = 'active';
        $sql .= ' ORDER BY v.next_due ASC';
    } else {
        $sql .= ' ORDER BY v.date_given DESC, v.id DESC';
    }
    return array_map('vaccination_with_status', q_all($sql . ' LIMIT 500', $params));
}

function vaccination_save(): array
{
    $u = require_perm('treatment.write');
    $id = opt_id('id');
    $pet = pet_for_user(req_id('pet_id'), $u);
    $data = [
        'pet_id' => (int)$pet['id'],
        'kind' => v_enum(opt('kind', 'vaccine'), TREATMENT_KINDS, 'type'),
        'vaccine' => req('vaccine', 'Vaccine', 120),
        'date_given' => v_date(opt('date_given'), 'date given'),
        'next_due' => v_date(opt('next_due'), 'next due date', false),
        'batch_no' => opt('batch_no'),
        'notes' => opt('notes'),
        'reminder_sent' => 0,
    ];
    if ($data['date_given'] > today()) {
        fail('Date given cannot be in the future');
    }
    if ($data['next_due'] && $data['next_due'] <= $data['date_given']) {
        fail('Next due date must be after the date given');
    }
    if ($id) {
        if (!q_val('SELECT COUNT(*) FROM vaccinations WHERE id = ?', [$id])) {
            fail('Vaccination not found', 404);
        }
        update('vaccinations', $data, $id);
    } else {
        $data['vet_id'] = in_array($u['role'], ['vet', 'assistant'], true) ? $u['id'] : null;
        $data['created_at'] = now();
        $id = insert('vaccinations', $data);
        // An earlier dose of the same vaccine no longer needs a reminder.
        q('UPDATE vaccinations SET reminder_sent = 1 WHERE pet_id = ? AND vaccine = ? AND id <> ?', [$pet['id'], $data['vaccine'], $id]);
        $due = $data['next_due'] ? ' Next dose due ' . date('j M Y', strtotime($data['next_due'])) . '.' : '';
        notify((int)$pet['owner_id'], 'Vaccination recorded', "{$pet['name']} received {$data['vaccine']}.$due", 'vaccinations');
    }
    return vaccination_with_status(q_one('SELECT * FROM vaccinations WHERE id = ?', [$id]));
}

function vaccination_delete(): array
{
    require_perm('treatment.write');
    $id = req_id();
    q('DELETE FROM vaccinations WHERE id = ?', [$id]);
    return ['deleted' => $id];
}
