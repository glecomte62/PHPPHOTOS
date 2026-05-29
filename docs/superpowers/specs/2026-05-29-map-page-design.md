# Page Carte Interactive — Design

Date : 2026-05-29

## Objectif

Ajouter une page `/map.php` accessible depuis un menu de navigation présent sur toutes les pages. La carte affiche toutes les photos géolocalisées du compte Flickr sous forme de marqueurs clusterisés. Un clic sur un marqueur ouvre une popup avec miniature, titre et lien vers la photo sur Flickr.

---

## Périmètre

**Nouveaux fichiers :**
- `map.php` — page HTML carte
- `map-data.php` — endpoint JSON (données géo + cache)

**Fichiers modifiés :**
- `lib/FlickrAPI.php` — nouvelle méthode `getGeoPhotos(int $page): array`
- `css/style.css` — styles menu nav + popup Leaflet
- `index.php`, `collection.php`, `gallery.php` — ajout du menu

---

## 1. FlickrAPI::getGeoPhotos()

### Signature
```php
public function getGeoPhotos(int $page = 1): array
```

### Appel Flickr
`flickr.photos.search` avec :
- `user_id` = `$this->userId`
- `has_geo` = 1
- `extras` = `geo,url_z,url_m`
- `per_page` = 250
- `page` = `$page`

### Retour
```php
[
  'photos' => [
    ['id' => '...', 'title' => '...', 'lat' => 48.85, 'lng' => 2.35, 'url_thumb' => '...'],
    ...
  ],
  'pages' => 4,   // nombre total de pages
]
```

- `url_thumb` = `url_z` si disponible, sinon `url_m`
- `lat`/`lng` castés en float
- Photos sans coordonnées valides ignorées silencieusement
- Erreur API → `RuntimeException`

---

## 2. map-data.php

### Rôle
Endpoint JSON appelé par `map.php` en AJAX. Agrège toutes les pages de `getGeoPhotos()`, met en cache le résultat.

### Cache
- Fichier : `sys_get_temp_dir() . '/phpphotos_map_' . md5(FLICKR_USER_ID) . '.json'`
- TTL : 3600 secondes (`filemtime` + 3600 > `time()`)
- Si cache valide → `readfile()` direct, header JSON
- Sinon → pagine, merge, `file_put_contents`, retourne JSON

### Réponse
```json
[
  {"id":"52980002248","title":"Rafale","lat":48.85,"lng":2.35,"url_thumb":"https://..."},
  ...
]
```

Header : `Content-Type: application/json; charset=utf-8`
Erreur : HTTP 500 + `{"error": "message"}`

### Sécurité
- Seul `FLICKR_USER_ID` influence le nom de cache (md5 pour éviter path traversal)
- Pas d'input utilisateur

---

## 3. map.php

### Structure HTML
```
<header class="site-header"> ... menu ... </header>
<div id="map" class="map-fullscreen"></div>
```

### Dépendances (CDN)
- Leaflet 1.9.4 CSS + JS (déjà utilisé dans gallery.php)
- Leaflet.markercluster 1.5.3 CSS + JS

### Comportement JS
1. `fetch('map-data.php')` au chargement
2. Spinner pendant le chargement (`<div class="map-loading">`)
3. `L.map('map', { zoomControl: true })` centré sur `[20, 0]` zoom 2
4. Tuiles OpenStreetMap (dark) : `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png`
5. `L.markerClusterGroup()` — tous les marqueurs ajoutés dedans
6. Marqueur custom : cercle doré `var(--accent)` (#c8a96e), 10px, bordure blanche 2px — via `L.circleMarker`
7. Popup au clic :
   ```html
   <div class="map-popup">
     <img src="{url_thumb}" alt="{title}">
     <p class="map-popup-title">{title}</p>
     <a href="https://www.flickr.com/photos/{FLICKR_USER_ID}/{id}" target="_blank" rel="noopener">
       Voir sur Flickr →
     </a>
   </div>
   ```
8. Erreur fetch → message d'erreur centré sur la carte

### `FLICKR_USER_ID` côté JS
Injecté dans le HTML via PHP : `<div id="map" data-user="<?= FLICKR_USER_ID ?>">` — le JS le lit via `dataset.user`.

---

## 4. Menu de navigation

### HTML (dans chaque header)
```html
<nav class="site-nav">
  <a class="site-nav-link" href="map.php">Carte</a>
</nav>
```

Placé à droite dans le `.site-header` (flex, `margin-left: auto`).

### CSS
```css
.site-nav { margin-left: auto; display: flex; gap: 1.5rem; align-items: center; }
.site-nav-link { font-size: 0.78rem; letter-spacing: 0.1em; text-transform: uppercase;
                 color: var(--text-muted); transition: color 0.2s; }
.site-nav-link:hover, .site-nav-link.active { color: var(--accent); }
```

Lien "Carte" actif (`.active`) sur `map.php`.

### Pages concernées
- `index.php` — nav ajoutée dans le `.hero-identity` non, dans le header. Mais index.php n'a plus de header (supprimé pour le hero). **Solution** : remettre un header minimal transparent par-dessus le hero avec uniquement le nav à droite.
- `collection.php` — nav après `.site-title-section`
- `gallery.php` — nav après `.site-subtitle`

---

## 5. CSS

### `.map-fullscreen`
```css
.map-fullscreen {
    height: calc(100vh - 65px);  /* 65px = hauteur header */
    width: 100%;
}
```

### `.map-loading`
```css
.map-loading {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-muted); font-size: 0.85rem; letter-spacing: 0.1em;
    text-transform: uppercase;
    z-index: 500; background: var(--bg);
}
```

### Popup Leaflet
```css
.leaflet-popup-content-wrapper {
    background: var(--surface); color: var(--text);
    border: 1px solid var(--border); border-radius: 2px; box-shadow: none;
}
.leaflet-popup-tip { background: var(--surface); }
.map-popup img { width: 200px; height: 133px; object-fit: cover; display: block; }
.map-popup-title { font-size: 0.8rem; padding: 0.5rem 0 0.3rem; }
.map-popup a { font-size: 0.75rem; color: var(--accent); }
```

---

## Contraintes

- Pas de base de données — cache fichier `/tmp`
- Pas de JS supplémentaire hors Leaflet + markercluster
- Compatible O2switch (PHP 8, `sys_get_temp_dir()` accessible en écriture)
- `FLICKR_USER_ID` ne doit pas apparaître en clair dans l'URL publique du cache
