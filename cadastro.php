<?php


require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$dados = json_decode(file_get_contents('php://input'), true);

$nome  = trim($dados['nome'] ?? '');
$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';

if ($nome === '' || $email === '' || $senha === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Preencha nome, email e senha.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Email inválido.']);
    exit;
}

if (strlen($senha) < 6) {
    http_response_code(400);
    echo json_encode(['erro' => 'A senha precisa ter no mínimo 6 caracteres.']);
    exit;
}

$consulta = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$consulta->execute([$email]);

if ($consulta->fetch()) {
    http_response_code(409);
    echo json_encode(['erro' => 'Esse email já está cadastrado.']);
    exit;
}

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

$inserir = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)');
$inserir->execute([$nome, $email, $senhaHash]);

echo json_encode(['sucesso' => true, 'mensagem' => 'Cadastro realizado!']);