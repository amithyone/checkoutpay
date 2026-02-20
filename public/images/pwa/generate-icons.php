<?php
/**
 * One-time script to generate PWA placeholder icons (run: php generate-icons.php).
 * Replace icon-192.png and icon-512.png with your own for production.
 */
$sizes = [192, 512];
foreach ($sizes as $size) {
    $im = imagecreatetruecolor($size, $size);
    if (!$im) continue;
    $bg = imagecolorallocate($im, 0x3C, 0x50, 0xE0); // #3C50E0
    imagefill($im, 0, 0, $bg);
    $white = imagecolorallocate($im, 255, 255, 255);
    $font = 5; // built-in font
    $text = 'CP';
    $x = (int)(($size - imagefontwidth($font) * strlen($text)) / 2);
    $y = (int)(($size - imagefontheight($font)) / 2);
    imagestring($im, $font, $x, $y, $text, $white);
    imagepng($im, __DIR__ . "/icon-{$size}.png");
    imagedestroy($im);
}
echo "Generated icon-192.png and icon-512.png\n";
