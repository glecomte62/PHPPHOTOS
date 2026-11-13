# Système de commentaires et livre d'or

**Date :** 2026-05-30
**Projet :** guillaumelecomte.net — galerie photo PHP/Flickr
**Statut :** Approuvé

---

## Résumé

Ajout d'un système de commentaires visiteurs sur les albums photo et les photos individuelles, ainsi qu'une page livre d'or. Tout est stocké en base MySQL. Les commentaires sont publics immédiatement (pas de modération). Un email est envoyé à chaque nouveau message.

---

## Base de données

Serveur : O2switch
Base : `legu7203_phpphotos`
Utilisateur : `legu7203_phpphotos`

```sql
CREATE TABLE comments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  context_type ENUM('album','photo','guestbook') NOT NULL,
  context_id   VARCHAR(64) NOT NULL DEFAULT '',
  author_name  VARCHAR(100) NOT NULL,
  author_city  VARCHAR(100) DEFAULT NULL,
  message      TEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_context (context_type, context_id)
);
```

- `context_type='album'` + `context_id=<photoset_id>` → commentaires album
- `context_type='photo'` + `context_id=<photo_id>` → commentaires photo
- `context_type='guestbook'` + `context_id=''` → livre d'or

---

## Architecture fichiers

### Nouveaux fichiers

| Fichier | Rôle |
|---|---|
| `db.php` | Connexion PDO à `legu7203_phpphotos`, retourne une instance `PDO` |
| `comment_submit.php` | Reçoit POST, valide, honeypot, insère, envoie email, redirige |
| `guestbook.php` | Page livre d'or : liste des messages + formulaire |
| `includes/comment_form.php` | Fragment HTML formulaire (paramétré par context_type/context_id) |
| `includes/comment_list.php` | Fragment HTML liste de commentaires |

### Fichiers modifiés

| Fichier | Modification |
|---|---|
| `gallery.php` | Inclure `comment_list.php` et `comment_form.php` en bas de page |
| `.env` | Ajouter `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `NOTIFY_EMAIL` |
| `config.php` | Charger et définir les constantes MySQL depuis `.env` |
| `map.php` / `index.php` | Ajouter lien "Livre d'or" dans la navigation |

---

## Flux de soumission

1. Visiteur remplit le formulaire et soumet (POST vers `comment_submit.php`)
2. `comment_submit.php` vérifie le honeypot (`website` non vide → redirection silencieuse)
3. Validation des champs (voir règles ci-dessous)
4. Insertion PDO dans `comments`
5. `mail()` vers `NOTIFY_EMAIL` avec contexte et message
6. Redirection vers la page d'origine avec ancre `#comments`

---

## Validation

| Champ | Règle |
|---|---|
| `author_name` | Obligatoire, max 100 caractères |
| `message` | Obligatoire, max 2000 caractères |
| `author_city` | Optionnel, max 100 caractères (livre d'or uniquement) |
| `context_type` | Doit être `album`, `photo`, ou `guestbook` (whitelist) |
| `context_id` | Nettoyé — uniquement chiffres pour album/photo, vide pour guestbook |
| `website` (honeypot) | Doit être vide — champ masqué CSS `display:none` |

En cas d'erreur de validation : redirection vers la page d'origine avec paramètre `?error=1` et affichage d'un message d'erreur inline.

---

## Formulaires

### Commentaire album / photo

```
Nom *         [________________________]
Message *     [________________________]
              [________________________]
[Laisser un commentaire]
```

### Livre d'or

```
Nom *         [________________________]
Ville / Pays  [________________________]
Message *     [________________________]
              [________________________]
[Signer le livre d'or]
```

---

## Notification email

- Envoi via SMTP avec connexion socket PHP natif (pas de dépendance externe)
- Compte expéditeur : `info@guillaumelecomte.net`
- Serveur SMTP : `guillaumelecomte.net`, port 465 (SSL)
- Destinataire : `info@guillaumelecomte.net` (constante `NOTIFY_EMAIL` dans `.env`)
- Credentials dans `.env` : `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `NOTIFY_EMAIL`
- Sujet : `[guillaumelecomte.net] Nouveau commentaire — <context>`
- Corps : nom, ville (si guestbook), message, contexte, date

---

## Anti-spam

Champ honeypot `<input type="text" name="website">` masqué via CSS :
```css
.comment-honeypot { display: none; }
```
Si le champ est rempli au moment du POST, insertion ignorée, redirection normale (le bot ne sait pas qu'il a été piégé).

---

## Affichage commentaires

- Triés par `created_at ASC` (plus ancien en premier)
- Format : nom en gras, date en gris, message en dessous
- Livre d'or : nom + ville/pays en gras, date, message
- Si aucun commentaire : message "Soyez le premier à laisser un commentaire."

---

## Navigation

Lien "Livre d'or" ajouté dans la `<nav>` de toutes les pages (à côté du lien "Carte").

---

## Hors périmètre

- Modération / suppression de commentaires (pas d'interface admin)
- Réponses aux commentaires (pas de threading)
- Commentaires sur photos individuelles via le lightbox (formulaire sur gallery.php uniquement, lié à l'album)
- Pagination des commentaires
