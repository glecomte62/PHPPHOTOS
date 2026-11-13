<?php /** @var array $album @var list<array> $themes @var array $errors */ $isNew = $album['id'] === null; ?>
<div class="toolbar">
  <h1><?= $isNew ? 'Nouvel album' : 'Infos de l\'album' ?></h1>
  <a href="/admin/albums">← Albums</a>
</div>
<form method="post" action="<?= $isNew ? '/admin/albums' : '/admin/albums/' . (int) $album['id'] ?>" class="form panel">
  <?= csrf_field() ?>
  <label>Thème
    <select name="theme_id" required>
      <?php foreach ($themes as $t): ?>
        <option value="<?= (int) $t['id'] ?>"<?= (int) $album['theme_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!empty($errors['theme_id'])): ?><span class="error"><?= e($errors['theme_id']) ?></span><?php endif; ?>
  </label>
  <label>Titre
    <input type="text" name="title" maxlength="150" required value="<?= e($album['title']) ?>">
    <?php if (!empty($errors['title'])): ?><span class="error"><?= e($errors['title']) ?></span><?php endif; ?>
  </label>
  <?php if (!$isNew): ?>
  <label>Adresse (slug)
    <input type="text" name="slug" maxlength="90" value="<?= e($album['slug']) ?>">
    <span class="hint">Laissez vide pour la recalculer depuis le titre.</span>
  </label>
  <?php endif; ?>
  <label>Date de prise de vue
    <input type="date" name="shot_on" value="<?= e($album['shot_on']) ?>">
    <?php if (!empty($errors['shot_on'])): ?><span class="error"><?= e($errors['shot_on']) ?></span><?php endif; ?>
  </label>
  <label>Lieu
    <input type="text" name="location" maxlength="150" value="<?= e($album['location']) ?>">
    <?php if (!empty($errors['location'])): ?><span class="error"><?= e($errors['location']) ?></span><?php endif; ?>
  </label>
  <label>Texte de présentation
    <textarea name="description" rows="5"><?= e($album['description']) ?></textarea>
  </label>
  <label>Statut
    <select name="status">
      <option value="draft"<?= $album['status'] !== 'published' ? ' selected' : '' ?>>Brouillon (invisible pour les visiteurs)</option>
      <option value="published"<?= $album['status'] === 'published' ? ' selected' : '' ?>>Publié</option>
    </select>
  </label>
  <div class="form-actions">
    <button class="btn" type="submit"><?= $isNew ? 'Créer et ajouter des photos' : 'Enregistrer' ?></button>
    <?php if (!$isNew): ?><a href="/admin/albums/<?= (int) $album['id'] ?>/photos">Gérer les photos</a><?php endif; ?>
  </div>
</form>
<?php if (!$isNew): ?>
<form method="post" action="/admin/albums/<?= (int) $album['id'] ?>/delete" data-confirm="Supprimer cet album et toutes ses photos ? Cette action est définitive.">
  <?= csrf_field() ?>
  <button class="btn btn--danger btn--small" type="submit">Supprimer l'album</button>
</form>
<?php endif; ?>
