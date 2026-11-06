<?php
/**
 * Safaricom Daraja STK Push callback.
 * Set MPESA_CALLBACK_URL in .env to: https://YOUR-DOMAIN/api/mpesa_callback.php?key=YOUR_SECRET
 * and MPESA_CALLBACK_SECRET=YOUR_SECRET so random requests are ignored.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/gateways.php';

header('Content-Type: application/json');
$ack = json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

$secret = env('MPESA_CALLBACK_SECRET');
if ($secret && !hash_equals($secret, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit(json_encode(['ResultCode' => 1, 'ResultDesc' => 'Forbidden']));
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
$cb = $data['Body']['stkCallback'] ?? null;
if (!$cb || empty($cb['CheckoutRequestID'])) {
    exit($ack);
}

try {
    $p = q_one('SELECT * FROM payments WHERE checkout_request_id = ?', [$cb['CheckoutRequestID']]);
    if (!$p) {
        error_log('M-Pesa callback for unknown CheckoutRequestID ' . $cb['CheckoutRequestID']);
        exit($ack);
    }
    if ((string)($cb['ResultCode'] ?? '') === '0') {
        $meta = [];
        foreach ($cb['CallbackMetadata']['Item'] ?? [] as $item) {
            $meta[$item['Name']] = $item['Value'] ?? null;
        }
        $paid = (float)($meta['Amount'] ?? 0);
        if ($paid + 0.001 < floor((float)$p['amount'])) {
            mark_payment_failed((int)$p['id'], 'Amount paid (' . $paid . ') is less than amount due');
        } else {
            mark_payment_completed((int)$p['id'], (string)($meta['MpesaReceiptNumber'] ?? $cb['CheckoutRequestID']));
        }
    } else {
        mark_payment_failed((int)$p['id'], (string)($cb['ResultDesc'] ?? 'Payment not completed'));
    }
} catch (Throwable $e) {
    error_log('M-Pesa callback error: ' . $e->getMessage());
}
echo $ack;
