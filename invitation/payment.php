<?php
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::requireUser();
$invId = (int)($_GET['id'] ?? 0);

$inv = DB::one(
    "SELECT i.*, o.id AS order_id, o.amount, o.status AS order_status
     FROM invitations i
     LEFT JOIN orders o ON o.invitation_id = i.id AND o.status = 'pending_payment'
     WHERE i.id = ? AND i.user_id = ?
     ORDER BY o.id DESC LIMIT 1",
    [$invId, (int)$user['id']]
);
if (!$inv) {
    flash_set('info', 'Order tidak ditemukan.');
    redirect('dashboard/');
}

// Manual "mark as paid" handler — used by the dev-mode button
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'mark_paid') {
        DB::run("UPDATE orders SET status='paid', paid_at=NOW() WHERE id = ?", [(int)$inv['order_id']]);
        DB::run("UPDATE invitations SET status='paid' WHERE id = ?", [$invId]);
        audit('payment_manual', (int)$user['id'], 'invitation', $invId);
        redirect('invitation/generate.php?id=' . $invId);
    }
}

$page_title = 'Pembayaran';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-shell-narrow">
  <div class="card card-deep" style="text-align:center;padding:48px 32px;">
    <span class="page-eyebrow">Pembayaran</span>
    <h1 class="page-title" style="margin-bottom:16px;">Total: <em><?= idr($inv['amount'] ?? 0) ?></em></h1>

    <p class="page-desc">Untuk: <strong><?= e($inv['groom_name']) ?> &amp; <?= e($inv['bride_name']) ?></strong></p>

    <div class="alert alert-info" style="margin:24px 0;">
      <strong>Integrasi Payment Gateway Belum Dikonfigurasi</strong><br>
      Hubungkan ke Midtrans, Xendit, atau gateway pilihan Anda di file ini (<code>invitation/payment.php</code>),
      lalu panggil callback yang menandai order sebagai PAID.<br>
      <span style="font-size:.85rem;opacity:.8;">Untuk pengembangan, klik tombol di bawah untuk menandai sebagai lunas secara manual.</span>
    </div>

    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_paid">
      <button class="btn btn-primary">Tandai Lunas (Dev) →</button>
    </form>

    <p style="margin-top:18px;font-size:.85rem;color:var(--muted);">
      <a href="<?= APP_URL ?>/dashboard/" style="color:inherit;">← Batal &amp; kembali ke Dasbor</a>
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
