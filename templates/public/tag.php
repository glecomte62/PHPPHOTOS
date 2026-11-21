<?php /** @var array $tag @var list<array> $photos @var int $total @var int $page @var int $pages */ ?>
<header class="page-header">
  <p class="eyebrow"><a href="/mots-cles">Mots-clés</a></p>
  <h1 class="page-title"><?= e($tag['name']) ?></h1>
  <p class="page-meta"><?= $total ?> photo<?= $total > 1 ? 's' : '' ?><?= $pages > 1 ? ' · page ' . $page . ' sur ' . $pages : '' ?></p>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="section"></section>
<section class="mosaic" data-mosaic>
  <?php foreach ($photos as $p): ?>
    <?php $label = photo_label((string) $p['title'], $p['album_title'] . ' — photo'); ?>
    <a class="mosaic__item" href="<?= e('/' . $p['theme_slug'] . '/' . $p['album_slug'] . '/photo/' . $p['id']) ?>"
       data-ratio="<?= e(round((int) $p['width'] / max(1, (int) $p['height']), 4)) ?>" title="<?= e($label . ' · ' . $p['album_title']) ?>">
      <?= $view->partial('partials/picture', [
          'key' => $p['file_key'], 'alt' => $label, 'width' => $p['width'], 'height' => $p['height'],
          'sizes' => '(min-width: 900px) 33vw, 50vw',
      ]) ?>
    </a>
  <?php endforeach; ?>
</section>
<?= $view->partial('partials/pager', ['page' => $page, 'pages' => $pages, 'href' => static fn (int $n): string => '/mots-cles/' . $tag['slug'] . ($n > 1 ? '?page=' . $n : '')]) ?>
