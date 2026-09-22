<?php


require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso restrito a administradores.']);
    exit;
}
// (a letra solta "a" que existia aqui foi removida — o PHP tentava
// interpretá-la como uma constante/função chamada "a", que não
// existe, e isso gerava um erro fatal que derrubava o arquivo
// inteiro antes de chegar em qualquer ação)

$acao = $_GET['action'] ?? '';
$dados = json_decode(file_get_contents('php://input'), true) ?? [];

function garantirCampoCategoria(PDO $pdo): void
{
    $consulta = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'categoria'");
    if (!$consulta->fetch()) {
        $pdo->exec("ALTER TABLE produtos ADD categoria varchar(50) NOT NULL DEFAULT 'tenis' AFTER nome");
    }
}

// Mesmo padrão da função acima: cria as colunas só se ainda não
// existirem, então é seguro chamar toda vez que o admin abre a
// página, sem precisar rodar SQL manual em cada computador/servidor.
function garantirCamposExtras(PDO $pdo): void
{
    $consulta = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'estoque'");
    if (!$consulta->fetch()) {
        $pdo->exec("ALTER TABLE produtos ADD estoque INT NOT NULL DEFAULT 0 AFTER preco_antigo");
    }

    $consulta = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'descricao'");
    if (!$consulta->fetch()) {
        $pdo->exec("ALTER TABLE produtos ADD descricao TEXT NULL AFTER nome");
    }
}

function categoriaValida(string $categoria): bool
{
    return in_array($categoria, ['tenis', 'jaquetas', 'conjuntos', 'camisetas', 'chinelos', 'bones', 'bolsas'], true);
}

garantirCampoCategoria($pdo);
garantirCamposExtras($pdo);

// ---------------------------------------------------
// LISTAR (traz também a primeira imagem de cada produto)
// ---------------------------------------------------
if ($acao === 'listar') {
    // "p.*" já traz TODAS as colunas da tabela produtos — incluindo
    // marca e cor, sem precisar listar campo por campo. Por isso o
    // painel admin não precisa de nenhuma mudança aqui pra "puxar"
    // marca/cor pra tela de listagem: elas já vêm junto.
    $consulta = $pdo->query(
        'SELECT p.*,
            (SELECT url FROM produto_imagens WHERE produto_id = p.id ORDER BY ordem ASC LIMIT 1) AS imagem_principal
         FROM produtos p
         ORDER BY p.id DESC'
    );

    echo json_encode(['sucesso' => true, 'produtos' => $consulta->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ---------------------------------------------------
// ADICIONAR
// ---------------------------------------------------
if ($acao === 'adicionar') {
    $nome        = trim($dados['nome'] ?? '');
    $categoria   = trim($dados['categoria'] ?? '');
    // lê marca e cor do JSON enviado pelo JS; trim() tira espaço
    // em branco acidental que o <select> normalmente não geraria,
    // mas é uma proteção barata de qualquer forma
    $marca       = trim($dados['marca'] ?? '');
    $cor         = trim($dados['cor'] ?? '');
    $descricaoTexto = trim($dados['descricao'] ?? '');
    $preco       = $dados['preco'] ?? null;
    $precoAntigo = $dados['preco_antigo'] ?? null;
    $ativo       = !empty($dados['ativo']) ? 1 : 0;

    if ($nome === '' || !categoriaValida($categoria) || !is_numeric($preco) || $preco <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Preencha nome, categoria e preço corretamente.']);
        exit;
    }

    if ($precoAntigo !== null && $precoAntigo !== '' && !is_numeric($precoAntigo)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Preço antigo inválido.']);
        exit;
    }
    $precoAntigo = ($precoAntigo === '' || $precoAntigo === null) ? null : $precoAntigo;
    // descrição é opcional — string vazia vira NULL em vez de salvar
    // uma string vazia mesmo (fica mais fácil checar "tem descrição?"
    // depois, tanto no PHP quanto no JS)
    $descricaoSalvar = $descricaoTexto === '' ? null : $descricaoTexto;

    $inserir = $pdo->prepare('INSERT INTO produtos (nome, categoria, marca, cor, descricao, preco, preco_antigo, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $inserir->execute([$nome, $categoria, $marca, $cor, $descricaoSalvar, $preco, $precoAntigo, $ativo]);

    echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId(), 'mensagem' => 'Produto cadastrado!']);
    exit;
}

// ---------------------------------------------------
// ATUALIZAR
// ---------------------------------------------------
if ($acao === 'atualizar') {
    $id          = $dados['id'] ?? null;
    $nome        = trim($dados['nome'] ?? '');
    $categoria   = trim($dados['categoria'] ?? '');
    $marca       = trim($dados['marca'] ?? '');
    $cor         = trim($dados['cor'] ?? '');
    $descricaoTexto = trim($dados['descricao'] ?? '');
    $preco       = $dados['preco'] ?? null;
    $precoAntigo = $dados['preco_antigo'] ?? null;
    $ativo       = !empty($dados['ativo']) ? 1 : 0;

    if (!$id || $nome === '' || !categoriaValida($categoria) || !is_numeric($preco) || $preco <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Dados inválidos.']);
        exit;
    }
    $precoAntigo = ($precoAntigo === '' || $precoAntigo === null) ? null : $precoAntigo;
    $descricaoSalvar = $descricaoTexto === '' ? null : $descricaoTexto;

    $atualizar = $pdo->prepare(
        'UPDATE produtos SET nome = ?, categoria = ?, marca = ?, cor = ?, descricao = ?, preco = ?, preco_antigo = ?, ativo = ? WHERE id = ?'
    );
    $atualizar->execute([$nome, $categoria, $marca, $cor, $descricaoSalvar, $preco, $precoAntigo, $ativo, $id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto atualizado!']);
    exit;
}

// ---------------------------------------------------
// REMOVER (produto + imagens do banco e do disco)
// ---------------------------------------------------
if ($acao === 'remover') {
    $id = $dados['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Produto inválido.']);
        exit;
    }

    $consultaImagens = $pdo->prepare('SELECT url FROM produto_imagens WHERE produto_id = ?');
    $consultaImagens->execute([$id]);

    foreach ($consultaImagens->fetchAll(PDO::FETCH_COLUMN) as $url) {
        $caminhoFisico = __DIR__ . '/../' . ltrim($url, '/');
        if (is_file($caminhoFisico)) {
            @unlink($caminhoFisico);
        }
    }

    $pdo->prepare('DELETE FROM produto_imagens WHERE produto_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM produtos WHERE id = ?')->execute([$id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto removido.']);
    exit;
}

// ---------------------------------------------------
// ALTERNAR ATIVO/INATIVO
// ---------------------------------------------------
if ($acao === 'alternar_ativo') {
    $id = $dados['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Produto inválido.']);
        exit;
    }

    $pdo->prepare('UPDATE produtos SET ativo = NOT ativo WHERE id = ?')->execute([$id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Status atualizado.']);
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.']);