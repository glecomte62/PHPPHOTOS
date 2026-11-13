<?php /** @var list<array> $themes */ ?>
<div class="toolbar">
  <h1>Thèmes</h1>
  <a class="btn" href="/admin/themes/new">Nouveau thème</a>
</div>
<?php if ($themes): ?>
<p class="hint">Glissez les poignées ⠿ pour changer l'ordre d'affichage sur le site.</p>
<ul class="list" data-sortable="/admin/themes/reorder" data-handle=".drag-handle">
  <?php foreach ($themes as $t): ?>
  <li data-id="<?= (int) $t['id'] ?>">
    <span class="drag-handle" title="Déplacer">⠿</span>
    <?php if ($t['cover_key']): ?><img src="<?= e(media_url($t['cover_key'], 480)) ?>" alt=""><?php endif; ?>
    <span class="grow"><a href="/admin/themes/<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a> <small>/<?= e($t['slug']) ?> · <?= (int) $t['total_albums'] ?> album(s)</small></span>
    <?php if ($t['featured']): ?><span class="badge badge--featured">Mis en avant</span><?php endif; ?>
  </li>
  <?php endforeach; ?>
</ul>
<?php else: ?>
<p>Aucun thème. Créez par exemple « Vues du ciel », « Paysages », « Aviation », « Villes &amp; patrimoine ».</p>
<?php endif; ?>
