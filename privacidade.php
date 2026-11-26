<?php
// Política de privacidade e segurança (LGPD). Texto-base: peça para um advogado revisar antes do lançamento oficial.
require __DIR__ . '/includes/bootstrap.php';
$titulo = 'Privacidade e segurança';
$descricaoPagina = 'Como a ' . SITE_NOME . ' protege sua conta e trata seus dados pessoais, de acordo com a LGPD.';
$atualizada = '26/11/2026';
require __DIR__ . '/includes/header.php';
?>
<div class="container secao texto-legal politica">
  <h1>Privacidade e segurança</h1>
  <p class="sub">Atualizada em <?= $atualizada ?>. Aqui explicamos, sem juridiquês, quais dados a <?= e(SITE_NOME) ?> guarda, para que servem e como protegemos sua conta. Seguimos a Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018).</p>

  <nav class="politica-indice" aria-label="Nesta página">
    <a href="#dados">Dados que guardamos</a>
    <a href="#publico">O que fica visível</a>
    <a href="#compartilhamento">Compartilhamento</a>
    <a href="#seguranca">Como protegemos</a>
    <a href="#cookies">Cookies</a>
    <a href="#direitos">Seus direitos</a>
    <a href="#dicas">Dicas para negociar com segurança</a>
  </nav>

  <h2 id="dados">Dados que guardamos</h2>
  <ul>
    <li><strong>Cadastro:</strong> nome, e-mail, telefone, CPF (ou CNPJ, para empresas), cidade e, se você informar, o número do CRECI.</li>
    <li><strong>Anúncios:</strong> descrição, endereço, valores e fotos dos imóveis publicados.</li>
    <li><strong>Conversas e avaliações:</strong> mensagens trocadas pelo chat do site e as notas dadas aos anunciantes.</li>
    <li><strong>Assinatura:</strong> plano, situação e valor das cobranças. Os dados do cartão ou da conta são digitados direto no Mercado Pago; eles nunca passam pelo nosso site.</li>
    <li><strong>Registros de acesso:</strong> data, hora e endereço de internet (IP) de entradas na conta, tentativas de senha e pedidos de nova senha.</li>
  </ul>
  <p>Usamos esses dados só para fazer o site funcionar: criar e proteger sua conta, publicar anúncios, entregar mensagens, cobrar a assinatura de quem anuncia, evitar fraudes e cumprir a lei. Não vendemos dados e não enviamos propaganda de terceiros.</p>

  <h2 id="publico">O que fica visível no site</h2>
  <p>Do anunciante, ficam públicos o nome, a foto, a cidade, a apresentação, os anúncios, as avaliações recebidas, se o CRECI foi verificado e o WhatsApp, quando ele decide mostrar. Do cliente, só o nome aparece, e apenas para o anunciante com quem conversa e junto às avaliações que escrever.</p>
  <p>CPF, CNPJ, e-mail e senha nunca aparecem para outros usuários.</p>

  <h2 id="compartilhamento">Com quem os dados são compartilhados</h2>
  <ul>
    <li><strong>Mercado Pago:</strong> processa os pagamentos das assinaturas. Recebe o e-mail informado para a cobrança e o valor do plano.</li>
    <li><strong>Hostinger:</strong> empresa onde o site e o banco de dados ficam hospedados.</li>
    <li><strong>Google Fonts:</strong> fornece as fontes de letra do site e, como todo site visitado, recebe o endereço de internet do navegador.</li>
    <li><strong>ViaCEP:</strong> quando um anunciante digita o CEP de um imóvel, o número do CEP é consultado para preencher o endereço.</li>
    <li><strong>Autoridades:</strong> somente quando houver ordem judicial ou obrigação legal.</li>
  </ul>

  <h2 id="seguranca">Como protegemos sua conta e seus dados</h2>
  <ul class="politica-protecoes">
    <li><strong>Conexão criptografada (HTTPS).</strong> Tudo o que você envia, como senha, mensagens e documentos, viaja protegido. Quem abre o site sem o cadeado é levado automaticamente para a versão segura.</li>
    <li><strong>Senha que nem nós vemos.</strong> Guardamos só uma versão embaralhada da senha (bcrypt). Por isso ninguém da equipe consegue ler a sua senha, nem vai pedi-la.</li>
    <li><strong>Bloqueio contra tentativas de adivinhar a senha.</strong> Depois de 5 senhas erradas seguidas, a conta fica travada para novas tentativas por 15 minutos. Senhas muito comuns, como 12345678, não são aceitas.</li>
    <li><strong>Saída automática.</strong> A conta é desconectada depois de algumas horas sem uso. Ao trocar a senha, os outros aparelhos conectados saem na hora.</li>
    <li><strong>Recuperação de senha segura.</strong> O link enviado por e-mail vale por 30 minutos e funciona uma única vez.</li>
    <li><strong>Contas de pessoas reais.</strong> Todo cadastro exige CPF ou CNPJ válido, e cada documento só pode ter uma conta. O CRECI informado é conferido pela nossa equipe antes de ganhar o selo de verificado.</li>
    <li><strong>Fotos sem localização escondida.</strong> As fotos enviadas são reprocessadas pelo site, o que apaga dados internos da câmera, como a localização GPS de onde foram tiradas.</li>
    <li><strong>Proteção contra ataques comuns.</strong> Formulários com código de verificação, bloqueio de conteúdo malicioso nas páginas e limites contra robôs e envio de spam.</li>
    <li><strong>Pagamentos fora do site.</strong> A cobrança acontece no ambiente do Mercado Pago. O site só recebe a confirmação de que o pagamento foi feito.</li>
    <li><strong>Acesso restrito.</strong> O painel de administração só abre para contas de administrador, e cada acesso fica registrado.</li>
  </ul>
  <p>Nenhum sistema é 100% imune. Se percebermos um incidente que possa afetar seus dados, avisaremos você e a Autoridade Nacional de Proteção de Dados (ANPD), como manda a lei.</p>

  <h2 id="cookies">Cookies</h2>
  <p>Usamos um único cookie, necessário para manter você conectado enquanto navega. Ele some quando você fecha o navegador ou sai da conta. Não usamos cookies de propaganda nem de rastreamento.</p>

  <h2 id="direitos">Seus direitos</h2>
  <p>Você pode pedir a qualquer momento para ver os dados que temos sobre você, corrigir o que estiver errado (a maior parte pode ser alterada em <a href="<?= url('conta.php') ?>">Minha conta</a>), levar seus dados para outro serviço ou excluir sua conta. Para isso, fale com o nosso atendimento pelos contatos abaixo. Respondemos em até 15 dias.</p>
  <p>Guardamos os dados enquanto a conta existir. Depois da exclusão, apagamos tudo, exceto o que a lei manda manter: os registros de acesso ficam guardados por 6 meses (Marco Civil da Internet) e os dados de cobrança pelo prazo exigido pela legislação fiscal.</p>

  <h2 id="dicas">Dicas para negociar com segurança</h2>
  <ul>
    <li>Nunca informe sua senha a ninguém. A <?= e(SITE_NOME) ?> nunca pede senha por WhatsApp, telefone ou e-mail.</li>
    <li>Desconfie de quem pede depósito, sinal ou "taxa de reserva" antes de você visitar o imóvel.</li>
    <li>Prefira anunciantes com o selo "CRECI verificado" e confira as avaliações no perfil.</li>
    <li>Visite o imóvel pessoalmente e confira a documentação antes de assinar ou pagar qualquer coisa.</li>
    <li>Viu um anúncio suspeito? Avise o nosso atendimento.</li>
  </ul>

  <h2 id="contato">Contato</h2>
  <p>Atendimento e pedidos sobre dados pessoais: Samuel Venâncio, WhatsApp <a href="<?= e(link_whatsapp('88982237038', 'Olá! Tenho uma dúvida sobre privacidade e segurança na Ademimóveis.')) ?>" target="_blank" rel="noopener">(88) 98223-7038</a>, e-mail <a href="mailto:contato@ademimoveis.com.br">contato@ademimoveis.com.br</a>.</p>
  <p>Esta política pode mudar para acompanhar melhorias no site ou mudanças na lei. A data no topo mostra a última atualização. Veja também os <a href="<?= url('termos.php') ?>">Termos de uso</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
