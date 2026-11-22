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
 * @var array{photos: int, albums: int, themes: int, places: int, from: ?int, to: ?int} $stats
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
  <h1 class="intro__title">Guillaume Lecomte <span>Photographe &amp; pilote ULM</span></h1>
  <?php $same = static fn (string $s): string => mb_strtolower(trim(str_replace('&amp;', '&', $s))); ?>
  <?php if ($tagline !== '' && $same($tagline) !== $same('Photographe & pilote ULM')): ?>
    <p class="intro__tagline"><?= e($tagline) ?></p>
  <?php endif; ?>
  <?php if ($stats['photos'] > 0): ?>
  <ul class="stats" aria-label="Le site en chiffres">
    <li><a href="/photos"><strong data-count="<?= (int) $stats['photos'] ?>"><?= number_format($stats['photos'], 0, ',', ' ') ?></strong><span>photos</span></a></li>
    <li><a href="#albums"><strong data-count="<?= (int) $stats['albums'] ?>"><?= (int) $stats['albums'] ?></strong><span>albums</span></a></li>
    <li><a href="#galeries"><strong data-count="<?= (int) $stats['themes'] ?>"><?= (int) $stats['themes'] ?></strong><span>galeries</span></a></li>
    <?php if ($stats['places'] > 0): ?>
    <li><a href="/carte"><strong data-count="<?= (int) $stats['places'] ?>"><?= (int) $stats['places'] ?></strong><span>lieux sur la carte</span></a></li>
    <?php endif; ?>
    <?php if ($stats['from'] && $stats['to'] && $stats['to'] > $stats['from']): ?>
    <li><a href="/photos"><strong data-count="<?= $stats['to'] - $stats['from'] + 1 ?>"><?= $stats['to'] - $stats['from'] + 1 ?></strong><span>ans de photos <small><?= (int) $stats['from'] ?> – <?= (int) $stats['to'] ?></small></span></a></li>
    <?php endif; ?>
  </ul>
  <?php endif; ?>
</section>

<?php if ($featured): ?>
<section class="sky">
  <div class="sky__text">
    <p class="eyebrow">Depuis le cockpit</p>
    <h2 class="section-title section-title--big"><?= e($featured['title']) ?></h2>
    <p class="sky__count"><?= (int) $featured['published_albums'] ?> album<?= (int) $featured['published_albums'] > 1 ? 's' : '' ?> · <?= (int) ($featured['published_photos'] ?? 0) ?> photos</p>
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
<section class="section" id="albums">
  <header class="section-head">
    <div>
      <p class="eyebrow">Nouveautés</p>
      <h2 class="section-title section-title--big">Derniers albums</h2>
    </div>
    <a class="link-arrow" href="/photos">Toutes les photos</a>
  </header>
  <div class="card-grid card-grid--home">
    <?php foreach ($latest as $album): ?>
      <?= $view->partial('partials/album-card', ['album' => $album, 'variant' => 'overlay']) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($themes): ?>
<section class="section" id="galeries">
  <header class="section-head">
    <div>
      <p class="eyebrow">Explorer</p>
      <h2 class="section-title section-title--big">Galeries</h2>
    </div>
    <a class="link-arrow" href="/carte">Voir la carte</a>
  </header>
  <div class="theme-grid">
    <?php foreach ($themes as $theme): ?>
      <a class="theme-card" href="/<?= e($theme['slug']) ?>">
        <?php if (!empty($theme['cover_key'])): ?>
          <?= $view->partial('partials/picture', ['key' => $theme['cover_key'], 'alt' => '', 'sizes' => '(min-width: 900px) 45vw, 100vw']) ?>
        <?php endif; ?>
        <span class="theme-card__over">
          <span class="theme-card__title"><?= e($theme['title']) ?></span>
          <span class="theme-card__count"><?= (int) $theme['published_albums'] ?> album<?= (int) $theme['published_albums'] > 1 ? 's' : '' ?> · <?= (int) $theme['published_photos'] ?> photos</span>
        </span>
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
