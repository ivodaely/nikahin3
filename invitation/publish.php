<?php
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::requireUser();
$invId = (int)($_GET['id'] ?? 0);

$inv = DB::one("SELECT * FROM invitations WHERE id = ? AND user_id = ?", [$invId, (int)$user['id']]);
if (!$inv) {
    flash_set('info', 'Undangan tidak ditemukan.');
    redirect('dashboard/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || empty($inv['published_at'])) {
    if ($inv['status'] === 'ready_for_preview' || empty($inv['published_at'])) {
        // Ensure slug exists
        if (empty($inv['slug'])) {
            $profile = json_decode($inv['profile_json'] ?: '{}', true) ?: [];
            $slug = make_slug(
                $profile['groom']['short'] ?? 'mempelai',
                $profile['bride']['short'] ?? 'mempelai'
            );
            DB::run("UPDATE invitations SET slug = ? WHERE id = ?", [$slug, $invId]);
            $inv['slug'] = $slug;
        }
        DB::run("UPDATE invitations SET status='published', published_at=NOW() WHERE id = ?", [$invId]);
        audit('invitation_published', (int)$user['id'], 'invitation', $invId);
    }
    $inv['status'] = 'published';
}

$publicUrl = APP_URL . '/v/' . $inv['slug'];
$page_title = 'Berhasil Dipublikasikan';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-shell-narrow">
  <div class="card card-deep" style="text-align:center;padding:64px 32px;">
    <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-light));margin:0 auto 24px;display:flex;align-items:center;justify-content:center;color:white;font-size:2rem;font-weight:700;box-shadow:var(--shadow-card);">✓</div>
    <h1 style="font-size:2rem;margin-bottom:8px;">Berhasil <em style="font-style:italic;color:var(--gold);">terbit</em>!</h1>
    <p style="color:var(--brown);max-width:500px;margin:0 auto 32px;">Undangan Anda kini dapat diakses oleh siapa saja yang memiliki tautan di bawah ini.</p>

    <div style="background:var(--ecru);padding:18px 24px;border-radius:12px;margin:0 auto 24px;max-width:560px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:space-between;">
      <code style="font-size:1rem;word-break:break-all;color:var(--dark);"><?= e($publicUrl) ?></code>
      <button class="btn btn-primary btn-sm" data-copy="<?= e($publicUrl) ?>">Salin</button>
    </div>

    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:20px;">
      <a class="btn btn-dark" target="_blank" href="<?= e($publicUrl) ?>">Buka Undangan ↗</a>
      <a class="btn btn-ghost"
         target="_blank"
         href="https://wa.me/?text=<?= e(rawurlencode("Bismillah. Kami mengundang Anda di pernikahan kami: " . $publicUrl)) ?>">
        Bagikan via WhatsApp
      </a>
      <a class="btn btn-ghost" href="<?= APP_URL ?>/invitation/manage.php?id=<?= $invId ?>">Kelola Tamu &amp; RSVP</a>
    </div>

    <p style="font-size:.85rem;color:var(--muted);">Anda dapat terus mengedit teks dan foto. Nama lengkap dan tanggal pernikahan dikunci untuk mencegah perubahan tidak sengaja.</p>
  </div>
</div>

<script>
$('[data-copy]').on('click', function(e){
  e.preventDefault();
  var v = $(this).data('copy'); var $b = $(this); var t = $b.text();
  if (navigator.clipboard) navigator.clipboard.writeText(v);
  $b.text('Tersalin ✓'); setTimeout(function(){ $b.text(t); }, 1500);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
