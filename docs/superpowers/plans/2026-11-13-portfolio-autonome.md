# Portfolio photo autonome — plan d'implémentation

> **Pour les agents :** sous-skill OBLIGATOIRE : utiliser superpowers:subagent-driven-development (recommandé) ou superpowers:executing-plans pour exécuter ce plan tâche par tâche. Les étapes utilisent des cases à cocher (`- [ ]`) pour le suivi.

**Objectif :** remplacer la galerie Flickr par un portfolio PHP autonome, avec une administration pour envoyer des photos, des thèmes et des albums, une visionneuse avec EXIF, une carte GPS, une page Présentation et un formulaire de contact. Le tout est déployé sur `dev.guillaumelecomte.net`.

**Architecture :** une application PHP 8 sans framework. Tout passe par un contrôleur frontal unique (`public/index.php`), puis un routeur à expressions régulières, des contrôleurs, des templates PHP natifs, et des modèles statiques sur PDO MySQL. Les images sont traitées avec GD : 3 largeurs, en WebP et en JPEG. Côté navigateur, il n'y a que du JS natif, avec Leaflet et SortableJS chargés par CDN dans des versions figées.

**Stack technique :** PHP 8.4+ (8.5 en local et sur le serveur), MySQL/MariaDB, GD (WebP), ext-exif, Dom\HTMLDocument (PHP 8.4), PHPUnit 11, Leaflet 1.9.4, Leaflet.markercluster 1.5.3, SortableJS 1.15.2, hébergement O2switch (LiteSpeed).

**Spécification :** `docs/superpowers/specs/2026-11-13-portfolio-autonome-design.md`

**Écarts assumés par rapport à la spec** (reportés dans la spec en tâche 1) :
- Les images sont rangées par préfixe de clé, `public/media/{2 premiers caractères de la clé}/{clé}-{largeur}.{ext}`, et non par `album_id`. Ainsi, déplacer une photo d'un album à un autre ne déplace aucun fichier.
- Sur l'accueil, la grille « Galeries » affiche **tous** les thèmes visibles, y compris le thème mis en avant. Le lien de navigation « Galeries » fonctionne donc toujours.
- Les noms de fichiers sont remplacés en place (sans dossier temporaire), avec un nettoyage des fichiers partiels en cas d'erreur.

**Conventions :**
- Tous les fichiers PHP commencent par `declare(strict_types=1);`, sauf les templates.
- Chaque sortie dans un template passe par `e()`, sauf le HTML déjà filtré par `HtmlSanitizer`.
- Chaque route POST de l'administration commence par `$this->verifyCsrf()`.
- Les entiers dans `LIMIT` sont convertis en `int` puis interpolés directement, jamais passés en paramètre `?`.
- Les commandes s'exécutent depuis la racine du dépôt : `"/Users/guillaumelecomte/Library/Mobile Documents/com~apple~CloudDocs/Documents/VSCODE/PHPPHOTOS"`, sur la branche `portfolio-autonome`.

---

## Arborescence finale

```
bin/create-admin.php            création ou réinitialisation du compte admin (CLI interactif)
bin/migrate.php                 schéma + données initiales + création des dossiers
bin/set-presentation-images.php import des 2 photos de la page Présentation
composer.json, phpunit.xml, .env.example, .gitignore, deploy-ssh.sh
database/schema.sql, database/seed.sql
public/index.php                contrôleur frontal
public/.htaccess, public/.user.ini
public/media/.htaccess          interdit l'exécution de scripts dans les médias
public/assets/css/site.css, public/assets/css/admin.css
public/assets/js/mosaic.js, viewer.js, map.js, admin.js, admin-upload.js
public/assets/img/logo.png
src/bootstrap.php, src/helpers.php, src/routes.php
src/Services/Env.php, Slug.php, Router.php, RateLimiter.php, HtmlSanitizer.php,
             ExifReader.php, ImageException.php, ImageProcessor.php, Mailer.php,
             ContactForm.php, Database.php, View.php, Csrf.php, Auth.php, PresentationImages.php
src/Models/Theme.php, Album.php, Photo.php, Page.php, Setting.php
src/Controllers/Controller.php, PublicController.php, ContactController.php
src/Controllers/Admin/AdminController.php, AuthController.php, DashboardController.php,
             ThemeController.php, AlbumController.php, PhotoController.php, ContentController.php
templates/layout.php
templates/partials/header.php, footer.php, trace.php, picture.php, album-card.php
templates/public/home.php, theme.php, album.php, map.php, presentation.php, contact.php, error.php
templates/admin/layout.php, login.php, dashboard.php, content.php
templates/admin/themes/index.php, form.php
templates/admin/albums/index.php, form.php, photos.php
templates/admin/partials/photo-card.php
tests/bootstrap.php, tests/fixtures/*, tests/Services/*Test.php
storage/logs/.gitkeep, storage/ratelimit/.gitkeep
```

---

### Tâche 1 : archiver l'ancien site et poser le socle

**Fichiers :**
- Supprimer : tout l'ancien code (`*.php` à la racine, `css/`, `js/`, `lib/`, `includes/`, `.htaccess`, `.github/`, `.env.example`, `deploy-ssh.sh`)
- Créer : `composer.json`, `phpunit.xml`, `.gitignore`, `.env.example`, `src/bootstrap.php`, `src/helpers.php`, `tests/bootstrap.php`, `storage/logs/.gitkeep`, `storage/ratelimit/.gitkeep`, `public/media/.htaccess`, `public/assets/img/presentation/.gitkeep`
- Modifier : `docs/superpowers/specs/2026-11-13-portfolio-autonome-design.md`

- [ ] **Étape 1 : archiver les modifications non commitées de l'ancien site**

```bash
git add -A collection.php deploy-ssh.sh gallery.php guestbook.php includes index.php lib map.php docs/superpowers/specs/2026-05-30-comments-guestbook-design.md
git commit -m "archive: dernier état de l'ancien site Flickr

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Étape 2 : supprimer l'ancien code**

```bash
git rm -r -q collection.php comment_submit.php config.php db.php gallery.php guestbook.php index.php map-data.php map.php photo-info.php sitemap.php css js lib includes .htaccess .github .env.example deploy-ssh.sh
ls
```
Attendu : il ne reste que `docs/` (plus les fichiers ignorés comme `.env`).

- [ ] **Étape 3 : créer `composer.json`**

```json
{
    "name": "glecomte/portfolio",
    "description": "Portfolio photo de Guillaume Lecomte",
    "type": "project",
    "require": {
        "php": ">=8.4",
        "ext-gd": "*",
        "ext-exif": "*",
        "ext-pdo": "*",
        "ext-fileinfo": "*",
        "ext-dom": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.5"
    },
    "autoload": {
        "psr-4": { "App\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Tests\\": "tests/" }
    },
    "scripts": {
        "test": "phpunit"
    },
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Étape 4 : créer `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Étape 5 : créer `.gitignore`** (remplace l'existant)

```gitignore
.env
.DS_Store
.superpowers/
.claude/
.worktrees/
/vendor/
/.phpunit.cache/
/public/media/*
!/public/media/.htaccess
/public/assets/img/presentation/*
!/public/assets/img/presentation/.gitkeep
/public/.well-known/
/storage/logs/*
!/storage/logs/.gitkeep
/storage/ratelimit/*
!/storage/ratelimit/.gitkeep
```

- [ ] **Étape 6 : créer `.env.example`**

```dotenv
# dev = site non indexé (robots + X-Robots-Tag) ; prod = site public
APP_ENV=dev
# 1 = afficher les erreurs à l'écran (local uniquement)
APP_DEBUG=1
APP_URL=http://localhost:8000
# 64 caractères hexadécimaux : php -r 'echo bin2hex(random_bytes(32));'
APP_KEY=

DB_HOST=localhost
DB_NAME=portfolio_local
DB_USER=root
DB_PASS=

MAIL_HOST=
MAIL_PORT=465
MAIL_USER=
MAIL_PASS=
NOTIFY_EMAIL=
```

- [ ] **Étape 7 : créer `src/bootstrap.php`**

```php
<?php
declare(strict_types=1);

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once ROOT . '/src/helpers.php';

App\Services\Env::load(ROOT . '/.env');

date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL);
ini_set('display_errors', App\Services\Env::get('APP_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/app.log');
```

- [ ] **Étape 8 : créer `src/helpers.php`**

```php
<?php
declare(strict_types=1);

use App\Services\Csrf;
use App\Services\Env;

const SESSION_NAME = 'gl_session';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function absolute_url(string $path = '/'): string
{
    return rtrim((string) Env::get('APP_URL', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = ROOT . '/public/assets/' . $path;
    return '/assets/' . $path . '?v=' . (is_file($file) ? filemtime($file) : '0');
}

function media_path(string $key, int $width, string $ext = 'webp'): string
{
    return 'media/' . substr($key, 0, 2) . '/' . $key . '-' . $width . '.' . $ext;
}

function media_url(string $key, int $width, string $ext = 'webp'): string
{
    return '/' . media_path($key, $width, $ext);
}

function media_dir(string $key): string
{
    return ROOT . '/public/media/' . substr($key, 0, 2);
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, ?string $message = null): ?string
{
    start_session();
    if ($message !== null) {
        $_SESSION['_flash'][$type] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$type] ?? null;
    unset($_SESSION['_flash'][$type]);
    return $value;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function redirect(string $to, int $code = 303): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

const MONTHS_FR = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function format_date_fr(?string $date): string
{
    $t = $date ? strtotime($date) : false;
    if ($t === false) {
        return '';
    }
    return (int) date('j', $t) . ' ' . MONTHS_FR[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

function format_month_fr(?string $date): string
{
    $t = $date ? strtotime($date) : false;
    if ($t === false) {
        return '';
    }
    return MONTHS_FR[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}
```

- [ ] **Étape 9 : créer `tests/bootstrap.php`**

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}
require_once ROOT . '/src/helpers.php';
```

- [ ] **Étape 10 : créer les dossiers et le `.htaccess` des médias**

```bash
mkdir -p storage/logs storage/ratelimit public/media public/assets/img/presentation tests/Services tests/fixtures
touch storage/logs/.gitkeep storage/ratelimit/.gitkeep public/assets/img/presentation/.gitkeep
```

`public/media/.htaccess` :
```apache
# Médias : aucun script ne doit jamais s'exécuter ici
Options -Indexes -ExecCGI
RewriteEngine Off
RemoveHandler .php .phtml .phar .php5 .php7 .php8
<FilesMatch "\.(php|phtml|phar|php\d|pl|py|cgi|sh)$">
    Require all denied
</FilesMatch>
```

- [ ] **Étape 11 : écrire un test de fumée pour les helpers**

`tests/Services/HelpersTest.php` :
```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testEscapesHtml(): void
    {
        self::assertSame('&lt;b&gt;&quot;Été&quot;&lt;/b&gt;', e('<b>"Été"</b>'));
    }

    public function testMediaUrlUsesKeyPrefixFolder(): void
    {
        self::assertSame('/media/ab/abcdef-1280.jpg', media_url('abcdef', 1280, 'jpg'));
    }

    public function testFrenchDates(): void
    {
        self::assertSame('14 juin 2025', format_date_fr('2025-06-14 10:32:05'));
        self::assertSame('août 2024', format_month_fr('2024-08-01'));
        self::assertSame('', format_date_fr(null));
    }
}
```

- [ ] **Étape 12 : installer les dépendances et lancer les tests**

```bash
composer install
composer test
```
Attendu : `OK (3 tests, 5 assertions)`.

- [ ] **Étape 13 : reporter les écarts dans la spec**

Dans `docs/superpowers/specs/2026-11-13-portfolio-autonome-design.md` :
- remplacer la ligne qui commence par `- Chemin : \`public/media/{album_id}/` par :
  `- Chemin : \`public/media/{2 premiers caractères de file_key}/{file_key}-{w}.{webp|jpg}\`, avec w ∈ {480, 1280, 2560}. Le dossier ne dépend pas de l'album, donc déplacer une photo ne déplace aucun fichier.`
- dans « Accueil », point 4, remplacer `tous les autres thèmes` par `tous les thèmes visibles (y compris le thème mis en avant)`.

- [ ] **Étape 14 : commit**

```bash
git add -A
git commit -m "chore: socle du nouveau portfolio (composer, phpunit, bootstrap, helpers)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 2 : Env (lecture du `.env`)

**Fichiers :**
- Créer : `src/Services/Env.php`
- Test : `tests/Services/EnvTest.php`

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testParsesValuesCommentsAndQuotes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# commentaire\nAPP_ENV=dev\nDB_PASS=\"a b=c\"\nEMPTY=\nQUOTED='x'\n\nBROKEN_LINE\n");

        Env::load($file);

        self::assertSame('dev', Env::get('APP_ENV'));
        self::assertSame('a b=c', Env::get('DB_PASS'));
        self::assertSame('', Env::get('EMPTY'));
        self::assertSame('x', Env::get('QUOTED'));
        self::assertNull(Env::get('BROKEN_LINE'));
        self::assertSame('defaut', Env::get('ABSENT', 'defaut'));
        unlink($file);
    }

    public function testMissingFileIsIgnored(): void
    {
        Env::load('/nonexistent/.env');
        self::assertSame('x', Env::get('NOPE', 'x'));
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Commande : `./vendor/bin/phpunit tests/Services/EnvTest.php`
Attendu : ÉCHEC avec `Class "App\Services\Env" not found`.

- [ ] **Étape 3 : écrire l'implémentation**

`src/Services/Env.php` :
```php
<?php
declare(strict_types=1);

namespace App\Services;

final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            $quoted = strlen($value) >= 2
                && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")));
            self::$vars[trim($key)] = $quoted ? substr($value, 1, -1) : $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$vars[$key] ?? $default;
    }
}
```

- [ ] **Étape 4 : lancer le test pour vérifier qu'il passe**

Commande : `./vendor/bin/phpunit tests/Services/EnvTest.php`
Attendu : `OK (2 tests, 7 assertions)`.

- [ ] **Étape 5 : commit**

```bash
git add src/Services/Env.php tests/Services/EnvTest.php
git commit -m "feat: lecture du fichier .env

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 3 : Slug

**Fichiers :**
- Créer : `src/Services/Slug.php`
- Test : `tests/Services/SlugTest.php`

L'extension intl n'est pas installée en local : on utilise une table de conversion des accents.

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Slug;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testMakesReadableAsciiSlugs(): void
    {
        self::assertSame('cote-d-opale-2026', Slug::make("Côte d'Opale 2026"));
        self::assertSame('vues-du-ciel', Slug::make('  Vues du ciel !! '));
        self::assertSame('noel-a-l-ecole-oeuvre', Slug::make('Noël à l’École — Œuvre'));
        self::assertSame('sans-titre', Slug::make('???'));
    }

    public function testTruncatesTo80Characters(): void
    {
        $slug = Slug::make(str_repeat('abc ', 40));
        self::assertLessThanOrEqual(80, strlen($slug));
        self::assertStringEndsNotWith('-', $slug);
    }

    public function testUniqueAvoidsReservedAndExisting(): void
    {
        self::assertSame('carte-2', Slug::unique('Carte', fn () => false, true));
        self::assertSame('carte', Slug::unique('Carte', fn () => false, false));
        $taken = ['paysages', 'paysages-2'];
        self::assertSame('paysages-3', Slug::unique('Paysages', fn (string $s) => in_array($s, $taken, true)));
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Commande : `./vendor/bin/phpunit tests/Services/SlugTest.php`
Attendu : ÉCHEC avec `Class "App\Services\Slug" not found`.

- [ ] **Étape 3 : écrire l'implémentation**

`src/Services/Slug.php` :
```php
<?php
declare(strict_types=1);

namespace App\Services;

final class Slug
{
    /** Premiers segments d'URL utilisés par le site : aucun thème ne peut les prendre. */
    public const RESERVED = ['admin', 'assets', 'media', 'carte', 'carte.json', 'presentation', 'contact', 'sitemap.xml', 'robots.txt'];

    private const MAP = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'ý' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    public static function make(string $text): string
    {
        $s = strtr(mb_strtolower($text, 'UTF-8'), self::MAP);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim(substr(trim($s, '-'), 0, 80), '-');
        return $s === '' ? 'sans-titre' : $s;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true);
    }

    /** @param callable(string): bool $exists */
    public static function unique(string $text, callable $exists, bool $checkReserved = false): string
    {
        $base = self::make($text);
        $slug = $base;
        $i = 2;
        while (($checkReserved && self::isReserved($slug)) || $exists($slug)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
```

- [ ] **Étape 4 : lancer le test pour vérifier qu'il passe**

Commande : `./vendor/bin/phpunit tests/Services/SlugTest.php`
Attendu : `OK (3 tests, 9 assertions)`.

- [ ] **Étape 5 : commit**

```bash
git add src/Services/Slug.php tests/Services/SlugTest.php
git commit -m "feat: génération des slugs d'URL

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 4 : Router

**Fichiers :**
- Créer : `src/Services/Router.php`
- Test : `tests/Services/RouterTest.php`

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', fn () => 'home');
        $this->router->get('/carte', fn () => 'map');
        $this->router->get('/admin/albums/{id:\d+}', fn (string $id) => "album $id");
        $this->router->post('/admin/albums/{id:\d+}', fn (string $id) => "update $id");
        $this->router->get('/{theme}/{album}/photo/{photo:\d+}', fn (string $theme, string $album, string $photo) => "$theme/$album/$photo");
        $this->router->get('/{theme}/{album}', fn (string $theme, string $album) => "$theme/$album");
        $this->router->get('/{theme}', fn (string $theme) => "theme $theme");
    }

    private function call(string $method, string $path): ?string
    {
        $found = $this->router->match($method, $path);
        return $found === null ? null : ($found[0])(...$found[1]);
    }

    public function testMatchesStaticAndDynamicRoutesInOrder(): void
    {
        self::assertSame('home', $this->call('GET', '/'));
        self::assertSame('map', $this->call('GET', '/carte'));
        self::assertSame('map', $this->call('GET', '/carte/'));
        self::assertSame('album 12', $this->call('GET', '/admin/albums/12'));
        self::assertSame('update 12', $this->call('POST', '/admin/albums/12'));
        self::assertSame('vues-du-ciel/arras/34', $this->call('GET', '/vues-du-ciel/arras/photo/34'));
        self::assertSame('vues-du-ciel/arras', $this->call('GET', '/vues-du-ciel/arras'));
        self::assertSame('theme paysages', $this->call('GET', '/paysages'));
    }

    public function testHeadIsTreatedAsGet(): void
    {
        self::assertSame('map', $this->call('HEAD', '/carte'));
    }

    public function testNoMatch(): void
    {
        self::assertNull($this->router->match('GET', '/admin/albums/abc'));
        self::assertNull($this->router->match('POST', '/carte'));
        self::assertNull($this->router->match('GET', '/Majuscules'));
        self::assertNull($this->router->match('GET', '/a/b/c/d'));
    }

    public function testDispatchCallsNotFound(): void
    {
        $called = false;
        $this->router->dispatch('GET', '/x/y/z/w?q=1', function () use (&$called) { $called = true; });
        self::assertTrue($called);
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Commande : `./vendor/bin/phpunit tests/Services/RouterTest.php`
Attendu : ÉCHEC avec `Class "App\Services\Router" not found`.

- [ ] **Étape 3 : écrire l'implémentation**

`src/Services/Router.php` :
```php
<?php
declare(strict_types=1);

namespace App\Services;

final class Router
{
    /** @var list<array{0: string, 1: string, 2: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[2] ?? '[a-z0-9-]+') . ')',
            $pattern
        );
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    /** @return array{0: callable, 1: array<string, string>}|null */
    public function match(string $method, string $path): ?array
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $path = '/' . trim($path, '/');
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod === $method && preg_match($regex, $path, $m)) {
                return [$handler, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }
        return null;
    }

    public function dispatch(string $method, string $uri, callable $notFound): void
    {
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '/'));
        $found = $this->match($method, $path);
        if ($found === null) {
            $notFound();
            return;
        }
        [$handler, $params] = $found;
        $handler(...$params);
    }
}
```

- [ ] **Étape 4 : lancer le test pour vérifier qu'il passe**

Commande : `./vendor/bin/phpunit tests/Services/RouterTest.php`
Attendu : `OK (4 tests, 14 assertions)`.

- [ ] **Étape 5 : commit**

```bash
git add src/Services/Router.php tests/Services/RouterTest.php
git commit -m "feat: routeur à expressions régulières

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 5 : RateLimiter

**Fichiers :**
- Créer : `src/Services/RateLimiter.php`
- Test : `tests/Services/RateLimiterTest.php`

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rl-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testBlocksAfterMaxHitsWithinWindow(): void
    {
        $rl = new RateLimiter($this->dir);
        $now = 1_000_000;
        for ($i = 0; $i < 3; $i++) {
            self::assertFalse($rl->tooMany('contact:1.2.3.4', 3, 3600, $now));
            $rl->hit('contact:1.2.3.4', 3600, $now);
        }
        self::assertTrue($rl->tooMany('contact:1.2.3.4', 3, 3600, $now + 10));
        self::assertFalse($rl->tooMany('contact:5.6.7.8', 3, 3600, $now + 10));
    }

    public function testOldHitsExpire(): void
    {
        $rl = new RateLimiter($this->dir);
        $rl->hit('k', 60, 1000);
        $rl->hit('k', 60, 1001);
        self::assertTrue($rl->tooMany('k', 2, 60, 1030));
        self::assertFalse($rl->tooMany('k', 2, 60, 1100));
    }

    public function testClear(): void
    {
        $rl = new RateLimiter($this->dir);
        $rl->hit('k', 60, 1000);
        $rl->clear('k');
        self::assertFalse($rl->tooMany('k', 1, 60, 1001));
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Commande : `./vendor/bin/phpunit tests/Services/RateLimiterTest.php`
Attendu : ÉCHEC avec `Class "App\Services\RateLimiter" not found`.

- [ ] **Étape 3 : écrire l'implémentation**

`src/Services/RateLimiter.php` :
```php
<?php
declare(strict_types=1);

namespace App\Services;

final class RateLimiter
{
    public function __construct(private string $dir)
    {
    }

    public function tooMany(string $key, int $max, int $windowSeconds, ?int $now = null): bool
    {
        return count($this->recent($key, $windowSeconds, $now ?? time())) >= $max;
    }

    public function hit(string $key, int $windowSeconds, ?int $now = null): void
    {
        $now ??= time();
        $hits = $this->recent($key, $windowSeconds, $now);
        $hits[] = $now;
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        file_put_contents($this->file($key), json_encode($hits), LOCK_EX);
    }

    public function clear(string $key): void
    {
        if (is_file($this->file($key))) {
            unlink($this->file($key));
        }
    }

    /** @return list<int> */
    private function recent(string $key, int $windowSeconds, int $now): array
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return [];
        }
        $hits = json_decode((string) file_get_contents($file), true);
        if (!is_array($hits)) {
            return [];
        }
        return array_values(array_filter($hits, static fn ($t) => is_int($t) && $t > $now - $windowSeconds));
    }

    private function file(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.json';
    }
}
```

- [ ] **Étape 4 : lancer le test pour vérifier qu'il passe**

Commande : `./vendor/bin/phpunit tests/Services/RateLimiterTest.php`
Attendu : `OK (3 tests, 8 assertions)`.

- [ ] **Étape 5 : commit**

```bash
git add src/Services/RateLimiter.php tests/Services/RateLimiterTest.php
git commit -m "feat: limitation de débit par fichiers

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 6 : HtmlSanitizer

**Fichiers :**
- Créer : `src/Services/HtmlSanitizer.php`
- Test : `tests/Services/HtmlSanitizerTest.php`

Utilise `Dom\HTMLDocument` (PHP 8.4+), un analyseur HTML5 qui gère l'UTF-8 correctement.

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function testRemovesDangerousContent(): void
    {
        $dirty = '<p onclick="x()">Été <script>alert(1)</script><a href="javascript:alert(1)">lien</a> <span>ok</span></p><!-- c -->';
        self::assertSame('<p>Été <a>lien</a> ok</p>', HtmlSanitizer::clean($dirty));
    }

    public function testKeepsAllowedTagsAndSafeLinks(): void
    {
        $html = '<p><strong>Gras</strong> <em>it</em> <a href="https://exemple.fr" target="_blank">x</a> <a href="/contact">c</a></p><ul><li>un</li></ul>';
        self::assertSame(
            '<p><strong>Gras</strong> <em>it</em> <a href="https://exemple.fr" rel="noopener">x</a> <a href="/contact">c</a></p><ul><li>un</li></ul>',
            HtmlSanitizer::clean($html)
        );
    }

    public function testParagraphsFromPlainText(): void
    {
        self::assertSame("<p>Un</p>\n<p>Deux<br>trois</p>", HtmlSanitizer::paragraphs("Un\n\nDeux\ntrois"));
        self::assertSame('<p>Déjà</p>', HtmlSanitizer::paragraphs('<p>Déjà</p>'));
        self::assertSame('', HtmlSanitizer::paragraphs("  \n "));
    }

    public function testEditableRoundTrip(): void
    {
        $stored = "<p>Un <strong>a</strong></p>\n<p>Deux<br>trois</p>";
        $editable = HtmlSanitizer::toEditable($stored);
        self::assertSame("Un <strong>a</strong>\n\nDeux\ntrois", $editable);
        self::assertSame($stored, HtmlSanitizer::clean(HtmlSanitizer::paragraphs($editable)));
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Commande : `./vendor/bin/phpunit tests/Services/HtmlSanitizerTest.php`
Attendu : ÉCHEC avec `Class "App\Services\HtmlSanitizer" not found`.

- [ ] **Étape 3 : écrire l'implémentation**

`src/Services/HtmlSanitizer.php` :
```php
<?php
declare(strict_types=1);

namespace App\Services;

use Dom\Comment;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

final class HtmlSanitizer
{
    /** Balises autorisées => attributs autorisés */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'a' => ['href'], 'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'blockquote' => [],
    ];

    /** Balises supprimées avec leur contenu */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body><div id="root">' . $html . '</div></body></html>',
            LIBXML_NOERROR
        );
        $root = $doc->getElementById('root');
        if ($root === null) {
            return '';
        }
        self::walk($root);
        return trim($root->innerHTML);
    }

    /** Texte brut (paragraphes séparés par une ligne vide) => HTML. Laisse intact un texte qui contient déjà des blocs. */
    public static function paragraphs(string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return '';
        }
        if (preg_match('#<(p|ul|ol|h2|h3|blockquote)\b#i', $text)) {
            return $text;
        }
        $parts = preg_split("/\n{2,}/", $text) ?: [];
        return implode("\n", array_map(
            static fn (string $p): string => '<p>' . str_replace("\n", '<br>', trim($p)) . '</p>',
            $parts
        ));
    }

    /** HTML stocké => texte éditable (inverse de paragraphs()). */
    public static function toEditable(string $html): string
    {
        $t = preg_replace('#\s*</p>\s*<p>\s*#i', "\n\n", trim($html)) ?? '';
        $t = preg_replace('#^<p>|</p>$#i', '', $t) ?? '';
        return preg_replace('#<br\s*/?>\n?#i', "\n", $t) ?? '';
    }

    private static function walk(Node $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof Comment) {
                $child->remove();
                continue;
            }
            if (!$child instanceof Element) {
                continue;
            }
            $tag = strtolower($child->localName);
            if (in_array($tag, self::DROP, true)) {
                $child->remove();
                continue;
            }
            self::walk($child);
            if (!array_key_exists($tag, self::ALLOWED)) {
                $child->replaceWith(...iterator_to_array($child->childNodes));
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attr->name);
                } elseif ($name === 'href' && !preg_match('#^(https?://|mailto:|/)#i', trim($attr->value))) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && preg_match('#^https?://#i', (string) $child->getAttribute('href'))) {
                $child->setAttribute('rel', 'noopener');
            }
        }
    }
}
```

- [ ] **Étape 4 : lancer le test pour vérifier qu'il passe**

Commande : `./vendor/bin/phpunit tests/Services/HtmlSanitizerTest.php`
Attendu : `OK (4 tests, 7 assertions)`.

Si `innerHTML` ne sérialise pas `<br>` sous cette forme (par exemple `<br />`), c'est un détail de sérialisation : ajuster les assertions à la forme réellement produite par `Dom\HTMLDocument` sans changer la logique, puis relancer.

- [ ] **Étape 5 : commit**

```bash
git add src/Services/HtmlSanitizer.php tests/Services/HtmlSanitizerTest.php
git commit -m "feat: filtrage HTML en liste blanche pour la page Présentation

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 7 : images de test et ExifReader

**Fichiers :**
- Créer : `tests/fixtures/exif-full.jpg`, `tests/fixtures/exif-rotated.jpg`, `tests/fixtures/no-exif.jpg`, `tests/fixtures/not-a-jpeg.png`
- Créer : `src/Services/ExifReader.php`
- Test : `tests/Services/ExifReaderTest.php`

- [ ] **Étape 1 : générer les images de test**

```bash
which exiftool || brew install exiftool
magick -size 600x400 gradient:'#88aadd-#335533' tests/fixtures/exif-full.jpg
exiftool -n -overwrite_original \
  -DateTimeOriginal='2025:06:14 10:32:05' -Make='NIKON CORPORATION' -Model='NIKON D810' \
  -LensModel='24.0-120.0 mm f/4.0' -FocalLength=35 -FNumber=8 -ExposureTime=0.002 -ISO=200 \
  -GPSLatitude=50.2915 -GPSLatitudeRef=N -GPSLongitude=2.7775 -GPSLongitudeRef=E -Orientation=1 \
  tests/fixtures/exif-full.jpg
magick -size 600x400 xc:'#cc8844' -fill '#224466' -draw 'rectangle 0,0 299,399' tests/fixtures/exif-rotated.jpg
exiftool -n -overwrite_original -Orientation=6 tests/fixtures/exif-rotated.jpg
magick -size 300x200 xc:'#777777' -strip tests/fixtures/no-exif.jpg
magick -size 50x50 xc:red tests/fixtures/not-a-jpeg.png
exiftool -s -Model -LensModel -GPSLatitude -Orientation tests/fixtures/exif-full.jpg tests/fixtures/exif-rotated.jpg
```
Attendu : `exif-full.jpg` affiche Model, LensModel, GPSLatitude et Orientation, et `exif-rotated.jpg` affiche `Orientation : Rotate 90 CW`.

Dans `exif-rotated.jpg`, la moitié gauche est bleue et la moitié droite orange. Une fois l'orientation 6 appliquée, le bleu doit se retrouver **en haut**.

- [ ] **Étape 2 : écrire le test qui échoue**

```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\ExifReader;
use PHPUnit\Framework\TestCase;

final class ExifReaderTest extends TestCase
{
    private const FIX = __DIR__ . '/../fixtures/';

    public function testReadsFullExif(): void
    {
        $exif = (new ExifReader())->read(self::FIX . 'exif-full.jpg');

        self::assertSame('2025-06-14 10:32:05', $exif['taken_at']);
        self::assertSame('NIKON D810', $exif['camera']);
        self::assertSame('24.0-120.0 mm f/4.0', $exif['lens']);
        self::assertSame('35 mm', $exif['focal_length']);
        self::assertSame('f/8', $exif['aperture']);
        self::assertSame('1/500 s', $exif['shutter']);
        self::assertSame(200, $exif['iso']);
        self::assertEqualsWithDelta(50.2915, $exif['lat'], 0.0001);
        self::assertEqualsWithDelta(2.7775, $exif['lng'], 0.0001);
        self::assertSame(1, $exif['orientation']);
    }

    public function testReadsOrientation(): void
    {
        self::assertSame(6, (new ExifReader())->read(self::FIX . 'exif-rotated.jpg')['orientation']);
    }

    public function testNoExifGivesNulls(): void
    {
        $exif = (new ExifReader())->read(self::FIX . 'no-exif.jpg');
        self::assertNull($exif['taken_at']);
        self::assertNull($exif['camera']);
        self::assertNull($exif['lat']);
        self::assertSame(1, $exif['orientation']);
    }

    public function testFormattingHelpers(): void
    {
        self::assertSame('SONY NEX-VG10E', ExifReader::camera('SONY', 'NEX-VG10E'));
        self::assertSame('Canon EOS R5', ExifReader::camera('Canon', 'Canon EOS R5'));
        self::assertSame('2 s', ExifReader::shutter(2.0));
        self::assertSame('1/60 s', ExifReader::shutter(1 / 60));
        self::assertSame('f/5.6', ExifReader::aperture(5.6));
        self::assertEqualsWithDelta(-33.8, ExifReader::gps(['33/1', '48/1', '0/1'], 'S'), 0.0001);
        self::assertNull(ExifReader::gps(['0/1', '0/1', '0/1'], 'N'));
    }
}
```

- [ ] **Étape 3 : lancer le test pour vérifier qu'il échoue**

Commande : `./vendor/bin/phpunit tests/Services/ExifReaderTest.php`
Attendu : ÉCHEC avec `Class "App\Services\ExifReader" not found`.

- [ ] **Étape 4 : écrire l'implémentation**

`src/Services/ExifReader.php` :
```php
<?php
declare(strict_types=1);

namespace App\Services;

final class ExifReader
{
    /**
     * @return array{taken_at: ?string, camera: ?string, lens: ?string, focal_length: ?string,
     *               aperture: ?string, shutter: ?string, iso: ?int, lat: ?float, lng: ?float, orientation: int}
     */
    public function read(string $path): array
    {
        $data = @exif_read_data($path, null, false);
        if (!is_array($data)) {
            $data = [];
        }
        $focal = self::rational($data['FocalLength'] ?? null);
        $fnumber = self::rational($data['FNumber'] ?? null);
        $orientation = (int) ($data['Orientation'] ?? 1);

        return [
            'taken_at' => self::date($data['DateTimeOriginal'] ?? $data['DateTime'] ?? null),
            'camera' => self::camera($data['Make'] ?? null, $data['Model'] ?? null),
            'lens' => self::str($data['LensModel'] ?? $data['UndefinedTag:0xA434'] ?? null),
            'focal_length' => $focal ? (int) round($focal) . ' mm' : null,
            'aperture' => $fnumber ? self::aperture($fnumber) : null,
            'shutter' => self::shutter(self::rational($data['ExposureTime'] ?? null)),
            'iso' => self::iso($data['ISOSpeedRatings'] ?? $data['PhotographicSensitivity'] ?? null),
            'lat' => self::gps($data['GPSLatitude'] ?? null, (string) ($data['GPSLatitudeRef'] ?? 'N')),
            'lng' => self::gps($data['GPSLongitude'] ?? null, (string) ($data['GPSLongitudeRef'] ?? 'E')),
            'orientation' => $orientation >= 1 && $orientation <= 8 ? $orientation : 1,
        ];
    }

    public static function camera(mixed $make, mixed $model): ?string
    {
        $make = self::str($make);
        $model = self::str($model);
        if ($model === null) {
            return $make;
        }
        if ($make === null) {
            return $model;
        }
        $brand = strtolower((string) strtok($make, ' '));
        return str_contains(strtolower($model), $brand) ? $model : $make . ' ' . $model;
    }

    public static function shutter(?float $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }
        return $seconds >= 1 ? self::trimNumber($seconds) . ' s' : '1/' . (int) round(1 / $seconds) . ' s';
    }

    public static function aperture(float $f): string
    {
        return 'f/' . self::trimNumber($f);
    }

    /** @param mixed $parts tableau de 3 rationnels (degrés, minutes, secondes) */
    public static function gps(mixed $parts, string $ref): ?float
    {
        if (!is_array($parts) || count($parts) < 3) {
            return null;
        }
        $deg = self::rational($parts[0]);
        $min = self::rational($parts[1]);
        $sec = self::rational($parts[2]);
        if ($deg === null || $min === null || $sec === null) {
            return null;
        }
        $value = $deg + $min / 60 + $sec / 3600;
        if ($value == 0.0) {
            return null;
        }
        return round(in_array(strtoupper($ref), ['S', 'W'], true) ? -$value : $value, 7);
    }

    private static function rational(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && preg_match('#^(-?\d+(?:\.\d+)?)(?:/(\d+))?$#', trim($value), $m)) {
            if (isset($m[2])) {
                return (int) $m[2] === 0 ? null : (float) $m[1] / (int) $m[2];
            }
            return (float) $m[1];
        }
        return null;
    }

    private static function date(mixed $value): ?string
    {
        $value = self::str($value);
        if ($value === null || str_starts_with($value, '0000')) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $value);
        return $d ? $d->format('Y-m-d H:i:s') : null;
    }

    private static function iso(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function str(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim(str_replace("\0", '', $value));
        return $value === '' ? null : $value;
    }

    private static function trimNumber(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
```

- [ ] **Étape 5 : lancer le test pour vérifier qu'il passe**

Commande : `./vendor/bin/phpunit tests/Services/ExifReaderTest.php`
Attendu : `OK (4 tests, …)`.

Si seule l'assertion `lens` échoue, afficher les clés réellement renvoyées avec `php -r 'print_r(exif_read_data("tests/fixtures/exif-full.jpg"));' | grep -i -B1 -A1 lens`. Ajouter ensuite la clé trouvée dans la chaîne `??` de `'lens'`, puis relancer.

- [ ] **Étape 6 : commit**

```bash
git add tests/fixtures src/Services/ExifReader.php tests/Services/ExifReaderTest.php
git commit -m "feat: lecture des EXIF (prise de vue, GPS, orientation)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 8 : ImageProcessor

**Fichiers :**
- Créer : `src/Services/ImageException.php`, `src/Services/ImageProcessor.php`
- Test : `tests/Services/ImageProcessorTest.php`

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\ImageException;
use App\Services\ImageProcessor;
use PHPUnit\Framework\TestCase;

final class ImageProcessorTest extends TestCase
{
    private const FIX = __DIR__ . '/../fixtures/';
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/img-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testCreatesAllVariantsWithoutUpscaling(): void
    {
        $size = (new ImageProcessor())->process(self::FIX . 'exif-full.jpg', $this->dir, 'abc', 1);

        self::assertSame(['width' => 600, 'height' => 400], $size);
        foreach ([480, 1280, 2560] as $w) {
            self::assertFileExists("{$this->dir}/abc-$w.webp");
            self::assertFileExists("{$this->dir}/abc-$w.jpg");
        }
        self::assertSame([480, 320], array_slice(getimagesize("{$this->dir}/abc-480.jpg"), 0, 2));
        self::assertSame([600, 400], array_slice(getimagesize("{$this->dir}/abc-1280.jpg"), 0, 2));
        self::assertSame(IMAGETYPE_WEBP, getimagesize("{$this->dir}/abc-480.webp")[2]);
    }

    public function testOutputHasNoExif(): void
    {
        (new ImageProcessor())->process(self::FIX . 'exif-full.jpg', $this->dir, 'abc', 1);
        $exif = @exif_read_data("{$this->dir}/abc-1280.jpg") ?: [];
        self::assertArrayNotHasKey('GPSLatitude', $exif);
        self::assertArrayNotHasKey('Model', $exif);
    }

    public function testAppliesOrientation(): void
    {
        $size = (new ImageProcessor())->process(self::FIX . 'exif-rotated.jpg', $this->dir, 'rot', 6);

        self::assertSame(['width' => 400, 'height' => 600], $size);
        $img = imagecreatefromjpeg("{$this->dir}/rot-1280.jpg");
        $top = imagecolorsforindex($img, imagecolorat($img, 200, 100));
        self::assertGreaterThan($top['red'], $top['blue'], 'la moitié bleue doit être en haut après rotation de 90° horaire');
    }

    public function testRejectsNonJpeg(): void
    {
        $this->expectException(ImageException::class);
        (new ImageProcessor())->process(self::FIX . 'not-a-jpeg.png', $this->dir, 'x', 1);
    }

    public function testRejectsTooManyPixels(): void
    {
        $this->expectException(ImageException::class);
        (new ImageProcessor(maxPixels: 1000))->process(self::FIX . 'exif-full.jpg', $this->dir, 'x', 1);
    }

    public function testDeleteVariants(): void
    {
        (new ImageProcessor())->process(self::FIX . 'no-exif.jpg', $this->dir, 'del', 1);
        ImageProcessor::deleteVariants($this->dir, 'del');
        self::assertSame([], glob($this->dir . '/del-*'));
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Commande : `./vendor/bin/phpunit tests/Services/ImageProcessorTest.php`
Attendu : ÉCHEC avec `Class "App\Services\ImageProcessor" not found`.

- [ ] **Étape 3 : écrire l'implémentation**

`src/Services/ImageException.php` :
```php
<?php
declare(strict_types=1);

namespace App\Services;

/** Erreur liée à l'image envoyée, avec un message affichable tel quel. */
final class ImageException extends \RuntimeException
{
}
```

`src/Services/ImageProcessor.php` :
```php
<?php
declare(strict_types=1);

namespace App\Services;

final class ImageProcessor
{
    public const WIDTHS = [480, 1280, 2560];

    public function __construct(
        private int $maxPixels = 60_000_000,
        private int $webpQuality = 82,
        private int $jpegQuality = 85,
    ) {
    }

    /**
     * Crée {key}-{w}.webp et {key}-{w}.jpg dans $destDir pour chaque largeur (sans agrandir).
     * Les fichiers produits ne contiennent aucune métadonnée EXIF.
     *
     * @param list<int> $widths
     * @return array{width: int, height: int} dimensions de l'image une fois orientée
     */
    public function process(string $source, string $destDir, string $key, int $orientation = 1, array $widths = self::WIDTHS): array
    {
        $info = @getimagesize($source);
        if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
            throw new ImageException('Le fichier n\'est pas une image JPEG valide.');
        }
        if ($info[0] * $info[1] > $this->maxPixels) {
            throw new ImageException('Image trop grande (' . intdiv($this->maxPixels, 1_000_000) . ' mégapixels maximum).');
        }
        ini_set('gd.jpeg_ignore_warning', '1');
        $img = @imagecreatefromjpeg($source);
        if ($img === false) {
            throw new ImageException('Impossible de lire l\'image.');
        }
        $img = $this->orient($img, $orientation);
        $w = imagesx($img);
        $h = imagesy($img);

        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new ImageException('Dossier de destination inaccessible.');
        }

        $written = [];
        try {
            foreach ($widths as $target) {
                $tw = min($target, $w);
                $th = max(1, (int) round($h * $tw / $w));
                $dst = imagecreatetruecolor($tw, $th);
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
                $base = $destDir . '/' . $key . '-' . $target;

                if (!imagewebp($dst, $base . '.webp', $this->webpQuality)) {
                    throw new ImageException('Écriture WebP impossible.');
                }
                $written[] = $base . '.webp';

                imageinterlace($dst, true);
                if (!imagejpeg($dst, $base . '.jpg', $this->jpegQuality)) {
                    throw new ImageException('Écriture JPEG impossible.');
                }
                $written[] = $base . '.jpg';
                $dst = null;
            }
        } catch (\Throwable $e) {
            foreach ($written as $file) {
                @unlink($file);
            }
            throw $e;
        }

        return ['width' => $w, 'height' => $h];
    }

    /** @param list<int> $widths */
    public static function deleteVariants(string $dir, string $key, array $widths = self::WIDTHS): void
    {
        foreach ($widths as $w) {
            foreach (['webp', 'jpg'] as $ext) {
                $file = "$dir/$key-$w.$ext";
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function orient(\GdImage $img, int $orientation): \GdImage
    {
        // imagerotate : angle positif = sens anti-horaire
        return match ($orientation) {
            2 => $this->flip($img, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($img, 180, 0),
            4 => $this->flip($img, IMG_FLIP_VERTICAL),
            5 => $this->flip(imagerotate($img, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($img, -90, 0),
            7 => $this->flip(imagerotate($img, 90, 0), IMG_FLIP_HORIZONTAL),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    private function flip(\GdImage $img, int $mode): \GdImage
    {
        imageflip($img, $mode);
        return $img;
    }
}
```

- [ ] **Étape 4 : lancer le test pour vérifier qu'il passe**

Commande : `./vendor/bin/phpunit tests/Services/ImageProcessorTest.php`
Attendu : `OK (6 tests, …)`.

- [ ] **Étape 5 : commit**

```bash
git add src/Services/ImageException.php src/Services/ImageProcessor.php tests/Services/ImageProcessorTest.php
git commit -m "feat: traitement d'image GD (3 tailles WebP/JPEG, orientation)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 9 : Mailer et ContactForm

**Fichiers :**
- Créer : `src/Services/Mailer.php`, `src/Services/ContactForm.php`
- Test : `tests/Services/MailerTest.php`, `tests/Services/ContactFormTest.php`

- [ ] **Étape 1 : écrire les tests qui échouent**

`tests/Services/MailerTest.php` :
```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Mailer;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    public function testBuildsUtf8MessageWithReplyTo(): void
    {
        $msg = Mailer::buildMessage('site@exemple.fr', 'moi@exemple.fr', 'Bonjour é', "Ligne 1\nLigne 2", 'visiteur@exemple.org');

        self::assertStringContainsString("From: guillaumelecomte.net <site@exemple.fr>\r\n", $msg);
        self::assertStringContainsString('Subject: =?UTF-8?B?' . base64_encode('Bonjour é') . '?=', $msg);
        self::assertStringContainsString("Reply-To: visiteur@exemple.org\r\n", $msg);
        [, $body] = explode("\r\n\r\n", $msg, 2);
        self::assertSame("Ligne 1\nLigne 2", base64_decode(str_replace("\r\n", '', $body)));
    }

    public function testRejectsHeaderInjection(): void
    {
        $msg = Mailer::buildMessage('site@exemple.fr', 'moi@exemple.fr', "Sujet\r\nBcc: x@y.z", 'corps', "a@b.c\r\nBcc: x@y.z");
        self::assertStringNotContainsString("\r\nBcc:", $msg);
        self::assertStringNotContainsString('Reply-To:', $msg);
    }
}
```

`tests/Services/ContactFormTest.php` :
```php
<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\ContactForm;
use PHPUnit\Framework\TestCase;

final class ContactFormTest extends TestCase
{
    private const KEY = 'test-key';
    private const NOW = 2_000_000;

    private function input(array $override = []): array
    {
        return $override + [
            'name' => 'Jeanne',
            'email' => 'jeanne@exemple.fr',
            'message' => 'Bonjour, j\'aime beaucoup vos vues du ciel !',
            'website' => '',
            'ts' => ContactForm::token(self::KEY, self::NOW - 30),
        ];
    }

    public function testValidSubmission(): void
    {
        $r = ContactForm::validate($this->input(), self::KEY, self::NOW);
        self::assertSame([], $r['errors']);
        self::assertFalse($r['spam']);
        self::assertSame('Jeanne', $r['data']['name']);
    }

    public function testFieldErrors(): void
    {
        $r = ContactForm::validate($this->input(['name' => '', 'email' => 'pas-un-email', 'message' => 'court']), self::KEY, self::NOW);
        self::assertSame(['name', 'email', 'message'], array_keys($r['errors']));
    }

    public function testSpamSignals(): void
    {
        self::assertTrue(ContactForm::validate($this->input(['website' => 'http://spam']), self::KEY, self::NOW)['spam']);
        self::assertTrue(ContactForm::validate($this->input(['ts' => ContactForm::token(self::KEY, self::NOW - 1)]), self::KEY, self::NOW)['spam']);
        self::assertTrue(ContactForm::validate($this->input(['ts' => ContactForm::token(self::KEY, self::NOW - 90000)]), self::KEY, self::NOW)['spam']);
        self::assertTrue(ContactForm::validate($this->input(['ts' => ContactForm::token('autre-cle', self::NOW - 30)]), self::KEY, self::NOW)['spam']);
        self::assertTrue(ContactForm::validate($this->input(['ts' => 'n importe quoi']), self::KEY, self::NOW)['spam']);
    }
}
```

- [ ] **Étape 2 : lancer les tests pour vérifier qu'ils échouent**

Commande : `./vendor/bin/phpunit tests/Services/MailerTest.php tests/Services/ContactFormTest.php`
Attendu : ÉCHEC avec `Class "App\Services\Mailer" not found`.

- [ ] **Étape 3 : écrire `src/Services/Mailer.php`**

```php
<?php
declare(strict_types=1);

namespace App\Services;

/** Client SMTP minimal (SSL implicite, AUTH LOGIN), adapté au SMTP d'O2switch. */
final class Mailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $pass,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            (string) Env::get('MAIL_HOST', ''),
            (int) Env::get('MAIL_PORT', '465'),
            (string) Env::get('MAIL_USER', ''),
            (string) Env::get('MAIL_PASS', ''),
        );
    }

    public static function buildMessage(string $from, string $to, string $subject, string $body, ?string $replyTo = null): string
    {
        $clean = static fn (string $v): string => str_replace(["\r", "\n"], '', $v);
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: guillaumelecomte.net <' . $clean($from) . '>',
            'To: ' . $clean($to),
            'Subject: =?UTF-8?B?' . base64_encode($clean($subject)) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        return implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
    }

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $socket = @stream_socket_client("ssl://{$this->host}:{$this->port}", $errno, $errstr, 15);
        if ($socket === false) {
            error_log("SMTP : connexion impossible ($errno) $errstr");
            return false;
        }
        stream_set_timeout($socket, 15);
        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO guillaumelecomte.net', 250);
            $this->command($socket, 'AUTH LOGIN', 334);
            $this->command($socket, base64_encode($this->user), 334);
            $this->command($socket, base64_encode($this->pass), 235);
            $this->command($socket, "MAIL FROM:<{$this->user}>", 250);
            $this->command($socket, "RCPT TO:<{$to}>", 250);
            $this->command($socket, 'DATA', 354);
            $message = preg_replace('/^\./m', '..', self::buildMessage($this->user, $to, $subject, $body, $replyTo));
            $this->command($socket, $message . "\r\n.", 250);
            fwrite($socket, "QUIT\r\n");
            return true;
        } catch (\RuntimeException $e) {
            error_log('SMTP : ' . $e->getMessage());
            return false;
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket, string $line, int $expected): void
    {
        fwrite($socket, $line . "\r\n");
        $this->expect($socket, $expected);
    }

    /** @param resource $socket */
    private function expect($socket, int $expected): void
    {
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new \RuntimeException("pas de réponse (attendu $expected)");
            }
        } while (isset($line[3]) && $line[3] === '-');
        if ((int) substr($line, 0, 3) !== $expected) {
            throw new \RuntimeException("attendu $expected, reçu " . trim($line));
        }
    }
}
```

- [ ] **Étape 4 : écrire `src/Services/ContactForm.php`**

```php
<?php
declare(strict_types=1);

namespace App\Services;

final class ContactForm
{
    public const MIN_SECONDS = 3;
    public const MAX_SECONDS = 86400;

    /** Jeton signé contenant l'heure d'affichage du formulaire. */
    public static function token(string $key, int $now): string
    {
        return $now . '.' . hash_hmac('sha256', (string) $now, $key);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{data: array{name: string, email: string, message: string}, errors: array<string, string>, spam: bool}
     */
    public static function validate(array $input, string $key, int $now): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Indiquez votre nom (100 caractères maximum).';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        $length = mb_strlen($message);
        if ($length < 20 || $length > 5000) {
            $errors['message'] = 'Votre message doit contenir entre 20 et 5000 caractères.';
        }

        $spam = trim((string) ($input['website'] ?? '')) !== ''
            || !self::tokenIsValid((string) ($input['ts'] ?? ''), $key, $now);

        return ['data' => ['name' => $name, 'email' => $email, 'message' => $message], 'errors' => $errors, 'spam' => $spam];
    }

    private static function tokenIsValid(string $token, string $key, int $now): bool
    {
        if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $m)) {
            return false;
        }
        if (!hash_equals(hash_hmac('sha256', $m[1], $key), $m[2])) {
            return false;
        }
        $age = $now - (int) $m[1];
        return $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
    }
}
```

- [ ] **Étape 5 : lancer les tests pour vérifier qu'ils passent**

Commande : `composer test`
Attendu : tous les tests sont verts (`OK`).

- [ ] **Étape 6 : commit**

```bash
git add src/Services/Mailer.php src/Services/ContactForm.php tests/Services/MailerTest.php tests/Services/ContactFormTest.php
git commit -m "feat: envoi SMTP et validation anti-spam du formulaire de contact

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 10 : base de données, vues, sessions et outils en ligne de commande

**Fichiers :**
- Créer : `src/Services/Database.php`, `src/Services/View.php`, `src/Services/Csrf.php`, `src/Services/Auth.php`
- Créer : `database/schema.sql`, `database/seed.sql`, `bin/migrate.php`, `bin/create-admin.php`
- Créer : `.env` (local, non commité)

- [ ] **Étape 1 : écrire `src/Services/Database.php`**

```php
<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOStatement;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', Env::get('DB_HOST', 'localhost'), Env::get('DB_NAME', ''));
            self::$pdo = new PDO($dsn, (string) Env::get('DB_USER', ''), (string) Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    /** @param array<int|string, mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** @return list<array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<string, mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $cols)),
            implode(', ', array_map(static fn ($c) => ":$c", $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function update(string $table, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $set = implode(', ', array_map(static fn ($c) => "`$c` = :$c", array_keys($data)));
        self::run("UPDATE `$table` SET $set WHERE id = :__id", $data + ['__id' => $id]);
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
```

- [ ] **Étape 2 : écrire `src/Services/View.php`**

```php
<?php
declare(strict_types=1);

namespace App\Services;

final class View
{
    public function __construct(private string $dir)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->partial($template, $data);
        return $layout === null ? $content : $this->partial($layout, ['content' => $content] + $data);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template introuvable : $template");
        }
        $view = $this;
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
```

- [ ] **Étape 3 : écrire `src/Services/Csrf.php`**

```php
<?php
declare(strict_types=1);

namespace App\Services;

final class Csrf
{
    public static function token(): string
    {
        start_session();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function check(mixed $token): bool
    {
        start_session();
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}
```

- [ ] **Étape 4 : écrire `src/Services/Auth.php`**

```php
<?php
declare(strict_types=1);

namespace App\Services;

final class Auth
{
    public const IDLE_SECONDS = 7200;

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::one('SELECT id, password_hash FROM admin_users WHERE email = ?', [mb_strtolower(trim($email))]);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        start_session();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_seen'] = time();
        Database::run('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        return true;
    }

    /** Ne démarre pas de session pour un visiteur sans cookie (pages publiques sans cookie). */
    public static function check(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[SESSION_NAME])) {
            return false;
        }
        start_session();
        if (empty($_SESSION['admin_id'])) {
            return false;
        }
        if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > self::IDLE_SECONDS) {
            self::logout();
            return false;
        }
        $_SESSION['admin_seen'] = time();
        return true;
    }

    public static function logout(): void
    {
        start_session();
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
```

- [ ] **Étape 5 : écrire `database/schema.sql`**

```sql
CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS themes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(90) NOT NULL UNIQUE,
  title VARCHAR(150) NOT NULL,
  intro TEXT NULL,
  cover_photo_id INT UNSIGNED NULL,
  position INT NOT NULL DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS albums (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  theme_id INT UNSIGNED NOT NULL,
  slug VARCHAR(90) NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  shot_on DATE NULL,
  location VARCHAR(150) NULL,
  cover_photo_id INT UNSIGNED NULL,
  position INT NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_theme_slug (theme_id, slug),
  KEY idx_status_published (status, published_at),
  CONSTRAINT fk_albums_theme FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id INT UNSIGNED NOT NULL,
  file_key CHAR(24) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL DEFAULT '',
  caption TEXT NULL,
  position INT NOT NULL DEFAULT 0,
  width INT UNSIGNED NOT NULL,
  height INT UNSIGNED NOT NULL,
  taken_at DATETIME NULL,
  camera VARCHAR(100) NULL,
  lens VARCHAR(150) NULL,
  focal_length VARCHAR(20) NULL,
  aperture VARCHAR(20) NULL,
  shutter VARCHAR(20) NULL,
  iso INT UNSIGNED NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_album_position (album_id, position),
  CONSTRAINT fk_photos_album FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(90) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  lead VARCHAR(300) NULL,
  body_html MEDIUMTEXT NULL,
  pilot_info TEXT NULL,
  banner_image VARCHAR(60) NULL,
  portrait_image VARCHAR(60) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(60) NOT NULL PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Étape 6 : écrire `database/seed.sql`** (texte provisoire de la page Présentation, à remplacer dans l'administration)

```sql
INSERT IGNORE INTO pages (slug, title, lead, body_html, pilot_info) VALUES (
  'presentation',
  'Présentation',
  'Photographe amateur et pilote ULM, curieux de tout ce qui se voit d''en haut.',
  '<p>Texte de présentation à rédiger depuis l''administration (Contenus → Page Présentation).</p>',
  'ULM : à compléter\nTerrain de base : à compléter\nHeures de vol : à compléter'
);

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('hero_photo_id', NULL),
  ('site_tagline', 'Photographe & pilote ULM'),
  ('sky_intro', 'Depuis le cockpit, les paysages se dévoilent autrement : des lignes, des reliefs, des lumières rasantes. Une sélection de mes vues du ciel.');
```

Note : dans MySQL, `\n` à l'intérieur d'une chaîne entre apostrophes donne un vrai retour à la ligne. C'est voulu ici, pour l'encart pilote.

- [ ] **Étape 7 : écrire `bin/migrate.php`**

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\Database;

foreach (['storage/logs', 'storage/ratelimit', 'public/media', 'public/assets/img/presentation'] as $dir) {
    if (!is_dir(ROOT . '/' . $dir)) {
        mkdir(ROOT . '/' . $dir, 0775, true);
    }
}

foreach (['schema.sql', 'seed.sql'] as $file) {
    $sql = (string) file_get_contents(ROOT . '/database/' . $file);
    foreach (preg_split('/;\s*\n/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            Database::pdo()->exec($statement);
        }
    }
    echo "OK $file\n";
}
```

- [ ] **Étape 8 : écrire `bin/create-admin.php`**

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\Database;

$email = mb_strtolower(trim($argv[1] ?? ''));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage : php bin/create-admin.php adresse@email\n");
    exit(1);
}

fwrite(STDOUT, 'Mot de passe (12 caractères minimum) : ');
shell_exec('stty -echo');
$password = trim((string) fgets(STDIN));
shell_exec('stty echo');
fwrite(STDOUT, "\n");

if (mb_strlen($password) < 12) {
    fwrite(STDERR, "Mot de passe trop court.\n");
    exit(1);
}

Database::run(
    'INSERT INTO admin_users (email, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)',
    [$email, password_hash($password, PASSWORD_DEFAULT)]
);
echo "Compte administrateur prêt : $email\n";
```

- [ ] **Étape 9 : préparer MySQL en local et migrer**

```bash
brew services start mysql
mysql -u root -e "CREATE DATABASE IF NOT EXISTS portfolio_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cp .env.example .env
php -r '$f=".env"; file_put_contents($f, str_replace("APP_KEY=", "APP_KEY=".bin2hex(random_bytes(32)), file_get_contents($f)));'
php bin/migrate.php
mysql -u root portfolio_local -e "SHOW TABLES; SELECT slug, title FROM pages; SELECT * FROM settings;"
```
Attendu : `OK schema.sql`, `OK seed.sql`, 6 tables, une ligne `presentation` et 3 réglages.

Si `root` a un mot de passe, renseigner `DB_PASS` dans `.env`.

- [ ] **Étape 10 : créer le compte admin local** (étape interactive, à lancer par Guillaume)

```bash
php bin/create-admin.php <ton-email>
```
Attendu : `Compte administrateur prêt : …`.

- [ ] **Étape 11 : commit**

```bash
git add src/Services/Database.php src/Services/View.php src/Services/Csrf.php src/Services/Auth.php database bin/migrate.php bin/create-admin.php
git commit -m "feat: base de données, vues, CSRF, authentification et migrations

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 11 : modèles

**Fichiers :**
- Créer : `src/Models/Theme.php`, `src/Models/Album.php`, `src/Models/Photo.php`, `src/Models/Page.php`, `src/Models/Setting.php`

Ces modèles sont vérifiés par la recette manuelle (tâches 14 à 20) : ils dépendent de MySQL.

- [ ] **Étape 1 : écrire `src/Models/Theme.php`**

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\Slug;

final class Theme
{
    private const SELECT = "SELECT t.*,
        COALESCE(
          (SELECT p.file_key FROM photos p WHERE p.id = t.cover_photo_id),
          (SELECT p.file_key FROM photos p JOIN albums a ON a.id = p.album_id
            WHERE a.theme_id = t.id AND a.status = 'published'
            ORDER BY a.position, a.id, p.position, p.id LIMIT 1)
        ) AS cover_key,
        (SELECT COUNT(*) FROM albums a WHERE a.theme_id = t.id AND a.status = 'published') AS published_albums,
        (SELECT COUNT(*) FROM albums a WHERE a.theme_id = t.id) AS total_albums
        FROM themes t";

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Database::all(self::SELECT . ' ORDER BY t.position, t.id');
    }

    /** Thèmes ayant au moins un album publié. @return list<array<string, mixed>> */
    public static function visible(): array
    {
        return Database::all('SELECT * FROM (' . self::SELECT . ') x WHERE x.published_albums > 0 ORDER BY x.position, x.id');
    }

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE t.id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::one(self::SELECT . ' WHERE t.slug = ?', [$slug]);
    }

    public static function featured(): ?array
    {
        return Database::one(self::SELECT . ' WHERE t.featured = 1 LIMIT 1');
    }

    /** @param array{title: string, intro: string, featured: bool} $data */
    public static function create(array $data): int
    {
        return Database::transaction(static function () use ($data): int {
            if ($data['featured']) {
                Database::run('UPDATE themes SET featured = 0');
            }
            return Database::insert('themes', [
                'slug' => self::uniqueSlug($data['title']),
                'title' => $data['title'],
                'intro' => $data['intro'] !== '' ? $data['intro'] : null,
                'featured' => $data['featured'] ? 1 : 0,
                'position' => (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM themes'),
            ]);
        });
    }

    /** @param array{title: string, slug: string, intro: string, featured: bool} $data */
    public static function update(int $id, array $data): void
    {
        Database::transaction(static function () use ($id, $data): void {
            if ($data['featured']) {
                Database::run('UPDATE themes SET featured = 0 WHERE id <> ?', [$id]);
            }
            Database::update('themes', $id, [
                'title' => $data['title'],
                'slug' => self::uniqueSlug($data['slug'] !== '' ? $data['slug'] : $data['title'], $id),
                'intro' => $data['intro'] !== '' ? $data['intro'] : null,
                'featured' => $data['featured'] ? 1 : 0,
            ]);
        });
    }

    public static function delete(int $id): void
    {
        if ((int) Database::value('SELECT COUNT(*) FROM albums WHERE theme_id = ?', [$id]) > 0) {
            throw new \DomainException('Ce thème contient encore des albums : déplacez-les ou supprimez-les d\'abord.');
        }
        Database::run('DELETE FROM themes WHERE id = ?', [$id]);
    }

    /** @param list<int|string> $ids */
    public static function reorder(array $ids): void
    {
        Database::transaction(static function () use ($ids): void {
            foreach (array_values($ids) as $i => $id) {
                Database::run('UPDATE themes SET position = ? WHERE id = ?', [$i + 1, (int) $id]);
            }
        });
    }

    public static function setCover(int $id, int $photoId): void
    {
        Database::run('UPDATE themes SET cover_photo_id = ? WHERE id = ?', [$photoId, $id]);
    }

    public static function uniqueSlug(string $text, ?int $exceptId = null): string
    {
        return Slug::unique(
            $text,
            static fn (string $s): bool => (bool) Database::value('SELECT 1 FROM themes WHERE slug = ? AND id <> ?', [$s, $exceptId ?? 0]),
            true
        );
    }
}
```

- [ ] **Étape 2 : écrire `src/Models/Album.php`**

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\Slug;

final class Album
{
    private const SELECT = "SELECT a.*, t.slug AS theme_slug, t.title AS theme_title,
        COALESCE(
          (SELECT p.file_key FROM photos p WHERE p.id = a.cover_photo_id),
          (SELECT p.file_key FROM photos p WHERE p.album_id = a.id ORDER BY p.position, p.id LIMIT 1)
        ) AS cover_key,
        (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) AS photo_count
        FROM albums a JOIN themes t ON t.id = a.theme_id";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE a.id = ?', [$id]);
    }

    public static function findBySlugs(string $themeSlug, string $albumSlug, bool $includeDrafts = false): ?array
    {
        $sql = self::SELECT . ' WHERE t.slug = ? AND a.slug = ?' . ($includeDrafts ? '' : " AND a.status = 'published'");
        return Database::one($sql, [$themeSlug, $albumSlug]);
    }

    /** @return list<array<string, mixed>> */
    public static function forTheme(int $themeId, bool $publishedOnly = true): array
    {
        $sql = self::SELECT . ' WHERE a.theme_id = ?' . ($publishedOnly ? " AND a.status = 'published'" : '') . ' ORDER BY a.position, a.id';
        return Database::all($sql, [$themeId]);
    }

    /** @return list<array<string, mixed>> */
    public static function latestPublished(int $limit = 6): array
    {
        return Database::all(self::SELECT . " WHERE a.status = 'published' ORDER BY a.published_at DESC, a.id DESC LIMIT " . (int) $limit);
    }

    /** @return list<array<string, mixed>> */
    public static function allPublished(): array
    {
        return Database::all(self::SELECT . " WHERE a.status = 'published' ORDER BY t.position, a.position, a.id");
    }

    /** @return list<array<string, mixed>> */
    public static function allForAdmin(): array
    {
        return Database::all(self::SELECT . ' ORDER BY t.position, t.id, a.position, a.id');
    }

    /** @return list<array<string, mixed>> */
    public static function recentForAdmin(int $limit = 8): array
    {
        return Database::all(self::SELECT . ' ORDER BY a.updated_at DESC, a.id DESC LIMIT ' . (int) $limit);
    }

    /** @param array{theme_id: int, title: string, slug: string, description: string, shot_on: string, location: string, status: string} $data */
    public static function create(array $data): int
    {
        $published = $data['status'] === 'published';
        return Database::insert('albums', [
            'theme_id' => $data['theme_id'],
            'slug' => self::uniqueSlug($data['theme_id'], $data['title']),
            'title' => $data['title'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'shot_on' => $data['shot_on'] !== '' ? $data['shot_on'] : null,
            'location' => $data['location'] !== '' ? $data['location'] : null,
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? date('Y-m-d H:i:s') : null,
            'position' => (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM albums WHERE theme_id = ?', [$data['theme_id']]),
        ]);
    }

    /** @param array{theme_id: int, title: string, slug: string, description: string, shot_on: string, location: string, status: string} $data */
    public static function update(int $id, array $data): void
    {
        $current = self::find($id) ?? throw new \DomainException('Album introuvable.');
        $fields = [
            'theme_id' => $data['theme_id'],
            'title' => $data['title'],
            'slug' => self::uniqueSlug($data['theme_id'], $data['slug'] !== '' ? $data['slug'] : $data['title'], $id),
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'shot_on' => $data['shot_on'] !== '' ? $data['shot_on'] : null,
            'location' => $data['location'] !== '' ? $data['location'] : null,
            'status' => $data['status'] === 'published' ? 'published' : 'draft',
        ];
        if ($fields['status'] === 'published' && $current['published_at'] === null) {
            $fields['published_at'] = date('Y-m-d H:i:s');
        }
        Database::update('albums', $id, $fields);
    }

    /** Supprime l'album et ses photos en base. @return list<string> clés des fichiers à supprimer */
    public static function delete(int $id): array
    {
        $keys = array_column(Database::all('SELECT file_key FROM photos WHERE album_id = ?', [$id]), 'file_key');
        Database::run('DELETE FROM albums WHERE id = ?', [$id]);
        return $keys;
    }

    /** @param list<int|string> $ids */
    public static function reorder(array $ids): void
    {
        Database::transaction(static function () use ($ids): void {
            foreach (array_values($ids) as $i => $id) {
                Database::run('UPDATE albums SET position = ? WHERE id = ?', [$i + 1, (int) $id]);
            }
        });
    }

    public static function setCover(int $albumId, ?int $photoId): void
    {
        Database::run('UPDATE albums SET cover_photo_id = ? WHERE id = ?', [$photoId, $albumId]);
    }

    public static function touch(int $albumId): void
    {
        Database::run('UPDATE albums SET updated_at = NOW() WHERE id = ?', [$albumId]);
    }

    public static function uniqueSlug(int $themeId, string $text, ?int $exceptId = null): string
    {
        return Slug::unique(
            $text,
            static fn (string $s): bool => (bool) Database::value(
                'SELECT 1 FROM albums WHERE theme_id = ? AND slug = ? AND id <> ?',
                [$themeId, $s, $exceptId ?? 0]
            )
        );
    }
}
```

- [ ] **Étape 3 : écrire `src/Models/Photo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Photo
{
    private const SELECT = 'SELECT p.*, a.slug AS album_slug, a.title AS album_title, a.status AS album_status,
        a.theme_id, t.slug AS theme_slug
        FROM photos p JOIN albums a ON a.id = p.album_id JOIN themes t ON t.id = a.theme_id';

    private const EDITABLE = ['title', 'caption', 'lat', 'lng'];

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE p.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public static function forAlbum(int $albumId): array
    {
        return Database::all(self::SELECT . ' WHERE p.album_id = ? ORDER BY p.position, p.id', [$albumId]);
    }

    /** @param array<string, mixed> $data colonnes de la table photos (hors id/position) */
    public static function create(array $data): int
    {
        $data['position'] = (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM photos WHERE album_id = ?', [$data['album_id']]);
        $id = Database::insert('photos', $data);
        Album::touch((int) $data['album_id']);
        return $id;
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('photos', $id, array_intersect_key($data, array_flip(self::EDITABLE)));
    }

    /** @return string|null clé des fichiers à supprimer */
    public static function delete(int $id): ?string
    {
        $photo = self::find($id);
        if ($photo === null) {
            return null;
        }
        Database::run('UPDATE albums SET cover_photo_id = NULL WHERE cover_photo_id = ?', [$id]);
        Database::run('UPDATE themes SET cover_photo_id = NULL WHERE cover_photo_id = ?', [$id]);
        Database::run('DELETE FROM photos WHERE id = ?', [$id]);
        return (string) $photo['file_key'];
    }

    /** @param list<int|string> $ids */
    public static function reorder(int $albumId, array $ids): void
    {
        Database::transaction(static function () use ($albumId, $ids): void {
            foreach (array_values($ids) as $i => $id) {
                Database::run('UPDATE photos SET position = ? WHERE id = ? AND album_id = ?', [$i + 1, (int) $id, $albumId]);
            }
        });
    }

    public static function move(int $id, int $toAlbumId): void
    {
        Database::transaction(static function () use ($id, $toAlbumId): void {
            Database::run('UPDATE albums SET cover_photo_id = NULL WHERE cover_photo_id = ?', [$id]);
            $position = (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM photos WHERE album_id = ?', [$toAlbumId]);
            Database::run('UPDATE photos SET album_id = ?, position = ? WHERE id = ?', [$toAlbumId, $position, $id]);
        });
    }

    /** Photos géolocalisées des albums publiés. @return list<array<string, mixed>> */
    public static function geotagged(): array
    {
        return Database::all(self::SELECT . " WHERE a.status = 'published' AND p.lat IS NOT NULL AND p.lng IS NOT NULL ORDER BY p.id");
    }

    /** @return list<array<string, mixed>> */
    public static function latestInTheme(int $themeId, int $limit): array
    {
        return Database::all(
            self::SELECT . " WHERE a.theme_id = ? AND a.status = 'published' ORDER BY a.published_at DESC, p.position, p.id LIMIT " . (int) $limit,
            [$themeId]
        );
    }
}
```

- [ ] **Étape 4 : écrire `src/Models/Page.php` et `src/Models/Setting.php`**

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Page
{
    private const EDITABLE = ['title', 'lead', 'body_html', 'pilot_info', 'banner_image', 'portrait_image'];

    public static function find(string $slug): ?array
    {
        return Database::one('SELECT * FROM pages WHERE slug = ?', [$slug]);
    }

    /** @param array<string, mixed> $data */
    public static function update(string $slug, array $data): void
    {
        $fields = array_intersect_key($data, array_flip(self::EDITABLE));
        if ($fields === []) {
            return;
        }
        $set = implode(', ', array_map(static fn ($c) => "`$c` = :$c", array_keys($fields)));
        Database::run("UPDATE pages SET $set WHERE slug = :__slug", $fields + ['__slug' => $slug]);
    }
}
```

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Setting
{
    /** @var array<string, ?string>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::$cache ??= array_column(Database::all('SELECT `key`, `value` FROM settings'), 'value', 'key');
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value]
        );
        self::$cache = null;
    }
}
```

- [ ] **Étape 5 : vérification rapide en ligne de commande**

```bash
php -r 'require "src/bootstrap.php"; $id = App\Models\Theme::create(["title"=>"Carte","intro"=>"","featured"=>true]); $t = App\Models\Theme::find($id); echo $t["slug"], " ", $t["featured"], "\n"; App\Models\Theme::delete($id); echo App\Models\Setting::get("site_tagline"), "\n";'
```
Attendu : `carte-2 1` (le slug réservé est évité), puis `Photographe & pilote ULM`.

- [ ] **Étape 6 : commit**

```bash
git add src/Models
git commit -m "feat: modèles Theme, Album, Photo, Page, Setting

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 12 : contrôleur frontal, mise en page publique et feuille de style

**Fichiers :**
- Créer : `public/index.php`, `public/.htaccess`, `public/.user.ini`, `public/assets/img/logo.png`
- Créer : `src/routes.php`, `src/Controllers/Controller.php`, `src/Controllers/PublicController.php` (version initiale)
- Créer : `templates/layout.php`, `templates/partials/header.php`, `templates/partials/footer.php`, `templates/partials/trace.php`, `templates/partials/picture.php`, `templates/partials/album-card.php`, `templates/public/error.php`, `templates/public/home.php` (version initiale)
- Créer : `public/assets/css/site.css`

- [ ] **Étape 1 : préparer le logo** (signature détourée, 1400 px de large)

```bash
magick "/Users/guillaumelecomte/Library/Mobile Documents/com~apple~CloudDocs/Documents/Signatures Photos AI/Guillaume-Lecomte-black-hiRes.png" -trim +repage -resize 1400x public/assets/img/logo.png
magick identify public/assets/img/logo.png
```
Attendu : `PNG 1400x384`.

- [ ] **Étape 2 : écrire `public/index.php`**

```php
<?php
declare(strict_types=1);

// Serveur PHP intégré (local) : servir les fichiers statiques directement
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\Env;
use App\Services\View;

$view = new View(ROOT . '/templates');

if (Env::get('APP_ENV') !== 'prod') {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

set_exception_handler(static function (\Throwable $e) use ($view): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (Env::get('APP_DEBUG') === '1') {
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    try {
        echo $view->render('public/error', ['meta' => ['title' => 'Erreur'], 'code' => 500, 'message' => 'Une erreur est survenue. Réessayez dans un instant.']);
    } catch (\Throwable) {
        echo 'Une erreur est survenue.';
    }
});

/** @var App\Services\Router $router */
$router = require ROOT . '/src/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', static function () use ($view): void {
    http_response_code(404);
    echo $view->render('public/error', ['meta' => ['title' => 'Page introuvable'], 'code' => 404, 'message' => 'Cette page n\'existe pas ou plus.']);
});
```

- [ ] **Étape 3 : écrire `public/.htaccess` et `public/.user.ini`**

`public/.htaccess` :
```apache
Options -Indexes
DirectoryIndex index.php
RewriteEngine On

# Validation des certificats Let's Encrypt
RewriteRule ^\.well-known/ - [L]

# Tout ce qui n'est pas un fichier existant passe par index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L,QSA]

# Fichiers cachés (.env, .user.ini…) jamais servis
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set X-Frame-Options "SAMEORIGIN"
    <FilesMatch "\.(css|js|webp|jpg|jpeg|png|svg|woff2)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
</IfModule>
```

`public/.user.ini` :
```ini
upload_max_filesize = 48M
post_max_size = 50M
memory_limit = 512M
max_execution_time = 120
```

- [ ] **Étape 4 : écrire `src/Controllers/Controller.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\View;

abstract class Controller
{
    protected const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    protected const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    protected const CLUSTER_CSS = 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css';
    protected const CLUSTER_DEFAULT_CSS = 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css';
    protected const CLUSTER_JS = 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js';

    public function __construct(protected View $view)
    {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        echo $this->view->render($template, $data);
    }

    protected function notFound(): void
    {
        $this->render('public/error', ['meta' => ['title' => 'Page introuvable'], 'code' => 404, 'message' => 'Cette page n\'existe pas ou plus.'], 404);
    }
}
```

- [ ] **Étape 5 : écrire `src/Controllers/PublicController.php`** (version initiale : l'accueil seul, complété aux tâches 17 à 20)

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Theme;

final class PublicController extends Controller
{
    public function home(): void
    {
        $featured = Theme::featured();
        if ($featured !== null && (int) $featured['published_albums'] === 0) {
            $featured = null;
        }
        $heroId = (int) Setting::get('hero_photo_id', '0');
        $hero = $heroId > 0 ? Photo::find($heroId) : null;
        if ($hero !== null && $hero['album_status'] !== 'published') {
            $hero = null;
        }

        $this->render('public/home', [
            'meta' => ['image' => $hero ? media_path((string) $hero['file_key'], 1280, 'jpg') : null],
            'hero' => $hero,
            'tagline' => (string) Setting::get('site_tagline', 'Photographe & pilote ULM'),
            'featured' => $featured,
            'skyPhotos' => $featured ? Photo::latestInTheme((int) $featured['id'], 5) : [],
            'skyIntro' => (string) Setting::get('sky_intro', ''),
            'latest' => Album::latestPublished(6),
            'themes' => Theme::visible(),
        ]);
    }
}
```

- [ ] **Étape 6 : écrire `src/routes.php`** (version initiale, complétée dans les tâches suivantes)

```php
<?php
declare(strict_types=1);

use App\Controllers\PublicController;
use App\Services\Router;
use App\Services\View;

/** @var View $view */
$router = new Router();
$public = static fn (): PublicController => new PublicController($view);

// --- Public ---
$router->get('/', static fn () => $public()->home());

return $router;
```

- [ ] **Étape 7 : écrire les partials**

`templates/partials/trace.php` :
```php
<svg class="trace" viewBox="0 0 520 24" fill="none" aria-hidden="true">
  <path d="M2 18 C 120 18, 170 4, 260 6 S 420 20, 518 8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
</svg>
```

`templates/partials/picture.php` :
```php
<?php
/**
 * @var string $key
 * @var ?string $alt
 * @var ?string $sizes
 * @var ?string $class
 * @var ?string $loading
 * @var int|string|null $width
 * @var int|string|null $height
 */
$sizes ??= '100vw';
$srcset = static fn (string $ext): string => implode(', ', array_map(
    static fn (int $w): string => media_url($key, $w, $ext) . ' ' . $w . 'w',
    [480, 1280, 2560]
));
?>
<picture>
  <source type="image/webp" srcset="<?= e($srcset('webp')) ?>" sizes="<?= e($sizes) ?>">
  <img src="<?= e(media_url($key, 1280, 'jpg')) ?>" srcset="<?= e($srcset('jpg')) ?>" sizes="<?= e($sizes) ?>"
       alt="<?= e($alt ?? '') ?>"<?php if (!empty($width) && !empty($height)): ?> width="<?= (int) $width ?>" height="<?= (int) $height ?>"<?php endif; ?>
       loading="<?= e($loading ?? 'lazy') ?>" decoding="async" class="<?= e($class ?? '') ?>">
</picture>
```

`templates/partials/album-card.php` :
```php
<?php /** @var array<string, mixed> $album */ ?>
<a class="album-card" href="/<?= e($album['theme_slug']) ?>/<?= e($album['slug']) ?>">
  <div class="album-card__img">
    <?php if (!empty($album['cover_key'])): ?>
      <?= $view->partial('partials/picture', ['key' => $album['cover_key'], 'alt' => '', 'sizes' => '(min-width: 900px) 30vw, (min-width: 600px) 45vw, 100vw']) ?>
    <?php endif; ?>
  </div>
  <h3 class="album-card__title"><?= e($album['title']) ?></h3>
  <p class="album-card__meta"><?= e(implode(' · ', array_filter([(string) $album['location'], format_month_fr($album['shot_on'])]))) ?></p>
</a>
```

`templates/partials/header.php` :
```php
<?php
$current = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$links = ['/#galeries' => 'Galeries', '/carte' => 'Carte', '/presentation' => 'Présentation', '/contact' => 'Contact'];
?>
<header class="site-header">
  <a class="site-header__logo" href="/" aria-label="Accueil — Guillaume Lecomte">
    <img src="<?= e(asset('img/logo.png')) ?>" width="1400" height="384" alt="Guillaume Lecomte — Photographe">
  </a>
  <nav class="site-nav" aria-label="Navigation principale">
    <?php foreach ($links as $href => $label): ?>
      <a href="<?= e($href) ?>"<?= $current === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
```

`templates/partials/footer.php` :
```php
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
```

- [ ] **Étape 8 : écrire `templates/layout.php`**

```php
<?php
/** @var string $content */
$meta = ($meta ?? []) + ['title' => null, 'description' => null, 'image' => null, 'type' => 'website', 'jsonld' => null];
$siteName = 'Guillaume Lecomte — Photographe';
$fullTitle = $meta['title'] ? $meta['title'] . ' · Guillaume Lecomte' : $siteName;
$description = $meta['description'] ?: 'Portfolio de Guillaume Lecomte, photographe amateur et pilote ULM : paysages, patrimoine, aviation et vues du ciel.';
$canonical = absolute_url((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="<?= e($meta['type']) ?>">
<meta property="og:title" content="<?= e($meta['title'] ?? $siteName) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($meta['image']): ?>
<meta property="og:image" content="<?= e(absolute_url($meta['image'])) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<meta name="theme-color" content="#f4f1ec">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php foreach ($styles ?? [] as $href): ?>
<link rel="stylesheet" href="<?= e($href) ?>">
<?php endforeach; ?>
<?php if ($meta['jsonld']): ?>
<script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body>
<?= $view->partial('partials/header') ?>
<main id="contenu">
<?= $content ?>
</main>
<?= $view->partial('partials/footer') ?>
<?php foreach ($scripts ?? [] as $src): ?>
<script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
```

- [ ] **Étape 9 : écrire `templates/public/error.php` et `templates/public/home.php`**

`templates/public/error.php` :
```php
<?php /** @var int $code @var string $message */ ?>
<section class="error-page">
  <p class="error-page__code"><?= (int) $code ?></p>
  <h1 class="page-title"><?= e($message) ?></h1>
  <p><a class="link-arrow" href="/">Retour à l'accueil</a></p>
</section>
```

`templates/public/home.php` :
```php
<?php
/**
 * @var ?array $hero
 * @var string $tagline
 * @var ?array $featured
 * @var list<array> $skyPhotos
 * @var string $skyIntro
 * @var list<array> $latest
 * @var list<array> $themes
 */
?>
<?php if ($hero): ?>
<section class="hero">
  <?= $view->partial('partials/picture', ['key' => $hero['file_key'], 'alt' => $hero['title'], 'sizes' => '100vw', 'loading' => 'eager', 'class' => 'hero__img']) ?>
</section>
<?php endif; ?>

<section class="intro">
  <h1 class="visually-hidden">Guillaume Lecomte — Photographe &amp; pilote ULM</h1>
  <p class="intro__tagline"><?= e($tagline) ?></p>
  <?= $view->partial('partials/trace') ?>
</section>

<?php if ($featured): ?>
<section class="sky" id="galeries">
  <div class="sky__text">
    <p class="eyebrow">Depuis le cockpit</p>
    <h2 class="section-title"><?= e($featured['title']) ?></h2>
    <?php if ($skyIntro !== ''): ?><p><?= nl2br(e($skyIntro)) ?></p><?php endif; ?>
    <a class="link-arrow" href="/<?= e($featured['slug']) ?>">Voir la galerie</a>
  </div>
  <div class="sky__mosaic">
    <?php foreach ($skyPhotos as $p): ?>
      <a href="/<?= e($p['theme_slug']) ?>/<?= e($p['album_slug']) ?>/photo/<?= (int) $p['id'] ?>">
        <?= $view->partial('partials/picture', ['key' => $p['file_key'], 'alt' => $p['title'], 'sizes' => '(min-width: 900px) 30vw, 50vw']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($latest): ?>
<section class="section">
  <h2 class="section-title">Derniers albums</h2>
  <div class="card-grid">
    <?php foreach ($latest as $album): ?>
      <?= $view->partial('partials/album-card', ['album' => $album]) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($themes): ?>
<section class="section"<?= $featured ? '' : ' id="galeries"' ?>>
  <h2 class="section-title">Galeries</h2>
  <div class="theme-grid">
    <?php foreach ($themes as $theme): ?>
      <a class="theme-card" href="/<?= e($theme['slug']) ?>">
        <?php if (!empty($theme['cover_key'])): ?>
          <?= $view->partial('partials/picture', ['key' => $theme['cover_key'], 'alt' => '', 'sizes' => '(min-width: 900px) 45vw, 100vw']) ?>
        <?php endif; ?>
        <span class="theme-card__title"><?= e($theme['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!$hero && !$featured && !$latest && !$themes): ?>
<section class="section empty" id="galeries">
  <p>Les premières galeries arrivent bientôt.</p>
</section>
<?php endif; ?>
```

- [ ] **Étape 10 : écrire `public/assets/css/site.css`**

```css
/* Portfolio Guillaume Lecomte — style « papier clair » */
:root {
  --paper: #f4f1ec;
  --paper-deep: #ebe6de;
  --ink: #1b1b1a;
  --muted: #7a756d;
  --line: #d9d3c9;
  --danger: #9b2c1f;
  --serif: 'Cormorant Garamond', Georgia, serif;
  --sans: 'Inter', system-ui, -apple-system, sans-serif;
  --gutter: clamp(16px, 4vw, 48px);
  --max: 1280px;
}

*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  margin: 0;
  background: var(--paper);
  color: var(--ink);
  font-family: var(--sans);
  font-size: 16px;
  line-height: 1.65;
  -webkit-font-smoothing: antialiased;
}
img { max-width: 100%; height: auto; display: block; }
picture { display: block; }
a { color: inherit; }
:focus-visible { outline: 2px solid var(--ink); outline-offset: 3px; }
.visually-hidden {
  position: absolute !important; width: 1px; height: 1px; overflow: hidden;
  clip: rect(0 0 0 0); white-space: nowrap;
}

/* En-tête */
.site-header {
  display: flex; align-items: center; justify-content: space-between; gap: 24px;
  max-width: var(--max); margin: 0 auto; padding: 20px var(--gutter);
}
.site-header__logo img { width: clamp(150px, 22vw, 230px); }
.site-nav { display: flex; gap: clamp(14px, 2.5vw, 32px); font-size: .78rem; letter-spacing: .16em; text-transform: uppercase; }
.site-nav a { text-decoration: none; padding: 6px 0; border-bottom: 1px solid transparent; }
.site-nav a:hover, .site-nav a[aria-current="page"] { border-color: var(--ink); }
@media (max-width: 640px) {
  .site-header { flex-direction: column; gap: 10px; }
  .site-nav { gap: 16px; font-size: .7rem; letter-spacing: .12em; }
}

/* Trace en pointillés */
.trace { display: block; width: min(420px, 70%); height: 20px; margin: 22px auto 0; color: var(--muted); }
.trace path { stroke-dasharray: 4 7; }

/* Typographie */
.eyebrow { margin: 0 0 8px; font-size: .72rem; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); }
.eyebrow a { text-decoration: none; }
.page-title, .section-title { margin: 0; font-family: var(--serif); font-style: italic; font-weight: 400; line-height: 1.15; }
.page-title { font-size: clamp(2.2rem, 5vw, 3.6rem); }
.section-title { margin-bottom: 28px; font-size: clamp(1.8rem, 3.5vw, 2.6rem); }
.page-meta { margin: 10px 0 0; color: var(--muted); font-size: .85rem; letter-spacing: .08em; }
.page-intro { max-width: 40em; margin: 18px auto 0; color: var(--muted); }
.lead { max-width: 30em; margin: 18px auto 0; font-family: var(--serif); font-style: italic; font-size: clamp(1.3rem, 2.4vw, 1.7rem); line-height: 1.4; }
.link-arrow {
  display: inline-block; padding-bottom: 3px; border-bottom: 1px solid var(--ink);
  font-size: .78rem; letter-spacing: .16em; text-transform: uppercase; text-decoration: none;
}
.link-arrow::after { content: ' →'; }

/* Mise en page */
.section { max-width: var(--max); margin: 0 auto; padding: clamp(48px, 8vw, 96px) var(--gutter) 0; }
.section.narrow { max-width: 640px; }
.page-header { max-width: 900px; margin: 0 auto; padding: clamp(40px, 7vw, 80px) var(--gutter) 0; text-align: center; }
.empty { text-align: center; color: var(--muted); }

/* Accueil */
.hero__img { width: 100%; height: min(80vh, 900px); object-fit: cover; }
.intro { padding: 40px var(--gutter) 0; text-align: center; }
.intro__tagline { margin: 0; font-family: var(--serif); font-style: italic; font-size: clamp(1.4rem, 3vw, 2rem); }
.sky {
  display: grid; grid-template-columns: minmax(260px, 1fr) 2fr; gap: clamp(28px, 5vw, 64px); align-items: center;
  max-width: var(--max); margin: 0 auto; padding: clamp(56px, 9vw, 110px) var(--gutter) 0;
}
.sky__text p { color: var(--muted); }
.sky__mosaic { display: grid; grid-template-columns: repeat(6, 1fr); grid-auto-rows: clamp(110px, 14vw, 190px); gap: 8px; }
.sky__mosaic a { overflow: hidden; background: var(--paper-deep); }
.sky__mosaic a:nth-child(1) { grid-column: span 4; grid-row: span 2; }
.sky__mosaic a:nth-child(2), .sky__mosaic a:nth-child(3) { grid-column: span 2; }
.sky__mosaic a:nth-child(4), .sky__mosaic a:nth-child(5) { grid-column: span 3; }
.sky__mosaic picture, .album-card__img picture, .theme-card picture { height: 100%; }
.sky__mosaic img, .album-card__img img, .theme-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease; }
.sky__mosaic a:hover img, .album-card:hover img, .theme-card:hover img { transform: scale(1.03); }
@media (max-width: 860px) { .sky { grid-template-columns: 1fr; } }

/* Cartes d'albums et de thèmes */
.card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 40px 28px; }
.album-card { display: block; text-decoration: none; }
.album-card__img { aspect-ratio: 3 / 2; overflow: hidden; background: var(--paper-deep); }
.album-card__title { margin: 14px 0 2px; font-family: var(--serif); font-style: italic; font-weight: 400; font-size: 1.5rem; }
.album-card__meta { margin: 0; font-size: .78rem; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
.theme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 440px), 1fr)); gap: 28px; }
.theme-card { position: relative; display: block; aspect-ratio: 16 / 10; overflow: hidden; background: var(--paper-deep); text-decoration: none; }
.theme-card__title {
  position: absolute; left: 0; bottom: 0; padding: 10px 22px 6px 0; background: var(--paper);
  font-family: var(--serif); font-style: italic; font-size: clamp(1.5rem, 2.6vw, 2.1rem);
}

/* Mosaïque d'album (rangées justifiées par mosaic.js, grille simple sans JS) */
.mosaic {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px;
  max-width: 1600px; margin: clamp(40px, 6vw, 72px) auto 0; padding: 0 var(--gutter);
}
.mosaic.is-justified { display: flex; flex-wrap: wrap; }
.mosaic__item { display: block; overflow: hidden; background: var(--paper-deep); cursor: zoom-in; }
.mosaic:not(.is-justified) .mosaic__item { aspect-ratio: 1; }
.mosaic__item picture, .mosaic__item img { width: 100%; height: 100%; object-fit: cover; }
.draft-badge { display: inline-block; margin-top: 14px; padding: 4px 12px; border: 1px dashed var(--muted); font-size: .8rem; color: var(--muted); }

/* Visionneuse */
body.viewer-open { overflow: hidden; }
.viewer { position: fixed; inset: 0; z-index: 1000; display: grid; grid-template-columns: 1fr auto; background: var(--paper); }
.viewer[hidden] { display: none; }
.viewer__stage { display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 0; min-height: 0; margin: 0; padding: 56px 72px 24px; }
.viewer__stage picture { display: contents; }
.viewer__stage img { width: auto; height: auto; max-width: 100%; max-height: calc(100vh - 120px); object-fit: contain; box-shadow: 0 20px 50px rgba(27, 27, 26, .12); }
.viewer__caption { margin-top: 14px; font-family: var(--serif); font-style: italic; font-size: 1.15rem; text-align: center; }
.viewer__btn { position: absolute; z-index: 2; width: 52px; height: 52px; border: 0; background: none; color: var(--ink); font: 300 2.4rem/1 var(--serif); cursor: pointer; }
.viewer__btn:hover { color: var(--muted); }
.viewer__close { top: 8px; right: 8px; }
.viewer__info-toggle { top: 17px; right: 64px; width: 34px; height: 34px; border: 1px solid var(--ink); border-radius: 50%; font: italic 500 1.3rem/1 var(--serif); }
.viewer__prev { left: 8px; top: 50%; transform: translateY(-50%); }
.viewer__next { right: 8px; top: 50%; transform: translateY(-50%); }
.viewer:has(.viewer__info:not([hidden])) .viewer__next { right: 348px; }
.viewer__info { width: 340px; padding: 72px 24px 24px; overflow-y: auto; border-left: 1px solid var(--line); background: var(--paper); }
.viewer__title { margin: 0 0 8px; font-family: var(--serif); font-style: italic; font-weight: 400; font-size: 1.6rem; line-height: 1.2; }
.viewer__text { margin: 0 0 20px; color: var(--muted); }
.viewer__exif { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; margin: 0 0 20px; font-size: .88rem; }
.viewer__exif dt { color: var(--muted); }
.viewer__exif dd { margin: 0; }
.viewer__map { height: 200px; border: 1px solid var(--line); }
@media (max-width: 760px) {
  .viewer { grid-template-columns: 1fr; }
  .viewer__stage { padding: 56px 8px 64px; }
  .viewer__prev, .viewer__next { top: auto; bottom: 8px; transform: none; }
  .viewer__info { position: absolute; inset: auto 0 0 0; width: auto; max-height: 60vh; padding-top: 24px; border-left: 0; border-top: 1px solid var(--line); z-index: 3; }
  .viewer:has(.viewer__info:not([hidden])) .viewer__next { right: 8px; }
}

/* Carte */
.map-page { max-width: 1600px; margin: 0 auto; padding: 32px var(--gutter) 0; }
#map { height: min(75vh, 820px); border: 1px solid var(--line); background: var(--paper-deep); }
.leaflet-container { font-family: var(--sans); }
.map-popup { display: block; width: 220px; text-decoration: none; color: var(--ink); }
.map-popup img { width: 220px; height: 146px; margin-bottom: 6px; object-fit: cover; }
.map-popup span { display: block; font-family: var(--serif); font-style: italic; font-size: 1.1rem; }
.map-popup small { color: var(--muted); }
.marker-cluster-small, .marker-cluster-medium, .marker-cluster-large { background: rgba(27, 27, 26, .18); }
.marker-cluster-small div, .marker-cluster-medium div, .marker-cluster-large div { background: var(--ink); color: var(--paper); font-family: var(--sans); }

/* Présentation */
.banner__img { width: 100%; height: min(60vh, 680px); object-fit: cover; object-position: 55% 40%; }
.about {
  display: grid; grid-template-columns: 2fr 3fr; gap: clamp(28px, 5vw, 72px); align-items: start;
  max-width: 1100px; margin: 0 auto; padding: clamp(48px, 7vw, 88px) var(--gutter) 0;
}
.about__img { width: 100%; aspect-ratio: 2 / 3; object-fit: cover; }
.prose p { margin: 0 0 1.1em; }
.prose h2, .prose h3 { font-family: var(--serif); font-style: italic; font-weight: 400; }
.pilot { margin: 36px 0; padding-top: 20px; border-top: 1px solid var(--line); }
.pilot__title { margin: 0 0 12px; font-family: var(--serif); font-style: italic; font-weight: 400; font-size: 1.5rem; }
.pilot dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 20px; margin: 0; }
.pilot dt { padding-top: 3px; font-size: .75rem; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
.pilot dd { margin: 0; }
@media (max-width: 760px) { .about { grid-template-columns: 1fr; } }

/* Formulaire de contact */
.form { display: grid; gap: 20px; }
.field { display: grid; gap: 6px; font-size: .78rem; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
.field input, .field textarea {
  padding: 10px 0; border: 0; border-bottom: 1px solid var(--line); border-radius: 0; background: transparent;
  color: var(--ink); font: inherit; font-size: 1rem; letter-spacing: normal; text-transform: none;
}
.field textarea { min-height: 180px; padding: 12px; border: 1px solid var(--line); resize: vertical; }
.field input:focus, .field textarea:focus { outline: none; border-color: var(--ink); }
.field__error { color: var(--danger); letter-spacing: normal; text-transform: none; }
.hp { position: absolute; left: -9999px; }
.button {
  justify-self: start; padding: 16px 28px; border: 0; background: var(--ink); color: var(--paper); cursor: pointer;
  font: 500 .8rem/1 var(--sans); letter-spacing: .16em; text-transform: uppercase;
}
.button:hover { background: #3a3936; }
.notice { padding: 16px 20px; border: 1px solid var(--line); background: var(--paper-deep); }
.notice--error { border-color: #d7a39b; }

/* Pied de page et erreurs */
.site-footer { margin-top: clamp(72px, 10vw, 128px); padding: 0 var(--gutter) 40px; text-align: center; color: var(--muted); font-size: .8rem; }
.site-footer__logo { width: 160px; margin: 32px auto 16px; opacity: .85; }
.site-footer nav { display: flex; justify-content: center; gap: 24px; margin-bottom: 14px; font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; }
.site-footer nav a { text-decoration: none; }
.site-footer p { margin: 0; }
.error-page { padding: clamp(60px, 12vw, 140px) var(--gutter); text-align: center; }
.error-page__code { margin: 0; font-family: var(--serif); font-size: 5rem; color: var(--muted); }
```

- [ ] **Étape 11 : lancer le serveur local et vérifier**

```bash
php -S localhost:8000 -t public public/index.php
```
Dans un autre terminal :
```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/
curl -s http://localhost:8000/ | grep -o "Les premières galeries arrivent bientôt"
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/n-existe-pas/vraiment/pas/du-tout
curl -sI http://localhost:8000/ | grep -i x-robots-tag
```
Attendu : `200`, puis le message d'accueil vide, puis `404`, puis `X-Robots-Tag: noindex, nofollow, noarchive`.

Ouvrir http://localhost:8000 dans le navigateur : on doit voir la signature, la navigation, l'accroche, la trace et le pied de page.

- [ ] **Étape 12 : commit**

```bash
git add public src/routes.php src/Controllers templates
git commit -m "feat: contrôleur frontal, mise en page publique et feuille de style

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 13 : administration — connexion, structure et tableau de bord

**Fichiers :**
- Créer : `src/Controllers/Admin/AdminController.php`, `AuthController.php`, `DashboardController.php`
- Créer : `templates/admin/layout.php`, `templates/admin/login.php`, `templates/admin/dashboard.php`
- Créer : `public/assets/css/admin.css`, `public/assets/js/admin.js`
- Modifier : `src/routes.php`

- [ ] **Étape 1 : écrire `src/Controllers/Admin/AdminController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\View;

abstract class AdminController extends Controller
{
    protected const SORTABLE_JS = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';

    public function __construct(View $view)
    {
        parent::__construct($view);
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        if (!Auth::check()) {
            redirect('/admin/login');
        }
    }

    /** @param array<string, mixed> $data */
    protected function page(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        $data['scripts'] = array_merge([self::SORTABLE_JS, asset('js/admin.js')], $data['scripts'] ?? []);
        echo $this->view->render($template, $data + ['nav' => true], 'admin/layout');
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::check($token)) {
            if ($this->wantsJson()) {
                json_response(['error' => 'Session expirée : rechargez la page.'], 419);
            }
            http_response_code(419);
            exit('Session expirée : rechargez la page.');
        }
    }

    protected function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /** @return array<string, mixed> */
    protected function jsonBody(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    protected function abort404(string $message = 'Élément introuvable.'): never
    {
        if ($this->wantsJson()) {
            json_response(['error' => $message], 404);
        }
        http_response_code(404);
        exit($message);
    }
}
```

- [ ] **Étape 2 : écrire `src/Controllers/Admin/AuthController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\RateLimiter;

final class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 900;

    public function form(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        $this->show();
    }

    public function login(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->show('Session expirée, réessayez.', 419);
            return;
        }
        $limiter = new RateLimiter(ROOT . '/storage/ratelimit');
        $key = 'login:' . client_ip();
        if ($limiter->tooMany($key, self::MAX_ATTEMPTS, self::WINDOW)) {
            $this->show('Trop de tentatives. Réessayez dans 15 minutes.', 429);
            return;
        }
        if (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            $limiter->clear($key);
            redirect('/admin');
        }
        $limiter->hit($key, self::WINDOW);
        $this->show('E-mail ou mot de passe incorrect.', 401);
    }

    public function logout(): void
    {
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Auth::logout();
        }
        redirect('/admin/login');
    }

    private function show(?string $error = null, int $status = 200): void
    {
        http_response_code($status);
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        echo $this->view->render('admin/login', ['error' => $error, 'title' => 'Connexion'], 'admin/layout');
    }
}
```

- [ ] **Étape 3 : écrire `src/Controllers/Admin/DashboardController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Album;
use App\Services\Database;

final class DashboardController extends AdminController
{
    public function index(): void
    {
        $this->page('admin/dashboard', [
            'title' => 'Tableau de bord',
            'counts' => [
                'themes' => (int) Database::value('SELECT COUNT(*) FROM themes'),
                'albums' => (int) Database::value('SELECT COUNT(*) FROM albums'),
                'photos' => (int) Database::value('SELECT COUNT(*) FROM photos'),
            ],
            'albums' => Album::recentForAdmin(8),
            'limits' => [
                'upload' => (string) ini_get('upload_max_filesize'),
                'post' => (string) ini_get('post_max_size'),
                'memory' => (string) ini_get('memory_limit'),
            ],
        ]);
    }
}
```

- [ ] **Étape 4 : écrire les templates d'administration**

`templates/admin/layout.php` :
```php
<?php /** @var string $content */ ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(App\Services\Csrf::token()) ?>">
<title><?= e(($title ?? 'Administration') . ' · Admin') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<?php if (!empty($nav)): ?>
<header class="admin-bar">
  <a class="admin-bar__brand" href="/admin">Administration</a>
  <nav>
    <a href="/admin/themes">Thèmes</a>
    <a href="/admin/albums">Albums</a>
    <a href="/admin/content">Contenus</a>
    <a href="/" target="_blank" rel="noopener">Voir le site ↗</a>
  </nav>
  <form method="post" action="/admin/logout"><?= csrf_field() ?><button class="link-button" type="submit">Déconnexion</button></form>
</header>
<?php endif; ?>
<main class="admin-main">
<?php if ($m = flash('success')): ?><p class="flash flash--success"><?= e($m) ?></p><?php endif; ?>
<?php if ($m = flash('error')): ?><p class="flash flash--error"><?= e($m) ?></p><?php endif; ?>
<?= $content ?>
</main>
<?php foreach ($scripts ?? [] as $src): ?>
<script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
```

`templates/admin/login.php` :
```php
<?php /** @var ?string $error */ ?>
<section class="login panel">
  <h1>Connexion</h1>
  <?php if (!empty($error)): ?><p class="flash flash--error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/admin/login" class="form">
    <?= csrf_field() ?>
    <label>E-mail<input type="email" name="email" required autocomplete="username" autofocus></label>
    <label>Mot de passe<input type="password" name="password" required autocomplete="current-password"></label>
    <div class="form-actions"><button class="btn" type="submit">Se connecter</button></div>
  </form>
</section>
```

`templates/admin/dashboard.php` :
```php
<?php /** @var array $counts @var list<array> $albums @var array $limits */ ?>
<div class="toolbar">
  <h1>Tableau de bord</h1>
  <a class="btn" href="/admin/albums/new">Nouvel album</a>
</div>
<div class="stats">
  <div class="stat"><strong><?= (int) $counts['themes'] ?></strong>thèmes</div>
  <div class="stat"><strong><?= (int) $counts['albums'] ?></strong>albums</div>
  <div class="stat"><strong><?= (int) $counts['photos'] ?></strong>photos</div>
</div>
<h2>Albums récemment modifiés</h2>
<?php if ($albums): ?>
<ul class="list">
  <?php foreach ($albums as $a): ?>
  <li>
    <?php if ($a['cover_key']): ?><img src="<?= e(media_url($a['cover_key'], 480)) ?>" alt=""><?php endif; ?>
    <span class="grow"><a href="/admin/albums/<?= (int) $a['id'] ?>/photos"><?= e($a['title']) ?></a> <small>— <?= e($a['theme_title']) ?> · <?= (int) $a['photo_count'] ?> photo(s)</small></span>
    <span class="badge<?= $a['status'] === 'draft' ? ' badge--draft' : '' ?>"><?= $a['status'] === 'draft' ? 'Brouillon' : 'Publié' ?></span>
  </li>
  <?php endforeach; ?>
</ul>
<?php else: ?>
<p>Aucun album pour l'instant. Commencez par <a href="/admin/themes/new">créer un thème</a>.</p>
<?php endif; ?>
<p class="hint">Limites du serveur : fichier <?= e($limits['upload']) ?>, requête <?= e($limits['post']) ?>, mémoire <?= e($limits['memory']) ?>.</p>
```

- [ ] **Étape 5 : écrire `public/assets/css/admin.css`**

```css
:root { --paper: #f4f1ec; --white: #fffdf9; --ink: #1b1b1a; --muted: #7a756d; --line: #d9d3c9; --danger: #9b2c1f; --ok: #2f6b3a; }
* { box-sizing: border-box; }
body.admin { margin: 0; background: var(--paper); color: var(--ink); font: 15px/1.5 system-ui, -apple-system, 'Segoe UI', sans-serif; }
a { color: inherit; }
.admin-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 24px; padding: 12px 24px; background: var(--ink); color: var(--paper); }
.admin-bar a { color: var(--paper); text-decoration: none; }
.admin-bar nav { display: flex; flex: 1; flex-wrap: wrap; gap: 18px; }
.admin-bar__brand { font-weight: 600; }
.admin-bar .link-button { color: var(--paper); }
.admin-main { max-width: 1200px; margin: 0 auto; padding: 28px 16px 80px; }
h1 { margin: 0 0 20px; font-size: 1.6rem; }
h2 { margin: 28px 0 12px; font-size: 1.15rem; }
.hint { color: var(--muted); font-size: .85rem; font-weight: 400; }
.flash { margin: 0 0 20px; padding: 12px 16px; border-radius: 6px; }
.flash--success { background: #e3efe2; color: var(--ok); }
.flash--error { background: #f5e1dd; color: var(--danger); }
.toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.toolbar h1 { margin: 0; }
.btn { display: inline-block; padding: 9px 16px; border: 1px solid var(--ink); border-radius: 6px; background: var(--ink); color: var(--paper); font: inherit; text-decoration: none; cursor: pointer; }
.btn--ghost { background: transparent; color: var(--ink); }
.btn--danger { border-color: var(--danger); background: var(--danger); }
.btn--small { padding: 4px 10px; font-size: .85rem; }
.link-button { padding: 0; border: 0; background: none; color: var(--ink); font: inherit; text-decoration: underline; cursor: pointer; }
.panel { margin-bottom: 20px; padding: 20px; border: 1px solid var(--line); border-radius: 8px; background: var(--white); }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; }
.stat { padding: 16px; border: 1px solid var(--line); border-radius: 8px; background: var(--white); }
.stat strong { display: block; font-size: 2rem; }
.list { margin: 0; padding: 0; list-style: none; border: 1px solid var(--line); border-radius: 8px; background: var(--white); }
.list li { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid var(--line); }
.list li:last-child { border-bottom: 0; }
.list .grow { flex: 1; min-width: 0; }
.list img { width: 64px; height: 44px; border-radius: 4px; object-fit: cover; background: var(--paper); }
.drag-handle { padding: 0 4px; color: var(--muted); cursor: grab; user-select: none; }
.is-saved { outline: 2px solid var(--ok); outline-offset: 2px; }
.badge { display: inline-block; padding: 2px 8px; border: 1px solid var(--line); border-radius: 999px; background: var(--paper); font-size: .75rem; }
.badge--draft { border-style: dashed; }
.badge--featured { border-color: var(--ink); background: var(--ink); color: var(--paper); }
.form { display: grid; gap: 16px; max-width: 760px; }
.form label { display: grid; gap: 4px; font-size: .9rem; font-weight: 600; }
.form input[type=text], .form input[type=email], .form input[type=password], .form input[type=date], .form select, .form textarea {
  padding: 9px 11px; border: 1px solid var(--line); border-radius: 6px; background: var(--white); font: inherit; font-weight: 400;
}
.form textarea { min-height: 120px; resize: vertical; }
.form .error { color: var(--danger); font-size: .85rem; font-weight: 400; }
.form label.checkbox { display: flex; align-items: center; gap: 8px; font-weight: 400; }
.form-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.text-toolbar { display: flex; gap: 6px; }
.text-toolbar button { padding: 2px 10px; border: 1px solid var(--line); border-radius: 4px; background: var(--white); font: inherit; cursor: pointer; }
.dropzone { display: block; padding: 36px 20px; border: 2px dashed var(--line); border-radius: 10px; background: var(--white); text-align: center; cursor: pointer; }
.dropzone.is-over { border-color: var(--ink); }
.dropzone input { display: none; }
.upload-queue { display: grid; gap: 6px; margin: 12px 0 24px; padding: 0; list-style: none; }
.upload-item { display: grid; grid-template-columns: 1fr 160px 220px auto; gap: 12px; align-items: center; font-size: .88rem; }
.upload-item.is-done .upload-item__status { color: var(--ok); }
.upload-item.is-error .upload-item__status { color: var(--danger); }
.photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
.photo-card { display: flex; flex-direction: column; overflow: hidden; border: 1px solid var(--line); border-radius: 8px; background: var(--white); }
.photo-card__img { position: relative; aspect-ratio: 3 / 2; background: var(--paper); }
.photo-card__img img { width: 100%; height: 100%; object-fit: cover; }
.photo-card__img .drag-handle { position: absolute; top: 6px; left: 6px; padding: 2px 8px; border-radius: 4px; background: rgba(255, 253, 249, .9); }
.photo-card__badges { position: absolute; top: 6px; right: 6px; display: flex; gap: 4px; }
.photo-card > form { display: grid; gap: 6px; padding: 10px; }
.photo-card input[type=text], .photo-card textarea, .photo-card select { width: 100%; padding: 6px 8px; border: 1px solid var(--line); border-radius: 5px; font: inherit; font-size: .85rem; }
.photo-card textarea { min-height: 54px; resize: vertical; }
.photo-card details { padding: 0 10px 10px; font-size: .85rem; }
.photo-card details form { display: grid; gap: 6px; padding-top: 8px; }
.photo-card .actions { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 10px 10px; }
.photo-card .actions form { display: inline; }
.save-status { min-height: 1em; color: var(--muted); font-size: .8rem; }
.exif-line { margin: 0; padding: 0 10px 8px; color: var(--muted); font-size: .78rem; }
.login { max-width: 380px; margin: 10vh auto; }
.preview { display: block; max-width: 320px; border: 1px solid var(--line); border-radius: 6px; }
@media (max-width: 640px) { .upload-item { grid-template-columns: 1fr; } }
```

- [ ] **Étape 6 : écrire `public/assets/js/admin.js`**

```js
(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // Confirmation avant les actions destructrices : <form data-confirm="…">
  document.addEventListener('submit', (ev) => {
    const message = ev.target.dataset.confirm;
    if (message && !window.confirm(message)) ev.preventDefault();
  });

  // Tri par glisser-déposer : conteneur [data-sortable="/url"], enfants [data-id]
  document.querySelectorAll('[data-sortable]').forEach((list) => {
    if (!window.Sortable) return;
    Sortable.create(list, {
      animation: 150,
      handle: list.dataset.handle || null,
      onEnd: () => {
        const ids = Array.from(list.children).map((el) => el.dataset.id).filter(Boolean);
        fetch(list.dataset.sortable, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
          body: JSON.stringify({ ids }),
        })
          .then((r) => {
            if (!r.ok) throw new Error(String(r.status));
            list.classList.add('is-saved');
            setTimeout(() => list.classList.remove('is-saved'), 800);
          })
          .catch(() => alert("L'ordre n'a pas pu être enregistré. Rechargez la page."));
      },
    });
  });

  // Enregistrement automatique : <form data-autosave> (titre et légende des photos)
  document.addEventListener('change', (ev) => {
    const form = ev.target.closest('form[data-autosave]');
    if (!form) return;
    const status = form.querySelector('.save-status');
    if (status) status.textContent = 'Enregistrement…';
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
      .then(() => { if (status) status.textContent = 'Enregistré ✓'; })
      .catch(() => { if (status) status.textContent = 'Erreur, réessayez'; });
  });

  // Barre gras / italique / lien au-dessus d'une zone de texte : [data-toolbar="id-du-textarea"]
  document.querySelectorAll('[data-toolbar]').forEach((bar) => {
    const area = document.getElementById(bar.dataset.toolbar);
    if (!area) return;
    bar.addEventListener('click', (ev) => {
      const btn = ev.target.closest('button[data-wrap]');
      if (!btn) return;
      ev.preventDefault();
      let open; let close;
      if (btn.dataset.wrap === 'link') {
        const href = window.prompt('Adresse du lien (https://…)');
        if (!href) return;
        open = `<a href="${href.replace(/"/g, '&quot;')}">`;
        close = '</a>';
      } else {
        [open, close] = btn.dataset.wrap.split('|');
      }
      const { selectionStart: s, selectionEnd: e, value } = area;
      area.value = value.slice(0, s) + open + value.slice(s, e) + close + value.slice(e);
      area.focus();
      area.selectionStart = s + open.length;
      area.selectionEnd = e + open.length;
    });
  });
})();
```

- [ ] **Étape 7 : brancher les routes d'authentification et le tableau de bord**

Dans `src/routes.php`, ajouter les imports :
```php
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
```
puis, **avant** le bloc `// --- Public ---` :
```php
// --- Administration ---
$router->get('/admin/login', static fn () => (new AuthController($view))->form());
$router->post('/admin/login', static fn () => (new AuthController($view))->login());
$router->post('/admin/logout', static fn () => (new AuthController($view))->logout());
$router->get('/admin', static fn () => (new DashboardController($view))->index());
```

- [ ] **Étape 8 : vérifier**

Avec le serveur local lancé :
```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost:8000/admin
```
Attendu : `303 http://localhost:8000/admin/login`.

Dans le navigateur, sur http://localhost:8000/admin/login :
1. un mauvais mot de passe affiche « E-mail ou mot de passe incorrect » ;
2. le bon mot de passe amène au tableau de bord (0 / 0 / 0) et affiche les limites du serveur ;
3. « Déconnexion » ramène à l'écran de connexion.

- [ ] **Étape 9 : commit**

```bash
git add src/Controllers/Admin src/routes.php templates/admin public/assets/css/admin.css public/assets/js/admin.js
git commit -m "feat: connexion admin, structure de l'administration et tableau de bord

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 14 : administration — thèmes

**Fichiers :**
- Créer : `src/Controllers/Admin/ThemeController.php`, `templates/admin/themes/index.php`, `templates/admin/themes/form.php`
- Modifier : `src/routes.php`

- [ ] **Étape 1 : écrire `src/Controllers/Admin/ThemeController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Theme;

final class ThemeController extends AdminController
{
    public function index(): void
    {
        $this->page('admin/themes/index', ['title' => 'Thèmes', 'themes' => Theme::all()]);
    }

    public function create(): void
    {
        $this->form(['id' => null, 'title' => '', 'slug' => '', 'intro' => '', 'featured' => 0]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->input();
        if ($errors = $this->validate($data)) {
            $this->form(['id' => null] + $data, $errors, 422);
            return;
        }
        Theme::create($data);
        flash('success', 'Thème créé.');
        redirect('/admin/themes');
    }

    public function edit(int $id): void
    {
        $theme = Theme::find($id) ?? $this->abort404('Thème introuvable.');
        $this->form($theme);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        Theme::find($id) ?? $this->abort404('Thème introuvable.');
        $data = $this->input();
        if ($errors = $this->validate($data)) {
            $this->form(['id' => $id] + $data, $errors, 422);
            return;
        }
        Theme::update($id, $data);
        flash('success', 'Thème enregistré.');
        redirect('/admin/themes');
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        try {
            Theme::delete($id);
            flash('success', 'Thème supprimé.');
        } catch (\DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/admin/themes');
    }

    public function reorder(): void
    {
        $this->verifyCsrf();
        Theme::reorder(array_map('intval', (array) ($this->jsonBody()['ids'] ?? [])));
        json_response(['ok' => true]);
    }

    /** @param array<string, mixed> $theme @param array<string, string> $errors */
    private function form(array $theme, array $errors = [], int $status = 200): void
    {
        $this->page('admin/themes/form', [
            'title' => $theme['id'] === null ? 'Nouveau thème' : 'Modifier le thème',
            'theme' => $theme,
            'errors' => $errors,
        ], $status);
    }

    /** @return array{title: string, slug: string, intro: string, featured: bool} */
    private function input(): array
    {
        return [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'intro' => trim((string) ($_POST['intro'] ?? '')),
            'featured' => isset($_POST['featured']),
        ];
    }

    /** @return array<string, string> */
    private function validate(array $data): array
    {
        $errors = [];
        if ($data['title'] === '' || mb_strlen($data['title']) > 150) {
            $errors['title'] = 'Titre obligatoire (150 caractères maximum).';
        }
        return $errors;
    }
}
```

- [ ] **Étape 2 : écrire `templates/admin/themes/index.php`**

```php
<?php /** @var list<array> $themes */ ?>
<div class="toolbar">
  <h1>Thèmes</h1>
  <a class="btn" href="/admin/themes/new">Nouveau thème</a>
</div>
<?php if ($themes): ?>
<p class="hint">Glissez les poignées ⠿ pour changer l'ordre d'affichage sur le site.</p>
<ul class="list" data-sortable="/admin/themes/reorder" data-handle=".drag-handle">
  <?php foreach ($themes as $t): ?>
  <li data-id="<?= (int) $t['id'] ?>">
    <span class="drag-handle" title="Déplacer">⠿</span>
    <?php if ($t['cover_key']): ?><img src="<?= e(media_url($t['cover_key'], 480)) ?>" alt=""><?php endif; ?>
    <span class="grow"><a href="/admin/themes/<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a> <small>/<?= e($t['slug']) ?> · <?= (int) $t['total_albums'] ?> album(s)</small></span>
    <?php if ($t['featured']): ?><span class="badge badge--featured">Mis en avant</span><?php endif; ?>
  </li>
  <?php endforeach; ?>
</ul>
<?php else: ?>
<p>Aucun thème. Créez par exemple « Vues du ciel », « Paysages », « Aviation », « Villes &amp; patrimoine ».</p>
<?php endif; ?>
```

- [ ] **Étape 3 : écrire `templates/admin/themes/form.php`**

```php
<?php /** @var array $theme @var array $errors */ $isNew = $theme['id'] === null; ?>
<div class="toolbar">
  <h1><?= $isNew ? 'Nouveau thème' : 'Modifier le thème' ?></h1>
  <a href="/admin/themes">← Thèmes</a>
</div>
<form method="post" action="<?= $isNew ? '/admin/themes' : '/admin/themes/' . (int) $theme['id'] ?>" class="form panel">
  <?= csrf_field() ?>
  <label>Titre
    <input type="text" name="title" maxlength="150" required value="<?= e($theme['title']) ?>">
    <?php if (!empty($errors['title'])): ?><span class="error"><?= e($errors['title']) ?></span><?php endif; ?>
  </label>
  <?php if (!$isNew): ?>
  <label>Adresse (slug)
    <input type="text" name="slug" maxlength="90" value="<?= e($theme['slug']) ?>">
    <span class="hint">guillaumelecomte.net/<?= e($theme['slug']) ?>. Laissez vide pour la recalculer depuis le titre.</span>
  </label>
  <?php endif; ?>
  <label>Introduction
    <textarea name="intro" rows="4"><?= e($theme['intro']) ?></textarea>
  </label>
  <label class="checkbox"><input type="checkbox" name="featured" value="1"<?= !empty($theme['featured']) ? ' checked' : '' ?>> Mettre en avant sur l'accueil (section « Depuis le cockpit »)</label>
  <div class="form-actions"><button class="btn" type="submit">Enregistrer</button></div>
</form>
<?php if (!$isNew): ?>
<form method="post" action="/admin/themes/<?= (int) $theme['id'] ?>/delete" data-confirm="Supprimer ce thème ?">
  <?= csrf_field() ?>
  <button class="btn btn--danger btn--small" type="submit">Supprimer le thème</button>
</form>
<?php endif; ?>
```

- [ ] **Étape 4 : ajouter les routes**

Dans `src/routes.php` : import `use App\Controllers\Admin\ThemeController;` et, après la route `/admin` :
```php
$router->get('/admin/themes', static fn () => (new ThemeController($view))->index());
$router->get('/admin/themes/new', static fn () => (new ThemeController($view))->create());
$router->post('/admin/themes', static fn () => (new ThemeController($view))->store());
$router->post('/admin/themes/reorder', static fn () => (new ThemeController($view))->reorder());
$router->get('/admin/themes/{id:\d+}', static fn (string $id) => (new ThemeController($view))->edit((int) $id));
$router->post('/admin/themes/{id:\d+}', static fn (string $id) => (new ThemeController($view))->update((int) $id));
$router->post('/admin/themes/{id:\d+}/delete', static fn (string $id) => (new ThemeController($view))->delete((int) $id));
```

- [ ] **Étape 5 : vérifier dans le navigateur**

1. Créer « Vues du ciel » (case « mis en avant » cochée), « Paysages » et « Aviation ».
2. Réordonner par glisser-déposer, recharger : l'ordre est conservé.
3. Créer un thème intitulé « Contact » : son slug doit être `contact-2`.
4. Supprimer « Contact » : le message de succès s'affiche.
5. Cocher « mis en avant » sur « Paysages » : le badge passe de « Vues du ciel » à « Paysages ». Le remettre sur « Vues du ciel ».

- [ ] **Étape 6 : commit**

```bash
git add src/Controllers/Admin/ThemeController.php templates/admin/themes src/routes.php
git commit -m "feat: gestion des thèmes dans l'administration

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 15 : administration — albums

**Fichiers :**
- Créer : `src/Controllers/Admin/AlbumController.php`, `templates/admin/albums/index.php`, `templates/admin/albums/form.php`
- Modifier : `src/routes.php`

- [ ] **Étape 1 : écrire `src/Controllers/Admin/AlbumController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Theme;
use App\Services\ImageProcessor;

final class AlbumController extends AdminController
{
    public function index(): void
    {
        $albums = Album::allForAdmin();
        $groups = [];
        foreach (Theme::all() as $theme) {
            $groups[] = [
                'theme' => $theme,
                'albums' => array_values(array_filter($albums, static fn (array $a): bool => (int) $a['theme_id'] === (int) $theme['id'])),
            ];
        }
        $this->page('admin/albums/index', ['title' => 'Albums', 'groups' => $groups]);
    }

    public function create(): void
    {
        $themes = Theme::all();
        if ($themes === []) {
            flash('error', 'Créez d\'abord un thème.');
            redirect('/admin/themes/new');
        }
        $this->form([
            'id' => null, 'theme_id' => (int) ($_GET['theme'] ?? $themes[0]['id']), 'title' => '', 'slug' => '',
            'description' => '', 'shot_on' => '', 'location' => '', 'status' => 'draft',
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->input();
        if ($errors = $this->validate($data)) {
            $this->form(['id' => null] + $data, $errors, 422);
            return;
        }
        $id = Album::create($data);
        flash('success', 'Album créé : ajoutez vos photos.');
        redirect('/admin/albums/' . $id . '/photos');
    }

    public function edit(int $id): void
    {
        $album = Album::find($id) ?? $this->abort404('Album introuvable.');
        $this->form($album);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        Album::find($id) ?? $this->abort404('Album introuvable.');
        $data = $this->input();
        if ($errors = $this->validate($data)) {
            $this->form(['id' => $id] + $data, $errors, 422);
            return;
        }
        Album::update($id, $data);
        flash('success', 'Album enregistré.');
        redirect('/admin/albums/' . $id . '/photos');
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        Album::find($id) ?? $this->abort404('Album introuvable.');
        $heroId = (int) Setting::get('hero_photo_id', '0');
        $photoIds = array_map(static fn (array $p): int => (int) $p['id'], Photo::forAlbum($id));
        foreach (Album::delete($id) as $key) {
            ImageProcessor::deleteVariants(media_dir($key), $key);
        }
        if (in_array($heroId, $photoIds, true)) {
            Setting::set('hero_photo_id', null);
        }
        flash('success', 'Album et photos supprimés.');
        redirect('/admin/albums');
    }

    public function reorder(): void
    {
        $this->verifyCsrf();
        Album::reorder(array_map('intval', (array) ($this->jsonBody()['ids'] ?? [])));
        json_response(['ok' => true]);
    }

    public function photos(int $id): void
    {
        $album = Album::find($id) ?? $this->abort404('Album introuvable.');
        $this->page('admin/albums/photos', [
            'title' => $album['title'],
            'album' => $album,
            'photos' => Photo::forAlbum($id),
            'albums' => Album::allForAdmin(),
            'heroId' => (int) Setting::get('hero_photo_id', '0'),
            'scripts' => [asset('js/admin-upload.js')],
        ]);
    }

    /** @param array<string, mixed> $album @param array<string, string> $errors */
    private function form(array $album, array $errors = [], int $status = 200): void
    {
        $this->page('admin/albums/form', [
            'title' => $album['id'] === null ? 'Nouvel album' : 'Infos de l\'album',
            'album' => $album,
            'themes' => Theme::all(),
            'errors' => $errors,
        ], $status);
    }

    /** @return array{theme_id: int, title: string, slug: string, description: string, shot_on: string, location: string, status: string} */
    private function input(): array
    {
        return [
            'theme_id' => (int) ($_POST['theme_id'] ?? 0),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'shot_on' => trim((string) ($_POST['shot_on'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'status' => ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft',
        ];
    }

    /** @return array<string, string> */
    private function validate(array $data): array
    {
        $errors = [];
        if (Theme::find($data['theme_id']) === null) {
            $errors['theme_id'] = 'Choisissez un thème.';
        }
        if ($data['title'] === '' || mb_strlen($data['title']) > 150) {
            $errors['title'] = 'Titre obligatoire (150 caractères maximum).';
        }
        if ($data['shot_on'] !== '' && \DateTimeImmutable::createFromFormat('!Y-m-d', $data['shot_on']) === false) {
            $errors['shot_on'] = 'Date invalide.';
        }
        if (mb_strlen($data['location']) > 150) {
            $errors['location'] = '150 caractères maximum.';
        }
        return $errors;
    }
}
```

- [ ] **Étape 2 : écrire `templates/admin/albums/index.php`**

```php
<?php /** @var list<array{theme: array, albums: list<array>}> $groups */ ?>
<div class="toolbar">
  <h1>Albums</h1>
  <a class="btn" href="/admin/albums/new">Nouvel album</a>
</div>
<?php if (!$groups): ?><p>Créez d'abord <a href="/admin/themes/new">un thème</a>.</p><?php endif; ?>
<?php foreach ($groups as $group): ?>
  <h2><?= e($group['theme']['title']) ?> <a class="btn btn--ghost btn--small" href="/admin/albums/new?theme=<?= (int) $group['theme']['id'] ?>">+ album</a></h2>
  <?php if ($group['albums']): ?>
  <ul class="list" data-sortable="/admin/albums/reorder" data-handle=".drag-handle">
    <?php foreach ($group['albums'] as $a): ?>
    <li data-id="<?= (int) $a['id'] ?>">
      <span class="drag-handle" title="Déplacer">⠿</span>
      <?php if ($a['cover_key']): ?><img src="<?= e(media_url($a['cover_key'], 480)) ?>" alt=""><?php endif; ?>
      <span class="grow"><a href="/admin/albums/<?= (int) $a['id'] ?>/photos"><?= e($a['title']) ?></a> <small><?= (int) $a['photo_count'] ?> photo(s)</small></span>
      <span class="badge<?= $a['status'] === 'draft' ? ' badge--draft' : '' ?>"><?= $a['status'] === 'draft' ? 'Brouillon' : 'Publié' ?></span>
      <a class="btn btn--ghost btn--small" href="/admin/albums/<?= (int) $a['id'] ?>">Infos</a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="hint">Aucun album dans ce thème.</p>
  <?php endif; ?>
<?php endforeach; ?>
```

- [ ] **Étape 3 : écrire `templates/admin/albums/form.php`**

```php
<?php /** @var array $album @var list<array> $themes @var array $errors */ $isNew = $album['id'] === null; ?>
<div class="toolbar">
  <h1><?= $isNew ? 'Nouvel album' : 'Infos de l\'album' ?></h1>
  <a href="/admin/albums">← Albums</a>
</div>
<form method="post" action="<?= $isNew ? '/admin/albums' : '/admin/albums/' . (int) $album['id'] ?>" class="form panel">
  <?= csrf_field() ?>
  <label>Thème
    <select name="theme_id" required>
      <?php foreach ($themes as $t): ?>
        <option value="<?= (int) $t['id'] ?>"<?= (int) $album['theme_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!empty($errors['theme_id'])): ?><span class="error"><?= e($errors['theme_id']) ?></span><?php endif; ?>
  </label>
  <label>Titre
    <input type="text" name="title" maxlength="150" required value="<?= e($album['title']) ?>">
    <?php if (!empty($errors['title'])): ?><span class="error"><?= e($errors['title']) ?></span><?php endif; ?>
  </label>
  <?php if (!$isNew): ?>
  <label>Adresse (slug)
    <input type="text" name="slug" maxlength="90" value="<?= e($album['slug']) ?>">
    <span class="hint">Laissez vide pour la recalculer depuis le titre.</span>
  </label>
  <?php endif; ?>
  <label>Date de prise de vue
    <input type="date" name="shot_on" value="<?= e($album['shot_on']) ?>">
    <?php if (!empty($errors['shot_on'])): ?><span class="error"><?= e($errors['shot_on']) ?></span><?php endif; ?>
  </label>
  <label>Lieu
    <input type="text" name="location" maxlength="150" value="<?= e($album['location']) ?>">
    <?php if (!empty($errors['location'])): ?><span class="error"><?= e($errors['location']) ?></span><?php endif; ?>
  </label>
  <label>Texte de présentation
    <textarea name="description" rows="5"><?= e($album['description']) ?></textarea>
  </label>
  <label>Statut
    <select name="status">
      <option value="draft"<?= $album['status'] !== 'published' ? ' selected' : '' ?>>Brouillon (invisible pour les visiteurs)</option>
      <option value="published"<?= $album['status'] === 'published' ? ' selected' : '' ?>>Publié</option>
    </select>
  </label>
  <div class="form-actions">
    <button class="btn" type="submit"><?= $isNew ? 'Créer et ajouter des photos' : 'Enregistrer' ?></button>
    <?php if (!$isNew): ?><a href="/admin/albums/<?= (int) $album['id'] ?>/photos">Gérer les photos</a><?php endif; ?>
  </div>
</form>
<?php if (!$isNew): ?>
<form method="post" action="/admin/albums/<?= (int) $album['id'] ?>/delete" data-confirm="Supprimer cet album et toutes ses photos ? Cette action est définitive.">
  <?= csrf_field() ?>
  <button class="btn btn--danger btn--small" type="submit">Supprimer l'album</button>
</form>
<?php endif; ?>
```

- [ ] **Étape 4 : ajouter les routes**

Dans `src/routes.php` : import `use App\Controllers\Admin\AlbumController;` et, après les routes des thèmes :
```php
$router->get('/admin/albums', static fn () => (new AlbumController($view))->index());
$router->get('/admin/albums/new', static fn () => (new AlbumController($view))->create());
$router->post('/admin/albums', static fn () => (new AlbumController($view))->store());
$router->post('/admin/albums/reorder', static fn () => (new AlbumController($view))->reorder());
$router->get('/admin/albums/{id:\d+}', static fn (string $id) => (new AlbumController($view))->edit((int) $id));
$router->post('/admin/albums/{id:\d+}', static fn (string $id) => (new AlbumController($view))->update((int) $id));
$router->post('/admin/albums/{id:\d+}/delete', static fn (string $id) => (new AlbumController($view))->delete((int) $id));
$router->get('/admin/albums/{id:\d+}/photos', static fn (string $id) => (new AlbumController($view))->photos((int) $id));
```

La page `photos` utilise le template `admin/albums/photos.php` et le partial `admin/partials/photo-card.php`, créés à la tâche 16. Ne vérifier pour l'instant que la création, la modification et la liste des albums.

- [ ] **Étape 5 : vérifier dans le navigateur**

1. Créer « Côte d'Opale » dans « Paysages », en brouillon, avec une date et un lieu : la création redirige vers `/admin/albums/{id}/photos`. Cette page renvoie une erreur 500 tant que la tâche 16 n'est pas faite, c'est attendu.
2. Sur `/admin/albums`, l'album apparaît sous « Paysages » avec le badge Brouillon.
3. « Infos » : passer en Publié et enregistrer.

- [ ] **Étape 6 : commit**

```bash
git add src/Controllers/Admin/AlbumController.php templates/admin/albums/index.php templates/admin/albums/form.php src/routes.php
git commit -m "feat: gestion des albums dans l'administration

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 16 : administration — envoi et gestion des photos

**Fichiers :**
- Créer : `src/Controllers/Admin/PhotoController.php`, `templates/admin/albums/photos.php`, `templates/admin/partials/photo-card.php`, `public/assets/js/admin-upload.js`
- Modifier : `src/routes.php`

- [ ] **Étape 1 : écrire `src/Controllers/Admin/PhotoController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Theme;
use App\Services\ExifReader;
use App\Services\ImageException;
use App\Services\ImageProcessor;

final class PhotoController extends AdminController
{
    private const MAX_BYTES = 48 * 1024 * 1024;

    public function upload(int $albumId): void
    {
        $this->verifyCsrf();
        $album = Album::find($albumId) ?? $this->abort404('Album introuvable.');

        $file = $_FILES['photo'] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            json_response(['error' => 'Fichier absent ou trop lourd (48 Mo maximum).'], 413);
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            json_response(['error' => self::uploadError((int) $file['error'])], 400);
        }
        if ($file['size'] > self::MAX_BYTES) {
            json_response(['error' => 'Fichier trop lourd (48 Mo maximum).'], 413);
        }
        if ((new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) !== 'image/jpeg') {
            json_response(['error' => 'Seuls les fichiers JPEG sont acceptés.'], 415);
        }

        $key = bin2hex(random_bytes(12));
        $dir = media_dir($key);
        $exif = (new ExifReader())->read($file['tmp_name']);

        try {
            $size = (new ImageProcessor())->process($file['tmp_name'], $dir, $key, $exif['orientation']);
        } catch (ImageException $e) {
            @unlink($file['tmp_name']);
            json_response(['error' => $e->getMessage()], 422);
        }

        try {
            $id = Photo::create([
                'album_id' => $albumId,
                'file_key' => $key,
                'title' => self::titleFromFilename((string) $file['name']),
                'width' => $size['width'],
                'height' => $size['height'],
                'taken_at' => $exif['taken_at'],
                'camera' => $exif['camera'],
                'lens' => $exif['lens'],
                'focal_length' => $exif['focal_length'],
                'aperture' => $exif['aperture'],
                'shutter' => $exif['shutter'],
                'iso' => $exif['iso'],
                'lat' => $exif['lat'],
                'lng' => $exif['lng'],
            ]);
        } catch (\Throwable $e) {
            ImageProcessor::deleteVariants($dir, $key);
            throw $e;
        } finally {
            @unlink($file['tmp_name']);
        }

        json_response([
            'id' => $id,
            'html' => $this->view->partial('admin/partials/photo-card', [
                'photo' => Photo::find($id),
                'album' => Album::find($albumId),
                'albums' => Album::allForAdmin(),
                'heroId' => (int) Setting::get('hero_photo_id', '0'),
            ]),
        ], 201);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        $caption = trim((string) ($_POST['caption'] ?? ''));
        Photo::update($id, [
            'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190),
            'caption' => $caption !== '' ? $caption : null,
        ]);
        $this->done($photo, 'Photo enregistrée.');
    }

    public function gps(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        $lat = str_replace(',', '.', trim((string) ($_POST['lat'] ?? '')));
        $lng = str_replace(',', '.', trim((string) ($_POST['lng'] ?? '')));
        if ($lat === '' && $lng === '') {
            Photo::update($id, ['lat' => null, 'lng' => null]);
            $this->done($photo, 'Position GPS retirée.');
        }
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            $this->done($photo, 'Coordonnées GPS invalides (ex. 50.2915 et 2.7775).', true);
        }
        Photo::update($id, ['lat' => (float) $lat, 'lng' => (float) $lng]);
        $this->done($photo, 'Position GPS enregistrée.');
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        $key = Photo::delete($id);
        if ($key !== null) {
            ImageProcessor::deleteVariants(media_dir($key), $key);
        }
        if ((int) Setting::get('hero_photo_id', '0') === $id) {
            Setting::set('hero_photo_id', null);
        }
        $this->done($photo, 'Photo supprimée.');
    }

    public function move(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        $target = Album::find((int) ($_POST['album_id'] ?? 0));
        if ($target === null) {
            $this->done($photo, 'Album de destination introuvable.', true);
        }
        Photo::move($id, (int) $target['id']);
        $this->done($photo, 'Photo déplacée vers « ' . $target['title'] . ' ».');
    }

    public function cover(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        Album::setCover((int) $photo['album_id'], $id);
        $this->done($photo, 'Couverture de l\'album mise à jour.');
    }

    public function themeCover(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        Theme::setCover((int) $photo['theme_id'], $id);
        $this->done($photo, 'Couverture du thème mise à jour.');
    }

    public function hero(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        Setting::set('hero_photo_id', (string) $id);
        $this->done($photo, 'Photo d\'ouverture de l\'accueil mise à jour.');
    }

    public function reorder(int $albumId): void
    {
        $this->verifyCsrf();
        Photo::reorder($albumId, array_map('intval', (array) ($this->jsonBody()['ids'] ?? [])));
        json_response(['ok' => true]);
    }

    /** @param array<string, mixed> $photo */
    private function done(array $photo, string $message, bool $error = false): never
    {
        if ($this->wantsJson()) {
            json_response($error ? ['error' => $message] : ['ok' => true], $error ? 422 : 200);
        }
        flash($error ? 'error' : 'success', $message);
        redirect('/admin/albums/' . (int) $photo['album_id'] . '/photos#photo-' . (int) $photo['id']);
    }

    private static function titleFromFilename(string $name): string
    {
        $base = (string) pathinfo($name, PATHINFO_FILENAME);
        return mb_substr(trim((string) preg_replace('/[_\-]+/', ' ', $base)), 0, 190);
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd (48 Mo maximum).',
            UPLOAD_ERR_PARTIAL => 'Envoi interrompu, réessayez.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier reçu.',
            default => 'Erreur serveur lors de l\'envoi (code ' . $code . ').',
        };
    }
}
```

- [ ] **Étape 2 : écrire `templates/admin/partials/photo-card.php`**

```php
<?php
/** @var array $photo @var array $album @var list<array> $albums @var int $heroId */
$id = (int) $photo['id'];
$isCover = (int) ($album['cover_photo_id'] ?? 0) === $id;
$isHero = (int) ($heroId ?? 0) === $id;
$otherAlbums = array_filter($albums, static fn (array $a): bool => (int) $a['id'] !== (int) $photo['album_id']);
?>
<article class="photo-card" data-id="<?= $id ?>" id="photo-<?= $id ?>">
  <div class="photo-card__img">
    <img src="<?= e(media_url($photo['file_key'], 480)) ?>" alt="" loading="lazy">
    <span class="drag-handle" title="Déplacer">⠿</span>
    <span class="photo-card__badges">
      <?php if ($isCover): ?><span class="badge">Couverture</span><?php endif; ?>
      <?php if ($isHero): ?><span class="badge badge--featured">Accueil</span><?php endif; ?>
      <?php if ($photo['lat'] !== null): ?><span class="badge" title="Photo géolocalisée">GPS</span><?php endif; ?>
    </span>
  </div>
  <form method="post" action="/admin/photos/<?= $id ?>" data-autosave>
    <?= csrf_field() ?>
    <input type="text" name="title" maxlength="190" value="<?= e($photo['title']) ?>" placeholder="Titre" aria-label="Titre">
    <textarea name="caption" placeholder="Légende (facultatif)" aria-label="Légende"><?= e($photo['caption']) ?></textarea>
    <noscript><button class="btn btn--small" type="submit">Enregistrer</button></noscript>
    <span class="save-status" aria-live="polite"></span>
  </form>
  <p class="exif-line"><?= e(implode(' · ', array_filter([
      $photo['camera'], $photo['focal_length'], $photo['aperture'], $photo['shutter'],
      $photo['iso'] ? 'ISO ' . $photo['iso'] : null,
  ]))) ?></p>
  <details>
    <summary>Plus d'options</summary>
    <form method="post" action="/admin/photos/<?= $id ?>/gps">
      <?= csrf_field() ?>
      <label>Latitude <input type="text" name="lat" inputmode="decimal" value="<?= e($photo['lat'] ?? '') ?>"></label>
      <label>Longitude <input type="text" name="lng" inputmode="decimal" value="<?= e($photo['lng'] ?? '') ?>"></label>
      <button class="btn btn--ghost btn--small" type="submit">Enregistrer la position</button>
    </form>
    <?php if ($otherAlbums): ?>
    <form method="post" action="/admin/photos/<?= $id ?>/move">
      <?= csrf_field() ?>
      <select name="album_id" aria-label="Déplacer vers l'album">
        <?php foreach ($otherAlbums as $a): ?>
          <option value="<?= (int) $a['id'] ?>"><?= e($a['theme_title'] . ' › ' . $a['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost btn--small" type="submit">Déplacer</button>
    </form>
    <?php endif; ?>
  </details>
  <div class="actions">
    <form method="post" action="/admin/photos/<?= $id ?>/cover"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit">Couverture album</button></form>
    <form method="post" action="/admin/photos/<?= $id ?>/theme-cover"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit">Couverture thème</button></form>
    <form method="post" action="/admin/photos/<?= $id ?>/hero"><?= csrf_field() ?><button class="btn btn--ghost btn--small" type="submit">Photo d'accueil</button></form>
    <form method="post" action="/admin/photos/<?= $id ?>/delete" data-confirm="Supprimer définitivement cette photo ?"><?= csrf_field() ?><button class="btn btn--danger btn--small" type="submit">Supprimer</button></form>
  </div>
</article>
```

- [ ] **Étape 3 : écrire `templates/admin/albums/photos.php`**

```php
<?php /** @var array $album @var list<array> $photos @var list<array> $albums @var int $heroId */ ?>
<div class="toolbar">
  <h1><?= e($album['title']) ?> <span class="badge<?= $album['status'] === 'draft' ? ' badge--draft' : '' ?>"><?= $album['status'] === 'draft' ? 'Brouillon' : 'Publié' ?></span></h1>
  <div class="form-actions">
    <a class="btn btn--ghost" href="/admin/albums/<?= (int) $album['id'] ?>">Infos de l'album</a>
    <a class="btn btn--ghost" href="/<?= e($album['theme_slug']) ?>/<?= e($album['slug']) ?>" target="_blank" rel="noopener">Voir ↗</a>
  </div>
</div>

<label class="dropzone" data-upload="/admin/albums/<?= (int) $album['id'] ?>/photos">
  <input type="file" accept="image/jpeg" multiple>
  <strong>Glissez vos photos ici</strong><br>
  <span class="hint">ou cliquez pour les choisir · JPEG uniquement, 48 Mo maximum par fichier</span>
</label>
<ul class="upload-queue" aria-live="polite"></ul>

<p class="hint">Glissez ⠿ pour réordonner. Titres et légendes s'enregistrent automatiquement.</p>
<div class="photo-grid" data-photo-grid data-sortable="/admin/albums/<?= (int) $album['id'] ?>/photos/reorder" data-handle=".drag-handle">
  <?php foreach ($photos as $photo): ?>
    <?= $view->partial('admin/partials/photo-card', ['photo' => $photo, 'album' => $album, 'albums' => $albums, 'heroId' => $heroId]) ?>
  <?php endforeach; ?>
</div>
```

- [ ] **Étape 4 : écrire `public/assets/js/admin-upload.js`**

```js
(() => {
  const zone = document.querySelector('[data-upload]');
  if (!zone) return;
  const input = zone.querySelector('input[type=file]');
  const queueEl = document.querySelector('.upload-queue');
  const grid = document.querySelector('[data-photo-grid]');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const queue = [];
  let busy = false;

  const fail = (item, message, canRetry) => {
    const { row } = item;
    row.classList.add('is-error');
    row.querySelector('.upload-item__status').textContent = message;
    if (!canRetry) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'link-button';
    btn.textContent = 'Réessayer';
    btn.addEventListener('click', () => {
      btn.remove();
      row.classList.remove('is-error');
      row.querySelector('progress').value = 0;
      row.querySelector('.upload-item__status').textContent = 'En attente';
      queue.push(item);
      next();
    });
    row.appendChild(btn);
  };

  const send = (item) => new Promise((resolve) => {
    const { file, row } = item;
    const status = row.querySelector('.upload-item__status');
    const bar = row.querySelector('progress');
    if (!/^image\/jpe?g$/i.test(file.type)) {
      fail(item, 'Format refusé (JPEG uniquement)', false);
      resolve();
      return;
    }
    const xhr = new XMLHttpRequest();
    const data = new FormData();
    data.append('photo', file);
    xhr.open('POST', zone.dataset.upload);
    xhr.setRequestHeader('X-CSRF-Token', csrf);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = (ev) => {
      if (!ev.lengthComputable) return;
      bar.value = Math.round((ev.loaded / ev.total) * 90);
      status.textContent = 'Envoi…';
    };
    xhr.upload.onload = () => { status.textContent = 'Traitement…'; };
    xhr.onload = () => {
      let res = {};
      try { res = JSON.parse(xhr.responseText); } catch (e) { /* réponse non JSON (erreur serveur) */ }
      if (xhr.status === 201 && res.html) {
        bar.value = 100;
        status.textContent = 'Ajoutée ✓';
        row.classList.add('is-done');
        grid.insertAdjacentHTML('beforeend', res.html);
      } else {
        fail(item, res.error || `Erreur ${xhr.status}`, xhr.status >= 500 || xhr.status === 0);
      }
      resolve();
    };
    xhr.onerror = () => { fail(item, 'Connexion interrompue', true); resolve(); };
    xhr.send(data);
  });

  const next = () => {
    if (busy || queue.length === 0) return;
    busy = true;
    send(queue.shift()).finally(() => { busy = false; next(); });
  };

  const add = (files) => {
    Array.from(files).forEach((file) => {
      const row = document.createElement('li');
      row.className = 'upload-item';
      row.innerHTML = '<span class="upload-item__name"></span><progress max="100" value="0"></progress><span class="upload-item__status">En attente</span>';
      row.querySelector('.upload-item__name').textContent = file.name;
      queueEl.appendChild(row);
      queue.push({ file, row });
    });
    next();
  };

  input.addEventListener('change', () => { add(input.files); input.value = ''; });
  ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (ev) => {
    ev.preventDefault();
    zone.classList.add('is-over');
  }));
  ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (ev) => {
    ev.preventDefault();
    zone.classList.remove('is-over');
  }));
  zone.addEventListener('drop', (ev) => add(ev.dataTransfer.files));
})();
```

- [ ] **Étape 5 : ajouter les routes**

Dans `src/routes.php` : import `use App\Controllers\Admin\PhotoController;` et, après les routes des albums :
```php
$router->post('/admin/albums/{id:\d+}/photos', static fn (string $id) => (new PhotoController($view))->upload((int) $id));
$router->post('/admin/albums/{id:\d+}/photos/reorder', static fn (string $id) => (new PhotoController($view))->reorder((int) $id));
$router->post('/admin/photos/{id:\d+}', static fn (string $id) => (new PhotoController($view))->update((int) $id));
$router->post('/admin/photos/{id:\d+}/gps', static fn (string $id) => (new PhotoController($view))->gps((int) $id));
$router->post('/admin/photos/{id:\d+}/delete', static fn (string $id) => (new PhotoController($view))->delete((int) $id));
$router->post('/admin/photos/{id:\d+}/move', static fn (string $id) => (new PhotoController($view))->move((int) $id));
$router->post('/admin/photos/{id:\d+}/cover', static fn (string $id) => (new PhotoController($view))->cover((int) $id));
$router->post('/admin/photos/{id:\d+}/theme-cover', static fn (string $id) => (new PhotoController($view))->themeCover((int) $id));
$router->post('/admin/photos/{id:\d+}/hero', static fn (string $id) => (new PhotoController($view))->hero((int) $id));
```

- [ ] **Étape 6 : relancer le serveur local avec les limites d'envoi du `.user.ini`**

Le serveur intégré de PHP ne lit pas `.user.ini`. On passe donc les limites en ligne de commande :
```bash
php -d upload_max_filesize=48M -d post_max_size=50M -d memory_limit=512M -S localhost:8000 -t public public/index.php
```

- [ ] **Étape 7 : vérifier dans le navigateur**

1. Ouvrir l'album « Côte d'Opale » et glisser `tests/fixtures/exif-full.jpg`, `tests/fixtures/exif-rotated.jpg`, `tests/fixtures/not-a-jpeg.png`, puis 2 vraies photos de `~/Desktop/PHOTOS`.
2. Les JPEG s'envoient un par un, avec leur barre de progression. Le PNG est refusé avec « Format refusé ». Les cartes apparaissent sans recharger la page.
3. `exif-full` affiche « NIKON D810 · 35 mm · f/8 · 1/500 s · ISO 200 » et le badge GPS. `exif-rotated` est en portrait.
4. `ls public/media/*/` : 6 fichiers par photo (3 tailles × WebP/JPEG).
5. Modifier un titre, cliquer ailleurs : « Enregistré ✓ ». Recharger : le titre est conservé.
6. Réordonner, recharger : l'ordre est conservé.
7. Tester « Couverture album », « Photo d'accueil » (badge Accueil), « Déplacer », la position GPS (avec une virgule, puis vide) et « Supprimer » (les fichiers disparaissent de `public/media`).

- [ ] **Étape 8 : commit**

```bash
git add src/Controllers/Admin/PhotoController.php templates/admin public/assets/js/admin-upload.js src/routes.php
git commit -m "feat: envoi des photos (EXIF, 3 tailles) et gestion dans l'administration

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 17 : administration — contenus (accueil et page Présentation)

**Fichiers :**
- Créer : `src/Services/PresentationImages.php`, `src/Controllers/Admin/ContentController.php`, `templates/admin/content.php`, `bin/set-presentation-images.php`
- Modifier : `src/routes.php`

- [ ] **Étape 1 : écrire `src/Services/PresentationImages.php`**

```php
<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Page;

/** Les 2 images propres à la page Présentation (hors albums). */
final class PresentationImages
{
    public const DIR = '/public/assets/img/presentation';
    public const WIDTHS = [1280, 2560];
    public const SLOTS = ['banner', 'portrait'];

    public static function url(string $key, int $width, string $ext = 'jpg'): string
    {
        return '/assets/img/presentation/' . $key . '-' . $width . '.' . $ext;
    }

    /** Traite la nouvelle image, met à jour la page et supprime l'ancienne. Retourne la nouvelle clé. */
    public static function replace(string $slot, string $source): string
    {
        if (!in_array($slot, self::SLOTS, true)) {
            throw new \InvalidArgumentException("Emplacement inconnu : $slot");
        }
        $dir = ROOT . self::DIR;
        $key = $slot . '-' . bin2hex(random_bytes(4));
        $exif = (new ExifReader())->read($source);
        (new ImageProcessor())->process($source, $dir, $key, $exif['orientation'], self::WIDTHS);

        $column = $slot . '_image';
        $old = Page::find('presentation')[$column] ?? null;
        Page::update('presentation', [$column => $key]);
        if (is_string($old) && $old !== '') {
            ImageProcessor::deleteVariants($dir, $old, self::WIDTHS);
        }
        return $key;
    }
}
```

- [ ] **Étape 2 : écrire `src/Controllers/Admin/ContentController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Page;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\HtmlSanitizer;
use App\Services\ImageException;
use App\Services\PresentationImages;

final class ContentController extends AdminController
{
    public function edit(): void
    {
        $heroId = (int) Setting::get('hero_photo_id', '0');
        $page = Page::find('presentation') ?? throw new \RuntimeException('Page « presentation » absente : lancez php bin/migrate.php');
        $page['body_editable'] = HtmlSanitizer::toEditable((string) $page['body_html']);
        $this->page('admin/content', [
            'title' => 'Contenus',
            'tagline' => (string) Setting::get('site_tagline', ''),
            'skyIntro' => (string) Setting::get('sky_intro', ''),
            'hero' => $heroId > 0 ? Photo::find($heroId) : null,
            'page' => $page,
        ]);
    }

    public function update(): void
    {
        $this->verifyCsrf();
        Setting::set('site_tagline', mb_substr(trim((string) ($_POST['site_tagline'] ?? '')), 0, 150));
        Setting::set('sky_intro', trim((string) ($_POST['sky_intro'] ?? '')));

        $title = trim((string) ($_POST['title'] ?? ''));
        $lead = mb_substr(trim((string) ($_POST['lead'] ?? '')), 0, 300);
        $pilot = trim((string) ($_POST['pilot_info'] ?? ''));
        Page::update('presentation', [
            'title' => $title !== '' ? mb_substr($title, 0, 190) : 'Présentation',
            'lead' => $lead !== '' ? $lead : null,
            'body_html' => HtmlSanitizer::clean(HtmlSanitizer::paragraphs((string) ($_POST['body_html'] ?? ''))),
            'pilot_info' => $pilot !== '' ? $pilot : null,
        ]);

        $errors = [];
        foreach (PresentationImages::SLOTS as $slot) {
            $file = $_FILES[$slot] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'L\'envoi de l\'image « ' . $slot . ' » a échoué.';
                continue;
            }
            try {
                PresentationImages::replace($slot, $file['tmp_name']);
            } catch (ImageException $e) {
                $errors[] = $e->getMessage();
            } finally {
                @unlink($file['tmp_name']);
            }
        }

        $errors ? flash('error', implode(' ', $errors)) : flash('success', 'Contenus enregistrés.');
        redirect('/admin/content');
    }
}
```

- [ ] **Étape 3 : écrire `templates/admin/content.php`**

```php
<?php
/** @var string $tagline @var string $skyIntro @var ?array $hero @var array $page */
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
```

- [ ] **Étape 4 : écrire `bin/set-presentation-images.php`**

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\PresentationImages;

$banner = $argv[1] ?? '';
$portrait = $argv[2] ?? '';
if (!is_file($banner) || !is_file($portrait)) {
    fwrite(STDERR, "Usage : php bin/set-presentation-images.php bandeau.jpg portrait.jpg\n");
    exit(1);
}
ini_set('memory_limit', '1024M');
echo 'Bandeau : ' . PresentationImages::replace('banner', $banner) . "\n";
echo 'Portrait : ' . PresentationImages::replace('portrait', $portrait) . "\n";
```

- [ ] **Étape 5 : ajouter les routes**

Dans `src/routes.php` : import `use App\Controllers\Admin\ContentController;` et :
```php
$router->get('/admin/content', static fn () => (new ContentController($view))->edit());
$router->post('/admin/content', static fn () => (new ContentController($view))->update());
```

- [ ] **Étape 6 : importer les deux photos en local et vérifier**

```bash
php bin/set-presentation-images.php ~/Desktop/PHOTOS/DSC00296-2-Modifier.jpg ~/Desktop/PHOTOS/DSC_29571.jpg
ls public/assets/img/presentation/
```
Attendu : 2 clés affichées, et 8 fichiers (`banner-xxxx-1280/2560.webp/jpg`, `portrait-xxxx-…`).

Dans le navigateur, sur `/admin/content` :
1. les deux aperçus s'affichent ;
2. modifier l'accroche, écrire deux paragraphes et mettre un mot en gras avec le bouton G, puis enregistrer : le message « Contenus enregistrés » s'affiche et, après rechargement, le texte revient avec la ligne vide entre les paragraphes ;
3. écrire `<script>alert(1)</script>` dans le texte et enregistrer : la balise disparaît.

- [ ] **Étape 7 : commit**

```bash
git add src/Services/PresentationImages.php src/Controllers/Admin/ContentController.php templates/admin/content.php bin/set-presentation-images.php src/routes.php
git commit -m "feat: contenus de l'accueil et de la page Présentation dans l'administration

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 18 : pages Thème et Album, mosaïque et visionneuse

**Fichiers :**
- Modifier : `src/Controllers/PublicController.php`, `src/routes.php`
- Créer : `templates/public/theme.php`, `templates/public/album.php`, `public/assets/js/mosaic.js`, `public/assets/js/viewer.js`

- [ ] **Étape 1 : ajouter `theme()`, `album()` et `viewerPhoto()` à `PublicController`**

Ajouter `use App\Services\Auth;` aux imports, puis ces méthodes dans la classe :
```php
    public function theme(string $theme): void
    {
        $t = Theme::findBySlug($theme);
        $isAdmin = Auth::check();
        if ($t === null || ((int) $t['published_albums'] === 0 && !$isAdmin)) {
            $this->notFound();
            return;
        }
        $this->render('public/theme', [
            'meta' => [
                'title' => $t['title'],
                'description' => $t['intro'] ? mb_substr((string) $t['intro'], 0, 160) : null,
                'image' => $t['cover_key'] ? media_path((string) $t['cover_key'], 1280, 'jpg') : null,
            ],
            'theme' => $t,
            'albums' => Album::forTheme((int) $t['id'], !$isAdmin),
        ]);
    }

    public function album(string $theme, string $album, ?string $photo = null): void
    {
        $a = Album::findBySlugs($theme, $album, Auth::check());
        if ($a === null) {
            $this->notFound();
            return;
        }
        $photos = Photo::forAlbum((int) $a['id']);
        $openIndex = null;
        if ($photo !== null) {
            foreach ($photos as $i => $p) {
                if ((int) $p['id'] === (int) $photo) {
                    $openIndex = $i;
                }
            }
            if ($openIndex === null) {
                $this->notFound();
                return;
            }
        }
        $base = '/' . $a['theme_slug'] . '/' . $a['slug'];
        $current = $openIndex !== null ? $photos[$openIndex] : null;
        $imageKey = $current['file_key'] ?? $a['cover_key'];
        $description = $current && $current['caption'] ? (string) $current['caption'] : (string) $a['description'];

        $this->render('public/album', [
            'meta' => [
                'title' => $current ? (($current['title'] !== '' ? $current['title'] . ' — ' : '') . $a['title']) : $a['title'],
                'description' => $description !== '' ? mb_substr($description, 0, 160) : null,
                'image' => $imageKey ? media_path((string) $imageKey, 1280, 'jpg') : null,
                'type' => 'article',
                'jsonld' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'ImageGallery',
                    'name' => $a['title'],
                    'url' => absolute_url($base),
                    'author' => ['@type' => 'Person', 'name' => 'Guillaume Lecomte'],
                ],
            ],
            'album' => $a,
            'photos' => $photos,
            'baseUrl' => $base,
            'openIndex' => $openIndex,
            'viewerPhotos' => array_map(self::viewerPhoto(...), $photos),
            'styles' => [self::LEAFLET_CSS],
            'scripts' => [self::LEAFLET_JS, asset('js/mosaic.js'), asset('js/viewer.js')],
        ]);
    }

    /** @param array<string, mixed> $p */
    private static function viewerPhoto(array $p): array
    {
        $key = (string) $p['file_key'];
        return [
            'id' => (int) $p['id'],
            'title' => (string) $p['title'],
            'caption' => (string) ($p['caption'] ?? ''),
            'webp' => media_url($key, 2560, 'webp'),
            'jpg' => media_url($key, 2560, 'jpg'),
            'webpM' => media_url($key, 1280, 'webp'),
            'jpgM' => media_url($key, 1280, 'jpg'),
            'taken' => $p['taken_at'] ? format_date_fr((string) $p['taken_at']) : null,
            'camera' => $p['camera'],
            'lens' => $p['lens'],
            'focal' => $p['focal_length'],
            'aperture' => $p['aperture'],
            'shutter' => $p['shutter'],
            'iso' => $p['iso'] !== null ? (string) $p['iso'] : null,
            'lat' => $p['lat'] !== null ? (float) $p['lat'] : null,
            'lng' => $p['lng'] !== null ? (float) $p['lng'] : null,
        ];
    }
```

- [ ] **Étape 2 : écrire `templates/public/theme.php`**

```php
<?php /** @var array $theme @var list<array> $albums */ ?>
<header class="page-header">
  <p class="eyebrow">Galerie</p>
  <h1 class="page-title"><?= e($theme['title']) ?></h1>
  <?php if ($theme['intro']): ?><p class="page-intro"><?= nl2br(e($theme['intro'])) ?></p><?php endif; ?>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="section">
  <?php if ($albums): ?>
  <div class="card-grid">
    <?php foreach ($albums as $album): ?>
      <?= $view->partial('partials/album-card', ['album' => $album]) ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="empty">Aucun album publié dans cette galerie pour l'instant.</p>
  <?php endif; ?>
</section>
```

- [ ] **Étape 3 : écrire `templates/public/album.php`**

```php
<?php /** @var array $album @var list<array> $photos @var string $baseUrl @var ?int $openIndex @var list<array> $viewerPhotos */ ?>
<header class="page-header">
  <p class="eyebrow"><a href="/<?= e($album['theme_slug']) ?>"><?= e($album['theme_title']) ?></a></p>
  <h1 class="page-title"><?= e($album['title']) ?></h1>
  <?php $meta = implode(' · ', array_filter([(string) $album['location'], format_date_fr($album['shot_on'])])); ?>
  <?php if ($meta !== ''): ?><p class="page-meta"><?= e($meta) ?></p><?php endif; ?>
  <?php if ($album['description']): ?><p class="page-intro"><?= nl2br(e($album['description'])) ?></p><?php endif; ?>
  <?php if ($album['status'] !== 'published'): ?><p class="draft-badge">Brouillon : visible uniquement par vous</p><?php endif; ?>
  <?= $view->partial('partials/trace') ?>
</header>

<section class="mosaic" data-mosaic>
  <?php foreach ($photos as $i => $p): ?>
    <a class="mosaic__item" href="<?= e($baseUrl . '/photo/' . $p['id']) ?>" data-index="<?= (int) $i ?>"
       data-ratio="<?= e(round((int) $p['width'] / max(1, (int) $p['height']), 4)) ?>">
      <?= $view->partial('partials/picture', [
          'key' => $p['file_key'], 'alt' => $p['title'], 'width' => $p['width'], 'height' => $p['height'],
          'sizes' => '(min-width: 900px) 33vw, 50vw',
      ]) ?>
    </a>
  <?php endforeach; ?>
</section>

<script type="application/json" id="viewer-data"><?= json_encode(
    ['base' => $baseUrl, 'open' => $openIndex, 'photos' => $viewerPhotos],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
) ?></script>
```

- [ ] **Étape 4 : écrire `public/assets/js/mosaic.js`**

```js
(() => {
  const GAP = 8;

  const layout = (container) => {
    const items = Array.from(container.querySelectorAll('.mosaic__item'));
    const width = container.clientWidth - parseFloat(getComputedStyle(container).paddingLeft) - parseFloat(getComputedStyle(container).paddingRight);
    if (!width || items.length === 0) return;
    const target = width < 640 ? 160 : 280;
    let row = [];
    let ratios = 0;

    const place = (height) => {
      row.forEach((el) => {
        el.style.width = `${Math.floor(height * (parseFloat(el.dataset.ratio) || 1))}px`;
        el.style.height = `${Math.floor(height)}px`;
      });
      row = [];
      ratios = 0;
    };

    items.forEach((el) => {
      row.push(el);
      ratios += parseFloat(el.dataset.ratio) || 1;
      const height = (width - GAP * (row.length - 1)) / ratios;
      if (height <= target) place(height);
    });
    if (row.length) place(Math.min(target, (width - GAP * (row.length - 1)) / ratios));
  };

  const containers = document.querySelectorAll('[data-mosaic]');
  const run = () => containers.forEach((c) => { c.classList.add('is-justified'); layout(c); });
  run();
  let timer;
  window.addEventListener('resize', () => { clearTimeout(timer); timer = setTimeout(run, 120); });
})();
```

- [ ] **Étape 5 : écrire `public/assets/js/viewer.js`**

```js
(() => {
  const dataEl = document.getElementById('viewer-data');
  if (!dataEl) return;
  const data = JSON.parse(dataEl.textContent);
  const photos = data.photos;
  if (!photos.length) return;

  let index = -1;
  let pushed = false;
  let miniMap = null;
  let marker = null;

  const root = document.createElement('div');
  root.className = 'viewer';
  root.hidden = true;
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-modal', 'true');
  root.setAttribute('aria-label', 'Visionneuse de photos');
  root.innerHTML = `
    <button class="viewer__btn viewer__close" type="button" aria-label="Fermer">×</button>
    <button class="viewer__btn viewer__info-toggle" type="button" aria-label="Informations sur la photo" aria-expanded="false">i</button>
    <button class="viewer__btn viewer__prev" type="button" aria-label="Photo précédente">‹</button>
    <button class="viewer__btn viewer__next" type="button" aria-label="Photo suivante">›</button>
    <figure class="viewer__stage">
      <picture><source type="image/webp"><img alt=""></picture>
      <figcaption class="viewer__caption"></figcaption>
    </figure>
    <aside class="viewer__info" hidden>
      <h2 class="viewer__title"></h2>
      <p class="viewer__text"></p>
      <dl class="viewer__exif"></dl>
      <div class="viewer__map" hidden></div>
    </aside>`;
  document.body.appendChild(root);

  const $ = (sel) => root.querySelector(sel);
  const img = $('img');
  const source = $('source');
  const info = $('.viewer__info');
  const toggle = $('.viewer__info-toggle');
  const mapBox = $('.viewer__map');
  const large = () => window.innerWidth * (window.devicePixelRatio || 1) > 1400;
  const urlFor = (i) => `${data.base}/photo/${photos[i].id}`;

  const updateMap = (p) => {
    const hasGps = p.lat !== null && p.lng !== null;
    mapBox.hidden = !hasGps;
    if (!hasGps || info.hidden || !window.L) return;
    if (!miniMap) {
      miniMap = L.map(mapBox, { zoomControl: false, scrollWheelZoom: false });
      L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap, © CARTO', maxZoom: 18,
      }).addTo(miniMap);
      marker = L.circleMarker([p.lat, p.lng], { radius: 7, color: '#1b1b1a', weight: 2, fillColor: '#f4f1ec', fillOpacity: 1 }).addTo(miniMap);
    }
    marker.setLatLng([p.lat, p.lng]);
    miniMap.setView([p.lat, p.lng], 11);
    setTimeout(() => miniMap.invalidateSize(), 50);
  };

  const preload = (i) => {
    const p = photos[(i + photos.length) % photos.length];
    const im = new Image();
    im.src = large() ? p.webp : p.webpM;
  };

  const show = (i) => {
    index = (i + photos.length) % photos.length;
    const p = photos[index];
    source.srcset = large() ? p.webp : p.webpM;
    img.src = large() ? p.jpg : p.jpgM;
    img.alt = p.title || '';
    $('.viewer__caption').textContent = p.title || '';
    $('.viewer__title').textContent = p.title || '';
    $('.viewer__text').textContent = p.caption || '';
    const dl = $('.viewer__exif');
    dl.replaceChildren();
    [['Date', p.taken], ['Appareil', p.camera], ['Objectif', p.lens], ['Focale', p.focal],
      ['Ouverture', p.aperture], ['Vitesse', p.shutter], ['ISO', p.iso]]
      .filter(([, v]) => v)
      .forEach(([k, v]) => {
        const dt = document.createElement('dt');
        dt.textContent = k;
        const dd = document.createElement('dd');
        dd.textContent = v;
        dl.append(dt, dd);
      });
    updateMap(p);
    preload(index + 1);
    preload(index - 1);
    if (root.hidden) {
      root.hidden = false;
      document.body.classList.add('viewer-open');
      $('.viewer__close').focus();
    }
  };

  const hide = () => {
    root.hidden = true;
    document.body.classList.remove('viewer-open');
    index = -1;
  };

  const open = (i) => {
    show(i);
    history.pushState({ viewer: true }, '', urlFor(index));
    pushed = true;
  };

  const go = (delta) => {
    show(index + delta);
    history.replaceState({ viewer: true }, '', urlFor(index));
  };

  const close = () => {
    if (pushed) {
      pushed = false;
      history.back();
    } else {
      hide();
      history.replaceState({}, '', data.base);
    }
  };

  window.addEventListener('popstate', () => {
    const m = location.pathname.match(/\/photo\/(\d+)$/);
    const i = m ? photos.findIndex((p) => String(p.id) === m[1]) : -1;
    if (i >= 0) {
      show(i);
    } else {
      hide();
      pushed = false;
    }
  });

  document.querySelectorAll('.mosaic__item').forEach((a) => a.addEventListener('click', (ev) => {
    if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button !== 0) return;
    ev.preventDefault();
    open(parseInt(a.dataset.index, 10));
  }));
  $('.viewer__close').addEventListener('click', close);
  $('.viewer__prev').addEventListener('click', () => go(-1));
  $('.viewer__next').addEventListener('click', () => go(1));
  toggle.addEventListener('click', () => {
    info.hidden = !info.hidden;
    toggle.setAttribute('aria-expanded', String(!info.hidden));
    updateMap(photos[index]);
  });
  document.addEventListener('keydown', (ev) => {
    if (root.hidden) return;
    if (ev.key === 'Escape') close();
    else if (ev.key === 'ArrowLeft') go(-1);
    else if (ev.key === 'ArrowRight') go(1);
    else if (ev.key === 'i') toggle.click();
  });
  let startX = null;
  $('.viewer__stage').addEventListener('touchstart', (ev) => { startX = ev.touches[0].clientX; }, { passive: true });
  $('.viewer__stage').addEventListener('touchend', (ev) => {
    if (startX === null) return;
    const dx = ev.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
    startX = null;
  });

  if (Number.isInteger(data.open)) show(data.open);
})();
```

- [ ] **Étape 6 : ajouter les routes publiques** (à la **fin** du bloc public, dans cet ordre)

```php
$router->get('/{theme}/{album}/photo/{photo:\d+}', static fn (string $theme, string $album, string $photo) => $public()->album($theme, $album, $photo));
$router->get('/{theme}/{album}', static fn (string $theme, string $album) => $public()->album($theme, $album));
$router->get('/{theme}', static fn (string $theme) => $public()->theme($theme));
```

- [ ] **Étape 7 : vérifier dans le navigateur**

1. `/paysages` affiche la carte de l'album « Côte d'Opale » (publié).
2. `/paysages/cote-d-opale` affiche des rangées justifiées alignées à droite. En redimensionnant la fenêtre, la mise en page se recalcule. La photo portrait n'est pas recadrée.
3. Cliquer une photo : la visionneuse s'ouvre et l'adresse devient `/…/photo/{id}`. ← et → font défiler, et l'adresse suit. La touche « i » ouvre le panneau d'infos (EXIF et mini-carte pour `exif-full`). Échap ferme et rend l'adresse de l'album. Le bouton Retour du navigateur ferme aussi la visionneuse.
4. Ouvrir directement `/paysages/cote-d-opale/photo/{id}` : la visionneuse est ouverte au chargement.
5. Passer l'album en brouillon : il s'affiche avec la mention « Brouillon » quand on est connecté, et renvoie une 404 en navigation privée.
6. En mode mobile (outils de développement, 390 px) : un glissement du doigt fait défiler, et le panneau d'infos s'ouvre en bas.

- [ ] **Étape 8 : commit**

```bash
git add src/Controllers/PublicController.php src/routes.php templates/public/theme.php templates/public/album.php public/assets/js/mosaic.js public/assets/js/viewer.js
git commit -m "feat: pages thème et album, mosaïque justifiée et visionneuse

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 19 : carte et page Présentation

**Fichiers :**
- Modifier : `src/Controllers/PublicController.php`, `src/routes.php`
- Créer : `templates/public/map.php`, `templates/public/presentation.php`, `public/assets/js/map.js`

- [ ] **Étape 1 : ajouter `map()`, `mapData()` et `presentation()` à `PublicController`**

Ajouter `use App\Models\Page;` et `use App\Services\PresentationImages;` aux imports, puis :
```php
    public function map(): void
    {
        $this->render('public/map', [
            'meta' => ['title' => 'Carte des photos', 'description' => 'Les lieux de mes photos sur une carte : paysages, villes et vues du ciel.'],
            'styles' => [self::LEAFLET_CSS, self::CLUSTER_CSS, self::CLUSTER_DEFAULT_CSS],
            'scripts' => [self::LEAFLET_JS, self::CLUSTER_JS, asset('js/map.js')],
        ]);
    }

    public function mapData(): void
    {
        header('Cache-Control: public, max-age=300');
        json_response(array_map(static fn (array $p): array => [
            'lat' => (float) $p['lat'],
            'lng' => (float) $p['lng'],
            'title' => (string) $p['title'],
            'album' => (string) $p['album_title'],
            'thumb' => media_url((string) $p['file_key'], 480),
            'url' => '/' . $p['theme_slug'] . '/' . $p['album_slug'] . '/photo/' . $p['id'],
        ], Photo::geotagged()));
    }

    public function presentation(): void
    {
        $page = Page::find('presentation') ?? [
            'title' => 'Présentation', 'lead' => null, 'body_html' => '', 'pilot_info' => null,
            'banner_image' => null, 'portrait_image' => null,
        ];
        $this->render('public/presentation', [
            'meta' => [
                'title' => $page['title'],
                'description' => $page['lead'],
                'image' => $page['banner_image'] ? ltrim(PresentationImages::url((string) $page['banner_image'], 1280), '/') : null,
                'type' => 'profile',
                'jsonld' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Person',
                    'name' => 'Guillaume Lecomte',
                    'jobTitle' => 'Photographe amateur et pilote ULM',
                    'url' => absolute_url('/'),
                ],
            ],
            'page' => $page,
        ]);
    }
```

- [ ] **Étape 2 : écrire `templates/public/map.php`**

```php
<header class="page-header">
  <h1 class="page-title">Carte</h1>
  <p class="page-intro">Chaque point est une photo prise à cet endroit. Zoomez, cliquez, partez en balade.</p>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="map-page">
  <div id="map" role="region" aria-label="Carte des photos"></div>
  <noscript><p class="empty">La carte nécessite JavaScript.</p></noscript>
</section>
```

- [ ] **Étape 3 : écrire `public/assets/js/map.js`**

```js
(() => {
  const el = document.getElementById('map');
  if (!el || !window.L) return;
  const map = L.map(el).setView([46.6, 2.4], 6);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, © <a href="https://carto.com/attributions">CARTO</a>',
    maxZoom: 19,
  }).addTo(map);
  const cluster = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });

  fetch('/carte.json')
    .then((r) => r.json())
    .then((photos) => {
      photos.forEach((p) => {
        const marker = L.circleMarker([p.lat, p.lng], { radius: 7, color: '#1b1b1a', weight: 2, fillColor: '#f4f1ec', fillOpacity: 1 });
        const box = document.createElement('a');
        box.className = 'map-popup';
        box.href = p.url;
        const img = document.createElement('img');
        img.src = p.thumb;
        img.alt = p.title || '';
        img.loading = 'lazy';
        const title = document.createElement('span');
        title.textContent = p.title || p.album;
        const album = document.createElement('small');
        album.textContent = p.album;
        box.append(img, title, album);
        marker.bindPopup(box, { maxWidth: 240 });
        cluster.addLayer(marker);
      });
      map.addLayer(cluster);
      if (photos.length) map.fitBounds(cluster.getBounds(), { padding: [40, 40], maxZoom: 12 });
    });
})();
```

- [ ] **Étape 4 : écrire `templates/public/presentation.php`**

```php
<?php
/** @var array $page */
use App\Services\PresentationImages;

$picture = static function (?string $key, string $sizes, string $alt, string $class, string $loading = 'lazy'): string {
    if (!$key) {
        return '';
    }
    $set = static fn (string $ext): string => PresentationImages::url($key, 1280, $ext) . ' 1280w, ' . PresentationImages::url($key, 2560, $ext) . ' 2560w';
    return '<picture><source type="image/webp" srcset="' . e($set('webp')) . '" sizes="' . e($sizes) . '">'
        . '<img src="' . e(PresentationImages::url($key, 1280)) . '" srcset="' . e($set('jpg')) . '" sizes="' . e($sizes) . '"'
        . ' alt="' . e($alt) . '" class="' . e($class) . '" loading="' . e($loading) . '" decoding="async"></picture>';
};

$pilot = [];
foreach (preg_split('/\R/', (string) $page['pilot_info']) ?: [] as $line) {
    $parts = array_map('trim', explode(':', $line, 2));
    if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
        $pilot[] = $parts;
    }
}
?>
<?php if ($page['banner_image']): ?>
<section class="banner">
  <?= $picture($page['banner_image'], '100vw', 'Guillaume Lecomte photographiant sur le tarmac d\'un aéroport', 'banner__img', 'eager') ?>
</section>
<?php endif; ?>

<header class="page-header">
  <h1 class="page-title"><?= e($page['title']) ?></h1>
  <?php if ($page['lead']): ?><p class="lead"><?= e($page['lead']) ?></p><?php endif; ?>
  <?= $view->partial('partials/trace') ?>
</header>

<section class="about">
  <?php if ($page['portrait_image']): ?>
  <div class="about__portrait">
    <?= $picture($page['portrait_image'], '(min-width: 760px) 40vw, 100vw', 'Portrait de Guillaume Lecomte, appareil photo en main', 'about__img') ?>
  </div>
  <?php endif; ?>
  <div class="about__body prose">
    <?= $page['body_html'] /* HTML filtré par HtmlSanitizer à l'enregistrement */ ?>
    <?php if ($pilot): ?>
    <aside class="pilot">
      <h2 class="pilot__title">Côté pilote</h2>
      <dl>
        <?php foreach ($pilot as [$label, $value]): ?>
          <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
        <?php endforeach; ?>
      </dl>
    </aside>
    <?php endif; ?>
    <p><a class="link-arrow" href="/contact">Me contacter</a></p>
  </div>
</section>
```

- [ ] **Étape 5 : ajouter les routes** (avant les routes `/{theme}…`)

```php
$router->get('/carte', static fn () => $public()->map());
$router->get('/carte.json', static fn () => $public()->mapData());
$router->get('/presentation', static fn () => $public()->presentation());
```

- [ ] **Étape 6 : vérifier**

```bash
curl -s http://localhost:8000/carte.json
```
Attendu : un tableau JSON contenant la photo `exif-full` (lat 50.29…, lng 2.77…), si son album est publié.

Dans le navigateur :
1. `/carte` : la carte est cadrée sur Arras, avec un marqueur. Un clic affiche la vignette, le titre et l'album, et la vignette mène à la photo dans la visionneuse.
2. `/presentation` : le bandeau noir et blanc est en pleine largeur et cadre bien le visage et l'appareil. On voit ensuite le titre, l'accroche et la trace, puis le portrait à gauche et le texte à droite, l'encart « Côté pilote » avec ses 3 lignes provisoires, et le lien de contact.
3. En 390 px : le portrait passe au-dessus du texte.

- [ ] **Étape 7 : commit**

```bash
git add src/Controllers/PublicController.php src/routes.php templates/public/map.php templates/public/presentation.php public/assets/js/map.js
git commit -m "feat: carte des photos et page Présentation

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 20 : contact, sitemap et robots.txt

**Fichiers :**
- Créer : `src/Controllers/ContactController.php`, `templates/public/contact.php`
- Modifier : `src/Controllers/PublicController.php`, `src/routes.php`

- [ ] **Étape 1 : écrire `src/Controllers/ContactController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ContactForm;
use App\Services\Env;
use App\Services\Mailer;
use App\Services\RateLimiter;

final class ContactController extends Controller
{
    private const MAX_PER_HOUR = 3;

    public function show(): void
    {
        $this->page(['sent' => isset($_GET['envoye'])]);
    }

    public function send(): void
    {
        $key = (string) Env::get('APP_KEY', '');
        $result = ContactForm::validate($_POST, $key, time());

        if ($result['spam']) {
            // On ne dit rien au robot : on fait comme si l'envoi avait réussi
            redirect('/contact?envoye=1');
        }
        if ($result['errors']) {
            $this->page(['errors' => $result['errors'], 'old' => $result['data']], 422);
            return;
        }

        $limiter = new RateLimiter(ROOT . '/storage/ratelimit');
        $rateKey = 'contact:' . client_ip();
        if ($limiter->tooMany($rateKey, self::MAX_PER_HOUR, 3600)) {
            $this->page(['errors' => ['global' => 'Vous avez déjà envoyé plusieurs messages. Réessayez dans une heure.'], 'old' => $result['data']], 429);
            return;
        }

        $d = $result['data'];
        $body = "Nom : {$d['name']}\nE-mail : {$d['email']}\n\n{$d['message']}\n\n— Envoyé depuis le formulaire de contact de guillaumelecomte.net";
        $sent = Mailer::fromEnv()->send((string) Env::get('NOTIFY_EMAIL', ''), 'Contact site — ' . $d['name'], $body, $d['email']);
        if (!$sent) {
            $this->page(['errors' => ['global' => 'L\'envoi a échoué. Réessayez un peu plus tard.'], 'old' => $d], 502);
            return;
        }
        $limiter->hit($rateKey, 3600);
        redirect('/contact?envoye=1');
    }

    /** @param array<string, mixed> $data */
    private function page(array $data, int $status = 200): void
    {
        $this->render('public/contact', $data + [
            'meta' => ['title' => 'Contact', 'description' => 'Écrire à Guillaume Lecomte, photographe et pilote ULM.'],
            'sent' => false,
            'errors' => [],
            'old' => [],
            'ts' => ContactForm::token((string) Env::get('APP_KEY', ''), time()),
        ], $status);
    }
}
```

- [ ] **Étape 2 : écrire `templates/public/contact.php`**

```php
<?php /** @var bool $sent @var array $errors @var array $old @var string $ts */ ?>
<header class="page-header">
  <h1 class="page-title">Contact</h1>
  <p class="page-intro">Une question sur une photo, une envie d'échanger sur l'aviation ou la photographie ? Écrivez-moi.</p>
  <?= $view->partial('partials/trace') ?>
</header>
<section class="section narrow">
<?php if ($sent): ?>
  <p class="notice">Merci, votre message est bien parti. Je vous réponds dès que possible.</p>
<?php else: ?>
  <?php if (!empty($errors['global'])): ?><p class="notice notice--error"><?= e($errors['global']) ?></p><?php endif; ?>
  <form method="post" action="/contact" class="form" novalidate>
    <input type="hidden" name="ts" value="<?= e($ts) ?>">
    <div class="hp" aria-hidden="true">
      <label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <label class="field">Nom
      <input type="text" name="name" maxlength="100" required autocomplete="name" value="<?= e($old['name'] ?? '') ?>">
      <?php if (!empty($errors['name'])): ?><span class="field__error"><?= e($errors['name']) ?></span><?php endif; ?>
    </label>
    <label class="field">E-mail
      <input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
      <?php if (!empty($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
    </label>
    <label class="field">Message
      <textarea name="message" maxlength="5000" required><?= e($old['message'] ?? '') ?></textarea>
      <?php if (!empty($errors['message'])): ?><span class="field__error"><?= e($errors['message']) ?></span><?php endif; ?>
    </label>
    <button class="button" type="submit">Envoyer</button>
  </form>
<?php endif; ?>
</section>
```

- [ ] **Étape 3 : ajouter `sitemap()` et `robots()` à `PublicController`**

Ajouter `use App\Services\Env;` aux imports, puis :
```php
    public function sitemap(): void
    {
        $urls = ['/', '/presentation', '/carte', '/contact'];
        foreach (Theme::visible() as $t) {
            $urls[] = '/' . $t['slug'];
        }
        foreach (Album::allPublished() as $a) {
            $urls[] = '/' . $a['theme_slug'] . '/' . $a['slug'];
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '  <url><loc>' . e(absolute_url($u)) . "</loc></url>\n";
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (Env::get('APP_ENV') !== 'prod') {
            echo "User-agent: *\nDisallow: /\n";
            return;
        }
        echo "User-agent: *\nDisallow: /admin\n\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
    }
```

- [ ] **Étape 4 : ajouter les routes** (avant les routes `/{theme}…`)

Import : `use App\Controllers\ContactController;`
```php
$router->get('/contact', static fn () => (new ContactController($view))->show());
$router->post('/contact', static fn () => (new ContactController($view))->send());
$router->get('/sitemap.xml', static fn () => $public()->sitemap());
$router->get('/robots.txt', static fn () => $public()->robots());
```

- [ ] **Étape 5 : vérifier**

```bash
curl -s http://localhost:8000/robots.txt
curl -s http://localhost:8000/sitemap.xml
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" -X POST -d "name=x&email=a@b.c&message=bonjour%20bonjour%20bonjour&website=spam&ts=1" http://localhost:8000/contact
```
Attendu : `Disallow: /` (APP_ENV=dev) ; un sitemap qui liste `/`, `/presentation`, `/carte`, `/contact`, `/paysages` et `/paysages/cote-d-opale` ; `303 …/contact?envoye=1` pour le robot.

Dans le navigateur, sur `/contact` :
1. envoyer le formulaire vide : les 3 messages d'erreur s'affichent et les champs sont conservés ;
2. envoyer en moins de 3 secondes : on obtient une fausse confirmation, et rien n'est envoyé ;
3. l'envoi réel sera testé sur le dev, où se trouvent les identifiants SMTP (tâche 22).

- [ ] **Étape 6 : lancer tous les tests**

Commande : `composer test`
Attendu : `OK`.

- [ ] **Étape 7 : commit**

```bash
git add src/Controllers/ContactController.php src/Controllers/PublicController.php templates/public/contact.php src/routes.php
git commit -m "feat: formulaire de contact, sitemap et robots.txt

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 21 : script de déploiement

**Fichiers :**
- Créer : `deploy-ssh.sh`

- [ ] **Étape 1 : écrire `deploy-ssh.sh`**

```bash
#!/bin/bash
#
# Déploiement du portfolio vers O2switch.
# Usage : ./deploy-ssh.sh dev|prod [--dry-run]
#
set -euo pipefail

TARGET="${1:-}"
DRY="${2:-}"
case "$TARGET" in
  dev)  REMOTE_DIR="/home/legu7203/portfolio-dev" ;;
  prod) REMOTE_DIR="/home/legu7203/portfolio" ;;
  *) echo "Usage : $0 dev|prod [--dry-run]"; exit 1 ;;
esac

HOST="legu7203@legu7203.odns.fr"
KEY="$HOME/.ssh/id_ed25519"
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)"
SOCKET="$HOME/.ssh/sockets/legu7203-portfolio"
mkdir -p "$HOME/.ssh/sockets"
SSH="ssh -i $KEY -o ControlMaster=auto -o ControlPath=$SOCKET -o ControlPersist=120"
trap 'ssh -O exit -o ControlPath="$SOCKET" "$HOST" 2>/dev/null || true' EXIT

if [[ "$TARGET" == "prod" && "$DRY" != "--dry-run" ]]; then
  read -r -p "Déployer en PRODUCTION ? (oui/non) " answer
  [[ "$answer" == "oui" ]] || exit 1
fi

cd "$LOCAL_DIR"
echo "▶ Tests"
composer test

echo "▶ Synchronisation vers $TARGET ($REMOTE_DIR)"
rsync -az --delete ${DRY:+--dry-run} -i -e "$SSH" \
  --exclude '.git/' --exclude '.github/' --exclude '.env' --exclude '.DS_Store' \
  --exclude 'vendor/' --exclude 'tests/' --exclude 'docs/' --exclude '.phpunit.cache/' \
  --exclude '.superpowers/' --exclude '.claude/' --exclude '.worktrees/' \
  --include 'public/media/.htaccess' --exclude 'public/media/*' \
  --exclude 'public/assets/img/presentation/*' \
  --exclude 'public/.well-known/' \
  --exclude 'storage/logs/*' --exclude 'storage/ratelimit/*' \
  ./ "$HOST:$REMOTE_DIR/"

if [[ -n "$DRY" ]]; then
  echo "Simulation terminée (rien n'a été modifié)."
  exit 0
fi

echo "▶ Migrations"
$SSH "$HOST" "cd $REMOTE_DIR && php bin/migrate.php"
echo "✓ Déployé sur $TARGET"
```

- [ ] **Étape 2 : rendre le script exécutable et le simuler**

```bash
chmod +x deploy-ssh.sh
./deploy-ssh.sh dev --dry-run
```
Attendu : les tests passent, puis rsync liste les fichiers qu'il **enverrait** (`<f+++++++++`) vers `/home/legu7203/portfolio-dev/`. Aucun fichier de `tests/`, `docs/`, `vendor/` ou `.env` ne doit figurer dans la liste.

- [ ] **Étape 3 : commit**

```bash
git add deploy-ssh.sh
git commit -m "chore: script de déploiement dev/prod

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tâche 22 : mise en place sur dev.guillaumelecomte.net

Toutes les commandes passent par `ssh -i ~/.ssh/id_ed25519 legu7203@legu7203.odns.fr`, abrégé `SSH` ci-dessous. Limiter les appels `curl` vers le serveur, car TigerProtect d'O2switch renvoie une erreur 429 en cas de rafale.

- [ ] **Étape 1 : créer la base de données et son utilisateur** (le mot de passe est généré sur le serveur et n'est jamais affiché)

```bash
SSH 'set -e
PASS=$(openssl rand -hex 20)
uapi Mysql create_database name=legu7203_portfolio_dev >/dev/null
uapi Mysql create_user name=legu7203_pfdev password="$PASS" >/dev/null
uapi Mysql set_privileges_on_database user=legu7203_pfdev database=legu7203_portfolio_dev privileges=ALL%20PRIVILEGES >/dev/null
mkdir -p ~/portfolio-dev
OLD=$(mktemp); tar xzf ~/backups_db/phpphotos-flickr-2026-11-13.tgz -O phpphotos/.env > "$OLD"
{
  echo "APP_ENV=dev"
  echo "APP_DEBUG=0"
  echo "APP_URL=https://dev.guillaumelecomte.net"
  echo "APP_KEY=$(openssl rand -hex 32)"
  echo "DB_HOST=localhost"
  echo "DB_NAME=legu7203_portfolio_dev"
  echo "DB_USER=legu7203_pfdev"
  echo "DB_PASS=$PASS"
  grep -E "^(MAIL_HOST|MAIL_PORT|MAIL_USER|MAIL_PASS|NOTIFY_EMAIL)=" "$OLD"
} > ~/portfolio-dev/.env
rm -f "$OLD"; chmod 600 ~/portfolio-dev/.env
sed -E "s/^(DB_PASS|MAIL_PASS|APP_KEY)=.*/\1=***/" ~/portfolio-dev/.env'
```
Attendu : le `.env` s'affiche avec les secrets masqués, et les 5 variables MAIL et NOTIFY sont présentes. S'il en manque, demander les valeurs à Guillaume.

- [ ] **Étape 2 : premier déploiement**

```bash
./deploy-ssh.sh dev
```
Attendu : tests OK, synchronisation, puis `OK schema.sql`, `OK seed.sql` et `✓ Déployé sur dev`.

- [ ] **Étape 3 : faire pointer le sous-domaine sur `public/`**

```bash
SSH 'set -e; cd ~
cp -a dev.guillaumelecomte.net/.well-known portfolio-dev/public/ 2>/dev/null || true
mv dev.guillaumelecomte.net dev.guillaumelecomte.net.old
ln -s /home/legu7203/portfolio-dev/public dev.guillaumelecomte.net
ls -la dev.guillaumelecomte.net'
curl -s -o /dev/null -w "%{http_code}\n" https://dev.guillaumelecomte.net/
curl -s https://dev.guillaumelecomte.net/robots.txt
```
Attendu : `200`, puis `Disallow: /`.

Si on obtient 403 ou 404 (LiteSpeed refuse le lien symbolique), annuler avec `SSH 'cd ~ && rm dev.guillaumelecomte.net && mv dev.guillaumelecomte.net.old dev.guillaumelecomte.net'`. Demander ensuite à Guillaume de changer la racine du sous-domaine dans cPanel : **Domaines → dev.guillaumelecomte.net → Gérer → Racine du document : `portfolio-dev/public`**.

- [ ] **Étape 4 : vérifier les limites d'envoi (`.user.ini`)**

Créer le compte admin (étape 5), puis ouvrir `/admin`. Le tableau de bord doit afficher « fichier 48M, requête 50M, mémoire 512M ». LiteSpeed met en cache `.user.ini` pendant 5 minutes au maximum : attendre puis recharger si les anciennes valeurs (12M) s'affichent encore.

- [ ] **Étape 5 : créer le compte admin du dev** (interactif, à lancer par Guillaume)

```bash
ssh -t -i ~/.ssh/id_ed25519 legu7203@legu7203.odns.fr 'cd ~/portfolio-dev && php bin/create-admin.php <ton-email>'
```

- [ ] **Étape 6 : importer les photos de la page Présentation**

```bash
scp -i ~/.ssh/id_ed25519 ~/Desktop/PHOTOS/DSC00296-2-Modifier.jpg ~/Desktop/PHOTOS/DSC_29571.jpg legu7203@legu7203.odns.fr:portfolio-dev/storage/
SSH 'cd ~/portfolio-dev && php bin/set-presentation-images.php storage/DSC00296-2-Modifier.jpg storage/DSC_29571.jpg && rm storage/DSC00296-2-Modifier.jpg storage/DSC_29571.jpg'
```
Attendu : `Bandeau : banner-…` et `Portrait : portrait-…`.

- [ ] **Étape 7 : recette complète sur le dev** (navigateur, avec l'aide de Guillaume)

1. https://dev.guillaumelecomte.net/presentation affiche le bandeau, le portrait et le texte provisoire.
2. Dans l'administration, créer 2 thèmes, dont « Vues du ciel » mis en avant, puis un album, y envoyer 3 vraies photos de 20 à 40 Mo, le publier et choisir une photo d'accueil.
3. L'accueil montre la photo d'ouverture, la section *Vues du ciel*, les derniers albums et les galeries.
4. La visionneuse, la carte et `/sitemap.xml` fonctionnent.
5. **Contact :** envoyer un vrai message, après au moins 3 secondes sur la page. Il doit arriver à `NOTIFY_EMAIL`, et « Répondre » doit viser l'adresse du visiteur. Si l'envoi échoue, lire `SSH 'tail -n 20 ~/portfolio-dev/storage/logs/app.log'`. Si on y lit une erreur de certificat TLS, mettre dans `.env` `MAIL_HOST=pluie.o2switch.net`, le nom du serveur couvert par le certificat.
6. Vérifier les en-têtes : `curl -sI https://dev.guillaumelecomte.net/ | grep -i x-robots` doit afficher `noindex`.

- [ ] **Étape 8 : nettoyer l'ancien dossier et mettre à jour la mémoire du projet**

```bash
SSH 'rm -rf ~/dev.guillaumelecomte.net.old'
```
Puis noter dans la mémoire du projet : le code est dans `~/portfolio-dev`, `dev.guillaumelecomte.net` est un lien symbolique vers `portfolio-dev/public`, la base est `legu7203_portfolio_dev`, et on déploie avec `./deploy-ssh.sh dev`.

- [ ] **Étape 9 : commit final et point d'étape**

```bash
git status
git log --oneline portfolio-autonome ^main | head -30
```
Présenter à Guillaume le site de dev et la liste des commits. La mise en production (base `legu7203_portfolio`, `~/portfolio`, remplacement de la page d'attente) fera l'objet d'une étape séparée, une fois le dev validé.
