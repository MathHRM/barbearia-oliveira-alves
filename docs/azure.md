# Implantação na Azure

Documentação do planejamento e da configuração inicial da infraestrutura da
Barbearia Oliveira Alves na Azure.

## Status atual

**Aplicação publicada com domínio e HTTPS ativo. Validação funcional ainda pendente.**

O PostgreSQL e a VM já foram criados no portal. O acesso SSH foi validado, o
Docker foi instalado e a aplicação está respondendo pelo domínio
`barbearia-oliveira-alves.matheushrm.dev` com HTTPS. Ainda falta a validação
funcional completa do sistema.

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

### PostgreSQL fora da VM

O PostgreSQL será executado no **Azure Database for PostgreSQL Flexible
Server**, separado da VM.

```text
VM B2ats v2
  Laravel/FrankenPHP via Docker
          |
          | TCP 5432 + SSL
          v
PostgreSQL Flexible Server B1ms
```

Essa decisão foi tomada para aprendizado e separação de responsabilidades.
Permite praticar conexão com banco externo, firewall, credenciais de produção,
SSL e migrations remotas. Também evita que Laravel e PostgreSQL disputem a
memória de uma VM pequena.

O custo operacional é maior do que colocar o banco no mesmo Docker Compose,
mas o B1ms aparece dentro da cota gratuita do PostgreSQL.

### Serviços que não serão usados agora

Não serão provisionados neste primeiro estágio:

- Azure Blob Storage;
- backup manual ou serviço Azure Backup;
- Redis;
- queue worker;
- scheduler;
- Service Bus;
- Azure Container Registry;
- Load Balancer;
- VPN Gateway;
- Azure DNS;
- envio real de e-mails.

O PostgreSQL Flexible Server pode manter backup automático mínimo obrigatório
do próprio serviço. Isso é diferente de configurarmos uma rotina de backup da
aplicação; a retenção escolhida foi a mínima de 7 dias.

## Alternativas analisadas

### PostgreSQL dentro da VM

Seria a opção mais barata e simples. Também seria compatível com o Compose
atual. Foi mantida como alternativa futura, mas não será usada agora porque
queremos aprender a operar um banco gerenciado fora da aplicação.

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

Foram adicionados:

- `docker-compose.prod.yml`: inicia somente o container `app`, publica as portas
  `80` e `443` e mantém `/app/storage`, `/data` e `/config` em volumes Docker;
- `.env.production.example`: modelo sem senha ou chave da aplicação;
- ajuste em `config/database.php`: `DB_SSLMODE` agora pode definir o modo SSL,
  permitindo `require` no PostgreSQL do Azure.

O arquivo real `.env.production` deve ser criado somente na VM. Ele é ignorado
por Git e não deve ser enviado ao repositório. O Compose de produção foi
executado com sucesso após o preenchimento do ambiente e a aplicação está
respondendo por HTTP e HTTPS.

⚠️ **Limitação conhecida:** o estágio `prod` do `Dockerfile` compila os assets
Node durante o build. Como a VM tem apenas 1 GiB de memória, o build pode
precisar de swap temporário ou ser feito fora da VM caso ocorra falta de
memória.

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

O PostgreSQL será acessado pela aplicação através do endpoint do Flexible
Server. A porta 5432 não deve ser publicada pela VM.

## Configuração prevista da aplicação

Na produção, o Docker Compose não iniciará o container local `postgres`.

As variáveis principais serão semelhantes a:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.matheushrm.dev

DB_CONNECTION=pgsql
DB_HOST=<servidor>.postgres.database.azure.com
DB_PORT=5432
DB_DATABASE=barbearia
DB_USERNAME=barbeariaadmin
DB_PASSWORD=<segredo armazenado fora do Git>
DB_SSLMODE=require

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

O PostgreSQL Flexible Server também deverá ser parado quando a aplicação não
estiver sendo usada. O banco não ficará disponível enquanto estiver parado.

Discos, IP público e outros recursos podem continuar gerando consumo mesmo com
a VM desalocada. Antes de excluir a VM, verificar também discos, NIC e IP
público, pois eles podem permanecer separados no grupo de recursos.

## Próximas etapas

1. Confirmar periodicamente no portal que a regra do PostgreSQL continua
   limitada ao IP público atual da VM.
2. Confirmar periodicamente no Network Security Group que SSH continua
   restrito ao IP do administrador.
3. Validar página pública, login, agendamento e painel.
4. Testar desalocação e inicialização dos recursos.
5. Conferir o consumo no Azure Cost Management.

## Inicialização do administrador e catálogo

O `CatalogSeeder` contém os cinco serviços-base, mas o `TeamSeeder` também
cria barbeiros demonstrativos e usa uma senha fixa de desenvolvimento. Em
produção, criar apenas o administrador e o catálogo com o Tinker idempotente
registrado no procedimento operacional do deploy.

## Riscos aceitos neste estágio

- sem backup manual da aplicação;
- sem alta disponibilidade;
- banco com acesso público, protegido por firewall de IP;
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
