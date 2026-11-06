<?php
/** Paystack redirects the customer here after the card checkout. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/gateways.php';

$ref = (string)($_GET['reference'] ?? $_GET['trxref'] ?? '');
$status = 'unknown';
if ($ref !== '' && paystack_mode() === 'live') {
    try {
        $p = paystack_verify($ref);
        $status = $p['status'] ?? 'unknown';
    } catch (Throwable $e) {
        error_log('Paystack return error: ' . $e->getMessage());
    }
}
header('Location: ../app.html#payments?result=' . urlencode($status));
