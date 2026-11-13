#!/bin/bash
#
# Déploiement du portfolio vers O2switch.
# Usage : ./deploy-ssh.sh dev|prod [--dry-run]
#
set -euo pipefail

TARGET="${1:-}"
DRY="${2:-}"
case "$TARGET" in
  dev)  REMOTE_DIR="/home/legu7203/portfolio-dev"; SITE_URL="https://dev.guillaumelecomte.net" ;;
  prod) REMOTE_DIR="/home/legu7203/portfolio";     SITE_URL="https://guillaumelecomte.net" ;;
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
  --exclude '.git/' --exclude '.github/' --exclude '.env*' --exclude '.DS_Store' --exclude 'error_log' --exclude '*.icloud' \
  --exclude '/vendor/' --exclude '/tests/' --exclude '/docs/' --exclude '/.phpunit.cache/' \
  --exclude '/.superpowers/' --exclude '/.claude/' --exclude '/.worktrees/' \
  --include 'public/media/.htaccess' --exclude 'public/media/*' \
  --exclude 'public/assets/img/presentation/*' \
  --exclude 'public/.well-known/' \
  --exclude 'storage/logs/*' --exclude 'storage/ratelimit/*' --exclude 'storage/sessions/*' \
  ./ "$HOST:$REMOTE_DIR/"

if [[ -n "$DRY" ]]; then
  echo "Simulation terminée (rien n'a été modifié)."
  exit 0
fi

echo "▶ Migrations"
$SSH "$HOST" "cd $REMOTE_DIR && php bin/migrate.php"

# Vider l'OPcache du PHP web (sinon l'ancien code peut rester servi un moment)
echo "▶ Vidage du cache PHP"
FLUSH="opcache-$(openssl rand -hex 8).php"
$SSH "$HOST" "printf '%s' '<?php if (function_exists(\"opcache_reset\")) { opcache_reset(); } echo \"ok\";' > $REMOTE_DIR/public/$FLUSH"
curl -s --max-time 15 "$SITE_URL/$FLUSH" || true
echo
$SSH "$HOST" "rm -f $REMOTE_DIR/public/$FLUSH"
echo "✓ Déployé sur $TARGET"
