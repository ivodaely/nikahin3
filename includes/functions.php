<?php
/**
 * Shared helpers: escaping, CSRF, slugs, formatters, redirects.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ---------------------------------------------------------
// Session bootstrap
// ---------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------
// HTML escape shortcut
// ---------------------------------------------------------
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------
// CSRF
// ---------------------------------------------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('CSRF token mismatch. Please reload the page and try again.');
    }
}

// ---------------------------------------------------------
// Redirect
// ---------------------------------------------------------
function redirect(string $path): void {
    if (str_starts_with($path, 'http')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    }
    exit;
}

// ---------------------------------------------------------
// Flash messages
// ---------------------------------------------------------
function flash_set(string $key, string $msg): void {
    $_SESSION['_flash'][$key] = $msg;
}
function flash_get(string $key): ?string {
    $m = $_SESSION['_flash'][$key] ?? null;
    if ($m !== null) unset($_SESSION['_flash'][$key]);
    return $m;
}

// ---------------------------------------------------------
// Slug generation
// ---------------------------------------------------------
function make_slug(string $groomShort, string $brideShort): string {
    $base = strtolower(trim($groomShort . '-and-' . $brideShort));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base);
    $base = trim($base, '-');
    if ($base === '') $base = 'undangan';

    // Ensure uniqueness against existing invitations
    $slug = $base;
    $i = 1;
    while (DB::one("SELECT id FROM invitations WHERE slug = ?", [$slug])) {
        $slug = $base . '-' . (++$i);
    }
    return $slug;
}

// ---------------------------------------------------------
// JSON helpers
// ---------------------------------------------------------
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ---------------------------------------------------------
// Phone normalization (Indonesia + permissive international)
// ---------------------------------------------------------
function normalize_phone(string $raw): ?string {
    $digits = preg_replace('/[^\d+]/', '', $raw);
    if ($digits === '') return null;

    if (str_starts_with($digits, '+')) {
        // Already E.164-ish
        return '+' . preg_replace('/\D/', '', substr($digits, 1));
    }
    // Indonesian formats: 08xx → +628xx; 628xx → +628xx
    $digits = preg_replace('/\D/', '', $digits);
    if (str_starts_with($digits, '0')) {
        return '+62' . substr($digits, 1);
    }
    if (str_starts_with($digits, '62')) {
        return '+' . $digits;
    }
    return '+' . $digits;
}

// ---------------------------------------------------------
// IDR currency formatting
// ---------------------------------------------------------
function idr(int $amount): string {
    return 'IDR ' . number_format($amount, 0, ',', '.');
}

// ---------------------------------------------------------
// Indonesian-friendly date formatting
// ---------------------------------------------------------
function fmt_date_id(?string $date): string {
    if (!$date) return '';
    $months = [1=>'Januari','Februari','Maret','April','Mei','Juni',
               'Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($date);
    if ($ts === false) return e($date);
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    return $d . ' ' . $months[$m] . ' ' . $y;
}

// ---------------------------------------------------------
// File upload helper (returns path relative to UPLOAD_DIR or null on failure)
// ---------------------------------------------------------
function handle_upload(array $file, string $subdir = 'misc'): ?array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 10 * 1024 * 1024) return null; // 10 MB cap

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) return null;

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'bin',
    };

    $dir = UPLOAD_DIR . '/' . preg_replace('/[^a-z0-9_-]/i', '', $subdir);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $abs  = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $abs)) return null;

    return [
        'path' => str_replace(UPLOAD_DIR . '/', '', $abs),
        'url'  => UPLOAD_URL . '/' . str_replace(UPLOAD_DIR . '/', '', $abs),
    ];
}

// ---------------------------------------------------------
// Audit log helper
// ---------------------------------------------------------
function audit(string $action, ?int $actorId = null, ?string $targetType = null, ?int $targetId = null, $payload = null): void {
    try {
        DB::run(
            "INSERT INTO audit_log (actor_id, action, target_type, target_id, payload, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $actorId,
                $action,
                $targetType,
                $targetId,
                $payload === null ? null : (is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE)),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    } catch (Throwable $e) { /* swallow — audit must never block a request */ }
}
