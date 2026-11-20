<?php
declare(strict_types=1);

/*
 * Régénère les images d'un album à partir des originaux, retrouvés par leur empreinte SHA-1.
 * Par défaut, seules les photos dont le profil couleur n'est pas sRGB sont retraitées (--all : toutes).
 * Chaque photo retraitée reçoit une nouvelle clé de fichier : les anciennes images, mises en cache
 * un an par les navigateurs, ne sont plus servies. Titres, légendes et ordre sont conservés.
 *
 * Usage : php bin/reprocess-photos.php <album_id> <dossier des originaux> [--all]
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\Database;
use App\Services\ExifReader;
use App\Services\ImageProcessor;

ini_set('memory_limit', '1536M');
set_time_limit(0);

$albumId = (int) ($argv[1] ?? 0);
$dir = rtrim((string) ($argv[2] ?? ''), '/');
$all = in_array('--all', $argv, true);
if ($albumId <= 0 || !is_dir($dir)) {
    fwrite(STDERR, "Usage : php bin/reprocess-photos.php <album_id> <dossier des originaux> [--all]\n");
    exit(1);
}

$byHash = [];
foreach (Database::all('SELECT id, file_key, file_hash FROM photos WHERE album_id = ? AND file_hash IS NOT NULL', [$albumId]) as $row) {
    $byHash[$row['file_hash']] = $row;
}

$done = 0;
$files = array_filter(glob($dir . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) ?: [], static fn (string $f): bool => !str_starts_with(basename($f), '._'));
foreach ($files as $path) {
    $photo = $byHash[(string) sha1_file($path)] ?? null;
    if ($photo === null) {
        echo 'absent de l\'album : ' . basename($path) . "\n";
        continue;
    }
    $profile = ImageProcessor::iccDescription($path);
    if (!$all && ($profile === null || stripos($profile, 'srgb') !== false)) {
        continue;
    }
    $exif = (new ExifReader())->read($path);
    $key = bin2hex(random_bytes(12));
    $size = (new ImageProcessor(requireSrgb: true))->process($path, media_dir($key), $key, $exif['orientation']);
    Database::run('UPDATE photos SET file_key = ?, width = ?, height = ? WHERE id = ?', [$key, $size['width'], $size['height'], $photo['id']]);
    ImageProcessor::deleteVariants(media_dir($photo['file_key']), $photo['file_key']);
    $done++;
    echo 'retraitée : ' . basename($path) . ' (' . ($profile ?? 'sans profil') . ") -> photo {$photo['id']}\n";
}
echo "$done photo(s) retraitée(s)\n";
