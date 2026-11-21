<?php /** @var string $content */ ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(App\Services\Csrf::token()) ?>">
<title><?= e(($title ?? 'Administration') . ' · Admin') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<?php if (!empty($nav)): ?>
<header class="admin-bar">
  <a class="admin-bar__brand" href="/admin">Administration</a>
  <nav>
    <a href="/admin/themes">Thèmes</a>
    <a href="/admin/albums">Albums</a>
    <?php $pendingComments = \App\Models\Comment::countPending(); ?>
    <a href="/admin/commentaires">Commentaires<?php if ($pendingComments > 0): ?> <span class="badge badge--featured" title="À valider"><?= $pendingComments ?></span><?php endif; ?></a>
    <a href="/admin/content">Contenus</a>
    <a href="/" target="_blank" rel="noopener">Voir le site ↗</a>
  </nav>
  <form method="post" action="/admin/logout"><?= csrf_field() ?><button class="link-button" type="submit">Déconnexion</button></form>
</header>
<?php endif; ?>
<main class="admin-main">
<?php if ($m = flash('success')): ?><p class="flash flash--success"><?= e($m) ?></p><?php endif; ?>
<?php if ($m = flash('error')): ?><p class="flash flash--error"><?= e($m) ?></p><?php endif; ?>
<?= $content ?>
</main>
<?php foreach ($scripts ?? [] as $src): ?>
<script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
