<?php
require_once __DIR__ . '/../includes/auth.php';

if (empty($_SESSION['otp_pending'])) {
    redirect('auth/login.php');
}
$pending = $_SESSION['otp_pending'];
$dev_otp = $_SESSION['dev_otp'] ?? null;
unset($_SESSION['dev_otp']); // show only once

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!empty($_POST['resend'])) {
        $newOtp = Auth::issueOtp($pending['identifier'], $pending['purpose']);
        if (DEV_MODE_SHOW_OTP && $newOtp) {
            $_SESSION['dev_otp'] = $newOtp;
        }
        flash_set('info', 'Kode baru telah dikirim.');
        redirect('auth/verify-otp.php');
    }

    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (strlen($code) !== 6) {
        $errors[] = 'Kode harus 6 digit.';
    } elseif (!Auth::verifyOtp($pending['identifier'], $code, $pending['purpose'])) {
        $errors[] = 'Kode salah atau sudah kadaluarsa.';
    } else {
        // Activate and sign in
        if ($pending['purpose'] === 'register') {
            DB::run("UPDATE users SET status = 'active' WHERE id = ?", [$pending['user_id']]);
            $userId = $pending['user_id'];
        } else {
            // login flow: identifier may be email or phone
            $u = DB::one(
                "SELECT id FROM users WHERE email = ? OR phone_e164 = ?",
                [$pending['identifier'], $pending['identifier']]
            );
            if (!$u) {
                $errors[] = 'Akun tidak ditemukan.';
                $userId = null;
            } else {
                $userId = (int)$u['id'];
            }
        }
        if ($userId) {
            unset($_SESSION['otp_pending']);
            Auth::login($userId);
            redirect('dashboard/');
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verifikasi OTP — nikahin</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<div class="auth-shell">
  <aside class="auth-side">
    <a href="<?= APP_URL ?>/" class="app-logo" style="font-size:1.6rem;">nika<span>hin</span></a>
    <div>
      <h2>Tinggal <em>satu langkah</em> lagi.</h2>
      <p>Kami telah mengirim kode 6 digit ke <strong><?= e($pending['identifier']) ?></strong>. Kode berlaku 5 menit.</p>
    </div>
    <p style="font-size:.85rem;color:var(--brown);">© <?= date('Y') ?> nikahin</p>
  </aside>

  <div class="auth-card-wrap">
    <div class="auth-card">
      <h3>Masukkan Kode OTP</h3>
      <p class="muted">6 digit angka yang dikirim ke <?= e($pending['identifier']) ?>.</p>

      <?php if (DEV_MODE_SHOW_OTP && $dev_otp): ?>
        <div class="dev-otp-banner">
          🔧 <em>Dev Mode</em> — SMS gateway belum aktif. Kode Anda:
          <br><strong><?= e($dev_otp) ?></strong>
        </div>
      <?php endif; ?>

      <?php if ($msg = flash_get('info')): ?>
        <div class="alert alert-info"><?= e($msg) ?></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/auth/verify-otp.php">
        <?= csrf_field() ?>
        <div class="field">
          <label>Kode OTP</label>
          <input type="text" name="code" maxlength="6" inputmode="numeric"
                 pattern="[0-9]*" autocomplete="one-time-code" autofocus
                 class="otp-input" placeholder="••••••" required>
        </div>
        <button class="btn btn-primary btn-block">Verifikasi →</button>
      </form>

      <form method="POST" action="<?= APP_URL ?>/auth/verify-otp.php" style="margin-top:14px;text-align:center;">
        <?= csrf_field() ?>
        <input type="hidden" name="resend" value="1">
        <button id="otp-resend" type="submit" class="btn btn-ghost btn-sm">Kirim ulang kode</button>
      </form>

      <p class="auth-link"><a href="<?= APP_URL ?>/auth/login.php">← Kembali</a></p>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
</body>
</html>
