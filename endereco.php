<?php
// =====================================================
// ARQUIVO: endereco.php
// Gerencia os endereços do usuário logado.
// Ações (via ?action=): listar, adicionar, atualizar, remover, definir_principal
// Segue o mesmo padrão do carrinho.php
// =====================================================

require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Faça login para gerenciar endereços.']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$acao = $_GET['action'] ?? '';
$dados = json_decode(file_get_contents('php://input'), true) ?? [];

// ---------------------------------------------------
// LISTAR
// ---------------------------------------------------
if ($acao === 'listar') {
    $consulta = $pdo->prepare(
        'SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC'
    );
    $consulta->execute([$usuarioId]);

    echo json_encode([
        'sucesso' => true,
        'enderecos' => $consulta->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

// ---------------------------------------------------
// ADICIONAR
// ---------------------------------------------------
if ($acao === 'adicionar') {
    $apelido     = trim($dados['apelido'] ?? '');
    $cep         = trim($dados['cep'] ?? '');
    $logradouro  = trim($dados['logradouro'] ?? '');
    $numero      = trim($dados['numero'] ?? '');
    $complemento = trim($dados['complemento'] ?? '');
    $bairro      = trim($dados['bairro'] ?? '');
    $cidade      = trim($dados['cidade'] ?? '');
    $estado      = strtoupper(trim($dados['estado'] ?? ''));
    $principal   = !empty($dados['principal']) ? 1 : 0;

    if ($apelido === '' || $cep === '' || $logradouro === '' || $numero === '' ||
        $bairro === '' || $cidade === '' || $estado === '') {
        http_response_code(400);
        echo json_encode(['erro' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    if (strlen($estado) !== 2) {
        http_response_code(400);
        echo json_encode(['erro' => 'Estado inválido (use a sigla, ex: SP).']);
        exit;
    }

    // Se esse endereço for marcado como principal, tira o "principal" dos outros
    if ($principal) {
        $limpar = $pdo->prepare('UPDATE enderecos SET principal = 0 WHERE usuario_id = ?');
        $limpar->execute([$usuarioId]);
    }

    // Se é o primeiro endereço do usuário, já entra como principal automaticamente
    $contagem = $pdo->prepare('SELECT COUNT(*) FROM enderecos WHERE usuario_id = ?');
    $contagem->execute([$usuarioId]);
    if ((int)$contagem->fetchColumn() === 0) {
        $principal = 1;
    }

    $inserir = $pdo->prepare(
        'INSERT INTO enderecos (usuario_id, apelido, cep, logradouro, numero, complemento, bairro, cidade, estado, principal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $inserir->execute([$usuarioId, $apelido, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, $principal]);

    echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId(), 'mensagem' => 'Endereço cadastrado!']);
    exit;
}

// ---------------------------------------------------
// ATUALIZAR
// ---------------------------------------------------
if ($acao === 'atualizar') {
    $id = $dados['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Endereço inválido.']);
        exit;
    }

    $apelido     = trim($dados['apelido'] ?? '');
    $cep         = trim($dados['cep'] ?? '');
    $logradouro  = trim($dados['logradouro'] ?? '');
    $numero      = trim($dados['numero'] ?? '');
    $complemento = trim($dados['complemento'] ?? '');
    $bairro      = trim($dados['bairro'] ?? '');
    $cidade      = trim($dados['cidade'] ?? '');
    $estado      = strtoupper(trim($dados['estado'] ?? ''));

    if ($apelido === '' || $cep === '' || $logradouro === '' || $numero === '' ||
        $bairro === '' || $cidade === '' || $estado === '') {
        http_response_code(400);
        echo json_encode(['erro' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    $atualizar = $pdo->prepare(
        'UPDATE enderecos
         SET apelido = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?,
             bairro = ?, cidade = ?, estado = ?
         WHERE id = ? AND usuario_id = ?'
    );
    $atualizar->execute([$apelido, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, $id, $usuarioId]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Endereço atualizado!']);
    exit;
}

// ---------------------------------------------------
// REMOVER
// ---------------------------------------------------
if ($acao === 'remover') {
    $id = $dados['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Endereço inválido.']);
        exit;
    }

    $remover = $pdo->prepare('DELETE FROM enderecos WHERE id = ? AND usuario_id = ?');
    $remover->execute([$id, $usuarioId]);

    // Se o endereço removido era o principal, promove o mais recente que sobrou
    $consulta = $pdo->prepare('SELECT id FROM enderecos WHERE usuario_id = ? AND principal = 1');
    $consulta->execute([$usuarioId]);
    if (!$consulta->fetch()) {
        $promover = $pdo->prepare(
            'UPDATE enderecos SET principal = 1 WHERE usuario_id = ? ORDER BY id DESC LIMIT 1'
        );
        $promover->execute([$usuarioId]);
    }

    echo json_encode(['sucesso' => true, 'mensagem' => 'Endereço removido.']);
    exit;
}

// ---------------------------------------------------
// DEFINIR COMO PRINCIPAL
// ---------------------------------------------------
if ($acao === 'definir_principal') {
    $id = $dados['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Endereço inválido.']);
        exit;
    }

    $limpar = $pdo->prepare('UPDATE enderecos SET principal = 0 WHERE usuario_id = ?');
    $limpar->execute([$usuarioId]);

    $definir = $pdo->prepare('UPDATE enderecos SET principal = 1 WHERE id = ? AND usuario_id = ?');
    $definir->execute([$id, $usuarioId]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Endereço principal atualizado.']);
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.']);