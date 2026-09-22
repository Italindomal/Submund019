<?php
require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$acao = $_GET['action'] ?? '';
$dados = json_decode(file_get_contents('php://input'), true) ?? [];

// ---------------------------------------------------
// CONTAR (usada pelo contador no header, em toda página)
// Fica ANTES da checagem de login de propósito: um visitante que
// não fez login também vê o header, então precisa de uma resposta
// válida (quantidade 0), não um erro 401.
// ---------------------------------------------------
if ($acao === 'contar') {
    if (!isset($_SESSION['usuario_id'])) {
        // não logado = carrinho "vazio" do ponto de vista da tela,
        // mesmo que exista carrinho de visitante salvo em outro lugar
        echo json_encode(['sucesso' => true, 'quantidade' => 0]);
        exit;
    }

    // soma a coluna "quantidade" de todas as linhas do carrinho desse
    // usuário — ex: 2 carrinhos com quantidade 3 e 1 = contador mostra 4
    $consulta = $pdo->prepare('SELECT COALESCE(SUM(quantidade), 0) AS total FROM carrinho WHERE usuario_id = ?');
    $consulta->execute([$_SESSION['usuario_id']]);
    $total = $consulta->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode(['sucesso' => true, 'quantidade' => (int)$total]);
    exit;
}

// ---------------------------------------------------
// Todas as ações abaixo desta linha exigem login
// ---------------------------------------------------
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Faça login para usar o carrinho.']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];

if ($acao === 'adicionar') {
    $produtoId = $dados['produto_id'] ?? null;
    $quantidade = (int)($dados['quantidade'] ?? 1);
    $tamanho = $dados['tamanho'] ?? null;

    if (!$produtoId) {
        http_response_code(400);
        echo json_encode(['erro' => 'Produto inválido.']);
        exit;
    }

    if ($quantidade < 1) $quantidade = 1;

    $consulta = $pdo->prepare(
        'SELECT id, quantidade FROM carrinho
         WHERE usuario_id = ? AND produto_id = ? AND (tamanho <=> ?)'
    );
    $consulta->execute([$usuarioId, $produtoId, $tamanho]);
    $item = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $novaQuantidade = $item['quantidade'] + $quantidade;

        $atualizar = $pdo->prepare(
            'UPDATE carrinho SET quantidade = ? WHERE id = ? AND usuario_id = ?'
        );
        $atualizar->execute([$novaQuantidade, $item['id'], $usuarioId]);
    } else {
        $inserir = $pdo->prepare(
            'INSERT INTO carrinho (usuario_id, produto_id, quantidade, tamanho)
             VALUES (?, ?, ?, ?)'
        );
        $inserir->execute([$usuarioId, $produtoId, $quantidade, $tamanho]);
    }

    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto adicionado ao carrinho.']);
    exit;
}

if ($acao === 'listar') {
    $consulta = $pdo->prepare(
        'SELECT
            c.id,
            c.produto_id,
            c.quantidade,
            c.tamanho,
            p.nome,
            p.preco,
            (SELECT pi.url FROM produto_imagens pi
             WHERE pi.produto_id = p.id
             ORDER BY pi.ordem ASC LIMIT 1) AS imagem
         FROM carrinho c
         INNER JOIN produtos p ON p.id = c.produto_id
         WHERE c.usuario_id = ?
         ORDER BY c.id DESC'
    );
    $consulta->execute([$usuarioId]);

    echo json_encode([
        'sucesso' => true,
        'itens' => $consulta->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

if ($acao === 'atualizar') {
    $id = $dados['id'] ?? null;
    $quantidade = (int)($dados['quantidade'] ?? 1);

    if (!$id || $quantidade < 1) {
        http_response_code(400);
        echo json_encode(['erro' => 'Quantidade inválida.']);
        exit;
    }

    $atualizar = $pdo->prepare(
        'UPDATE carrinho SET quantidade = ? WHERE id = ? AND usuario_id = ?'
    );
    $atualizar->execute([$quantidade, $id, $usuarioId]);

    echo json_encode(['sucesso' => true]);
    exit;
}

if ($acao === 'remover') {
    $id = $dados['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Item inválido.']);
        exit;
    }

    $remover = $pdo->prepare(
        'DELETE FROM carrinho WHERE id = ? AND usuario_id = ?'
    );
    $remover->execute([$id, $usuarioId]);

    echo json_encode(['sucesso' => true]);
    exit;
}

// ---------------------------------------------------
// LIMPAR (remove todos os itens do carrinho do usuário)
// ---------------------------------------------------
if ($acao === 'limpar') {
    $limpar = $pdo->prepare('DELETE FROM carrinho WHERE usuario_id = ?');
    $limpar->execute([$usuarioId]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Carrinho esvaziado.']);
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.']);