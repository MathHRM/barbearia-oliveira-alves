#!/usr/bin/env bash

set -Eeuo pipefail

readonly PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"

: "${DEPLOY_USER:=barbeariaadmin}"
: "${DEPLOY_HOST:=102.133.162.154}"
: "${DEPLOY_PATH:=/home/barbeariaadmin/barbearia}"
: "${DEPLOY_URL:=https://barbearia-oliveira-alves.matheushrm.dev}"
: "${COMPOSE_FILE:=docker-compose.prod.yml}"

readonly DEPLOY_TARGET="${DEPLOY_USER}@${DEPLOY_HOST}"

log() {
    printf '\n==> %s\n' "$*"
}

die() {
    printf 'Erro: %s\n' "$*" >&2
    exit 1
}

usage() {
    cat <<'EOF'
Uso: scripts/deploy.sh

Variáveis opcionais:
  DEPLOY_USER    usuário SSH (padrão: barbeariaadmin)
  DEPLOY_HOST    host/IP da VM
  DEPLOY_PATH    diretório da aplicação na VM
  DEPLOY_URL     URL usada no health check
  COMPOSE_FILE   arquivo Compose de produção
EOF
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
fi

command -v rsync >/dev/null || die "rsync não encontrado."
command -v ssh >/dev/null || die "ssh não encontrado."

[[ -f "${PROJECT_ROOT}/${COMPOSE_FILE}" ]] || die "arquivo ${COMPOSE_FILE} não encontrado."

log "Validando acesso SSH"
ssh -o BatchMode=yes "${DEPLOY_TARGET}" true

log "Sincronizando código com ${DEPLOY_TARGET}:${DEPLOY_PATH}"
rsync -az --delete \
    --exclude='.env' \
    --exclude='.env.production' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='public/build' \
    --exclude='storage' \
    --exclude='.git' \
    "${PROJECT_ROOT}/" "${DEPLOY_TARGET}:${DEPLOY_PATH}/"

log "Reconstruindo e recriando a aplicação"
ssh "${DEPLOY_TARGET}" "cd '${DEPLOY_PATH}' && docker compose -f '${COMPOSE_FILE}' build --no-cache app && docker compose -f '${COMPOSE_FILE}' up -d --force-recreate app"

log "Validando container e endpoint"
ssh "${DEPLOY_TARGET}" "cd '${DEPLOY_PATH}' && docker compose -f '${COMPOSE_FILE}' ps"
curl --fail --silent --show-error --location --head --max-time 30 "${DEPLOY_URL}" >/dev/null

log "Deploy concluído: ${DEPLOY_URL}"
