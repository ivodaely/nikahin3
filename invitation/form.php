<?php
require_once __DIR__ . '/../includes/auth.php';
$user = Auth::requireUser();

$invId = (int)($_GET['id'] ?? 0);
$inv = DB::one(
    "SELECT * FROM invitations WHERE id = ? AND user_id = ?",
    [$invId, (int)$user['id']]
);
if (!$inv) {
    flash_set('info', 'Undangan tidak ditemukan.');
    redirect('dashboard/');
}

// Decode existing profile
$profile = $inv['profile_json'] ? json_decode($inv['profile_json'], true) : [];
if (!is_array($profile)) $profile = [];

// Pull existing assets
$assets = DB::all(
    "SELECT * FROM invitation_assets WHERE invitation_id = ? ORDER BY type, position, id",
    [$invId]
);
$assetsByType = [];
foreach ($assets as $a) {
    $assetsByType[$a['type']][] = $a;
}

// Submission of full form (final step or "Continue to Payment")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    csrf_check();
    // The latest profile_json should have been saved via api/save-draft.php; trust DB.
    // Refresh:
    $inv = DB::one("SELECT * FROM invitations WHERE id = ?", [$invId]);
    if (empty($inv['groom_name']) || empty($inv['bride_name']) || empty($inv['wedding_date'])) {
        flash_set('info', 'Mohon lengkapi nama dan tanggal pernikahan.');
        redirect('invitation/form.php?id=' . $invId);
    }
    // Create order
    $price = $inv['tier'] === 'ai' ? PRICE_AI : PRICE_BASIC;
    $orderId = DB::insert(
        "INSERT INTO orders (user_id, invitation_id, amount, status) VALUES (?, ?, ?, 'pending_payment')",
        [(int)$user['id'], $invId, $price]
    );
    DB::run("UPDATE invitations SET status='pending_payment' WHERE id = ?", [$invId]);

    // SKIP_PAYMENT shortcut: mark paid immediately
    if (SKIP_PAYMENT) {
        DB::run("UPDATE orders SET status='paid', paid_at=NOW() WHERE id = ?", [$orderId]);
        DB::run("UPDATE invitations SET status='paid' WHERE id = ?", [$invId]);
        audit('payment_skipped', (int)$user['id'], 'invitation', $invId);
        redirect('invitation/generate.php?id=' . $invId);
    } else {
        redirect('invitation/payment.php?id=' . $invId);
    }
}

$page_title = 'Buat Undangan';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <a href="<?= APP_URL ?>/dashboard/" style="color:var(--brown);font-size:.9rem;display:inline-block;margin-bottom:18px;">← Kembali ke Dasbor</a>

  <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;margin-bottom:8px;">
    <div>
      <span class="page-eyebrow">Paket: <?= $inv['tier'] === 'ai' ? 'AI-Generated ✦' : 'Basic Template' ?></span>
      <h1 class="page-title">Lengkapi <em>profil</em> Anda.</h1>
    </div>
    <span id="save-status" style="font-size:.85rem;color:var(--muted);">Tersimpan otomatis</span>
  </div>
  <p class="page-desc">Setiap perubahan disimpan otomatis. Anda dapat menutup halaman ini dan melanjutkan kapan saja.</p>

  <!-- Steps bar -->
  <div class="steps-bar">
    <?php $stepLabels = ['Pasangan & Jadwal','Mempelai Pria','Mempelai Wanita','Tema','Pre-Wedding','Angpau','Review']; ?>
    <?php foreach ($stepLabels as $i => $label): $sn = $i + 1; ?>
      <div class="step <?= $sn === 1 ? 'is-active' : '' ?>" data-step="<?= $sn ?>">
        <span class="step-num"><?= $sn ?></span>
        <span class="step-label"><?= e($label) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <form id="invitation-form"
        data-invitation-id="<?= (int)$invId ?>"
        data-save-url="<?= APP_URL ?>/api/save-draft.php"
        method="POST" action="<?= APP_URL ?>/invitation/form.php?id=<?= $invId ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="submit">

    <!-- ============ STEP 1: Couple + Schedule ============ -->
    <div class="step-pane is-active" data-step="1">
      <div class="card card-deep">
        <div class="card-h"><h3>Pasangan &amp; Jadwal</h3><p>Identitas dan tanggal acara.</p></div>

        <div class="field-row">
          <div class="field">
            <label>Nama Lengkap Mempelai Pria</label>
            <input type="text" name="profile[groom][name]" value="<?= e($profile['groom']['name'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Nama Panggilan Pria</label>
            <input type="text" name="profile[groom][short]" value="<?= e($profile['groom']['short'] ?? '') ?>" required>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Nama Lengkap Mempelai Wanita</label>
            <input type="text" name="profile[bride][name]" value="<?= e($profile['bride']['name'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Nama Panggilan Wanita</label>
            <input type="text" name="profile[bride][short]" value="<?= e($profile['bride']['short'] ?? '') ?>" required>
          </div>
        </div>

        <h4 style="margin-top:24px;font-size:1.1rem;">Akad / Pemberkatan</h4>
        <div class="field-row">
          <div class="field"><label>Tanggal</label>
            <input type="date" name="profile[schedule][akad][date]" value="<?= e($profile['schedule']['akad']['date'] ?? '') ?>" required>
          </div>
          <div class="field"><label>Waktu</label>
            <input type="time" name="profile[schedule][akad][time]" value="<?= e($profile['schedule']['akad']['time'] ?? '') ?>">
          </div>
        </div>
        <div class="field"><label>Lokasi Akad</label>
          <input type="text" name="profile[schedule][akad][venue]" value="<?= e($profile['schedule']['akad']['venue'] ?? '') ?>" placeholder="Masjid / Gereja / Pura / Rumah">
        </div>

        <h4 style="margin-top:24px;font-size:1.1rem;">Resepsi (opsional)</h4>
        <div class="field-row">
          <div class="field"><label>Tanggal</label>
            <input type="date" name="profile[schedule][resepsi][date]" value="<?= e($profile['schedule']['resepsi']['date'] ?? '') ?>">
          </div>
          <div class="field"><label>Waktu</label>
            <input type="time" name="profile[schedule][resepsi][time]" value="<?= e($profile['schedule']['resepsi']['time'] ?? '') ?>">
          </div>
        </div>
        <div class="field"><label>Lokasi Resepsi</label>
          <input type="text" name="profile[schedule][resepsi][venue]" value="<?= e($profile['schedule']['resepsi']['venue'] ?? '') ?>" placeholder="Gedung / Hotel / Rumah">
        </div>

        <div class="field"><label>Zona Waktu</label>
          <select name="profile[schedule][timezone]">
            <?php foreach (['WIB','WITA','WIT'] as $tz):
              $sel = ($profile['schedule']['timezone'] ?? 'WIB') === $tz ? 'selected' : ''; ?>
              <option value="<?= $tz ?>" <?= $sel ?>><?= $tz ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-actions">
          <span></span>
          <button class="btn btn-primary btn-next" type="button">Lanjut →</button>
        </div>
      </div>
    </div>

    <!-- ============ STEP 2: Groom ============ -->
    <div class="step-pane" data-step="2">
      <div class="card card-deep">
        <div class="card-h"><h3>Profil Mempelai Pria</h3><p>Detail keluarga dan pribadi.</p></div>
        <div class="field-row">
          <div class="field"><label>Nama Ayah</label>
            <input type="text" name="profile[groom][father]" value="<?= e($profile['groom']['father'] ?? '') ?>"></div>
          <div class="field"><label>Nama Ibu</label>
            <input type="text" name="profile[groom][mother]" value="<?= e($profile['groom']['mother'] ?? '') ?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Anak ke- (urutan)</label>
            <input type="text" name="profile[groom][family_order]" placeholder="Putra ketiga dari…" value="<?= e($profile['groom']['family_order'] ?? '') ?>"></div>
          <div class="field"><label>Agama</label>
            <select name="profile[groom][religion]">
              <?php foreach (['','Islam','Kristen','Katolik','Hindu','Buddha','Konghucu','Lainnya'] as $r):
                $sel = ($profile['groom']['religion'] ?? '') === $r ? 'selected' : ''; ?>
                <option value="<?= e($r) ?>" <?= $sel ?>><?= e($r ?: '— pilih —') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>Warna Favorit</label>
            <input type="text" name="profile[groom][fav_color]" placeholder="cth: navy, sage green, dusty pink" value="<?= e($profile['groom']['fav_color'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>Profil Singkat</label>
          <textarea name="profile[groom][bio]" rows="3" placeholder="Paragraf singkat yang ingin Anda tunjukkan ke tamu…"><?= e($profile['groom']['bio'] ?? '') ?></textarea>
        </div>

        <label style="font-size:.8rem;font-weight:600;color:var(--brown);letter-spacing:.08em;text-transform:uppercase;display:block;margin-top:12px;margin-bottom:8px;">Foto (1–3)</label>
        <div class="photo-uploader" data-slot="groom_photo" data-invitation-id="<?= $invId ?>" data-upload-url="<?= APP_URL ?>/api/upload.php">
          <div class="photo-grid">
            <?php foreach ($assetsByType['groom_photo'] ?? [] as $a): ?>
              <div class="photo-thumb" data-id="<?= (int)$a['id'] ?>">
                <img src="<?= e($a['url']) ?>" alt="">
                <button class="photo-remove" data-id="<?= (int)$a['id'] ?>">✕</button>
              </div>
            <?php endforeach; ?>
            <label class="photo-add">
              + Tambah Foto
              <input type="file" class="photo-input" accept="image/*" multiple>
            </label>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn-outline btn-prev" type="button">← Sebelumnya</button>
          <button class="btn btn-primary btn-next" type="button">Lanjut →</button>
        </div>
      </div>
    </div>

    <!-- ============ STEP 3: Bride ============ -->
    <div class="step-pane" data-step="3">
      <div class="card card-deep">
        <div class="card-h"><h3>Profil Mempelai Wanita</h3><p>Detail keluarga dan pribadi.</p></div>
        <div class="field-row">
          <div class="field"><label>Nama Ayah</label>
            <input type="text" name="profile[bride][father]" value="<?= e($profile['bride']['father'] ?? '') ?>"></div>
          <div class="field"><label>Nama Ibu</label>
            <input type="text" name="profile[bride][mother]" value="<?= e($profile['bride']['mother'] ?? '') ?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Anak ke- (urutan)</label>
            <input type="text" name="profile[bride][family_order]" placeholder="Putri kedua dari…" value="<?= e($profile['bride']['family_order'] ?? '') ?>"></div>
          <div class="field"><label>Agama</label>
            <select name="profile[bride][religion]">
              <?php foreach (['','Islam','Kristen','Katolik','Hindu','Buddha','Konghucu','Lainnya'] as $r):
                $sel = ($profile['bride']['religion'] ?? '') === $r ? 'selected' : ''; ?>
                <option value="<?= e($r) ?>" <?= $sel ?>><?= e($r ?: '— pilih —') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>Warna Favorit</label>
            <input type="text" name="profile[bride][fav_color]" placeholder="cth: dusty pink, champagne" value="<?= e($profile['bride']['fav_color'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>Profil Singkat</label>
          <textarea name="profile[bride][bio]" rows="3"><?= e($profile['bride']['bio'] ?? '') ?></textarea>
        </div>

        <label style="font-size:.8rem;font-weight:600;color:var(--brown);letter-spacing:.08em;text-transform:uppercase;display:block;margin-top:12px;margin-bottom:8px;">Foto (1–3)</label>
        <div class="photo-uploader" data-slot="bride_photo" data-invitation-id="<?= $invId ?>" data-upload-url="<?= APP_URL ?>/api/upload.php">
          <div class="photo-grid">
            <?php foreach ($assetsByType['bride_photo'] ?? [] as $a): ?>
              <div class="photo-thumb" data-id="<?= (int)$a['id'] ?>">
                <img src="<?= e($a['url']) ?>" alt="">
                <button class="photo-remove" data-id="<?= (int)$a['id'] ?>">✕</button>
              </div>
            <?php endforeach; ?>
            <label class="photo-add">
              + Tambah Foto
              <input type="file" class="photo-input" accept="image/*" multiple>
            </label>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn-outline btn-prev" type="button">← Sebelumnya</button>
          <button class="btn btn-primary btn-next" type="button">Lanjut →</button>
        </div>
      </div>
    </div>

    <!-- ============ STEP 4: Theme ============ -->
    <div class="step-pane" data-step="4">
      <div class="card card-deep">
        <div class="card-h"><h3>Tema &amp; Preferensi Visual</h3><p>Bantu AI memahami selera Anda.</p></div>
        <div class="field"><label>Tema Preset</label>
          <select name="profile[theme][preset]">
            <?php foreach (['Classic','Floral','Minimalist','Modern','Javanese','Sundanese','Batak','Balinese','Custom'] as $t):
              $sel = ($profile['theme']['preset'] ?? '') === $t ? 'selected' : ''; ?>
              <option value="<?= e($t) ?>" <?= $sel ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-row">
          <div class="field"><label>Palet Warna 1</label>
            <input type="text" name="profile[theme][color1]" placeholder="cth: dusty rose, #E8B4B8" value="<?= e($profile['theme']['color1'] ?? '') ?>"></div>
          <div class="field"><label>Palet Warna 2</label>
            <input type="text" name="profile[theme][color2]" placeholder="cth: champagne, #F0D9A8" value="<?= e($profile['theme']['color2'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>Brief Desain</label>
          <textarea name="profile[theme][brief]" rows="4" placeholder="cth: soft pastel, watercolor florals, gold accents…"><?= e($profile['theme']['brief'] ?? '') ?></textarea>
          <div class="field-help">Semakin detail, semakin baik AI memahami selera Anda.</div>
        </div>
        <div class="form-actions">
          <button class="btn btn-outline btn-prev" type="button">← Sebelumnya</button>
          <button class="btn btn-primary btn-next" type="button">Lanjut →</button>
        </div>
      </div>
    </div>

    <!-- ============ STEP 5: Prewedding ============ -->
    <div class="step-pane" data-step="5">
      <div class="card card-deep">
        <div class="card-h"><h3>Foto Pre-Wedding</h3><p>Hingga 10 foto, maksimum 5 MB per file.</p></div>
        <div class="photo-uploader" data-slot="prewedding" data-invitation-id="<?= $invId ?>" data-upload-url="<?= APP_URL ?>/api/upload.php">
          <div class="photo-grid">
            <?php foreach ($assetsByType['prewedding'] ?? [] as $a): ?>
              <div class="photo-thumb" data-id="<?= (int)$a['id'] ?>">
                <img src="<?= e($a['url']) ?>" alt="">
                <button class="photo-remove" data-id="<?= (int)$a['id'] ?>">✕</button>
              </div>
            <?php endforeach; ?>
            <label class="photo-add">
              + Tambah Foto
              <input type="file" class="photo-input" accept="image/*" multiple>
            </label>
          </div>
        </div>
        <p style="font-size:.85rem;color:var(--muted);margin-top:14px;">
          Tidak punya foto pre-wedding? Tidak masalah — AI dapat membuat ilustrasi tematik untuk Anda.
        </p>
        <div class="form-actions">
          <button class="btn btn-outline btn-prev" type="button">← Sebelumnya</button>
          <button class="btn btn-primary btn-next" type="button">Lanjut →</button>
        </div>
      </div>
    </div>

    <!-- ============ STEP 6: Gift ============ -->
    <div class="step-pane" data-step="6">
      <div class="card card-deep">
        <div class="card-h"><h3>Rekening Angpau Digital</h3><p>Opsional. Bank atau e-wallet untuk hadiah dari tamu.</p></div>
        <div class="gift-rows">
          <?php
          $giftRows = $profile['gift'] ?? [];
          if (empty($giftRows)) $giftRows = [['provider' => '', 'number' => '', 'holder' => '']];
          foreach ($giftRows as $i => $g): ?>
            <div class="gift-row">
              <div class="field"><label>Bank / E-wallet</label>
                <input type="text" name="profile[gift][<?= $i ?>][provider]" value="<?= e($g['provider'] ?? '') ?>" placeholder="BCA, GoPay, OVO…"></div>
              <div class="field"><label>Nomor</label>
                <input type="text" name="profile[gift][<?= $i ?>][number]" value="<?= e($g['number'] ?? '') ?>" placeholder="123 456 7890"></div>
              <div class="field"><label>Atas Nama</label>
                <input type="text" name="profile[gift][<?= $i ?>][holder]" value="<?= e($g['holder'] ?? '') ?>" placeholder="Nama pemilik"></div>
              <button class="btn-remove-gift" title="Hapus">✕</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-ghost btn-sm btn-add-gift" style="margin-top:8px;">+ Tambah Rekening</button>

        <div class="form-actions">
          <button class="btn btn-outline btn-prev" type="button">← Sebelumnya</button>
          <button class="btn btn-primary btn-next" type="button">Lanjut →</button>
        </div>
      </div>
    </div>

    <!-- ============ STEP 7: Review ============ -->
    <div class="step-pane" data-step="7">
      <div class="card card-deep">
        <div class="card-h"><h3>Tinjau Sebelum Lanjut</h3><p>Pastikan semua data sudah benar.</p></div>
        <p style="background:var(--ecru);padding:14px 18px;border-radius:8px;color:var(--brown);font-size:.92rem;">
          Anda dapat kembali ke langkah sebelumnya untuk mengedit. Setelah klik <strong>Lanjut ke Pembayaran</strong>,
          undangan akan dibuat dan tidak dapat dibatalkan setelah pembayaran berhasil.
        </p>

        <div style="display:grid;gap:12px;margin-top:18px;font-size:.95rem;">
          <div><strong>Paket:</strong> <?= $inv['tier'] === 'ai' ? 'AI-Generated ✦' : 'Basic Template' ?></div>
          <div><strong>Total:</strong> <?= idr($inv['tier'] === 'ai' ? PRICE_AI : PRICE_BASIC) ?></div>
        </div>

        <?php if (SKIP_PAYMENT): ?>
          <div class="alert alert-info" style="margin-top:18px;">
            🔧 <em>Mode Pengembangan</em> — Pembayaran dilewati. Tombol di bawah akan langsung menandai sebagai <strong>PAID</strong> dan memulai generasi AI.
          </div>
        <?php endif; ?>

        <div class="form-actions">
          <button class="btn btn-outline btn-prev" type="button">← Sebelumnya</button>
          <button class="btn btn-primary" type="submit">
            <?= SKIP_PAYMENT ? 'Tandai Lunas &amp; Buat Undangan →' : 'Lanjut ke Pembayaran →' ?>
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  window.NIKAHIN_CSRF = '<?= e(csrf_token()) ?>';
  window.NIKAHIN_REMOVE_ASSET_URL = '<?= APP_URL ?>/api/upload.php?action=delete';
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
