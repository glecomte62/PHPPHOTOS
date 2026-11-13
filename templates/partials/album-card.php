<?php /** @var array<string, mixed> $album */ ?>
<a class="album-card" href="/<?= e($album['theme_slug']) ?>/<?= e($album['slug']) ?>">
  <div class="album-card__img">
    <?php if (!empty($album['cover_key'])): ?>
      <?= $view->partial('partials/picture', ['key' => $album['cover_key'], 'alt' => '', 'sizes' => '(min-width: 900px) 30vw, (min-width: 600px) 45vw, 100vw']) ?>
    <?php endif; ?>
  </div>
  <h3 class="album-card__title"><?= e($album['title']) ?></h3>
  <p class="album-card__meta"><?= e(implode(' · ', array_filter([(string) $album['location'], format_month_fr($album['shot_on'])]))) ?></p>
</a>
