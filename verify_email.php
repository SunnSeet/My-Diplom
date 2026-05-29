<?php
require_once 'config.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';

if ($token) {
    $stmt = $pdo->prepare("SELECT id, new_email FROM users WHERE email_verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // Обновляем email
        $stmt = $pdo->prepare("UPDATE users SET email = ?, new_email = NULL, email_verified = 1, email_verification_token = NULL WHERE id = ?");
        $stmt->execute([$user['new_email'], $user['id']]);
        $_SESSION['verify_success'] = "Email успешно подтвержден!";
        header('Location: profile.php?tab=profile');
        exit();
    } else {
        $_SESSION['verify_error'] = "Неверный или просроченный токен подтверждения.";
        header('Location: profile.php?tab=profile');
        exit();
    }
}
