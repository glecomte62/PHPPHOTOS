<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\Database;

foreach (['storage/logs', 'storage/ratelimit', 'public/media', 'public/assets/img/presentation'] as $dir) {
    if (!is_dir(ROOT . '/' . $dir)) {
        mkdir(ROOT . '/' . $dir, 0775, true);
    }
}

foreach (['schema.sql', 'seed.sql'] as $file) {
    $sql = (string) file_get_contents(ROOT . '/database/' . $file);
    foreach (preg_split('/;\s*\n/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            Database::pdo()->exec($statement);
        }
    }
    echo "OK $file\n";
}
