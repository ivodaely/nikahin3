<?php
/**
 * nikahin — Application Configuration
 *
 * Edit values below to match your environment.
 * For production, prefer loading from environment variables.
 */

// ---------------------------------------------------------
// Database
// ---------------------------------------------------------
define('DB_HOST', getenv('NIKAHIN_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('NIKAHIN_DB_PORT') ?: '3306');
define('DB_NAME', getenv('NIKAHIN_DB_NAME') ?: 'nikahin');
define('DB_USER', getenv('NIKAHIN_DB_USER') ?: 'root');
define('DB_PASS', getenv('NIKAHIN_DB_PASS') ?: '');

// ---------------------------------------------------------
// Application
// ---------------------------------------------------------
// Public-facing base URL — used for share links and public invitation URLs.
// Set this to whatever your dev environment is, e.g. 'http://localhost/nikahin'
define('APP_URL', getenv('NIKAHIN_APP_URL') ?: 'http://localhost/nikahin');
define('APP_NAME', 'nikahin');
define('APP_VERSION', '1.0.0');

// Where uploaded files live on disk (absolute path) and how they're served.
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL', APP_URL . '/uploads');

// ---------------------------------------------------------
// Session / Security
// ---------------------------------------------------------
define('SESSION_NAME', 'nikahin_sess');
define('SESSION_LIFETIME', 60 * 60 * 24 * 30); // 30 days
// Random 64-byte hex string. Generate once and keep stable in production.
define('APP_SECRET', getenv('NIKAHIN_SECRET') ?:
    'CHANGE_ME_a8f3c7b2d6e1f4a9c8b7e2d3f6a1b4c7d0e3f6a9b2c5d8e1f4a7b0c3d6e9');

// ---------------------------------------------------------
// OTP
// ---------------------------------------------------------
// In dev mode, generated OTPs are returned to the page so you can test
// without an SMS gateway. Set to false in production.
define('DEV_MODE_SHOW_OTP', true);
define('OTP_TTL_SECONDS', 300); // 5 minutes
define('OTP_MAX_ATTEMPTS', 3);

// ---------------------------------------------------------
// Claude API
// ---------------------------------------------------------
// Set the env var NIKAHIN_CLAUDE_KEY or replace the fallback string below.
define('CLAUDE_API_KEY', getenv('NIKAHIN_CLAUDE_KEY') ?: '');
define('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages');
define('CLAUDE_MODEL',   'claude-sonnet-4-6');   // text + design reasoning. Swap to 'claude-opus-4-7' for higher quality.
define('CLAUDE_VERSION', '2023-06-01');
define('CLAUDE_MAX_TOKENS', 4000);

// ---------------------------------------------------------
// Pricing (IDR)
// ---------------------------------------------------------
define('PRICE_BASIC', 50000);
define('PRICE_AI',    150000);

// ---------------------------------------------------------
// Toggles
// ---------------------------------------------------------
// Skip-payment mode: payment page becomes a "Mark as Paid" button
// that immediately advances the order to PAID. Set to false to
// re-enable a real payment integration.
define('SKIP_PAYMENT', true);

// ---------------------------------------------------------
// Display errors in development
// ---------------------------------------------------------
define('APP_DEBUG', true);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ---------------------------------------------------------
// Default timezone
// ---------------------------------------------------------
date_default_timezone_set('Asia/Jakarta');
