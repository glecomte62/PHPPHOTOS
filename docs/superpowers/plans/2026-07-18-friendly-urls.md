# Friendly URLs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer les URLs paramétriques (`gallery.php?id=…&col=…`) par des URLs lisibles basées sur les titres Flickr (`/belgique/liege`).

**Architecture:** Résolution à la volée — `getCollections()` (déjà en cache LiteSpeed 5 min) est appelée pour reconstruire la map slug→ID à chaque requête. Une classe `Slug` partagée calcule les slugs. Le `.htaccess` route les URLs propres vers les fichiers PHP existants via des paramètres `slug` / `slug_col`.

**Tech Stack:** PHP 8, Apache mod_rewrite / LiteSpeed, API Flickr (déjà en place)

---

## Fichiers modifiés

| Fichier | Action | Responsabilité |
|---|---|---|
| `lib/Slug.php` | Créer | Fonction `slugify()` partagée |
| `.htaccess` | Modifier | Routage des URLs friendly |
| `collection.php` | Modifier | Résolution slug→ID, liens générés, canonical |
| `gallery.php` | Modifier | Résolution slug→ID, liens générés, canonical, breadcrumb, commentReturnUrl |
| `index.php` | Modifier | Liens vers collections |
| `sitemap.php` | Modifier | URLs friendly dans le sitemap XML |

---

## Task 1 : Créer lib/Slug.php

**Files:**
- Create: `lib/Slug.php`

- [ ] **Step 1 : Créer le fichier**

```php
<?php

class Slug
{
    public static function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
```

- [ ] **Step 2 : Vérifier manuellement la transformation**

Créer un fichier temporaire `test-slug.php` à la racine et l'ouvrir dans un navigateur (ou via `php test-slug.php`) :

```php
<?php
require_once __DIR__ . '/lib/Slug.php';
$cases = [
    'Belgique'       => 'belgique',
    'Île-de-France'  => 'ile-de-france',
    'Liège 2024'     => 'liege-2024',
    'Paris & Lyon'   => 'paris-lyon',
];
foreach ($cases as $input => $expected) {
    $result = Slug::slugify($input);
    echo ($result === $expected ? 'OK' : "FAIL: got $result") . " — $input\n";
}
```

Run : `php test-slug.php`
Expected :
```
OK — Belgique
OK — Île-de-France
OK — Liège 2024
OK — Paris & Lyon
```

- [ ] **Step 3 : Supprimer le fichier de test**

```bash
rm test-slug.php
```

- [ ] **Step 4 : Commit**

```bash
git add lib/Slug.php
git commit -m "feat: add Slug helper class"
```

---

## Task 2 : Mettre à jour .htaccess

**Files:**
- Modify: `.htaccess`

- [ ] **Step 1 : Ajouter les deux règles slug après la règle sitemap**

Localiser dans `.htaccess` le bloc :
```apache
# Sitemap dynamique accessible à la racine
RewriteRule ^sitemap\.xml$ sitemap.php [L]
```

Ajouter immédiatement après :
```apache
# URLs friendly : /{slug-collection}
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-z0-9-]+)$ collection.php?slug=$1 [L,QSA]

# URLs friendly : /{slug-collection}/{slug-album}
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-z0-9-]+)/([a-z0-9-]+)$ gallery.php?slug_col=$1&slug=$2 [L,QSA]
```

Le fichier `.htaccess` doit ressembler à ceci dans cette section :

```apache
# Sitemap dynamique accessible à la racine
RewriteRule ^sitemap\.xml$ sitemap.php [L]

# URLs friendly : /{slug-collection}
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-z0-9-]+)$ collection.php?slug=$1 [L,QSA]

# URLs friendly : /{slug-collection}/{slug-album}
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-z0-9-]+)/([a-z0-9-]+)$ gallery.php?slug_col=$1&slug=$2 [L,QSA]

# Rediriger les requêtes vers index.php si le fichier n'existe pas
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?path=$1 [QSA,L]
```

- [ ] **Step 2 : Commit**

```bash
git add .htaccess
git commit -m "feat: add friendly URL rewrite rules"
```

---

## Task 3 : Mettre à jour collection.php

**Files:**
- Modify: `collection.php`

- [ ] **Step 1 : Ajouter le require Slug et la résolution slug en tête du fichier**

Remplacer les 9 premières lignes :
```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$collectionId = $_GET['id'] ?? '';
if (!$collectionId) {
    header('Location: index.php');
    exit;
}
```

Par :
```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';
require_once __DIR__ . '/lib/Slug.php';

// Résolution slug → ID si URL friendly
if (isset($_GET['slug']) && !isset($_GET['id'])) {
    $api = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
    try {
        foreach ($api->getCollections() as $col) {
            if (Slug::slugify($col['title']) === $_GET['slug']) {
                $_GET['id'] = $col['id'];
                break;
            }
        }
    } catch (RuntimeException) {}
    if (!isset($_GET['id'])) {
        header('Location: /', true, 301);
        exit;
    }
}

$collectionId = $_GET['id'] ?? '';
if (!$collectionId) {
    header('Location: index.php');
    exit;
}
```

- [ ] **Step 2 : Calculer $colSlug après résolution de la collection**

Dans le bloc `try` existant (autour de la ligne 17), après la ligne `$collection = $col; break;`, ajouter une variable `$colSlug` calculée juste après le `foreach` des collections (après le `if (!$collection)`) :

Remplacer :
```php
    if (!$collection) {
        throw new RuntimeException('Collection introuvable.');
    }
```

Par :
```php
    if (!$collection) {
        throw new RuntimeException('Collection introuvable.');
    }
    $colSlug = Slug::slugify($collection['title']);
```

Et ajouter `$colSlug = '';` dans les variables d'initialisation en tête (juste après `$photosets  = [];`) :
```php
$colSlug    = '';
```

- [ ] **Step 3 : Mettre à jour la balise canonical et og:url**

Remplacer :
```php
    <link rel="canonical" href="https://guillaumelecomte.net/collection.php?id=<?= htmlspecialchars($_GET['id'] ?? '') ?>">
```
Par :
```php
    <link rel="canonical" href="https://guillaumelecomte.net/<?= htmlspecialchars($colSlug) ?>">
```

Remplacer :
```php
    <meta property="og:url" content="https://guillaumelecomte.net/collection.php?id=<?= htmlspecialchars($_GET['id'] ?? '') ?>">
```
Par :
```php
    <meta property="og:url" content="https://guillaumelecomte.net/<?= htmlspecialchars($colSlug) ?>">
```

- [ ] **Step 4 : Mettre à jour le lien vers chaque album dans la grille**

Remplacer :
```php
        <a class="gallery-card" href="gallery.php?id=<?= htmlspecialchars($set['id']) ?>&col=<?= htmlspecialchars($collectionId) ?>">
```
Par :
```php
        <a class="gallery-card" href="/<?= htmlspecialchars($colSlug) ?>/<?= htmlspecialchars(Slug::slugify($set['title'])) ?>">
```

- [ ] **Step 5 : Commit**

```bash
git add collection.php
git commit -m "feat: friendly URLs in collection.php"
```

---

## Task 4 : Mettre à jour gallery.php

**Files:**
- Modify: `gallery.php`

- [ ] **Step 1 : Ajouter le require Slug et la résolution slug en tête du fichier**

Remplacer les 3 premières lignes :
```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';
```

Par :
```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';
require_once __DIR__ . '/lib/Slug.php';

// Résolution slug → ID si URL friendly
if (isset($_GET['slug'], $_GET['slug_col']) && !isset($_GET['id'])) {
    $api = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
    try {
        foreach ($api->getCollections() as $col) {
            if (Slug::slugify($col['title']) === $_GET['slug_col']) {
                $_GET['col'] = $col['id'];
                foreach ($col['sets'] as $set) {
                    if (Slug::slugify($set['title']) === $_GET['slug']) {
                        $_GET['id'] = $set['id'];
                        break 2;
                    }
                }
            }
        }
    } catch (RuntimeException) {}
    if (!isset($_GET['id'])) {
        header('Location: /', true, 301);
        exit;
    }
}
```

- [ ] **Step 2 : Supprimer la validation id qui bloque les slugs**

La ligne 7 actuelle (après déplacement, quelques lignes plus bas) :
```php
if (empty($photosetId) || !preg_match('/^\d+$/', $photosetId)) {
    header('Location: index.php');
    exit;
}
```

Cette validation reste valide car après la résolution slug, `$_GET['id']` contient l'ID numérique Flickr. Ne pas la modifier.

- [ ] **Step 3 : Calculer $colSlug et $albumSlug après le bloc try**

Le bloc `try` se termine vers la ligne 37 (`}`). Ajouter juste après la fermeture du `try/catch` :

```php
$colSlug   = $collectionTitle ? Slug::slugify($collectionTitle) : '';
$albumSlug = Slug::slugify($galleryTitle);
```

- [ ] **Step 4 : Mettre à jour la balise canonical et og:url**

Localiser dans le `<head>` :
```php
    $canonicalId  = htmlspecialchars($_GET['id'] ?? '');
```

Remplacer par :
```php
    $friendlyUrl = ($colSlug && $albumSlug)
        ? 'https://guillaumelecomte.net/' . htmlspecialchars($colSlug) . '/' . htmlspecialchars($albumSlug)
        : 'https://guillaumelecomte.net/gallery.php?id=' . htmlspecialchars($_GET['id'] ?? '');
```

Remplacer :
```php
    <link rel="canonical" href="https://guillaumelecomte.net/gallery.php?id=<?= $canonicalId ?>">
```
Par :
```php
    <link rel="canonical" href="<?= $friendlyUrl ?>">
```

Remplacer :
```php
    <meta property="og:url" content="https://guillaumelecomte.net/gallery.php?id=<?= $canonicalId ?>">
```
Par :
```php
    <meta property="og:url" content="<?= $friendlyUrl ?>">
```

- [ ] **Step 5 : Mettre à jour le breadcrumb (lien vers la collection)**

Remplacer :
```php
    &nbsp;/&nbsp;<a href="collection.php?id=<?= htmlspecialchars($collectionId) ?>"><?= htmlspecialchars($collectionTitle) ?></a>
```
Par :
```php
    &nbsp;/&nbsp;<a href="/<?= htmlspecialchars($colSlug) ?>"><?= htmlspecialchars($collectionTitle) ?></a>
```

- [ ] **Step 6 : Mettre à jour $commentReturnUrl**

Remplacer :
```php
    $commentReturnUrl   = '/gallery.php?id=' . urlencode($photosetId) . ($collectionId ? '&col=' . urlencode($collectionId) : '');
```
Par :
```php
    $commentReturnUrl   = ($colSlug && $albumSlug)
        ? '/' . $colSlug . '/' . $albumSlug
        : '/gallery.php?id=' . urlencode($photosetId) . ($collectionId ? '&col=' . urlencode($collectionId) : '');
```

- [ ] **Step 7 : Commit**

```bash
git add gallery.php
git commit -m "feat: friendly URLs in gallery.php"
```

---

## Task 5 : Mettre à jour index.php

**Files:**
- Modify: `index.php`

- [ ] **Step 1 : Ajouter le require Slug**

Après la ligne `require_once __DIR__ . '/lib/FlickrAPI.php';`, ajouter :
```php
require_once __DIR__ . '/lib/Slug.php';
```

- [ ] **Step 2 : Mettre à jour le lien vers chaque collection**

Remplacer :
```php
        <a class="gallery-card" href="collection.php?id=<?= htmlspecialchars($col['id']) ?>">
```
Par :
```php
        <a class="gallery-card" href="/<?= htmlspecialchars(Slug::slugify($col['title'])) ?>">
```

- [ ] **Step 3 : Commit**

```bash
git add index.php
git commit -m "feat: friendly URLs in index.php"
```

---

## Task 6 : Mettre à jour sitemap.php

**Files:**
- Modify: `sitemap.php`

- [ ] **Step 1 : Ajouter le require Slug**

Après la ligne `require_once __DIR__ . '/lib/FlickrAPI.php';`, ajouter :
```php
require_once __DIR__ . '/lib/Slug.php';
```

- [ ] **Step 2 : Remplacer les URLs des collections et albums**

Remplacer :
```php
        $urls[] = [
            'loc'        => $base . '/collection.php?id=' . urlencode($col['id']),
            'changefreq' => 'monthly',
            'priority'   => '0.8',
        ];
        foreach ($col['sets'] ?? [] as $set) {
            $urls[] = [
                'loc'        => $base . '/gallery.php?id=' . urlencode($set['id']) . '&col=' . urlencode($col['id']),
                'changefreq' => 'monthly',
                'priority'   => '0.7',
            ];
        }
```
Par :
```php
        $colSlug = Slug::slugify($col['title']);
        $urls[] = [
            'loc'        => $base . '/' . $colSlug,
            'changefreq' => 'monthly',
            'priority'   => '0.8',
        ];
        foreach ($col['sets'] ?? [] as $set) {
            $urls[] = [
                'loc'        => $base . '/' . $colSlug . '/' . Slug::slugify($set['title']),
                'changefreq' => 'monthly',
                'priority'   => '0.7',
            ];
        }
```

- [ ] **Step 3 : Commit**

```bash
git add sitemap.php
git commit -m "feat: friendly URLs in sitemap.php"
```

---

## Task 7 : Vérification finale

- [ ] **Step 1 : Déployer et tester les URLs**

Après déploiement (`bash deploy-ssh.sh`), tester :

```bash
# Collection → doit retourner 200
curl -sI "https://guillaumelecomte.net/belgique" | grep HTTP

# Album → doit retourner 200
curl -sI "https://guillaumelecomte.net/belgique/liege" | grep HTTP

# Ancienne URL collection → doit fonctionner (la règle htaccess catch-all redirige vers index.php, pas idéal mais pas cassé)
curl -sI "https://guillaumelecomte.net/collection.php?id=196254507-72157725150442283" | grep HTTP

# Slug inexistant → doit retourner 301 vers /
curl -sI "https://guillaumelecomte.net/slug-inexistant" | grep -E "HTTP|Location"

# Sitemap → doit contenir des URLs friendly
curl -s "https://guillaumelecomte.net/sitemap.xml" | grep '<loc>'
```

- [ ] **Step 2 : Vérifier dans le navigateur**

- Ouvrir `https://guillaumelecomte.net/` → les cartes de collection pointent vers `/nom-collection`
- Cliquer une collection → URL dans la barre = `/nom-collection`, les cartes d'albums pointent vers `/nom-collection/nom-album`
- Cliquer un album → URL = `/nom-collection/nom-album`, breadcrumb pointe vers `/nom-collection`
- Vérifier que la balise `<link rel="canonical">` dans le source pointe vers la friendly URL
- Soumettre un commentaire → la redirection après soumission revient sur la friendly URL
