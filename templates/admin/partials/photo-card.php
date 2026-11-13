<?php
/** @var array $photo @var array $album @var list<array> $albums @var int $heroId */
$id = (int) $photo['id'];
$isCover = (int) ($album['cover_photo_id'] ?? 0) === $id;
$isHero = (int) ($heroId ?? 0) === $id;
$otherAlbums = array_filter($albums, static fn (array $a): bool => (int) $a['id'] !== (int) $photo['album_id']);
?>
<article class="photo-card" data-id="<?= $id ?>" id="photo-<?= $id ?>">
  <div class="photo-card__img">
    <img src="<?= e(media_url($photo['file_key'], 480)) ?>" alt="" loading="lazy">
    <span class="drag-handle" title="Déplacer">⠿</span>
    <span class="photo-card__badges">
      <?php if ($isCover): ?><span class="badge">Couverture</span><?php endif; ?>
      <?php if ($isHero): ?><span class="badge badge--featured">Accueil</span><?php endif; ?>
      <?php if ($photo['lat'] !== null): ?><span class="badge" title="Photo géolocalisée">GPS</span><?php endif; ?>
    </span>
  </div>
  <form method="post" action="/admin/photos/<?= $id ?>" data-autosave>
    <?= csrf_field() ?>
    <input type="text" name="title" maxlength="190" value="<?= e($photo['title']) ?>" placeholder="Titre" aria-label="Titre">
    <textarea name="caption" placeholder="Légende (facultatif)" aria-label="Légende"><?= e($photo['caption']) ?></textarea>
    <noscript><button class="btn btn--small" type="submit">Enregistrer</button></noscript>
    <span class="save-status" aria-live="polite"></span>
  </form>
  <p class="exif-line"><?= e(implode(' · ', array_filter([
      $photo['camera'], $photo['focal_length'], $photo['aperture'], $photo['shutter'],
      $photo['iso'] ? 'ISO ' . $photo['iso'] : null,
  ]))) ?></p>
  <details>
    <summary>Plus d'options</summary>
    <form method="post" action="/admin/photos/<?= $id ?>/gps">
      <?= csrf_field() ?>
      <label>Latitude <input type="text" name="lat" inputmode="decimal" value="<?= e($photo['lat'] ?? '') ?>"></label>
      <label>Longitude <input type="text" name="lng" inputmode="decimal" value="<?= e($photo['lng'] ?? '') ?>"></label>
      <button class="btn btn--ghost btn--small" type="submit">Enregistrer la position</button>
    </form>
    <?php if ($otherAlbums): ?>
    <form method="post" action="/admin/photos/<?= $id ?>/move">
      <?= csrf_field() ?>
      <select name="album_id" aria-label="Déplacer vers l'album">
        <?php foreach ($otherAlbums as $a): ?>
          <option value="<?= (int) $a['id'] ?>"><?= e($a['theme_title'] . ' › ' . $a['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost btn--small" type="submit">Déplacer</button>
    </form>
    <?php endif; ?>
  </details>
  <div class="actions">
    <form method="post" action="/admin/photos/<?= $id ?>/cover"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit">Couverture album</button></form>
    <form method="post" action="/admin/photos/<?= $id ?>/theme-cover"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit">Couverture thème</button></form>
    <form method="post" action="/admin/photos/<?= $id ?>/hero"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit">Photo d'accueil</button></form>
    <form method="post" action="/admin/photos/<?= $id ?>/delete" data-confirm="Supprimer définitivement cette photo ?"><?= csrf_field() ?><button class="btn btn--danger btn--small" type="submit">Supprimer</button></form>
  </div>
</article>
