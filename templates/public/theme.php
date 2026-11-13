<?php /** @var array $theme @var list<array> $albums */ ?>
<header class="page-header">
  <p class="eyebrow">Galerie</p>
  <h1 class="page-title"><?= e($theme['title']) ?></h1>
  <?php if ($theme['intro']): ?><p class="page-intro"><?= nl2br(e($theme['intro'])) ?></p><?php endif; ?>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="section">
  <?php if ($albums): ?>
  <div class="card-grid">
    <?php foreach ($albums as $album): ?>
      <?= $view->partial('partials/album-card', ['album' => $album]) ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="empty">Aucun album publié dans cette galerie pour l'instant.</p>
  <?php endif; ?>
</section>
