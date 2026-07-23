# Recent Albums Homepage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Afficher une section "Derniers albums" avec 6 cartes en haut du contenu de la homepage, chaque carte pointant vers la galerie de l'album via URL friendly.

**Architecture:** On ajoute un paramètre `$limit` à `FlickrAPI::getPhotosets()`, puis dans `index.php` on construit un map `set_id → colSlug` à partir des collections déjà chargées, et on injecte la section HTML avant la grille des collections. Le CSS réutilise les classes `.gallery-card` existantes avec une nouvelle grille à 6 colonnes.

**Tech Stack:** PHP 8, Flickr REST API, CSS Grid

---

### Task 1 : Ajouter le paramètre `$limit` à `FlickrAPI::getPhotosets()`

**Files:**
- Modify: `lib/FlickrAPI.php:65-96`

- [ ] **Step 1 : Modifier la signature et la requête**

Remplacer la ligne 65 :
```php
public function getPhotosets(): array
```
par :
```php
public function getPhotosets(int $limit = 0): array
```

Et remplacer la ligne dans `$this->request([...])` où `'per_page' => 500` se trouve (ligne ~70) :
```php
'per_page' => $limit > 0 ? $limit : 500,
```

- [ ] **Step 2 : Vérifier manuellement que la signature est correcte**

```bash
grep -n "getPhotosets" lib/FlickrAPI.php
```
Résultat attendu : deux occurrences — la définition et éventuellement d'autres fichiers qui l'appellent.

- [ ] **Step 3 : Commit**

```bash
git add lib/FlickrAPI.php
git commit -m "feat: add \$limit param to FlickrAPI::getPhotosets()"
```

---

### Task 2 : Charger les 6 derniers albums dans `index.php`

**Files:**
- Modify: `index.php:6-14`

- [ ] **Step 1 : Ajouter le chargement des albums récents et la construction du map**

Après le bloc `try { $collections = ... }` (ligne ~10-13), ajouter :

```php
$recentAlbums = [];
$setToColSlug = [];

// Construire le map set_id → colSlug à partir des collections déjà chargées
foreach ($collections as $col) {
    $colSlug = Slug::slugify($col['title']);
    foreach ($col['sets'] as $set) {
        $setToColSlug[$set['id']] = $colSlug;
    }
}

try {
    $recentAlbums = $api->getPhotosets(6);
} catch (RuntimeException $e) {
    // silencieux : la section est absente si l'API échoue
}
```

- [ ] **Step 2 : Vérifier la syntaxe PHP**

```bash
php -l index.php
```
Résultat attendu : `No syntax errors detected in index.php`

- [ ] **Step 3 : Commit**

```bash
git add index.php
git commit -m "feat: load 6 recent albums in index.php with col slug map"
```

---

### Task 3 : Ajouter la section HTML dans `index.php`

**Files:**
- Modify: `index.php:111-132` (bloc `<?php else: ?>`)

- [ ] **Step 1 : Insérer la section "Derniers albums" avant `.galleries-grid`**

Juste après `<?php else: ?>` (ligne ~111) et avant `<div class="galleries-grid">`, ajouter :

```php
<?php if (!empty($recentAlbums)): ?>
<section class="recent-albums">
    <h2 class="recent-albums-title">Derniers albums</h2>
    <div class="recent-albums-grid">
        <?php foreach ($recentAlbums as $album):
            $albumSlug = Slug::slugify($album['title']);
            $colSlug   = $setToColSlug[$album['id']] ?? null;
            $href      = $colSlug
                ? '/' . htmlspecialchars($colSlug) . '/' . htmlspecialchars($albumSlug)
                : 'gallery.php?id=' . htmlspecialchars($album['id']);
        ?>
        <a class="gallery-card" href="<?= $href ?>">
            <?php if ($album['cover_url']): ?>
            <img
                class="gallery-card-cover"
                src="<?= htmlspecialchars($album['cover_url']) ?>"
                alt="<?= htmlspecialchars($album['title']) ?>"
                loading="lazy"
            >
            <?php else: ?>
            <div class="gallery-card-cover gallery-card-no-cover"></div>
            <?php endif; ?>
            <div class="gallery-card-info">
                <div class="gallery-card-title"><?= htmlspecialchars($album['title']) ?></div>
                <div class="gallery-card-count"><?= $album['photo_count'] ?> photo<?= $album['photo_count'] > 1 ? 's' : '' ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
```

- [ ] **Step 2 : Vérifier la syntaxe PHP**

```bash
php -l index.php
```
Résultat attendu : `No syntax errors detected in index.php`

- [ ] **Step 3 : Commit**

```bash
git add index.php
git commit -m "feat: add recent albums section HTML on homepage"
```

---

### Task 4 : Ajouter les styles CSS

**Files:**
- Modify: `css/style.css`

- [ ] **Step 1 : Ajouter les styles de la section après le bloc `GRILLE COLLECTIONS/ALBUMS`**

Après la règle `.gallery-card-no-cover { ... }` (ligne ~315), insérer :

```css
/* ============================
   SECTION DERNIERS ALBUMS
   ============================ */
.recent-albums {
    margin-bottom: 3rem;
}

.recent-albums-title {
    font-family: var(--font-serif);
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-muted);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 1rem;
}

.recent-albums-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1.2rem;
}
```

- [ ] **Step 2 : Ajouter les breakpoints responsive**

Dans le media query `@media (max-width: 1024px)` (ligne ~664), ajouter :
```css
.recent-albums-grid { grid-template-columns: repeat(3, 1fr); }
```

Dans le media query `@media (max-width: 768px)`, ajouter :
```css
.recent-albums-grid { grid-template-columns: repeat(2, 1fr); }
```

Dans le media query `@media (max-width: 600px)`, ajouter :
```css
.recent-albums-grid { grid-template-columns: repeat(2, 1fr); }
```

- [ ] **Step 3 : Commit**

```bash
git add css/style.css
git commit -m "feat: add CSS for recent albums section on homepage"
```

---

### Task 5 : Vérification visuelle

- [ ] **Step 1 : Tester en local ou sur le serveur**

Ouvrir la homepage et vérifier :
- La section "Derniers albums" apparaît avant la grille des collections
- 6 cartes sont affichées avec cover, titre et nombre de photos
- Chaque carte pointe vers `/colSlug/albumSlug`
- Sur mobile (< 768px), la grille passe à 2 colonnes
- En cas d'échec API (désactiver temporairement la clé), la section est absente sans erreur visible

- [ ] **Step 2 : Commit final si ajustements mineurs**

```bash
git add -p
git commit -m "fix: minor adjustments to recent albums section"
```
