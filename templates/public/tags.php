<?php /** @var list<array{slug: string, name: string, n: int}> $cloud @var int $max */ ?>
<header class="page-header">
  <h1 class="page-title">Mots-clés</h1>
  <p class="page-intro">Lieux, sujets, avions, monuments… Choisissez un mot-clé pour voir toutes les photos qui le portent, tous albums confondus.</p>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="section narrow">
  <?php if ($cloud): ?>
    <ul class="tag-cloud">
      <?php foreach ($cloud as $tag): ?>
        <?php $weight = log($tag['n'] + 1) / log($max + 1); // 0 à 1 ?>
        <li><a href="/mots-cles/<?= e($tag['slug']) ?>" style="font-size: <?= round(0.9 + $weight * 1.1, 2) ?>rem" title="<?= $tag['n'] ?> photo<?= $tag['n'] > 1 ? 's' : '' ?>"><?= e($tag['name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="empty">Aucun mot-clé pour le moment.</p>
  <?php endif; ?>
</section>
