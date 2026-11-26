
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

function createMailer(): PHPMailer
{
    $config = require __DIR__ . '/mail_secret.php';

    $mail = new PHPMailer(true);

    // Konfigurasi SMTP Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['username'];
    $mail->Password   = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Pengaturan email
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['username'], 'LaporWarga');

    return $mail;
}
