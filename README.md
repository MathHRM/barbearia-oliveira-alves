# Barbearia Oliveira Alves

Agendamento online com pagamento antecipado + painel de gestão do barbeiro.

Laravel 12 · Inertia · React 19 · TypeScript · shadcn/ui · Tailwind 4 · PostgreSQL 16 · Asaas · Render.

Spec completa do sistema: [`SPEC.md`](SPEC.md).

---

## Rodando (tudo em Docker)

Requisito único: **Docker** com Compose v2. Nada de PHP, Composer ou Node na máquina.

```bash
cp .env.example .env          # opcional: o entrypoint faz isso se faltar
echo "UID=$(id -u)"  >> .env  # evita arquivo root-owned no volume
echo "GID=$(id -g)"  >> .env

docker compose up -d --build
```

Primeira subida leva alguns minutos (build da imagem + `composer install` + `npm ci`).

| Serviço | Onde | O quê |
|---|---|---|
| `app` | http://localhost:8010 | FrankenPHP servindo o Laravel |
| `vite` | http://localhost:5183 | dev server do front (HMR) |
| `queue` | — | `queue:work`, processa e-mails e jobs |
| `scheduler` | — | `schedule:work`, expira reservas e dispara lembretes |
| `postgres` | `localhost:5433` | banco (usuário/senha/base: `barbearia` / `secret` / `barbearia`) |

Abra **http://localhost:8010**. O `app` só sobe depois do Postgres ficar saudável, e roda
as migrations sozinho no boot.

### Comandos do dia a dia

```bash
docker compose logs -f app                       # logs
docker compose exec app php artisan migrate      # migrations
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan tinker
docker compose exec app php artisan test
docker compose exec app composer require pacote/x
docker compose exec vite npm install pacote-x
docker compose exec postgres psql -U barbearia   # psql

docker compose restart queue                     # depois de mudar um Job
docker compose down                              # para tudo
docker compose down -v                           # para tudo E apaga o banco
```

Nunca rode `php`, `composer` ou `npm` direto no host — a extensão `pdo_pgsql` e as
versões corretas só existem dentro dos containers.

### Debug com Xdebug

Vem instalado e desligado (custo zero). Para ligar:

```bash
docker compose exec app sh -c 'echo "xdebug.mode=debug\nxdebug.client_host=host.docker.internal\nxdebug.start_with_request=yes" > /usr/local/etc/php/conf.d/zz-xdebug.ini'
docker compose restart app
```

---

## Variáveis de ambiente

Além do padrão do Laravel:

| Var | Default | O quê |
|---|---|---|
| `ASAAS_ENV` | `sandbox` | `sandbox` ou `production` |
| `ASAAS_API_KEY` | — | chave da API do Asaas |
| `ASAAS_WEBHOOK_TOKEN` | — | token esperado no header `asaas-access-token` |
| `BARBEARIA_RESERVATION_TTL_MIN` | `10` | minutos que o slot fica preso esperando pagamento |
| `BARBEARIA_CANCEL_WINDOW_HOURS` | `12` | prazo para o cliente cancelar com estorno integral |
| `BARBEARIA_MIN_LEAD_MIN` | `60` | antecedência mínima para agendar |
| `BARBEARIA_HORIZON_DAYS` | `21` | até quantos dias à frente a agenda abre |
| `BARBEARIA_SLOT_STEP_MIN` | `15` | granularidade dos horários |
| `BARBEARIA_CHURN_DAYS` | `60` | dias sem voltar para o cliente contar como perdido |

`APP_TIMEZONE` é `America/Sao_Paulo`; o banco guarda tudo em UTC (`timestamptz`).

### Webhook do Asaas em desenvolvimento

O Asaas precisa alcançar a máquina. Exponha a porta 8010 com um túnel e aponte o
webhook para `https://<seu-tunel>/webhooks/asaas`:

```bash
docker run --rm -it --network host ngrok/ngrok http 8010
```

---

## Deploy no Render

O `Dockerfile` tem o estágio `prod` (assets buildados, sem dev deps, autoload otimizado).

| Serviço Render | Tipo | Comando |
|---|---|---|
| `barbearia-web` | Web Service (Docker, target `prod`) | default do Dockerfile |
| `barbearia-worker` | Background Worker (mesma imagem) | `php artisan queue:work --tries=3` |
| `barbearia-cron` | Cron Job, `*/5 * * * *` | `php artisan appointments:expire` |
| `barbearia-db` | PostgreSQL 16 | — |

O entrypoint de produção roda `migrate --force` e recria os caches a cada deploy.
Aponte `DB_*` para a Internal Database URL do Render e defina `APP_KEY` uma vez
(`php artisan key:generate --show`).

---

## Estrutura

```
app/            Laravel: models, actions, http, jobs
resources/js/   React + Inertia
  pages/        telas (wizard público, painel)
  components/   shadcn/ui e componentes do projeto
docker/         entrypoints
design/         estudos visuais iniciais (comparador de 3 direções)
SPEC.md         spec do sistema — fonte de verdade
```
