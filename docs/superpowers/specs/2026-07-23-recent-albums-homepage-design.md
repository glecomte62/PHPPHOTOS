# Derniers albums sur la homepage

**Date :** 2026-07-23
**Statut :** approuvé

## Objectif

Afficher une ligne de 6 cartes "Derniers albums" en haut du contenu de la homepage, avant la grille des collections. Chaque carte pointe vers la galerie de l'album (URL friendly).

## Données

1. `getCollections()` est déjà appelé → on construit `map[set_id => colSlug]` en parcourant les collections.
2. Nouvel appel `$api->getPhotosets(6)` pour récupérer les 6 albums les plus récents (Flickr les retourne triés par date de publication décroissante).
3. Pour chaque album récent, l'URL est construite ainsi :
   - Si `set_id` trouvé dans le map → `/colSlug/albumSlug`
   - Sinon → `gallery.php?id=<set_id>` (fallback)

## Modifications

### `lib/FlickrAPI.php`

Ajouter un paramètre `int $limit = 0` à `getPhotosets()`. Si `$limit > 0`, passer `per_page=$limit` dans la requête Flickr (sinon 500 comme aujourd'hui).

### `index.php`

- Après le chargement des collections, tenter `$recentAlbums = $api->getPhotosets(6)` dans un try/catch (silencieux si erreur, `$recentAlbums = []`).
- Construire `$setToColSlug = [set_id => colSlug]` en parcourant `$collections`.
- Insérer la section HTML avant `.galleries-grid`.

### HTML (dans `index.php`)

```html
<section class="recent-albums">
  <h2 class="recent-albums-title">Derniers albums</h2>
  <div class="recent-albums-grid">
    <!-- foreach $recentAlbums -->
    <a class="gallery-card" href="/colSlug/albumSlug">
      <img class="gallery-card-cover" ...>
      <div class="gallery-card-info">
        <div class="gallery-card-title">...</div>
        <div class="gallery-card-count">N photo(s)</div>
      </div>
    </a>
  </div>
</section>
```

Les cartes réutilisent les classes `.gallery-card`, `.gallery-card-cover`, `.gallery-card-info`, `.gallery-card-title`, `.gallery-card-count` — cohérence visuelle totale avec les collections.

### `css/style.css`

- `.recent-albums` : margin-bottom avant la section collections.
- `.recent-albums-title` : titre de section (style typographique cohérent avec le reste).
- `.recent-albums-grid` : même grille CSS que `.galleries-grid`.

## Gestion d'erreur

Si `getPhotosets(6)` lève une exception, `$recentAlbums = []` et la section est absente de la page — aucun impact sur les collections.
