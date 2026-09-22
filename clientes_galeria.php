<?php


require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$produtoId = $_GET['produto_id'] ?? null;

if (!$produtoId) {
    http_response_code(400);
    echo json_encode(['erro' => 'Informe o produto_id na URL, ex: ?produto_id=5']);
    exit;
}

$consulta = $pdo->prepare('SELECT id, url FROM cliente_galeria WHERE produto_id = ? AND ativo = 1 ORDER BY ordem ASC, id DESC');
$consulta->execute([$produtoId]);

echo json_encode([
    'sucesso' => true,
    'imagens' => $consulta->fetchAll(PDO::FETCH_ASSOC)
]);