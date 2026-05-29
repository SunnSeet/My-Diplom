<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getUserData($_SESSION['user_id']);

if ($user['email_verified']) {
    $_SESSION['error'] = "Email уже подтвержден";
    header('Location: profile.php');
    exit();
}

$token = bin2hex(random_bytes(32));
$stmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
$stmt->execute([$token, $_SESSION['user_id']]);

if (sendVerificationEmail($user['email'], $token)) {
    $_SESSION['success'] = "Письмо с подтверждением отправлено повторно";
} else {
    $_SESSION['error'] = "Ошибка отправки письма. Попробуйте позже";
}

header('Location: profile.php');
exit();
