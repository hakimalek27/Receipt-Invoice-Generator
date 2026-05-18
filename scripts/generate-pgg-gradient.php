<?php

/**
 * Generate the PGG gradient header PNG (purple -> gold).
 * Run on demand: php scripts/generate-pgg-gradient.php
 * Output: resources/views/pdf/persada/assets/header-gradient.png
 *
 * Requires ext-gd. Skipped if unavailable.
 */

declare(strict_types=1);

if (! function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD extension is not available; aborting.\n");
    exit(1);
}

$width = 1200;
$height = 80;

$im = imagecreatetruecolor($width, $height);

// PGG palette: purple (#5d3a9b) -> deep accent (#3f2872) -> gold (#D4AF37)
$purple = [0x5d, 0x3a, 0x9b];
$mid = [0x3f, 0x28, 0x72];
$gold = [0xD4, 0xAF, 0x37];

for ($x = 0; $x < $width; $x++) {
    $t = $x / ($width - 1);
    if ($t < 0.65) {
        $u = $t / 0.65;
        $r = (int) round($purple[0] * (1 - $u) + $mid[0] * $u);
        $g = (int) round($purple[1] * (1 - $u) + $mid[1] * $u);
        $b = (int) round($purple[2] * (1 - $u) + $mid[2] * $u);
    } else {
        $u = ($t - 0.65) / 0.35;
        $r = (int) round($mid[0] * (1 - $u) + $gold[0] * $u);
        $g = (int) round($mid[1] * (1 - $u) + $gold[1] * $u);
        $b = (int) round($mid[2] * (1 - $u) + $gold[2] * $u);
    }
    $color = imagecolorallocate($im, $r, $g, $b);
    imagefilledrectangle($im, $x, 0, $x, $height - 1, $color);
}

// Bottom border accent (gold thin line)
$accent = imagecolorallocate($im, 0xD4, 0xAF, 0x37);
imagefilledrectangle($im, 0, $height - 3, $width - 1, $height - 1, $accent);

$outPath = __DIR__ . '/../resources/views/pdf/persada/assets/header-gradient.png';
if (! is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0755, true);
}

imagepng($im, $outPath, 6);
imagedestroy($im);

echo "Wrote: {$outPath}\n";
