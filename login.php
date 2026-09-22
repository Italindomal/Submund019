<?php


require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$dados = json_decode(file_get_contents('php://input'), true);

$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';

if ($email === '' || $senha === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Preencha email e senha.']);
    exit;
}

$consulta = $pdo->prepare('SELECT id, nome, senha_hash, tipo FROM usuarios WHERE email = ?');
$consulta->execute([$email]);
$usuario = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Email ou senha incorretos.']);
    exit;
}

// Gera um novo ID de sessão após o login (evita session fixation)
session_regenerate_id(true);

$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_tipo'] = $usuario['tipo'];

echo json_encode(['sucesso' => true, 'nome' => $usuario['nome']]);