<?php /** @var string $by @var list<array> $photos */ ?>
<header class="page-header">
  <h1 class="page-title"><?= $by === 'aimees' ? 'Les plus aimées' : 'Les plus vues' ?></h1>
  <p class="page-intro">Les photos que les visiteurs regardent le plus, ou qu'ils ont marquées d'un cœur.</p>
  <nav class="archive" aria-label="Classement">
    <a href="/populaires"<?= $by === 'vues' ? ' aria-current="page"' : '' ?>>Les plus vues</a>
    <a href="/populaires?tri=aimees"<?= $by === 'aimees' ? ' aria-current="page"' : '' ?>>Les plus aimées</a>
  </nav>
  <?= $view->partial('partials/trace') ?>
</header>
<?php if ($photos): ?>
<section class="section"></section>
<section class="mosaic" data-mosaic>
  <?php foreach ($photos as $p): ?>
    <?php
      $label = photo_label((string) $p['title'], $p['album_title'] . ' — photo');
      $stats = (int) $p['views'] . ' vue' . ((int) $p['views'] > 1 ? 's' : '') . ' · ' . (int) $p['likes'] . ' j\'aime';
    ?>
    <a class="mosaic__item" href="<?= e('/' . $p['theme_slug'] . '/' . $p['album_slug'] . '/photo/' . $p['id']) ?>"
       data-ratio="<?= e(round((int) $p['width'] / max(1, (int) $p['height']), 4)) ?>" title="<?= e($label . ' · ' . $p['album_title'] . ' · ' . $stats) ?>">
      <?= $view->partial('partials/picture', [
          'key' => $p['file_key'], 'alt' => $label, 'width' => $p['width'], 'height' => $p['height'],
          'sizes' => '(min-width: 900px) 33vw, 50vw',
      ]) ?>
    </a>
  <?php endforeach; ?>
</section>
<?php else: ?>
<section class="section"><p class="empty">Pas encore assez de visites pour établir un classement. Revenez bientôt !</p></section>
<?php endif; ?>
