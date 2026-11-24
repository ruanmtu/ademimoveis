# ADemóveis

Portal de imóveis em **PHP + MySQL** com três tipos de conta e um único login:

| Tipo | O que faz |
|---|---|
| **Cliente** (grátis) | Busca imóveis, vê fotos, conversa com anunciantes pelo chat e avalia anunciantes com quem conversou |
| **Anunciante** (mensalidade via Mercado Pago) | Cadastra imóveis (venda, aluguel ou temporada) com fotos, tem perfil público com avaliações e responde no chat |
| **Administrador** | Painel exclusivo para gerenciar usuários, imóveis, conversas, avaliações, planos e pagamentos |

## Requisitos

- PHP 8.1 ou superior, com as extensões `pdo_mysql`, `gd`, `curl` e `mbstring` (já vêm na maioria das hospedagens e no XAMPP)
- MySQL 5.7+ ou MariaDB 10.3+ (gerenciado pelo phpMyAdmin)
- Apache. Se o servidor for Nginx, bloqueie as pastas `config/`, `includes/` e `database/` e desative o PHP em `uploads/`

## Instalação

1. **Copie a pasta** `ademoveis` para o servidor. No XAMPP, a pasta é `C:\xampp\htdocs\ademoveis`.
2. **Crie o banco no phpMyAdmin:** abra a aba **Importar**, escolha `database/schema.sql` e clique em Executar. O banco `ademoveis` será criado com todas as tabelas e um plano inicial de R$ 49,90, que você pode alterar depois.
   *Em hospedagem compartilhada, onde o nome do banco já vem definido:* crie o banco pelo painel da hospedagem, apague as linhas `CREATE DATABASE` e `USE` do arquivo e importe dentro desse banco.
3. **Configure:** copie `config/config.exemplo.php` para `config/config.php` e preencha:
   - `APP_URL`: o endereço do site, por exemplo `http://localhost/ademoveis`
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`: os dados do banco
4. **Crie o administrador:** acesse `SEU-SITE/instalar.php`, preencha nome, e-mail e senha. Esse formulário só funciona enquanto não existir nenhum administrador. **Depois, apague o arquivo `instalar.php`.**
5. Garanta que a pasta `uploads/` tenha **permissão de escrita** pelo servidor (755 ou 775).

## Mercado Pago (mensalidade dos anunciantes)

1. Crie uma aplicação em https://www.mercadopago.com.br/developers/panel/app
2. Copie o **Access Token** para `MP_ACCESS_TOKEN` em `config/config.php`. Use o token de **teste** primeiro.
3. Em **Webhooks**, cadastre a URL `https://SEU-SITE/pagamento/webhook.php` e marque o evento **Planos e assinaturas**. Copie a **assinatura secreta** para `MP_WEBHOOK_SECRET`.
4. O webhook só funciona com o site publicado na internet (com HTTPS). No `localhost` o Mercado Pago não consegue avisar o site. Nesse caso a assinatura é atualizada quando o anunciante volta do checkout, ou você ativa manualmente pelo painel admin.

**Como funciona:**

- O anunciante escolhe o plano em *Painel → Assinatura* e é levado ao checkout do Mercado Pago.
- Com o pagamento aprovado, a assinatura fica **ativa** até a próxima cobrança, mais uma tolerância (`ASSINATURA_TOLERANCIA_DIAS`).
- Se o pagamento atrasar ou a assinatura for cancelada, os imóveis saem do ar sozinhos quando o período pago termina. Não precisa de cron.
- **Planos** (*Admin → Planos*): crie quantos tipos quiser (Básico, Profissional, Anual...), com valor, cobrança mensal, trimestral, semestral ou anual, limite de imóveis, fotos por imóvel, quantidade de imóveis em destaque e benefícios. Mudar o preço vale para novas assinaturas; quem já assinou continua com o valor contratado.
- **Trocar o plano de um anunciante** (*Admin → Usuários → anunciante → Plano*): os limites mudam na hora, e você pode atualizar o valor cobrado no Mercado Pago na mesma tela. O próprio anunciante também pode trocar de plano em *Painel → Assinatura*: a assinatura antiga só é cancelada quando a nova é paga.
- **Cupons** (*Admin → Cupons*): desconto em porcentagem ou em reais, válido só na 1ª cobrança, nas primeiras X cobranças ou para sempre. Pode valer para um plano específico, ter limite de usos e data de validade. Quando o desconto acaba, o site volta sozinho o valor da assinatura para o preço cheio. Cupom de 100% libera o período grátis sem passar pelo Mercado Pago. Cada anunciante usa o mesmo cupom só uma vez.
- Sem Mercado Pago configurado, o admin pode ativar anunciantes manualmente em *Admin → Usuários* (cortesia, período de teste, pagamento por fora).

## Página inicial

- **Foto de fundo do topo:** por padrão aparece uma ilustração de casas ao entardecer. Para usar uma foto sua, salve-a como `assets/img/hero.jpg`, de preferência na horizontal, com cerca de 1600 × 700 px.
- **Buscas populares** (como "Casas com 2 quartos"): edite a lista `$buscasPopulares` no começo do `index.php`.
- **Comodidades** (piscina, aceita pets...): o anunciante marca no cadastro do imóvel e o cliente filtra pela busca. A lista fica na função `comodidades()` em `includes/helpers.php`.

## Atualizando de uma versão anterior

Se você já tinha importado o `schema.sql` de uma versão anterior, importe no phpMyAdmin, na ordem, só os arquivos que ainda não importou:

1. `database/atualizacao_01_comodidades.sql`
2. `database/atualizacao_02_cupons_planos.sql`
3. `database/atualizacao_03_documentos.sql`
4. `database/atualizacao_04_recuperar_senha.sql`

## CPF, CNPJ e CRECI

- **Clientes** informam CPF no cadastro e **anunciantes**, CPF ou CNPJ. O site confere os dígitos verificadores, inclusive do **CNPJ alfanumérico** emitido desde julho de 2026, e não aceita o mesmo documento em duas contas. O número não aparece no site.
- O **CRECI** é opcional. O número fica como na carteira (ex.: CRECI-CE 24199); se a pessoa digitar "CRECI-CE 24199", o estado é preenchido sozinho. A letra F ou J é opcional e, quando digitada, precisa combinar com CPF (F) ou CNPJ (J). Como não existe consulta nacional automática, a confirmação é feita em *Admin → Usuários → anunciante → Verificar CRECI*, na consulta pública do CRECI do estado. Depois de confirmado, o perfil ganha o selo "CRECI verificado".
- O dígito verificador só garante que o número é bem formado, não que a pessoa ou empresa existe ou está regular.

## Recuperação de senha

Em *Entrar → Esqueceu a senha?*, a pessoa recebe por e-mail um link que vale por 30 minutos e só pode ser usado uma vez (o banco guarda só o hash do link). O e-mail sai pela função `mail()` da hospedagem, como no BV Fotos.

Na Hostinger, crie um e-mail do seu domínio no hPanel (ex.: `nao-responda@ademoveis.com.br`) e coloque em `MAIL_FROM` no `config/config.php`. Sem isso, o site usa `nao-responda@` + o domínio atual, que pode cair no spam ou ser recusado.

## Estrutura

```
index.php, imoveis.php, imovel.php    páginas públicas (home, busca, detalhe)
anunciante.php                         perfil público + avaliações
login.php, cadastro.php, conta.php     login único, cadastro com escolha do tipo, minha conta
painel/                                área do anunciante (imóveis, fotos, assinatura)
chat/                                  conversas e API do chat (atualiza a cada 3 s)
admin/                                 painel exclusivo do administrador
pagamento/webhook.php                  notificações do Mercado Pago
includes/                              conexão, funções, login, layout
database/schema.sql                    estrutura do banco para o phpMyAdmin
assets/                                CSS, JavaScript e imagens
uploads/                               fotos enviadas (não versionar)
```

## Segurança já incluída

- Senhas com `password_hash` e bloqueio temporário após 5 tentativas erradas de login
- Proteção CSRF em todos os formulários e na API do chat
- Consultas com *prepared statements* (contra SQL injection) e escape de todo conteúdo exibido (contra XSS)
- Fotos validadas e regravadas como JPEG, o que descarta arquivos disfarçados, e PHP desativado na pasta `uploads/`
- Ninguém consegue se cadastrar como administrador pelo site
- O webhook consulta a API do Mercado Pago antes de ativar qualquer assinatura e valida a assinatura secreta

## Antes de lançar

- [ ] Trocar o texto provisório de `termos.php` por termos e política de privacidade revisados (LGPD)
- [ ] Ativar HTTPS (descomente as linhas no `.htaccess`)
- [ ] Trocar as credenciais de teste do Mercado Pago pelas de produção
- [ ] Apagar `instalar.php`
- [ ] Configurar backup diário do banco e da pasta `uploads/`
