<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = Auth::requireAdmin();

$tab = $_GET['tab'] ?? 'overview';

// ── Actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'flag_invitation') {
        $id = (int)$_POST['id'];
        DB::run("UPDATE invitations SET status='flagged' WHERE id = ?", [$id]);
        audit('invitation_flagged', (int)$admin['id'], 'invitation', $id);
        flash_set('info', 'Undangan ditandai sebagai flagged.');
    }
    if ($action === 'unflag_invitation') {
        $id = (int)$_POST['id'];
        DB::run("UPDATE invitations SET status='published' WHERE id = ?", [$id]);
        audit('invitation_unflagged', (int)$admin['id'], 'invitation', $id);
        flash_set('info', 'Undangan dipulihkan.');
    }
    if ($action === 'suspend_user') {
        $id = (int)$_POST['id'];
        if ($id !== (int)$admin['id']) {
            DB::run("UPDATE users SET status='suspended' WHERE id = ?", [$id]);
            audit('user_suspended', (int)$admin['id'], 'user', $id);
            flash_set('info', 'Pengguna disuspend.');
        }
    }
    if ($action === 'restore_user') {
        $id = (int)$_POST['id'];
        DB::run("UPDATE users SET status='active' WHERE id = ?", [$id]);
        audit('user_restored', (int)$admin['id'], 'user', $id);
        flash_set('info', 'Pengguna dipulihkan.');
    }
    if ($action === 'hide_message') {
        $id = (int)$_POST['id'];
        DB::run("UPDATE guestbook SET hidden = 1 - hidden WHERE id = ?", [$id]);
        audit('message_toggled', (int)$admin['id'], 'guestbook', $id);
    }
    redirect('admin/?tab=' . $tab);
}

// ── Data per tab ───────────────────────────────────────────
$counts = [
    'users'        => (int)DB::one("SELECT COUNT(*) AS c FROM users")['c'],
    'invitations'  => (int)DB::one("SELECT COUNT(*) AS c FROM invitations")['c'],
    'published'    => (int)DB::one("SELECT COUNT(*) AS c FROM invitations WHERE status='published'")['c'],
    'paid_orders'  => (int)DB::one("SELECT COUNT(*) AS c FROM orders WHERE status='paid'")['c'],
    'revenue'      => (int)(DB::one("SELECT COALESCE(SUM(amount),0) AS s FROM orders WHERE status='paid'")['s'] ?? 0),
    'flagged'      => (int)DB::one("SELECT COUNT(*) AS c FROM invitations WHERE status='flagged'")['c'],
    'pending_gen'  => (int)DB::one("SELECT COUNT(*) AS c FROM invitations WHERE status IN ('paid','generating')")['c'],
];

$page_title = 'Admin';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <span class="page-eyebrow">Admin Panel</span>
  <h1 class="page-title">Pusat <em>kendali</em>.</h1>
  <p class="page-desc">Kelola pengguna, undangan, generasi, dan moderasi konten.</p>

  <?php if ($msg = flash_get('info')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
  <?php endif; ?>

  <div class="tabs">
    <a href="?tab=overview"     class="<?= $tab === 'overview'     ? 'active' : '' ?>">Ringkasan</a>
    <a href="?tab=invitations"  class="<?= $tab === 'invitations'  ? 'active' : '' ?>">Undangan</a>
    <a href="?tab=users"        class="<?= $tab === 'users'        ? 'active' : '' ?>">Pengguna</a>
    <a href="?tab=generations"  class="<?= $tab === 'generations'  ? 'active' : '' ?>">Antrian AI</a>
    <a href="?tab=guestbook"    class="<?= $tab === 'guestbook'    ? 'active' : '' ?>">Buku Tamu</a>
    <a href="?tab=audit"        class="<?= $tab === 'audit'        ? 'active' : '' ?>">Audit Log</a>
  </div>

  <?php if ($tab === 'overview'): ?>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-num"><?= number_format($counts['users']) ?></div><div class="stat-label">Total Pengguna</div></div>
      <div class="stat-card"><div class="stat-num"><?= number_format($counts['invitations']) ?></div><div class="stat-label">Total Undangan</div></div>
      <div class="stat-card"><div class="stat-num" style="color:#4a9460;"><?= number_format($counts['published']) ?></div><div class="stat-label">Terpublikasi</div></div>
      <div class="stat-card"><div class="stat-num"><?= number_format($counts['paid_orders']) ?></div><div class="stat-label">Order Lunas</div></div>
      <div class="stat-card"><div class="stat-num" style="color:var(--gold);"><?= idr($counts['revenue']) ?></div><div class="stat-label">Pendapatan</div></div>
      <div class="stat-card"><div class="stat-num" style="color:#b94a4a;"><?= number_format($counts['flagged']) ?></div><div class="stat-label">Flagged</div></div>
      <div class="stat-card"><div class="stat-num"><?= number_format($counts['pending_gen']) ?></div><div class="stat-label">Antrian AI</div></div>
    </div>

    <div class="card" style="margin-top:24px;">
      <h3 style="font-size:1.2rem;margin-bottom:12px;">Pengaturan Saat Ini</h3>
      <table class="data">
        <tr><td><strong>Mode Pengembangan (OTP terlihat di layar)</strong></td><td><?= DEV_MODE_SHOW_OTP ? '✅ Aktif' : '❌ Nonaktif' ?></td></tr>
        <tr><td><strong>Lewati Pembayaran (auto-mark PAID)</strong></td><td><?= SKIP_PAYMENT ? '✅ Aktif' : '❌ Nonaktif' ?></td></tr>
        <tr><td><strong>Claude API Key</strong></td><td><?= CLAUDE_API_KEY ? '✅ Terkonfigurasi' : '⚠️ Belum (akan jatuh ke preset desain)' ?></td></tr>
        <tr><td><strong>Model AI</strong></td><td><code><?= e(CLAUDE_MODEL) ?></code></td></tr>
        <tr><td><strong>Harga Basic</strong></td><td><?= idr(PRICE_BASIC) ?></td></tr>
        <tr><td><strong>Harga AI</strong></td><td><?= idr(PRICE_AI) ?></td></tr>
      </table>
      <p style="font-size:.82rem;color:var(--muted);margin-top:14px;">
        Edit <code>config/config.php</code> untuk mengubah pengaturan ini. Setelah live, pastikan <code>DEV_MODE_SHOW_OTP</code> dan <code>SKIP_PAYMENT</code> dimatikan.
      </p>
    </div>

  <?php elseif ($tab === 'invitations'):
    $rows = DB::all(
      "SELECT i.*, u.email
       FROM invitations i JOIN users u ON u.id = i.user_id
       ORDER BY i.created_at DESC LIMIT 100"
    ); ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr>
          <th>ID</th><th>Pasangan</th><th>Pengguna</th><th>Tier</th>
          <th>Status</th><th>Tanggal</th><th>Views</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td>
              <strong><?= e(($r['groom_name'] ?? '—') . ' & ' . ($r['bride_name'] ?? '—')) ?></strong>
              <?php if ($r['slug']): ?>
                <div style="font-size:.78rem;color:var(--muted);">/v/<?= e($r['slug']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:.85rem;"><?= e($r['email']) ?></td>
            <td><?= $r['tier'] === 'ai' ? 'AI ✦' : 'Basic' ?></td>
            <td><span class="pill pill-<?= e($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
            <td style="font-size:.85rem;"><?= e(fmt_date_id($r['wedding_date'] ?? '')) ?></td>
            <td><?= number_format((int)$r['view_count']) ?></td>
            <td>
              <?php if ($r['status'] === 'published'): ?>
                <a class="btn btn-ghost btn-sm" target="_blank" href="<?= APP_URL ?>/v/<?= e($r['slug']) ?>">Buka</a>
                <form method="POST" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="flag_invitation">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-ghost btn-sm" style="color:#b94a4a;" onclick="return confirm('Tandai flagged?')">⚠ Flag</button>
                </form>
              <?php elseif ($r['status'] === 'flagged'): ?>
                <form method="POST" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unflag_invitation">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-ghost btn-sm">↺ Pulihkan</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab === 'users'):
    $rows = DB::all(
      "SELECT u.*,
         (SELECT COUNT(*) FROM invitations i WHERE i.user_id = u.id) AS inv_count
       FROM users u
       ORDER BY u.created_at DESC LIMIT 100"
    ); ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr>
          <th>ID</th><th>Email</th><th>Telepon</th><th>Status</th>
          <th>Admin?</th><th>Undangan</th><th>Daftar</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e($r['email']) ?></td>
            <td style="font-family:monospace;font-size:.85rem;"><?= e($r['phone_e164'] ?: '—') ?></td>
            <td><span class="pill pill-<?= $r['status'] === 'active' ? 'paid' : ($r['status'] === 'suspended' ? 'flagged' : 'draft') ?>"><?= e($r['status']) ?></span></td>
            <td><?= $r['is_admin'] ? '✓' : '—' ?></td>
            <td><?= number_format((int)$r['inv_count']) ?></td>
            <td style="font-size:.82rem;"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
            <td>
              <?php if ($r['id'] !== $admin['id']): ?>
                <?php if ($r['status'] === 'suspended'): ?>
                  <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="restore_user">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-ghost btn-sm">↺ Pulihkan</button>
                  </form>
                <?php else: ?>
                  <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="suspend_user">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-ghost btn-sm" style="color:#b94a4a;" onclick="return confirm('Suspend pengguna ini?')">Suspend</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <em style="color:var(--muted);font-size:.82rem;">— anda —</em>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab === 'generations'):
    $rows = DB::all(
      "SELECT g.*, i.groom_name, i.bride_name, i.tier
       FROM generations g
       JOIN invitations i ON i.id = g.invitation_id
       ORDER BY g.id DESC LIMIT 100"
    ); ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr>
          <th>ID</th><th>Undangan</th><th>Tier</th><th>Status</th>
          <th>Token In</th><th>Token Out</th><th>Model</th><th>Selesai</th>
        </tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">Belum ada generasi.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><strong><?= e($r['groom_name'] . ' & ' . $r['bride_name']) ?></strong></td>
            <td><?= $r['tier'] === 'ai' ? 'AI ✦' : 'Basic' ?></td>
            <td><span class="pill pill-<?= $r['status'] === 'succeeded' ? 'paid' : ($r['status'] === 'failed' ? 'flagged' : 'draft') ?>"><?= e($r['status']) ?></span>
              <?php if ($r['error_message']): ?>
                <div style="font-size:.78rem;color:#b94a4a;margin-top:4px;"><?= e(mb_strimwidth($r['error_message'], 0, 60, '…')) ?></div>
              <?php endif; ?>
            </td>
            <td><?= number_format((int)$r['tokens_in']) ?></td>
            <td><?= number_format((int)$r['tokens_out']) ?></td>
            <td style="font-family:monospace;font-size:.78rem;"><?= e($r['model_version'] ?: '—') ?></td>
            <td style="font-size:.82rem;"><?= e($r['finished_at'] ?? '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab === 'guestbook'):
    $rows = DB::all(
      "SELECT gb.*, i.slug, i.groom_name, i.bride_name
       FROM guestbook gb JOIN invitations i ON i.id = gb.invitation_id
       ORDER BY gb.id DESC LIMIT 100"
    ); ?>
    <div class="card">
      <h3 style="font-size:1.2rem;margin-bottom:12px;">Pesan Buku Tamu (50 terakhir)</h3>
      <?php if (empty($rows)): ?>
        <p style="color:var(--muted);">Belum ada pesan.</p>
      <?php else: foreach ($rows as $m): ?>
        <div style="padding:14px 0;border-bottom:1px solid rgba(92,58,30,.08);<?= $m['hidden'] ? 'opacity:.5;' : '' ?>">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <strong><?= e($m['guest_name']) ?>
              <span style="color:var(--muted);font-weight:400;font-size:.82rem;">
                untuk <?= e($m['groom_name'] . ' & ' . $m['bride_name']) ?>
              </span>
            </strong>
            <span style="font-size:.82rem;color:var(--muted);"><?= e(date('d M Y H:i', strtotime($m['created_at']))) ?></span>
          </div>
          <p style="font-size:.92rem;margin-bottom:6px;"><?= nl2br(e($m['message'])) ?></p>
          <form method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hide_message">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-ghost btn-sm"><?= $m['hidden'] ? 'Tampilkan' : 'Sembunyikan' ?></button>
          </form>
        </div>
      <?php endforeach; endif; ?>
    </div>

  <?php elseif ($tab === 'audit'):
    $rows = DB::all(
      "SELECT a.*, u.email
       FROM audit_log a LEFT JOIN users u ON u.id = a.actor_id
       ORDER BY a.id DESC LIMIT 200"
    ); ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr>
          <th>Waktu</th><th>Aksi</th><th>Pengguna</th><th>Subjek</th><th>Meta</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="font-family:monospace;font-size:.78rem;"><?= e(date('d/m H:i:s', strtotime($r['created_at']))) ?></td>
            <td><strong><?= e($r['action']) ?></strong></td>
            <td style="font-size:.85rem;"><?= e($r['email'] ?: '—') ?></td>
            <td style="font-size:.85rem;"><?= e($r['target_type']) ?> #<?= (int)$r['target_id'] ?></td>
            <td style="font-family:monospace;font-size:.78rem;color:var(--muted);"><?= e(mb_strimwidth($r['payload'] ?? '', 0, 60, '…')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
