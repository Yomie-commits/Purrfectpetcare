<?php
/**
 * Purrfect Pet Care JSON API
 *
 * Call as:  api/index.php?action=<name>
 * - GET  actions only read data.
 * - POST actions change data and must send the X-CSRF-Token header
 *   (the token comes from the `me`, `login` or `register` responses).
 * Responses: {"ok":true,"data":...} or {"ok":false,"error":"..."}
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/gateways.php';
require __DIR__ . '/lib/migrations.php';
require __DIR__ . '/lib/demo.php';
require __DIR__ . '/lib/activity.php';
require __DIR__ . '/lib/messaging.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (!is_installed()) {
    json_out(['ok' => false, 'error' => 'The system is not installed yet. Open install.php to set it up.'], 503);
}

foreach (glob(__DIR__ . '/handlers/*.php') as $file) {
    require $file;
}

// Upgrade the database automatically after new code is uploaded.
try {
    run_migrations();
} catch (Throwable $e) {
    error_log('Migration failed: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'The database upgrade failed: ' . (env('APP_DEBUG') === 'true' ? $e->getMessage() : 'see storage/php-errors.log')], 500);
}

// action => [function, HTTP method]
const ROUTES = [
    // Auth & profile
    'me'                    => ['auth_me', 'GET'],
    'public_info'           => ['auth_public_info', 'GET'],
    'login'                 => ['auth_login', 'POST'],
    'register'              => ['auth_register', 'POST'],
    'logout'                => ['auth_logout', 'POST'],
    'forgot_password'       => ['auth_forgot', 'POST'],
    'reset_password'        => ['auth_reset', 'POST'],
    'update_profile'        => ['auth_update_profile', 'POST'],
    'change_password'       => ['auth_change_password', 'POST'],
    'delete_account'        => ['auth_delete_account', 'POST'],

    // Pets
    'pets_list'             => ['pets_list', 'GET'],
    'pet_get'               => ['pet_get', 'GET'],
    'pet_save'              => ['pet_save', 'POST'],
    'pet_delete'            => ['pet_delete', 'POST'],

    // Appointments
    'appointments_list'     => ['appointments_list', 'GET'],
    'appointment_slots'     => ['appointment_slots', 'GET'],
    'appointment_book'      => ['appointment_book', 'POST'],
    'appointment_status'    => ['appointment_status', 'POST'],
    'appointment_reschedule'=> ['appointment_reschedule', 'POST'],
    'appointment_assign'    => ['appointment_assign', 'POST'],
    'walkin_create'         => ['walkin_create', 'POST'],

    // Medical
    'records_list'          => ['records_list', 'GET'],
    'record_save'           => ['record_save', 'POST'],
    'record_delete'         => ['record_delete', 'POST'],
    'record_history'        => ['record_history', 'GET'],
    'vaccinations_list'     => ['vaccinations_list', 'GET'],
    'vaccination_save'      => ['vaccination_save', 'POST'],
    'vaccination_delete'    => ['vaccination_delete', 'POST'],

    // Payments
    'payments_list'         => ['payments_list', 'GET'],
    'payment_options'       => ['payment_options', 'GET'],
    'pay_mpesa'             => ['pay_mpesa', 'POST'],
    'pay_card'              => ['pay_card', 'POST'],
    'payment_status'        => ['payment_status', 'GET'],
    'payment_record_cash'   => ['payment_record_cash', 'POST'],
    'payment_cancel'        => ['payment_cancel', 'POST'],

    // Community
    'forum_list'            => ['forum_list', 'GET'],
    'forum_get'             => ['forum_get', 'GET'],
    'forum_create'          => ['forum_create', 'POST'],
    'forum_reply'           => ['forum_reply', 'POST'],
    'forum_moderate'        => ['forum_moderate', 'POST'],
    'forum_delete'          => ['forum_delete', 'POST'],
    'forum_reply_delete'    => ['forum_reply_delete', 'POST'],
    'adoption_list'         => ['adoption_list', 'GET'],
    'adoption_save'         => ['adoption_save', 'POST'],
    'adoption_delete'       => ['adoption_delete', 'POST'],
    'adoption_request'      => ['adoption_request', 'POST'],
    'adoption_requests'     => ['adoption_requests', 'GET'],
    'adoption_decide'       => ['adoption_decide', 'POST'],
    'lostfound_list'        => ['lostfound_list', 'GET'],
    'lostfound_save'        => ['lostfound_save', 'POST'],
    'lostfound_resolve'     => ['lostfound_resolve', 'POST'],
    'lostfound_delete'      => ['lostfound_delete', 'POST'],

    // Admin & shared
    'dashboard'             => ['dashboard_stats', 'GET'],
    'vets_list'             => ['vets_list', 'GET'],
    'users_list'            => ['users_list', 'GET'],
    'user_save'             => ['user_save', 'POST'],
    'user_delete'           => ['user_delete', 'POST'],
    'services_list'         => ['services_list', 'GET'],
    'service_save'          => ['service_save', 'POST'],
    'service_delete'        => ['service_delete', 'POST'],
    'settings_get'          => ['settings_get', 'GET'],
    'settings_save'         => ['settings_save', 'POST'],
    'reports'               => ['reports', 'GET'],
    'report_export'         => ['report_export', 'GET'],
    'notifications'         => ['notifications_list', 'GET'],
    'notifications_read'    => ['notifications_read', 'POST'],

    // Files
    'upload'                => ['upload_file', 'POST'],
    'attachments'           => ['attachments_list', 'GET'],
    'attachment_delete'     => ['attachment_delete', 'POST'],

    // Vet schedules
    'schedule_get'          => ['schedule_get', 'GET'],
    'schedule_save'         => ['schedule_save', 'POST'],
    'time_off_add'          => ['time_off_add', 'POST'],
    'time_off_delete'       => ['time_off_delete', 'POST'],

    // Invoices & inventory
    'invoice_get'           => ['invoice_get', 'GET'],
    'invoice_item_add'      => ['invoice_item_add', 'POST'],
    'invoice_item_delete'   => ['invoice_item_delete', 'POST'],
    'invoice_item_discount' => ['invoice_item_discount', 'POST'],
    'products_list'         => ['products_list', 'GET'],
    'product_save'          => ['product_save', 'POST'],
    'product_delete'        => ['product_delete', 'POST'],
    'stock_adjust'          => ['stock_adjust', 'POST'],
    'stock_movements'       => ['stock_movements', 'GET'],

    // Maintenance
    'backup_download'       => ['backup_download', 'GET'],
    'backup_files'          => ['backup_files', 'GET'],
    'demo_data_load'        => ['demo_data_load', 'POST'],
    'demo_data_remove'      => ['demo_data_remove', 'POST'],
    'activity_list'         => ['activity_list', 'GET'],

    // Weight history, health card, enquiries, messaging
    'pet_weights'           => ['pet_weights', 'GET'],
    'weight_add'            => ['weight_add', 'POST'],
    'weight_delete'         => ['weight_delete', 'POST'],
    'pet_card_save'         => ['pet_card_save', 'POST'],
    'enquiry_create'        => ['enquiry_create', 'POST'],
    'enquiries_list'        => ['enquiries_list', 'GET'],
    'enquiry_get'           => ['enquiry_get', 'GET'],
    'enquiry_reply'         => ['enquiry_reply', 'POST'],
    'enquiry_status'        => ['enquiry_status', 'POST'],
    'messaging_get'         => ['messaging_get', 'GET'],
    'messaging_save'        => ['messaging_save', 'POST'],
    'reminders_run'         => ['reminders_run', 'POST'],
    'message_test'          => ['message_test', 'POST'],
];

try {
    start_session();
    $action = (string)($_GET['action'] ?? '');
    if (!isset(ROUTES[$action])) {
        fail('Unknown action', 404);
    }
    [$fn, $method] = ROUTES[$action];
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        fail("This action requires $method", 405);
    }
    if ($method === 'POST') {
        check_csrf();
    }
    $result = $fn();
    if ($method === 'POST' && isset(ACTIVITY_LABELS[$action])) {
        [$subject, $entityId] = activity_subject($action, $result);
        $uid = is_array($result) && isset($result['user']['id']) ? (int)$result['user']['id'] : null;
        log_activity($action, $subject, $entityId, $uid);
    }
    json_out(['ok' => true, 'data' => $result]);
} catch (ApiError $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('API error [' . ($action ?? '') . ']: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = env('APP_DEBUG') === 'true' ? $e->getMessage() : 'Something went wrong. Please try again.';
    json_out(['ok' => false, 'error' => $msg], 500);
}
