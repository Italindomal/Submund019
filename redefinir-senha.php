<?php
/**
 * redefinir-senha.php
 * ---------------------
 * Os 3 passos da redefinição de senha juntos, escolhidos pelo
 * parâmetro "acao" na URL (mesmo padrão do perfil.php e do
 * endereco.php):
 *
 *   POST redefinir-senha.php?acao=solicitar_codigo  { email }
 *   POST redefinir-senha.php?acao=validar_codigo     { codigo, email }
 *   POST redefinir-senha.php?acao=trocar_senha        { email, senha }
 */

// esconde erros do PHP na tela (eles viravam HTML no meio da resposta
// e quebravam o JSON no front) — em vez disso, qualquer erro fatal é
// capturado abaixo e devolvido como JSON de verdade, com a mensagem real
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    require 'config.php';
    session_start();

    $acao = $_GET['acao'] ?? '';

    switch ($acao) {
        case 'solicitar_codigo':
            acaoSolicitarCodigo($pdo);
            break;

        case 'validar_codigo':
            acaoValidarCodigo();
            break;

        case 'trocar_senha':
            acaoTrocarSenha($pdo);
            break;

        default:
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Ação inválida']);
    }

} catch (\Throwable $erroFatal) {
    // isso não deveria acontecer em produção, mas enquanto o site tá
    // sendo montado, é bem mais útil ver a mensagem real do que só
    // "Erro de conexão" sem saber o motivo
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'FALHA! Erro interno. Tente novamente mais tarde.'
    ]);
}


// =========================================================================
// ETAPA 1 — gera o código, salva na sessão e envia por email
// =========================================================================
function acaoSolicitarCodigo(PDO $pdo): void
{
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Tente novamente']);
        return;
    }

    $dados = json_decode(file_get_contents('php://input'), true);
    $email = trim($dados['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Digite um email válido']);
        return;
    }

    // confere se existe uma conta com esse email antes de gastar envio de email.
    // (opção de design: revela se o email existe ou não, o que é melhor pra
    // experiência do usuário, mas tecnicamente permite alguém descobrir quais
    // emails têm conta testando aqui — troca aceitável pra um TCC)
    $consulta = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $consulta->execute([$email]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Não existe conta com esse email']);
        return;
    }

    // código de 6 dígitos, com zero à esquerda se precisar (ex: "004821")
    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $_SESSION['codigo_redefinicao'] = $codigo;
    $_SESSION['email_redefinicao']  = $email;
    $_SESSION['codigo_expira_em']   = time() + 600; // 10 minutos

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'isabelasueli2@gmail.com';
        $mail->Password   = GMAIL_APP_PASSWORD; // definido no config.php
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('isabelasueli2@gmail.com', 'Submundo019');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Seu código de redefinição de senha - Submundo019';
        $mail->Body    = "<h2>Redefinição de senha</h2><p>Seu código é: <b style=\"font-size:22px;letter-spacing:3px;\">{$codigo}</b></p><p>Ele expira em 10 minutos. Se você não pediu essa redefinição, ignore este email.</p>";
        $mail->AltBody = "Seu código de redefinição de senha é: {$codigo} (expira em 10 minutos)";

        $mail->send();

        echo json_encode(['sucesso' => true, 'mensagem' => 'SUCESSO! Código enviado pro seu email']);

    } catch (Exception $e) {
        error_log('Falha ao enviar código de redefinição: ' . $mail->ErrorInfo);
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Não foi possível enviar o email, tente novamente']);
    }
}


// =========================================================================
// ETAPA 2 — confere se o código bate e não expirou
// =========================================================================
function acaoValidarCodigo(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Tente novamente']);
        return;
    }

    $dadosRecebidos = json_decode(file_get_contents('php://input'), true);
    $codigoDigitado = trim($dadosRecebidos['codigo'] ?? '');
    $email          = trim($dadosRecebidos['email'] ?? '');

    // se não tem nada guardado na sessão, não tem o que validar
    // (ex: pessoa entrou direto nessa etapa sem passar pela anterior)
    if (empty($_SESSION['codigo_redefinicao']) || empty($_SESSION['email_redefinicao'])) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Solicite um novo código']);
        return;
    }

    // confere se o código expirou (10 minutos, definido em solicitar_codigo)
    if (time() > $_SESSION['codigo_expira_em']) {
        unset($_SESSION['codigo_redefinicao'], $_SESSION['email_redefinicao'], $_SESSION['codigo_expira_em']);
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Código expirado, solicite outro']);
        return;
    }

    // confere se o email bate com o que gerou o código
    // (evita que alguém use o código de outra pessoa trocando o email no fetch)
    if ($email !== $_SESSION['email_redefinicao']) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Tente novamente']);
        return;
    }

    // compara o código digitado com o código salvo — hash_equals em vez de
    // !==, pra não vazar (por tempo de resposta) quantos caracteres bateram
    if (!hash_equals($_SESSION['codigo_redefinicao'], $codigoDigitado)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Código incorreto']);
        return;
    }

    // código certo: marca na sessão que esse email já foi verificado —
    // isso vai ser checado na etapa 3, pra ninguém trocar a senha sem
    // ter passado por essa validação
    $_SESSION['email_verificado'] = $email;

    // já pode limpar o código, ele não serve mais pra nada depois de validado
    unset($_SESSION['codigo_redefinicao'], $_SESSION['codigo_expira_em']);

    echo json_encode(['sucesso' => true, 'mensagem' => 'SUCESSO! Código validado']);
}


// =========================================================================
// ETAPA 3 — troca a senha de verdade
// =========================================================================
function acaoTrocarSenha(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Método não permitido']);
        return;
    }

    // só deixa trocar a senha se essa sessão realmente validou o código antes
    // (trava de segurança, evita chamar essa ação direto pulando a etapa do código)
    if (empty($_SESSION['email_verificado'])) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Valide o código novamente']);
        return;
    }

    $dados = json_decode(file_get_contents('php://input'), true);

    $email = trim($dados['email'] ?? '');
    $senha = $dados['senha'] ?? '';

    // confere se o email mandado bate com o que foi verificado na etapa do código
    // (evita que alguém troque o email no fetch e mexa na conta de outra pessoa)
    if ($email === '' || $email !== $_SESSION['email_verificado']) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Valide o código novamente']);
        return;
    }

    if (strlen($senha) < 8) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! A senha precisa ter pelo menos 8 caracteres']);
        return;
    }

    $consultaSenha = $pdo->prepare('SELECT id, senha_hash FROM usuarios WHERE email = ?');
    $consultaSenha->execute([$email]);
    $usuario = $consultaSenha->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Tente novamente']);
        return;
    }

    if (password_verify($senha, $usuario['senha_hash'])) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Escolha uma senha diferente da atual']);
        return;
    }

    // confere se o usuário existe antes de tentar atualizar
    // nunca salva a senha em texto puro, sempre com hash
    // (igual o login.php já faz a verificação com password_verify)
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $atualizar = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE email = ?');
    $atualizar->execute([$senhaHash, $email]);

    // limpa as travas da sessão, esse fluxo já terminou
    unset($_SESSION['email_verificado'], $_SESSION['email_redefinicao']);
    session_regenerate_id(true);

    echo json_encode(['sucesso' => true, 'mensagem' => 'SUCESSO! Senha alterada']);
}
