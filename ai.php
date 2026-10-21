<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

const ZETHAL_AI_RATE_LIMIT  = 20;
const ZETHAL_AI_RATE_WINDOW = 60;
const ZETHAL_AI_MAX_TOKENS  = 400;

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_key(): string
{
    $saltFile = sys_get_temp_dir() . '/zethal-salt';
    if (!is_file($saltFile)) {
        @file_put_contents($saltFile, bin2hex(random_bytes(32)), LOCK_EX);
    }
    $salt = (string)@file_get_contents($saltFile);
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . date('Y-m-d') . '|' . $salt);
}

function rate_limited(): bool
{
    $dir = sys_get_temp_dir() . '/zethal-rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . '/ai-' . client_key();
    $now  = time();
    $data = ['start' => $now, 'count' => 0];
    $fh   = @fopen($file, 'c+');
    if ($fh === false) {
        return false;
    }
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    if ($raw) {
        $data = json_decode($raw, true) ?: $data;
    }
    if ($now - (int)$data['start'] >= ZETHAL_AI_RATE_WINDOW) {
        $data = ['start' => $now, 'count' => 0];
    }
    $data['count']++;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data['count'] > ZETHAL_AI_RATE_LIMIT;
}

function build_prompt(string $q, array $sources): string
{
    $lines = [];
    foreach (array_slice($sources, 0, 5) as $i => $s) {
        $lines[] = '[' . ($i + 1) . '] ' . mb_substr(strip_tags((string)($s['title'] ?? '')), 0, 160)
            . ': ' . mb_substr(strip_tags((string)($s['description'] ?? '')), 0, 300);
    }
    return "Answer the search query in 2 to 4 short sentences, using only the sources below. "
        . "If the sources are not enough, say so. Reply in the language of the query.\n\n"
        . "Query: " . $q . "\n\nSources:\n" . implode("\n", $lines);
}

function call_ai(string $prompt, ?array $image = null): string
{
    $provider = (string)(getenv('ZETHAL_AI_PROVIDER') ?: 'anthropic');
    $key      = (string)getenv('ZETHAL_AI_KEY');
    $model    = (string)getenv('ZETHAL_AI_MODEL');

    if ($provider === 'anthropic') {
        $url     = 'https://api.anthropic.com/v1/messages';
        $headers = ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'];
        $content = $image ? [['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['data']]], ['type' => 'text', 'text' => $prompt]] : $prompt;
        $payload = ['model' => $model, 'max_tokens' => ZETHAL_AI_MAX_TOKENS, 'messages' => [['role' => 'user', 'content' => $content]]];
    } else {
        $url     = (string)getenv('ZETHAL_AI_URL');
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $key];
        $content = $image ? [['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime'] . ';base64,' . $image['data']]], ['type' => 'text', 'text' => $prompt]] : $prompt;
        $payload = ['model' => $model, 'max_tokens' => ZETHAL_AI_MAX_TOKENS, 'messages' => [['role' => 'user', 'content' => $content]]];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (!is_string($body) || $status !== 200) {
        respond(502, ['error' => 'ai_unavailable']);
    }
    $data = (array)json_decode($body, true);

    if ($provider === 'anthropic') {
        $text = '';
        foreach ((array)($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string)$block['text'];
            }
        }
        return trim($text);
    }
    return trim((string)($data['choices'][0]['message']['content'] ?? ''));
}

function extractive_summary(string $q, array $sources): string
{
    $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q)) ?: [], fn($w) => mb_strlen($w) > 2);
    $scored = [];
    $order  = 0;
    foreach (array_slice($sources, 0, 5) as $s) {
        $text = trim(strip_tags((string)($s['description'] ?? '')));
        foreach (preg_split('/(?<=[.!?])\s+/u', $text) ?: [] as $sentence) {
            $sentence = trim((string)preg_replace('/(?:\.|…)+$/u', '', trim($sentence)));
            if (mb_strlen($sentence) < 30) {
                continue;
            }
            $lower = mb_strtolower($sentence);
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($lower, $w)) {
                    $score++;
                }
            }
            $scored[] = ['text' => $sentence, 'score' => $score, 'order' => $order++];
        }
    }
    usort($scored, fn($a, $b) => [$b['score'], $a['order']] <=> [$a['score'], $b['order']]);
    $picked = array_slice($scored, 0, 3);
    usort($picked, fn($a, $b) => $a['order'] <=> $b['order']);
    return implode('. ', array_map(fn($p) => $p['text'], $picked)) . ($picked ? '.' : '');
}

function query_from_filename(string $name): string
{
    $base  = mb_strtolower((string)preg_replace('/\.[a-z0-9]{2,5}$/i', '', basename($name)));
    $parts = preg_split('/[\s_.\-()\[\]]+/u', $base) ?: [];
    $skip  = ['img', 'dsc', 'dscn', 'pxl', 'mvimg', 'screenshot', 'screen', 'shot', 'photo', 'image', 'picture', 'pic', 'whatsapp', 'signal', 'telegram', 'copy', 'edited', 'scaled', 'final'];
    $keep  = array_filter($parts, fn($p) => $p !== '' && !preg_match('/^\d+$/', $p) && !in_array($p, $skip, true) && preg_match('/\p{L}{2,}/u', $p));
    $query = trim(implode(' ', array_slice(array_values($keep), 0, 6)));
    return mb_strlen($query) >= 3 ? mb_substr($query, 0, 80) : '';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(405, ['error' => 'method_not_allowed']);
}

$input = (array)json_decode((string)file_get_contents('php://input', false, null, 0, 6000000), true);
$mode  = (string)($input['mode'] ?? 'summary');

if (rate_limited()) {
    respond(429, ['error' => 'rate_limited']);
}
$configured = getenv('ZETHAL_AI_KEY') && getenv('ZETHAL_AI_MODEL');

if ($mode === 'image') {
    $mime = (string)($input['mime'] ?? '');
    $data = (string)($input['image'] ?? '');
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $data === '' || strlen($data) > 5600000) {
        respond(400, ['error' => 'invalid_image']);
    }
    $bin = base64_decode($data, true);
    $info = $bin !== false ? @getimagesizefromstring($bin) : false;
    if (!$info || ($info['mime'] ?? '') !== $mime) {
        respond(400, ['error' => 'invalid_image']);
    }
    if (!$configured) {
        $query = query_from_filename((string)($input['filename'] ?? ''));
        respond(200, ['query' => $query, 'kind' => 'filename', 'message' => $query === '' ? 'Photo recognition needs an AI model. Set ZETHAL_AI_KEY and ZETHAL_AI_MODEL, or use a photo with a descriptive file name.' : '']);
    }
    $query = call_ai(
        'Look at the image and reply with one short web search query (2 to 6 words) that would find what it shows. '
        . 'Name the exact object, product, place, plant, animal or landmark if you can recognise it. Reply with the query only, no quotes.',
        ['mime' => $mime, 'data' => $data]
    );
    $query = mb_substr(trim(strip_tags($query), " \t\n\r\"'."), 0, 120);
    respond(200, ['query' => $query, 'kind' => 'ai']);
}

$q       = mb_substr(trim((string)($input['q'] ?? '')), 0, 400);
$sources = array_values(array_filter((array)($input['sources'] ?? []), 'is_array'));

if ($q === '') {
    respond(400, ['error' => 'missing_query']);
}
if (!$configured) {
    respond(200, ['answer' => extractive_summary($q, $sources), 'kind' => 'extractive']);
}

$answer = call_ai(build_prompt($q, $sources));
respond(200, ['answer' => mb_substr(strip_tags($answer), 0, 2000), 'kind' => 'ai']);
