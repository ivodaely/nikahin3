<?php
require_once __DIR__ . '/../includes/auth.php';
$user = Auth::requireUser();

// Pull invitations
$invitations = DB::all(
    "SELECT i.*,
       (SELECT COUNT(*) FROM guests g WHERE g.invitation_id = i.id AND g.rsvp_status = 'yes') AS rsvp_yes,
       (SELECT COUNT(*) FROM gifts gf WHERE gf.invitation_id = i.id AND gf.status = 'confirmed') AS gifts_confirmed
     FROM invitations i
     WHERE i.user_id = ?
     ORDER BY i.created_at DESC",
    [(int)$user['id']]
);

$page_title = 'Dasbor';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <span class="page-eyebrow">Dasbor Anda</span>
  <h1 class="page-title">Halo, <em><?= e($user['display_name'] ?: 'Calon Pengantin') ?></em>.</h1>
  <p class="page-desc">Semua undangan Anda ada di sini. Buat baru, edit yang masih draft, atau pantau RSVP undangan yang sudah terbit.</p>

  <?php if (empty($invitations)): ?>
    <div class="dash-cta">
      <h2>Mulai membuat undangan <em>pertama</em> Anda.</h2>
      <p>Pilih paket Basic atau AI-Generated, isi profil pasangan, dan publikasikan dalam 15 menit.</p>
      <a href="<?= APP_URL ?>/invitation/create.php" class="btn btn-primary">+ Buat Undangan Baru</a>
    </div>
    <div class="empty">
      <h3>Belum ada undangan</h3>
      <p>Klik tombol di atas untuk membuat undangan pertama Anda.</p>
    </div>
  <?php else: ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
      <h2 style="font-size:1.5rem;">Undangan Anda</h2>
      <a href="<?= APP_URL ?>/invitation/create.php" class="btn btn-primary">+ Buat Baru</a>
    </div>

    <div class="invitations-grid">
      <?php foreach ($invitations as $inv): ?>
        <div class="inv-card">
          <div class="inv-card-head">
            <span class="inv-card-tier"><?= $inv['tier'] === 'ai' ? 'AI-Generated ✦' : 'Basic Template' ?></span>
            <span class="pill pill-<?= e($inv['status']) ?>"><?= e(str_replace('_', ' ', $inv['status'])) ?></span>
          </div>
          <div class="inv-card-names">
            <?= e($inv['groom_name'] ?: 'Mempelai Pria') ?> &amp; <?= e($inv['bride_name'] ?: 'Mempelai Wanita') ?>
          </div>
          <div class="inv-card-date">
            <?= $inv['wedding_date'] ? e(fmt_date_id($inv['wedding_date'])) : '— belum ditentukan —' ?>
          </div>

          <?php if ($inv['status'] === 'published'): ?>
            <div class="inv-card-stats">
              <div>
                <div class="inv-stat-num"><?= number_format((int)$inv['view_count']) ?></div>
                <div class="inv-stat-label">Views</div>
              </div>
              <div>
                <div class="inv-stat-num"><?= number_format((int)$inv['rsvp_yes']) ?></div>
                <div class="inv-stat-label">RSVP Ya</div>
              </div>
              <div>
                <div class="inv-stat-num"><?= number_format((int)$inv['gifts_confirmed']) ?></div>
                <div class="inv-stat-label">Angpau</div>
              </div>
            </div>
          <?php endif; ?>

          <div class="inv-card-actions">
            <?php if (in_array($inv['status'], ['draft', 'pending_payment'], true)): ?>
              <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/invitation/form.php?id=<?= (int)$inv['id'] ?>">Lanjutkan</a>
            <?php elseif ($inv['status'] === 'paid' || $inv['status'] === 'generating'): ?>
              <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/invitation/generate.php?id=<?= (int)$inv['id'] ?>">Lihat Progress</a>
            <?php elseif ($inv['status'] === 'ready_for_preview'): ?>
              <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/invitation/preview.php?id=<?= (int)$inv['id'] ?>">Pratinjau &amp; Edit</a>
            <?php elseif ($inv['status'] === 'published'): ?>
              <a class="btn btn-primary btn-sm" target="_blank" href="<?= APP_URL ?>/v/<?= e($inv['slug']) ?>">Buka Undangan</a>
              <a class="btn btn-ghost btn-sm" href="<?= APP_URL ?>/invitation/manage.php?id=<?= (int)$inv['id'] ?>">Kelola</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
