<?php
require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso restrito a administradores.']);
    exit;
}

function garantirTabelaGaleriaClientes(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cliente_galeria (
            id int NOT NULL AUTO_INCREMENT,
            produto_id int NULL,
            url varchar(255) NOT NULL,
            ordem int NOT NULL DEFAULT 0,
            ativo tinyint(1) NOT NULL DEFAULT 1,
            criado_em timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );

    // Se a tabela já existia sem a coluna, adiciona agora
    $consulta = $pdo->query("SHOW COLUMNS FROM cliente_galeria LIKE 'produto_id'");
    if (!$consulta->fetch()) {
        $pdo->exec("ALTER TABLE cliente_galeria ADD produto_id int NULL AFTER id");
    }
}

garantirTabelaGaleriaClientes($pdo);

$acao = $_POST['action'] ?? $_GET['action'] ?? '';

// ---------------------------------------------------
// LISTAR (fotos de um produto específico)
// ---------------------------------------------------
if ($acao === 'listar') {
    $produtoId = $_GET['produto_id'] ?? null;

    if (!$produtoId) {
        http_response_code(400);
        echo json_encode(['erro' => 'Informe o produto_id.']);
        exit;
    }

    $consulta = $pdo->prepare('SELECT id, url, ativo FROM cliente_galeria WHERE produto_id = ? ORDER BY ordem ASC, id DESC');
    $consulta->execute([$produtoId]);
    echo json_encode(['sucesso' => true, 'imagens' => $consulta->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ---------------------------------------------------
// UPLOAD (fotos de clientes de um produto específico)
// ---------------------------------------------------
if ($acao === 'upload') {
    $produtoId = $_POST['produto_id'] ?? null;

    if (!$produtoId) {
        http_response_code(400);
        echo json_encode(['erro' => 'Produto inválido.']);
        exit;
    }

    if (empty($_FILES['imagens'])) {
        http_response_code(400);
        echo json_encode(['erro' => 'Nenhuma imagem enviada.']);
        exit;
    }

    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
    $tamanhoMaximo = 5 * 1024 * 1024;
    $pastaDestino = __DIR__ . '/../imagens/clientes/';

    if (!is_dir($pastaDestino)) {
        mkdir($pastaDestino, 0755, true);
    }

    $consultaOrdem = $pdo->prepare('SELECT COALESCE(MAX(ordem), -1) FROM cliente_galeria WHERE produto_id = ?');
    $consultaOrdem->execute([$produtoId]);
    $ordem = (int)$consultaOrdem->fetchColumn() + 1;

    $arquivos = $_FILES['imagens'];
    $totalArquivos = is_array($arquivos['name']) ? count($arquivos['name']) : 0;
    $salvas = [];
    $erros = [];

    for ($i = 0; $i < $totalArquivos; $i++) {
        if ($arquivos['error'][$i] !== UPLOAD_ERR_OK) {
            $erros[] = $arquivos['name'][$i] . ': falha no upload.';
            continue;
        }

        if ($arquivos['size'][$i] > $tamanhoMaximo) {
            $erros[] = $arquivos['name'][$i] . ': arquivo maior que 5MB.';
            continue;
        }

        $extensao = strtolower(pathinfo($arquivos['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($extensao, $extensoesPermitidas, true)) {
            $erros[] = $arquivos['name'][$i] . ': formato não permitido.';
            continue;
        }

        $nomeArquivo = uniqid('cliente_' . $produtoId . '_') . '.' . $extensao;
        $caminhoFisico = $pastaDestino . $nomeArquivo;

        if (!move_uploaded_file($arquivos['tmp_name'][$i], $caminhoFisico)) {
            $erros[] = $arquivos['name'][$i] . ': não foi possível salvar.';
            continue;
        }

        $urlRelativa = 'imagens/clientes/' . $nomeArquivo;
        $inserir = $pdo->prepare('INSERT INTO cliente_galeria (produto_id, url, ordem, ativo) VALUES (?, ?, ?, 1)');
        $inserir->execute([$produtoId, $urlRelativa, $ordem]);

        $salvas[] = ['id' => $pdo->lastInsertId(), 'url' => $urlRelativa];
        $ordem++;
    }

    echo json_encode(['sucesso' => true, 'salvas' => $salvas, 'erros' => $erros]);
    exit;
}

// ---------------------------------------------------
// REMOVER
// ---------------------------------------------------
if ($acao === 'remover') {
    $dados = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $dados['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Imagem inválida.']);
        exit;
    }

    $consulta = $pdo->prepare('SELECT url FROM cliente_galeria WHERE id = ?');
    $consulta->execute([$id]);
    $url = $consulta->fetchColumn();

    if ($url) {
        $caminhoFisico = __DIR__ . '/../' . ltrim($url, '/');
        if (is_file($caminhoFisico)) {
            @unlink($caminhoFisico);
        }
    }

    $pdo->prepare('DELETE FROM cliente_galeria WHERE id = ?')->execute([$id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Imagem removida.']);
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.']);