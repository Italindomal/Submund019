<?php


require 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$acao = $_GET['acao'] ?? '';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['logado' => false, 'sucesso' => false, 'mensagem' => 'Não autenticado.']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$corpo = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($acao) {

    // ============================================================
    // GET ?acao=perfil
    // ============================================================
    case 'perfil':
        $consulta = $pdo->prepare(
            'SELECT id, nome, email, telefone, data_nascimento, genero, cpf, tipo
             FROM usuarios WHERE id = ?'
        );
        $consulta->execute([$usuarioId]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'logado' => true,
            'usuario' => $usuario
        ]);
        break;

    // ============================================================
    // POST ?acao=salvar_perfil
    // body: { nome, telefone, data_nascimento, genero, cpf }
    // ============================================================
    case 'salvar_perfil':
        $nome           = trim($corpo['nome'] ?? '');
        $telefone       = trim($corpo['telefone'] ?? '');
        $dataNascimento = trim($corpo['data_nascimento'] ?? '');
        $genero         = trim($corpo['genero'] ?? '');
        $cpf            = preg_replace('/\D/', '', $corpo['cpf'] ?? '');

        if ($nome === '') {
            echo json_encode(['sucesso' => false, 'mensagem' => 'O nome não pode ficar em branco.']);
            break;
        }

        if ($telefone !== '' && !preg_match('/^\(?\d{2}\)?\s?9?\s?\d{4}-?\d{4}$/', $telefone)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Telefone em formato inválido.']);
            break;
        }

        if ($cpf !== '' && strlen($cpf) !== 11) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'CPF inválido.']);
            break;
        }

        $dataNascimentoFinal = $dataNascimento !== '' ? $dataNascimento : null;
        $cpfFinal = $cpf !== '' ? $cpf : null;
        $generoFinal = $genero !== '' ? $genero : null;

        try {
            $atualizar = $pdo->prepare(
                'UPDATE usuarios
                 SET nome = ?, telefone = ?, data_nascimento = ?, genero = ?, cpf = ?
                 WHERE id = ?'
            );
            $atualizar->execute([$nome, $telefone, $dataNascimentoFinal, $generoFinal, $cpfFinal, $usuarioId]);
            echo json_encode(['sucesso' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar. Tente novamente.']);
        }
        break;

    // ============================================================
    // POST ?acao=salvar_email
    // body: { email_novo, senha_atual }
    // ============================================================
    case 'salvar_email':
        $emailNovo = trim($corpo['email_novo'] ?? '');
        $senhaAtual = $corpo['senha_atual'] ?? '';

        if ($emailNovo === '' || !filter_var($emailNovo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Digite um email válido.']);
            break;
        }

        if ($senhaAtual === '') {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Confirme sua senha atual para alterar o email.']);
            break;
        }

        // Confere a senha atual antes de qualquer mudança sensível
        $consulta = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $consulta->execute([$usuarioId]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($senhaAtual, $usuario['senha_hash'])) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Senha atual incorreta.']);
            break;
        }

        // Garante que o novo email não está em uso por outra conta
        $consultaEmail = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
        $consultaEmail->execute([$emailNovo, $usuarioId]);
        if ($consultaEmail->fetch()) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Este email já está em uso.']);
            break;
        }

        try {
            $atualizar = $pdo->prepare('UPDATE usuarios SET email = ? WHERE id = ?');
            $atualizar->execute([$emailNovo, $usuarioId]);
            echo json_encode(['sucesso' => true, 'email' => $emailNovo]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar. Tente novamente.']);
        }
        break;

    // ============================================================
    // POST ?acao=salvar_senha
    // body: { senha_atual, senha_nova, confirmar_senha_nova }
    // ============================================================
    case 'salvar_senha':
        $senhaAtual = $corpo['senha_atual'] ?? '';
        $senhaNova = $corpo['senha_nova'] ?? '';
        $confirmarSenhaNova = $corpo['confirmar_senha_nova'] ?? '';

        if ($senhaAtual === '' || $senhaNova === '' || $confirmarSenhaNova === '') {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Preencha todos os campos de senha.']);
            break;
        }

        if (strlen($senhaNova) < 8) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'A nova senha precisa ter pelo menos 8 caracteres.']);
            break;
        }

        if ($senhaNova !== $confirmarSenhaNova) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'As senhas novas não coincidem.']);
            break;
        }

        $consulta = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $consulta->execute([$usuarioId]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($senhaAtual, $usuario['senha_hash'])) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Senha atual incorreta.']);
            break;
        }

        if (password_verify($senhaNova, $usuario['senha_hash'])) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Escolha uma senha diferente da atual.']);
            break;
        }

        try {
            $novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);
            $atualizar = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
            $atualizar->execute([$novoHash, $usuarioId]);
            session_regenerate_id(true);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Senha alterada com sucesso.']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar. Tente novamente.']);
        }
        break;

    // ============================================================
    // POST ?acao=sair
    // ============================================================
    case 'sair':
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametros['path'],
                $parametros['domain'],
                $parametros['secure'],
                $parametros['httponly']
            );
        }

        session_destroy();
        echo json_encode(['sucesso' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida.']);
        break;
}
