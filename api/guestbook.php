<?php
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$slug    = trim($_POST['slug'] ?? '');
$name    = trim($_POST['name'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$slug || !$name || !$message) json_response(['ok' => false, 'error' => 'Data tidak lengkap'], 400);
if (mb_strlen($message) > 280) json_response(['ok' => false, 'error' => 'Maksimal 280 karakter'], 400);

$inv = DB::one("SELECT id FROM invitations WHERE slug = ? AND status = 'published'", [$slug]);
if (!$inv) json_response(['ok' => false, 'error' => 'Undangan tidak ditemukan'], 404);

// Light profanity filter (placeholder — replace with proper service in production)
$bad = ['kasar1','kasar2'];
foreach ($bad as $w) {
    if (stripos($message, $w) !== false) {
        json_response(['ok' => false, 'error' => 'Pesan mengandung kata yang tidak diperbolehkan'], 400);
    }
}

$id = DB::insert(
    "INSERT INTO guestbook (invitation_id, guest_name, message) VALUES (?, ?, ?)",
    [$inv['id'], $name, $message]
);

json_response([
    'ok' => true,
    'message' => [
        'id'         => $id,
        'guest_name' => e($name),
        'message'    => e($message),
        'created_at' => date('d M Y H:i'),
    ],
]);
