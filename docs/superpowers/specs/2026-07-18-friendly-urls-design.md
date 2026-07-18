# Design : URLs user-friendly

**Date :** 2026-07-18

## Objectif

Remplacer les URLs paramétriques (`gallery.php?id=72177720334730934&col=196254507-72157725150442283`) par des URLs lisibles basées sur les titres Flickr (`/belgique/liege`).

## Structure d'URL

| Avant | Après |
|---|---|
| `collection.php?id=196254507-…` | `/belgique` |
| `gallery.php?id=72177720…&col=196254507-…` | `/belgique/liege` |

Pas de préfixe. Format : `/{slug-collection}` et `/{slug-collection}/{slug-album}`.

## Architecture

### 1. lib/Slug.php (nouveau fichier)

Classe utilitaire avec une seule méthode statique :

```php
class Slug {
    public static function slugify(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
```

Exemples : `"Belgique"` → `"belgique"`, `"Île-de-France"` → `"ile-de-france"`.

### 2. .htaccess (nouvelles règles)

Ajoutées avant la règle catch-all existante, après la règle sitemap :

```apache
RewriteRule ^([a-z0-9-]+)$ collection.php?slug=$1 [L,QSA]
RewriteRule ^([a-z0-9-]+)/([a-z0-9-]+)$ gallery.php?slug_col=$1&slug=$2 [L,QSA]
```

Les conditions `!-f / !-d` déjà en place protègent les fichiers statiques.

### 3. collection.php — résolution slug

En tête du fichier, si `$_GET['slug']` est présent :

1. Appel `getCollections()` (LiteSpeed cache 5 min)
2. Pour chaque collection, calcul `Slug::slugify($col['title'])`
3. Si match → utilise l'ID pour la suite normale
4. Si non trouvé → `header('Location: /', 301); exit;`

### 4. gallery.php — résolution slug

En tête du fichier, si `$_GET['slug']` est présent :

1. Appel `getCollections()`
2. Trouve la collection via `slug_col`
3. Trouve le set via `slug`
4. Si trouvé → injecte `$_GET['id']` et `$_GET['col']` et continue normalement
5. Si non trouvé → `header('Location: /', 301); exit;`

### 5. Liens générés (index.php, collection.php, gallery.php)

Tous les `href` vers `collection.php?id=…` et `gallery.php?id=…&col=…` sont remplacés par les URLs friendly. La logique de génération :

```php
// collection
'/' . Slug::slugify($col['title'])

// album
'/' . Slug::slugify($collectionSlug) . '/' . Slug::slugify($set['title'])
```

### 6. Balises canoniques

Les `<link rel="canonical">` dans `collection.php` et `gallery.php` sont mis à jour pour pointer vers les nouvelles URLs.

## Gestion des collisions de slugs

Si deux collections ou albums ont le même titre slugifié, le premier match dans le tableau retourné par `getCollections()` est utilisé. Ce cas est peu probable dans ce projet (albums Flickr personnels avec titres distincts).

## Fichiers modifiés

- `.htaccess` — 2 nouvelles règles RewriteRule
- `lib/Slug.php` — nouveau fichier
- `collection.php` — résolution slug en tête + liens générés + canonical
- `gallery.php` — résolution slug en tête + liens générés + canonical
- `index.php` — liens vers collections mis à jour

## Fichiers non modifiés

- `lib/FlickrAPI.php` — aucun changement
- `map.php`, `guestbook.php`, `comment_submit.php` — non concernés
