<?php

require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

// só deixa trocar a senha se essa sessão realmente validou o código antes
// (trava de segurança, evita chamar esse endpoint direto pulando a etapa do código)
if (empty($_SESSION['email_verificado'])) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Valide o código novamente']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);

$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';

// confere se o email mandado bate com o que foi verificado na etapa do código
// (evita que alguém troque o email no fetch e mexa na conta de outra pessoa)
if ($email === '' || $email !== $_SESSION['email_verificado']) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Valide o código novamente']);
    exit;
}

if (strlen($senha) < 8) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! A senha precisa ter pelo menos 8 caracteres']);
    exit;
}

// confere se o usuário existe antes de tentar atualizar
$consulta = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$consulta->execute([$email]);
$usuario = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'mensagem' => 'FALHA! Tente novamente']);
    exit;
}

// nunca salva a senha em texto puro, sempre com hash
// (igual o login.php já faz a verificação com password_verify)
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

$atualizar = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE email = ?');
$atualizar->execute([$senhaHash, $email]);

// limpa a trava da sessão, pra não poder trocar a senha de novo sem validar outro código
unset($_SESSION['email_verificado']);

echo json_encode(['sucesso' => true, 'mensagem' => 'SUCESSO! Senha alterada']);