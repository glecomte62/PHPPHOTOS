<?php
/**
 * @var ?array $hero
 * @var list<array> $reel
 * @var string $tagline
 * @var ?array $featured
 * @var list<array> $skyPhotos
 * @var string $skyIntro
 * @var list<array> $latest
 * @var list<array> $themes
 */
?>
<?php if ($reel): ?>
<section class="hero<?= count($reel) > 1 ? ' hero--reel' : '' ?>" aria-roledescription="carrousel" aria-label="Photos à la une">
  <?php foreach ($reel as $i => $p): ?>
    <a class="hero__slide<?= $i === 0 ? ' is-active' : '' ?>" href="/<?= e($p['theme_slug']) ?>/<?= e($p['album_slug']) ?>/photo/<?= (int) $p['id'] ?>"<?= $i === 0 ? '' : ' tabindex="-1" aria-hidden="true"' ?>>
      <?= $view->partial('partials/picture', ['key' => $p['file_key'], 'alt' => photo_label($p['title'], $p['album_title']), 'sizes' => '100vw', 'loading' => $i === 0 ? 'eager' : 'lazy', 'class' => 'hero__img']) ?>
      <span class="hero__caption"><?= e($p['album_title']) ?></span>
    </a>
  <?php endforeach; ?>
  <?php if (count($reel) > 1): ?>
    <button type="button" class="hero__toggle" aria-label="Mettre le diaporama en pause" hidden>
      <svg class="hero__icon-pause" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true"><rect x="2" y="1" width="3" height="10"/><rect x="7" y="1" width="3" height="10"/></svg>
      <svg class="hero__icon-play" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true"><path d="M3 1l8 5-8 5z"/></svg>
    </button>
  <?php endif; ?>
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

<?php if (!$reel && !$featured && !$latest && !$themes): ?>
<section class="section empty" id="galeries">
  <p>Les premières galeries arrivent bientôt.</p>
</section>
<?php endif; ?>
