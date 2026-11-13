<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\PresentationImages;

$banner = $argv[1] ?? '';
$portrait = $argv[2] ?? '';
if (!is_file($banner) || !is_file($portrait)) {
    fwrite(STDERR, "Usage : php bin/set-presentation-images.php bandeau.jpg portrait.jpg\n");
    exit(1);
}
ini_set('memory_limit', '1024M');
echo 'Bandeau : ' . PresentationImages::replace('banner', $banner) . "\n";
echo 'Portrait : ' . PresentationImages::replace('portrait', $portrait) . "\n";
