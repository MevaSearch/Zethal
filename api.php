<?php

declare(strict_types=1);

require __DIR__ . '/lang.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

const ZETHAL_RATE_LIMIT  = 60;
const ZETHAL_RATE_WINDOW = 60;
const ZETHAL_CACHE_TTL   = 600;
const ZETHAL_USER_AGENT  = 'ZethalFramework/1.0 (self-hosted search template)';

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

function rate_limited(string $bucket): bool
{
    $dir = sys_get_temp_dir() . '/zethal-rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $fh = @fopen($dir . '/' . $bucket . '-' . client_key(), 'c+');
    if ($fh === false) {
        return false;
    }
    $now  = time();
    $data = ['start' => $now, 'count' => 0];
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    if ($raw) {
        $data = json_decode($raw, true) ?: $data;
    }
    if ($now - (int)$data['start'] >= ZETHAL_RATE_WINDOW) {
        $data = ['start' => $now, 'count' => 0];
    }
    $data['count']++;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data['count'] > ZETHAL_RATE_LIMIT;
}

function http_json(string $url, array $headers = []): ?array
{
    $cacheDir = sys_get_temp_dir() . '/zethal-cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0700, true);
    }
    $cacheFile = $cacheDir . '/' . hash('sha256', $url);
    if (is_file($cacheFile) && time() - (int)filemtime($cacheFile) < ZETHAL_CACHE_TTL) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT      => ZETHAL_USER_AGENT,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (!is_string($body) || $status !== 200) {
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return null;
    }
    @file_put_contents($cacheFile, $body, LOCK_EX);
    return $data;
}

function plain(string $html, int $max): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_substr(trim((string)preg_replace('/\s+/u', ' ', $text)), 0, $max);
}

function wiki_lang(): string
{
    $lang = explode('-', zethal_lang())[0];
    return preg_match('/^[a-z]{2,3}$/', $lang) ? $lang : 'en';
}

function wiki_search(string $q, string $type, int $page, int $count): array
{
    $lang   = wiki_lang();
    $offset = ($page - 1) * $count;
    $params = [
        'action'   => 'query',
        'list'     => 'search',
        'srsearch' => $q,
        'srlimit'  => $count,
        'sroffset' => $offset,
        'srprop'   => 'snippet|timestamp',
        'format'   => 'json',
        'utf8'     => 1,
    ];
    if ($type === 'news') {
        $params['srsort'] = 'last_edit_desc';
    }
    $data = http_json('https://' . $lang . '.wikipedia.org/w/api.php?' . http_build_query($params));
    if ($data === null) {
        return ['results' => [], 'total' => 0, 'failed' => true];
    }
    $results = [];
    foreach ((array)($data['query']['search'] ?? []) as $hit) {
        $title     = (string)($hit['title'] ?? '');
        $results[] = [
            'title'       => $title,
            'url'         => 'https://' . $lang . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title)),
            'description' => plain((string)($hit['snippet'] ?? ''), 300),
            'date'        => substr((string)($hit['timestamp'] ?? ''), 0, 10),
            'sitelinks'   => [],
        ];
    }
    return ['results' => $results, 'total' => (int)($data['query']['searchinfo']['totalhits'] ?? count($results))];
}

function commons_search(string $q, string $type, int $page, int $count): array
{
    $filter = $type === 'videos' ? 'filetype:video ' : 'filetype:bitmap ';
    $data   = http_json('https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'       => 'query',
        'generator'    => 'search',
        'gsrsearch'    => $filter . $q,
        'gsrnamespace' => 6,
        'gsrlimit'     => $count,
        'gsroffset'    => ($page - 1) * $count,
        'prop'         => 'imageinfo',
        'iiprop'       => 'url',
        'iiurlwidth'   => 400,
        'format'       => 'json',
        'utf8'         => 1,
    ]));
    if ($data === null) {
        return ['results' => [], 'total' => 0, 'failed' => true];
    }
    $pages = (array)($data['query']['pages'] ?? []);
    usort($pages, fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
    $results = [];
    foreach ($pages as $p) {
        $info  = (array)($p['imageinfo'][0] ?? []);
        $thumb = (string)($info['thumburl'] ?? '');
        $title = preg_replace('/^File:|\.[a-z0-9]{2,5}$/i', '', (string)($p['title'] ?? ''));
        $results[] = [
            'title'       => str_replace('_', ' ', (string)$title),
            'url'         => (string)($info['descriptionurl'] ?? ''),
            'description' => '',
            'thumbnail'   => preg_match('#^https://(upload|thumb)\.wikimedia\.org/#', $thumb) ? strtok($thumb, '?') : '',
            'sitelinks'   => [],
        ];
    }
    $more = isset($data['continue']['gsroffset']);
    return ['results' => $results, 'total' => ($page - 1) * $count + count($results) + ($more ? $count : 0)];
}

function wiki_panel(string $title): ?array
{
    $lang = wiki_lang();
    $data = http_json('https://' . $lang . '.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $title)));
    if ($data === null || ($data['type'] ?? '') === 'disambiguation' || empty($data['extract'])) {
        return null;
    }
    return [
        'title'    => plain((string)($data['title'] ?? $title), 120),
        'subtitle' => plain((string)($data['description'] ?? ''), 160),
        'text'     => plain((string)$data['extract'], 700),
        'url'      => (string)($data['content_urls']['desktop']['page'] ?? ''),
        'source'   => 'Wikipedia',
    ];
}

function custom_provider(string $q, string $type, int $page, int $count, string $safe): ?array
{
    $base = (string)getenv('ZETHAL_SEARCH_URL');
    if ($base === '') {
        return null;
    }
    $key = (string)getenv('ZETHAL_SEARCH_KEY');
    $raw = http_json($base . (str_contains($base, '?') ? '&' : '?') . http_build_query(compact('q', 'type', 'page', 'count', 'safe')),
        $key !== '' ? ['Authorization: Bearer ' . $key] : []);
    if ($raw === null) {
        return ['results' => [], 'total' => 0, 'failed' => true];
    }
    $results = [];
    foreach ((array)($raw['results'] ?? []) as $r) {
        $url = (string)($r['url'] ?? '');
        if (!preg_match('#^https?://#i', $url)) {
            continue;
        }
        $results[] = [
            'title'       => plain((string)($r['title'] ?? ''), 200),
            'url'         => $url,
            'description' => plain((string)($r['description'] ?? ''), 400),
            'thumbnail'   => preg_match('#^https://#i', (string)($r['thumbnail'] ?? '')) ? (string)$r['thumbnail'] : '',
            'sitelinks'   => [],
        ];
    }
    return ['results' => $results, 'total' => (int)($raw['total'] ?? count($results)), 'panel' => $raw['panel'] ?? null];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(405, ['error' => 'method_not_allowed']);
}

$q     = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 400);
$type  = (string)($_GET['type'] ?? 'all');
$type  = in_array($type, ['all', 'images', 'videos', 'news'], true) ? $type : 'all';
$page  = max(1, min(20, (int)($_GET['page'] ?? 1)));
$count = (int)($_GET['count'] ?? 10);
$count = in_array($count, [10, 20, 30], true) ? $count : 10;
$safe  = (string)($_GET['safe'] ?? 'moderate');
$safe  = in_array($safe, ['strict', 'moderate', 'off'], true) ? $safe : 'moderate';

if ($q === '') {
    respond(400, ['error' => 'missing_query']);
}
if (rate_limited('search')) {
    respond(429, ['error' => 'rate_limited']);
}

$data = custom_provider($q, $type, $page, $count, $safe)
    ?? (in_array($type, ['images', 'videos'], true) ? commons_search($q, $type, $page, $count) : wiki_search($q, $type, $page, $count));

if (!empty($data['failed'])) {
    respond(502, ['error' => 'provider_unavailable']);
}

if (!isset($data['panel']) && $type === 'all' && $page === 1 && !empty($data['results'][0]['title'])) {
    $data['panel'] = wiki_panel((string)$data['results'][0]['title']);
}

respond(200, [
    'results' => $data['results'],
    'total'   => $data['total'],
    'count'   => $count,
    'panel'   => $data['panel'] ?? null,
    'source'  => getenv('ZETHAL_SEARCH_URL') ? 'custom' : 'wikimedia',
]);
