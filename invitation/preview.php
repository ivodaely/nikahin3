<?php
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::requireUser();
$invId = (int)($_GET['id'] ?? 0);

$inv = DB::one("SELECT * FROM invitations WHERE id = ? AND user_id = ?", [$invId, (int)$user['id']]);
if (!$inv) {
    flash_set('info', 'Undangan tidak ditemukan.');
    redirect('dashboard/');
}
if (!in_array($inv['status'], ['ready_for_preview','published'], true)) {
    redirect('invitation/generate.php?id=' . $invId);
}

// Inline edits to specific copy fields
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'update_copy') {
        $design = json_decode($inv['design_json'] ?: '{}', true) ?: [];
        $design['copy_id']['welcome']    = trim($_POST['welcome']    ?? ($design['copy_id']['welcome']    ?? ''));
        $design['copy_id']['invocation'] = trim($_POST['invocation'] ?? ($design['copy_id']['invocation'] ?? ''));
        $design['copy_id']['story']      = trim($_POST['story']      ?? ($design['copy_id']['story']      ?? ''));
        $design['rsvp_prompt']           = trim($_POST['rsvp_prompt'] ?? ($design['rsvp_prompt']         ?? ''));
        DB::run("UPDATE invitations SET design_json = ? WHERE id = ?",
                [json_encode($design, JSON_UNESCAPED_UNICODE), $invId]);
        flash_set('info', 'Perubahan tersimpan.');
        redirect('invitation/preview.php?id=' . $invId);
    }
    if (($_POST['action'] ?? '') === 'regenerate' && $inv['tier'] === 'ai') {
        DB::run("UPDATE invitations SET status='paid' WHERE id = ?", [$invId]);
        redirect('invitation/generate.php?id=' . $invId);
    }
}

// Build view data
$profile = json_decode($inv['profile_json'] ?: '{}', true) ?: [];
$design  = json_decode($inv['design_json']  ?: '{}', true) ?: [];

$page_title = 'Pratinjau';
include __DIR__ . '/../includes/header.php';
?>
<style>
  .preview-frame { display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: flex-start; }
  .preview-iframe { width: 100%; height: 80vh; border: 0; border-radius: 20px;
    box-shadow: var(--shadow-deep); background: var(--white); }
  .preview-side { position: sticky; top: 100px; }
  @media (max-width: 1000px) { .preview-frame { grid-template-columns: 1fr; } .preview-side { position: static; } }
</style>

<div class="app-shell-wide">
  <a href="<?= APP_URL ?>/dashboard/" style="color:var(--brown);font-size:.9rem;display:inline-block;margin-bottom:18px;">← Kembali ke Dasbor</a>

  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
    <div>
      <span class="page-eyebrow">Pratinjau</span>
      <h1 class="page-title"><?= $inv['status'] === 'published' ? 'Sudah <em>tayang</em>' : 'Hampir <em>siap</em>' ?></h1>
      <p class="page-desc">
        <?php if ($inv['status'] === 'published'): ?>
          Undangan Anda dapat diakses publik di <strong><?= e(APP_URL . '/v/' . $inv['slug']) ?></strong>.
        <?php else: ?>
          Edit copy di sebelah kanan. Klik <strong>Publikasikan</strong> saat siap dibagikan.
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <?php if ($inv['status'] === 'ready_for_preview'): ?>
        <a class="btn btn-primary" href="<?= APP_URL ?>/invitation/publish.php?id=<?= $invId ?>">Publikasikan →</a>
      <?php else: ?>
        <a class="btn btn-primary" target="_blank" href="<?= APP_URL ?>/v/<?= e($inv['slug']) ?>">Buka Undangan ↗</a>
        <a class="btn btn-ghost" href="<?= APP_URL ?>/invitation/manage.php?id=<?= $invId ?>">Kelola Tamu</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($msg = flash_get('info')): ?>
    <div class="alert alert-success" style="margin-top:18px;"><?= e($msg) ?></div>
  <?php endif; ?>

  <div class="preview-frame" style="margin-top:24px;">
    <div>
      <iframe class="preview-iframe" src="<?= APP_URL ?>/view.php?id=<?= $invId ?>&preview=1"></iframe>
    </div>

    <aside class="preview-side">
      <div class="card card-deep">
        <div class="card-h"><h3>Edit Copy</h3><p>Sesuaikan teks utama di undangan.</p></div>

        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_copy">
          <div class="field"><label>Pembuka</label>
            <textarea name="invocation" rows="2"><?= e($design['copy_id']['invocation'] ?? '') ?></textarea></div>
          <div class="field"><label>Sambutan</label>
            <textarea name="welcome" rows="2"><?= e($design['copy_id']['welcome'] ?? '') ?></textarea></div>
          <div class="field"><label>Kisah Kami</label>
            <textarea name="story" rows="4"><?= e($design['copy_id']['story'] ?? '') ?></textarea></div>
          <div class="field"><label>Prompt RSVP</label>
            <textarea name="rsvp_prompt" rows="2"><?= e($design['rsvp_prompt'] ?? '') ?></textarea></div>
          <button class="btn btn-primary btn-block">Simpan Perubahan</button>
        </form>

        <?php if ($inv['tier'] === 'ai' && $inv['status'] === 'ready_for_preview'): ?>
          <hr style="border:0;border-top:1px solid rgba(92,58,30,.1);margin:20px 0;">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="regenerate">
            <button class="btn btn-outline btn-block" onclick="return confirm('Generasi ulang akan menimpa desain saat ini. Lanjutkan?');">
              ↻ Generasi Ulang Penuh
            </button>
            <p style="font-size:.78rem;color:var(--muted);margin-top:8px;text-align:center;">1× regenerasi penuh termasuk dalam paket AI.</p>
          </form>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
