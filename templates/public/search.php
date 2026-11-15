<?php /** @var string $query @var bool $searched @var list<array> $albums @var list<array> $photos @var int $photoLimit */ ?>
<header class="page-header">
  <h1 class="page-title">Recherche</h1>
  <p class="page-intro">Un lieu, un sujet, un avion, une ville… Cherchez parmi les albums et les légendes des photos.</p>
  <form class="search-form" method="get" action="/recherche" role="search">
    <label class="visually-hidden" for="q">Rechercher</label>
    <input id="q" type="search" name="q" value="<?= e($query) ?>" placeholder="Ex. : pont de Brooklyn, Bretagne, Concorde" maxlength="100" autocomplete="off"<?= $query === '' ? ' autofocus' : '' ?>>
    <button class="button" type="submit">Chercher</button>
  </form>
  <?= $view->partial('partials/trace') ?>
</header>

<?php if ($searched): ?>
  <?php if (!$albums && !$photos): ?>
  <section class="section">
    <p class="empty">Aucun résultat pour « <?= e($query) ?> ». Essayez un autre mot ou une orthographe plus simple.</p>
  </section>
  <?php endif; ?>

  <?php if ($albums): ?>
  <section class="section">
    <h2 class="section-title"><?= count($albums) ?> album<?= count($albums) > 1 ? 's' : '' ?></h2>
    <div class="card-grid">
      <?php foreach ($albums as $album): ?>
        <?= $view->partial('partials/album-card', ['album' => $album]) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($photos): ?>
  <section class="section">
    <h2 class="section-title">
      <?= count($photos) >= $photoLimit ? 'Les ' . $photoLimit . ' premières photos' : count($photos) . ' photo' . (count($photos) > 1 ? 's' : '') ?>
    </h2>
  </section>
  <section class="mosaic" data-mosaic>
    <?php foreach ($photos as $i => $p): ?>
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
  <?php endif; ?>
<?php endif; ?>
