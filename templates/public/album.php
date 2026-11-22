<?php /** @var array $album @var list<array> $photos @var string $baseUrl @var ?int $openIndex @var list<array> $viewerPhotos */ ?>
<?php $cover = $album['cover_key'] ? (string) $album['cover_key'] : null; ?>
<header class="<?= $cover ? 'album-hero' : 'page-header' ?>">
  <?php if ($cover): ?>
    <?= $view->partial('partials/picture', ['key' => $cover, 'alt' => '', 'sizes' => '100vw', 'loading' => 'eager', 'class' => 'album-hero__img']) ?>
  <?php endif; ?>
  <div class="<?= $cover ? 'album-hero__text' : '' ?>">
    <p class="eyebrow"><a href="/<?= e($album['theme_slug']) ?>"><?= e($album['theme_title']) ?></a></p>
    <h1 class="page-title"><?= e($album['title']) ?></h1>
    <?php $meta = implode(' · ', array_filter([(string) $album['location'], format_date_fr($album['shot_on']), count($photos) . ' photo' . (count($photos) > 1 ? 's' : '')])); ?>
    <?php if ($meta !== ''): ?><p class="page-meta"><?= e($meta) ?></p><?php endif; ?>
    <?php if ($album['description']): ?><p class="page-intro"><?= nl2br(e($album['description'])) ?></p><?php endif; ?>
    <?php if (count($photos) > 1): ?>
      <p class="album-actions"><button class="slideshow-start" type="button" data-slideshow hidden>
        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M7 4.5v15l12-7.5z" fill="currentColor"/></svg> Diaporama
      </button></p>
    <?php endif; ?>
    <?php if ($album['status'] !== 'published'): ?><p class="draft-badge">Brouillon : visible uniquement par vous</p><?php endif; ?>
    <?php if (!$cover): ?><?= $view->partial('partials/trace') ?><?php endif; ?>
  </div>
</header>
<?php if ($cover): ?><div class="album-hero__trace"><?= $view->partial('partials/trace') ?></div><?php endif; ?>

<section class="mosaic" data-mosaic>
  <?php foreach ($photos as $i => $p): ?>
    <a class="mosaic__item" href="<?= e($baseUrl . '/photo/' . $p['id']) ?>" data-index="<?= (int) $i ?>"
       data-ratio="<?= e(round((int) $p['width'] / max(1, (int) $p['height']), 4)) ?>">
      <?= $view->partial('partials/picture', [
          'key' => $p['file_key'], 'alt' => photo_label($p['title'], $album['title'] . ' — photo ' . ($i + 1)), 'width' => $p['width'], 'height' => $p['height'],
          'sizes' => '(min-width: 900px) 33vw, 50vw',
      ]) ?>
    </a>
  <?php endforeach; ?>
</section>

<script type="application/json" id="viewer-data"><?= json_encode(
    ['base' => $baseUrl, 'open' => $openIndex, 'photos' => $viewerPhotos, 'ts' => $commentToken,
     'path' => ['theme' => $album['theme_title'], 'themeUrl' => '/' . $album['theme_slug'], 'album' => $album['title']]],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
) ?></script>
