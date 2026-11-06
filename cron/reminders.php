<?php
/**
 * Daily reminders (optional). Where the host has cron, run it every day at 7am:
 *   php /home/USERNAME/public_html/cron/reminders.php
 * On hosts without cron (e.g. InfinityFree) the same reminders run automatically
 * on the first staff or client visit each day, so this file is not needed there.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__) . '/api/lib/bootstrap.php';
require dirname(__DIR__) . '/api/lib/http.php';
require dirname(__DIR__) . '/api/lib/messaging.php';

$r = run_reminders();
echo date('c') . " - treatment reminders: {$r['treatments']}, appointment reminders: {$r['appointments']}\n";
