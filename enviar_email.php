<?php
/**
 * enviar_email.php
 * -----------------
 * Script de TESTE, só pra confirmar que o PHPMailer está
 * configurado certo antes de usar no formulário de contato de
 * verdade (contato.php). Pode apagar depois de confirmar que
 * funciona.
 *
 * Esse arquivo precisa estar direto dentro de backend/, no
 * mesmo lugar que contato.php - assim os caminhos abaixo batem.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CORRIGIDO: antes apontava pra "../src/..." (uma pasta acima),
// mas o PHPMailer está em backend/PHPMailer/src/ - mesma pasta
// usada pelo contato.php, pra não ter 2 cópias da biblioteca.
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    // Configurações do Servidor do Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'isabelasueli2@gmail.com';
    $mail->Password   = 'kuqo tpat ijwk zjmk';           // senha de app do Gmail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // CORRIGIDO: faltava a vírgula entre os dois argumentos
    // (isso causava "Parse error: syntax error" e travava o arquivo inteiro)
    $mail->setFrom('isabelasueli2@gmail.com', 'Submundo019');

    $mail->addAddress('destino@email.com', 'Nome do Destinatário');
    // ^ troca esse email pelo seu próprio email, só pra testar
    //   se a mensagem chega de verdade na sua caixa de entrada

    $mail->isHTML(true);
    $mail->Subject = 'Teste de envio pelo PHPMailer';
    $mail->Body    = '<h2>Sucesso!</h2><p>Este e-mail foi enviado usando o Gmail e PHPMailer.</p>';
    $mail->AltBody = 'Texto limpo para leitores de e-mail que não aceitam HTML.';

    $mail->send();
    echo 'E-mail enviado com sucesso!';

} catch (Exception $e) {
    echo "Erro ao enviar o e-mail: {$mail->ErrorInfo}";
}