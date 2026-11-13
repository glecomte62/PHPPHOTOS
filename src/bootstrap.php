<?php
declare(strict_types=1);

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once ROOT . '/src/helpers.php';

App\Services\Env::load(ROOT . '/.env');

date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL);
ini_set('display_errors', App\Services\Env::get('APP_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/app.log');
