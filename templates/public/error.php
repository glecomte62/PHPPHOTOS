<?php /** @var int $code @var string $message */ ?>
<section class="error-page">
  <p class="error-page__code"><?= (int) $code ?></p>
  <h1 class="page-title"><?= e($message) ?></h1>
  <p><a class="link-arrow" href="/">Retour à l'accueil</a></p>
</section>
