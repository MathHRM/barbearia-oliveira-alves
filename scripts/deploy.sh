#!/usr/bin/env bash

set -Eeuo pipefail

readonly PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"

: "${DEPLOY_USER:=barbeariaadmin}"
: "${DEPLOY_HOST:=102.133.162.154}"
: "${DEPLOY_PATH:=/home/barbeariaadmin/barbearia}"
: "${DEPLOY_URL:=https://barbearia-oliveira-alves.matheushrm.dev}"
: "${COMPOSE_FILE:=docker-compose.prod.yml}"

readonly DEPLOY_TARGET="${DEPLOY_USER}@${DEPLOY_HOST}"
SSH_KEY=""

SSH_OPTIONS=(
    -o BatchMode=yes
    -o ConnectTimeout=10
    -o ConnectionAttempts=1
    -o ServerAliveInterval=10
    -o ServerAliveCountMax=3
)

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
  --ssh-key      caminho da chave privada SSH
EOF
}

while (($# > 0)); do
    case "$1" in
        -h|--help)
            usage
            exit 0
            ;;
        --ssh-key)
            (($# >= 2)) || die "--ssh-key exige o caminho da chave privada."
            SSH_KEY="$2"
            shift 2
            ;;
        --ssh-key=*)
            SSH_KEY="${1#*=}"
            [[ -n "${SSH_KEY}" ]] || die "--ssh-key exige o caminho da chave privada."
            shift
            ;;
        *)
            die "argumento desconhecido: $1"
            ;;
    esac
done

if [[ -n "${SSH_KEY}" ]]; then
    [[ -f "${SSH_KEY}" ]] || die "chave SSH não encontrada: ${SSH_KEY}"
    [[ -r "${SSH_KEY}" ]] || die "chave SSH sem permissão de leitura: ${SSH_KEY}"
    SSH_OPTIONS+=(
        -i "${SSH_KEY}"
        -o IdentitiesOnly=yes
    )
fi

readonly SSH_OPTIONS
printf -v RSYNC_SSH_COMMAND '%q ' ssh "${SSH_OPTIONS[@]}"

command -v rsync >/dev/null || die "rsync não encontrado."
command -v ssh >/dev/null || die "ssh não encontrado."

[[ -f "${PROJECT_ROOT}/${COMPOSE_FILE}" ]] || die "arquivo ${COMPOSE_FILE} não encontrado."

log "Validando acesso SSH"
ssh "${SSH_OPTIONS[@]}" "${DEPLOY_TARGET}" true

log "Sincronizando código com ${DEPLOY_TARGET}:${DEPLOY_PATH}"
rsync -az --delete \
    --exclude='.env' \
    --exclude='.env.production' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='public/build' \
    --exclude='storage' \
    --exclude='.git' \
    -e "${RSYNC_SSH_COMMAND}" \
    "${PROJECT_ROOT}/" "${DEPLOY_TARGET}:${DEPLOY_PATH}/"

log "Reconstruindo e recriando a aplicação"
ssh "${SSH_OPTIONS[@]}" "${DEPLOY_TARGET}" "cd '${DEPLOY_PATH}' && docker compose -f '${COMPOSE_FILE}' build --no-cache app && docker compose -f '${COMPOSE_FILE}' up -d --force-recreate app"

log "Validando container e endpoint"
ssh "${SSH_OPTIONS[@]}" "${DEPLOY_TARGET}" "cd '${DEPLOY_PATH}' && docker compose -f '${COMPOSE_FILE}' ps"
curl --fail --silent --show-error --location --head --max-time 30 "${DEPLOY_URL}" >/dev/null

log "Deploy concluído: ${DEPLOY_URL}"
