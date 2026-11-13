<footer class="site-footer">
  <?= $view->partial('partials/trace') ?>
  <img class="site-footer__logo" src="<?= e(asset('img/logo.png')) ?>" width="1400" height="384" alt="">
  <nav aria-label="Liens de pied de page">
    <a href="/presentation">Présentation</a>
    <a href="/carte">Carte</a>
    <a href="/contact">Contact</a>
  </nav>
  <p>© <?= date('Y') ?> Guillaume Lecomte · Photographe &amp; pilote ULM</p>
</footer>
