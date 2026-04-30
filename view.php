<?php
require_once __DIR__ . '/includes/functions.php';

// Resolve invitation by id (preview) or slug (public)
$inv = null;
$is_preview = false;
$guest = null;

if (!empty($_GET['id'])) {
    require_once __DIR__ . '/includes/auth.php';
    $user = Auth::user();
    if (!$user) { http_response_code(403); die('Forbidden'); }
    $inv = DB::one(
        "SELECT * FROM invitations WHERE id = ? AND user_id = ?",
        [(int)$_GET['id'], (int)$user['id']]
    );
    $is_preview = !empty($_GET['preview']);
} elseif (!empty($_GET['slug'])) {
    $inv = DB::one("SELECT * FROM invitations WHERE slug = ?", [$_GET['slug']]);
    if ($inv && $inv['status'] !== 'published') {
        http_response_code(404); die('Belum dipublikasikan.');
    }
    if (!empty($_GET['token'])) {
        $guest = DB::one(
            "SELECT * FROM guests WHERE invitation_id = ? AND link_token = ?",
            [(int)$inv['id'], $_GET['token']]
        );
    }
    // Bump view counter (best-effort)
    if ($inv) DB::run("UPDATE invitations SET view_count = view_count + 1 WHERE id = ?", [(int)$inv['id']]);
}

if (!$inv) { http_response_code(404); die('Undangan tidak ditemukan.'); }

$invitation = $inv;
$profile = json_decode($inv['profile_json'] ?: '{}', true) ?: [];
$design  = json_decode($inv['design_json']  ?: '{}', true) ?: [];

// Group assets
$rawAssets = DB::all("SELECT * FROM invitation_assets WHERE invitation_id = ? ORDER BY position, id", [(int)$inv['id']]);
$assets = [];
foreach ($rawAssets as $a) {
    $assets[$a['type']][] = $a;
}

include __DIR__ . '/BasicTemplate/template.php';
