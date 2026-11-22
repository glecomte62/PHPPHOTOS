<?php /** @var array<string, mixed> $album @var ?string $variant « overlay » : titre posé sur la photo */ ?>
<?php
$overlay = ($variant ?? '') === 'overlay';
$count = (int) ($album['photo_count'] ?? 0);
$meta = implode(' · ', array_filter([(string) $album['location'], format_month_fr($album['shot_on'])]));
?>
<a class="album-card<?= $overlay ? ' album-card--overlay' : '' ?>" href="/<?= e($album['theme_slug']) ?>/<?= e($album['slug']) ?>">
  <div class="album-card__img">
    <?php if (!empty($album['cover_key'])): ?>
      <?= $view->partial('partials/picture', ['key' => $album['cover_key'], 'alt' => '', 'sizes' => '(min-width: 900px) 30vw, (min-width: 600px) 45vw, 100vw']) ?>
    <?php endif; ?>
    <?php if ($count > 0): ?><span class="album-card__count"><?= $count ?> photo<?= $count > 1 ? 's' : '' ?></span><?php endif; ?>
    <?php if ($overlay): ?>
      <span class="album-card__over">
        <?php if (!empty($album['theme_title'])): ?><span class="album-card__theme"><?= e($album['theme_title']) ?></span><?php endif; ?>
        <span class="album-card__title"><?= e($album['title']) ?></span>
        <?php if ($meta !== ''): ?><span class="album-card__meta"><?= e($meta) ?></span><?php endif; ?>
      </span>
    <?php endif; ?>
  </div>
  <?php if (!$overlay): ?>
    <h3 class="album-card__title"><?= e($album['title']) ?></h3>
    <p class="album-card__meta"><?= e($meta) ?></p>
  <?php endif; ?>
</a>
