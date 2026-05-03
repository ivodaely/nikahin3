<?php
/**
 * BasicTemplate — the shared structural template referenced in spec §8.2.
 * The AI design engine outputs a *design specification* that is applied to
 * THIS template, ensuring layout consistency across both Basic and AI tiers.
 *
 * Expects in scope: $invitation (DB row), $profile (decoded), $design (decoded),
 * $assets (grouped by type), $is_preview (bool), $guest (optional, for personalized link).
 */

// Pull data with safe fallbacks
$groomShort = $profile['groom']['short'] ?? ($profile['groom']['name'] ?? 'Mempelai');
$brideShort = $profile['bride']['short'] ?? ($profile['bride']['name'] ?? 'Mempelai');
$groomFull  = $profile['groom']['name']  ?? $groomShort;
$brideFull  = $profile['bride']['name']  ?? $brideShort;

$akadDate    = $profile['schedule']['akad']['date']     ?? null;
$akadTime    = $profile['schedule']['akad']['time']     ?? null;
$akadVenue   = $profile['schedule']['akad']['venue']    ?? null;
$resepsiDate = $profile['schedule']['resepsi']['date']  ?? null;
$resepsiTime = $profile['schedule']['resepsi']['time']  ?? null;
$resepsiVenue= $profile['schedule']['resepsi']['venue'] ?? null;
$tz          = $profile['schedule']['timezone']         ?? 'WIB';

$pal     = $design['palette']    ?? [];
$typo    = $design['typography'] ?? [];
$copyId  = $design['copy_id']    ?? [];
$copyEn  = $design['copy_en']    ?? [];
$rsvpPr  = $design['rsvp_prompt'] ?? '';

$primary   = $pal['primary']   ?? '#F4EDE0';
$secondary = $pal['secondary'] ?? '#EAD9BC';
$accent    = $pal['accent']    ?? '#C8943A';
$ink       = $pal['ink']       ?? '#2C1A0E';
$paper     = $pal['paper']     ?? '#FAF6EE';
$headFont  = $typo['headline_font'] ?? 'Playfair Display';
$bodyFont  = $typo['body_font']     ?? 'Inter';

$slug      = $invitation['slug'] ?? '';
$rsvpAction= APP_URL . '/api/rsvp.php';
$gbAction  = APP_URL . '/api/guestbook.php';
$guestToken= $guest['link_token'] ?? '';
$guestName = $guest['name']       ?? '';

// Pull guestbook entries (only for published)
$gb = [];
if (($invitation['status'] ?? '') === 'published') {
    $gb = DB::all(
        "SELECT guest_name, message, created_at FROM guestbook
         WHERE invitation_id = ? AND hidden = 0
         ORDER BY id DESC LIMIT 50",
        [(int)$invitation['id']]
    );
}

// Fonts: load from Google Fonts dynamically (URL-safe encode)
$fontsUrl = 'https://fonts.googleapis.com/css2'
          . '?family=' . urlencode($headFont) . ':ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600'
          . '&family=' . urlencode($bodyFont) . ':wght@300;400;500;600;700'
          . '&display=swap';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Undangan Pernikahan <?= e($groomShort . ' & ' . $brideShort) ?></title>
  <meta name="description" content="<?= e(($copyId['welcome'] ?? '') . ' — ' . $groomShort . ' & ' . $brideShort) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="<?= e($fontsUrl) ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/invitation.css">
  <style>
    :root {
      --inv-primary:   <?= e($primary) ?>;
      --inv-secondary: <?= e($secondary) ?>;
      --inv-accent:    <?= e($accent) ?>;
      --inv-ink:       <?= e($ink) ?>;
      --inv-paper:     <?= e($paper) ?>;
      --inv-head:      "<?= e($headFont) ?>", Georgia, serif;
      --inv-body:      "<?= e($bodyFont) ?>", system-ui, sans-serif;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="inv-page">

<!-- ===== COVER ===== -->
<section class="inv-cover">
  <div class="inv-cover-tag">The Wedding Of</div>
  <h1 class="inv-cover-names"><?= e($groomShort) ?></h1>
  <span class="inv-cover-and">&amp;</span>
  <h1 class="inv-cover-names"><?= e($brideShort) ?></h1>
  <?php if ($akadDate): ?>
    <div class="inv-cover-date"><?= e(strtoupper(fmt_date_id($akadDate))) ?></div>
  <?php endif; ?>
  <div class="inv-cover-scroll">— scroll —</div>
</section>

<!-- ===== INVOCATION + WELCOME ===== -->
<section class="inv-section tinted">
  <div class="inv-shell" style="text-align:center;">
    <p class="invocation" style="font-style:italic;font-size:1.2rem;color:var(--inv-accent);">
      <?= e($copyId['invocation'] ?? '') ?>
    </p>
    <span class="inv-rule"></span>
    <p class="desc"><?= e($copyId['welcome'] ?? '') ?></p>
    <?php if (!empty($guestName)): ?>
      <p class="desc" style="margin-top:14px;">
        Kepada Yth.<br><strong><?= e($guestName) ?></strong>
      </p>
    <?php endif; ?>
  </div>
</section>

<!-- ===== COUPLE ===== -->
<section class="inv-section">
  <h2>Mempelai</h2><span class="inv-rule"></span>
  <div class="inv-couple-grid">
    <?php
    foreach (['groom' => 'Mempelai Pria', 'bride' => 'Mempelai Wanita'] as $key => $title):
      $person = $profile[$key] ?? [];
      $photos = $assets[$key . '_photo'] ?? [];
      $first  = $photos[0]['url'] ?? null;
    ?>
      <div class="inv-person">
        <?php if ($first): ?>
          <div class="inv-person-photo"><img src="<?= e($first) ?>" alt=""></div>
        <?php else: ?>
          <div class="inv-person-photo" style="display:flex;align-items:center;justify-content:center;background:var(--inv-secondary);color:var(--inv-ink);font-family:var(--inv-head);font-size:2rem;font-style:italic;">
            <?= e(mb_substr($person['short'] ?? '?', 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div class="inv-person-name"><?= e($person['name'] ?? '—') ?></div>
        <?php if (!empty($person['family_order'])): ?>
          <div class="inv-person-meta"><?= e($person['family_order']) ?></div>
        <?php endif; ?>
        <?php if (!empty($person['bio'])): ?>
          <div class="inv-person-bio"><?= e($person['bio']) ?></div>
        <?php endif; ?>
        <?php if (!empty($person['father']) || !empty($person['mother'])): ?>
          <div class="inv-person-fam">
            <?php if (!empty($person['father'])): ?>Putra/i dari Bpk. <?= e($person['father']) ?><?php endif; ?>
            <?php if (!empty($person['mother'])): ?> &amp; Ibu <?= e($person['mother']) ?><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===== STORY ===== -->
<?php if (!empty($copyId['story'])): ?>
<section class="inv-section tinted">
  <h2>Kisah Kami</h2><span class="inv-rule"></span>
  <p class="inv-story"><?= e($copyId['story']) ?></p>
</section>
<?php endif; ?>

<!-- ===== SCHEDULE ===== -->
<section class="inv-section">
  <h2>Acara</h2><span class="inv-rule"></span>
  <div class="inv-events">
    <?php if ($akadDate): ?>
      <div class="inv-event">
        <h3>Akad / Pemberkatan</h3>
        <div class="dt"><?= e(fmt_date_id($akadDate)) ?></div>
        <?php if ($akadTime): ?><div class="time"><?= e(date('H:i', strtotime($akadTime))) ?> <?= e($tz) ?></div><?php endif; ?>
        <?php if ($akadVenue): ?><div class="venue"><?= nl2br(e($akadVenue)) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($resepsiDate): ?>
      <div class="inv-event">
        <h3>Resepsi</h3>
        <div class="dt"><?= e(fmt_date_id($resepsiDate)) ?></div>
        <?php if ($resepsiTime): ?><div class="time"><?= e(date('H:i', strtotime($resepsiTime))) ?> <?= e($tz) ?></div><?php endif; ?>
        <?php if ($resepsiVenue): ?><div class="venue"><?= nl2br(e($resepsiVenue)) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ===== GIFT ===== -->
<?php if (!empty($profile['gift']) && is_array($profile['gift']) && count($profile['gift']) > 0): ?>
<section class="inv-section tinted">
  <h2>Hadiah Pernikahan</h2><span class="inv-rule"></span>
  <p class="desc">Doa restu Anda adalah hadiah terindah. Namun jika ingin memberikan tanda kasih, kami sangat menghargai.</p>
  <div class="inv-gifts">
    <?php foreach ($profile['gift'] as $g): if (empty($g['number'])) continue; ?>
      <div class="inv-gift-card">
        <div class="inv-gift-bank"><?= e($g['provider'] ?? '') ?></div>
        <div class="inv-gift-num"><?= e($g['number']) ?></div>
        <?php if (!empty($g['holder'])): ?><div class="inv-gift-holder">a/n <?= e($g['holder']) ?></div><?php endif; ?>
        <button class="inv-gift-copy" data-copy="<?= e($g['number']) ?>">Salin Nomor</button>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ===== RSVP ===== -->
<section class="inv-section">
  <h2>Konfirmasi Kehadiran</h2><span class="inv-rule"></span>
  <?php if ($rsvpPr): ?><p class="desc"><?= e($rsvpPr) ?></p><?php endif; ?>

  <form id="rsvp-form" class="inv-rsvp" action="<?= e($rsvpAction) ?>" method="POST">
    <input type="hidden" name="slug" value="<?= e($slug) ?>">
    <input type="hidden" name="token" value="<?= e($guestToken) ?>">
    <div class="field"><label>Nama Anda</label>
      <input type="text" name="name" required value="<?= e($guestName) ?>"></div>
    <div class="field"><label>Akan Hadir?</label>
      <div class="opts">
        <input type="radio" id="att-yes"   name="attending" value="yes" checked><label for="att-yes">Ya</label>
        <input type="radio" id="att-no"    name="attending" value="no"><label for="att-no">Tidak</label>
        <input type="radio" id="att-maybe" name="attending" value="maybe"><label for="att-maybe">Mungkin</label>
      </div>
    </div>
    <div class="field"><label>Jumlah</label>
      <input type="number" name="attendees" min="1" max="10" value="2"></div>
    <div class="field"><label>Acara</label>
      <select name="event">
        <option value="both">Akad &amp; Resepsi</option>
        <option value="akad">Hanya Akad</option>
        <option value="resepsi">Hanya Resepsi</option>
      </select>
    </div>
    <div class="field"><label>Pesan (opsional)</label>
      <textarea name="message" rows="2"></textarea></div>
    <button type="submit">Kirim RSVP</button>
  </form>
</section>

<!-- ===== GUESTBOOK ===== -->
<section class="inv-section tinted">
  <h2>Buku Tamu</h2><span class="inv-rule"></span>
  <form id="guestbook-form" class="inv-gb-form" action="<?= e($gbAction) ?>" method="POST">
    <input type="hidden" name="slug" value="<?= e($slug) ?>">
    <div class="field">
      <input type="text" name="name" placeholder="Nama Anda" required value="<?= e($guestName) ?>">
    </div>
    <div class="field">
      <textarea name="message" placeholder="Tulis ucapan Anda (maks 280 karakter)" maxlength="280" rows="3" required></textarea>
    </div>
    <button type="submit">Kirim Ucapan</button>
  </form>

  <div class="inv-gb-list">
    <?php foreach ($gb as $m): ?>
      <div class="inv-gb-msg">
        <div class="inv-gb-msg-head">
          <span class="inv-gb-msg-name"><?= e($m['guest_name']) ?></span>
          <span><?= e(date('d M Y H:i', strtotime($m['created_at']))) ?></span>
        </div>
        <div class="inv-gb-msg-text"><?= nl2br(e($m['message'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="inv-foot">
  <div class="inv-foot-names"><?= e($groomShort) ?> &amp; <?= e($brideShort) ?></div>
  <div class="inv-foot-copy">
    Powered by <strong style="color:var(--inv-accent);">nikahin</strong> — A Custom Super Premium E-Wedding Invitation
  </div>
</footer>

<?php if (!empty($is_preview)): ?>
  <div class="inv-watermark">Preview — Belum Dipublikasikan</div>
<?php endif; ?>

<script>
$(function(){
  $('[data-copy]').on('click', function(e){
    e.preventDefault();
    var v = $(this).data('copy'); var $b = $(this); var t = $b.text();
    if (navigator.clipboard) navigator.clipboard.writeText(v);
    $b.text('Tersalin ✓'); setTimeout(function(){ $b.text(t); }, 1500);
  });
  $('#rsvp-form').on('submit', function(e){
    e.preventDefault();
    var $f = $(this);
    $.post($f.attr('action'), $f.serialize(), null, 'json').done(function(r){
      if (r.ok) {
        $f.replaceWith('<div style="text-align:center;padding:24px;background:var(--inv-paper);border-radius:24px;max-width:520px;margin:0 auto;"><h3 style="font-family:var(--inv-head);font-style:italic;font-size:1.4rem;">Terima kasih!</h3><p style="margin-top:8px;">Kehadiran Anda telah kami catat.</p></div>');
      } else { alert(r.error || 'Gagal'); }
    });
  });
  $('#guestbook-form').on('submit', function(e){
    e.preventDefault();
    var $f = $(this);
    $.post($f.attr('action'), $f.serialize(), null, 'json').done(function(r){
      if (r.ok && r.message) {
        var html = '<div class="inv-gb-msg"><div class="inv-gb-msg-head"><span class="inv-gb-msg-name">' + r.message.guest_name + '</span><span>' + r.message.created_at + '</span></div><div class="inv-gb-msg-text">' + r.message.message + '</div></div>';
        $('.inv-gb-list').prepend(html);
        $f[0].reset();
      } else { alert(r.error || 'Gagal'); }
    });
  });
});
</script>
</body>
</html>
