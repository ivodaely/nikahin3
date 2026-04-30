<?php
require_once __DIR__ . '/includes/functions.php';

// Pull recently-published invitations for the gallery
$published = DB::all(
    "SELECT id, slug, groom_name, bride_name, wedding_date, design_json, view_count
     FROM invitations
     WHERE status = 'published'
     ORDER BY published_at DESC
     LIMIT 9"
);

// Helper: pull palette from design_json or fall back to a rotating preset
function gallery_palette(?string $designJson, int $idx): array {
    $presets = [
        ['#F0D9A8', '#E8C4A0', '#2C1A0E'],   // champagne
        ['#D8C4B0', '#B89878', '#FFFDF8'],   // mocha
        ['#2C1A0E', '#5C3A1E', '#E8C4A0'],   // dark
        ['#F4EDE0', '#EAD9BC', '#2C1A0E'],   // cream
        ['#E8C4A0', '#C8943A', '#FFFDF8'],   // sunset
        ['#FAF6EE', '#E8C882', '#2C1A0E'],   // ivory
    ];
    if ($designJson) {
        $d = json_decode($designJson, true);
        if (isset($d['palette'])) {
            return [
                $d['palette']['primary']   ?? $presets[$idx % 6][0],
                $d['palette']['secondary'] ?? $presets[$idx % 6][1],
                $d['palette']['ink']       ?? $presets[$idx % 6][2],
            ];
        }
    }
    return $presets[$idx % 6];
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="nikahin — Buat undangan pernikahan digital yang unik dengan kecerdasan buatan. Desain eksklusif, tampilan elegan.">
  <title>nikahin — AI-Powered E-Wedding Invitation</title>

  <link rel="preconnect" href="https://api.fontshare.com">
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@900,800,700,500,400&f[]=general-sans@600,500,400&display=swap" rel="stylesheet">

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <link rel="stylesheet" href="<?= APP_URL ?>/css/style.css">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<header class="navbar" id="navbar">
  <div class="navbar-inner">
    <a href="#hero" class="navbar-logo">nika<span>hin</span></a>
    <ul class="navbar-menu">
      <li><a href="#hero" class="active">Home</a></li>
      <li><a href="#designs">Design</a></li>
      <li><a href="#pricing">Price</a></li>
      <li><a href="#about">About</a></li>
    </ul>
    <div class="navbar-cta">
      <a href="<?= APP_URL ?>/auth/login.php" class="navbar-login">Masuk</a>
      <a href="<?= APP_URL ?>/auth/register.php" class="btn-primary">
        <span>Mulai Sekarang</span>
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1 7h12M7 1l6 6-6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
    <button class="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
  <div class="navbar-mobile">
    <a href="#hero">Home</a>
    <a href="#designs">Design</a>
    <a href="#pricing">Price</a>
    <a href="#about">About</a>
    <a href="<?= APP_URL ?>/auth/login.php">Masuk</a>
    <a href="<?= APP_URL ?>/auth/register.php" style="color:var(--gold);">Mulai Sekarang →</a>
  </div>
</header>

<!-- ===== HERO ===== -->
<section class="hero" id="hero">
  <div class="hero-grid">
    <div class="hero-content">
      <div class="hero-eyebrow">
        <span class="dot"></span> AI-Powered • Dirancang Khusus Untuk Anda
      </div>
      <h1 class="hero-title">Setiap kisah cinta pantas mendapatkan <em>undangan yang unik</em>.</h1>
      <p class="hero-desc">
        nikahin menggunakan kecerdasan buatan untuk merancang undangan pernikahan digital
        yang sepenuhnya disesuaikan dengan profil, gaya, dan kisah Anda berdua —
        bukan sekadar template yang sama dengan ribuan pasangan lain.
      </p>
      <div class="hero-actions">
        <a href="<?= APP_URL ?>/auth/register.php" class="btn-gold">
          <span>Coba Sekarang</span>
          <svg width="16" height="16" viewBox="0 0 14 14" fill="none"><path d="M1 7h12M7 1l6 6-6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <a href="#designs" class="btn-outline">
          <span>Lihat Galeri</span>
        </a>
      </div>
      <div class="hero-stats">
        <div>
          <div class="hero-stat-num"><?= count($published) > 0 ? number_format(DB::one("SELECT COUNT(*) AS c FROM invitations WHERE status='published'")['c']) : '∞' ?></div>
          <div class="hero-stat-label">Undangan Diterbitkan</div>
        </div>
        <div>
          <div class="hero-stat-num">15<sub style="font-size:50%;">m</sub></div>
          <div class="hero-stat-label">Selesai Dalam Menit</div>
        </div>
        <div>
          <div class="hero-stat-num">100%</div>
          <div class="hero-stat-label">Desain Unik</div>
        </div>
      </div>
    </div>

    <div class="hero-mockup-wrapper">
      <div class="hero-mockup">
        <div class="hero-mockup-screen">
          <div class="hero-mockup-tag">The Wedding Of</div>
          <div class="hero-mockup-names">Anya</div>
          <div class="hero-mockup-and">&amp;</div>
          <div class="hero-mockup-names">Rafi</div>
          <div class="hero-mockup-date">25 • DES • 2026</div>
        </div>
      </div>
      <div class="hero-floating-badge b1">
        <span class="icon">AI</span>
        <span>Desain Unik</span>
      </div>
      <div class="hero-floating-badge b2">
        <span class="icon">✓</span>
        <span>Mobile Ready</span>
      </div>
    </div>
  </div>
</section>

<!-- ===== DESIGNS GALLERY ===== -->
<section id="designs">
  <div class="container">
    <span class="section-eyebrow">Galeri Karya</span>
    <h2 class="section-title">Setiap undangan adalah <em>satu-satunya</em>.</h2>
    <p class="section-desc">
      Pilih dari undangan yang sudah diterbitkan sebagai inspirasi —
      atau buat milik Anda sendiri dalam hitungan menit.
    </p>

    <div class="designs-grid">
      <?php if (empty($published)): ?>
        <!-- Sample placeholders if there are no published yet -->
        <?php for ($i = 0; $i < 6; $i++):
            $names = [['Anya','Rafi'],['Sasha','Bima'],['Maya','Dimas'],['Putri','Arya'],['Lina','Reza'],['Ayu','Eka']];
            $dates = ['25 DES 2026','12 OKT 2026','03 NOV 2026','19 SEP 2026','08 AGS 2026','22 JUL 2026'];
            $tone  = 't' . (($i % 6) + 1);
            $n     = $names[$i];
        ?>
        <div class="design-card">
          <div class="design-card-cover <?= $tone ?>">
            <div class="design-card-tag">The Wedding Of</div>
            <div class="design-card-names"><?= e($n[0]) ?></div>
            <div class="design-card-and">&amp;</div>
            <div class="design-card-names"><?= e($n[1]) ?></div>
            <div class="design-card-date"><?= $dates[$i] ?></div>
          </div>
          <div class="design-card-overlay"><span>Contoh Galeri →</span></div>
        </div>
        <?php endfor; ?>
      <?php else: ?>
        <?php foreach ($published as $i => $inv):
            [$bg1, $bg2, $ink] = gallery_palette($inv['design_json'], $i);
            $url = APP_URL . '/v/' . $inv['slug'];
        ?>
        <div class="design-card" data-demo="<?= e($url) ?>">
          <div class="design-card-cover" style="background:linear-gradient(170deg, <?= e($bg1) ?>, <?= e($bg2) ?>);color:<?= e($ink) ?>;">
            <div class="design-card-tag">The Wedding Of</div>
            <div class="design-card-names"><?= e(mb_strimwidth($inv['groom_name'] ?? 'Mempelai', 0, 14, '')) ?></div>
            <div class="design-card-and">&amp;</div>
            <div class="design-card-names"><?= e(mb_strimwidth($inv['bride_name'] ?? 'Mempelai', 0, 14, '')) ?></div>
            <div class="design-card-date"><?= e(strtoupper(fmt_date_id($inv['wedding_date'] ?? null))) ?></div>
          </div>
          <div class="design-card-overlay"><span>Lihat Undangan →</span></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===== PRICING ===== -->
<section id="pricing">
  <div class="container">
    <span class="section-eyebrow">Pilih Paket</span>
    <h2 class="section-title">Harga yang <em>sederhana</em>, hasil yang luar biasa.</h2>
    <p class="section-desc">
      Dua paket yang berbeda secara substantif — bukan hanya nama. Pilih yang paling cocok untuk hari spesial Anda.
    </p>

    <div class="pricing-grid">
      <div class="pricing-card">
        <div class="pricing-name">Basic Template</div>
        <div class="pricing-tagline">Pilih template kurasi, isi detail, lalu publikasikan.</div>
        <div class="pricing-price">
          <span class="pricing-price-curr">IDR</span>
          <span class="pricing-price-big">50K</span>
        </div>
        <ul class="pricing-features">
          <li>Template kurasi siap pakai</li>
          <li>Personalisasi nama, tanggal, foto</li>
          <li>RSVP &amp; daftar tamu</li>
          <li>Tautan personal per tamu</li>
          <li>Akun untuk angpau digital</li>
          <li>Berlaku 12 bulan</li>
        </ul>
        <a href="<?= APP_URL ?>/auth/register.php" class="btn-outline" style="display:flex;justify-content:center;width:100%;">Mulai dengan Basic</a>
      </div>

      <div class="pricing-card premium">
        <div class="pricing-badge">Recommended</div>
        <div class="pricing-name">AI-Generated ✦</div>
        <div class="pricing-tagline">Desain unik dirancang AI dari profil &amp; gaya Anda.</div>
        <div class="pricing-price">
          <span class="pricing-price-curr">IDR</span>
          <span class="pricing-price-big">150K</span>
        </div>
        <ul class="pricing-features">
          <li>Semua fitur paket Basic</li>
          <li>Desain orisinal dirancang AI Claude</li>
          <li>Palet warna sesuai preferensi</li>
          <li>Salinan teks dalam Bahasa Indonesia</li>
          <li>1 regenerasi penuh + edit elemen</li>
          <li>Dukungan prioritas</li>
        </ul>
        <a href="<?= APP_URL ?>/auth/register.php" class="btn-gold" style="display:flex;justify-content:center;width:100%;">
          <span>Mulai dengan AI</span>
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1 7h12M7 1l6 6-6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ===== REGISTER ===== -->
<section id="register">
  <div class="container">
    <div class="register-grid">
      <div>
        <span class="section-eyebrow">Mulai Hari Ini</span>
        <h2 class="section-title">Daftar dalam <em>satu menit</em>.</h2>
        <p class="section-desc">
          Verifikasi nomor Anda dengan kode OTP — tidak perlu kata sandi.
          Mulai membuat undangan Anda segera setelah login.
        </p>
      </div>
      <div class="register-form-card">
        <h3>Buat Akun</h3>
        <p class="muted">Masukkan email dan nomor HP — kami akan kirim kode OTP.</p>
        <form action="<?= APP_URL ?>/auth/register.php" method="POST">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="anda@email.com" required>
          </div>
          <div class="form-group">
            <label>Nomor HP</label>
            <input type="tel" name="phone" placeholder="+62 8xx xxxx xxxx" required>
          </div>
          <button type="submit" class="btn-gold form-submit" style="display:flex;justify-content:center;width:100%;">
            Kirim OTP →
          </button>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- ===== ABOUT ===== -->
<section id="about">
  <div class="container">
    <span class="section-eyebrow">Tentang Kami</span>
    <h2 class="section-title">Setiap pasangan punya kisah <em>yang unik</em>. Undangan Anda juga harus begitu.</h2>
    <p class="section-desc" style="color:rgba(250,246,238,.7);">
      nikahin lahir dari pengalaman para perancang undangan profesional yang sering kewalahan
      memenuhi permintaan personalisasi. Kami menggabungkan keahlian desain manusia dengan
      kekuatan AI agar setiap undangan benar-benar mencerminkan kepribadian pasangan, bukan template massal.
    </p>
    <div class="about-pillars">
      <div class="about-pillar">
        <div class="about-pillar-num">01</div>
        <h4>Mobile-First</h4>
        <p>Tamu Anda akan membuka undangan dari ponsel mereka. Kami merancang dari layar kecil dulu, baru desktop — bukan sebaliknya.</p>
      </div>
      <div class="about-pillar">
        <div class="about-pillar-num">02</div>
        <h4>Powered by Claude</h4>
        <p>Mesin desain kami menggunakan model AI Claude dari Anthropic untuk menghasilkan palet, tipografi, dan salinan yang koheren dan personal.</p>
      </div>
      <div class="about-pillar">
        <div class="about-pillar-num">03</div>
        <h4>Privasi Tamu</h4>
        <p>Daftar tamu dan rekening angpau hanya dapat diakses oleh penerima undangan terverifikasi — tidak terindeks mesin pencari.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">nika<span>hin</span></div>
        <p class="footer-tagline">A Custom Super Premium E-Wedding Invitation Powered by AI. Dibuat dengan sepenuh hati di Indonesia.</p>
      </div>
      <div class="footer-col">
        <h4>Navigasi</h4>
        <ul class="footer-links">
          <li><a href="#hero">Beranda</a></li>
          <li><a href="#designs">Galeri</a></li>
          <li><a href="#pricing">Harga</a></li>
          <li><a href="#about">Tentang</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Produk</h4>
        <ul class="footer-links">
          <li><a href="<?= APP_URL ?>/auth/register.php">Daftar</a></li>
          <li><a href="<?= APP_URL ?>/auth/login.php">Masuk</a></li>
          <li><a href="#pricing">Paket</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Hubungi Kami</h4>
        <div class="footer-contact-item">
          <div class="footer-contact-icon">📧</div>
          <div class="footer-contact-text">
            <span>Email</span>
            <a href="mailto:halo@nikahin.id">halo@nikahin.id</a>
          </div>
        </div>
        <div class="footer-contact-item">
          <div class="footer-contact-icon">📱</div>
          <div class="footer-contact-text">
            <span>WhatsApp</span>
            <a href="tel:+6281234567890">+62 812-3456-7890</a>
          </div>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© <?= date('Y') ?> <span>nikahin</span>. Dibuat dengan ❤ di Indonesia.</p>
      <div class="footer-bottom-links">
        <a href="#">Syarat &amp; Ketentuan</a>
        <a href="#">Kebijakan Privasi</a>
      </div>
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/js/main.js"></script>
</body>
</html>
