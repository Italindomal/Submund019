<?php
/**
 * email-config.php
 * -----------------
 * Guarda as credenciais do Gmail separadas do resto do código.
 *
 * IMPORTANTE: esse arquivo NÃO deve ser enviado pro GitHub.
 * Adiciona essa linha no seu arquivo .gitignore (na raiz do projeto):
 *
 *     backend/email-config.php
 *
 * Se esse arquivo já foi enviado pro GitHub antes de você
 * adicionar ele no .gitignore, ele continua lá no histórico -
 * nesse caso, troque a senha de app do Gmail por uma nova.
 */

return [
    'host'     => 'smtp.gmail.com',
    'usuario'  => 'isabelasueli2@gmail.com',
    'senha'    => 'kuqo tpat ijwk zjmk',   // senha de app do Gmail (16 letras)
    'porta'    => 587,
    'nome_remetente' => 'Submundo019',
    'email_destino'  => 'italokj43@gmail.com', // pra onde as mensagens de contato vão
];