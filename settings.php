<?php

declare(strict_types=1);

require __DIR__ . '/lang.php';
require __DIR__ . '/bangs.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

zethal_texts('settings');

function setting(string $ico, string $label, string $hint, string $control, bool $block = false): string
{
    return '<div class="setting-item' . ($block ? ' setting-item-block' : '') . '"><div class="setting-info">'
        . '<span class="setting-row-icon">' . icon($ico, 16) . '</span>'
        . '<div class="setting-text"><span class="setting-label">' . t($label) . '</span>'
        . ($hint !== '' ? '<p class="setting-desc">' . t($hint) . '</p>' : '')
        . '</div></div>' . $control . '</div>';
}

function toggle(string $name, string $label): string
{
    return '<input class="toggle-input" type="checkbox" name="' . e($name) . '" aria-label="' . t($label) . '">';
}

function select(string $name, string $label, array $options): string
{
    $html = '<select class="custom-select" name="' . e($name) . '" aria-label="' . t($label) . '">';
    foreach ($options as $value => $text) {
        $html .= '<option value="' . e((string)$value) . '">' . $text . '</option>';
    }
    return $html . '</select>';
}

function section(string $id, string $ico, string $title, string $body): string
{
    return '<section class="settings-section" id="' . e($id) . '"><h2 class="section-title">' . icon($ico, 18) . '<span>' . t($title) . '</span></h2>' . $body . '</section>';
}

function bang_table(): string
{
    $rows = '';
    foreach (zethal_bangs() as $code => $b) {
        $rows .= '<li><code>!' . e($code) . '</code><span>' . e($b['name']) . '</span></li>';
    }
    return '<ul class="bang-table">' . $rows . '</ul>';
}

$sections = [
    'appearance' => ['sec_appearance', 'theme'],
    'search'     => ['sec_search', 'search'],
    'privacy'    => ['sec_privacy', 'shield'],
    'ai'         => ['sec_ai', 'spark'],
    'data'       => ['sec_data', 'sliders'],
];

$languages = [];
foreach (zethal_languages() as $code => $info) {
    $languages[$code] = e($info['name']);
}
?>
<!DOCTYPE html>
<html <?= zethal_html_attrs() ?>>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="generator" content="Zethal Framework">
        <meta name="robots" content="noindex,nofollow">
        <title><?= t('title') ?> · <?= t('app_name') ?></title>
        <link rel="stylesheet" href="<?= asset('styles.css') ?>">
        <script src="<?= asset('script.js') ?>"></script>
    </head>
    <body class="page-settings" data-lang="<?= e(zethal_lang()) ?>" data-icons="<?= asset('zethal-icons.php') ?>">
        <script type="application/json" id="zethalText"><?= zethal_js_texts() ?></script>
        <div class="capsule">
            <div class="app-shell">
                <aside class="sidebar">
                    <a class="sidebar-brand" href="index.php"><?= icon('arrow-left', 18) ?><span><?= t('app_name') ?></span></a>
                    <nav class="sidebar-nav" aria-label="<?= t('title') ?>">
<?php foreach ($sections as $id => [$label, $ico]): ?>
                        <a href="#<?= $id ?>" data-section="<?= $id ?>"><?= icon($ico, 15) ?><span><?= t($label) ?></span></a>
<?php endforeach; ?>
                    </nav>
                    <div class="sidebar-footer">
                        <button class="theme-switch-btn" type="button" data-theme-toggle><?= icon('theme', 15) ?><span><?= t('toggle_theme') ?></span></button>
                    </div>
                </aside>

                <div class="main-col">
                    <main class="settings-main">
                        <header class="settings-header">
                            <h1><?= t('title') ?></h1>
                            <p><?= t('lead') ?></p>
                        </header>

                        <form id="settingsForm">
                            <?= section('appearance', 'theme', 'sec_appearance',
                                setting('globe', 'language', 'language_hint', select('lang', 'language', $languages))
                                . setting('theme', 'theme', 'theme_hint', select('theme', 'theme', ['system' => t('theme_system'), 'light' => t('theme_light'), 'dark' => t('theme_dark')]))
                                . setting('sliders', 'capsule', 'capsule_hint', toggle('capsule', 'capsule'))
                                . setting('news', 'compact', 'compact_hint', toggle('compact', 'compact'))
                                . setting('plus', 'shortcuts', 'shortcuts_hint', toggle('showShortcuts', 'shortcuts'))) ?>

                            <?= section('search', 'search', 'sec_search',
                                setting('news', 'per_page', '', select('perPage', 'per_page', ['10' => '10', '20' => '20', '30' => '30']))
                                . setting('shield', 'safe', 'safe_hint', select('safe', 'safe', ['strict' => t('safe_strict'), 'moderate' => t('safe_moderate'), 'off' => t('safe_off')]))
                                . setting('arrow', 'new_tab', '', toggle('newTab', 'new_tab'))
                                . setting('search', 'bangs', 'bangs_hint', bang_table(), true)) ?>

                            <?= section('privacy', 'shield', 'sec_privacy',
                                setting('close', 'strip', 'strip_hint', toggle('stripTracking', 'strip'))
                                . setting('globe', 'hidden', 'hidden_hint', '<textarea class="setting-textarea" name="hiddenDomains" rows="4" spellcheck="false" placeholder="example.com"></textarea>', true)) ?>

                            <?= section('ai', 'spark', 'sec_ai',
                                setting('spark', 'ai_summary', 'ai_summary_hint', toggle('aiSummary', 'ai_summary'))
                                . setting('camera', 'photo', 'photo_hint', '')) ?>

                            <?= section('data', 'sliders', 'sec_data',
                                setting('close', 'clear', 'clear_hint', '<button type="button" class="btn btn-ghost" id="clearData">' . t('clear_btn') . '</button>')) ?>

                            <div class="settings-actions">
                                <button type="button" class="btn btn-ghost" id="settingsReset"><?= t('reset') ?></button>
                                <button type="submit" class="btn"><?= t('save') ?></button>
                            </div>
                            <p class="toast" id="settingsToast" role="status"></p>
                        </form>
                    </main>

                    <footer class="footer">
                        <span>&copy; <?= date('Y') ?> <?= t('app_name') ?></span>
                        <span class="footer-sep">·</span>
                        <span><?= t('built_with') ?></span>
                    </footer>
                </div>
            </div>
        </div>
    </body>
</html>
