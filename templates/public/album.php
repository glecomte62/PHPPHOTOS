<?php /** @var array $album @var list<array> $photos @var string $baseUrl @var ?int $openIndex @var list<array> $viewerPhotos */ ?>
<header class="page-header">
  <p class="eyebrow"><a href="/<?= e($album['theme_slug']) ?>"><?= e($album['theme_title']) ?></a></p>
  <h1 class="page-title"><?= e($album['title']) ?></h1>
  <?php $meta = implode(' · ', array_filter([(string) $album['location'], format_date_fr($album['shot_on'])])); ?>
  <?php if ($meta !== ''): ?><p class="page-meta"><?= e($meta) ?></p><?php endif; ?>
  <?php if ($album['description']): ?><p class="page-intro"><?= nl2br(e($album['description'])) ?></p><?php endif; ?>
  <?php if ($album['status'] !== 'published'): ?><p class="draft-badge">Brouillon : visible uniquement par vous</p><?php endif; ?>
  <?= $view->partial('partials/trace') ?>
</header>

<section class="mosaic" data-mosaic>
  <?php foreach ($photos as $i => $p): ?>
    <a class="mosaic__item" href="<?= e($baseUrl . '/photo/' . $p['id']) ?>" data-index="<?= (int) $i ?>"
       data-ratio="<?= e(round((int) $p['width'] / max(1, (int) $p['height']), 4)) ?>">
      <?= $view->partial('partials/picture', [
          'key' => $p['file_key'], 'alt' => $p['title'], 'width' => $p['width'], 'height' => $p['height'],
          'sizes' => '(min-width: 900px) 33vw, 50vw',
      ]) ?>
    </a>
  <?php endforeach; ?>
</section>

<script type="application/json" id="viewer-data"><?= json_encode(
    ['base' => $baseUrl, 'open' => $openIndex, 'photos' => $viewerPhotos],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
) ?></script>
