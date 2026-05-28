<?php

$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
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
unset($parsed);

if (empty(FLICKR_API_KEY) || empty(FLICKR_USER_ID)) {
    die('<div style="font-family:monospace;color:#c00;padding:2rem">Erreur : FLICKR_API_KEY et FLICKR_USER_ID sont requis dans .env</div>');
}
