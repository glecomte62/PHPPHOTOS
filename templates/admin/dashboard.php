<?php /** @var array $counts @var list<array> $albums @var array $limits @var array $totals @var int $pendingComments @var list<array> $mostViewed */ ?>
<div class="toolbar">
  <h1>Tableau de bord</h1>
  <a class="btn" href="/admin/albums/new">Nouvel album</a>
</div>
<div class="stats">
  <div class="stat"><strong><?= (int) $counts['themes'] ?></strong>thèmes</div>
  <div class="stat"><strong><?= (int) $counts['albums'] ?></strong>albums</div>
  <div class="stat"><strong><?= (int) $counts['photos'] ?></strong>photos</div>
  <div class="stat"><strong><?= (int) $totals['views'] ?></strong>vues de photos</div>
  <div class="stat"><strong><?= (int) $totals['likes'] ?></strong>« j'aime »</div>
  <a class="stat" href="/admin/commentaires"><strong><?= (int) $pendingComments ?></strong>commentaire<?= $pendingComments > 1 ? 's' : '' ?> à valider</a>
</div>
<?php if ($mostViewed): ?>
<h2>Photos les plus vues</h2>
<ul class="list">
  <?php foreach ($mostViewed as $p): ?>
  <li>
    <img src="<?= e(media_url($p['file_key'], 480)) ?>" alt="">
    <span class="grow"><a href="/<?= e($p['theme_slug']) ?>/<?= e($p['album_slug']) ?>/photo/<?= (int) $p['id'] ?>" target="_blank" rel="noopener"><?= e(photo_label((string) $p['title'], (string) $p['album_title'])) ?></a> <small>— <?= e($p['album_title']) ?></small></span>
    <span class="badge"><?= (int) $p['views'] ?> vues · <?= (int) $p['likes'] ?> ♥</span>
  </li>
  <?php endforeach; ?>
</ul>
<p><a href="/populaires" target="_blank" rel="noopener">Voir la page Populaires ↗</a></p>
<?php endif; ?>
<h2>Albums récemment modifiés</h2>
<?php if ($albums): ?>
<ul class="list">
  <?php foreach ($albums as $a): ?>
  <li>
    <?php if ($a['cover_key']): ?><img src="<?= e(media_url($a['cover_key'], 480)) ?>" alt=""><?php endif; ?>
    <span class="grow"><a href="/admin/albums/<?= (int) $a['id'] ?>/photos"><?= e($a['title']) ?></a> <small>— <?= e($a['theme_title']) ?> · <?= (int) $a['photo_count'] ?> photo(s)</small></span>
    <span class="badge<?= $a['status'] === 'draft' ? ' badge--draft' : '' ?>"><?= $a['status'] === 'draft' ? 'Brouillon' : 'Publié' ?></span>
  </li>
  <?php endforeach; ?>
</ul>
<?php else: ?>
<p>Aucun album pour l'instant. Commencez par <a href="/admin/themes/new">créer un thème</a>.</p>
<?php endif; ?>
<p class="hint">Limites du serveur : fichier <?= e($limits['upload']) ?>, requête <?= e($limits['post']) ?>, mémoire <?= e($limits['memory']) ?>.</p>
