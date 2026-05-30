# Commentaires & Livre d'or — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un système de commentaires visiteurs sur les albums et une page livre d'or, avec notification email SMTP et anti-spam honeypot.

**Architecture:** Table MySQL unique `comments` (context_type + context_id), soumission via `comment_submit.php`, fragments réutilisables dans `includes/`, SMTP SSL port 465 via socket PHP natif.

**Tech Stack:** PHP 8+, PDO/MariaDB, SMTP SSL socket natif, HTML/CSS

---

## Carte des fichiers

| Fichier | Action | Rôle |
|---|---|---|
| `.env` | Modifier | Ajouter credentials DB et SMTP |
| `config.php` | Modifier | Charger et définir constantes DB/SMTP |
| `db.php` | Créer | Retourne une instance PDO connectée |
| `lib/Mailer.php` | Créer | Envoi SMTP SSL via socket PHP natif |
| `comment_submit.php` | Créer | POST handler : valide, insère, notifie, redirige |
| `includes/comment_form.php` | Créer | Fragment formulaire (album/guestbook) |
| `includes/comment_list.php` | Créer | Fragment liste de commentaires |
| `guestbook.php` | Créer | Page livre d'or complète |
| `gallery.php` | Modifier | Inclure commentaires en bas de page |
| `index.php` | Modifier | Lien "Livre d'or" dans la nav hero |
| `collection.php` | Modifier | Lien "Livre d'or" dans la nav |
| `map.php` | Modifier | Lien "Livre d'or" dans la nav |
| `css/style.css` | Modifier | Styles section commentaires |

---

## Tâche 1 : Créer la table MySQL

**Fichiers :**
- Aucun fichier PHP — exécution SQL directe sur le serveur

- [ ] **Étape 1 : Créer la table via SSH**

```bash
ssh -i ~/.ssh/id_ed25519 -p 22 legu7203@legu7203.odns.fr \
  "mariadb -u legu7203_phpphotos -p'0}b0r#M1XcV+;$Wu' legu7203_phpphotos -e \"
CREATE TABLE IF NOT EXISTS comments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  context_type ENUM('album','photo','guestbook') NOT NULL,
  context_id   VARCHAR(64) NOT NULL DEFAULT '',
  author_name  VARCHAR(100) NOT NULL,
  author_city  VARCHAR(100) DEFAULT NULL,
  message      TEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_context (context_type, context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
\""
```

- [ ] **Étape 2 : Vérifier la table**

```bash
ssh -i ~/.ssh/id_ed25519 -p 22 legu7203@legu7203.odns.fr \
  "mariadb -u legu7203_phpphotos -p'0}b0r#M1XcV+;$Wu' legu7203_phpphotos -e 'DESCRIBE comments;'"
```

Résultat attendu : 7 colonnes listées (id, context_type, context_id, author_name, author_city, message, created_at).

---

## Tâche 2 : Ajouter les credentials dans `.env` et `config.php`

**Fichiers :**
- Modifier : `.env`
- Modifier : `config.php`

- [ ] **Étape 1 : Ajouter les variables dans `.env`**

Ajouter à la fin du fichier `.env` existant (qui contient déjà les clés Flickr) :

```
DB_HOST=localhost
DB_NAME=legu7203_phpphotos
DB_USER=legu7203_phpphotos
DB_PASS=0}b0r#M1XcV+;$Wu
MAIL_HOST=guillaumelecomte.net
MAIL_PORT=465
MAIL_USER=info@guillaumelecomte.net
MAIL_PASS=SgmRXYC_{mB2G?F_
NOTIFY_EMAIL=info@guillaumelecomte.net
```

- [ ] **Étape 2 : Charger les constantes dans `config.php`**

Ajouter après le bloc `define('FLICKR_USER_ID', ...)` existant :

```php
define('DB_HOST',      $parsed['DB_HOST']      ?? 'localhost');
define('DB_NAME',      $parsed['DB_NAME']      ?? '');
define('DB_USER',      $parsed['DB_USER']      ?? '');
define('DB_PASS',      $parsed['DB_PASS']      ?? '');
define('MAIL_HOST',    $parsed['MAIL_HOST']    ?? '');
define('MAIL_PORT',    (int)($parsed['MAIL_PORT'] ?? 465));
define('MAIL_USER',    $parsed['MAIL_USER']    ?? '');
define('MAIL_PASS',    $parsed['MAIL_PASS']    ?? '');
define('NOTIFY_EMAIL', $parsed['NOTIFY_EMAIL'] ?? '');
```

- [ ] **Étape 3 : Commit**

```bash
git add config.php
git commit -m "feat: charger constantes DB et SMTP depuis .env"
```

(`.env` n'est pas commité — il est dans `.gitignore` ou exclu du deploy)

---

## Tâche 3 : Créer `db.php`

**Fichiers :**
- Créer : `db.php`

- [ ] **Étape 1 : Créer le fichier**

```php
<?php
require_once __DIR__ . '/config.php';

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
```

- [ ] **Étape 2 : Tester la connexion via SSH**

```bash
ssh -i ~/.ssh/id_ed25519 -p 22 legu7203@legu7203.odns.fr \
  "php -r \"
require '/home/legu7203/phpphotos/db.php';
\$pdo = get_pdo();
echo \$pdo ? 'OK' : 'FAIL';
\""
```

Résultat attendu : `OK`

- [ ] **Étape 3 : Commit**

```bash
git add db.php
git commit -m "feat: connexion PDO db.php"
```

---

## Tâche 4 : Créer `lib/Mailer.php`

**Fichiers :**
- Créer : `lib/Mailer.php`

- [ ] **Étape 1 : Créer le fichier**

```php
<?php

class Mailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $from;

    public function __construct(string $host, int $port, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->from = $user;
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $socket = stream_socket_client(
            "ssl://{$this->host}:{$this->port}",
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );

        if (!$socket) return false;

        $read = fn() => fgets($socket, 512);
        $write = fn(string $cmd) => fwrite($socket, $cmd . "\r\n");

        $read(); // 220 greeting
        $write("EHLO guillaumelecomte.net");
        while (true) { $line = $read(); if ($line[3] === ' ') break; } // multi-line EHLO
        $write("AUTH LOGIN");
        $read(); // 334
        $write(base64_encode($this->user));
        $read(); // 334
        $write(base64_encode($this->pass));
        $read(); // 235

        $write("MAIL FROM:<{$this->from}>");
        $read();
        $write("RCPT TO:<{$to}>");
        $read();
        $write("DATA");
        $read(); // 354

        $headers  = "From: guillaumelecomte.net <{$this->from}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        $write($headers . "\r\n" . chunk_split(base64_encode($body)));
        $write(".");
        $read(); // 250

        $write("QUIT");
        fclose($socket);
        return true;
    }
}
```

- [ ] **Étape 2 : Commit**

```bash
git add lib/Mailer.php
git commit -m "feat: Mailer SMTP SSL socket natif"
```

---

## Tâche 5 : Créer `includes/comment_form.php`

**Fichiers :**
- Créer : `includes/comment_form.php`

Ce fragment reçoit trois variables du contexte appelant :
- `$commentContextType` : `'album'` ou `'guestbook'`
- `$commentContextId` : photoset_id (string) ou `''` pour guestbook
- `$commentReturnUrl` : URL de redirection après soumission (avec ancre)
- `$commentError` : booléen, true si `$_GET['error']` est défini

- [ ] **Étape 1 : Créer le fichier**

```php
<?php
$isGuestbook = ($commentContextType === 'guestbook');
$btnLabel    = $isGuestbook ? 'Signer le livre d'or' : 'Laisser un commentaire';
?>
<section class="comments-section" id="comments">
    <h2 class="comments-heading"><?= $isGuestbook ? 'Livre d\'or' : 'Commentaires' ?></h2>

    <?php if ($commentError): ?>
    <p class="comments-error">Tous les champs obligatoires doivent être remplis (nom et message, max 2000 caractères).</p>
    <?php endif; ?>

    <form class="comment-form" method="post" action="/comment_submit.php">
        <input type="hidden" name="context_type" value="<?= htmlspecialchars($commentContextType) ?>">
        <input type="hidden" name="context_id"   value="<?= htmlspecialchars($commentContextId) ?>">
        <input type="hidden" name="return_url"   value="<?= htmlspecialchars($commentReturnUrl) ?>">

        <div class="comment-honeypot">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <label class="comment-label">
            Nom <span class="comment-required">*</span>
            <input class="comment-input" type="text" name="author_name" maxlength="100" required>
        </label>

        <?php if ($isGuestbook): ?>
        <label class="comment-label">
            Ville / Pays
            <input class="comment-input" type="text" name="author_city" maxlength="100">
        </label>
        <?php endif; ?>

        <label class="comment-label">
            Message <span class="comment-required">*</span>
            <textarea class="comment-textarea" name="message" maxlength="2000" rows="4" required></textarea>
        </label>

        <button class="comment-submit" type="submit"><?= htmlspecialchars($btnLabel) ?></button>
    </form>
</section>
```

- [ ] **Étape 2 : Commit**

```bash
git add includes/comment_form.php
git commit -m "feat: fragment formulaire commentaire"
```

---

## Tâche 6 : Créer `includes/comment_list.php`

**Fichiers :**
- Créer : `includes/comment_list.php`

Ce fragment reçoit :
- `$commentContextType` : `'album'` ou `'guestbook'`
- `$commentContextId` : photoset_id ou `''`

- [ ] **Étape 1 : Créer le fichier**

```php
<?php
require_once __DIR__ . '/../db.php';

$pdo  = get_pdo();
$stmt = $pdo->prepare(
    'SELECT author_name, author_city, message, created_at
     FROM comments
     WHERE context_type = ? AND context_id = ?
     ORDER BY created_at ASC'
);
$stmt->execute([$commentContextType, $commentContextId]);
$comments = $stmt->fetchAll();
?>
<?php if (!empty($comments)): ?>
<ul class="comment-list">
    <?php foreach ($comments as $c): ?>
    <li class="comment-item">
        <div class="comment-meta">
            <span class="comment-author"><?= htmlspecialchars($c['author_name']) ?></span>
            <?php if (!empty($c['author_city'])): ?>
            <span class="comment-city"><?= htmlspecialchars($c['author_city']) ?></span>
            <?php endif; ?>
            <span class="comment-date"><?= htmlspecialchars(
                (new DateTime($c['created_at']))->format('d/m/Y à H\hi')
            ) ?></span>
        </div>
        <p class="comment-message"><?= nl2br(htmlspecialchars($c['message'])) ?></p>
    </li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
<p class="comment-empty">Soyez le premier à laisser un commentaire.</p>
<?php endif; ?>
```

- [ ] **Étape 2 : Commit**

```bash
git add includes/comment_list.php
git commit -m "feat: fragment liste commentaires"
```

---

## Tâche 7 : Créer `comment_submit.php`

**Fichiers :**
- Créer : `comment_submit.php`

- [ ] **Étape 1 : Créer le fichier**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$returnUrl = $_POST['return_url'] ?? 'index.php';
// Sécuriser return_url : doit commencer par / ou être une page connue
if (!preg_match('#^/#', $returnUrl)) {
    $returnUrl = 'index.php';
}

// Honeypot
if (!empty($_POST['website'])) {
    header('Location: ' . $returnUrl . '#comments');
    exit;
}

$contextType = $_POST['context_type'] ?? '';
$contextId   = $_POST['context_id']   ?? '';
$authorName  = trim($_POST['author_name'] ?? '');
$authorCity  = trim($_POST['author_city'] ?? '');
$message     = trim($_POST['message']     ?? '');

// Validation whitelist context_type
if (!in_array($contextType, ['album', 'photo', 'guestbook'], true)) {
    header('Location: ' . $returnUrl . '?error=1#comments');
    exit;
}

// Nettoyer context_id : chiffres uniquement sauf guestbook
if ($contextType !== 'guestbook') {
    $contextId = preg_replace('/[^\d]/', '', $contextId);
} else {
    $contextId = '';
}

// Validation champs
$errorUrl = preg_replace('/\?.*/', '', $returnUrl) . '?error=1#comments';
if (empty($authorName) || mb_strlen($authorName) > 100) {
    header('Location: ' . $errorUrl);
    exit;
}
if (empty($message) || mb_strlen($message) > 2000) {
    header('Location: ' . $errorUrl);
    exit;
}
if (mb_strlen($authorCity) > 100) {
    header('Location: ' . $errorUrl);
    exit;
}

// Insertion
$pdo  = get_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO comments (context_type, context_id, author_name, author_city, message)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([
    $contextType,
    $contextId,
    $authorName,
    $authorCity ?: null,
    $message,
]);

// Notification email
$contextLabel = match($contextType) {
    'album'     => 'Album #' . $contextId,
    'photo'     => 'Photo #' . $contextId,
    'guestbook' => 'Livre d\'or',
};
$emailBody  = "Nouveau commentaire sur guillaumelecomte.net\n\n";
$emailBody .= "Contexte : {$contextLabel}\n";
$emailBody .= "Nom : {$authorName}\n";
if ($authorCity) $emailBody .= "Ville : {$authorCity}\n";
$emailBody .= "Date : " . date('d/m/Y à H:i') . "\n\n";
$emailBody .= "Message :\n{$message}\n";

try {
    $mailer = new Mailer(MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS);
    $mailer->send(NOTIFY_EMAIL, '[guillaumelecomte.net] Nouveau commentaire — ' . $contextLabel, $emailBody);
} catch (Throwable) {
    // L'échec d'envoi email ne bloque pas la soumission
}

header('Location: ' . $returnUrl . '#comments');
exit;
```

- [ ] **Étape 2 : Commit**

```bash
git add comment_submit.php
git commit -m "feat: comment_submit.php — validation, insertion, email, honeypot"
```

---

## Tâche 8 : Créer `guestbook.php`

**Fichiers :**
- Créer : `guestbook.php`

- [ ] **Étape 1 : Créer le fichier**

```php
<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livre d'or — Guillaume LECOMTE</title>
    <meta name="description" content="Livre d'or de Guillaume Lecomte, photographe amateur né à Arras, vivant en Belgique. Laissez un message !">
    <meta name="author" content="Guillaume Lecomte">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://guillaumelecomte.net/guestbook.php">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://guillaumelecomte.net/guestbook.php">
    <meta property="og:title" content="Livre d'or — Guillaume LECOMTE">
    <meta property="og:description" content="Laissez un message dans le livre d'or de Guillaume Lecomte, photographe amateur.">
    <meta property="og:locale" content="fr_FR">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <a class="site-title" href="index.php"><span class="site-title-first">Guillaume</span> <span class="site-title-last">LECOMTE</span></a>
    <nav class="site-nav">
        <a class="site-nav-link" href="map.php">Carte</a>
        <a class="site-nav-link active" href="guestbook.php">Livre d'or</a>
    </nav>
</header>

<nav class="breadcrumb">
    <a href="index.php">Collections</a>
    &nbsp;/&nbsp;Livre d'or
</nav>

<main class="container">
    <h1 class="page-heading">Livre d'or</h1>
    <p class="page-description">Vous avez visité la galerie ? Laissez un message, c'est toujours un plaisir de lire vos impressions.</p>

    <?php
    $commentContextType = 'guestbook';
    $commentContextId   = '';
    $commentReturnUrl   = '/guestbook.php';
    $commentError       = isset($_GET['error']);
    require __DIR__ . '/includes/comment_list.php';
    require __DIR__ . '/includes/comment_form.php';
    ?>
</main>

<footer class="site-footer">
    <p class="site-footer-copy">&copy; <?= date('Y') ?> Guillaume LECOMTE — Toutes les photographies présentées sur ce site sont protégées par le droit d'auteur. Toute reproduction, diffusion ou utilisation, même partielle, sans autorisation écrite préalable est strictement interdite.</p>
</footer>

</body>
</html>
```

- [ ] **Étape 2 : Commit**

```bash
git add guestbook.php
git commit -m "feat: page livre d'or"
```

---

## Tâche 9 : Modifier `gallery.php` — ajouter les commentaires

**Fichiers :**
- Modifier : `gallery.php`

- [ ] **Étape 1 : Ajouter les variables et includes avant le `</main>`**

Localiser le bloc `</main>` dans `gallery.php` et le remplacer par :

```php
    <?php
    $commentContextType = 'album';
    $commentContextId   = $photosetId;
    $commentReturnUrl   = '/gallery.php?id=' . urlencode($photosetId) . ($collectionId ? '&col=' . urlencode($collectionId) : '');
    $commentError       = isset($_GET['error']);
    require __DIR__ . '/includes/comment_list.php';
    require __DIR__ . '/includes/comment_form.php';
    ?>

</main>
```

- [ ] **Étape 2 : Commit**

```bash
git add gallery.php
git commit -m "feat: commentaires album dans gallery.php"
```

---

## Tâche 10 : Ajouter le lien "Livre d'or" dans la navigation de toutes les pages

**Fichiers :**
- Modifier : `index.php`
- Modifier : `collection.php`
- Modifier : `map.php`
- Modifier : `gallery.php`

- [ ] **Étape 1 : `index.php` — nav hero**

Remplacer dans `index.php` :
```html
<nav class="hero-nav site-nav">
    <a class="site-nav-link" href="map.php">Carte</a>
</nav>
```
Par :
```html
<nav class="hero-nav site-nav">
    <a class="site-nav-link" href="map.php">Carte</a>
    <a class="site-nav-link" href="guestbook.php">Livre d'or</a>
</nav>
```

- [ ] **Étape 2 : `collection.php`, `gallery.php`, `map.php` — nav header**

Dans chacun de ces fichiers, remplacer :
```html
<nav class="site-nav">
    <a class="site-nav-link" href="map.php">Carte</a>
</nav>
```
Par :
```html
<nav class="site-nav">
    <a class="site-nav-link" href="map.php">Carte</a>
    <a class="site-nav-link" href="guestbook.php">Livre d'or</a>
</nav>
```

(Dans `map.php`, le lien "Carte" a `class="site-nav-link active"` — le conserver tel quel, ajouter seulement le lien Livre d'or.)
(Dans `guestbook.php`, déjà géré à la tâche 8.)

- [ ] **Étape 3 : Commit**

```bash
git add index.php collection.php gallery.php map.php
git commit -m "feat: lien Livre d'or dans la navigation"
```

---

## Tâche 11 : Ajouter les styles CSS des commentaires

**Fichiers :**
- Modifier : `css/style.css`

- [ ] **Étape 1 : Ajouter à la fin de `css/style.css`**

```css
/* ============================
   COMMENTAIRES
   ============================ */

.comment-honeypot { display: none; }

.comments-section {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.comments-heading {
    font-family: var(--font-serif);
    font-size: 1.4rem;
    font-weight: 400;
    margin-bottom: 1.5rem;
}

.comments-error {
    color: #e07070;
    font-size: 0.9rem;
    margin-bottom: 1rem;
    padding: 0.6rem 1rem;
    border: 1px solid #e07070;
    border-radius: 4px;
}

.comment-list {
    list-style: none;
    margin-bottom: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
}

.comment-item {
    padding: 1rem 1.2rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 4px;
}

.comment-meta {
    display: flex;
    align-items: baseline;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-bottom: 0.4rem;
}

.comment-author {
    font-weight: 500;
    color: var(--accent);
}

.comment-city {
    font-size: 0.82rem;
    color: var(--text-muted);
}

.comment-city::before { content: '— '; }

.comment-date {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-left: auto;
}

.comment-message {
    font-size: 0.92rem;
    line-height: 1.6;
    color: var(--text);
}

.comment-empty {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

.comment-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-width: 600px;
    margin-top: 1.5rem;
}

.comment-label {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.88rem;
    color: var(--text-muted);
}

.comment-required { color: var(--accent); }

.comment-input,
.comment-textarea {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--text);
    font-family: var(--font-sans);
    font-size: 0.95rem;
    padding: 0.55rem 0.8rem;
    transition: border-color 0.2s;
}

.comment-input:focus,
.comment-textarea:focus {
    outline: none;
    border-color: var(--accent);
}

.comment-textarea { resize: vertical; min-height: 100px; }

.comment-submit {
    align-self: flex-start;
    background: transparent;
    border: 1px solid var(--accent);
    border-radius: 4px;
    color: var(--accent);
    cursor: pointer;
    font-family: var(--font-sans);
    font-size: 0.88rem;
    letter-spacing: 0.05em;
    padding: 0.55rem 1.4rem;
    transition: background 0.2s, color 0.2s;
}

.comment-submit:hover {
    background: var(--accent);
    color: var(--bg);
}
```

- [ ] **Étape 2 : Commit**

```bash
git add css/style.css
git commit -m "feat: styles section commentaires"
```

---

## Tâche 12 : Déployer et tester

**Fichiers :**
- Aucun nouveau fichier

- [ ] **Étape 1 : Déployer via SSH**

```bash
bash deploy-ssh.sh
```

- [ ] **Étape 2 : Mettre à jour le `.env` sur le serveur**

Le `.env` est exclu du déploiement (rsync `--exclude '.env'`). L'ajouter manuellement :

```bash
ssh -i ~/.ssh/id_ed25519 -p 22 legu7203@legu7203.odns.fr \
  "cat >> /home/legu7203/phpphotos/.env << 'EOF'
DB_HOST=localhost
DB_NAME=legu7203_phpphotos
DB_USER=legu7203_phpphotos
DB_PASS=0}b0r#M1XcV+;$Wu
MAIL_HOST=guillaumelecomte.net
MAIL_PORT=465
MAIL_USER=info@guillaumelecomte.net
MAIL_PASS=SgmRXYC_{mB2G?F_
NOTIFY_EMAIL=info@guillaumelecomte.net
EOF"
```

- [ ] **Étape 3 : Tester le formulaire album Arras**

Ouvrir `https://guillaumelecomte.net/gallery.php?id=72177720333941273&col=196254507-72157724984122496`
→ Vérifier que la section "Commentaires" apparaît en bas avec le formulaire et le message "Soyez le premier à laisser un commentaire."
→ Soumettre un commentaire de test (nom + message)
→ Vérifier redirection vers `#comments` et affichage du commentaire
→ Vérifier réception email sur `info@guillaumelecomte.net`

- [ ] **Étape 4 : Tester le livre d'or**

Ouvrir `https://guillaumelecomte.net/guestbook.php`
→ Vérifier affichage du formulaire avec champ Ville/Pays
→ Soumettre un message avec ville
→ Vérifier affichage avec ville sous le nom

- [ ] **Étape 5 : Tester l'anti-spam honeypot**

Via les DevTools, remplir le champ `name="website"` caché puis soumettre.
→ Vérifier qu'aucun commentaire n'est inséré en base.

- [ ] **Étape 6 : Commit final**

```bash
git add .
git commit -m "feat: système commentaires et livre d'or — déployé"
```
