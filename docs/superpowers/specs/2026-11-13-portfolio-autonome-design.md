# Portfolio photo autonome — guillaumelecomte.net

Date : 2026-11-13
Statut : validé (brainstorming), en attente du plan d'implémentation

## Objectif

Remplacer l'ancienne galerie basée sur Flickr par un **portfolio personnel autonome**. Guillaume, photographe amateur et pilote ULM, y téléverse lui-même ses photos.
Il n'y a pas d'objectif commercial : ni vente, ni devis. Le style s'inspire de la structure éditoriale de yapasphoto.com, sans en copier le contenu, le texte ou le visuel.

## Décisions prises

| Sujet | Décision |
|---|---|
| Usage | Portfolio personnel, sans volet commercial |
| Contenu existant | Repartir de zéro, sans import depuis Flickr. Guillaume téléverse ses originaux. |
| Organisation | Des thèmes, qui contiennent des albums, qui contiennent des photos |
| Fonctionnalités en plus | Carte GPS, infos de prise de vue (EXIF), page « Présentation », formulaire de contact |
| Exclu | Commentaires, livre d'or, filigrane, vente de tirages, mode sombre |
| Ambiance | « Papier clair » uniquement (identité de la page d'attente) |
| Accueil | Éditorial : ouverture, section Vues du ciel, derniers albums, thèmes |
| Fichiers | Seules les versions web sont conservées, l'original est supprimé après traitement |
| Technique | PHP 8 sur mesure + MySQL, sans framework |

## Architecture

### Arborescence

```
public/              racine web
  index.php          point d'entrée unique
  .htaccess          réécriture vers index.php, en-têtes, noindex si APP_ENV=dev
  .user.ini          upload_max_filesize=48M, post_max_size=50M, memory_limit=512M
  assets/css, assets/js, assets/img (logo)
  media/             images générées (hors git) + .htaccess bloquant tout script
src/
  Controllers/       PublicController, AdminController, UploadController, ContactController
  Services/          Router, Database, View, Auth, Csrf, ImageProcessor, ExifReader, Mailer, Slug, RateLimiter
  Models/            Theme, Album, Photo, Page, Setting
templates/
  layout.php, public/*.php, admin/*.php, partials/*.php
storage/logs/, storage/ratelimit/
database/schema.sql, database/seed.sql
tests/               PHPUnit
.env                 DB, SMTP, APP_ENV, APP_URL (hors git)
```

- Les templates sont en PHP natif. Les variables y passent par `extract()` et chaque sortie est échappée avec la fonction `e()`.
- Les dépendances Composer se limitent à PHPUnit, en développement uniquement. Le `.env` est lu par un petit parseur interne.
- Le site public charge deux bibliothèques par CDN, Leaflet et Leaflet.markercluster, depuis une version figée. L'administration charge SortableJS par CDN, également en version figée.

### Données (MySQL, utf8mb4)

- `themes` : id, slug (unique), title, intro, cover_photo_id (nullable), position, featured (bool, pour « Vues du ciel »), created_at, updated_at
- `albums` : id, theme_id (FK), slug (unique par thème), title, description, shot_on (date), location, cover_photo_id (nullable), position, status (`draft`|`published`), published_at, created_at, updated_at
- `photos` : id, album_id (FK), file_key (chaîne aléatoire), title, caption, position, width, height, taken_at, camera, lens, focal_length, aperture, shutter, iso, lat, lng (nullable), created_at
- `pages` : id, slug (`presentation`), title, lead (accroche), body_html, pilot_info (texte), banner_image, portrait_image (chemins vers des images propres à la page, hors albums)
- `settings` : clé et valeur. On y trouve hero_photo_id, sky_intro (texte de la section Vues du ciel), site_tagline.
- `admin_users` : id, email, password_hash, last_login_at

Une photo n'est visible publiquement que si son album est `published`.

### Images

- Chemin : `public/media/{2 premiers caractères de file_key}/{file_key}-{w}.{webp|jpg}`, avec w ∈ {480, 1280, 2560}. Le dossier ne dépend pas de l'album, donc déplacer une photo ne déplace aucun fichier.
- La largeur maximale est de 2560 px, sans agrandissement si l'image est plus petite. La qualité est de 82 en WebP et de 85 en JPEG.
- Côté navigateur, on utilise `<picture>` avec une source WebP et un repli en JPEG. Les attributs `srcset`/`sizes` laissent le navigateur choisir la taille, et `loading="lazy"` charge les images au défilement.
- Après traitement, aucune donnée EXIF ne reste dans les fichiers publiés. GD les supprime de lui-même, ce qui retire aussi le GPS des fichiers. Les coordonnées GPS ne sont conservées qu'en base, pour la carte.

## Site public

| Adresse | Contenu |
|---|---|
| `/` | Accueil éditorial |
| `/{theme}` | Introduction du thème et grille de ses albums publiés |
| `/{theme}/{album}` | En-tête de l'album et photos en mosaïque justifiée |
| `/{theme}/{album}/photo/{id}` | Visionneuse ouverte sur la photo (adresse qu'on peut partager) |
| `/carte` | Carte de toutes les photos géolocalisées |
| `/presentation` | Page « Présentation » (voir ci-dessous) |
| `/contact` | Formulaire de contact (GET et POST) |
| `/sitemap.xml`, `/robots.txt` | Générés selon APP_ENV |

Les slugs `carte`, `presentation`, `contact`, `admin`, `media`, `assets` et `sitemap.xml` sont réservés. Aucun thème ne peut les prendre.

### Accueil
1. **Ouverture** : photo `hero_photo_id` en pleine largeur, d'environ 80vh, recadrée par `object-fit: cover`. La signature est placée dessous, sur un bandeau papier, et non par-dessus la photo.
2. **Vues du ciel** : le thème `featured`. On y trouve `sky_intro`, une mosaïque de 3 à 5 photos (les plus récentes de ses albums publiés) et un lien vers le thème.
3. **Derniers albums** : les 6 derniers albums publiés, avec couverture, titre, lieu et date.
4. **Thèmes** : tous les thèmes visibles (y compris le thème mis en avant) en cartes (couverture et titre en italique).
5. **Pied de page** : signature, liens vers Présentation, Carte et Contact, et « © Guillaume Lecomte ».

### Mosaïque d'album
Les rangées sont justifiées : la hauteur cible est de 280 px sur ordinateur et de 160 px sur mobile, et chaque photo garde ses proportions. La mise en page est calculée en JavaScript à partir des largeurs et hauteurs stockées en base. Sans JavaScript, une grille CSS simple prend le relais.

### Visionneuse
- Fond ivoire et photo en 2560 px.
- Navigation par flèches, au clavier (← → Échap) et par glissement du doigt. L'adresse se met à jour à chaque photo (`history.pushState`).
- Le panneau « i » affiche le titre, la légende, la date, l'appareil, l'objectif, la focale, l'ouverture, la vitesse et l'ISO, plus une mini-carte Leaflet si la photo a un GPS.

### Carte
Une carte Leaflet avec des tuiles claires (CARTO Positron). Les marqueurs sont regroupés (markercluster). Une infobulle montre la vignette 480, le titre et un lien vers la photo. Les données viennent de `/carte.json`, qui ne contient que les albums publiés.

### Présentation (`/presentation`)
La page s'appuie sur deux photos fournies par Guillaume :
- **Bandeau** : `DSC00296-2-Modifier.jpg`, en noir et blanc, format paysage, prise sur un tarmac d'aéroport (Guillaume photographie, avions en arrière-plan). Elle est affichée en pleine largeur en haut de page, environ 60vh, recadrée par `object-fit: cover` et centrée sur le visage et l'appareil (position d'environ 55 % horizontalement). Le titre « Présentation » ou l'accroche apparaît dessous, sur le fond papier.
- **Portrait** : `DSC_29571.jpg`, portrait studio en couleur au format vertical (Nikon en main). Il est placé dans la colonne de gauche à côté du texte sur ordinateur (environ 40 % de largeur, ratio 2:3), et au-dessus du texte sur mobile.

Disposition, de haut en bas :
1. Le bandeau noir et blanc.
2. L'accroche, en Cormorant italique et en grand, puis la ligne de « trace » en pointillés.
3. Deux colonnes : le portrait à gauche, le texte de présentation à droite (`body_html`).
4. Un encart « Pilote », sous forme de liste sobre sur filet fin : ULM, terrain de base et heures de vol, au gré de Guillaume (`pilot_info`).
5. Un lien vers Contact.

Les deux images sont redimensionnées une fois en 1280 et 2560 px, en WebP et JPEG sans EXIF, puis stockées dans `public/assets/img/presentation/`. Elles ne font pas partie des albums. L'administration permet de les remplacer et de modifier l'accroche, le texte et l'encart « Pilote ».

Le texte de présentation est écrit par Guillaume. La version de départ est un brouillon à valider.

### Contact
Champs : nom, e-mail et message (20 à 5000 caractères). Les protections sont un champ piège invisible, un délai minimal de 3 s entre l'affichage et l'envoi, et une limite de 3 envois par heure et par adresse IP (fichiers dans `storage/ratelimit`). L'envoi se fait par le SMTP d'O2switch vers `NOTIFY_EMAIL`, avec le visiteur en `Reply-To`. Une page de confirmation suit l'envoi.

### Style
- Couleurs : `--paper #f4f1ec`, `--ink #1b1b1a`, `--muted #7a756d`, `--line #d9d3c9`.
- Polices : Cormorant Garamond italique pour les titres, Inter pour le texte.
- La signature (`logo.png`, détourée) et la ligne de « trace » en pointillés servent de motif récurrent.
- La mise en page est mobile d'abord, avec des marges latérales de 16 px au minimum.
- Pas de mode sombre.

### Référencement
Balises title et meta description par page, Open Graph et Twitter Card (image 1280), données structurées (`ImageGallery`, `Person`) et sitemap. Quand `APP_ENV=dev`, le site envoie l'en-tête `X-Robots-Tag: noindex, nofollow` et un robots.txt qui bloque tout.

## Administration (`/admin`)

- **Authentification** : e-mail et mot de passe (`password_hash`/`password_verify`), session régénérée à la connexion, déconnexion après 2 h d'inactivité. Les tentatives sont limitées à 5 toutes les 15 min par IP. Chaque POST vérifie un jeton CSRF.
- **Création du compte** : par une commande `php bin/create-admin.php`, et non par un formulaire web.
- **Tableau de bord** : compteurs, derniers albums, raccourci « Nouvel album ».
- **Thèmes** : création, modification, suppression (refusée s'il reste des albums) et ordre par glisser-déposer. Un seul thème peut être `featured`.
- **Albums** : création, modification, statut brouillon ou publié, choix de la couverture d'un clic et ordre par glisser-déposer. Supprimer un album supprime aussi ses photos et ses fichiers.
- **Photos** :
  - zone de dépôt pour plusieurs fichiers JPEG, envoyés **un par un** en `fetch`, avec une barre de progression et la possibilité de relancer une photo en erreur ;
  - grille modifiable directement (titre, légende), avec ordre par glisser-déposer, déplacement vers un autre album, suppression et correction du GPS (latitude et longitude saisies à la main).
- **Contenus** : page « Présentation » (éditeur de texte simple, avec une liste blanche de balises HTML au filtrage), photo d'ouverture, texte de la section Vues du ciel et accroche du site.

### Traitement d'un envoi (`POST /admin/albums/{id}/photos`)
1. Vérifications : session, jeton CSRF, présence du fichier, taille ≤ 48 Mo, type MIME `image/jpeg` contrôlé par `finfo`, `getimagesize` valide et image de 60 Mpx au maximum (limite imposée par la mémoire de GD avec 512 Mo).
2. Lecture des infos par `ExifReader` : date, appareil, objectif, focale, ouverture, vitesse, ISO, GPS converti en degrés décimaux, et orientation.
3. `ImageProcessor` : chargement avec GD, rotation selon l'orientation EXIF, puis création des 3 largeurs en WebP et en JPEG dans un dossier temporaire, déplacé ensuite à sa place définitive. La mémoire est libérée après chaque taille.
4. Insertion en base. Le titre est déduit du nom de fichier (sans extension, tirets et soulignés remplacés par des espaces).
5. Suppression du fichier temporaire d'origine.
6. Réponse en JSON : `{id, thumb_url, title}` ou `{error}` avec le code HTTP adapté.

En cas d'échec à n'importe quelle étape, les fichiers partiels sont supprimés et rien n'est écrit en base.

## Erreurs et journalisation
- En production, une page d'erreur sobre s'affiche (404 et 500 avec le style du site), et le détail est écrit dans `storage/logs/app.log`.
- En dev, les erreurs s'affichent à l'écran.
- Les adresses qui n'existent pas, ou les albums en brouillon pour un visiteur, renvoient une 404.

## Déploiement
- **Dev** : la racine web de `dev.guillaumelecomte.net` passe sur `~/dev.guillaumelecomte.net/public` (ou un lien symbolique), avec une base MySQL dédiée `legu7203_portfolio_dev` et `APP_ENV=dev`. L'envoi se fait avec le script `deploy-ssh.sh` réécrit (rsync sans `--delete` sur `public/media` et `storage`, avec `--exclude .env`).
- **Prod** : même chose vers `~/phpphotos` avec la base `legu7203_portfolio`, une fois le site validé. La page d'attente actuelle sera alors remplacée.
- Le workflow GitHub `.github/workflows/deploy.yml` (git pull dans `~/phpphotos`) est **supprimé** : il écraserait la page d'attente et ne correspond plus au déploiement.
- `public/media` est inclus dans la sauvegarde cPanel d'O2switch. Les originaux restent sur le Mac et dans iCloud.

## Tests
- PHPUnit pour `ExifReader` (sur des images de test contenant ou non des données GPS), `ImageProcessor` (tailles, rotation, refus des fichiers non JPEG), `Slug`, `Router`, `RateLimiter` et le filtre HTML de la page « Présentation ».
- Vérification manuelle sur le site de dev de chaque page publique, sur ordinateur et sur mobile, et du parcours complet : création d'un thème, d'un album, envoi de photos, puis publication.

## Hors périmètre
Commentaires, livre d'or, filigrane, vente ou tirages, comptes multiples, mode sombre, import Flickr, formats RAW, HEIC et vidéo.
