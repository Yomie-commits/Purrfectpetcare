<?php
/** Printable itemised invoice for an appointment. */
require __DIR__ . '/api/lib/bootstrap.php';
require __DIR__ . '/api/lib/http.php';
require __DIR__ . '/api/lib/print.php';

$u = require_print_user();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$a = q_one('SELECT a.*, p.name AS pet_name, p.species, o.name AS owner_name, o.phone AS owner_phone, o.email AS owner_email, v.name AS vet_name, s.name AS service_name
            FROM appointments a JOIN pets p ON p.id = a.pet_id JOIN users o ON o.id = a.owner_id LEFT JOIN users v ON v.id = a.vet_id LEFT JOIN services s ON s.id = a.service_id
            WHERE a.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$a || (!is_staff($u) && (int)$a['owner_id'] !== $u['id'])) {
    http_response_code(404);
    exit('Invoice not found.');
}
$items = q_all('SELECT i.*, pr.unit FROM appointment_items i LEFT JOIN products pr ON pr.id = i.product_id WHERE i.appointment_id = ? ORDER BY i.id', [$a['id']]);
$payments = q_all("SELECT * FROM payments WHERE appointment_id = ? AND status = 'completed' ORDER BY paid_at", [$a['id']]);
$total = 0.0;
foreach ($items as $i) {
    $total += (float)$i['quantity'] * (float)$i['unit_price'] - (float)($i['discount'] ?? 0);
}
if (!$items && (float)$a['price'] > 0) {
    $items = [['description' => $a['service_name'] ?: 'Consultation', 'quantity' => 1, 'unit_price' => $a['price'], 'item_type' => 'service', 'unit' => null]];
    $total = (float)$a['price'];
}
$paid = array_sum(array_map(fn($p) => (float)$p['amount'], $payments));
$balance = max(0, round($total - $paid, 2));
$methods = ['mpesa' => 'M-Pesa', 'card' => 'Card', 'cash' => 'Cash'];
$number = 'INV-' . str_pad((string)$a['id'], 6, '0', STR_PAD_LEFT);

print_head('Invoice ' . $number);
?>
<div class="d-flex justify-content-between align-items-start mb-4">
  <div>
    <h2 class="h4 mb-1">Invoice</h2>
    <div class="text-muted small">No. <strong><?= $h($number) ?></strong> &middot; Visit <?= $h(date('j M Y', strtotime($a['appt_date']))) ?> <?= $h(substr($a['appt_time'], 0, 5)) ?></div>
  </div>
  <span class="badge fs-6 <?= $balance > 0 ? 'bg-warning text-dark' : 'bg-success' ?>"><?= $balance > 0 ? 'BALANCE DUE' : 'PAID' ?></span>
</div>
<div class="row mb-4">
  <div class="col-sm-6"><div class="small text-muted text-uppercase">Billed to</div><strong><?= $h($a['owner_name']) ?></strong><div><?= $h($a['owner_phone']) ?></div><div><?= substr((string)$a['owner_email'], -13) === '@walkin.local' ? '' : $h($a['owner_email']) ?></div></div>
  <div class="col-sm-6 text-sm-end"><div class="small text-muted text-uppercase">Patient</div><strong><?= $h($a['pet_name']) ?></strong> (<?= $h($a['species']) ?>)<div><?= $h($a['vet_name'] ? 'Seen by ' . $a['vet_name'] : '') ?></div></div>
</div>
<table class="table">
  <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($items as $i): $disc = (float)($i['discount'] ?? 0); $line = (float)$i['quantity'] * (float)$i['unit_price'] - $disc; ?>
    <tr><td><?= $h($i['description']) ?><?= $disc > 0 ? '<div class="small text-muted">Discount ' . $h(money($disc)) . '</div>' : '' ?></td><td class="text-end"><?= $h(rtrim(rtrim(number_format((float)$i['quantity'], 2), '0'), '.')) ?><?= $i['unit'] ? ' ' . $h($i['unit']) : '' ?></td>
      <td class="text-end"><?= $h(money((float)$i['unit_price'])) ?></td><td class="text-end"><?= $h(money($line)) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= $h(money($total)) ?></th></tr>
    <?php foreach ($payments as $p): ?>
      <tr class="text-muted"><td colspan="3" class="text-end small">Paid <?= $h(date('j M Y', strtotime($p['paid_at']))) ?> by <?= $h($methods[$p['method']] ?? $p['method']) ?> (<?= $h($p['reference']) ?>)</td><td class="text-end small">− <?= $h(money((float)$p['amount'])) ?></td></tr>
    <?php endforeach; ?>
    <tr><th colspan="3" class="text-end">Balance due</th><th class="text-end"><?= $h(money($balance)) ?></th></tr>
  </tfoot>
</table>
<p class="small text-muted">Pay by M-Pesa or card from your dashboard, or at the clinic. Thank you for trusting us with <?= $h($a['pet_name']) ?>'s care.</p>
<?php print_foot('app.html#appointments');
