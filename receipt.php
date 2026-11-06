<?php
/** Printable payment receipt. Only the payer or clinic staff can view it. */
require __DIR__ . '/api/lib/bootstrap.php';
require __DIR__ . '/api/lib/http.php';

start_session();
$u = current_user();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
if (!$u) {
    header('Location: index.html?login=1');
    exit;
}
$p = q_one('SELECT pay.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, a.appt_date, a.appt_time,
                   s.name AS service_name, pt.name AS pet_name, v.name AS vet_name
            FROM payments pay JOIN users u ON u.id = pay.user_id
            LEFT JOIN appointments a ON a.id = pay.appointment_id
            LEFT JOIN services s ON s.id = a.service_id
            LEFT JOIN pets pt ON pt.id = a.pet_id
            LEFT JOIN users v ON v.id = a.vet_id
            WHERE pay.reference = ?', [(string)($_GET['ref'] ?? '')]);
if (!$p || (!is_staff($u) && (int)$p['user_id'] !== $u['id'])) {
    http_response_code(404);
    exit('Receipt not found.');
}
$methods = ['mpesa' => 'M-Pesa', 'card' => 'Card', 'cash' => 'Cash'];
$clinic = setting('clinic_name', 'Purrfect Pet Care');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="assets/img/paw-favicon.png?v=1">
<title>Receipt <?= $h($p['reference']) ?> - <?= $h($clinic) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
 body{background:#FFF5E1;font-family:'Poppins',system-ui,sans-serif}
 .receipt{max-width:720px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.08)}
 .head{background:linear-gradient(135deg,#FF7300,#3AAFA9);color:#fff;padding:28px}
 .head img{height:48px;background:#fff;border-radius:50%;padding:4px}
 .head h1{color:#fff}
 .stamp{display:inline-block;border:3px solid #198754;color:#198754;font-weight:700;padding:4px 14px;border-radius:6px;transform:rotate(-6deg)}
 .stamp.pending{border-color:#fd7e14;color:#fd7e14}.stamp.failed,.stamp.cancelled{border-color:#dc3545;color:#dc3545}
 @media print{body{background:#fff}.no-print{display:none!important}.receipt{box-shadow:none;margin:0}}
</style>
</head>
<body>
<div class="receipt">
  <div class="head d-flex align-items-center gap-3">
    <img src="assets/img/paws1.png" alt="">
    <div>
      <h1 class="h4 mb-0"><?= $h($clinic) ?></h1>
      <small><?= $h(setting('clinic_address')) ?> &middot; <?= $h(setting('clinic_phone')) ?> &middot; <?= $h(setting('clinic_email')) ?></small>
    </div>
  </div>
  <div class="p-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
      <div>
        <h2 class="h5 mb-1">Payment receipt</h2>
        <div class="text-muted">Ref: <strong><?= $h($p['reference']) ?></strong></div>
        <div class="text-muted">Date: <?= $h(date('j M Y, H:i', strtotime($p['paid_at'] ?: $p['created_at']))) ?></div>
      </div>
      <span class="stamp <?= $h($p['status']) ?>"><?= $h(strtoupper($p['status'] === 'completed' ? 'PAID' : $p['status'])) ?></span>
    </div>
    <div class="row mb-4">
      <div class="col-sm-6">
        <div class="text-muted small text-uppercase">Billed to</div>
        <div><strong><?= $h($p['user_name']) ?></strong></div>
        <div><?= $h($p['user_email']) ?></div>
        <div><?= $h($p['user_phone']) ?></div>
      </div>
      <div class="col-sm-6 text-sm-end">
        <div class="text-muted small text-uppercase">Payment method</div>
        <div><strong><?= $h($methods[$p['method']] ?? $p['method']) ?></strong></div>
        <?php if ($p['provider_ref']): ?><div>Transaction: <?= $h($p['provider_ref']) ?></div><?php endif; ?>
        <?php if ($p['phone']): ?><div>Phone: <?= $h($p['phone']) ?></div><?php endif; ?>
      </div>
    </div>
    <table class="table">
      <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
      <tbody>
        <tr>
          <td>
            <?= $h($p['service_name'] ?: $p['description']) ?>
            <?php if ($p['pet_name']): ?><div class="small text-muted">Pet: <?= $h($p['pet_name']) ?><?= $p['appt_date'] ? ' &middot; Visit: ' . $h(date('j M Y', strtotime($p['appt_date']))) : '' ?><?= $p['vet_name'] ? ' &middot; ' . $h($p['vet_name']) : '' ?></div><?php endif; ?>
          </td>
          <td class="text-end"><?= $h(money((float)$p['amount'])) ?></td>
        </tr>
      </tbody>
      <tfoot><tr><th>Total</th><th class="text-end"><?= $h(money((float)$p['amount'])) ?></th></tr></tfoot>
    </table>
    <p class="text-muted small mb-0">Thank you for trusting us with your pet's care.</p>
    <div class="no-print mt-4 d-flex gap-2">
      <button class="btn btn-dark" onclick="window.print()">Print / Save as PDF</button>
      <a class="btn btn-outline-secondary" href="app.html#payments">Back to dashboard</a>
    </div>
  </div>
</div>
</body>
</html>
