# Flickr Gallery Site — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construire un site PHP sombre et immersif qui affiche les galeries Flickr d'un utilisateur, avec lightbox et panneau d'infos.

**Architecture:** PHP vanilla sans framework, appels cURL à l'API Flickr REST côté serveur, rendu HTML direct. Trois couches : config (.env), wrapper API (FlickrAPI.php), pages de présentation (index.php, gallery.php) + assets front (CSS dark, JS lightbox).

**Tech Stack:** PHP 8+, cURL, API Flickr REST JSON, CSS vanilla (Grid + columns masonry), JavaScript vanilla, Google Fonts (Playfair Display + Inter), déploiement rsync sur O2switch.

---

## Fichiers à créer

| Fichier | Rôle |
|---|---|
| `app/.env` | Clés API et User ID (non déployé) |
| `app/.env.example` | Template vide pour documentation |
| `app/.htaccess` | Rewrite rules Apache |
| `app/config.php` | Lecture `.env`, constantes globales |
| `app/lib/FlickrAPI.php` | Classe wrapper cURL → Flickr REST API |
| `app/index.php` | Page d'accueil : grille des galeries |
| `app/gallery.php` | Page galerie : grille masonry des photos |
| `app/css/style.css` | Design dark, responsive, animations |
| `app/js/lightbox.js` | Lightbox vanilla JS avec panneau d'infos |
| `deploy-ssh.sh` | Modifier : exclure `.env` du rsync |

---

## Task 1 : Structure de base et configuration

**Files:**
- Create: `app/.env`
- Create: `app/.env.example`
- Create: `app/.htaccess`
- Create: `app/config.php`
- Modify: `deploy-ssh.sh`

- [ ] **Step 1 : Créer `app/.env`**

```
FLICKR_API_KEY=18caedefb328eba11e90fc70a01f65ce
FLICKR_SECRET=c67730ea9f6f2d5d
FLICKR_USER_ID=196274855@N02
```

- [ ] **Step 2 : Créer `app/.env.example`**

```
FLICKR_API_KEY=
FLICKR_SECRET=
FLICKR_USER_ID=
```

- [ ] **Step 3 : Créer `app/.htaccess`**

```apache
Options -Indexes
RewriteEngine On

# Bloquer l'accès direct au .env
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

# Rediriger les requêtes vers index.php si le fichier n'existe pas
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?path=$1 [QSA,L]
```

- [ ] **Step 4 : Créer `app/config.php`**

```php
<?php

$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    die('<div style="font-family:monospace;color:#c00;padding:2rem">Erreur : fichier .env manquant. Copiez .env.example en .env et renseignez vos clés.</div>');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

define('FLICKR_API_KEY', $_ENV['FLICKR_API_KEY'] ?? '');
define('FLICKR_SECRET',  $_ENV['FLICKR_SECRET']  ?? '');
define('FLICKR_USER_ID', $_ENV['FLICKR_USER_ID'] ?? '');

if (empty(FLICKR_API_KEY) || empty(FLICKR_USER_ID)) {
    die('<div style="font-family:monospace;color:#c00;padding:2rem">Erreur : FLICKR_API_KEY et FLICKR_USER_ID sont requis dans .env</div>');
}
```

- [ ] **Step 5 : Modifier `deploy-ssh.sh` — ajouter l'exclusion du `.env`**

Trouver la section rsync upload (ligne ~95) et ajouter `--exclude '.env'` :

```bash
    rsync -avz --delete --timeout=60 $DRY_RUN \
        -e "ssh -i $SSH_KEY $SSH_OPTS" \
        --exclude '.DS_Store' \
        --exclude '*.log' \
        --exclude '.env' \
        "$LOCAL_DIR/" "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"
```

- [ ] **Step 6 : Vérifier que la structure existe**

```bash
ls app/
```

Attendu : `.env`, `.env.example`, `.htaccess`, `config.php`

- [ ] **Step 7 : Commit**

```bash
git add app/.env.example app/.htaccess app/config.php deploy-ssh.sh
git commit -m "feat: structure de base, config .env, htaccess"
```

Note : ne jamais `git add app/.env`.

---

## Task 2 : Classe FlickrAPI

**Files:**
- Create: `app/lib/FlickrAPI.php`

- [ ] **Step 1 : Créer le dossier `app/lib/`**

```bash
mkdir -p app/lib
```

- [ ] **Step 2 : Créer `app/lib/FlickrAPI.php`**

```php
<?php

class FlickrAPI
{
    private string $apiKey;
    private string $userId;
    private string $baseUrl = 'https://api.flickr.com/services/rest/';

    public function __construct(string $apiKey, string $userId)
    {
        $this->apiKey  = $apiKey;
        $this->userId  = $userId;
    }

    /**
     * Retourne la liste des galeries (photosets) de l'utilisateur.
     * Chaque élément : ['id', 'title', 'cover_url', 'photo_count']
     */
    public function getPhotosets(): array
    {
        $data = $this->request([
            'method'  => 'flickr.photosets.getList',
            'user_id' => $this->userId,
            'per_page' => 500,
            'primary_photo_extras' => 'url_l,url_m',
        ]);

        if (($data['stat'] ?? '') !== 'ok') {
            throw new RuntimeException('Flickr API error: ' . ($data['message'] ?? 'unknown'));
        }

        $photosets = [];
        foreach ($data['photosets']['photoset'] ?? [] as $ps) {
            $primary = $ps['primary_photo_extras'] ?? [];
            $coverUrl = $ps['primary_photo_extras']['url_l']
                     ?? $ps['primary_photo_extras']['url_m']
                     ?? $this->buildPhotoUrl($ps['farm'], $ps['server'], $ps['primary'], $ps['secret'], 'z');

            $photosets[] = [
                'id'          => $ps['id'],
                'title'       => $ps['title']['_content'] ?? '',
                'description' => $ps['description']['_content'] ?? '',
                'photo_count' => (int)($ps['photos'] ?? 0),
                'cover_url'   => $coverUrl,
            ];
        }

        return $photosets;
    }

    /**
     * Retourne les photos d'une galerie.
     * Chaque élément : ['id', 'title', 'description', 'url_thumb', 'url_large', 'date_taken', 'tags']
     */
    public function getPhotos(string $photosetId): array
    {
        $data = $this->request([
            'method'      => 'flickr.photosets.getPhotos',
            'photoset_id' => $photosetId,
            'user_id'     => $this->userId,
            'extras'      => 'url_l,url_m,url_sq,date_taken,description,tags',
            'per_page'    => 500,
        ]);

        if (($data['stat'] ?? '') !== 'ok') {
            throw new RuntimeException('Flickr API error: ' . ($data['message'] ?? 'unknown'));
        }

        $photos = [];
        foreach ($data['photoset']['photo'] ?? [] as $p) {
            $photos[] = [
                'id'          => $p['id'],
                'title'       => $p['title'] ?? '',
                'description' => $p['description']['_content'] ?? '',
                'url_thumb'   => $p['url_sq'] ?? $p['url_m'] ?? '',
                'url_large'   => $p['url_l']  ?? $p['url_m'] ?? '',
                'date_taken'  => $p['datetaken'] ?? '',
                'tags'        => array_filter(explode(' ', $p['tags'] ?? '')),
            ];
        }

        return $photos;
    }

    private function buildPhotoUrl(string $farm, string $server, string $id, string $secret, string $size): string
    {
        return "https://farm{$farm}.staticflickr.com/{$server}/{$id}_{$secret}_{$size}.jpg";
    }

    private function request(array $params): array
    {
        $params['api_key'] = $this->apiKey;
        $params['format']  = 'json';
        $params['nojsoncallback'] = 1;

        $url = $this->baseUrl . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('cURL error: ' . $error);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new RuntimeException('Invalid JSON from Flickr API');
        }

        return $decoded;
    }
}
```

- [ ] **Step 3 : Test manuel rapide en CLI**

Créer un fichier temporaire `app/test_api.php` :

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$api = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);

try {
    $sets = $api->getPhotosets();
    echo "Galeries trouvées : " . count($sets) . "\n";
    foreach (array_slice($sets, 0, 3) as $s) {
        echo "  - [{$s['id']}] {$s['title']} ({$s['photo_count']} photos)\n";
    }
} catch (RuntimeException $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
}
```

```bash
cd app && php test_api.php
```

Attendu : liste de galeries Flickr avec IDs et titres. Supprimer `test_api.php` après validation.

- [ ] **Step 4 : Supprimer le fichier de test**

```bash
rm app/test_api.php
```

- [ ] **Step 5 : Commit**

```bash
git add app/lib/FlickrAPI.php
git commit -m "feat: classe FlickrAPI avec getPhotosets et getPhotos"
```

---

## Task 3 : CSS — Design sombre

**Files:**
- Create: `app/css/style.css`

- [ ] **Step 1 : Créer le dossier `app/css/`**

```bash
mkdir -p app/css
```

- [ ] **Step 2 : Créer `app/css/style.css`**

```css
/* ============================
   RESET & BASE
   ============================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:         #0a0a0a;
    --surface:    #141414;
    --border:     #2a2a2a;
    --text:       #f0f0f0;
    --text-muted: #888888;
    --accent:     #c8a96e;
    --font-serif: 'Playfair Display', Georgia, serif;
    --font-sans:  'Inter', system-ui, sans-serif;
}

html { font-size: 16px; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-sans);
    min-height: 100vh;
}

a { color: inherit; text-decoration: none; }

/* ============================
   HEADER
   ============================ */
.site-header {
    padding: 2.5rem 2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: baseline;
    gap: 1rem;
}

.site-title {
    font-family: var(--font-serif);
    font-size: 1.8rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--text);
}

.site-subtitle {
    font-size: 0.85rem;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

/* ============================
   NAVIGATION (fil d'ariane)
   ============================ */
.breadcrumb {
    padding: 1rem 2rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.breadcrumb a {
    color: var(--accent);
}

.breadcrumb a:hover {
    text-decoration: underline;
}

/* ============================
   PAGE CONTAINER
   ============================ */
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.page-heading {
    font-family: var(--font-serif);
    font-size: 2rem;
    font-weight: 400;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.page-meta {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 2.5rem;
}

/* ============================
   GRILLE DES GALERIES (accueil)
   ============================ */
.galleries-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.gallery-card {
    position: relative;
    background: var(--surface);
    border: 1px solid var(--border);
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s ease, border-color 0.3s ease;
    display: block;
}

.gallery-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
}

.gallery-card-cover {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    display: block;
    filter: brightness(0.85);
    transition: filter 0.3s ease;
}

.gallery-card:hover .gallery-card-cover {
    filter: brightness(1);
}

.gallery-card-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.5rem 1rem 1rem;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, transparent 100%);
}

.gallery-card-title {
    font-family: var(--font-serif);
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gallery-card-count {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.6);
    letter-spacing: 0.05em;
}

/* ============================
   GRILLE DES PHOTOS (galerie)
   ============================ */
.photos-masonry {
    columns: 4;
    column-gap: 0.75rem;
}

.photo-item {
    break-inside: avoid;
    margin-bottom: 0.75rem;
    cursor: pointer;
    overflow: hidden;
    position: relative;
}

.photo-item img {
    width: 100%;
    display: block;
    filter: grayscale(30%);
    transition: filter 0.4s ease, transform 0.4s ease;
}

.photo-item:hover img {
    filter: grayscale(0%);
    transform: scale(1.02);
}

/* ============================
   LIGHTBOX
   ============================ */
.lightbox-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.95);
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.25s ease;
}

.lightbox-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
}

.lightbox-overlay.visible {
    opacity: 1;
}

.lightbox-main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
    padding: 2rem;
    position: relative;
}

.lightbox-img {
    max-height: 90vh;
    max-width: 70vw;
    object-fit: contain;
    transform: scale(0.95);
    transition: transform 0.25s ease;
    display: block;
}

.lightbox-overlay.visible .lightbox-img {
    transform: scale(1);
}

.lightbox-panel {
    width: 300px;
    min-width: 300px;
    height: 100vh;
    background: var(--surface);
    border-left: 1px solid var(--border);
    padding: 2rem 1.5rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.lightbox-title {
    font-family: var(--font-serif);
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text);
    line-height: 1.4;
}

.lightbox-desc {
    font-size: 0.88rem;
    color: var(--text-muted);
    line-height: 1.6;
}

.lightbox-date {
    font-size: 0.78rem;
    color: var(--text-muted);
    letter-spacing: 0.03em;
}

.lightbox-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.lightbox-tag {
    font-size: 0.72rem;
    padding: 0.2rem 0.5rem;
    border: 1px solid var(--border);
    color: var(--text-muted);
    letter-spacing: 0.03em;
}

/* Boutons navigation */
.lightbox-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.08);
    border: 1px solid var(--border);
    color: var(--text);
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    transition: background 0.2s ease;
    user-select: none;
}

.lightbox-btn:hover { background: rgba(255,255,255,0.15); }
.lightbox-btn-prev { left: 1rem; }
.lightbox-btn-next { right: 1rem; }

.lightbox-close {
    position: fixed;
    top: 1.25rem;
    right: 1.25rem;
    background: rgba(255,255,255,0.08);
    border: 1px solid var(--border);
    color: var(--text);
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    z-index: 1001;
    transition: background 0.2s ease;
}

.lightbox-close:hover { background: rgba(255,255,255,0.2); }

/* ============================
   MESSAGE D'ERREUR
   ============================ */
.error-box {
    background: var(--surface);
    border: 1px solid #5c2020;
    padding: 2rem;
    color: #e08080;
    font-size: 0.9rem;
    line-height: 1.6;
    margin: 2rem 0;
}

.error-box strong { color: #e8a0a0; }

/* ============================
   RESPONSIVE
   ============================ */
@media (max-width: 1024px) {
    .galleries-grid { grid-template-columns: repeat(2, 1fr); }
    .photos-masonry { columns: 3; }

    .lightbox-panel {
        width: 260px;
        min-width: 260px;
    }
}

@media (max-width: 768px) {
    .galleries-grid { grid-template-columns: 1fr; }
    .photos-masonry { columns: 2; }

    .lightbox-overlay.active {
        flex-direction: column;
    }

    .lightbox-main {
        height: auto;
        padding: 1rem;
        flex: none;
    }

    .lightbox-img {
        max-width: 100vw;
        max-height: 60vh;
    }

    .lightbox-panel {
        width: 100%;
        min-width: 0;
        height: auto;
        border-left: none;
        border-top: 1px solid var(--border);
        padding: 1rem;
        flex: 1;
        overflow-y: auto;
    }

    .lightbox-btn-prev { left: 0.5rem; }
    .lightbox-btn-next { right: 0.5rem; }
}
```

- [ ] **Step 3 : Commit**

```bash
git add app/css/style.css
git commit -m "feat: CSS dark theme, grilles, lightbox, responsive"
```

---

## Task 4 : JavaScript Lightbox

**Files:**
- Create: `app/js/lightbox.js`

- [ ] **Step 1 : Créer le dossier `app/js/`**

```bash
mkdir -p app/js
```

- [ ] **Step 2 : Créer `app/js/lightbox.js`**

```javascript
(function () {
    'use strict';

    let photos = [];
    let current = 0;
    let overlay, img, title, desc, date, tags;

    function init() {
        overlay = document.getElementById('lightbox-overlay');
        if (!overlay) return;

        img   = overlay.querySelector('.lightbox-img');
        title = overlay.querySelector('.lightbox-title');
        desc  = overlay.querySelector('.lightbox-desc');
        date  = overlay.querySelector('.lightbox-date');
        tags  = overlay.querySelector('.lightbox-tags');

        overlay.querySelector('.lightbox-close').addEventListener('click', close);
        overlay.querySelector('.lightbox-btn-prev').addEventListener('click', prev);
        overlay.querySelector('.lightbox-btn-next').addEventListener('click', next);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target === overlay.querySelector('.lightbox-main')) {
                close();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('active')) return;
            if (e.key === 'Escape')      close();
            if (e.key === 'ArrowLeft')   prev();
            if (e.key === 'ArrowRight')  next();
        });

        document.querySelectorAll('.photo-item').forEach(function (el, index) {
            el.addEventListener('click', function () { open(index); });
        });

        photos = Array.from(document.querySelectorAll('.photo-item')).map(function (el) {
            return {
                large: el.dataset.large,
                title: el.dataset.title   || '',
                desc:  el.dataset.desc    || '',
                date:  el.dataset.date    || '',
                tags:  el.dataset.tags    ? el.dataset.tags.split(',').filter(Boolean) : [],
            };
        });
    }

    function open(index) {
        current = index;
        overlay.classList.add('active');
        requestAnimationFrame(function () {
            overlay.classList.add('visible');
        });
        render();
    }

    function close() {
        overlay.classList.remove('visible');
        setTimeout(function () {
            overlay.classList.remove('active');
        }, 250);
    }

    function prev() {
        current = (current - 1 + photos.length) % photos.length;
        render();
    }

    function next() {
        current = (current + 1) % photos.length;
        render();
    }

    function render() {
        const p = photos[current];
        img.src      = p.large;
        title.textContent = p.title || '—';
        desc.textContent  = p.desc  || '';
        date.textContent  = p.date  ? formatDate(p.date) : '';

        tags.innerHTML = '';
        p.tags.forEach(function (t) {
            const span = document.createElement('span');
            span.className   = 'lightbox-tag';
            span.textContent = t;
            tags.appendChild(span);
        });
    }

    function formatDate(str) {
        if (!str) return '';
        const d = new Date(str);
        if (isNaN(d)) return str;
        return d.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
```

- [ ] **Step 3 : Commit**

```bash
git add app/js/lightbox.js
git commit -m "feat: lightbox vanilla JS avec navigation clavier et panneau infos"
```

---

## Task 5 : Page d'accueil `index.php`

**Files:**
- Create: `app/index.php`

- [ ] **Step 1 : Créer `app/index.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$api   = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error = null;
$photosets = [];

try {
    $photosets = $api->getPhotosets();
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeries Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <span class="site-title">Galeries</span>
    <span class="site-subtitle">Collection photographique</span>
</header>

<main class="container">

    <?php if ($error): ?>
    <div class="error-box">
        <strong>Impossible de charger les galeries.</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>

    <?php elseif (empty($photosets)): ?>
    <div class="error-box">
        Aucune galerie trouvée pour ce compte Flickr.
    </div>

    <?php else: ?>
    <div class="galleries-grid">
        <?php foreach ($photosets as $set): ?>
        <a class="gallery-card" href="gallery.php?id=<?= htmlspecialchars($set['id']) ?>">
            <img
                class="gallery-card-cover"
                src="<?= htmlspecialchars($set['cover_url']) ?>"
                alt="<?= htmlspecialchars($set['title']) ?>"
                loading="lazy"
            >
            <div class="gallery-card-info">
                <div class="gallery-card-title"><?= htmlspecialchars($set['title']) ?></div>
                <div class="gallery-card-count"><?= $set['photo_count'] ?> photo<?= $set['photo_count'] > 1 ? 's' : '' ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

</body>
</html>
```

- [ ] **Step 2 : Tester en local**

```bash
cd app && php -S localhost:8080
```

Ouvrir `http://localhost:8080` dans un navigateur. Vérifier :
- Les galeries s'affichent avec photos de couverture
- Le hover fonctionne (élévation + bordure dorée)
- Pas d'erreur PHP dans le terminal

Arrêter le serveur avec `Ctrl+C`.

- [ ] **Step 3 : Commit**

```bash
git add app/index.php
git commit -m "feat: page d'accueil avec grille des galeries Flickr"
```

---

## Task 6 : Page galerie `gallery.php`

**Files:**
- Create: `app/gallery.php`

- [ ] **Step 1 : Créer `app/gallery.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

$photosetId = $_GET['id'] ?? '';

if (empty($photosetId) || !preg_match('/^\d+$/', $photosetId)) {
    header('Location: index.php');
    exit;
}

$api    = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$error  = null;
$photos = [];
$galleryTitle = 'Galerie';

try {
    $photos = $api->getPhotos($photosetId);

    // Récupérer le titre de la galerie depuis les photosets
    $photosets = $api->getPhotosets();
    foreach ($photosets as $ps) {
        if ($ps['id'] === $photosetId) {
            $galleryTitle = $ps['title'];
            break;
        }
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($galleryTitle) ?> — Galeries Photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <span class="site-title">Galeries</span>
    <span class="site-subtitle">Collection photographique</span>
</header>

<nav class="breadcrumb">
    <a href="index.php">← Toutes les galeries</a>
    &nbsp;/&nbsp;
    <?= htmlspecialchars($galleryTitle) ?>
</nav>

<main class="container">

    <?php if ($error): ?>
    <div class="error-box">
        <strong>Impossible de charger cette galerie.</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>

    <?php elseif (empty($photos)): ?>
    <div class="error-box">
        Cette galerie ne contient aucune photo.
    </div>

    <?php else: ?>
    <h1 class="page-heading"><?= htmlspecialchars($galleryTitle) ?></h1>
    <p class="page-meta"><?= count($photos) ?> photo<?= count($photos) > 1 ? 's' : '' ?></p>

    <div class="photos-masonry">
        <?php foreach ($photos as $photo): ?>
        <div
            class="photo-item"
            data-large="<?= htmlspecialchars($photo['url_large']) ?>"
            data-title="<?= htmlspecialchars($photo['title']) ?>"
            data-desc="<?= htmlspecialchars($photo['description']) ?>"
            data-date="<?= htmlspecialchars($photo['date_taken']) ?>"
            data-tags="<?= htmlspecialchars(implode(',', $photo['tags'])) ?>"
        >
            <img
                src="<?= htmlspecialchars($photo['url_large']) ?>"
                alt="<?= htmlspecialchars($photo['title']) ?>"
                loading="lazy"
            >
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<!-- Lightbox -->
<div id="lightbox-overlay" class="lightbox-overlay">
    <div class="lightbox-main">
        <button class="lightbox-btn lightbox-btn-prev" aria-label="Photo précédente">&#8592;</button>
        <img class="lightbox-img" src="" alt="">
        <button class="lightbox-btn lightbox-btn-next" aria-label="Photo suivante">&#8594;</button>
    </div>
    <aside class="lightbox-panel">
        <p class="lightbox-title"></p>
        <p class="lightbox-desc"></p>
        <p class="lightbox-date"></p>
        <div class="lightbox-tags"></div>
    </aside>
    <button class="lightbox-close" aria-label="Fermer">&#10005;</button>
</div>

<script src="js/lightbox.js"></script>
</body>
</html>
```

- [ ] **Step 2 : Tester en local**

```bash
cd app && php -S localhost:8080
```

Ouvrir `http://localhost:8080`, cliquer sur une galerie, puis :
- Vérifier que la grille masonry s'affiche
- Cliquer sur une photo → lightbox s'ouvre avec animation
- Naviguer avec ←/→ clavier et boutons
- Vérifier le panneau latéral (titre, description, date, tags)
- Fermer avec Escape et clic sur l'overlay
- Tester le fil d'ariane "← Toutes les galeries"

Arrêter le serveur avec `Ctrl+C`.

- [ ] **Step 3 : Commit**

```bash
git add app/gallery.php
git commit -m "feat: page galerie avec masonry, lightbox et panneau infos"
```

---

## Task 7 : Déploiement sur O2switch

**Files:**
- Modify: `deploy-ssh.sh` (vérification finale)

- [ ] **Step 1 : Vérifier que `.env` est bien exclu du rsync**

Relire `deploy-ssh.sh` et confirmer que `--exclude '.env'` est présent dans la commande rsync upload (ajouté en Task 1 Step 5).

- [ ] **Step 2 : Dry-run du déploiement**

```bash
./deploy-ssh.sh --dry-run
```

Vérifier dans la sortie :
- Les fichiers `app/.htaccess`, `app/config.php`, `app/lib/FlickrAPI.php`, `app/css/style.css`, `app/js/lightbox.js`, `app/index.php`, `app/gallery.php` sont listés
- `app/.env` n'apparaît PAS dans la liste

- [ ] **Step 3 : Créer `.env` sur le serveur distant**

Le `.env` doit exister sur le serveur mais n'est pas déployé automatiquement. Le créer manuellement via SSH :

```bash
ssh legu7203@legu7203.odns.fr
```

Puis sur le serveur :

```bash
cat > /home/legu7203/phpphotos/.env << 'EOF'
FLICKR_API_KEY=18caedefb328eba11e90fc70a01f65ce
FLICKR_SECRET=c67730ea9f6f2d5d
FLICKR_USER_ID=196274855@N02
EOF
chmod 600 /home/legu7203/phpphotos/.env
```

- [ ] **Step 4 : Déploiement réel**

```bash
./deploy-ssh.sh
```

Attendu : tous les fichiers synchronisés sans erreur.

- [ ] **Step 5 : Vérifier en ligne**

Ouvrir l'URL du site sur O2switch (selon la configuration DNS de votre hébergement) et vérifier :
- Page d'accueil avec les galeries
- Navigation dans une galerie
- Lightbox fonctionnelle

- [ ] **Step 6 : Commit final**

```bash
git add .
git commit -m "chore: déploiement initial PHPPHOTOS sur O2switch"
```

---

## Self-Review

**Couverture de la spec :**
- ✅ Structure fichiers : Tasks 1-6
- ✅ Config `.env` avec clés réelles : Task 1
- ✅ FlickrAPI cURL + getPhotosets + getPhotos : Task 2
- ✅ Gestion d'erreurs (API down, galerie introuvable, .env manquant) : Tasks 1, 5, 6
- ✅ CSS dark palette exacte (#0a0a0a etc.) : Task 3
- ✅ Playfair Display + Inter : Task 3
- ✅ Grille 3 cols + hover élévation + bordure dorée : Task 3
- ✅ Masonry CSS columns + grayscale→couleur hover : Task 3
- ✅ Lightbox overlay + animation + panneau latéral + tags : Tasks 3, 4
- ✅ Navigation clavier ←/→ + Escape : Task 4
- ✅ Responsive 3 breakpoints : Task 3
- ✅ `.env` exclu du rsync : Tasks 1, 7
- ✅ `.env.example` versionné : Task 1
- ✅ Déploiement O2switch : Task 7
