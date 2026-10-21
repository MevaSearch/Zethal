<?php

declare(strict_types=1);

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');

$icons = [
    'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'image'    => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5-9 9"/>',
    'play'     => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 5.5 3.5-5.5 3.5z"/>',
    'news'     => '<rect x="3" y="4" width="14" height="16" rx="2"/><path d="M17 8h2.5a1.5 1.5 0 0 1 1.5 1.5V18a2 2 0 0 1-2 2H7"/><path d="M7 8h6"/><path d="M7 12h6"/><path d="M7 16h4"/>',
    'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
    'mic'      => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><path d="M12 18v3"/>',
    'camera'   => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.09 10.76L21.55 11.02L21.55 12.98L19.09 13.24A7.2 7.2 0 0 1 17.89 16.14L19.44 18.06L18.06 19.44L16.14 17.89A7.2 7.2 0 0 1 13.24 19.09L12.98 21.55L11.02 21.55L10.76 19.09A7.2 7.2 0 0 1 7.86 17.89L5.94 19.44L4.56 18.06L6.11 16.14A7.2 7.2 0 0 1 4.91 13.24L2.45 12.98L2.45 11.02L4.91 10.76A7.2 7.2 0 0 1 6.11 7.86L4.56 5.94L5.94 4.56L7.86 6.11A7.2 7.2 0 0 1 10.76 4.91L11.02 2.45L12.98 2.45L13.24 4.91A7.2 7.2 0 0 1 16.14 6.11L18.06 4.56L19.44 5.94L17.89 7.86A7.2 7.2 0 0 1 19.09 10.76z"/>',
    'theme'    => '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor"/>',
    'arrow'    => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
    'plus'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    'close'    => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
    'menu'       => '<path d="M4 7h16"/><path d="M4 12h10"/><path d="M4 17h16"/>',
    'arrow-left' => '<path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>',
    'upload'     => '<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
    'spark'    => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 16l.7 1.8 1.8.7-1.8.7L19 21l-.7-1.8-1.8-.7 1.8-.7z"/>',
    'shield'   => '<path d="M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    'sliders'  => '<path d="M4 7h10"/><path d="M18 7h2"/><circle cx="16" cy="7" r="2"/><path d="M4 17h2"/><path d="M10 17h10"/><circle cx="8" cy="17" r="2"/>',
];

echo '<svg xmlns="http://www.w3.org/2000/svg">';
foreach ($icons as $id => $paths) {
    echo '<symbol id="i-' . $id . '" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</g></symbol>';
}
echo '</svg>';
