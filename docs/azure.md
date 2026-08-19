# Implantação na Azure

Documentação do planejamento e da configuração inicial da infraestrutura da
Barbearia Oliveira Alves na Azure.

## Status atual

**Aplicação publicada com domínio e HTTPS ativo, usando PostgreSQL local na VM.**

O PostgreSQL Flexible Server e a VM foram criados no portal. O acesso SSH foi
validado, o Docker foi instalado e a aplicação responde pelo domínio
`barbearia-oliveira-alves.matheushrm.dev` com HTTPS. O banco foi migrado para um
PostgreSQL 16 em Docker dentro da VM; o servidor PostgreSQL dedicado da Azure
foi parado, mas não excluído, para manter rollback temporário.

⚠️ **Operação manual:** o estado abaixo foi confirmado pelas telas do portal e
pelo teste informado pelo usuário. O repositório não consulta a assinatura
Azure automaticamente.

## Objetivo

Hospedar a aplicação Laravel/Inertia/React com o menor custo possível usando a
assinatura **Azure for Students**, mantendo uma arquitetura simples o bastante
para servir como aprendizado prático de infraestrutura.

O crédito confirmado pelo usuário é de **R$ 500**, aproximadamente **US$ 100**.

## Decisões confirmadas

### Aplicação em uma VM

A aplicação será executada em uma VM Linux dentro de Docker.

```text
Internet
   |
   v
IP público
   |
VM Linux B2ats v2
   |
Docker
   |
Laravel + FrankenPHP
```

Motivos:

- o projeto já possui `Dockerfile` e Docker Compose;
- a VM permite controlar o sistema operacional e o processo de deploy;
- é uma forma direta de aprender Linux, Docker, rede, firewall e HTTPS;
- a B2ats v2 aparece como gratuita na assinatura, com 750 horas mensais;
- não é necessário adotar Kubernetes ou dividir o monólito em serviços.

### PostgreSQL dentro da VM

O PostgreSQL de produção é executado no serviço `postgres` do
`docker-compose.prod.yml`, usando a imagem `postgres:16-alpine` e o volume
persistente `barbearia_postgres_data`.

```text
VM B2ats v2
  |
  +-- Laravel/FrankenPHP via Docker
  |
  +-- PostgreSQL 16 via Docker
          |
          +-- volume barbearia_postgres_data
```

Essa decisão reduz o custo mensal e elimina a dependência operacional do banco
gerenciado. A aplicação acessa o banco pela rede interna do Compose, usando
`DB_HOST=postgres`; a porta 5432 não é publicada para a internet.

O custo é assumir a operação do PostgreSQL, dos backups e da disponibilidade
da VM. Por isso, o volume persistente e uma rotina de backup fora da VM são
essenciais.

### Serviços que não serão usados agora

Não serão provisionados neste primeiro estágio:

- Azure Blob Storage;
- backup automatizado externo;
- Redis;
- queue worker;
- scheduler;
- Service Bus;
- Azure Container Registry;
- Load Balancer;
- VPN Gateway;
- Azure DNS;
- envio real de e-mails.

O PostgreSQL local possui um backup manual inicial em
`~/barbearia/backups/barbearia-local.dump`. Ainda não há uma rotina automática
de backup externo; isso permanece como próximo trabalho operacional.

## Alternativas analisadas

### PostgreSQL fora da VM

Foi usado inicialmente o **Azure Database for PostgreSQL Flexible Server**.
Depois da migração validada, o servidor dedicado foi parado para reduzir custo.
Ele permanece disponível como rollback temporário e não deve ser excluído antes
de confirmar os backups e a estabilidade do banco local.

Desvantagens da opção local:

- aplicação e banco parariam juntos;
- uma falha da VM afetaria os dois componentes;
- atualizações e manutenção do PostgreSQL ficariam sob nossa responsabilidade;
- não haveria separação de recursos;
- sem backup, uma falha de disco poderia causar perda de dados.

### Azure App Service

Não foi escolhido porque o tier gratuito não atende bem ao domínio
`matheushrm.dev`, possui limitações de execução e não aproveita diretamente o
Docker Compose com a mesma liberdade da VM. O banco continuaria sendo outro
serviço.

### Azure Container Apps

Não foi escolhido porque adicionaria ambientes, revisões, ingress e cobrança
por CPU, memória e requisições. É uma boa alternativa para aplicações que
precisam escalar, mas é complexa para este monólito pequeno.

### Azure Container Instances

Não foi escolhido porque é mais adequado para containers temporários e jobs do
que para manter aplicação web, banco e operação contínua.

### Azure Static Web Apps e Azure Functions

Não foram escolhidos porque a aplicação possui Laravel completo, sessões,
autenticação, painel, rotas dinâmicas e conexão relacional com PostgreSQL.

### AKS/Kubernetes

Não foi escolhido porque seria desproporcional: adicionaria cluster, nodes,
manifests, ingress, secrets e operação de Kubernetes para uma única aplicação.

### Azure SQL e Cosmos DB

Não foram escolhidos porque o projeto usa PostgreSQL, migrations específicas e
restrições relacionais. Trocar o banco exigiria adaptação e validação da
aplicação sem benefício proporcional.

## Região escolhida

Região definida para os recursos:

```text
South Africa North
```

Essa foi a melhor opção entre as regiões disponíveis para a VM B2ats v2,
considerando a localização dos usuários no Brasil e a disponibilidade do
PostgreSQL Flexible Server.

A VM e o PostgreSQL devem permanecer na mesma região para evitar latência e
transferência entre regiões.

## PostgreSQL configurado no portal

Configuração escolhida:

| Item                   | Valor                           |
| ---------------------- | ------------------------------- |
| Assinatura             | Azure for Students              |
| Grupo de recursos      | `barbearia`                     |
| Nome do servidor       | `barbearia`                     |
| Região                 | South Africa North              |
| PostgreSQL             | 16                              |
| Autenticação           | Somente autenticação PostgreSQL |
| Login administrativo   | `barbeariaadmin`                |
| Tier                   | Burstable                       |
| SKU                    | Standard_B1ms                   |
| vCPU                   | 1                               |
| Memória                | 2 GiB                           |
| Armazenamento          | 32 GiB                          |
| Tipo de armazenamento  | Premium SSD                     |
| Desempenho             | P4, 120 IOPS                    |
| Aumento automático     | Desabilitado                    |
| Alta disponibilidade   | Desabilitada                    |
| Resiliência zonal      | Desabilitada                    |
| Retenção de backup     | 7 dias                          |
| Redundância geográfica | Desabilitada                    |
| Versão de TLS          | Manter padrão seguro do serviço |

O portal exibiu os seguintes valores estimados antes da aplicação da cota:

- computação: USD 15,69/mês;
- armazenamento: USD 4,83/mês;
- total bruto estimado: USD 20,52/mês.

O mesmo painel marcou a computação como **gratuita até 750 horas** e o
armazenamento como **gratuito até 32 GB**. O consumo final deve ser acompanhado
no portal, pois a gratuidade depende da validade e das regras da assinatura.

### Estado provisionado

| Item | Estado observado |
| ---- | ---------------- |
| Status | Ready |
| Endpoint | `barbearia.postgres.database.azure.com` |
| Zona de disponibilidade | 3 |
| Alta disponibilidade | Não habilitada |
| Rede | Acesso público com regras de firewall |
| Acesso de qualquer serviço Azure | Desabilitado |

O endpoint é informação de configuração, não uma senha. A senha administrativa
não deve ser registrada neste documento nem no Git.

## Rede do PostgreSQL

### Escolha feita

Usar **acesso público com firewall restritivo**.

O banco terá endpoint público, mas não ficará aberto para qualquer endereço.
Após a criação da VM, será adicionada uma regra liberando somente o IP público
da VM:

```text
Nome: vm-barbearia
IP inicial: <IP público da VM>
IP final: <IP público da VM>
```

### Correção aplicada durante a criação

Na tela de revisão, a configuração estava assim:

```text
Permitir acesso público pela Internet: Sim
Permitir acesso público de qualquer serviço Azure: Sim
Regras de firewall: 1
```

Antes do provisionamento, foi feito o seguinte:

1. manter o acesso público pela Internet;
2. alterar o acesso público de qualquer serviço Azure para **Não**;
3. evitar `0.0.0.0/0`;
4. criar o servidor;
5. adicionar o IP da VM depois que ela existisse.

Não deve ser habilitada a opção que permite acesso de qualquer serviço Azure,
pois ela libera conexões de serviços de outras assinaturas Azure também.

## VM provisionada no portal

| Item | Valor observado |
| ---- | --------------- |
| Nome da VM | `barbearia` |
| Status | Em execução / Ready |
| Região | South Africa North |
| Sistema operacional | Ubuntu 22.04 |
| Tamanho | Standard B2ats v2 |
| CPU/memória | 2 vCPUs / 1 GiB |
| Arquitetura | x64 |
| Usuário SSH | `barbeariaadmin` |
| Autenticação | Chave pública SSH |
| Rede virtual | `barbearia-vnet/default` |
| IP privado observado | `10.1.1.4` |
| IP público observado | `102.133.162.154` |

O IP público deve ser conferido no portal antes de alterar regras de firewall,
pois a atribuição precisa permanecer estática para evitar quebra da conexão
com o PostgreSQL. A chave privada `barbearia_key.pem` foi baixada para o
computador do usuário e não deve ser adicionada ao repositório.

### Segurança aplicada

- SSH foi utilizado para validar o acesso à VM.
- HTTP e HTTPS permanecem disponíveis para a futura aplicação.
- PostgreSQL não foi aberto na VM; a porta `5432` é do servidor gerenciado.
- A regra de firewall do PostgreSQL deve permitir somente o IP público da VM.
- A origem da regra SSH deve ser restrita ao IP do administrador; confirmar no
  Network Security Group se o portal inicialmente criou SSH aberto para a
  Internet.

### Acessar a VM por SSH

O acesso administrativo é feito por SSH usando o usuário
`barbeariaadmin`, o IP público atual da VM e a chave privada baixada durante a
criação. A chave não é uma senha e não deve ser enviada para o Git, para a VM
ou compartilhada com terceiros.

Na máquina local, ajuste as permissões da chave e conecte-se:

```bash
chmod 600 /caminho/seguro/barbearia_key.pem
ssh -i /caminho/seguro/barbearia_key.pem \
  barbeariaadmin@102.133.162.154
```

Substitua `/caminho/seguro/barbearia_key.pem` pelo local real em que a chave
foi salva. O IP deve ser conferido no portal antes do comando, especialmente
depois de desalocar e religar a VM.

Na primeira conexão, o SSH pode perguntar se a impressão digital do servidor
deve ser aceita. Confirme somente se o endereço/IP corresponde à VM criada.
Depois de conectado, o prompt deverá indicar o usuário e o nome da VM. Para
acessar o projeto:

```bash
cd ~/barbearia
docker compose -f docker-compose.prod.yml ps
```

Para sair da VM:

```bash
exit
```

Se a conexão for recusada, conferir nesta ordem:

1. a VM está `Em execução`, e não `Parado (desalocado)`;
2. o IP público atual está correto;
3. a regra de entrada TCP `22` do Network Security Group permite o IP do
   computador administrador;
4. o arquivo da chave corresponde ao par de chaves criado para a VM;
5. a chave possui permissão restrita (`chmod 600`).

Como alternativa, o portal Azure oferece a opção **Conectar > SSH**, que
exibe o comando equivalente e ajuda a confirmar o IP, usuário e porta. O
Azure Cloud Shell também pode ser usado, desde que a chave privada esteja
disponível nesse ambiente.

### Docker instalado

Após a criação da VM, o Docker Engine e o plugin Docker Compose foram
instalados pelo repositório oficial do Docker para Ubuntu. O usuário
`barbeariaadmin` foi adicionado ao grupo `docker` e o teste da imagem
`hello-world` foi concluído com sucesso.

Estado confirmado:

- Docker Engine funcionando;
- `docker compose` disponível;
- container de teste executado;
- aplicação do projeto publicada por HTTP;

O Compose existente em `docker-compose.yml` é voltado ao desenvolvimento e
inicia Vite, queue, scheduler e PostgreSQL local. Ele não deve ser usado
diretamente na VM de produção.

### Arquivos de produção preparados

Foram adicionados ou atualizados:

- `docker-compose.prod.yml`: inicia `app` e `postgres`, publica somente as
  portas `80` e `443` e mantém dados da aplicação, Caddy e PostgreSQL em
  volumes Docker;
- `.env.production.example`: modelo sem senha ou chave da aplicação;
- `.env.production`: na VM, `DB_HOST=postgres`, `DB_SSLMODE=disable` e as
  variáveis `POSTGRES_*` inicializam o banco local.

O arquivo real `.env.production` deve ser criado somente na VM. Ele é ignorado
por Git e não deve ser enviado ao repositório. O Compose de produção foi
executado com sucesso após o preenchimento do ambiente e a aplicação está
respondendo por HTTP e HTTPS.

⚠️ **Limitação conhecida:** o estágio `prod` do `Dockerfile` compila os assets
Node durante o build. Como a VM tem apenas 1 GiB de memória, o build pode
precisar de swap temporário ou ser feito fora da VM caso ocorra falta de
memória.

## Procedimento de deploy

O deploy atual é manual e executado por SSH na VM. O código local é sincronizado
com `rsync`; o arquivo `.env.production` não deve ser copiado, pois existe
somente na VM.

### 1. Sincronizar o código

Na máquina de desenvolvimento, a partir da raiz do projeto:

```bash
rsync -az --delete \
  --exclude='.env' \
  --exclude='.env.production' \
  --exclude='node_modules' \
  --exclude='vendor' \
  ./ barbeariaadmin@102.133.162.154:/home/barbeariaadmin/barbearia/
```

O `--delete` mantém a cópia da VM equivalente ao diretório local. Usá-lo
somente apontando para o diretório correto da aplicação.

### 2. Reconstruir e recriar a aplicação

Na VM:

```bash
cd ~/barbearia
docker compose -f docker-compose.prod.yml build --no-cache app
docker compose -f docker-compose.prod.yml up -d --force-recreate app
```

O build compila os assets frontend e pode consumir bastante memória na VM de
1 GiB. O entrypoint executa as migrations e recria os caches de configuração,
eventos, rotas e views.

### 3. Validar o deploy

Ainda na VM:

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=100 app
curl -I https://barbearia-oliveira-alves.matheushrm.dev
```

O resultado esperado é o container em execução, logs sem erro fatal e uma
resposta HTTP `200`, `302` ou outra resposta própria da rota testada. Também
validar manualmente a página pública, o login em `/painel/login`, o cadastro de
agendamento e o painel.

### 4. Quando houver alteração somente de configuração

Depois de alterar o `.env.production` na VM, recriar o container para que as
variáveis sejam carregadas e os caches sejam refeitos:

```bash
cd ~/barbearia
docker compose -f docker-compose.prod.yml up -d --force-recreate app
```

Não versionar o `.env.production` nem colocar senhas em comandos registrados no
histórico do shell.

## Migração do PostgreSQL para a VM

A migração foi executada em agosto de 2026, sem novos agendamentos durante a
troca.

### Banco local

O serviço de produção usa:

- imagem `postgres:16-alpine`;
- serviço Compose `postgres`;
- volume persistente `barbearia_postgres_data`;
- healthcheck com `pg_isready`;
- nenhuma publicação da porta 5432.

As variáveis `POSTGRES_DB`, `POSTGRES_USER` e `POSTGRES_PASSWORD` existem
somente no `.env.production` da VM. O arquivo `.env.production` não deve ser
versionado ou enviado por `rsync`.

### Cópia e restauração

O dump foi criado a partir do Flexible Server com `pg_dump` 16 em formato
custom, usando `--no-owner` e `--no-acl`, e restaurado no banco local com
`pg_restore`. O arquivo temporário usado foi `barbearia-azure.dump`.

Após a restauração, as 15 tabelas da aplicação e a extensão `btree_gist` foram
encontradas no banco local. Um backup adicional foi criado em:

```text
~/barbearia/backups/barbearia-local.dump
```

Esse backup deve ser copiado para fora da VM. Um backup armazenado somente no
mesmo disco não protege contra perda da VM ou do disco.

### Corte para o banco local

O corte foi feito alterando o `.env.production` para:

```env
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=barbearia
DB_USERNAME=barbeariaadmin
DB_SSLMODE=disable
```

Depois, o container `app` foi recriado e o domínio continuou respondendo após
o PostgreSQL Azure ser parado. Isso confirma que a aplicação passou a usar o
banco local.

### Rollback

Se for necessário voltar temporariamente ao banco Azure:

1. iniciar o PostgreSQL Flexible Server e aguardar o estado `Ready`;
2. parar o container `app`;
3. restaurar no `.env.production` o `DB_HOST` Azure e `DB_SSLMODE=require`;
4. recriar o container `app`;
5. validar login, painel e agendamento.

O servidor Azure deve permanecer parado, e não excluído, até a validação dos
backups e da operação do banco local terminar.

### Incidentes resolvidos no primeiro deploy

- `btree_gist` foi adicionado à lista `azure.extensions` do PostgreSQL Azure,
  permitindo a migration da constraint `appointments_no_overlap`;
- o entrypoint passou a criar os diretórios de `storage` antes dos caches, pois
  o volume Docker de produção começa vazio;
- a aplicação foi reconstruída e iniciou sem novas migrations pendentes;
- o acesso HTTP ao IP público da VM foi validado pelo usuário.
- o domínio foi apontado para o IP público da VM;
- o HTTPS automático do FrankenPHP/Caddy foi habilitado;
- o comando produtivo foi corrigido para carregar o Caddyfile padrão da imagem;
- o acesso HTTPS pelo domínio foi validado pelo usuário.

### Incidente: HTTPS inicialmente recusava conexão

Sintomas observados:

- HTTP na porta 80 funcionava;
- a porta 443 estava publicada no Docker, mas recusava conexão;
- os logs informavam `server is listening only on the HTTP port`;
- `SERVER_NAME` estava correto no ambiente do container.

Causa:

O comando produtivo executava `frankenphp php-server`. Esse comando servia a
aplicação em HTTP, mas não carregava o Caddyfile padrão da imagem FrankenPHP,
que usa `SERVER_NAME` para configurar o domínio e o HTTPS automático.

Correção aplicada no `Dockerfile`:

```text
frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
```

Depois da reconstrução da imagem e recriação do container, a porta 443 passou
a responder e o HTTPS pelo domínio foi validado.

## Configuração prevista da VM

### Recursos

- sistema operacional: Ubuntu Linux LTS;
- tamanho: Linux B2ats v2;
- 2 vCPUs;
- 1 GiB de memória;
- uma VM somente;
- um disco de sistema elegível para a cota gratuita;
- nenhum disco de dados adicional inicialmente;
- IP público;
- Network Security Group.

### Portas

| Porta | Uso        | Regra                                             |
| ----: | ---------- | ------------------------------------------------- |
|    22 | SSH        | Restringir ao IP do administrador quando possível |
|    80 | HTTP       | Público                                           |
|   443 | HTTPS      | Público                                           |
|  5432 | PostgreSQL | Não abrir na VM                                   |

O PostgreSQL é acessado pela aplicação através do serviço interno `postgres`.
A porta 5432 não deve ser publicada pela VM.

## Configuração prevista da aplicação

Na produção, o Docker Compose inicia o container local `postgres` e a aplicação
aguarda o healthcheck do banco antes de iniciar.

As variáveis principais serão semelhantes a:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.matheushrm.dev

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=barbearia
DB_USERNAME=barbeariaadmin
DB_PASSWORD=<segredo armazenado fora do Git>
DB_SSLMODE=disable

QUEUE_CONNECTION=sync
# scheduler não utilizado neste estágio
MAIL_MAILER=log
```

O `QUEUE_CONNECTION=sync` evita a necessidade de um worker separado. Se no
futuro forem adicionados jobs demorados, lembretes ou integrações assíncronas,
será necessário reavaliar queue, scheduler e Redis.

O `MAIL_MAILER=log` mantém o comportamento atual de não enviar e-mails reais.

## Domínio

O domínio disponível é:

```text
matheushrm.dev
```

Subdomínio utilizado:

```text
barbearia-oliveira-alves.matheushrm.dev
```

No provedor DNS do domínio, será criado:

```text
Tipo: A
Nome: barbearia-oliveira-alves
Valor: 102.133.162.154
```

Não será criado Azure DNS, pois o domínio já possui provedor DNS.

## Como economizar desligando os recursos

Para economizar computação, a VM deve estar em:

```text
Stopped (Deallocated)
```

Parar o sistema operacional não é suficiente; é necessário desalocar a VM pelo
portal ou por `az vm deallocate`.

### Desligar pelo portal

1. Abra a VM `barbearia` no grupo de recursos `barbearia`.
2. Confirme que não há deploy ou acesso SSH em andamento.
3. Clique em **Parar** e confirme a opção de **desalocar** a VM.
4. Aguarde o estado `Parado (desalocado)`.

### Desligar pela Azure CLI

```bash
az vm deallocate \
  --resource-group barbearia \
  --name barbearia
```

Validar o estado:

```bash
az vm get-instance-view \
  --resource-group barbearia \
  --name barbearia \
  --query "instanceView.statuses[?starts_with(code, 'PowerState/')].displayStatus" \
  --output tsv
```

### Religar antes de acessar o sistema

Se o PostgreSQL também estiver parado, iniciar o servidor PostgreSQL Flexible
no portal e aguardar o estado `Ready` antes da VM. Depois, iniciar a VM:

```bash
az vm start \
  --resource-group barbearia \
  --name barbearia
```

Aguardar o SSH e conferir os containers:

```bash
ssh barbeariaadmin@102.133.162.154
cd ~/barbearia
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=100 app
```

O Compose usa `restart: unless-stopped`, então o container deve voltar após a
VM iniciar. Se ele não estiver em execução, iniciar manualmente:

```bash
docker compose -f docker-compose.prod.yml up -d app
```

⚠️ **IP público:** confirmar no portal se o IP público está configurado como
**Estático**. Se for dinâmico, ele pode mudar depois de uma desalocação e o
registro A de `barbearia-oliveira-alves.matheushrm.dev` precisará ser atualizado
antes de acessar o domínio.

O PostgreSQL Flexible Server dedicado foi parado após a migração. Ele não deve
ser iniciado novamente sem atualizar suas credenciais, pois a senha anterior
foi exposta durante a inspeção da configuração.

Discos, IP público e outros recursos podem continuar gerando consumo mesmo com
a VM desalocada. Antes de excluir a VM, verificar também discos, NIC e IP
público, pois eles podem permanecer separados no grupo de recursos.

## Próximas etapas

1. Copiar o backup `barbearia-local.dump` para fora da VM.
2. Confirmar periodicamente no Network Security Group que SSH continua
   restrito ao IP do administrador.
3. Validar página pública, login, agendamento e painel.
4. Testar desalocação e inicialização dos recursos.
5. Conferir o consumo no Azure Cost Management.
6. Excluir o PostgreSQL Azure somente após uma janela de rollback acordada.

## Inicialização do administrador e catálogo

O `CatalogSeeder` contém os cinco serviços-base, mas o `TeamSeeder` também
cria barbeiros demonstrativos e usa uma senha fixa de desenvolvimento. Em
produção, criar apenas o administrador e o catálogo com o Tinker idempotente
registrado no procedimento operacional do deploy.

## Riscos aceitos neste estágio

- backup manual do banco criado na VM, ainda dependente de cópia externa;
- sem alta disponibilidade;
- banco sem porta pública, acessível pela rede interna do Docker;
- uma única VM para a aplicação;
- VM de baixa memória;
- sem envio de e-mail;
- sem processamento assíncrono;
- indisponibilidade planejada quando VM e banco forem desligados.

Esses riscos são aceitáveis para o estágio atual de aprendizado e baixo
tráfego. Devem ser reavaliados antes de tratar o sistema como serviço crítico.

## Referências

- [Azure Database for PostgreSQL — opções de rede](https://learn.microsoft.com/en-us/azure/postgresql/network/how-to-networking)
- [Azure Database for PostgreSQL — regras de firewall](https://learn.microsoft.com/en-us/azure/postgresql/security/security-firewall-rules)
- [Azure Database for PostgreSQL — regiões](https://learn.microsoft.com/en-us/azure/postgresql/flexible-server/service-overview)
- [Azure VM — desalocar e remover cobrança de computação](https://learn.microsoft.com/en-us/azure/virtual-machines/states-billing)
- [Azure for Students — FAQ](https://learn.microsoft.com/en-us/azure/education-hub/faq)
