<?php
/** Shared header/footer for printable pages (receipt-style). */
defined('APP_LOADED') or exit;

function print_head(string $title): void
{
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $clinic = setting('clinic_name', 'Purrfect Pet Care');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<link rel="icon" type="image/png" href="assets/img/paw-favicon.png?v=1">'
        . '<title>' . $h($title) . ' - ' . $h($clinic) . '</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '<style>body{background:#FFF5E1;font-family:Poppins,system-ui,sans-serif}.sheet{max-width:820px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.08)}'
        . '.head{background:linear-gradient(135deg,#FF7300,#3AAFA9);color:#fff;padding:24px 28px}.head h1{color:#fff}.head img.logo{height:48px;background:#fff;border-radius:50%;padding:4px}'
        . '.pet-photo{width:96px;height:96px;object-fit:cover;border-radius:12px;border:3px solid #FFF5E1}.sig{border-top:1px solid #333;width:220px;padding-top:4px;font-size:.85rem}'
        . '.seal{border:3px solid #3AAFA9;color:#2B8A85;border-radius:50%;width:110px;height:110px;display:grid;place-items:center;text-align:center;font-weight:700;font-size:.75rem;transform:rotate(-8deg)}'
        . 'table th{font-size:.78rem;text-transform:uppercase;color:#666}'
        . '@media print{body{background:#fff}.no-print{display:none!important}.sheet{box-shadow:none;margin:0;max-width:none}}</style></head><body><div class="sheet">'
        . '<div class="head d-flex align-items-center gap-3"><img class="logo" src="assets/img/paws1.png" alt=""><div><h1 class="h4 mb-0">' . $h($clinic) . '</h1>'
        . '<small>' . $h(setting('clinic_address')) . ' &middot; ' . $h(setting('clinic_phone')) . ' &middot; ' . $h(setting('clinic_email')) . '</small></div></div><div class="p-4">';
}

function print_foot(string $back = 'app.html'): void
{
    echo '<div class="no-print mt-4 d-flex gap-2"><button class="btn btn-dark" onclick="window.print()">Print / Save as PDF</button>'
        . '<a class="btn btn-outline-secondary" href="' . htmlspecialchars($back, ENT_QUOTES) . '">Back to dashboard</a></div></div></div></body></html>';
}

function require_print_user(): array
{
    start_session();
    $u = current_user();
    if (!$u) {
        header('Location: index.html?login=1');
        exit;
    }
    return $u;
}
