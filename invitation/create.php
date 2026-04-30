<?php
require_once __DIR__ . '/../includes/auth.php';
$user = Auth::requireUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tier = ($_POST['tier'] ?? 'basic') === 'ai' ? 'ai' : 'basic';
    // Create draft invitation
    $invId = DB::insert(
        "INSERT INTO invitations (user_id, tier, status) VALUES (?, ?, 'draft')",
        [(int)$user['id'], $tier]
    );
    audit('invitation_created', (int)$user['id'], 'invitation', $invId, ['tier' => $tier]);
    redirect('invitation/form.php?id=' . $invId);
}

$page_title = 'Pilih Paket';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <a href="<?= APP_URL ?>/dashboard/" style="color:var(--brown);font-size:.9rem;display:inline-block;margin-bottom:18px;">← Kembali ke Dasbor</a>

  <span class="page-eyebrow">Langkah 1 dari 7</span>
  <h1 class="page-title">Pilih <em>paket</em> Anda.</h1>
  <p class="page-desc">Anda dapat memulai dengan paket apa pun — semua data yang Anda isi akan disimpan dan dapat dipindahkan.</p>

  <form method="POST" action="<?= APP_URL ?>/invitation/create.php">
    <?= csrf_field() ?>
    <div class="tier-grid">
      <button type="submit" name="tier" value="basic" class="tier-card" style="text-align:left;cursor:pointer;border:2px solid transparent;width:100%;font-family:inherit;">
        <div class="tier-name">Basic Template</div>
        <div class="tier-tagline">Pilih template yang sudah dikurasi, isi detail Anda.</div>
        <div class="tier-price"><?= idr(PRICE_BASIC) ?></div>
        <ul class="tier-features">
          <li>Template kurasi siap pakai</li>
          <li>Personalisasi nama, tanggal, foto</li>
          <li>RSVP &amp; Daftar tamu</li>
          <li>Buku tamu &amp; rekening angpau</li>
          <li>Berlaku 12 bulan</li>
        </ul>
        <span class="btn btn-outline btn-block">Pilih Basic</span>
      </button>

      <button type="submit" name="tier" value="ai" class="tier-card tier-ai" style="text-align:left;cursor:pointer;border:2px solid var(--gold);width:100%;font-family:inherit;">
        <span class="tier-badge">AI ✦</span>
        <div class="tier-name">AI-Generated</div>
        <div class="tier-tagline">Desain orisinal yang dirancang AI Claude untuk Anda.</div>
        <div class="tier-price" style="color:var(--gold-light);"><?= idr(PRICE_AI) ?></div>
        <ul class="tier-features">
          <li>Semua fitur Basic</li>
          <li>Desain unik dirancang AI</li>
          <li>Palet warna sesuai preferensi</li>
          <li>Salinan teks dalam Bahasa Indonesia</li>
          <li>1 regenerasi penuh + edit elemen</li>
        </ul>
        <span class="btn btn-primary btn-block">Pilih AI</span>
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
