<?php

require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['erro' => 'Informe o id do produto na URL, ex: ?id=1']);
    exit;
}

$ehAdmin = isset($_SESSION['usuario_id']) && ($_SESSION['usuario_tipo'] ?? '') === 'admin';

try {
    // Colunas explícitas em vez de SELECT * pra não vazar campos internos
    // (ex: custo, margem, fornecedor) que porventura existam na tabela.
    $colunas = 'id, nome, categoria, preco, preco_antigo, ativo, descricao, criado_em';

    $sql = $ehAdmin
        ? "SELECT $colunas FROM produtos WHERE id = ?"
        : "SELECT $colunas FROM produtos WHERE id = ? AND ativo = 1";

    $consulta = $pdo->prepare($sql);
    $consulta->execute([$id]);
    $produto = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        http_response_code(404);
        echo json_encode(['erro' => 'Produto não encontrado.']);
        exit;
    }

    $consultaImagens = $pdo->prepare('SELECT id, url FROM produto_imagens WHERE produto_id = ? ORDER BY ordem ASC');
    $consultaImagens->execute([$id]);
    $produto['imagens'] = $consultaImagens->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($produto);

} catch (PDOException $e) {
    error_log('Erro ao buscar produto: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro ao buscar o produto.',
    ]);
}
