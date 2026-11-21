<?php
/**
 * @var string $query @var bool $searched @var list<array> $albums @var list<array> $photos
 * @var int $total @var int $page @var int $pages @var \App\Services\PhotoFilter $filter @var array $facets
 */
use App\Services\PhotoFilter;

$active = $filter->values();
$select = static function (string $name, string $label, array $options) use ($active): string {
    $html = '<label class="filter"><span>' . e($label) . '</span><select name="' . e($name) . '"><option value="">Tous</option>';
    foreach ($options as $value => $text) {
        $selected = ($active[$name] ?? null) === (string) $value ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $selected . '>' . e($text) . '</option>';
    }
    return $html . '</select></label>';
};
$counted = static function (array $list, callable $label): array {
    $options = [];
    foreach ($list as $item) {
        $options[(string) $item['value']] = $label($item) . ' (' . $item['n'] . ')';
    }
    return $options;
};
$ranges = static fn (array $ranges): array => array_map(static fn (array $r): string => $r['label'], $ranges);
?>
<header class="page-header">
  <h1 class="page-title">Recherche</h1>
  <p class="page-intro">Un lieu, un sujet, un avion, une ville… Cherchez parmi les albums et les légendes des photos, ou filtrez par appareil, objectif et réglages.</p>
  <form class="search-form search-form--filters" method="get" action="/recherche" role="search" data-search-form>
    <div class="search-form__text">
      <label class="visually-hidden" for="q">Rechercher</label>
      <input id="q" type="search" name="q" value="<?= e($query) ?>" placeholder="Ex. : pont de Brooklyn, Bretagne, Concorde" maxlength="100" autocomplete="off"<?= !$searched ? ' autofocus' : '' ?>>
      <button class="button" type="submit">Chercher</button>
    </div>
    <details class="filters"<?= $active ? ' open' : '' ?>>
      <summary>Filtres<?= $active ? ' (' . count($active) . ')' : '' ?></summary>
      <div class="filters__grid">
        <?= $select('appareil', 'Appareil', $counted($facets['camera'], static fn (array $i): string => PhotoFilter::cameraLabel($i['value']))) ?>
        <?= $select('objectif', 'Objectif', $counted($facets['lens'], static fn (array $i): string => PhotoFilter::lensLabel($i['value']))) ?>
        <?= $select('focale', 'Focale', $ranges(PhotoFilter::FOCALS)) ?>
        <?= $select('ouverture', 'Ouverture', $ranges(PhotoFilter::APERTURES)) ?>
        <?= $select('iso', 'Sensibilité ISO', $ranges(PhotoFilter::ISOS)) ?>
        <?= $select('annee', 'Année', $counted($facets['year'], static fn (array $i): string => (string) $i['value'])) ?>
        <?= $select('theme', 'Thème', $counted($facets['theme'], static fn (array $i): string => $i['label'])) ?>
      </div>
      <noscript><button class="button" type="submit">Appliquer les filtres</button></noscript>
    </details>
  </form>
  <?php if ($active): ?>
  <ul class="filter-chips" aria-label="Filtres actifs">
    <?php foreach ($active as $key => $value): ?>
      <li><a href="<?= e($filter->url($query, 1, without: $key)) ?>" title="Retirer ce filtre"><?= e(PhotoFilter::label($key, $value)) ?> <span aria-hidden="true">×</span></a></li>
    <?php endforeach; ?>
    <?php if (count($active) > 1): ?>
      <li><a class="filter-chips__clear" href="<?= e($query !== '' ? '/recherche?' . http_build_query(['q' => $query]) : '/recherche') ?>">Tout effacer</a></li>
    <?php endif; ?>
  </ul>
  <?php endif; ?>
  <?= $view->partial('partials/trace') ?>
</header>

<?php if ($searched): ?>
  <?php if (!$albums && !$photos): ?>
  <section class="section">
    <p class="empty">Aucune photo ne correspond<?= $query !== '' ? ' à « ' . e($query) . ' »' : '' ?><?= $active ? ' avec ces filtres' : '' ?>. Essayez un autre mot ou retirez un filtre.</p>
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
    <h2 class="section-title"><?= $total ?> photo<?= $total > 1 ? 's' : '' ?></h2>
    <?php if ($pages > 1): ?><p class="page-meta">Page <?= $page ?> sur <?= $pages ?></p><?php endif; ?>
  </section>
  <section class="mosaic" data-mosaic>
    <?php foreach ($photos as $p): ?>
      <?php
        $label = photo_label((string) $p['title'], $p['album_title'] . ' — photo');
        $exif = implode(' · ', array_filter([
            $p['camera'] ? PhotoFilter::cameraLabel((string) $p['camera']) : null,
            $p['lens'] ? PhotoFilter::lensLabel((string) $p['lens']) : null,
            $p['focal_length'], $p['aperture'], $p['iso'] ? 'ISO ' . $p['iso'] : null,
        ]));
      ?>
      <a class="mosaic__item" href="<?= e('/' . $p['theme_slug'] . '/' . $p['album_slug'] . '/photo/' . $p['id']) ?>"
         data-ratio="<?= e(round((int) $p['width'] / max(1, (int) $p['height']), 4)) ?>" title="<?= e($label . ' · ' . $p['album_title'] . ($exif !== '' ? "\n" . $exif : '')) ?>">
        <?= $view->partial('partials/picture', [
            'key' => $p['file_key'], 'alt' => $label, 'width' => $p['width'], 'height' => $p['height'],
            'sizes' => '(min-width: 900px) 33vw, 50vw',
        ]) ?>
      </a>
    <?php endforeach; ?>
  </section>
  <?= $view->partial('partials/pager', ['page' => $page, 'pages' => $pages, 'href' => static fn (int $n): string => $filter->url($query, $n)]) ?>
  <?php endif; ?>
<?php endif; ?>
