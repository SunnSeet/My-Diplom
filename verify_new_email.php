<?php
require_once 'config.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (!$token) {
    $_SESSION['verify_error'] = "Неверная ссылка подтверждения.";
    header('Location: profile.php?tab=profile');
    exit();
}

// Ищем пользователя с таким токеном
$stmt = $pdo->prepare("SELECT id, new_email FROM users WHERE email_verification_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['verify_error'] = "❌ Неверная или просроченная ссылка подтверждения. Запросите смену email заново.";
    header('Location: profile.php?tab=profile');
    exit();
}

if (!$user['new_email']) {
    $_SESSION['verify_error'] = "❌ Нет ожидающего подтверждения email.";
    header('Location: profile.php?tab=profile');
    exit();
}

// Проверяем, не занят ли новый email другим пользователем
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$user['new_email'], $user['id']]);
if ($stmt->fetch()) {
    // Очищаем запрос на смену email
    $stmt = $pdo->prepare("UPDATE users SET new_email = NULL, email_verification_token = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);
    $_SESSION['verify_error'] = "❌ Этот email уже занят другим пользователем. Смена email отменена.";
    header('Location: profile.php?tab=profile');
    exit();
}

// Подтверждаем новый email
$stmt = $pdo->prepare("UPDATE users SET email = ?, new_email = NULL, email_verification_token = NULL, email_verified = 1 WHERE id = ?");
if ($stmt->execute([$user['new_email'], $user['id']])) {
    $_SESSION['admin_success'] = "✅ Email успешно изменен на " . htmlspecialchars($user['new_email']) . "!";

    // Обновляем сессию, если email был изменен у текущего пользователя
    if ($_SESSION['user_id'] == $user['id']) {
        $_SESSION['email'] = $user['new_email'];
    }
} else {
    $_SESSION['verify_error'] = "❌ Ошибка при смене email. Попробуйте позже.";
}

header('Location: profile.php?tab=profile');
exit();
