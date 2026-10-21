<?php

declare(strict_types=1);

require __DIR__ . '/lang.php';
require __DIR__ . '/bangs.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https://upload.wikimedia.org https://thumb.wikimedia.org; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

$endpoint   = 'api.php';
$aiEndpoint = 'ai.php';

zethal_texts('results');

$q    = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 400);
$type = (string)($_GET['type'] ?? 'all');
$type = in_array($type, ['all', 'images', 'videos', 'news'], true) ? $type : 'all';
$page = max(1, min(20, (int)($_GET['page'] ?? 1)));

$bangTarget = zethal_bang_redirect($q);
if ($bangTarget !== null) {
    header('Location: ' . $bangTarget, true, 302);
    exit;
}

$tabs = [
    'all'    => ['tab_all', 'search'],
    'images' => ['tab_images', 'image'],
    'videos' => ['tab_videos', 'play'],
    'news'   => ['tab_news', 'news'],
];
?>
<!DOCTYPE html>
<html <?= zethal_html_attrs() ?>>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="generator" content="Zethal Framework">
        <meta name="robots" content="noindex,nofollow">
        <title><?= $q !== '' ? e($q) . ' · ' : '' ?><?= t('app_name') ?></title>
        <link rel="stylesheet" href="<?= asset('styles.css') ?>">
        <script src="<?= asset('script.js') ?>"></script>
        <script src="<?= asset('results.js') ?>" defer></script>
    </head>
    <body class="page-results" data-q="<?= e($q) ?>" data-type="<?= e($type) ?>" data-page="<?= $page ?>" data-endpoint="<?= e($endpoint) ?>" data-ai-endpoint="<?= e($aiEndpoint) ?>" data-icons="<?= asset('zethal-icons.php') ?>">
        <script type="application/json" id="zethalText"><?= zethal_js_texts() ?></script>
        <script type="application/json" id="zethalBangs"><?= zethal_bangs_json() ?></script>
        <div class="capsule">
            <header class="results-header">
                <div class="results-header-row">
                    <a class="brand" href="index.php"><?= t('app_name') ?></a>
                    <form class="searchbox" action="results.php" method="get" role="search">
                        <span class="searchbox-lead"><?= icon('globe', 18) ?></span>
                        <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('search_placeholder') ?>" autocomplete="off" required maxlength="400">
                        <input type="hidden" name="type" value="<?= e($type) ?>">
                        <div class="searchbox-tools">
                            <button class="tool-btn" type="button" data-image-search aria-label="<?= t('image_search') ?>"><?= icon('camera', 17) ?></button>
                            <button class="tool-btn" type="button" data-voice aria-label="<?= t('voice_search') ?>" hidden><?= icon('mic', 17) ?></button>
                            <button class="tool-btn" type="submit" aria-label="<?= t('search') ?>"><?= icon('search', 15) ?></button>
                        </div>
                    </form>
                    <div class="topbar-actions">
                        <a class="icon-btn" href="settings.php" aria-label="<?= t('settings') ?>"><?= icon('settings', 20) ?></a>
                        <button class="icon-btn" type="button" data-theme-toggle aria-label="<?= t('toggle_theme') ?>"><?= icon('theme', 20) ?></button>
                    </div>
                </div>
                <nav class="tabs">
    <?php foreach ($tabs as $key => [$label, $ico]): ?>
                    <a class="tab<?= $key === $type ? ' active' : '' ?>" href="results.php?q=<?= rawurlencode($q) ?>&amp;type=<?= $key ?>"><?= icon($ico, 16) ?><span><?= t($label) ?></span></a>
    <?php endforeach; ?>
                </nav>
            </header>

            <main class="results-layout">
                <section class="results-main">
                    <section class="ai-card" id="aiCard" hidden>
                        <p class="ai-label"><?= icon('spark', 14) ?><span id="aiLabel" data-ai="<?= t('ai_label') ?>" data-extractive="<?= t('summary_label') ?>"><?= t('ai_label') ?></span></p>
                        <p class="ai-text" id="aiText"></p>
                        <p class="ai-note" id="aiNote" data-ai="<?= t('ai_note') ?>" data-extractive="<?= t('summary_note') ?>"><?= t('ai_note') ?></p>
                    </section>
                    <p class="results-meta" id="resultsMeta"></p>
                    <div id="results" aria-live="polite"></div>
                    <nav class="pager" id="pager"></nav>
                </section>
                <aside class="results-side" id="panel"></aside>
            </main>

            <footer class="footer">
                <span>&copy; <?= date('Y') ?> <?= t('app_name') ?></span>
                <span class="footer-sep">·</span>
                <span><?= t('built_with') ?></span>
            </footer>
        </div>
    </body>
</html>
