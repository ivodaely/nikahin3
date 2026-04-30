<?php
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$slug      = trim($_POST['slug'] ?? '');
$linkToken = trim($_POST['token'] ?? '');
$name      = trim($_POST['name'] ?? '');
$attending = $_POST['attending'] ?? 'yes';
$attendees = max(1, min(20, (int)($_POST['attendees'] ?? 1)));
$event     = $_POST['event'] ?? 'both';
$message   = trim($_POST['message'] ?? '');

if (!$slug || !$name) json_response(['ok' => false, 'error' => 'Data tidak lengkap'], 400);

$attending = in_array($attending, ['yes','no','maybe'], true) ? $attending : 'yes';
$event     = in_array($event, ['akad','resepsi','both'], true) ? $event : 'both';

$inv = DB::one("SELECT id FROM invitations WHERE slug = ? AND status = 'published'", [$slug]);
if (!$inv) json_response(['ok' => false, 'error' => 'Undangan tidak ditemukan'], 404);

if ($linkToken) {
    // Update existing guest record
    $g = DB::one(
        "SELECT id FROM guests WHERE invitation_id = ? AND link_token = ?",
        [$inv['id'], $linkToken]
    );
    if ($g) {
        DB::run(
            "UPDATE guests SET name=?, rsvp_status=?, attendees=?, event_choice=?, message=?, responded_at=NOW() WHERE id = ?",
            [$name, $attending, $attendees, $event, $message ?: null, $g['id']]
        );
        json_response(['ok' => true]);
    }
}

// New guest entry (open RSVP)
$token = bin2hex(random_bytes(8));
DB::insert(
    "INSERT INTO guests (invitation_id, name, link_token, rsvp_status, attendees, event_choice, message, responded_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
    [$inv['id'], $name, $token, $attending, $attendees, $event, $message ?: null]
);
json_response(['ok' => true]);
