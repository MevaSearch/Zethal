<?php

declare(strict_types=1);

const ZETHAL_LANG_DIR     = __DIR__ . '/lang';
const ZETHAL_DEFAULT_LANG = 'en';

function zethal_languages(): array
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $list = [];
    foreach (glob(ZETHAL_LANG_DIR . '/*/info.json') ?: [] as $file) {
        $code = basename(dirname($file));
        if (!preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/', $code)) {
            continue;
        }
        $info = json_decode((string)file_get_contents($file), true);
        $name = is_array($info) ? trim((string)($info['name'] ?? '')) : '';
        if ($name === '') {
            continue;
        }
        $list[$code] = [
            'name' => mb_substr($name, 0, 40),
            'dir'  => (($info['dir'] ?? 'ltr') === 'rtl') ? 'rtl' : 'ltr',
        ];
    }
    if (!isset($list[ZETHAL_DEFAULT_LANG])) {
        $list[ZETHAL_DEFAULT_LANG] = ['name' => 'English', 'dir' => 'ltr'];
    }
    uasort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $list;
}

function zethal_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $langs  = zethal_languages();
    $cookie = strtolower((string)($_COOKIE['zethal_lang'] ?? ''));
    if (isset($langs[$cookie])) {
        return $lang = $cookie;
    }
    foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
        $code = strtolower(trim(explode(';', $part)[0]));
        if (isset($langs[$code])) {
            return $lang = $code;
        }
        $base = explode('-', $code)[0];
        if (isset($langs[$base])) {
            return $lang = $base;
        }
    }
    return $lang = ZETHAL_DEFAULT_LANG;
}

function zethal_texts(string $page): array
{
    if (!preg_match('/^[a-z0-9-]+$/', $page)) {
        return [];
    }
    $load = function (string $lang, string $file): array {
        $path = ZETHAL_LANG_DIR . '/' . $lang . '/' . $file . '.php';
        $data = is_file($path) ? require $path : [];
        return is_array($data) ? $data : [];
    };
    $lang  = zethal_lang();
    $texts = array_merge($load(ZETHAL_DEFAULT_LANG, 'common'), $load(ZETHAL_DEFAULT_LANG, $page));
    if ($lang !== ZETHAL_DEFAULT_LANG) {
        $texts = array_merge($texts, $load($lang, 'common'), $load($lang, $page));
    }
    $GLOBALS['ZT'] = $texts;
    return $texts;
}

function t(string $key, array $vars = []): string
{
    $text = (string)($GLOBALS['ZT'][$key] ?? $key);
    foreach ($vars as $k => $v) {
        $text = str_replace('{' . $k . '}', (string)$v, $text);
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function asset(string $file): string
{
    $path = __DIR__ . '/' . ltrim($file, '/');
    return e($file . '?v=' . (is_file($path) ? filemtime($path) : 1));
}

function icon(string $name, int $size = 20): string
{
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" aria-hidden="true"><use href="'
        . asset('zethal-icons.php') . '#i-' . e($name) . '"/></svg>';
}

function zethal_js_texts(): string
{
    $js = [];
    foreach ($GLOBALS['ZT'] ?? [] as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) {
            $js[substr((string)$k, 3)] = (string)$v;
        }
    }
    return json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function zethal_html_attrs(): string
{
    $lang = zethal_lang();
    return 'lang="' . e($lang) . '" dir="' . zethal_languages()[$lang]['dir'] . '"';
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $out = [];
    foreach (zethal_languages() as $code => $info) {
        $out[] = ['code' => $code, 'name' => $info['name'], 'dir' => $info['dir']];
    }
    echo json_encode(['current' => zethal_lang(), 'languages' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
