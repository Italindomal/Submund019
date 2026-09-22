<?php
session_start();

// Limpa todos os dados da sessão
$_SESSION = [];

// Remove o cookie de sessão do navegador
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}

session_destroy();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['sucesso' => true]);