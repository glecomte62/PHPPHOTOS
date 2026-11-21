<?php
/** @var list<array> $pending @var list<array> $approved */
$photoUrl = static fn (array $c): string => '/' . $c['theme_slug'] . '/' . $c['album_slug'] . '/photo/' . (int) $c['photo_id'];
$card = static function (array $c, bool $isPending) use ($photoUrl): string {
    $title = photo_label((string) $c['photo_title'], (string) $c['album_title']);
    ob_start(); ?>
  <li class="comment-row">
    <a href="<?= e($photoUrl($c)) ?>" target="_blank" rel="noopener"><img src="<?= e(media_url((string) $c['file_key'], 480)) ?>" alt=""></a>
    <div class="grow">
      <p class="comment-row__meta"><strong><?= e($c['author_name']) ?></strong> · <?= e(format_date_fr((string) $c['created_at'])) ?>
        · <a href="<?= e($photoUrl($c)) ?>" target="_blank" rel="noopener"><?= e($title) ?></a> <small>(<?= e($c['album_title']) ?>)</small></p>
      <p class="comment-row__text"><?= nl2br(e($c['message'])) ?></p>
    </div>
    <div class="actions">
      <?php if ($isPending): ?>
        <form method="post" action="/admin/commentaires/<?= (int) $c['id'] ?>/publier"><?= csrf_field() ?><button class="btn btn--small" type="submit">Publier</button></form>
      <?php endif; ?>
      <form method="post" action="/admin/commentaires/<?= (int) $c['id'] ?>/supprimer" data-confirm="Supprimer ce commentaire ?"><?= csrf_field() ?><button class="btn btn--danger btn--small" type="submit">Supprimer</button></form>
    </div>
  </li>
    <?php return (string) ob_get_clean();
};
?>
<h1>Commentaires</h1>

<h2>À valider <span class="badge"><?= count($pending) ?></span></h2>
<?php if ($pending): ?>
  <ul class="list comment-list"><?php foreach ($pending as $c) { echo $card($c, true); } ?></ul>
<?php else: ?>
  <p class="hint">Aucun commentaire en attente. Vous recevez un e-mail à chaque nouveau commentaire.</p>
<?php endif; ?>

<h2>Publiés récemment</h2>
<?php if ($approved): ?>
  <ul class="list comment-list"><?php foreach ($approved as $c) { echo $card($c, false); } ?></ul>
<?php else: ?>
  <p class="hint">Aucun commentaire publié pour l'instant.</p>
<?php endif; ?>
