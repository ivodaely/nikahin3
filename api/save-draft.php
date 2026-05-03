<?php
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::requireUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_check();

$invId = (int)($_POST['invitation_id'] ?? 0);
$inv = DB::one(
    "SELECT id FROM invitations WHERE id = ? AND user_id = ?",
    [$invId, (int)$user['id']]
);
if (!$inv) json_response(['ok' => false, 'error' => 'Not found'], 404);

$profile = $_POST['profile'] ?? [];

// Light cleanup — strip empty gift rows
if (isset($profile['gift']) && is_array($profile['gift'])) {
    $profile['gift'] = array_values(array_filter($profile['gift'], function ($g) {
        return !empty($g['provider']) || !empty($g['number']);
    }));
}

// Denormalized fields used for listing/sorting
$groomName  = trim($profile['groom']['name']  ?? '');
$brideName  = trim($profile['bride']['name']  ?? '');
$weddingDate = $profile['schedule']['akad']['date']
    ?? $profile['schedule']['resepsi']['date']
    ?? null;

DB::run(
    "UPDATE invitations
     SET groom_name = ?, bride_name = ?, wedding_date = ?, profile_json = ?
     WHERE id = ?",
    [
        $groomName ?: null,
        $brideName ?: null,
        $weddingDate ?: null,
        json_encode($profile, JSON_UNESCAPED_UNICODE),
        $invId,
    ]
);

json_response(['ok' => true, 'saved_at' => date('c')]);
