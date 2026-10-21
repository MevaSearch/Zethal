<?php

declare(strict_types=1);

require __DIR__ . '/lang.php';
require __DIR__ . '/bangs.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

zethal_texts('index');
?>
<!DOCTYPE html>
<html <?= zethal_html_attrs() ?>>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="generator" content="Zethal Framework">
        <meta name="description" content="<?= t('app_description') ?>">
        <title><?= t('app_name') ?></title>
        <link rel="stylesheet" href="<?= asset('styles.css') ?>">
        <script src="<?= asset('script.js') ?>"></script>
    </head>
    <body class="page-home" data-ai-endpoint="ai.php" data-icons="<?= asset('zethal-icons.php') ?>">
        <script type="application/json" id="zethalText"><?= zethal_js_texts() ?></script>
        <script type="application/json" id="zethalBangs"><?= zethal_bangs_json() ?></script>
        <div class="capsule">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="icon-btn" type="button" data-menu-toggle aria-expanded="false" aria-controls="sideMenu" aria-label="<?= t('menu') ?>"><?= icon('menu', 20) ?></button>
                    <a class="topbar-brand" href="index.php"><?= t('app_name') ?></a>
                </div>
                <div class="topbar-actions">
                    <a class="icon-btn" href="settings.php" aria-label="<?= t('settings') ?>"><?= icon('settings', 20) ?></a>
                    <button class="icon-btn" type="button" data-theme-toggle aria-label="<?= t('toggle_theme') ?>"><?= icon('theme', 20) ?></button>
                </div>
            </header>

            <div class="side-menu-backdrop" data-menu-close hidden></div>
            <nav class="side-menu" id="sideMenu" aria-label="<?= t('menu') ?>" hidden>
                <div class="side-menu-head">
                    <span class="topbar-brand"><?= t('app_name') ?></span>
                    <button class="icon-btn" type="button" data-menu-close aria-label="<?= t('close') ?>"><?= icon('close', 18) ?></button>
                </div>
                <a href="index.php"><?= icon('search', 17) ?><span><?= t('menu_search') ?></span></a>
                <a href="results.php?type=images" data-needs-query="images"><?= icon('image', 17) ?><span><?= t('menu_images') ?></span></a>
                <a href="results.php?type=news" data-needs-query="news"><?= icon('news', 17) ?><span><?= t('menu_news') ?></span></a>
                <button type="button" data-image-search><?= icon('camera', 17) ?><span><?= t('image_search') ?></span></button>
                <span class="side-menu-sep"></span>
                <a href="settings.php"><?= icon('settings', 17) ?><span><?= t('settings') ?></span></a>
                <a href="settings.php#privacy"><?= icon('shield', 17) ?><span><?= t('menu_privacy') ?></span></a>
                <button type="button" data-theme-toggle><?= icon('theme', 17) ?><span><?= t('toggle_theme') ?></span></button>
            </nav>

            <main class="home">
                <div class="home-head">
                    <h1 class="wordmark"><?= t('app_name') ?></h1>
                    <p class="tagline"><?= t('app_tagline') ?></p>
                </div>

                <form class="searchbox" action="results.php" method="get" role="search">
                    <span class="searchbox-lead"><?= icon('globe', 19) ?></span>
                    <input type="search" name="q" placeholder="<?= t('search_placeholder') ?>" autocomplete="off" autofocus required maxlength="400">
                    <div class="searchbox-tools">
                        <button class="tool-btn" type="button" data-image-search aria-label="<?= t('image_search') ?>"><?= icon('camera', 18) ?></button>
                        <button class="tool-btn" type="button" data-voice aria-label="<?= t('voice_search') ?>" hidden><?= icon('mic', 18) ?></button>
                        <button class="tool-btn" type="submit" aria-label="<?= t('search') ?>"><?= icon('search', 18) ?></button>
                    </div>
                </form>

                <button class="btn-soft" type="button" data-submit-home><?= t('home_button', ['app' => $GLOBALS['ZT']['app_name'] ?? '']) ?></button>

                <div class="shortcuts" id="shortcuts">
                    <button class="shortcut shortcut-add" type="button" id="shortcutAdd">
                        <span class="shortcut-tile"><?= icon('plus', 22) ?></span>
                        <span class="shortcut-label"><?= t('shortcut_add') ?></span>
                    </button>
                </div>
            </main>

            <footer class="footer">
                <span>&copy; <?= date('Y') ?> <?= t('app_name') ?></span>
                <span class="footer-sep">·</span>
                <a href="settings.php"><?= t('settings') ?></a>
                <span class="footer-sep">·</span>
                <span><?= t('built_with') ?></span>
            </footer>
        </div>
    </body>
</html>
