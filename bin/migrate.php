<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\Database;

foreach (['storage/logs', 'storage/ratelimit', 'storage/sessions', 'public/media', 'public/assets/img/presentation'] as $dir) {
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

// Évolutions de schéma sur une base existante (CREATE TABLE IF NOT EXISTS ne les applique pas)
$hasColumn = static fn (string $table, string $column): bool => (bool) Database::value(
    'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    [$table, $column]
);
if (!$hasColumn('photos', 'file_hash')) {
    Database::pdo()->exec('ALTER TABLE photos ADD COLUMN file_hash CHAR(40) NULL AFTER file_key, ADD KEY idx_album_hash (album_id, file_hash)');
    echo "OK photos.file_hash ajoutée\n";
}
if (!$hasColumn('photos', 'original_name')) {
    Database::pdo()->exec('ALTER TABLE photos ADD COLUMN original_name VARCHAR(190) NULL AFTER file_hash');
    echo "OK photos.original_name ajoutée\n";
}
