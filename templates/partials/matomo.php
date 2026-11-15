<?php
// Mesure d'audience Matomo (stats.moncarnetulm.fr, site n° 3) : production uniquement,
// et pas pour l'administrateur connecté, afin de ne pas compter ses propres visites.
if (\App\Services\Env::get('APP_ENV') !== 'prod' || \App\Services\Auth::check()) {
    return;
}
?>
<!-- Matomo -->
<script>
  var _paq = window._paq = window._paq || [];
  _paq.push(["setDoNotTrack", true]);
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//stats.moncarnetulm.fr/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '3']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
<noscript><p><img referrerpolicy="no-referrer-when-downgrade" src="//stats.moncarnetulm.fr/matomo.php?idsite=3&amp;rec=1" style="border:0;" alt=""></p></noscript>
<!-- End Matomo Code -->
