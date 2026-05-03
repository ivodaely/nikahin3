<?php
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::requireUser();
$action = $_GET['action'] ?? 'upload';

if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    csrf_check();
    $assetId = (int)($_POST['asset_id'] ?? 0);
    $a = DB::one(
        "SELECT a.* FROM invitation_assets a
         JOIN invitations i ON i.id = a.invitation_id
         WHERE a.id = ? AND i.user_id = ?",
        [$assetId, (int)$user['id']]
    );
    if (!$a) json_response(['ok' => false, 'error' => 'Not found'], 404);

    // Delete physical file (best-effort)
    $abs = UPLOAD_DIR . '/' . str_replace(UPLOAD_URL . '/', '', $a['url']);
    if (is_file($abs)) @unlink($abs);

    DB::run("DELETE FROM invitation_assets WHERE id = ?", [$assetId]);
    json_response(['ok' => true]);
}

// upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$invId = (int)($_POST['invitation_id'] ?? 0);
$type  = $_POST['type'] ?? '';
$allowed = ['groom_photo','bride_photo','prewedding','reference','generated','cover'];
if (!in_array($type, $allowed, true)) {
    json_response(['ok' => false, 'error' => 'Invalid type'], 400);
}

$inv = DB::one("SELECT id FROM invitations WHERE id = ? AND user_id = ?", [$invId, (int)$user['id']]);
if (!$inv) json_response(['ok' => false, 'error' => 'Not found'], 404);

$saved = [];
if (!empty($_FILES['files'])) {
    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 0;
    for ($i = 0; $i < $count; $i++) {
        $one = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        $r = handle_upload($one, 'inv_' . $invId);
        if ($r) {
            $assetId = DB::insert(
                "INSERT INTO invitation_assets (invitation_id, type, url) VALUES (?, ?, ?)",
                [$invId, $type, $r['url']]
            );
            $saved[] = ['id' => $assetId, 'url' => $r['url']];
        }
    }
}

json_response(['ok' => true, 'assets' => $saved]);
