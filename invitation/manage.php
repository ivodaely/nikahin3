<?php
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::requireUser();
$invId = (int)($_GET['id'] ?? 0);

$inv = DB::one("SELECT * FROM invitations WHERE id = ? AND user_id = ?", [$invId, (int)$user['id']]);
if (!$inv) { flash_set('info', 'Undangan tidak ditemukan.'); redirect('dashboard/'); }

// Add guest manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_guest') {
    csrf_check();
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $group = trim($_POST['group'] ?? '');
    if ($name) {
        $token = bin2hex(random_bytes(8));
        DB::insert(
            "INSERT INTO guests (invitation_id, name, phone, group_label, link_token) VALUES (?, ?, ?, ?, ?)",
            [$invId, $name, $phone ?: null, $group ?: null, $token]
        );
    }
    redirect('invitation/manage.php?id=' . $invId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_message') {
    csrf_check();
    $msgId = (int)($_POST['msg_id'] ?? 0);
    DB::run(
        "UPDATE guestbook SET hidden = 1 - hidden
         WHERE id = ? AND invitation_id = ?",
        [$msgId, $invId]
    );
    redirect('invitation/manage.php?id=' . $invId . '#guestbook');
}

$guests = DB::all("SELECT * FROM guests WHERE invitation_id = ? ORDER BY created_at DESC", [$invId]);
$messages = DB::all("SELECT * FROM guestbook WHERE invitation_id = ? ORDER BY created_at DESC", [$invId]);

$stats = [
    'total'    => count($guests),
    'yes'      => count(array_filter($guests, fn($g) => $g['rsvp_status'] === 'yes')),
    'no'       => count(array_filter($guests, fn($g) => $g['rsvp_status'] === 'no')),
    'maybe'    => count(array_filter($guests, fn($g) => $g['rsvp_status'] === 'maybe')),
    'pending'  => count(array_filter($guests, fn($g) => $g['rsvp_status'] === 'pending')),
    'attendees' => array_sum(array_map(fn($g) => $g['rsvp_status'] === 'yes' ? (int)$g['attendees'] : 0, $guests)),
    'views'    => (int)$inv['view_count'],
];

$page_title = 'Kelola Tamu';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <a href="<?= APP_URL ?>/dashboard/" style="color:var(--brown);font-size:.9rem;display:inline-block;margin-bottom:18px;">← Kembali ke Dasbor</a>

  <span class="page-eyebrow">Pengelolaan</span>
  <h1 class="page-title"><em><?= e($inv['groom_name']) ?></em> &amp; <em><?= e($inv['bride_name']) ?></em></h1>
  <p class="page-desc">
    Tautan publik: <strong><a target="_blank" href="<?= APP_URL ?>/v/<?= e($inv['slug']) ?>"><?= e(APP_URL . '/v/' . $inv['slug']) ?></a></strong>
  </p>

  <div class="stats-grid">
    <div class="stat-card"><div class="stat-num"><?= $stats['views'] ?></div><div class="stat-label">Views</div></div>
    <div class="stat-card"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">Total Tamu</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#4a9460;"><?= $stats['yes'] ?></div><div class="stat-label">RSVP Ya</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#b94a4a;"><?= $stats['no'] ?></div><div class="stat-label">RSVP Tidak</div></div>
    <div class="stat-card"><div class="stat-num"><?= $stats['attendees'] ?></div><div class="stat-label">Total Hadir</div></div>
  </div>

  <div class="tabs">
    <a href="#guests" class="active" id="tab-guests">Tamu &amp; RSVP</a>
    <a href="#guestbook" id="tab-gb">Buku Tamu</a>
  </div>

  <!-- Guests tab -->
  <section id="guests-pane">
    <div class="card" style="margin-bottom:18px;">
      <h3 style="font-size:1.2rem;margin-bottom:14px;">Tambah Tamu Manual</h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_guest">
        <div style="display:grid;grid-template-columns:2fr 2fr 1.5fr auto;gap:12px;align-items:end;">
          <div class="field" style="margin-bottom:0;"><label>Nama</label><input type="text" name="name" required></div>
          <div class="field" style="margin-bottom:0;"><label>Nomor HP</label><input type="tel" name="phone"></div>
          <div class="field" style="margin-bottom:0;"><label>Grup</label><input type="text" name="group" placeholder="Keluarga / Teman / Kantor"></div>
          <button class="btn btn-primary">+ Tambah</button>
        </div>
      </form>
    </div>

    <div class="table-wrap">
      <table class="data">
        <thead><tr>
          <th>Nama</th><th>Grup</th><th>Status</th><th>Hadir</th><th>Acara</th><th>Tautan</th>
        </tr></thead>
        <tbody>
        <?php if (empty($guests)): ?>
          <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);">Belum ada tamu yang ditambahkan atau RSVP.</td></tr>
        <?php else: foreach ($guests as $g): $guestUrl = APP_URL . '/v/' . $inv['slug'] . '/g/' . $g['link_token']; ?>
          <tr>
            <td><strong><?= e($g['name']) ?></strong>
              <?php if (!empty($g['phone'])): ?><div style="font-size:.82rem;color:var(--muted);"><?= e($g['phone']) ?></div><?php endif; ?>
            </td>
            <td><?= e($g['group_label'] ?: '—') ?></td>
            <td><span class="pill pill-<?= $g['rsvp_status'] === 'yes' ? 'paid' : ($g['rsvp_status'] === 'no' ? 'flagged' : 'draft') ?>"><?= e($g['rsvp_status']) ?></span></td>
            <td><?= $g['rsvp_status'] === 'yes' ? (int)$g['attendees'] : '—' ?></td>
            <td><?= e($g['event_choice'] ?? '—') ?></td>
            <td><button class="btn btn-ghost btn-sm" data-copy="<?= e($guestUrl) ?>">Salin tautan</button></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Guestbook tab -->
  <section id="guestbook-pane" style="display:none;margin-top:24px;">
    <div class="card">
      <h3 style="font-size:1.2rem;margin-bottom:14px;">Pesan Buku Tamu (<?= count($messages) ?>)</h3>
      <?php if (empty($messages)): ?>
        <p style="color:var(--muted);">Belum ada pesan.</p>
      <?php else: foreach ($messages as $m): ?>
        <div style="padding:14px 0;border-bottom:1px solid rgba(92,58,30,.08);<?= $m['hidden'] ? 'opacity:.5;' : '' ?>">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <strong><?= e($m['guest_name']) ?></strong>
            <span style="font-size:.82rem;color:var(--muted);"><?= e(date('d M Y H:i', strtotime($m['created_at']))) ?></span>
          </div>
          <p style="font-size:.92rem;margin-bottom:8px;"><?= nl2br(e($m['message'])) ?></p>
          <form method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_message">
            <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-ghost btn-sm"><?= $m['hidden'] ? 'Tampilkan' : 'Sembunyikan' ?></button>
          </form>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </section>
</div>

<script>
$('#tab-guests, #tab-gb').on('click', function(e){
  e.preventDefault();
  $('.tabs a').removeClass('active');
  $(this).addClass('active');
  if (this.id === 'tab-gb') { $('#guests-pane').hide(); $('#guestbook-pane').show(); }
  else { $('#guests-pane').show(); $('#guestbook-pane').hide(); }
});
$('[data-copy]').on('click', function(e){
  e.preventDefault();
  var v = $(this).data('copy'); var $b = $(this); var t = $b.text();
  if (navigator.clipboard) navigator.clipboard.writeText(v);
  $b.text('Tersalin ✓'); setTimeout(function(){ $b.text(t); }, 1500);
});
if (location.hash === '#guestbook') $('#tab-gb').click();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
