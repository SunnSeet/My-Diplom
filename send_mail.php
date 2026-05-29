<?php
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';
require_once 'phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail($to, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
        // Настройки сервера
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sunnset960@gmail.com';
        $mail->Password   = 'vytc bfpw ztoo glmx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Кодировка
        $mail->CharSet = 'UTF-8';

        // Отправитель
        $mail->setFrom('noreply@sunnset.com', 'SUNNSET');
        $mail->addAddress($to);

        // Контент
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}

// function sendVerificationEmail($email, $token)
// {
//     $subject = "Подтверждение email - SUNNSET";
//     $body = "
//     <!DOCTYPE html>
//     <html>
//     <head>
//         <meta charset='UTF-8'>
//         <style>
//             body { font-family: Arial, sans-serif; background: #0F0E0E; margin: 0; padding: 0; }
//             .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #1a1a1a; border-radius: 15px; }
//             .header { text-align: center; padding: 20px; border-bottom: 2px solid #FF4343; }
//             .header h1 { color: #FF4343; margin: 0; font-size: 28px; }
//             .content { padding: 30px; text-align: center; }
//             .content p { color: #FFFFFF; font-size: 16px; line-height: 1.5; margin-bottom: 15px; }
//             .btn { display: inline-block; padding: 12px 30px; background: #740000; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
//             .btn:hover { background: #941607; }
//             .footer { text-align: center; padding: 20px; font-size: 12px; border-top: 1px solid #333; }
//             .footer p { color: #888; margin: 0; }
//             small { color: #888; }
//         </style>
//     </head>
//     <body>
//         <div class='container'>
//             <div class='header'>
//                 <h1>SUNNSET</h1>
//                 <p style='color: #C4C4C4; margin-top: 5px;'>Мир китайской культуры</p>
//             </div>
//             <div class='content'>
//                 <h2 style='color: #FF4343; margin-bottom: 20px;'>Подтверждение email адреса</h2>
//                 <p>Здравствуйте!</p>
//                 <p>Для завершения регистрации и активации аккаунта, пожалуйста, подтвердите ваш email адрес.</p>
//                 <a href='http://localhost/diplom/verify.php?token={$token}' class='btn'>Подтвердить email</a>
//                 <p>Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.</p>
//                 <p><small>Ссылка действительна 24 часа.</small></p>
//             </div>
//             <div class='footer'>
//                 <p>© 2025 SUNNSET — Все права защищены.</p>
//                 <p>г. Москва, ул. Китай-город, д. 1</p>
//             </div>
//         </div>
//     </body>
//     </html>
//     ";

//     return sendMail($email, $subject, $body);
// }
function sendVerificationEmail($email, $token)
{
    $code = substr($token, 0, 6);
    return sendVerificationCode($email, $code);
}

// Функция для отправки ссылки подтверждения нового email (для СМЕНЫ EMAIL)
function sendNewEmailVerificationLink($email, $token)
{
    $subject = "Подтверждение смены email - SUNNSET";
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #0F0E0E; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #1a1a1a; border-radius: 15px; }
            .header { text-align: center; padding: 20px; border-bottom: 2px solid #FF4343; }
            .header h1 { color: #FF4343; margin: 0; font-size: 28px; }
            .content { padding: 30px; text-align: center; }
            .content p { color: #FFFFFF; font-size: 16px; line-height: 1.5; margin-bottom: 15px; }
            .btn { display: inline-block; padding: 12px 30px; background: #740000; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
            .btn:hover { background: #941607; }
            .warning { background: rgba(244, 67, 54, 0.1); padding: 15px; border-radius: 8px; margin-top: 20px; }
            .warning p { color: #f44336; font-size: 14px; margin: 0; }
            .footer { text-align: center; padding: 20px; font-size: 12px; border-top: 1px solid #333; }
            .footer p { color: #888; margin: 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>SUNNSET</h1>
                <p style='color: #C4C4C4; margin-top: 5px;'>Мир китайской культуры</p>
            </div>
            <div class='content'>
                <h2 style='color: #FF4343; margin-bottom: 20px;'>Подтверждение смены email</h2>
                <p>Здравствуйте!</p>
                <p>Вы запросили смену email адреса в вашем аккаунте SUNNSET.</p>
                <p>Для подтверждения нового email адреса нажмите на кнопку ниже:</p>
                <a href='http://localhost/diplom/verify_new_email.php?token={$token}' class='btn'>Подтвердить новый email</a>
                <div class='warning'>
                    <p>⚠️ Ссылка действительна 24 часа.</p>
                    <p>Если вы не меняли email, проигнорируйте это письмо.</p>
                </div>
            </div>
            <div class='footer'>
                <p>© 2025 SUNNSET — Все права защищены.</p>
                <p>г. Москва, улица Ильинка, 23с1</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendMail($email, $subject, $body);
}

function sendResetEmail($email, $token)
{
    $subject = "Восстановление пароля - SUNNSET";
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #0F0E0E; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #1a1a1a; border-radius: 15px; }
            .header { text-align: center; padding: 20px; border-bottom: 2px solid #FF4343; }
            .header h1 { color: #FF4343; margin: 0; font-size: 28px; }
            .content { padding: 30px; text-align: center; }
            .content p { color: #FFFFFF; font-size: 16px; line-height: 1.5; margin-bottom: 15px; }
            .btn { display: inline-block; padding: 12px 30px; background: #740000; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
            .btn:hover { background: #941607; }
            .footer { text-align: center; padding: 20px; font-size: 12px; border-top: 1px solid #333; }
            .footer p { color: #080808; margin: 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>SUNNSET</h1>
                <p style='color: #C4C4C4; margin-top: 5px;'>Восстановление пароля</p>
            </div>
            <div class='content'>
                <h2 style='color: #FF4343; margin-bottom: 20px;'>Сброс пароля</h2>
                <p>Здравствуйте!</p>
                <p>Вы запросили восстановление пароля на сайте SUNNSET.</p>
                <p>Для установки нового пароля перейдите по ссылке ниже:</p>
                <a href='http://localhost/diplom/reset_password.php?token={$token}' class='btn'>Сбросить пароль</a>
                <p>Ссылка действительна в течение 1 часа.</p>
                <p>Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо.</p>
            </div>
            <div class='footer'>
                <p>© 2025 SUNNSET — Все права защищены.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    if (isset($_GET['debug'])) {
        echo "Ссылка для сброса: http://localhost/diplom/reset_password.php?token={$token}<br>";
        echo "Email: {$email}<br>";
    }

    return sendMail($email, $subject, $body);
}

// Добавьте эту функцию в send_mail.php
function sendVerificationCode($email, $code)
{
    $subject = "Код подтверждения - SUNNSET";
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #0F0E0E; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #1a1a1a; border-radius: 15px; }
            .header { text-align: center; padding: 20px; border-bottom: 2px solid #FF4343; }
            .header h1 { color: #FF4343; margin: 0; font-size: 28px; }
            .content { padding: 30px; text-align: center; }
            .content p { color: #FFFFFF; font-size: 16px; line-height: 1.5; margin-bottom: 15px; }
            .code-box { display: inline-block; padding: 20px 40px; background: #740000; border-radius: 12px; margin: 20px 0; }
            .code { font-size: 36px; font-weight: bold; letter-spacing: 8px; color: white; }
            .footer { text-align: center; padding: 20px; font-size: 12px; border-top: 1px solid #333; }
            .footer p { color: #888; margin: 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>SUNNSET</h1>
                <p style='color: #C4C4C4; margin-top: 5px;'>Мир китайской культуры</p>
            </div>
            <div class='content'>
                <h2 style='color: #FF4343; margin-bottom: 20px;'>Код подтверждения</h2>
                <p>Здравствуйте!</p>
                <p>Для завершения регистрации введите код подтверждения на сайте:</p>
                <div class='code-box'>
                    <div class='code'>{$code}</div>
                </div>
                <p>Код действителен в течение 15 минут.</p>
                <p>Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.</p>
            </div>
            <div class='footer'>
                <p>© 2025 SUNNSET — Все права защищены.</p>
                <p>г. Москва, улица Ильинка, 23с1</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendMail($email, $subject, $body);
}
