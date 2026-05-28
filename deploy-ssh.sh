#!/bin/bash
#
# Script de déploiement PHPPHOTOS via SSH (rsync)
# Usage: ./deploy-ssh.sh [--dry-run] [--download]
#
# --dry-run   : Simule sans transférer
# --download  : Télécharge le serveur → local (sens inverse)
#
# Utilise le multiplexage SSH pour éviter le rate-limiting O2switch
#

set -e

# Configuration O2switch
SSH_HOST="legu7203.odns.fr"
SSH_USER="legu7203"
SSH_KEY="~/.ssh/id_ed25519"

# Chemin distant
REMOTE_DIR="/home/legu7203/phpphotos"

# Chemin local (dossier du script)
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)/app"

# Multiplexage SSH
SOCKET_DIR="$HOME/.ssh/sockets"
SOCKET_PATH="$SOCKET_DIR/${SSH_USER}@${SSH_HOST}"
SSH_OPTS="-o ControlMaster=auto -o ControlPath=$SOCKET_PATH -o ControlPersist=300 -o ServerAliveInterval=30 -o ServerAliveCountMax=5"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Nettoyage à la sortie
cleanup() {
    echo -e "\n${BLUE}Fermeture connexion SSH...${NC}"
    ssh -O exit -o ControlPath="$SOCKET_PATH" "$SSH_USER@$SSH_HOST" 2>/dev/null || true
}
trap cleanup EXIT

# Options
DRY_RUN=""
DOWNLOAD=false

for arg in "$@"; do
    case "$arg" in
        --dry-run)  DRY_RUN="--dry-run" ;;
        --download) DOWNLOAD=true ;;
    esac
done

[[ -n "$DRY_RUN" ]] && echo -e "${YELLOW}Mode simulation (dry-run)${NC}"
[[ "$DOWNLOAD" == true ]] && echo -e "${YELLOW}Mode téléchargement (serveur → local)${NC}"

echo -e "${GREEN}╔════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   PHPPHOTOS - Déploiement SSH/rsync        ║${NC}"
echo -e "${GREEN}║   (Multiplexage SSH activé)                ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════╝${NC}"
echo ""

# Créer dossier pour socket SSH
mkdir -p "$SOCKET_DIR"

# Établir la connexion maître
echo -e "${BLUE}[1/3] Établissement connexion SSH maître...${NC}"
if ! ssh -o ConnectTimeout=15 -i "$SSH_KEY" $SSH_OPTS "$SSH_USER@$SSH_HOST" "echo 'OK'" &>/dev/null; then
    echo -e "${RED}✗ Connexion SSH échouée${NC}"
    echo "  Vérifiez que SSH est activé chez O2switch"
    echo "  et que votre clé publique est autorisée dans cPanel"
    echo "  Si bloqué par rate-limit, attendez 5-10 minutes"
    exit 1
fi
echo -e "${GREEN}✓ Connexion SSH maître établie${NC}"

if [[ "$DOWNLOAD" == true ]]; then
    # Téléchargement serveur → local
    echo -e "${BLUE}[2/3] Téléchargement $REMOTE_DIR → $LOCAL_DIR ...${NC}"
    mkdir -p "$LOCAL_DIR"
    rsync -avz --timeout=60 $DRY_RUN \
        -e "ssh -i $SSH_KEY $SSH_OPTS" \
        --exclude '.DS_Store' \
        "$SSH_USER@$SSH_HOST:$REMOTE_DIR/" "$LOCAL_DIR/"
    echo -e "${GREEN}✓ Téléchargement terminé${NC}"
else
    # Upload local → serveur
    echo -e "${BLUE}[2/3] Synchronisation $LOCAL_DIR → $REMOTE_DIR ...${NC}"
    if [[ ! -d "$LOCAL_DIR" ]]; then
        echo -e "${RED}✗ Dossier local introuvable : $LOCAL_DIR${NC}"
        echo "  Créez le dossier app/ ou lancez d'abord --download"
        exit 1
    fi
    rsync -avz --delete --timeout=60 $DRY_RUN \
        -e "ssh -i $SSH_KEY $SSH_OPTS" \
        --exclude '.DS_Store' \
        --exclude '*.log' \
        --exclude '.env' \
        "$LOCAL_DIR/" "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"
    echo -e "${GREEN}✓ Synchronisation terminée${NC}"
fi

# Informations distantes
echo -e "${BLUE}[3/3] Vérification dossier distant...${NC}"
ssh -i "$SSH_KEY" $SSH_OPTS "$SSH_USER@$SSH_HOST" "
    echo '  Contenu de $REMOTE_DIR :'
    ls -lh $REMOTE_DIR/ 2>/dev/null || echo '  (dossier vide ou inexistant)'
    echo ''
    echo '  Espace disque :'
    df -h $REMOTE_DIR 2>/dev/null | tail -1 || true
"

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║         ✓ Opération terminée !             ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════╝${NC}"
echo ""
echo "Dossier distant : $REMOTE_DIR"
echo ""
