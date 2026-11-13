<?php /** @var array $theme @var array $errors */ $isNew = $theme['id'] === null; ?>
<div class="toolbar">
  <h1><?= $isNew ? 'Nouveau thème' : 'Modifier le thème' ?></h1>
  <a href="/admin/themes">← Thèmes</a>
</div>
<form method="post" action="<?= $isNew ? '/admin/themes' : '/admin/themes/' . (int) $theme['id'] ?>" class="form panel">
  <?= csrf_field() ?>
  <label>Titre
    <input type="text" name="title" maxlength="150" required value="<?= e($theme['title']) ?>">
    <?php if (!empty($errors['title'])): ?><span class="error"><?= e($errors['title']) ?></span><?php endif; ?>
  </label>
  <?php if (!$isNew): ?>
  <label>Adresse (slug)
    <input type="text" name="slug" maxlength="90" value="<?= e($theme['slug']) ?>">
    <span class="hint">guillaumelecomte.net/<?= e($theme['slug']) ?>. Laissez vide pour la recalculer depuis le titre.</span>
  </label>
  <?php endif; ?>
  <label>Introduction
    <textarea name="intro" rows="4"><?= e($theme['intro']) ?></textarea>
  </label>
  <label class="checkbox"><input type="checkbox" name="featured" value="1"<?= !empty($theme['featured']) ? ' checked' : '' ?>> Mettre en avant sur l'accueil (section « Depuis le cockpit »)</label>
  <div class="form-actions"><button class="btn" type="submit">Enregistrer</button></div>
</form>
<?php if (!$isNew): ?>
<form method="post" action="/admin/themes/<?= (int) $theme['id'] ?>/delete" data-confirm="Supprimer ce thème ?">
  <?= csrf_field() ?>
  <button class="btn btn--danger btn--small" type="submit">Supprimer le thème</button>
</form>
<?php endif; ?>
