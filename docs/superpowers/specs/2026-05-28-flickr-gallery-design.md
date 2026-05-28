# PHPPHOTOS — Flickr Gallery Site Design

**Date:** 2026-05-28
**Statut:** Approuvé

---

## Vue d'ensemble

Site PHP pour afficher les galeries Flickr d'un utilisateur. Style sombre et immersif, type galerie photographique de musée. Déployé sur O2switch via le script `deploy-ssh.sh` existant.

---

## Architecture

### Structure des fichiers

```
app/
├── .env                    # API key Flickr + Flickr User ID (non déployé)
├── .htaccess               # Rewrite rules : routing vers index.php
├── index.php               # Page d'accueil : grille des galeries
├── gallery.php             # Page galerie : grille des photos d'un album
├── config.php              # Lit .env, définit les constantes globales
├── lib/
│   └── FlickrAPI.php       # Classe wrapper pour les appels API Flickr
├── css/
│   └── style.css           # Design dark, responsive, animations
└── js/
    └── lightbox.js         # Lightbox vanilla JS avec panneau infos
```

### Fichier `.env`

```
FLICKR_API_KEY=<your_api_key>
FLICKR_SECRET=<your_secret>
FLICKR_USER_ID=<your_user_id>
```

Le `.env` est exclu du déploiement rsync (ajouté dans le `--exclude` de `deploy-ssh.sh`). La clé API n'est jamais exposée au client.

---

## Flux de données

### API Flickr utilisée

| Méthode | Usage |
|---|---|
| `flickr.photosets.getList` | Liste de toutes les galeries (titre, cover, nb photos) |
| `flickr.photosets.getPhotos` | Photos d'une galerie spécifique |

Les appels sont faits en PHP côté serveur via `cURL` (plus fiable qu'`allow_url_fopen` sur O2switch). Pas de cache — données toujours fraîches.

### Page d'accueil (`index.php`)

1. `FlickrAPI::getPhotosets()` → tableau de galeries
2. Rendu HTML : grille de cards, chaque card = couverture + titre + nb de photos
3. Clic sur une card → `gallery.php?id=PHOTOSET_ID`

### Page galerie (`gallery.php`)

1. Lecture de `$_GET['id']`
2. `FlickrAPI::getPhotos($photosetId)` → tableau de photos
3. Rendu HTML : grille masonry de photos
4. Clic sur une photo → lightbox JS avec panneau d'infos

### Gestion d'erreur

- API indisponible → message d'erreur élégant à la place de la grille (pas de page blanche)
- Galerie introuvable (ID invalide) → redirection vers `index.php` avec message
- `.env` manquant ou clé invalide → page d'erreur de configuration

---

## Classe `FlickrAPI`

```php
class FlickrAPI {
    private string $apiKey;
    private string $userId;
    private string $baseUrl = 'https://api.flickr.com/services/rest/';

    public function __construct(string $apiKey, string $userId);
    public function getPhotosets(): array;          // Liste des galeries
    public function getPhotos(string $photosetId): array;  // Photos d'une galerie
    private function request(array $params): array; // Appel HTTP + parse JSON
}
```

Chaque méthode retourne un tableau PHP structuré. Les erreurs API sont remontées via des exceptions.

---

## Design visuel

### Palette

| Rôle | Couleur |
|---|---|
| Fond principal | `#0a0a0a` |
| Fond cards/surfaces | `#141414` |
| Bordures | `#2a2a2a` |
| Texte principal | `#f0f0f0` |
| Texte secondaire | `#888888` |
| Accent hover | `#c8a96e` (or chaud) |

### Typographie

- **Titres :** `Playfair Display` (Google Fonts) — élégant, référence photographique
- **UI/Corps :** `Inter` (Google Fonts) — lisible, moderne

### Grille des galeries (accueil)

- CSS Grid, 3 colonnes sur desktop, 2 sur tablette, 1 sur mobile
- Cards : photo de couverture en pleine hauteur (aspect-ratio 4/3), titre en overlay bas avec dégradé noir
- Hover : élévation légère (`transform: translateY(-4px)`) + bordure accent dorée
- Transition douce `0.3s ease`

### Grille des photos (galerie)

- CSS `columns` (masonry natif) — 3-4 colonnes selon viewport
- Photos en niveaux de gris au repos (`filter: grayscale(30%)`), couleur pleine au hover
- Transition `filter 0.4s ease`

### Lightbox

- Overlay `rgba(0,0,0,0.95)`, fermeture au clic sur l'overlay ou touche `Escape`
- Photo centrée, max `90vh` de hauteur, max `70vw` de largeur
- Panneau latéral droit (300px) : titre, description, date de prise de vue, tags
- Navigation : boutons ←/→ + touches clavier `ArrowLeft` / `ArrowRight`
- Animation d'ouverture : `opacity 0 → 1` + `scale(0.95) → scale(1)`, `0.25s ease`

---

## Responsive

| Breakpoint | Comportement |
|---|---|
| Desktop (>1024px) | Grille 3 cols, lightbox avec panneau latéral |
| Tablette (768-1024px) | Grille 2 cols, panneau lightbox en bas |
| Mobile (<768px) | Grille 1 col, lightbox plein écran |

---

## Déploiement

- Code PHP dans `app/`
- `.env` exclu du rsync (ajout de `--exclude '.env'` dans `deploy-ssh.sh`)
- `.env.example` versionné avec les clés vides comme documentation
- Destination sur O2switch : `/home/legu7203/phpphotos`

---

## Hors scope

- Authentification / admin
- Upload de photos
- Cache des appels API
- Commentaires ou interactions sociales
- Multi-utilisateurs
