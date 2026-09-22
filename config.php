<?php

$host    = 'localhost';
$banco   = 'submundo';
$usuario = 'root';
$senha   = '';
define('GMAIL_APP_PASSWORD', 'kuqo tpat ijwk zjmk');

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );
} catch (PDOException $erro) {
    die(json_encode(['erro' => 'Falha na conexão: ' . $erro->getMessage()]));
}