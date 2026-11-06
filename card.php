<?php
/**
 * Public digital health card, reached by scanning a pet's QR code: card.php?t=TOKEN
 * Shows only what helps someone who finds the pet or a vet seeing it for the first time:
 * photo, name, species, vaccine status, allergies and a phone number to call.
 * The owner turns it on or off (and can make a new link) from the pet's profile.
 */
require __DIR__ . '/api/lib/bootstrap.php';
require __DIR__ . '/api/lib/http.php';
if (!function_exists('upload_dir')) {
    require_once __DIR__ . '/api/handlers/features.php';
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$token = (string)($_GET['t'] ?? '');
$pet = preg_match('/^[a-f0-9]{24}$/', $token)
    ? q_one("SELECT p.*, o.name AS owner_name, o.phone AS owner_phone FROM pets p JOIN users o ON o.id = p.owner_id WHERE p.card_token = ?", [$token])
    : null;
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$clinic = setting('clinic_name', 'Purrfect Pet Care');
$clinicPhone = setting('clinic_phone', '');

if (!$pet) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Card not found</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">'
        . '<div class="container py-5 text-center" style="max-width:480px"><h1 class="h4">This health card is not active</h1><p class="text-muted">The owner may have turned it off or made a new QR code.</p>'
        . ($clinicPhone ? '<p>If you found a pet, please call ' . $h($clinic) . ': <a href="tel:' . $h(preg_replace('/\s/', '', $clinicPhone)) . '">' . $h($clinicPhone) . '</a></p>' : '')
        . '</div></body></html>';
    exit;
}

$photoId = q_val("SELECT MAX(id) FROM attachments WHERE entity_type = 'pet' AND entity_id = ? AND kind = 'photo'", [$pet['id']]);

// The pet's photo, served only through its card link.
if (isset($_GET['photo'])) {
    $a = $photoId ? q_one('SELECT * FROM attachments WHERE id = ?', [$photoId]) : null;
    $path = $a ? upload_dir() . '/' . $a['stored_name'] : '';
    if (!$a || !is_file($path) || strpos($a['mime'], 'image/') !== 0) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $a['mime']);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=3600');
    readfile($path);
    exit;
}

$showOwner = (int)$pet['card_show_phone'] === 1 && $pet['owner_phone'];
$callName = $showOwner ? $pet['owner_name'] : $clinic;
$callPhone = $showOwner ? $pet['owner_phone'] : $clinicPhone;
$tel = preg_replace('/[^\d+]/', '', (string)$callPhone);

// Latest dose of each vaccine / treatment
$vacc = q_all("SELECT v.vaccine, v.kind, v.date_given, v.next_due FROM vaccinations v
               WHERE v.pet_id = ? AND NOT EXISTS (SELECT 1 FROM vaccinations v2 WHERE v2.pet_id = v.pet_id AND v2.vaccine = v.vaccine AND v2.date_given > v.date_given)
               ORDER BY v.date_given DESC", [$pet['id']]);
$today = date('Y-m-d');
$status = function ($v) use ($today) {
    if (!$v['next_due']) {
        return ['Done', 'secondary'];
    }
    if ($v['next_due'] < $today) {
        return ['Overdue', 'danger'];
    }
    return [$v['next_due'] <= date('Y-m-d', strtotime('+30 days')) ? 'Due soon' : 'Up to date', $v['next_due'] <= date('Y-m-d', strtotime('+30 days')) ? 'warning' : 'success'];
};
$kindLabel = ['vaccine' => 'Vaccine', 'deworming' => 'Deworming', 'flea_tick' => 'Flea & tick'];
$overdue = count(array_filter($vacc, fn($v) => ($v['kind'] ?? 'vaccine') === 'vaccine' && $v['next_due'] && $v['next_due'] < $today));
$hasVaccine = count(array_filter($vacc, fn($v) => ($v['kind'] ?? 'vaccine') === 'vaccine')) > 0;
$age = '';
if ($pet['date_of_birth']) {
    $d = (new DateTime($pet['date_of_birth']))->diff(new DateTime());
    $age = $d->y ? $d->y . ' yr' . ($d->y > 1 ? 's' : '') : $d->m . ' mo';
}
$url = app_url() . '/card.php?t=' . $token;
$photoUrl = $photoId ? 'card.php?t=' . $token . '&photo=1' : 'assets/img/' . (['Dog' => 'dogicon.png', 'Cat' => 'caticon.png', 'Bird' => 'birdicon.webp'][$pet['species']] ?? 'paws1.png');
$print = isset($_GET['print']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" href="assets/img/paw-favicon.png?v=1">
<title><?= $h($pet['name']) ?> · Health card · <?= $h($clinic) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #FFF5E1; font-family: Poppins, system-ui, sans-serif; }
.card-wrap { max-width: 460px; margin: 24px auto; background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
.top { background: linear-gradient(135deg, #FF7300, #3AAFA9); color: #fff; padding: 20px; text-align: center; }
.top h1 { color: #fff; } h2 { color: #222; }
.photo { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 4px solid #fff; background: #fff; }
.alert-box { background: #fdecea; color: #8a1c13; border-radius: 10px; padding: 10px 12px; font-size: .9rem; }
.call { font-size: 1.1rem; }
.tag { width: 85.6mm; height: 54mm; border: 1px dashed #bbb; border-radius: 4mm; display: flex; align-items: center; gap: 4mm; padding: 4mm; background: #fff; margin: 0 auto 6mm; }
.tag svg { width: 40mm; height: 40mm; flex: none; }
@media print { body { background: #fff; } .no-print { display: none !important; } .card-wrap { box-shadow: none; margin: 0 auto; } }
</style>
</head>
<body>
<?php if ($print): ?>
<div class="container py-4">
  <p class="no-print text-center small text-muted">Print this page, cut out the tags and laminate one for the collar. Each tag opens <?= $h($pet['name']) ?>'s health card.</p>
  <?php for ($i = 0; $i < 2; $i++): ?>
  <div class="tag">
    <div class="qr"></div>
    <div>
      <div class="fw-bold fs-5"><?= $h($pet['name']) ?></div>
      <div class="small">If I'm lost, scan me or call</div>
      <div class="fw-semibold"><?= $h($callPhone) ?></div>
      <div class="small text-muted mt-1"><?= $h($clinic) ?></div>
    </div>
  </div>
  <?php endfor; ?>
  <div class="no-print text-center"><button class="btn btn-dark" onclick="window.print()">Print</button></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
  (function () {
    var qr = qrcode(0, 'M'); qr.addData(<?= json_encode($url) ?>); qr.make();
    var svg = qr.createSvgTag({ cellSize: 4, margin: 1, scalable: true });
    document.querySelectorAll('.qr').forEach(function (el) { el.outerHTML = svg; });
  })();
</script>
<?php else: ?>
<div class="card-wrap">
  <div class="top">
    <img class="photo mb-2" src="<?= $h($photoUrl) ?>" alt="<?= $h($pet['name']) ?>">
    <h1 class="h3 mb-0"><?= $h($pet['name']) ?></h1>
    <div class="small opacity-75"><?= $h(implode(' · ', array_filter([$pet['species'], $pet['breed'], $pet['sex'], $age, $pet['color']]))) ?></div>
    <?php if ($pet['status'] !== 'active'): ?><span class="badge bg-light text-dark mt-2"><?= $h(ucfirst($pet['status'])) ?></span><?php endif; ?>
  </div>
  <div class="p-4">
    <?php if ($callPhone): ?>
    <div class="text-center mb-4">
      <div class="small text-muted">Found <?= $h($pet['name']) ?>? Please call <?= $h($showOwner ? 'the owner' : 'the clinic') ?></div>
      <a class="btn btn-success btn-lg w-100 mt-2 call" href="tel:<?= $h($tel) ?>">📞 <?= $h($callName) ?> · <?= $h($callPhone) ?></a>
    </div>
    <?php endif; ?>
    <?php if ($pet['allergies'] || $pet['conditions']): ?>
    <div class="alert-box mb-3">
      <?php if ($pet['allergies']): ?><div><strong>⚠ Allergies:</strong> <?= $h($pet['allergies']) ?></div><?php endif; ?>
      <?php if ($pet['conditions']): ?><div><strong>Conditions:</strong> <?= $h($pet['conditions']) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($pet['microchip']): ?><div class="small mb-3"><strong>Microchip:</strong> <?= $h($pet['microchip']) ?></div><?php endif; ?>
    <h2 class="h6 d-flex justify-content-between align-items-center">Vaccines &amp; treatments
      <?php if ($hasVaccine): ?><span class="badge bg-<?= $overdue ? 'danger' : 'success' ?>"><?= $overdue ? 'Vaccine overdue' : 'Vaccines up to date' ?></span><?php endif; ?></h2>
    <?php if ($vacc): ?>
    <table class="table table-sm small mb-3">
      <thead><tr><th>Name</th><th>Given</th><th>Next due</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($vacc as $v): [$label, $cls] = $status($v); ?>
        <tr><td><?= $h($v['vaccine']) ?><div class="text-muted" style="font-size:.75rem"><?= $h($kindLabel[$v['kind'] ?? 'vaccine'] ?? 'Vaccine') ?></div></td>
          <td><?= $h(date('j M Y', strtotime($v['date_given']))) ?></td>
          <td><?= $v['next_due'] ? $h(date('j M Y', strtotime($v['next_due']))) : '—' ?></td>
          <td><span class="badge bg-<?= $cls ?><?= $cls === 'warning' ? ' text-dark' : '' ?>"><?= $h($label) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="small text-muted">No vaccines recorded at <?= $h($clinic) ?> yet.</p><?php endif; ?>
    <div class="text-center small text-muted border-top pt-3">
      Health card from <strong><?= $h($clinic) ?></strong><?= $clinicPhone ? ' · ' . $h($clinicPhone) : '' ?><br>
      Updated <?= $h(date('j M Y')) ?>. Medical notes and the owner's address are private.
    </div>
  </div>
</div>
<?php endif; ?>
</body>
</html>
