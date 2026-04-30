<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude_api.php';

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

// Only PAID or generating invitations may run generation
if (!in_array($inv['status'], ['paid','generating','ready_for_preview','published'], true)) {
    flash_set('info', 'Selesaikan pembayaran terlebih dahulu.');
    redirect('invitation/form.php?id=' . $invId);
}

// If already past generation, redirect onward
if (in_array($inv['status'], ['ready_for_preview','published'], true)) {
    redirect('invitation/preview.php?id=' . $invId);
}

// Trigger synchronous generation on first load (no separate worker needed for MVP).
// In production, push to a queue and let a worker handle this.
if ($inv['status'] === 'paid' && empty($_GET['polling'])) {
    DB::run("UPDATE invitations SET status='generating' WHERE id = ?", [$invId]);
    audit('generation_started', (int)$user['id'], 'invitation', $invId);

    // Run inline (this page will block briefly while AI responds — acceptable for MVP)
    $profile = json_decode($inv['profile_json'] ?: '{}', true) ?: [];

    if ($inv['tier'] === 'ai' && CLAUDE_API_KEY) {
        $result = ClaudeApi::generateDesign($profile);
    } else {
        // Basic tier OR no API key — use a deterministic preset
        $result = [
            'ok'     => true,
            'design' => default_design_for_basic($profile),
        ];
    }

    if ($result['ok'] && !empty($result['design'])) {
        $slug = make_slug(
            $profile['groom']['short'] ?? 'mempelai',
            $profile['bride']['short'] ?? 'mempelai'
        );
        DB::run(
            "UPDATE invitations
             SET status='ready_for_preview', design_json=?, slug=?
             WHERE id = ?",
            [json_encode($result['design'], JSON_UNESCAPED_UNICODE), $slug, $invId]
        );
        DB::run(
            "INSERT INTO generations (invitation_id, status, tokens_in, tokens_out, model_version, finished_at)
             VALUES (?, 'succeeded', ?, ?, ?, NOW())",
            [
                $invId,
                $result['tokens_in']  ?? 0,
                $result['tokens_out'] ?? 0,
                $result['model']      ?? CLAUDE_MODEL,
            ]
        );
        audit('generation_succeeded', (int)$user['id'], 'invitation', $invId);
        // Redirect to preview
        redirect('invitation/preview.php?id=' . $invId);
    } else {
        DB::run("UPDATE invitations SET status='paid' WHERE id = ?", [$invId]);
        DB::run(
            "INSERT INTO generations (invitation_id, status, error_message, finished_at)
             VALUES (?, 'failed', ?, NOW())",
            [$invId, $result['error'] ?? 'unknown']
        );
        $errorMsg = $result['error'] ?? 'Generasi gagal. Silakan coba lagi.';
    }
}

// Default design for Basic tier (no AI)
function default_design_for_basic(array $profile): array {
    return [
        'palette' => [
            'primary'   => '#F4EDE0',
            'secondary' => '#EAD9BC',
            'accent'    => '#C8943A',
            'ink'       => '#2C1A0E',
            'paper'     => '#FAF6EE',
        ],
        'typography' => [
            'headline_font'   => 'Playfair Display',
            'body_font'       => 'Plus Jakarta Sans',
            'headline_weight' => 600,
        ],
        'theme_label' => 'Classic Champagne',
        'motif' => ['primary' => 'floral', 'ornament_color' => '#C8943A'],
        'copy_id' => [
            'welcome'    => 'Dengan penuh sukacita, kami mengundang Bapak/Ibu/Saudara/i.',
            'invocation' => match ($profile['groom']['religion'] ?? '') {
                'Islam'   => 'Bismillaahirrahmaanirrahiim',
                'Kristen','Katolik' => 'Atas berkat Tuhan Yang Maha Esa',
                'Hindu'   => 'Om Swastiastu',
                'Buddha'  => 'Sotthi hotu',
                default   => 'Dengan rahmat Tuhan Yang Maha Esa',
            },
            'story' => 'Sebuah perjalanan bertahun-tahun, kini berlanjut dalam ikatan yang suci.',
        ],
        'copy_en' => [
            'welcome'    => 'With great joy, we invite you to share in our celebration.',
            'invocation' => 'In the grace of God',
            'story'      => 'A journey of years, now continuing as one.',
        ],
        'rsvp_prompt' => 'Mohon konfirmasi kehadiran Bapak/Ibu/Saudara/i sebelum hari pernikahan.',
        'section_order' => ['cover','couple','story','schedule','location','gift','rsvp','guestbook'],
    ];
}

$page_title = 'Membuat Undangan';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-shell-narrow">
  <div class="gen-stage" id="generation-status"
       data-poll-url="<?= APP_URL ?>/api/generate.php?id=<?= $invId ?>"
       data-redirect-url="<?= APP_URL ?>/invitation/preview.php?id=<?= $invId ?>">
    <?php if (!empty($errorMsg)): ?>
      <div class="alert alert-error"><?= e($errorMsg) ?></div>
      <a class="btn btn-outline" href="<?= APP_URL ?>/invitation/form.php?id=<?= $invId ?>">Kembali</a>
    <?php else: ?>
      <div class="gen-spinner"></div>
      <h2><?= $inv['tier'] === 'ai' ? 'AI sedang merancang…' : 'Menyiapkan undangan…' ?></h2>
      <p>Ini biasanya memakan waktu 30 detik hingga 3 menit. Anda dapat menutup halaman ini dan kembali nanti.</p>
      <ul class="gen-step-list">
        <li class="done">Profil pasangan diterima</li>
        <li class="done">Pembayaran terkonfirmasi</li>
        <li><?= $inv['tier'] === 'ai' ? 'Sintesis desain (palet, tipografi)' : 'Memuat template kurasi' ?></li>
        <li><?= $inv['tier'] === 'ai' ? 'Penulisan salinan (Bahasa Indonesia)' : 'Menerapkan personalisasi' ?></li>
        <li>Menyusun halaman undangan</li>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
