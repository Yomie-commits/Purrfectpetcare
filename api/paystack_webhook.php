<?php
/**
 * Paystack webhook. In your Paystack dashboard set the webhook URL to:
 * https://YOUR-DOMAIN/api/paystack_webhook.php
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/gateways.php';

$raw = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$secret = env('PAYSTACK_SECRET_KEY', '');
if (!$secret || !hash_equals(hash_hmac('sha512', $raw, $secret), $sig)) {
    http_response_code(401);
    exit;
}
$event = json_decode($raw, true);
if (($event['event'] ?? '') === 'charge.success' && !empty($event['data']['reference'])) {
    try {
        paystack_verify((string)$event['data']['reference']);
    } catch (Throwable $e) {
        error_log('Paystack webhook error: ' . $e->getMessage());
    }
}
http_response_code(200);
