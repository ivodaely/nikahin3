<?php
/**
 * Claude API wrapper for nikahin AI generation engine.
 *
 * Used by /invitation/generate.php to:
 *   1) synthesize a design specification (palette + typography + motif + copy)
 *   2) draft Indonesian-language wedding copy tailored to the couple
 *
 * Returns structured JSON the renderer can apply to BasicTemplate.
 */

require_once __DIR__ . '/functions.php';

final class ClaudeApi {

    /**
     * Generate a complete invitation design spec from a couple's profile.
     *
     * @param array $profile Decoded couple profile (groom, bride, schedule, theme)
     * @return array { ok: bool, design?: array, error?: string, tokens_in?: int, tokens_out?: int }
     */
    public static function generateDesign(array $profile): array {
        if (!CLAUDE_API_KEY) {
            return ['ok' => false, 'error' => 'CLAUDE_API_KEY belum dikonfigurasi.'];
        }

        $system = self::buildSystemPrompt();
        $user   = self::buildUserPrompt($profile);

        $body = [
            'model'      => CLAUDE_MODEL,
            'max_tokens' => CLAUDE_MAX_TOKENS,
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => $user],
            ],
        ];

        $response = self::request($body);
        if (!$response['ok']) return $response;

        // Parse the JSON design spec out of the first text block.
        $text = '';
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $design = self::extractJson($text);
        if ($design === null) {
            return [
                'ok'    => false,
                'error' => 'AI mengembalikan format yang tidak valid. Silakan coba lagi.',
                'raw'   => $text,
            ];
        }

        return [
            'ok'         => true,
            'design'     => $design,
            'tokens_in'  => $response['usage']['input_tokens']  ?? 0,
            'tokens_out' => $response['usage']['output_tokens'] ?? 0,
            'model'      => $response['model'] ?? CLAUDE_MODEL,
        ];
    }

    // ---------------------------------------------------
    // Prompt construction
    // ---------------------------------------------------
    private static function buildSystemPrompt(): string {
        return <<<PROMPT
You are the AI design engine for **nikahin**, a premium e-wedding invitation platform serving Indonesian couples.

Your job: take a couple's profile and produce a complete *design specification* that the renderer will apply to a fixed page structure (Cover → Couple → Story → Schedule → Location → Gift → RSVP → Guestbook).

You MUST return a single valid JSON object — no Markdown fences, no commentary before or after — matching exactly this schema:

{
  "palette": {
    "primary": "#hex",        // dominant background tone, warm/refined
    "secondary": "#hex",      // accent, complementary to primary
    "accent": "#hex",         // small highlight (gold/rose/etc), used sparingly
    "ink": "#hex",            // primary text color (must have AA contrast on primary)
    "paper": "#hex"           // light cream/ivory used for content cards
  },
  "typography": {
    "headline_font": "Playfair Display | Cormorant Garamond | Cardo | DM Serif Display | Tenor Sans",
    "body_font": "Inter | Plus Jakarta Sans | DM Sans | Lora | Karla",
    "headline_weight": 600
  },
  "theme_label": "short evocative phrase, e.g. 'Watercolor Botanical', 'Modern Minimal Gold', 'Javanese Heritage'",
  "motif": {
    "primary": "leaf | floral | geometric | wave | arch | lattice | none",
    "ornament_color": "#hex"
  },
  "copy_id": {                 // Bahasa Indonesia copy
    "welcome":  "warm 1-line greeting, max 80 chars",
    "invocation": "religiously-appropriate opening (Bismillah, Tuhan memberkati, Om Swastiastu, etc.) — match the couple's religion exactly",
    "story":    "2-3 sentence couple story written in 3rd person, warm and personal, weaving in cues from the brief"
  },
  "copy_en": {                 // English fallback copy, same fields, same lengths
    "welcome": "...",
    "invocation": "...",
    "story": "..."
  },
  "rsvp_prompt": "single Indonesian sentence inviting the guest to RSVP",
  "section_order": ["cover","couple","story","schedule","location","gift","rsvp","guestbook"]
}

Hard rules:
- Religion mapping (use exactly): Islam → "Bismillaahirrahmaanirrahiim" + brief Quranic-tone phrase about marriage (Ar-Rum 21 in spirit, paraphrase only); Christian/Catholic → "Atas berkat Tuhan Yang Maha Esa..."; Hindu → "Om Swastiastu"; Buddhist → "Sotthi hotu"; Confucian → respectful neutral; Other → respectful neutral.
- Palette must be cohesive — choose colors that visually relate. Avoid pure red/black/white. Favor warm, refined, slightly desaturated tones unless the couple's color preference clearly says otherwise.
- If couple supplied favorite_color or palette_seed values, treat them as STRONG hints and build the palette around them.
- Typography pairing must be tested combinations from the allowed list above. Headline serif + clean sans body is the safest, most elegant default.
- Story copy must reference real details from the couple profile (city, when they met if provided, etc.) — never invent specifics that contradict input.
- Output JSON ONLY. No prose before or after. No code fences.
PROMPT;
    }

    private static function buildUserPrompt(array $profile): string {
        // Pull only the fields the model needs — keep token cost low.
        $compact = [
            'groom' => [
                'name'        => $profile['groom']['name']         ?? '',
                'short'       => $profile['groom']['short']        ?? '',
                'father'      => $profile['groom']['father']       ?? '',
                'mother'      => $profile['groom']['mother']       ?? '',
                'family_order'=> $profile['groom']['family_order'] ?? '',
                'religion'    => $profile['groom']['religion']     ?? '',
                'fav_color'   => $profile['groom']['fav_color']    ?? '',
                'bio'         => $profile['groom']['bio']          ?? '',
            ],
            'bride' => [
                'name'        => $profile['bride']['name']         ?? '',
                'short'       => $profile['bride']['short']        ?? '',
                'father'      => $profile['bride']['father']       ?? '',
                'mother'      => $profile['bride']['mother']       ?? '',
                'family_order'=> $profile['bride']['family_order'] ?? '',
                'religion'    => $profile['bride']['religion']     ?? '',
                'fav_color'   => $profile['bride']['fav_color']    ?? '',
                'bio'         => $profile['bride']['bio']          ?? '',
            ],
            'schedule' => $profile['schedule'] ?? [],
            'theme'    => $profile['theme']    ?? [],
        ];
        return "Couple profile (JSON):\n\n" . json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nProduce the design specification now.";
    }

    // ---------------------------------------------------
    // HTTP
    // ---------------------------------------------------
    private static function request(array $body): array {
        $ch = curl_init(CLAUDE_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: '         . CLAUDE_API_KEY,
                'anthropic-version: ' . CLAUDE_VERSION,
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'Network error: ' . $err];
        }

        $json = json_decode($raw, true);
        if ($code >= 400 || !is_array($json)) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $code);
            return ['ok' => false, 'error' => 'Claude API: ' . $msg, 'http' => $code, 'raw' => $raw];
        }

        $json['ok'] = true;
        return $json;
    }

    // ---------------------------------------------------
    // Best-effort JSON extraction (in case the model wraps in fences)
    // ---------------------------------------------------
    private static function extractJson(string $text): ?array {
        $text = trim($text);
        // Strip leading/trailing fences if present
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        // Fallback: extract first {...} balanced block
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($text, $start, $end - $start + 1);
            $decoded   = json_decode($candidate, true);
            if (is_array($decoded)) return $decoded;
        }
        return null;
    }
}
