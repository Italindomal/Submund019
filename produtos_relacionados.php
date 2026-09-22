<?php


require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$categoria = $_GET['categoria'] ?? '';
$excluir = $_GET['excluir'] ?? 0;

if ($categoria === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Informe a categoria.']);
    exit;
}

$consulta = $pdo->prepare(
    'SELECT p.id, p.nome, p.preco, p.preco_antigo,
        (SELECT url FROM produto_imagens WHERE produto_id = p.id ORDER BY ordem ASC LIMIT 1) AS imagem_principal
     FROM produtos p
     WHERE p.categoria = ? AND p.ativo = 1 AND p.id != ?
     ORDER BY p.id DESC
     LIMIT 10'
);
$consulta->execute([$categoria, $excluir]);

echo json_encode([
    'sucesso' => true,
    'produtos' => $consulta->fetchAll(PDO::FETCH_ASSOC)
]);