<?php

$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    http_response_code(500);
    die('<div style="font-family:monospace;color:#c00;padding:2rem">Erreur : fichier .env manquant. Copiez .env.example en .env et renseignez vos clés.</div>');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$parsed = [];
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $parsed[trim($key)] = trim($value);
}

define('FLICKR_API_KEY', $parsed['FLICKR_API_KEY'] ?? '');
define('FLICKR_SECRET',  $parsed['FLICKR_SECRET']  ?? '');
define('FLICKR_USER_ID', $parsed['FLICKR_USER_ID'] ?? '');
define('DB_HOST',      $parsed['DB_HOST']      ?? 'localhost');
define('DB_NAME',      $parsed['DB_NAME']      ?? '');
define('DB_USER',      $parsed['DB_USER']      ?? '');
define('DB_PASS',      $parsed['DB_PASS']      ?? '');
define('MAIL_HOST',    $parsed['MAIL_HOST']    ?? '');
define('MAIL_PORT',    (int)($parsed['MAIL_PORT'] ?? 465));
define('MAIL_USER',    $parsed['MAIL_USER']    ?? '');
define('MAIL_PASS',    $parsed['MAIL_PASS']    ?? '');
define('NOTIFY_EMAIL', $parsed['NOTIFY_EMAIL'] ?? '');
unset($parsed);

if (empty(FLICKR_API_KEY) || empty(FLICKR_USER_ID)) {
    http_response_code(500);
    die('<div style="font-family:monospace;color:#c00;padding:2rem">Erreur : FLICKR_API_KEY et FLICKR_USER_ID sont requis dans .env</div>');
}
