<?php /** @var array $album @var list<array> $photos @var list<array> $albums @var int $heroId */ ?>
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

<p class="hint">Glissez ⠿ pour réordonner. Titres et légendes s'enregistrent automatiquement.</p>
<div class="photo-grid" data-photo-grid data-sortable="/admin/albums/<?= (int) $album['id'] ?>/photos/reorder" data-handle=".drag-handle">
  <?php foreach ($photos as $photo): ?>
    <?= $view->partial('admin/partials/photo-card', ['photo' => $photo, 'album' => $album, 'albums' => $albums, 'heroId' => $heroId]) ?>
  <?php endforeach; ?>
</div>
