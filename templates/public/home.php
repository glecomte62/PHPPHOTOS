<?php
/**
 * @var ?array $hero
 * @var string $tagline
 * @var ?array $featured
 * @var list<array> $skyPhotos
 * @var string $skyIntro
 * @var list<array> $latest
 * @var list<array> $themes
 */
?>
<?php if ($hero): ?>
<section class="hero">
  <?= $view->partial('partials/picture', ['key' => $hero['file_key'], 'alt' => photo_label($hero['title'], $hero['album_title']), 'sizes' => '100vw', 'loading' => 'eager', 'class' => 'hero__img']) ?>
</section>
<?php endif; ?>

<section class="intro">
  <h1 class="visually-hidden">Guillaume Lecomte — Photographe &amp; pilote ULM</h1>
  <p class="intro__tagline"><?= e($tagline) ?></p>
  <?= $view->partial('partials/trace') ?>
</section>

<?php if ($featured): ?>
<section class="sky" id="galeries">
  <div class="sky__text">
    <p class="eyebrow">Depuis le cockpit</p>
    <h2 class="section-title"><?= e($featured['title']) ?></h2>
    <?php if ($skyIntro !== ''): ?><p><?= nl2br(e($skyIntro)) ?></p><?php endif; ?>
    <a class="link-arrow" href="/<?= e($featured['slug']) ?>">Voir la galerie</a>
  </div>
  <div class="sky__mosaic">
    <?php foreach ($skyPhotos as $p): ?>
      <a href="/<?= e($p['theme_slug']) ?>/<?= e($p['album_slug']) ?>/photo/<?= (int) $p['id'] ?>">
        <?= $view->partial('partials/picture', ['key' => $p['file_key'], 'alt' => photo_label($p['title'], $p['album_title']), 'sizes' => '(min-width: 900px) 30vw, 50vw']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($latest): ?>
<section class="section">
  <h2 class="section-title">Derniers albums</h2>
  <div class="card-grid">
    <?php foreach ($latest as $album): ?>
      <?= $view->partial('partials/album-card', ['album' => $album]) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($themes): ?>
<section class="section"<?= $featured ? '' : ' id="galeries"' ?>>
  <h2 class="section-title">Galeries</h2>
  <div class="theme-grid">
    <?php foreach ($themes as $theme): ?>
      <a class="theme-card" href="/<?= e($theme['slug']) ?>">
        <?php if (!empty($theme['cover_key'])): ?>
          <?= $view->partial('partials/picture', ['key' => $theme['cover_key'], 'alt' => '', 'sizes' => '(min-width: 900px) 45vw, 100vw']) ?>
        <?php endif; ?>
        <span class="theme-card__title"><?= e($theme['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!$hero && !$featured && !$latest && !$themes): ?>
<section class="section empty" id="galeries">
  <p>Les premières galeries arrivent bientôt.</p>
</section>
<?php endif; ?>
