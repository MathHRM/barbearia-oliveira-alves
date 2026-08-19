# Barbearia Oliveira Alves

Agendamento online na hora + painel de gestão do barbeiro.

Laravel 12 · Inertia · React 19 · TypeScript · shadcn/ui · Tailwind 4 · PostgreSQL 16 · Render.

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
| `scheduler` | — | `schedule:work`, dispara lembretes |
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
| `BARBEARIA_CANCEL_WINDOW_HOURS` | `12` | prazo para o cliente cancelar |
| `BARBEARIA_MIN_LEAD_MIN` | `60` | antecedência mínima para agendar |
| `BARBEARIA_HORIZON_DAYS` | `21` | até quantos dias à frente a agenda abre |
| `BARBEARIA_SLOT_STEP_MIN` | `15` | granularidade dos horários |
| `BARBEARIA_CHURN_DAYS` | `60` | dias sem voltar para o cliente contar como perdido |

### Fuso horário

O app roda em **UTC** (`APP_TIMEZONE=UTC`) e o banco guarda `timestamptz` em UTC.
O fuso da barbearia fica em `BARBEARIA_TZ` (`America/Sao_Paulo`) e vale para
grade de horários, agenda e exibição.

Motivo: o Eloquent formata datas sem offset ao gravar, então um `Carbon` em
`-03:00` chegaria ao Postgres como se fosse UTC. As colunas de horário usam o
cast `App\Casts\UtcDateTime`, que converte explicitamente nas duas pontas, e as
janelas de consulta são convertidas para UTC antes de virar binding de query.

### Testes

Rodam contra Postgres de verdade (a exclusividade de slot depende de
`btree_gist`/`EXCLUDE`), no banco `barbearia_test`. Crie uma vez:

```bash
docker compose exec postgres psql -U barbearia -d postgres -c "CREATE DATABASE barbearia_test OWNER barbearia"
docker compose exec app php artisan test
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

## Deploy na Azure

O estado atual da infraestrutura Azure e o procedimento de migração do banco
estão documentados em [`docs/azure.md`](docs/azure.md). A aplicação roda em uma
VM com PostgreSQL 16 local em Docker; o PostgreSQL dedicado da Azure foi parado
após a migração e permanece disponível somente como rollback temporário.

Para executar o fluxo documentado de sincronização, build, recriação e health
check a partir da raiz do projeto:

```bash
./scripts/deploy.sh
```

O usuário, host, diretório remoto e URL podem ser sobrescritos por variáveis de
ambiente, por exemplo:

```bash
DEPLOY_HOST=vm.example.com DEPLOY_USER=deploy ./scripts/deploy.sh
```

Para usar uma chave privada específica:

```bash
./scripts/deploy.sh --ssh-key /caminho/seguro/barbearia_key.pem
```

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
