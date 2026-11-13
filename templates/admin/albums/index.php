<?php /** @var list<array{theme: array, albums: list<array>}> $groups */ ?>
<div class="toolbar">
  <h1>Albums</h1>
  <a class="btn" href="/admin/albums/new">Nouvel album</a>
</div>
<?php if (!$groups): ?><p>Créez d'abord <a href="/admin/themes/new">un thème</a>.</p><?php endif; ?>
<?php foreach ($groups as $group): ?>
  <h2><?= e($group['theme']['title']) ?> <a class="btn btn--ghost btn--small" href="/admin/albums/new?theme=<?= (int) $group['theme']['id'] ?>">+ album</a></h2>
  <?php if ($group['albums']): ?>
  <ul class="list" data-sortable="/admin/albums/reorder" data-handle=".drag-handle">
    <?php foreach ($group['albums'] as $a): ?>
    <li data-id="<?= (int) $a['id'] ?>">
      <span class="drag-handle" title="Déplacer">⠿</span>
      <?php if ($a['cover_key']): ?><img src="<?= e(media_url($a['cover_key'], 480)) ?>" alt=""><?php endif; ?>
      <span class="grow"><a href="/admin/albums/<?= (int) $a['id'] ?>/photos"><?= e($a['title']) ?></a> <small><?= (int) $a['photo_count'] ?> photo(s)</small></span>
      <span class="badge<?= $a['status'] === 'draft' ? ' badge--draft' : '' ?>"><?= $a['status'] === 'draft' ? 'Brouillon' : 'Publié' ?></span>
      <a class="btn btn--ghost btn--small" href="/admin/albums/<?= (int) $a['id'] ?>">Infos</a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="hint">Aucun album dans ce thème.</p>
  <?php endif; ?>
<?php endforeach; ?>
