<?php
/** Serves uploaded photos and documents to people allowed to see them. */
require __DIR__ . '/api/lib/bootstrap.php';
require __DIR__ . '/api/lib/http.php';
require __DIR__ . '/api/handlers/features.php';

start_session();
$u = current_user();
if (!$u) {
    http_response_code(401);
    exit('Please log in.');
}
$a = q_one('SELECT * FROM attachments WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
try {
    if (!$a || !attachment_access($a['entity_type'], (int)$a['entity_id'], $u)['view']) {
        throw new ApiError('Not found', 404);
    }
} catch (ApiError $e) {
    http_response_code(404);
    exit('File not found.');
}
$path = upload_dir() . '/' . $a['stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}
$download = isset($_GET['download']);
header('Content-Type: ' . $a['mime']);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, max-age=86400');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $a['original_name']) . '"');
readfile($path);
