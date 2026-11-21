<?php /** @var array $album @var list<array> $photos @var list<array> $albums @var int $heroId @var array<int, list<string>> $photoTags @var list<string> $albumTags */ ?>
<div class="toolbar">
  <h1><?= e($album['title']) ?> <span class="badge<?= $album['status'] === 'draft' ? ' badge--draft' : '' ?>"><?= $album['status'] === 'draft' ? 'Brouillon' : 'Publié' ?></span></h1>
  <div class="form-actions">
    <a class="btn btn--ghost" href="/admin/albums/<?= (int) $album['id'] ?>">Infos de l'album</a>
    <a class="btn btn--ghost" href="/<?= e($album['theme_slug']) ?>/<?= e($album['slug']) ?>" target="_blank" rel="noopener">Voir ↗</a>
  </div>
</div>

<label class="dropzone" data-upload="/admin/albums/<?= (int) $album['id'] ?>/photos">
  <input type="file" accept="image/jpeg" multiple>
  <strong>Glissez vos photos ici</strong><br>
  <span class="hint">ou cliquez pour les choisir · JPEG uniquement, 48 Mo maximum par fichier</span>
</label>
<ul class="upload-queue" aria-live="polite"></ul>

<?php if ($photos): ?>
<section class="panel tag-panel">
  <h2>Mots-clés de l'album</h2>
  <?php if ($albumTags): ?>
    <p class="hint">Présents sur toutes les photos (et ajoutés automatiquement aux prochaines) :</p>
    <ul class="tag-list">
      <?php foreach ($albumTags as $tag): ?>
        <li>
          <form method="post" action="/admin/albums/<?= (int) $album['id'] ?>/tags/remove" data-confirm="Retirer « <?= e($tag) ?> » de toutes les photos de l'album ?">
            <?= csrf_field() ?><input type="hidden" name="tag" value="<?= e($tag) ?>">
            <?= e($tag) ?> <button type="submit" title="Retirer de toutes les photos" aria-label="Retirer <?= e($tag) ?>">×</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <form method="post" action="/admin/albums/<?= (int) $album['id'] ?>/tags" class="inline-form">
    <?= csrf_field() ?>
    <input type="text" name="tags" maxlength="600" placeholder="Ex. : Belgique, street art, abandonné" aria-label="Mots-clés à ajouter à toutes les photos">
    <button class="btn btn--small" type="submit">Ajouter à toutes les photos</button>
  </form>
</section>
<?php endif; ?>

<p class="hint">Glissez ⠿ pour réordonner. Titres, légendes et mots-clés s'enregistrent automatiquement.</p>
<div class="photo-grid" data-photo-grid data-sortable="/admin/albums/<?= (int) $album['id'] ?>/photos/reorder" data-handle=".drag-handle">
  <?php foreach ($photos as $photo): ?>
    <?= $view->partial('admin/partials/photo-card', ['photo' => $photo, 'album' => $album, 'albums' => $albums, 'heroId' => $heroId, 'tags' => $photoTags[(int) $photo['id']] ?? []]) ?>
  <?php endforeach; ?>
</div>
