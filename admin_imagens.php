<?php


require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso restrito a administradores.']);
    exit;
}

$acao = $_POST['action'] ?? $_GET['action'] ?? '';

// ---------------------------------------------------
// UPLOAD (recebe um ou mais arquivos em "imagens[]")
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
    $tamanhoMaximo = 5 * 1024 * 1024; // 5 MB por imagem

    // Pasta física onde as imagens ficam salvas
    $pastaDestino = __DIR__ . '/../imagens/produtos/';
    if (!is_dir($pastaDestino)) {
        mkdir($pastaDestino, 0755, true);
    }

    // Descobre a próxima "ordem" livre pra esse produto
    $consultaOrdem = $pdo->prepare('SELECT COALESCE(MAX(ordem), -1) FROM produto_imagens WHERE produto_id = ?');
    $consultaOrdem->execute([$produtoId]);
    $ordem = (int)$consultaOrdem->fetchColumn() + 1;

    $salvas = [];
    $erros = [];
    $arquivos = $_FILES['imagens'];
    $totalArquivos = is_array($arquivos['name']) ? count($arquivos['name']) : 0;

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
        if (!in_array($extensao, $extensoesPermitidas)) {
            $erros[] = $arquivos['name'][$i] . ': formato não permitido (use jpg, png ou webp).';
            continue;
        }

        // Nome único, pra nunca sobrescrever imagem de outro produto
        $nomeArquivo = uniqid('produto_' . $produtoId . '_') . '.' . $extensao;
        $caminhoFisico = $pastaDestino . $nomeArquivo;

        if (!move_uploaded_file($arquivos['tmp_name'][$i], $caminhoFisico)) {
            $erros[] = $arquivos['name'][$i] . ': não foi possível salvar no servidor.';
            continue;
        }

        // Caminho relativo, sem barra no início (evita o bug de caminho absoluto que já tivemos)
        $urlRelativa = 'imagens/produtos/' . $nomeArquivo;

        $inserir = $pdo->prepare('INSERT INTO produto_imagens (produto_id, url, ordem) VALUES (?, ?, ?)');
        $inserir->execute([$produtoId, $urlRelativa, $ordem]);

        $salvas[] = ['id' => $pdo->lastInsertId(), 'url' => $urlRelativa];
        $ordem++;
    }

    echo json_encode(['sucesso' => true, 'salvas' => $salvas, 'erros' => $erros]);
    exit;
}

// ---------------------------------------------------
// REMOVER IMAGEM
// ---------------------------------------------------
if ($acao === 'remover') {
    $dados = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $dados['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Imagem inválida.']);
        exit;
    }

    $consulta = $pdo->prepare('SELECT url FROM produto_imagens WHERE id = ?');
    $consulta->execute([$id]);
    $url = $consulta->fetchColumn();

    if ($url) {
        $caminhoFisico = __DIR__ . '/../' . ltrim($url, '/');
        if (is_file($caminhoFisico)) {
            @unlink($caminhoFisico);
        }
    }

    $pdo->prepare('DELETE FROM produto_imagens WHERE id = ?')->execute([$id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Imagem removida.']);
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.']);