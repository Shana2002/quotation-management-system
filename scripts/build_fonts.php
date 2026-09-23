<?php

declare(strict_types=1);

/**
 * build_fonts.php
 *
 * Asks TCPDF to build its own font metric files (`.php` + `.z` + `.ctg.z`)
 * from the Carlito TTFs vendored alongside it. Run by scripts/install_fonts.ps1
 * after the TTFs have been downloaded; it is idempotent, since TCPDF returns
 * early when a generated `<name>.php` already exists.
 *
 * The TTF filenames are load-bearing: TCPDF_FONTS::addTTFfont() derives both
 * the font name and its style from the filename (stripping "regular" to
 * nothing, "bold" to "b", "italic" to "i"), so the four Carlito faces land on
 * exactly the conventional names carlito / carlitob / carlitoi / carlitobi
 * that SetFont() expects for the '', 'B', 'I' and 'BI' styles.
 */

require_once dirname(__DIR__) . '/libs/tcpdf/tcpdf.php';

$dir = dirname(__DIR__) . '/libs/tcpdf/fonts/';

if (!is_dir($dir)) {
    fwrite(STDERR, "TCPDF fonts directory not found at {$dir}\n");
    exit(1);
}

$faces = ['Carlito-Regular.ttf', 'Carlito-Bold.ttf', 'Carlito-Italic.ttf', 'Carlito-BoldItalic.ttf'];

$missing = [];
foreach ($faces as $face) {
    if (!is_file($dir . $face)) {
        $missing[] = $face;
    }
}
if ($missing !== []) {
    fwrite(STDERR, 'Missing TTF(s): ' . implode(', ', $missing) . "\n");
    exit(1);
}

// TCPDF's own docblock warns that its font code is noisy on PHP 8; keep the
// build output readable without hiding a genuine failure.
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

foreach ($faces as $face) {
    $name = TCPDF_FONTS::addTTFfont($dir . $face, 'TrueTypeUnicode', '', 32);
    if ($name === false) {
        fwrite(STDERR, "Failed to build {$face}\n");
        exit(1);
    }
    echo "  {$face} -> {$name}\n";
}

echo "Font metrics built.\n";
