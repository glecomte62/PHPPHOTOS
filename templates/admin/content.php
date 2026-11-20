<?php
/** @var string $tagline @var string $skyIntro @var string $reelPlaces @var int $reelNight @var ?array $hero @var array $page */
use App\Services\PresentationImages;
?>
<h1>Contenus</h1>
<form method="post" action="/admin/content" enctype="multipart/form-data" class="form">
  <?= csrf_field() ?>
  <section class="panel form">
    <h2>Accueil</h2>
    <label>Accroche sous la photo d'ouverture
      <input type="text" name="site_tagline" maxlength="150" value="<?= e($tagline) ?>">
    </label>
    <label>Texte de la section mise en avant (« Depuis le cockpit »)
      <textarea name="sky_intro" rows="4"><?= e($skyIntro) ?></textarea>
    </label>
    <div>
      <strong>Photo d'ouverture</strong>
      <?php if ($hero): ?>
        <img class="preview" src="<?= e(media_url($hero['file_key'], 480)) ?>" alt="">
        <span class="hint">Pour la changer : bouton « Photo d'accueil » sur une photo, dans un album.</span>
      <?php else: ?>
        <p class="hint">Aucune. Choisissez-la avec le bouton « Photo d'accueil » sur une photo, dans un album.</p>
      <?php endif; ?>
    </div>
    <label>Lieux toujours présents dans le diaporama d'accueil
      <input type="text" name="hero_reel_places" maxlength="300" value="<?= e($reelPlaces) ?>" placeholder="Arras, Paris">
      <span class="hint">Séparés par des virgules. Une photo d'un album dont le titre ou le lieu contient chaque nom est toujours incluse ; le reste est tiré au hasard.</span>
    </label>
    <label>Photos de nuit dans le diaporama d'accueil
      <input type="number" name="hero_reel_night" min="0" max="8" value="<?= (int) $reelNight ?>">
      <span class="hint">Reconnues à leur pose longue (une demi-seconde ou plus) ou à un mot comme « nuit », « illuminé » ou « heure bleue » dans le titre ou la légende.</span>
    </label>
  </section>

  <section class="panel form">
    <h2>Page Présentation</h2>
    <label>Titre
      <input type="text" name="title" maxlength="190" required value="<?= e($page['title']) ?>">
    </label>
    <label>Accroche
      <input type="text" name="lead" maxlength="300" value="<?= e($page['lead']) ?>">
    </label>
    <label for="body_html">Texte</label>
    <div class="text-toolbar" data-toolbar="body_html">
      <button type="button" data-wrap="<strong>|</strong>"><b>G</b></button>
      <button type="button" data-wrap="<em>|</em>"><i>I</i></button>
      <button type="button" data-wrap="link">Lien</button>
    </div>
    <textarea id="body_html" name="body_html" rows="12"><?= e($page['body_editable']) ?></textarea>
    <span class="hint">Une ligne vide sépare deux paragraphes.</span>
    <label>Encart « Côté pilote »
      <textarea name="pilot_info" rows="4"><?= e($page['pilot_info']) ?></textarea>
      <span class="hint">Une information par ligne, au format « Libellé : valeur » (ex. « ULM : Ikarus C42 »).</span>
    </label>
    <label>Photo bandeau (format paysage)
      <input type="file" name="banner" accept="image/jpeg">
    </label>
    <?php if ($page['banner_image']): ?><img class="preview" src="<?= e(PresentationImages::url($page['banner_image'], 1280)) ?>" alt=""><?php endif; ?>
    <label>Portrait (format vertical)
      <input type="file" name="portrait" accept="image/jpeg">
    </label>
    <?php if ($page['portrait_image']): ?><img class="preview" style="max-width:160px" src="<?= e(PresentationImages::url($page['portrait_image'], 1280)) ?>" alt=""><?php endif; ?>
  </section>

  <div class="form-actions">
    <button class="btn" type="submit">Enregistrer</button>
    <a href="/presentation" target="_blank" rel="noopener">Voir la page ↗</a>
  </div>
</form>
