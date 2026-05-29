# Redesign galerie — layout professionnel + performance

Date : 2026-05-29

## Problèmes à résoudre

1. `collection.php` : appels API Flickr séquentiels → lenteur proportionnelle au nombre d'albums
2. Lightbox : image non maximale, infos EXIF/carte toujours visibles en dessous
3. Grille photos : trop de padding/bordures, pas assez immersive

## Périmètre

Fichiers modifiés : `lib/FlickrAPI.php`, `collection.php`, `css/style.css`, `js/lightbox.js`, `gallery.php` (HTML lightbox uniquement).
Aucun nouveau fichier, pas de changement à `config.php`, `photo-info.php`, `index.php`.

---

## 1. Performance — appels parallèles

### Problème actuel
`collection.php` boucle sur `$collection['sets']` et appelle `$api->getPhotosetInfo($set['id'])` en séquence. Avec 10 albums → 10 × ~300ms = ~3s de latence.

### Solution
Ajouter une méthode `FlickrAPI::getPhotosetsInfo(array $ids): array` qui utilise `curl_multi_exec` pour lancer tous les appels en parallèle. `collection.php` remplace la boucle foreach par un seul appel à cette méthode.

Résultat attendu : temps de chargement ~300ms quelle que soit la taille de la collection.

---

## 2. Grille photos (gallery.php + CSS)

- Masonry CSS `columns: 4` (inchangé techniquement)
- Gap réduit à **4px** (actuellement 0.75rem ~12px)
- Suppression de toute bordure et arrondi sur `.photo-item`
- Fond de page : noir pur `#000`
- Hover : `brightness(1.08)` via `filter`, pas de scale (évite les décalages masonry)
- Aucun texte ni overlay sur les miniatures
- Responsive : 3 colonnes ≤1024px, 2 colonnes ≤768px, 1 colonne ≤480px

---

## 3. Lightbox — image plein écran

- `position: fixed; inset: 0` — 100vw × 100vh, fond `#000`
- Image : `max-width: 100%; max-height: 100vh; object-fit: contain` centrée absolument
- Boutons précédent/suivant : zones transparentes sur 10% gauche/droite, flèches SVG
- Croix fermeture : coin haut-droit, 40×40px
- Bouton **"i"** : coin bas-droit, 36×36px, cercle, fond semi-transparent
- Zoom au clic sur l'image : `scale(2.5)` avec `transform-origin` au point de clic (comportement existant conservé)

---

## 4. Panneau infos latéral

### Structure HTML (dans gallery.php)
```
#lightbox-overlay
  .lightbox-img-wrap        ← image + boutons nav
    img.lightbox-img
    button.lightbox-prev
    button.lightbox-next
  .lightbox-panel           ← panneau droit escamotable
    .lightbox-panel-header  ← titre + date + croix fermeture panneau
    .lightbox-panel-body
      .lightbox-tags
      .lightbox-exif
      #lightbox-map
  button.lightbox-close     ← ferme la lightbox (coin haut-droit)
  button.lightbox-info      ← toggle panneau (coin bas-droit)
```

### Comportement CSS
- `.lightbox-panel` : `position: fixed; right: 0; top: 0; height: 100%; width: 360px; transform: translateX(100%); transition: transform 0.3s ease`
- `.lightbox-panel.open` : `transform: translateX(0)`
- Sur desktop : quand le panneau est ouvert, `.lightbox-img-wrap` a `margin-right: 360px` (transition identique) pour que l'image reste centrée dans l'espace disponible
- Sur mobile (≤600px) : le panneau fait 100vw, superpose l'image

### Comportement JS
- Clic sur `.lightbox-info` → toggle classe `open` sur `.lightbox-panel`
- Le chargement EXIF + carte se déclenche **uniquement** à la première ouverture du panneau pour chaque photo (flag `infoLoaded` par index)
- Navigation (prev/next) : ferme le panneau, réinitialise `infoLoaded` pour la nouvelle photo
- Fermeture lightbox : ferme le panneau, reset complet

### Contenu du panneau
1. Titre de la photo
2. Date (formatée fr-FR)
3. Tags (chips)
4. Table EXIF (labels FR, ordre défini, tags inconnus en fin)
5. Carte Leaflet (si GPS disponible) — hauteur 200px

---

## Contraintes

- Pas de dépendance JS ajoutée (Leaflet déjà présent)
- Pas de bundler — CSS et JS inline dans les fichiers existants
- Compatible Safari/Chrome/Firefox récents
- `curl_multi` disponible sur O2switch (cPanel standard)
