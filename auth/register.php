<?php
require_once __DIR__ . '/../includes/auth.php';

$errors = [];
$dev_otp = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $phoneRaw = trim($_POST['phone'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    $phone = normalize_phone($phoneRaw);
    if (!$phone || strlen($phone) < 8) {
        $errors[] = 'Nomor HP tidak valid.';
    }

    if (!$errors) {
        // If user already exists, redirect to login with email pre-filled (per spec §4.1)
        $existing = DB::one("SELECT id FROM users WHERE email = ? OR phone_e164 = ?", [$email, $phone]);
        if ($existing) {
            flash_set('login_email', $email);
            flash_set('info', 'Akun ini sudah terdaftar — silakan masuk.');
            redirect('auth/login.php');
        }

        // Create pending user
        $uid = DB::insert(
            "INSERT INTO users (email, phone_e164, status) VALUES (?, ?, 'pending')",
            [$email, $phone]
        );
        audit('register_init', $uid);

        // Issue OTP — keyed by email so verify-otp.php can find it
        $dev_otp = Auth::issueOtp($email, 'register');

        // Stash identifier so verify page knows who to look up
        $_SESSION['otp_pending'] = ['identifier' => $email, 'purpose' => 'register', 'user_id' => $uid];

        if (DEV_MODE_SHOW_OTP && $dev_otp) {
            // Show the OTP on the next page (dev only)
            $_SESSION['dev_otp'] = $dev_otp;
        }
        redirect('auth/verify-otp.php');
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar — nikahin</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
</head>
<body>
<div class="auth-shell">
  <aside class="auth-side">
    <a href="<?= APP_URL ?>/" class="app-logo" style="font-size:1.6rem;">nika<span>hin</span></a>
    <div>
      <h2>Setiap kisah cinta pantas mendapatkan undangan <em>yang unik</em>.</h2>
      <p>Bergabunglah dalam dua langkah: masukkan email + HP, lalu verifikasi dengan OTP. Tanpa kata sandi.</p>
    </div>
    <p style="font-size:.85rem;color:var(--brown);">© <?= date('Y') ?> nikahin</p>
  </aside>

  <div class="auth-card-wrap">
    <div class="auth-card">
      <h3>Buat Akun</h3>
      <p class="muted">Kami akan mengirim kode OTP 6 digit untuk verifikasi.</p>

      <?php if ($errors): ?>
        <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
      <?php endif; ?>
      <?php if ($msg = flash_get('info')): ?>
        <div class="alert alert-info"><?= e($msg) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/auth/register.php">
        <?= csrf_field() ?>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="anda@email.com" required>
        </div>
        <div class="field">
          <label>Nomor HP</label>
          <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+62 8xx xxxx xxxx" required>
          <div class="field-help">Format Indonesia (08xx) atau internasional (+xx) didukung.</div>
        </div>
        <button class="btn btn-primary btn-block">Kirim Kode OTP →</button>
      </form>

      <p class="auth-link">Sudah punya akun? <a href="<?= APP_URL ?>/auth/login.php">Masuk di sini</a></p>
    </div>
  </div>
</div>
</body>
</html>
