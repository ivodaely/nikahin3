<?php
/**
 * Auth: OTP issuance, verification, session management, route guards.
 *
 * Login is passwordless: user submits email or phone → system sends OTP → user types OTP → signed in.
 * In DEV_MODE_SHOW_OTP, the code is also returned to the calling script so you can test without an SMS gateway.
 */

require_once __DIR__ . '/functions.php';

final class Auth {

    // ---------------------------------------------------
    // Sign in / out
    // ---------------------------------------------------
    public static function user(): ?array {
        if (empty($_SESSION['uid'])) return null;
        $u = DB::one("SELECT * FROM users WHERE id = ?", [(int)$_SESSION['uid']]);
        return $u ?: null;
    }

    public static function id(): ?int {
        return $_SESSION['uid'] ?? null;
    }

    public static function loggedIn(): bool {
        return !empty($_SESSION['uid']);
    }

    public static function login(int $userId): void {
        // Regenerate session id to prevent fixation
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        DB::run("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$userId]);
        audit('login', $userId);
    }

    public static function logout(): void {
        $uid = self::id();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        if ($uid) audit('logout', $uid);
    }

    // ---------------------------------------------------
    // Route guards
    // ---------------------------------------------------
    public static function requireUser(): array {
        $u = self::user();
        if (!$u) redirect('auth/login.php');
        return $u;
    }

    public static function requireAdmin(): array {
        $u = self::requireUser();
        if (empty($u['is_admin'])) {
            http_response_code(403);
            die('Forbidden');
        }
        return $u;
    }

    // ---------------------------------------------------
    // OTP
    // ---------------------------------------------------
    /**
     * Issue an OTP for a given identifier (email OR phone).
     * Returns the plain code in DEV_MODE so caller can show it; otherwise null.
     */
    public static function issueOtp(string $identifier, string $purpose): ?string {
        $code = (string)random_int(100000, 999999);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + OTP_TTL_SECONDS);

        // Invalidate older live codes for this identifier+purpose
        DB::run(
            "UPDATE otp_codes SET consumed_at = NOW()
             WHERE identifier = ? AND purpose = ? AND consumed_at IS NULL",
            [$identifier, $purpose]
        );

        DB::run(
            "INSERT INTO otp_codes (identifier, code_hash, purpose, expires_at)
             VALUES (?, ?, ?, ?)",
            [$identifier, $hash, $purpose, $expires]
        );

        // In production: dispatch SMS / WhatsApp here.
        // self::sendSms($identifier, "Kode nikahin Anda: $code (berlaku 5 menit).");

        return DEV_MODE_SHOW_OTP ? $code : null;
    }

    /**
     * Verify an OTP. Returns true on success and consumes the code.
     */
    public static function verifyOtp(string $identifier, string $code, string $purpose): bool {
        $row = DB::one(
            "SELECT * FROM otp_codes
             WHERE identifier = ? AND purpose = ? AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1",
            [$identifier, $purpose]
        );
        if (!$row) return false;
        if (strtotime($row['expires_at']) < time()) return false;
        if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) return false;

        DB::run("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?", [$row['id']]);

        if (!password_verify($code, $row['code_hash'])) return false;

        DB::run("UPDATE otp_codes SET consumed_at = NOW() WHERE id = ?", [$row['id']]);
        return true;
    }
}
