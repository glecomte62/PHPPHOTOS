<?php
/**
 * @var list<array> $photos @var int $total @var int $page @var int $pages
 * @var ?int $year @var ?int $month @var list<array{year: int, month: int, n: int}> $archive
 */
$years = [];
foreach ($archive as $row) {
    $years[$row['year']] = ($years[$row['year']] ?? 0) + $row['n'];
}
$months = array_values(array_filter($archive, static fn (array $r): bool => $r['year'] === $year));
$base = '/photos' . ($year !== null ? '/' . $year . ($month !== null ? '/' . sprintf('%02d', $month) : '') : '');
$heading = $year === null ? 'Toutes les photos' : ($month !== null ? ucfirst(MONTHS_FR[$month - 1]) . ' ' . $year : 'Photos de ' . $year);
?>
<header class="page-header">
  <?php if ($year !== null): ?><p class="eyebrow"><a href="/photos">Toutes les photos</a></p><?php endif; ?>
  <h1 class="page-title"><?= e($heading) ?></h1>
  <p class="page-meta"><?= $total ?> photo<?= $total > 1 ? 's' : '' ?>, de la plus récente à la plus ancienne</p>
  <nav class="archive" aria-label="Archives par année">
    <a href="/photos"<?= $year === null ? ' aria-current="page"' : '' ?>>Toutes</a>
    <?php foreach ($years as $y => $n): ?>
      <a href="/photos/<?= $y ?>"<?= $y === $year && $month === null ? ' aria-current="page"' : '' ?> title="<?= $n ?> photo<?= $n > 1 ? 's' : '' ?>"><?= $y ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($months): ?>
  <nav class="archive archive--months" aria-label="Mois de <?= $year ?>">
    <?php foreach (array_reverse($months) as $m): ?>
      <a href="/photos/<?= $year ?>/<?= sprintf('%02d', $m['month']) ?>"<?= $m['month'] === $month ? ' aria-current="page"' : '' ?>><?= e(MONTHS_FR[$m['month'] - 1]) ?> <small><?= $m['n'] ?></small></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <?= $view->partial('partials/trace') ?>
</header>

<?php if ($photos): ?>
<section class="section"><?php if ($pages > 1): ?><p class="page-meta">Page <?= $page ?> sur <?= $pages ?></p><?php endif; ?></section>
<section class="mosaic" data-mosaic>
  <?php foreach ($photos as $p): ?>
    <?php $label = photo_label((string) $p['title'], $p['album_title'] . ' — photo'); ?>
    <a class="mosaic__item" href="<?= e('/' . $p['theme_slug'] . '/' . $p['album_slug'] . '/photo/' . $p['id']) ?>"
       data-ratio="<?= e(round((int) $p['width'] / max(1, (int) $p['height']), 4)) ?>"
       title="<?= e($label . ' · ' . $p['album_title'] . ($p['taken_at'] ? ' · ' . format_date_fr((string) $p['taken_at']) : '')) ?>">
      <?= $view->partial('partials/picture', [
          'key' => $p['file_key'], 'alt' => $label, 'width' => $p['width'], 'height' => $p['height'],
          'sizes' => '(min-width: 900px) 33vw, 50vw',
      ]) ?>
    </a>
  <?php endforeach; ?>
</section>
<?= $view->partial('partials/pager', ['page' => $page, 'pages' => $pages, 'href' => static fn (int $n): string => $base . ($n > 1 ? '?page=' . $n : '')]) ?>
<?php else: ?>
<section class="section"><p class="empty">Aucune photo pour cette période.</p></section>
<?php endif; ?>
