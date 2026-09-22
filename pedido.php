<?php
// =====================================================
// ARQUIVO: pedido.php
// Ações (via ?action=): criar, listar
// - criar: transforma os itens SELECIONADOS do carrinho do usuário
//   em um pedido de verdade. Só remove do carrinho os itens que
//   foram incluídos no pedido — o restante continua lá.
// - listar: retorna o histórico de pedidos do usuário logado.
// =====================================================

require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Faça login para continuar.']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$acao = $_GET['action'] ?? '';
$dados = json_decode(file_get_contents('php://input'), true) ?? [];

// ---------------------------------------------------
// CRIAR PEDIDO (a partir dos itens selecionados do carrinho)
// ---------------------------------------------------
if ($acao === 'criar') {
    $metodoPagamento = $dados['metodo_pagamento'] ?? '';
    $itensIds = $dados['itens_ids'] ?? []; // ids da tabela `carrinho` selecionados no front

    if (!in_array($metodoPagamento, ['cartao', 'pix'], true)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Método de pagamento inválido.']);
        exit;
    }

    if (!is_array($itensIds) || count($itensIds) === 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Selecione pelo menos um item para finalizar a compra.']);
        exit;
    }

    // Garante que só ids numéricos entram na query (proteção extra além do prepare)
    $itensIds = array_values(array_filter(array_map('intval', $itensIds)));

    if (count($itensIds) === 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Itens inválidos.']);
        exit;
    }

    // Monta os placeholders (?, ?, ?) dinamicamente pro IN()
    $placeholders = implode(',', array_fill(0, count($itensIds), '?'));

    // Busca só os itens selecionados, direto do banco (nunca confia em
    // preço/total enviado pelo front — recalcula tudo aqui)
    $sql = "SELECT c.id AS carrinho_id, c.produto_id, c.quantidade, c.tamanho, p.nome, p.preco
            FROM carrinho c
            INNER JOIN produtos p ON p.id = c.produto_id
            WHERE c.usuario_id = ? AND c.id IN ($placeholders)";

    $consultaCarrinho = $pdo->prepare($sql);
    $consultaCarrinho->execute(array_merge([$usuarioId], $itensIds));
    $itensSelecionados = $consultaCarrinho->fetchAll(PDO::FETCH_ASSOC);

    if (count($itensSelecionados) === 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Nenhum item válido selecionado.']);
        exit;
    }

    $subtotal = 0;
    foreach ($itensSelecionados as $item) {
        $subtotal += $item['preco'] * $item['quantidade'];
    }

    $frete = 0; // frete grátis, igual já está no front
    $desconto = 0;
    $total = $subtotal + $frete - $desconto;

    // Número de pedido simples e único (data + aleatório)
    $numeroPedido = '#' . date('ymd') . rand(1000, 9999);

    try {
        $pdo->beginTransaction();

        $inserirPedido = $pdo->prepare(
            'INSERT INTO pedidos (usuario_id, numero_pedido, status, metodo_pagamento, subtotal, frete, desconto, total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $inserirPedido->execute([
            $usuarioId, $numeroPedido, 'processando', $metodoPagamento,
            $subtotal, $frete, $desconto, $total
        ]);
        $pedidoId = $pdo->lastInsertId();

        $inserirItem = $pdo->prepare(
            'INSERT INTO pedido_itens (pedido_id, produto_id, nome_produto, preco_unitario, quantidade, tamanho)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $idsCarrinhoParaRemover = [];
        foreach ($itensSelecionados as $item) {
            $inserirItem->execute([
                $pedidoId, $item['produto_id'], $item['nome'], $item['preco'], $item['quantidade'], $item['tamanho']
            ]);
            $idsCarrinhoParaRemover[] = $item['carrinho_id'];
        }

        // Remove do carrinho APENAS os itens que entraram nesse pedido.
        // Os itens não selecionados continuam no carrinho normalmente.
        $placeholdersRemover = implode(',', array_fill(0, count($idsCarrinhoParaRemover), '?'));
        $removerDoCarrinho = $pdo->prepare(
            "DELETE FROM carrinho WHERE usuario_id = ? AND id IN ($placeholdersRemover)"
        );
        $removerDoCarrinho->execute(array_merge([$usuarioId], $idsCarrinhoParaRemover));

        $pdo->commit();

        echo json_encode([
            'sucesso' => true,
            'numero_pedido' => $numeroPedido,
            'total' => $total
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao criar o pedido. Tente novamente.']);
    }
    exit;
}

// ---------------------------------------------------
// LISTAR PEDIDOS DO USUÁRIO (com os itens de cada um)
// ---------------------------------------------------
if ($acao === 'listar') {
    $consultaPedidos = $pdo->prepare(
        'SELECT id, numero_pedido, status, metodo_pagamento, total, criado_em
         FROM pedidos
         WHERE usuario_id = ?
         ORDER BY id DESC'
    );
    $consultaPedidos->execute([$usuarioId]);
    $pedidos = $consultaPedidos->fetchAll(PDO::FETCH_ASSOC);

    $consultaItens = $pdo->prepare(
        'SELECT nome_produto, quantidade, tamanho FROM pedido_itens WHERE pedido_id = ?'
    );

    foreach ($pedidos as &$pedido) {
        $consultaItens->execute([$pedido['id']]);
        $pedido['itens'] = $consultaItens->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['sucesso' => true, 'pedidos' => $pedidos]);
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.']);