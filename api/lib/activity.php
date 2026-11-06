<?php
/**
 * Activity log: who did what, and when.
 *
 * Every successful change made through the API is recorded automatically by
 * api/index.php using the labels below, so new actions only need a line here.
 */
defined('APP_LOADED') or exit;

/** action => [area, what happened] */
const ACTIVITY_LABELS = [
    'login' => ['account', 'Logged in'],
    'register' => ['account', 'Signed up'],
    'update_profile' => ['account', 'Updated their profile'],
    'change_password' => ['account', 'Changed their password'],
    'delete_account' => ['account', 'Deleted their own account'],
    'pet_save' => ['pets', 'Saved pet'],
    'pet_delete' => ['pets', 'Deleted pet'],
    'appointment_book' => ['appointments', 'Booked appointment'],
    'appointment_status' => ['appointments', 'Changed appointment'],
    'appointment_reschedule' => ['appointments', 'Rescheduled appointment'],
    'appointment_assign' => ['appointments', 'Assigned appointment'],
    'walkin_create' => ['appointments', 'Registered walk-in'],
    'record_save' => ['records', 'Saved health record'],
    'record_delete' => ['records', 'Deleted health record'],
    'vaccination_save' => ['records', 'Saved vaccination or treatment'],
    'vaccination_delete' => ['records', 'Deleted vaccination or treatment'],
    'pay_mpesa' => ['payments', 'Started M-Pesa payment'],
    'pay_card' => ['payments', 'Started card payment'],
    'payment_record_cash' => ['payments', 'Recorded payment at the desk'],
    'payment_cancel' => ['payments', 'Cancelled payment'],
    'invoice_item_add' => ['payments', 'Added invoice charge'],
    'invoice_item_delete' => ['payments', 'Removed invoice charge'],
    'invoice_item_discount' => ['payments', 'Gave discount'],
    'forum_create' => ['community', 'Posted in forum'],
    'forum_reply' => ['community', 'Replied in forum'],
    'forum_moderate' => ['community', 'Moderated forum post'],
    'forum_delete' => ['community', 'Deleted forum post'],
    'forum_reply_delete' => ['community', 'Deleted forum reply'],
    'adoption_save' => ['community', 'Saved adoption listing'],
    'adoption_delete' => ['community', 'Deleted adoption listing'],
    'adoption_request' => ['community', 'Requested adoption'],
    'adoption_decide' => ['community', 'Decided adoption request'],
    'lostfound_save' => ['community', 'Saved lost & found report'],
    'lostfound_resolve' => ['community', 'Resolved lost & found report'],
    'lostfound_delete' => ['community', 'Deleted lost & found report'],
    'user_save' => ['users', 'Saved user account'],
    'user_delete' => ['users', 'Deleted user account'],
    'service_save' => ['settings', 'Saved service'],
    'service_delete' => ['settings', 'Deleted service'],
    'settings_save' => ['settings', 'Changed clinic settings'],
    'upload' => ['files', 'Uploaded file'],
    'attachment_delete' => ['files', 'Deleted file'],
    'schedule_save' => ['schedule', 'Changed working hours'],
    'time_off_add' => ['schedule', 'Added time off'],
    'time_off_delete' => ['schedule', 'Removed time off'],
    'product_save' => ['stock', 'Saved stock item'],
    'product_delete' => ['stock', 'Deleted stock item'],
    'stock_adjust' => ['stock', 'Adjusted stock'],
    'demo_data_load' => ['settings', 'Loaded demo data'],
    'demo_data_remove' => ['settings', 'Removed demo data'],
    'backup_download' => ['settings', 'Downloaded database backup'],
    'backup_files' => ['settings', 'Downloaded files backup'],
    'weight_add' => ['records', 'Logged weight'],
    'weight_delete' => ['records', 'Removed weight'],
    'pet_card_save' => ['pets', 'Changed health card'],
    'enquiry_create' => ['enquiries', 'Sent enquiry'],
    'enquiry_reply' => ['enquiries', 'Replied to enquiry'],
    'enquiry_status' => ['enquiries', 'Changed enquiry status'],
    'messaging_save' => ['settings', 'Changed reminder settings'],
    'reminders_run' => ['settings', 'Sent reminders'],
    'message_test' => ['settings', 'Sent test message'],
];

function log_activity(string $action, ?string $subject = null, ?int $entityId = null, ?int $userId = null): void
{
    [$area, $label] = ACTIVITY_LABELS[$action] ?? ['other', str_replace('_', ' ', $action)];
    $u = current_user();
    try {
        insert('activity_log', [
            'user_id' => $userId ?? ($u['id'] ?? null),
            'action' => substr($action, 0, 40),
            'entity_type' => $area,
            'entity_id' => $entityId,
            'summary' => mb_substr($label . ($subject ? ': ' . $subject : ''), 0, 255),
            'ip' => substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
            'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage()); // never block the real action
    }
}

/** Build a short, readable subject for a finished API call. */
function activity_subject(string $action, $result): array
{
    $in = function (string $k) {
        $v = opt($k);
        return is_string($v) ? trim($v) : null;
    };
    $r = is_array($result) ? $result : [];
    if (isset($r['appointment']) && is_array($r['appointment'])) {
        $r = $r['appointment'] + $r; // invoice responses
    }
    $id = isset($r['id']) && is_numeric($r['id']) ? (int)$r['id'] : (opt_id('id') ?? null);
    $parts = [];
    if (in_array($action, ['invoice_item_add'], true) && !empty($r['pet_name'])) {
        $parts[] = $r['pet_name'] . ' – ' . ($in('description') ?: 'stock item');
    } elseif (!empty($r['pet_name'])) {
        $parts[] = $r['pet_name'] . (!empty($r['service_name']) ? ' – ' . $r['service_name'] : '');
    } elseif (!empty($r['title'])) {
        $parts[] = $r['title'];
    } elseif (!empty($r['name'])) {
        $parts[] = $r['name'];
    } elseif (!empty($r['vaccine'])) {
        $parts[] = $r['vaccine'];
    } elseif (!empty($r['description'])) {
        $parts[] = $r['description'];
    } elseif ($v = $in('name') ?: $in('title') ?: $in('description')) {
        $parts[] = $v;
    }
    if ($action === 'appointment_status' && ($s = $in('status'))) {
        $parts[] = 'now ' . str_replace('_', ' ', $s);
    }
    if (in_array($action, ['payment_record_cash', 'pay_mpesa', 'pay_card'], true) && isset($r['amount'])) {
        $parts[] = money((float)$r['amount']);
    }
    if ($action === 'stock_adjust' && ($q = $in('change'))) {
        $parts[] = 'change ' . $q;
    }
    if ($action === 'invoice_item_discount') {
        $parts[] = ($in('percent') ? $in('percent') . '%' : money((float)($in('discount') ?? 0))) . ' off';
    }
    if ($action === 'user_save' && !empty($r['role'])) {
        $parts[] = ROLE_LABELS[$r['role']] ?? $r['role'];
    }
    if (in_array($action, ['record_save'], true) && opt_id('id')) {
        $parts[] = 'edited';
    }
    $subject = implode(', ', array_filter($parts));
    if ($id && $subject === '') {
        $subject = '#' . $id;
    }
    return [$subject ?: null, $id];
}
