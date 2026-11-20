<?php /** @var bool $sent @var array $errors @var array $old @var string $ts @var ?array $photo @var ?string $photoPath */ ?>
<header class="page-header">
  <h1 class="page-title">Contact</h1>
  <p class="page-intro">Une question sur une photo, une envie d'échanger sur l'aviation ou la photographie ? Écrivez-moi.</p>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="section narrow">
<?php if ($sent): ?>
  <p class="notice">Merci, votre message est bien parti. Je vous réponds dès que possible.</p>
<?php else: ?>
  <?php if (!empty($errors['global'])): ?><p class="notice notice--error"><?= e($errors['global']) ?></p><?php endif; ?>
  <?php if ($photo): ?>
    <a class="photo-ref" href="<?= e($photoPath) ?>">
      <img src="<?= e(media_url($photo['file_key'], 480, 'jpg')) ?>" alt="" width="120" loading="lazy">
      <p>Votre message concerne la photo<br><strong><?= e(photo_label($photo['title'], $photo['album_title'])) ?></strong>
        <small><?= e($photo['album_title']) ?><?= $photo['original_name'] ? ' · ' . e($photo['original_name']) : '' ?></small></p>
    </a>
  <?php endif; ?>
  <form method="post" action="/contact" class="form" novalidate>
    <input type="hidden" name="ts" value="<?= e($ts) ?>">
    <?php if ($photo): ?><input type="hidden" name="photo" value="<?= (int) $photo['id'] ?>"><?php endif; ?>
    <div class="hp" aria-hidden="true">
      <label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <label class="field">Nom
      <input type="text" name="name" maxlength="100" required autocomplete="name" value="<?= e($old['name'] ?? '') ?>">
      <?php if (!empty($errors['name'])): ?><span class="field__error"><?= e($errors['name']) ?></span><?php endif; ?>
    </label>
    <label class="field">E-mail
      <input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
      <?php if (!empty($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
    </label>
    <label class="field">Message
      <textarea name="message" maxlength="5000" required<?= $photo ? ' placeholder="Tirage, usage dans une publication, simple question… dites-moi ce qui vous intéresse."' : '' ?>><?= e($old['message'] ?? '') ?></textarea>
      <?php if (!empty($errors['message'])): ?><span class="field__error"><?= e($errors['message']) ?></span><?php endif; ?>
    </label>
    <button class="button" type="submit">Envoyer</button>
  </form>
<?php endif; ?>
</section>
