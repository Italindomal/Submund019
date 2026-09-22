<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Carrega as credenciais do arquivo separado (não fica no código)
$config = require 'email-config.php';

// -----------------------------------------------------
// 1) Pega e valida os dados que vieram do formulário
// -----------------------------------------------------
$dados = json_decode(file_get_contents('php://input'), true);

$nome     = trim($dados['nome'] ?? '');
$email    = trim($dados['email'] ?? '');
$assunto  = trim($dados['assunto'] ?? '');
$mensagem = trim($dados['mensagem'] ?? '');

if ($nome === '' || $email === '' || $assunto === '' || $mensagem === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Preencha todos os campos.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Email inválido.']);
    exit;
}

// -----------------------------------------------------
// 2) Monta e envia o email
// -----------------------------------------------------
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $config['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['usuario'];
    $mail->Password   = $config['senha'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $config['porta'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($config['usuario'], $config['nome_remetente']);

    // "Reply-To" = quando você clicar em "Responder" no seu email,
    // a resposta vai direto pro cliente que preencheu o formulário
    $mail->addReplyTo($email, $nome);

    $mail->addAddress($config['email_destino']);

    $mail->isHTML(true);
    $mail->Subject = "Contato pelo site: {$assunto}";
    $mail->Body    = "
        <h2>Nova mensagem de contato</h2>
        <p><b>Nome:</b> " . htmlspecialchars($nome) . "</p>
        <p><b>Email:</b> " . htmlspecialchars($email) . "</p>
        <p><b>Assunto:</b> " . htmlspecialchars($assunto) . "</p>
        <p><b>Mensagem:</b></p>
        <p>" . nl2br(htmlspecialchars($mensagem)) . "</p>
    ";
    $mail->AltBody = "Nome: $nome\nEmail: $email\nAssunto: $assunto\n\n$mensagem";

    $mail->send();

    echo json_encode(['sucesso' => true, 'mensagem' => 'Mensagem enviada com sucesso!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível enviar. Tenta novamente mais tarde.']);
}