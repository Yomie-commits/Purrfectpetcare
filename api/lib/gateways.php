<?php
/**
 * Payment gateways: M-Pesa (Safaricom Daraja STK Push) and Paystack (cards).
 *
 * Each gateway has three modes, set in .env:
 *   simulate   - no money moves; payments auto-complete (for demos/testing)
 *   sandbox    - provider test environment with test credentials
 *   production - live payments
 */
defined('APP_LOADED') or exit;

function mpesa_mode(): string
{
    $m = env('MPESA_ENV', 'simulate');
    return in_array($m, ['sandbox', 'production'], true) ? $m : 'simulate';
}

function paystack_mode(): string
{
    return env('PAYSTACK_SECRET_KEY') ? 'live' : 'simulate';
}

function new_payment_reference(): string
{
    return 'PPC' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
}

/** Normalise Kenyan numbers to 2547XXXXXXXX / 2541XXXXXXXX. */
function mpesa_phone(string $phone): string
{
    $p = preg_replace('/\D/', '', $phone);
    if (strlen($p) === 10 && $p[0] === '0') {
        $p = '254' . substr($p, 1);
    } elseif (strlen($p) === 9 && ($p[0] === '7' || $p[0] === '1')) {
        $p = '254' . $p;
    }
    if (!preg_match('/^254[17]\d{8}$/', $p)) {
        fail('Enter a valid Safaricom number, e.g. 0712345678');
    }
    return $p;
}

function http_json(string $method, string $url, array $headers, ?array $body = null): array
{
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json'], $headers);
    if ($body !== null) {
        $h[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        error_log("Gateway request failed: $url - $err");
        fail('Could not reach the payment provider. Please try again.', 502);
    }
    $json = json_decode($raw, true);
    return ['status' => $code, 'body' => is_array($json) ? $json : ['raw' => $raw]];
}

// ---------------------------------------------------------------------------
// M-Pesa Daraja
// ---------------------------------------------------------------------------
function mpesa_base(): string
{
    return mpesa_mode() === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function mpesa_token(): string
{
    $key = env('MPESA_CONSUMER_KEY');
    $secret = env('MPESA_CONSUMER_SECRET');
    if (!$key || !$secret) {
        fail('M-Pesa is not configured. Add MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET to .env', 500);
    }
    $r = http_json('GET', mpesa_base() . '/oauth/v1/generate?grant_type=client_credentials', [
        'Authorization: Basic ' . base64_encode("$key:$secret"),
    ]);
    if (empty($r['body']['access_token'])) {
        error_log('M-Pesa token error: ' . json_encode($r));
        fail('M-Pesa authentication failed. Check your Daraja credentials.', 502);
    }
    return $r['body']['access_token'];
}

function mpesa_password(string $timestamp): string
{
    return base64_encode(env('MPESA_SHORTCODE', '174379') . env('MPESA_PASSKEY', '') . $timestamp);
}

/** Start an STK push. Returns the CheckoutRequestID. */
function mpesa_stk_push(string $phone, float $amount, string $reference, string $description): string
{
    if (mpesa_mode() === 'simulate') {
        return 'SIM_' . $reference;
    }
    $shortcode = env('MPESA_SHORTCODE', '174379');
    $ts = date('YmdHis');
    $type = env('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline');
    $partyB = $type === 'CustomerBuyGoodsOnline' ? env('MPESA_TILL_NUMBER', $shortcode) : $shortcode;
    $r = http_json('POST', mpesa_base() . '/mpesa/stkpush/v1/processrequest', [
        'Authorization: Bearer ' . mpesa_token(),
    ], [
        'BusinessShortCode' => $shortcode,
        'Password' => mpesa_password($ts),
        'Timestamp' => $ts,
        'TransactionType' => $type,
        'Amount' => (int)ceil($amount),
        'PartyA' => $phone,
        'PartyB' => $partyB,
        'PhoneNumber' => $phone,
        'CallBackURL' => env('MPESA_CALLBACK_URL', app_url() . '/api/mpesa_callback.php'),
        'AccountReference' => substr($reference, 0, 12),
        'TransactionDesc' => substr($description ?: 'Pet care', 0, 13),
    ]);
    $b = $r['body'];
    if (($b['ResponseCode'] ?? '') !== '0' || empty($b['CheckoutRequestID'])) {
        error_log('STK push error: ' . json_encode($b));
        fail($b['errorMessage'] ?? $b['CustomerMessage'] ?? 'M-Pesa request failed. Please try again.', 502);
    }
    return $b['CheckoutRequestID'];
}

/**
 * Ask Safaricom for the status of an STK push (used when the callback
 * has not arrived yet). Returns ['state' => pending|completed|failed, ...].
 */
function mpesa_query(string $checkoutId): array
{
    $shortcode = env('MPESA_SHORTCODE', '174379');
    $ts = date('YmdHis');
    $r = http_json('POST', mpesa_base() . '/mpesa/stkpushquery/v1/query', [
        'Authorization: Bearer ' . mpesa_token(),
    ], [
        'BusinessShortCode' => $shortcode,
        'Password' => mpesa_password($ts),
        'Timestamp' => $ts,
        'CheckoutRequestID' => $checkoutId,
    ]);
    $b = $r['body'];
    if (!isset($b['ResultCode'])) {
        return ['state' => 'pending']; // still processing
    }
    if ((string)$b['ResultCode'] === '0') {
        return ['state' => 'completed', 'receipt' => null];
    }
    return ['state' => 'failed', 'reason' => $b['ResultDesc'] ?? 'Payment was not completed'];
}

// ---------------------------------------------------------------------------
// Paystack (card payments; also supports M-Pesa via Paystack in Kenya)
// ---------------------------------------------------------------------------
function paystack_initialize(array $payment, string $email): string
{
    $r = http_json('POST', 'https://api.paystack.co/transaction/initialize', [
        'Authorization: Bearer ' . env('PAYSTACK_SECRET_KEY'),
    ], [
        'email' => $email,
        'amount' => (int)round((float)$payment['amount'] * 100),
        'currency' => env('PAYSTACK_CURRENCY', 'KES'),
        'reference' => $payment['reference'],
        'callback_url' => app_url() . '/api/paystack_return.php',
        'channels' => ['card'],
        'metadata' => ['payment_id' => (int)$payment['id']],
    ]);
    $b = $r['body'];
    if (empty($b['status']) || empty($b['data']['authorization_url'])) {
        error_log('Paystack init error: ' . json_encode($b));
        fail($b['message'] ?? 'Could not start card payment', 502);
    }
    return $b['data']['authorization_url'];
}

/** Verify a Paystack transaction and update our payment record. */
function paystack_verify(string $reference): ?array
{
    $p = q_one('SELECT * FROM payments WHERE reference = ?', [$reference]);
    if (!$p) {
        return null;
    }
    if ($p['status'] !== 'pending') {
        return $p;
    }
    $r = http_json('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference), [
        'Authorization: Bearer ' . env('PAYSTACK_SECRET_KEY'),
    ]);
    $d = $r['body']['data'] ?? [];
    $status = $d['status'] ?? '';
    if ($status === 'success') {
        $paid = ((int)($d['amount'] ?? 0)) / 100;
        if (abs($paid - (float)$p['amount']) > 0.01) {
            mark_payment_failed((int)$p['id'], 'Amount mismatch');
        } else {
            mark_payment_completed((int)$p['id'], (string)($d['id'] ?? $reference));
        }
    } elseif (in_array($status, ['failed', 'abandoned', 'reversed'], true)) {
        mark_payment_failed((int)$p['id'], 'Card payment ' . $status);
    }
    return q_one('SELECT * FROM payments WHERE id = ?', [$p['id']]);
}

// ---------------------------------------------------------------------------
// Shared state transitions
// ---------------------------------------------------------------------------
function mark_payment_completed(int $id, ?string $providerRef): void
{
    $p = q_one('SELECT * FROM payments WHERE id = ?', [$id]);
    if (!$p || $p['status'] === 'completed') {
        return;
    }
    q("UPDATE payments SET status = 'completed', provider_ref = ?, paid_at = ?, failure_reason = NULL WHERE id = ?", [
        $providerRef, now(), $id,
    ]);
    notify((int)$p['user_id'], 'Payment received', money((float)$p['amount']) . ' received. Thank you!', 'payments');
    $u = q_one('SELECT email, name FROM users WHERE id = ?', [$p['user_id']]);
    if ($u) {
        send_mail($u['email'], 'Payment receipt ' . $p['reference'],
            "Hi {$u['name']},\n\nWe received your payment of " . money((float)$p['amount']) .
            " (ref {$p['reference']}).\nView your receipt: " . app_url() . '/receipt.php?ref=' . urlencode($p['reference']) .
            "\n\n" . setting('clinic_name', 'Purrfect Pet Care'));
    }
}

function mark_payment_failed(int $id, string $reason): void
{
    q("UPDATE payments SET status = 'failed', failure_reason = ? WHERE id = ? AND status = 'pending'", [
        mb_substr($reason, 0, 255), $id,
    ]);
}
