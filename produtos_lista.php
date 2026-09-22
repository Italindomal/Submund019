<?php


require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$busca = trim($_GET['busca'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');

// arrays vindos de checkboxes: ?marca[]=Nike&marca[]=Adidas
$marcas = array_filter(array_map('trim', (array)($_GET['marca'] ?? [])));
$cores  = array_filter(array_map('trim', (array)($_GET['cor'] ?? [])));

// faixas de preço: ?preco[]=0-500&preco[]=1000-999999
$faixasPreco = array_filter(array_map('trim', (array)($_GET['preco'] ?? [])));

function garantirCampoCategoria(PDO $pdo): void
{
    $consulta = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'categoria'");
    if (!$consulta->fetch()) {
        $pdo->exec("ALTER TABLE produtos ADD categoria varchar(50) NOT NULL DEFAULT 'tenis' AFTER nome");
    }
}

garantirCampoCategoria($pdo);

$filtros = ['p.ativo = 1'];
$parametros = [];

if ($busca !== '') {
    $filtros[] = 'p.nome LIKE ?';
    $parametros[] = '%' . $busca . '%';
}

if ($categoria !== '') {
    $filtros[] = 'p.categoria = ?';
    $parametros[] = $categoria;
}

// marca/cor: várias opções marcadas = "qualquer uma dessas" (OR entre si),
// mas continua "E" com os outros filtros (categoria, preço etc.)
// LOWER() nos dois lados pra não depender de a caixinha do HTML bater
// exatamente com a caixa salva no banco (ex: "Nike" vs "nike").
if (!empty($marcas)) {
    $coringas = implode(',', array_fill(0, count($marcas), '?'));
    $filtros[] = "LOWER(p.marca) IN ($coringas)";
    foreach ($marcas as $marca) {
        $parametros[] = mb_strtolower($marca);
    }
}

if (!empty($cores)) {
    $coringas = implode(',', array_fill(0, count($cores), '?'));
    $filtros[] = "LOWER(p.cor) IN ($coringas)";
    foreach ($cores as $cor) {
        $parametros[] = mb_strtolower($cor);
    }
}

// preço: cada faixa marcada vira "p.preco BETWEEN min AND max",
// e as faixas marcadas se somam com OR entre elas
if (!empty($faixasPreco)) {
    $condicoesPreco = [];

    foreach ($faixasPreco as $faixa) {
        // espera o formato "min-max", ex: "0-500", "1000-999999"
        if (!preg_match('/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', $faixa, $partes)) {
            continue; // ignora valor mal formado em vez de quebrar a query
        }
        $condicoesPreco[] = 'p.preco BETWEEN ? AND ?';
        $parametros[] = $partes[1];
        $parametros[] = $partes[2];
    }

    if (!empty($condicoesPreco)) {
        $filtros[] = '(' . implode(' OR ', $condicoesPreco) . ')';
    }
}

$consulta = $pdo->prepare(
    'SELECT p.*,
        (SELECT url FROM produto_imagens WHERE produto_id = p.id ORDER BY ordem ASC LIMIT 1) AS imagem_principal
     FROM produtos p
     WHERE ' . implode(' AND ', $filtros) . '
     ORDER BY p.id DESC'
);
$consulta->execute($parametros);

echo json_encode([
    'sucesso' => true,
    'produtos' => $consulta->fetchAll(PDO::FETCH_ASSOC)
]);