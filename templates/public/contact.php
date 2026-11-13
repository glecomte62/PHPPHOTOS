<?php /** @var bool $sent @var array $errors @var array $old @var string $ts */ ?>
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
  <form method="post" action="/contact" class="form" novalidate>
    <input type="hidden" name="ts" value="<?= e($ts) ?>">
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
      <textarea name="message" maxlength="5000" required><?= e($old['message'] ?? '') ?></textarea>
      <?php if (!empty($errors['message'])): ?><span class="field__error"><?= e($errors['message']) ?></span><?php endif; ?>
    </label>
    <button class="button" type="submit">Envoyer</button>
  </form>
<?php endif; ?>
</section>
