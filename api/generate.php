<?php
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::requireUser();
$invId = (int)($_GET['id'] ?? 0);

$inv = DB::one(
    "SELECT id, status FROM invitations WHERE id = ? AND user_id = ?",
    [$invId, (int)$user['id']]
);
if (!$inv) json_response(['ok' => false, 'error' => 'Not found'], 404);

$last = DB::one(
    "SELECT status, error_message FROM generations
     WHERE invitation_id = ?
     ORDER BY id DESC LIMIT 1",
    [$invId]
);

json_response([
    'ok'     => true,
    'status' => $inv['status'],
    'error'  => $last['error_message'] ?? null,
]);
