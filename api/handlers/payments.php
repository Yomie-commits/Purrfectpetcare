<?php
/** Payments: M-Pesa STK push, card (Paystack) and cash. */
defined('APP_LOADED') or exit;

function payment_select(): string
{
    return 'SELECT pay.*, u.name AS user_name, u.email AS user_email, r.name AS recorded_by_name,
                   a.appt_date, s.name AS service_name, p.name AS pet_name
            FROM payments pay
            JOIN users u ON u.id = pay.user_id
            LEFT JOIN users r ON r.id = pay.recorded_by
            LEFT JOIN appointments a ON a.id = pay.appointment_id
            LEFT JOIN services s ON s.id = a.service_id
            LEFT JOIN pets p ON p.id = a.pet_id';
}

function payment_for_user(int $id, array $u): array
{
    $p = q_one(payment_select() . ' WHERE pay.id = ?', [$id]);
    if (!$p || (!is_staff($u) && (int)$p['user_id'] !== $u['id'])) {
        fail('Payment not found', 404);
    }
    $p['amount'] = (float)$p['amount'];
    return $p;
}

function payments_list(): array
{
    $u = require_login();
    $sql = payment_select() . ' WHERE 1=1';
    $params = [];
    if (!is_staff($u)) {
        $sql .= ' AND pay.user_id = ?';
        $params[] = $u['id'];
    }
    if ($st = opt('status')) {
        $sql .= ' AND pay.status = ?';
        $params[] = v_enum($st, ['pending', 'completed', 'failed', 'cancelled'], 'status');
    }
    $sql .= ' ORDER BY pay.created_at DESC, pay.id DESC LIMIT 500';
    return array_map(function ($p) {
        $p['amount'] = (float)$p['amount'];
        return $p;
    }, q_all($sql, $params));
}

function payment_options(): array
{
    require_login();
    return [
        'mpesa' => ['enabled' => true, 'mode' => mpesa_mode()],
        'card' => ['enabled' => true, 'mode' => paystack_mode()],
    ];
}

/** Appointment that still has a balance to pay. */
function payable_appointment(array $u): array
{
    $a = appointment_for_user(req_id('appointment_id'), $u);
    if (in_array($a['status'], ['cancelled', 'no_show'], true)) {
        fail('This appointment was cancelled, so nothing is due');
    }
    if ($a['balance'] <= 0) {
        fail('This appointment is already fully paid');
    }
    return $a;
}

/** Online payments may be part payments: the amount defaults to the full balance. */
function payment_amount(array $a): float
{
    $raw = opt('amount');
    if ($raw === null) {
        return (float)$a['balance'];
    }
    $amount = v_money($raw);
    if ($amount < 1) {
        fail('Enter an amount of at least ' . money(1));
    }
    if ($amount > $a['balance'] + 0.001) {
        fail('Amount is more than the balance of ' . money($a['balance']));
    }
    return round($amount, 2);
}

function create_pending_payment(array $a, string $method, ?string $phone, ?int $recordedBy = null): array
{
    // Any older pending attempt for the same appointment is replaced by this one.
    q("UPDATE payments SET status = 'cancelled', failure_reason = 'Replaced by a new attempt' WHERE appointment_id = ? AND status = 'pending'", [$a['id']]);
    $data = [
        'user_id' => (int)$a['owner_id'],
        'appointment_id' => (int)$a['id'],
        'amount' => payment_amount($a),
        'method' => $method,
        'status' => 'pending',
        'reference' => new_payment_reference(),
        'phone' => $phone,
        'description' => trim(($a['service_name'] ?? 'Service') . ' - ' . $a['pet_name']),
        'recorded_by' => $recordedBy,
        'created_at' => now(),
    ];
    $data['id'] = insert('payments', $data);
    return $data;
}

function pay_mpesa(): array
{
    $u = require_login();
    $a = payable_appointment($u);
    $phone = mpesa_phone(req('phone', 'Phone number'));
    $p = create_pending_payment($a, 'mpesa', $phone, is_staff($u) ? $u['id'] : null);
    try {
        $checkout = mpesa_stk_push($phone, $p['amount'], $p['reference'], 'Pet care');
    } catch (ApiError $e) {
        mark_payment_failed($p['id'], $e->getMessage());
        throw $e;
    }
    q('UPDATE payments SET checkout_request_id = ? WHERE id = ?', [$checkout, $p['id']]);
    return [
        'payment' => payment_for_user($p['id'], $u),
        'mode' => mpesa_mode(),
        'message' => mpesa_mode() === 'simulate'
            ? 'Test mode: no real money is charged. The payment will confirm in a few seconds.'
            : 'Check your phone and enter your M-Pesa PIN to complete the payment.',
    ];
}

function pay_card(): array
{
    $u = require_login();
    $a = payable_appointment($u);
    $p = create_pending_payment($a, 'card', null, is_staff($u) ? $u['id'] : null);
    if (paystack_mode() === 'simulate') {
        mark_payment_completed($p['id'], 'SIMCARD' . strtoupper(bin2hex(random_bytes(4))));
        return [
            'payment' => payment_for_user($p['id'], $u),
            'mode' => 'simulate',
            'message' => 'Test mode: card payment approved without charging a real card.',
        ];
    }
    $email = q_val('SELECT email FROM users WHERE id = ?', [$a['owner_id']]);
    try {
        $url = paystack_initialize($p, $email);
    } catch (ApiError $e) {
        mark_payment_failed($p['id'], $e->getMessage());
        throw $e;
    }
    return ['payment' => payment_for_user($p['id'], $u), 'mode' => 'live', 'redirect' => $url];
}

function payment_status(): array
{
    $u = require_login();
    $p = payment_for_user(req_id(), $u);
    if ($p['status'] === 'pending') {
        $age = time() - strtotime($p['created_at']);
        if ($p['method'] === 'mpesa') {
            if (mpesa_mode() === 'simulate') {
                if ($age >= 5) {
                    mark_payment_completed((int)$p['id'], 'SIM' . strtoupper(bin2hex(random_bytes(4))));
                }
            } elseif ($p['checkout_request_id'] && $age >= 20) {
                // Fallback for when Safaricom's callback cannot reach the server.
                $r = mpesa_query($p['checkout_request_id']);
                if ($r['state'] === 'completed') {
                    mark_payment_completed((int)$p['id'], $p['provider_ref'] ?: $p['checkout_request_id']);
                } elseif ($r['state'] === 'failed') {
                    mark_payment_failed((int)$p['id'], $r['reason']);
                } elseif ($age > 180) {
                    mark_payment_failed((int)$p['id'], 'No response from M-Pesa. Please try again.');
                }
            }
        } elseif ($p['method'] === 'card' && paystack_mode() === 'live') {
            paystack_verify($p['reference']);
        }
        $p = payment_for_user((int)$p['id'], $u);
    }
    return $p;
}

function payment_record_cash(): array
{
    $u = require_perm('billing');
    $a = payable_appointment($u);
    $amount = v_money(opt('amount') ?? (string)$a['balance']);
    if ($amount > $a['balance'] + 0.001) {
        fail('Amount is more than the balance of ' . money($a['balance']));
    }
    $method = v_enum(opt('method', 'cash'), ['cash', 'mpesa', 'card'], 'method');
    $id = insert('payments', [
        'user_id' => (int)$a['owner_id'],
        'appointment_id' => (int)$a['id'],
        'amount' => $amount,
        'method' => $method,
        'status' => 'pending',
        'reference' => new_payment_reference(),
        'description' => trim(($a['service_name'] ?? 'Service') . ' - ' . $a['pet_name']) . ' (paid at clinic)',
        'recorded_by' => $u['id'],
        'created_at' => now(),
    ]);
    mark_payment_completed($id, opt('provider_ref'));
    return payment_for_user($id, $u);
}

function payment_cancel(): array
{
    $u = require_login();
    $p = payment_for_user(req_id(), $u);
    if ($p['status'] !== 'pending') {
        fail('Only pending payments can be cancelled');
    }
    q("UPDATE payments SET status = 'cancelled', failure_reason = 'Cancelled by user' WHERE id = ?", [$p['id']]);
    return payment_for_user((int)$p['id'], $u);
}
