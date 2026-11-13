<?php /** @var array $counts @var list<array> $albums @var array $limits */ ?>
<div class="toolbar">
  <h1>Tableau de bord</h1>
  <a class="btn" href="/admin/albums/new">Nouvel album</a>
</div>
<div class="stats">
  <div class="stat"><strong><?= (int) $counts['themes'] ?></strong>thèmes</div>
  <div class="stat"><strong><?= (int) $counts['albums'] ?></strong>albums</div>
  <div class="stat"><strong><?= (int) $counts['photos'] ?></strong>photos</div>
</div>
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
