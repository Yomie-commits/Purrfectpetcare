<?php
/** Printable vaccination certificate or health summary for one pet. */
require __DIR__ . '/api/lib/bootstrap.php';
require __DIR__ . '/api/lib/http.php';
require __DIR__ . '/api/lib/print.php';

$u = require_print_user();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$fd = fn($d) => $d ? date('j M Y', strtotime($d)) : '—';
$pet = q_one('SELECT p.*, o.name AS owner_name, o.phone AS owner_phone, o.email AS owner_email, o.address AS owner_address FROM pets p JOIN users o ON o.id = p.owner_id WHERE p.id = ?', [(int)($_GET['pet'] ?? 0)]);
if (!$pet || (!is_staff($u) && (int)$pet['owner_id'] !== $u['id'])) {
    http_response_code(404);
    exit('Pet not found.');
}
$type = ($_GET['type'] ?? 'vaccination') === 'health' ? 'health' : 'vaccination';
if ($type === 'health' && is_staff($u) && !can($u, 'medical.view')) {
    http_response_code(403);
    exit('Health summaries are only available to vets and vet assistants.');
}
$photo = q_val("SELECT MAX(id) FROM attachments WHERE entity_type = 'pet' AND entity_id = ? AND kind = 'photo'", [$pet['id']]);
$vacc = q_all('SELECT v.*, s.name AS vet_name FROM vaccinations v LEFT JOIN users s ON s.id = v.vet_id WHERE v.pet_id = ? ORDER BY v.date_given DESC', [$pet['id']]);
$records = $type === 'health' ? q_all('SELECT r.*, v.name AS vet_name FROM health_records r LEFT JOIN users v ON v.id = r.vet_id WHERE r.pet_id = ? ORDER BY r.record_date DESC, r.id DESC', [$pet['id']]) : [];
$title = $type === 'health' ? 'Health summary' : 'Vaccination certificate';
$number = ($type === 'health' ? 'HS' : 'VC') . '-' . str_pad((string)$pet['id'], 5, '0', STR_PAD_LEFT) . '-' . date('Ymd');
$age = '';
if ($pet['date_of_birth']) {
    $d = (new DateTime($pet['date_of_birth']))->diff(new DateTime());
    $age = $d->y ? $d->y . ' yr' . ($d->y > 1 ? 's' : '') : $d->m . ' mo';
}

print_head($title . ' - ' . $pet['name']);
?>
<div class="d-flex justify-content-between align-items-start mb-4">
  <div>
    <h2 class="h4 mb-1"><?= $h($title) ?></h2>
    <div class="text-muted small">Certificate no. <strong><?= $h($number) ?></strong> &middot; Issued <?= $h(date('j M Y')) ?></div>
  </div>
  <div class="seal"><?= $h(strtoupper(setting('clinic_name', 'Purrfect Pet Care'))) ?><br>VERIFIED</div>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-7 d-flex gap-3">
    <?php if ($photo): ?><img class="pet-photo" src="file.php?id=<?= (int)$photo ?>" alt=""><?php endif; ?>
    <table class="table table-sm table-borderless mb-0">
      <tr><th>Pet</th><td><strong><?= $h($pet['name']) ?></strong></td></tr>
      <tr><th>Species / breed</th><td><?= $h($pet['species']) ?><?= $pet['breed'] ? ' / ' . $h($pet['breed']) : '' ?></td></tr>
      <tr><th>Sex / age</th><td><?= $h($pet['sex'] ?: '—') ?><?= $age ? ' / ' . $h($age) : '' ?><?= $pet['date_of_birth'] ? ' (born ' . $h($fd($pet['date_of_birth'])) . ')' : '' ?></td></tr>
      <tr><th>Colour</th><td><?= $h($pet['color'] ?: '—') ?></td></tr>
      <tr><th>Microchip</th><td><?= $h($pet['microchip'] ?: '—') ?></td></tr>
      <?php if ($pet['weight_kg']): ?><tr><th>Weight</th><td><?= $h((float)$pet['weight_kg']) ?> kg</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="col-md-5">
    <div class="small text-muted text-uppercase">Owner</div>
    <div><strong><?= $h($pet['owner_name']) ?></strong></div>
    <div><?= $h($pet['owner_phone']) ?></div>
    <div><?= substr((string)$pet['owner_email'], -13) === '@walkin.local' ? '' : $h($pet['owner_email']) ?></div>
    <div><?= $h($pet['owner_address']) ?></div>
  </div>
</div>

<?php
$groups = ['vaccine' => 'Vaccinations', 'deworming' => 'Deworming', 'flea_tick' => 'Flea &amp; tick treatment'];
foreach ($groups as $kind => $label):
    $rows = array_values(array_filter($vacc, fn($v) => ($v['kind'] ?? 'vaccine') === $kind));
    if (!$rows && $kind !== 'vaccine') continue;
?>
<h3 class="h6 text-uppercase text-muted"><?= $label ?></h3>
<table class="table table-sm mb-4">
  <thead><tr><th><?= $kind === 'vaccine' ? 'Vaccine' : 'Product' ?></th><th>Date given</th><th>Valid until / next due</th><th>Batch no.</th><th>Given by</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="5" class="text-muted">None recorded.</td></tr><?php endif; ?>
  <?php foreach ($rows as $v): ?>
    <tr><td><?= $h($v['vaccine']) ?></td><td><?= $h($fd($v['date_given'])) ?></td>
      <td><?= $h($fd($v['next_due'])) ?><?= ($v['next_due'] && $v['next_due'] < today()) ? ' <span class="badge bg-danger">overdue</span>' : '' ?></td>
      <td><?= $h($v['batch_no'] ?: '—') ?></td><td><?= $h($v['vet_name'] ?: '—') ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>
<?php if (!empty($pet['allergies']) || !empty($pet['conditions'])): ?>
<div class="alert alert-danger py-2 small"><?= !empty($pet['allergies']) ? '<strong>Allergies:</strong> ' . $h($pet['allergies']) . ' ' : '' ?><?= !empty($pet['conditions']) ? '<strong>Conditions:</strong> ' . $h($pet['conditions']) : '' ?></div>
<?php endif; ?>

<?php if ($type === 'health'): ?>
<h3 class="h6 text-uppercase text-muted">Medical history</h3>
<?php if ($pet['notes']): ?><p class="small"><strong>Notes / allergies:</strong> <?= $h($pet['notes']) ?></p><?php endif; ?>
<table class="table table-sm mb-4">
  <thead><tr><th>Date</th><th>Record</th><th>Findings & treatment</th><th>Vet</th></tr></thead>
  <tbody>
  <?php if (!$records): ?><tr><td colspan="4" class="text-muted">No health records yet.</td></tr><?php endif; ?>
  <?php foreach ($records as $r): ?>
    <tr><td class="text-nowrap"><?= $h($fd($r['record_date'])) ?></td><td><strong><?= $h($r['title']) ?></strong><div class="small text-muted text-capitalize"><?= $h($r['record_type']) ?></div></td>
      <td class="small"><?= $r['diagnosis'] ? '<div>' . $h($r['diagnosis']) . '</div>' : '' ?><?= $r['treatment'] ? '<div><em>Treatment:</em> ' . $h($r['treatment']) . '</div>' : '' ?><?= $r['prescription'] ? '<div><em>Prescription:</em> ' . $h($r['prescription']) . '</div>' : '' ?></td>
      <td class="small"><?= $h($r['vet_name'] ?: '—') ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-end mt-5">
  <div class="small text-muted">This document was generated from the clinic's records on <?= $h(date('j M Y, H:i')) ?>.</div>
  <div class="sig">Veterinarian's signature &amp; stamp</div>
</div>
<?php print_foot('app.html#pets');
