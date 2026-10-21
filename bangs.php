<?php

declare(strict_types=1);

function zethal_bangs(): array
{
    return [
        'w'      => ['name' => 'Wikipedia', 'url' => 'https://en.wikipedia.org/w/index.php?search=%s'],
        'yt'     => ['name' => 'YouTube', 'url' => 'https://www.youtube.com/results?search_query=%s'],
        'g'      => ['name' => 'Google', 'url' => 'https://www.google.com/search?q=%s'],
        'ddg'    => ['name' => 'DuckDuckGo', 'url' => 'https://duckduckgo.com/?q=%s'],
        'gh'     => ['name' => 'GitHub', 'url' => 'https://github.com/search?q=%s'],
        'so'     => ['name' => 'Stack Overflow', 'url' => 'https://stackoverflow.com/search?q=%s'],
        'mdn'    => ['name' => 'MDN Web Docs', 'url' => 'https://developer.mozilla.org/en-US/search?q=%s'],
        'maps'   => ['name' => 'OpenStreetMap', 'url' => 'https://www.openstreetmap.org/search?query=%s'],
        'r'      => ['name' => 'Reddit', 'url' => 'https://www.reddit.com/search/?q=%s'],
        'a'      => ['name' => 'Amazon', 'url' => 'https://www.amazon.com/s?k=%s'],
        'imdb'   => ['name' => 'IMDb', 'url' => 'https://www.imdb.com/find/?q=%s'],
        'npm'    => ['name' => 'npm', 'url' => 'https://www.npmjs.com/search?q=%s'],
        'php'    => ['name' => 'PHP Manual', 'url' => 'https://www.php.net/manual-lookup.php?pattern=%s'],
        'wikt'   => ['name' => 'Wiktionary', 'url' => 'https://en.wiktionary.org/w/index.php?search=%s'],
        'arxiv'  => ['name' => 'arXiv', 'url' => 'https://arxiv.org/a/search?query=%s'],
        'osm'    => ['name' => 'OpenStreetMap', 'url' => 'https://www.openstreetmap.org/search?query=%s'],
    ];
}

function zethal_bang_redirect(string $q): ?string
{
    if (!preg_match('/(?:^|\s)!([a-z0-9]+)(?=\s|$)/i', $q, $m)) {
        return null;
    }
    $code  = strtolower($m[1]);
    $bangs = zethal_bangs();
    if (!isset($bangs[$code])) {
        return null;
    }
    $rest = trim((string)preg_replace('/(?:^|\s)!' . preg_quote($m[1], '/') . '(?=\s|$)/i', ' ', $q));
    if ($rest === '') {
        $parts = parse_url($bangs[$code]['url']);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . '/';
    }
    return sprintf($bangs[$code]['url'], rawurlencode($rest));
}

function zethal_bangs_json(): string
{
    $out = [];
    foreach (zethal_bangs() as $code => $b) {
        $out[] = ['code' => $code, 'name' => $b['name'], 'host' => (string)parse_url($b['url'], PHP_URL_HOST)];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
