<?php /** @var ?string $error */ ?>
<section class="login panel">
  <h1>Connexion</h1>
  <?php if (!empty($error)): ?><p class="flash flash--error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/admin/login" class="form">
    <?= csrf_field() ?>
    <label>E-mail<input type="email" name="email" required autocomplete="username" autofocus></label>
    <label>Mot de passe<input type="password" name="password" required autocomplete="current-password"></label>
    <div class="form-actions"><button class="btn" type="submit">Se connecter</button></div>
  </form>
</section>
