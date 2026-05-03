<?php
require_once __DIR__ . '/../includes/auth.php';

if (Auth::loggedIn()) redirect('dashboard/');

$errors = [];
$prefill = flash_get('login_email') ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = trim($_POST['identifier'] ?? '');

    // Resolve identifier: email or phone
    $byPhone = filter_var($id, FILTER_VALIDATE_EMAIL) ? null : normalize_phone($id);
    $isEmail = filter_var($id, FILTER_VALIDATE_EMAIL);

    if (!$id) {
        $errors[] = 'Masukkan email atau nomor HP.';
    } else {
        $user = $isEmail
            ? DB::one("SELECT * FROM users WHERE email = ?", [$id])
            : DB::one("SELECT * FROM users WHERE phone_e164 = ?", [$byPhone]);

        if (!$user) {
            $errors[] = 'Akun tidak ditemukan. Silakan daftar.';
        } elseif ($user['status'] !== 'active' && $user['status'] !== 'pending') {
            $errors[] = 'Akun ini tidak dapat masuk. Hubungi admin.';
        } else {
            // Issue OTP keyed to the submitted identifier (so verify can match it)
            $identifier = $isEmail ? $id : $byPhone;
            $code = Auth::issueOtp($identifier, 'login');
            $_SESSION['otp_pending'] = [
                'identifier' => $identifier,
                'purpose'    => 'login',
                'user_id'    => (int)$user['id'],
            ];
            if (DEV_MODE_SHOW_OTP && $code) {
                $_SESSION['dev_otp'] = $code;
            }
            redirect('auth/verify-otp.php');
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk — nikahin</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
</head>
<body>
<div class="auth-shell">
  <aside class="auth-side">
    <a href="<?= APP_URL ?>/" class="app-logo" style="font-size:1.6rem;">nika<span>hin</span></a>
    <div>
      <h2>Selamat <em>datang kembali</em>.</h2>
      <p>Masukkan email atau nomor HP — kami akan kirim kode OTP untuk masuk dengan aman.</p>
    </div>
    <p style="font-size:.85rem;color:var(--brown);">© <?= date('Y') ?> nikahin</p>
  </aside>

  <div class="auth-card-wrap">
    <div class="auth-card">
      <h3>Masuk</h3>
      <p class="muted">Login tanpa kata sandi — pakai OTP.</p>

      <?php if ($msg = flash_get('info')): ?>
        <div class="alert alert-info"><?= e($msg) ?></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/auth/login.php">
        <?= csrf_field() ?>
        <div class="field">
          <label>Email atau Nomor HP</label>
          <input type="text" name="identifier" value="<?= e($prefill ?: ($_POST['identifier'] ?? '')) ?>"
                 placeholder="anda@email.com / +62 8xx" required autofocus>
        </div>
        <button class="btn btn-primary btn-block">Kirim OTP →</button>
      </form>

      <p class="auth-link">Belum punya akun? <a href="<?= APP_URL ?>/auth/register.php">Daftar di sini</a></p>
    </div>
  </div>
</div>
</body>
</html>
