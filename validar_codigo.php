<?php
// confere se o código digitado bate com o que foi salvo na sessão
// e se ainda não expirou

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Tente novamente']);
    exit;
}

$dadosRecebidos = json_decode(file_get_contents('php://input'), true);
$codigoDigitado = trim($dadosRecebidos['codigo'] ?? '');
$email          = trim($dadosRecebidos['email'] ?? '');

// se não tem nada guardado na sessão, não tem o que validar
// (ex: pessoa entrou direto nessa página sem passar pela anterior)
if (empty($_SESSION['codigo_redefinicao']) || empty($_SESSION['email_redefinicao'])) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Solicite um novo código']);
    exit;
}

// confere se o código expirou (10 minutos, definido no enviar_email.php)
if (time() > $_SESSION['codigo_expira_em']) {
    unset($_SESSION['codigo_redefinicao'], $_SESSION['email_redefinicao'], $_SESSION['codigo_expira_em']);
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Código expirado, solicite outro']);
    exit;
}

// confere se o email bate com o que gerou o código
// (evita que alguém use o código de outra pessoa trocando o email no fetch)
if ($email !== $_SESSION['email_redefinicao']) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Tente novamente']);
    exit;
}

// finalmente compara o código digitado com o código salvo
if ($codigoDigitado !== $_SESSION['codigo_redefinicao']) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Código incorreto']);
    exit;
}

// código certo: marca na sessão que esse email já foi verificado
// isso vai ser checado depois no arquivo que troca a senha de verdade,
// pra ninguém trocar a senha sem ter passado por essa validação
$_SESSION['email_verificado'] = $email;

// já pode limpar o código, ele não serve mais pra nada depois de validado
unset($_SESSION['codigo_redefinicao'], $_SESSION['codigo_expira_em']);

echo json_encode(['sucesso' => true, 'mensagem' => 'SUCESSO! Código validado']);