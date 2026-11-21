<?php
/** @var string $content */
$meta = ($meta ?? []) + ['title' => null, 'description' => null, 'image' => null, 'type' => 'website', 'jsonld' => null, 'robots' => null];
$siteName = 'Guillaume Lecomte — Photographe';
$fullTitle = $meta['title'] ? $meta['title'] . ' · Guillaume Lecomte' : $siteName;
$description = $meta['description'] ?: 'Portfolio de Guillaume Lecomte, photographe amateur et pilote ULM : paysages, patrimoine, aviation et vues du ciel.';
$canonical = absolute_url((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($meta['robots']): ?>
<meta name="robots" content="<?= e($meta['robots']) ?>">
<?php endif; ?>
<link rel="alternate" type="application/rss+xml" title="Nouveaux albums — Guillaume Lecomte" href="/rss.xml">
<link rel="search" type="application/opensearchdescription+xml" title="Guillaume Lecomte" href="/opensearch.xml">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="<?= e($meta['type']) ?>">
<meta property="og:title" content="<?= e($meta['title'] ?? $siteName) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($meta['image']): ?>
<meta property="og:image" content="<?= e(absolute_url($meta['image'])) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<meta name="theme-color" content="#f4f1ec">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="<?= e(asset('img/favicon-32.png')) ?>" type="image/png" sizes="32x32">
<link rel="icon" href="<?= e(asset('img/icon-512.png')) ?>" type="image/png" sizes="512x512">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php foreach ($styles ?? [] as $href): ?>
<link rel="stylesheet" href="<?= e($href) ?>">
<?php endforeach; ?>
<?php if ($meta['jsonld']): ?>
<script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body>
<?= $view->partial('partials/header') ?>
<main id="contenu">
<?= $content ?>
</main>
<?= $view->partial('partials/footer') ?>
<?php foreach ($scripts ?? [] as $src): ?>
<script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
<?= $view->partial('partials/matomo') ?>
</body>
</html>
