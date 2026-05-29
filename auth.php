<?php
require_once 'config.php';

// Если пользователь уже авторизован - отправляем в профиль
if (isLoggedIn()) {
    header('Location: profile.php');
    exit();
}

$error = '';
$success = '';
$oldData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'phone' => ''
];

// CSRF токен
$csrf_token = generateCSRFToken();

// Обработка AJAX запросов
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Обработка AJAX логина
if ($is_ajax && isset($_POST['action']) && $_POST['action'] == 'login') {
    header('Content-Type: application/json');

    $user_ip = $_SERVER['REMOTE_ADDR'];

    // Защита: санитизация username (XSS + SQL)
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password']; // пароль не санитизируем, он идёт в password_verify()

    // Удаляем старые записи
    $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 MINUTE)")->execute();

    // Получаем информацию о блокировке
    $block_info = getBlockInfo($user_ip);
    $is_blocked = $block_info['is_blocked'];
    $wait_seconds = $block_info['wait_seconds'];

    if ($is_blocked) {
        echo json_encode([
            'success' => false,
            'error' => "⚠️ Слишком много неудачных попыток. Подождите {$wait_seconds} сек.",
            'blocked' => true,
            'wait_seconds' => $wait_seconds
        ]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?)");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        recordLoginAttempt($user_ip, true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        echo json_encode(['success' => true, 'redirect' => 'profile.php']);
        exit();
    } else {
        recordLoginAttempt($user_ip, false);
        $new_info = getBlockInfo($user_ip);
        if ($new_info['is_blocked']) {
            echo json_encode([
                'success' => false,
                'error' => "⚠️ Слишком много неудачных попыток. Подождите {$new_info['wait_seconds']} сек.",
                'blocked' => true,
                'wait_seconds' => $new_info['wait_seconds'],
                'safe_username' => $username
            ]);
        } else {
            $remaining = $new_info['remaining_attempts'];
            echo json_encode([
                'success' => false,
                'error' => "❌ Неверный логин или пароль. Осталось попыток: {$remaining}",
                'safe_username' => $username
            ]);
        }
        exit();
    }
}

// Обработка AJAX регистрации
if ($is_ajax && isset($_POST['action']) && $_POST['action'] == 'register') {
    header('Content-Type: application/json');

    // CSRF проверка
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Ошибка безопасности. Обновите страницу.']);
        exit();
    }

    // Защита: санитизация всех текстовых полей (XSS + SQL)
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);

    // Валидация
    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'error' => 'Пароли не совпадают']);
        exit();
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'error' => 'Пароль должен содержать минимум 6 символов']);
        exit();
    }
    if (strlen($username) < 3) {
        echo json_encode(['success' => false, 'error' => 'Никнейм должен содержать минимум 3 символа']);
        exit();
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode(['success' => false, 'error' => 'Никнейм может содержать только латинские буквы, цифры и подчеркивание']);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Введите корректный email адрес']);
        exit();
    }

    // Проверка на существование пользователя (SQL защита через prepare)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Пользователь с таким никнеймом или email уже существует']);
        exit();
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $verification_code = sprintf("%06d", mt_rand(1, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, verification_code, verification_expires) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$username, $email, $hashed, $full_name, $phone, $verification_code, $expires])) {
        sendVerificationCode($email, $verification_code);
        $user_id = $pdo->lastInsertId();
        $_SESSION['temp_user_id'] = $user_id;
        $_SESSION['temp_email'] = $email;
        echo json_encode(['success' => true, 'redirect' => 'verify_code.php']);
        exit();
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка регистрации']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход / Регистрация - SUNNSET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #0F0E0E;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .auth-wrapper {
            position: relative;
            width: 1100px;
            max-width: 95%;
            min-height: 700px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0px 0px 45px rgba(255, 0, 0, 0.5);
            border: 1px solid rgba(163, 0, 0, 0.5);
        }

        .forms-container {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 700px;
        }

        .form-box {
            position: absolute;
            top: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            padding: 50px;
        }

        .form-box.login {
            left: 0;
            z-index: 2;
            justify-content: flex-start;
            transform: translateX(0);
            opacity: 1;
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-box.register {
            left: 0;
            z-index: 1;
            justify-content: flex-end;
            transform: translateX(100%);
            opacity: 0;
            visibility: hidden;
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-wrapper.active .form-box.login {
            transform: translateX(-100%);
            opacity: 0;
            visibility: hidden;
        }

        .auth-wrapper.active .form-box.register {
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
            z-index: 3;
        }

        .form-inner {
            width: 100%;
            max-width: 450px;
        }

        .form-box h2 {
            font-size: 36px;
            color: #FF4343;
            margin-bottom: 40px;
            text-align: left;
            font-weight: 500;
            display: flex;
            justify-content: center;
        }

        .floating-group {
            position: relative;
            margin-bottom: 20px;
            width: 100%;
        }

        .floating-group .field-icon {
            position: absolute;
            left: 0;
            bottom: 12px;
            color: #FF4343;
            font-size: 18px;
            z-index: 2;
            transition: all 0.2s;
        }

        .floating-group input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding: 14px 0 8px 28px;
            font-size: 16px;
            color: #FFFFFF;
            font-family: inherit;
            transition: all 0.25s ease;
            outline: none;
        }

        .floating-group input:focus {
            border-bottom-color: #FF4343;
        }

        /* .floating-group input:invalid:not(:placeholder-shown) {
            border-bottom-color: #f44336;
        } */

        .floating-group label {
            position: absolute;
            left: 28px;
            bottom: 10px;
            color: #9e9e9e;
            font-size: 16px;
            font-weight: 400;
            pointer-events: none;
            transition: 0.2s ease-out;
            background: transparent;
        }

        .floating-group.float-active label,
        .floating-group.float-filled label {
            bottom: 42px;
            left: 28px;
            font-size: 12px;
            color: #FF4343;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #740000;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            background: #941607;
            transform: translateY(-2px);
        }

        .forgot-link {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .forgot-link a {
            color: #FF4343;
            text-decoration: none;
        }

        .diagonal-slider {
            position: absolute;
            top: 0;
            right: 0;
            width: 45%;
            height: 100%;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: linear-gradient(135deg,
                    #1a0000 0%,
                    #3a0000 15%,
                    #5c0000 30%,
                    #8b0000 50%,
                    #cc0000 70%,
                    #ff1a1a 85%,
                    #ff6666 100%);
            clip-path: polygon(30% 0%, 100% 0%, 100% 100%, 0% 100%);
        }

        .diagonal-slider::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(255, 100, 100, 0.4) 0%, rgba(255, 0, 0, 0) 70%);
            pointer-events: none;
        }

        @keyframes slideRightToLeft {
            0% {
                right: 0;
                left: auto;
                width: 45%;
                clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 30% 100%);
            }

            40% {
                right: 0;
                left: auto;
                width: 100%;
                clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%);
            }

            60% {
                right: auto;
                left: 0;
                width: 100%;
                clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%);
            }

            100% {
                right: auto;
                left: 0;
                width: 50%;
                clip-path: polygon(0% 0%, 100% 0%, 70% 100%, 0% 100%);
            }
        }

        @keyframes slideLeftToRight {
            0% {
                right: auto;
                left: 0;
                width: 45%;
                clip-path: polygon(0% 0%, 100% 0%, 70% 100%, 0% 100%);
            }

            40% {
                right: auto;
                left: 0;
                width: 100%;
                clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%);
            }

            60% {
                right: 0;
                left: auto;
                width: 100%;
                clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%);
            }

            100% {
                right: 0;
                left: auto;
                width: 50%;
                clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 30% 100%);
            }
        }

        .auth-wrapper.active .diagonal-slider {
            animation: slideRightToLeft 1.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .auth-wrapper:not(.active) .diagonal-slider {
            animation: slideLeftToRight 1.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .slider-content {
            position: absolute;
            width: 110%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 40px;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        .slider-content.left-content {
            left: 0;
            top: 0;
            opacity: 1;
            transform: translateX(0);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .slider-content.right-content {
            left: 0;
            top: 0;
            opacity: 0;
            transform: translateX(50px);
            visibility: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-wrapper.active .slider-content.left-content {
            opacity: 0;
            transform: translateX(-80px);
            visibility: hidden;
        }

        .auth-wrapper.active .slider-content.right-content {
            opacity: 1;
            transform: translateX(0);
            visibility: visible;
            transition-delay: 1.65s;
            width: 85%;
        }

        .auth-wrapper:not(.active) .slider-content.right-content {
            opacity: 0;
            transform: translateX(80px);
            visibility: hidden;
        }

        .auth-wrapper:not(.active) .slider-content.left-content {
            opacity: 1;
            transform: translateX(0);
            visibility: visible;
            transition-delay: 1.65s;
        }

        .slider-content h2 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .slider-content p {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .slider-btn {
            background: transparent;
            border: 2px solid white;
            padding: 12px 35px;
            border-radius: 40px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .slider-btn:hover {
            background: white;
            color: #740000;
            transform: scale(1.05);
        }

        .form-box .animate-item {
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-wrapper.active .form-box.login .animate-item {
            transform: translateX(-80px);
            opacity: 0;
        }

        .form-box.register .animate-item {
            transform: translateX(120px);
            opacity: 0;
        }

        .auth-wrapper.active .form-box.register .animate-item {
            transform: translateX(0);
            opacity: 1;
        }

        .auth-wrapper:not(.active) .form-box.login .animate-item {
            transform: translateX(0);
            opacity: 1;
        }

        .form-box h2 {
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-wrapper.active .form-box.login h2 {
            transform: translateX(-80px);
            opacity: 0;
        }

        .form-box.register h2 {
            transform: translateX(120px);
            opacity: 0;
        }

        .auth-wrapper.active .form-box.register h2 {
            transform: translateX(0);
            opacity: 1;
        }

        .auth-wrapper:not(.active) .form-box.login h2 {
            transform: translateX(0);
            opacity: 1;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(1) {
            transition-delay: 1.65s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(2) {
            transition-delay: 1.71s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(3) {
            transition-delay: 1.77s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(4) {
            transition-delay: 1.83s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(5) {
            transition-delay: 1.89s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(6) {
            transition-delay: 1.95s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(7) {
            transition-delay: 2.01s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(8) {
            transition-delay: 2.07s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(9) {
            transition-delay: 2.13s;
        }

        .auth-wrapper.active .form-box.register .animate-item:nth-child(10) {
            transition-delay: 2.20s;
        }


        .auth-wrapper.active .form-box.register h2 {
            transition-delay: 1.59s;
        }

        .auth-wrapper:not(.active) .form-box.login .animate-item:nth-child(1) {
            transition-delay: 1.65s;
        }

        .auth-wrapper:not(.active) .form-box.login .animate-item:nth-child(2) {
            transition-delay: 1.71s;
        }

        .auth-wrapper:not(.active) .form-box.login .animate-item:nth-child(3) {
            transition-delay: 1.77s;
        }

        .auth-wrapper:not(.active) .form-box.login .animate-item:nth-child(4) {
            transition-delay: 1.83s;
        }

        .auth-wrapper:not(.active) .form-box.login .animate-item:nth-child(5) {
            transition-delay: 1.89s;
        }

        .auth-wrapper:not(.active) .form-box.login .animate-item:nth-child(6) {
            transition-delay: 1.95s;
        }

        .auth-wrapper:not(.active) .form-box.login .animate-item:nth-child(7) {
            transition-delay: 2.01s;
        }

        .auth-wrapper:not(.active) .form-box.login h2 {
            transition-delay: 1.59s;
        }

        .back-home {
            position: fixed;
            bottom: 30px;
            left: 30px;
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 24px;
            border-radius: 30px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            z-index: 200;
            font-size: 14px;
        }

        .back-home:hover {
            background: #740000;
        }

        @media (max-width: 900px) {
            body {
                padding: 20px;
            }

            .auth-wrapper {
                height: auto;
                min-height: 600px;
            }

            .diagonal-slider {
                width: 100%;
                height: 200px;
                clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%);
                bottom: 0;
                top: auto;
                right: 0;
                left: auto;
            }

            .auth-wrapper.active .diagonal-slider {
                left: auto;
                right: 0;
                width: 100%;
                height: 200px;
            }

            .forms-container {
                margin-top: 200px;
                min-height: 600px;
            }

            .form-box {
                padding: 30px;
            }

            .form-box.login {
                justify-content: center;
            }

            .form-box.register {
                justify-content: center;
            }

            .form-inner {
                max-width: 100%;
            }

            .slider-content h2 {
                font-size: 28px;
            }

            @keyframes slideRightToLeft {

                0%,
                100% {
                    width: 100%;
                    height: 200px;
                }
            }

            @keyframes slideLeftToRight {

                0%,
                100% {
                    width: 100%;
                    height: 200px;
                }
            }
        }

        .timer-circle {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            position: relative;
        }

        .error-message {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #f44336;
            font-size: 14px;
        }

        .error-message.success {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border-left: 4px solid #4caf50;
        }

        .auth-wrapper {
            position: relative;
            width: 1100px;
            max-width: 95%;
            min-height: 700px;
            height: 700px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0px 0px 45px rgba(255, 0, 0, 0.5);
            border: 1px solid rgba(163, 0, 0, 0.5);
        }

        .form-box.login {
            position: relative;
        }

        .form-box.login .block-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 14, 14, 0.95);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .global-block-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .global-block-overlay .block-content {
            text-align: center;
            background: rgba(15, 14, 14, 0.95);
            padding: 40px 60px;
            border-radius: 30px;
            border: 1px solid rgba(255, 67, 67, 0.3);
            box-shadow: 0 0 50px rgba(255, 0, 0, 0.3);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .global-block-overlay .timer-circle {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            position: relative;
        }

        .global-block-overlay .timer-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 36px;
            font-weight: bold;
            color: #FF4343;
        }

        .global-block-overlay .wait-message {
            color: #FF4343;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .global-block-overlay .wait-submessage {
            color: #C4C4C4;
            font-size: 14px;
        }
    </style>
</head>


<body>
    <div class="auth-wrapper" id="authWrapper">
        <div class="forms-container">
            <!-- Форма входа -->
            <div class="form-box login">
                <div class="form-inner">
                    <h2>Вход в аккаунт</h2>
                    <div id="loginError" class="error-message" style="display: none;"></div>
                    <div class="animate-item">
                        <div class="floating-group">
                            <i class="fas fa-user-tag field-icon"></i>
                            <input type="text" id="loginUsername" required>
                            <label for="loginUsername">Никнейм или Email</label>
                        </div>
                    </div>
                    <div class="animate-item">
                        <div class="floating-group">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" id="loginPassword" required>
                            <label for="loginPassword">Пароль</label>
                        </div>
                    </div>
                    <div class="animate-item">
                        <button class="btn-submit" onclick="submitLogin()">Войти</button>
                    </div>
                    <div class="animate-item">
                        <div class="forgot-link">
                            <a href="reset_password_request.php">Забыли пароль?</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Форма регистрации -->
            <div class="form-box register">
                <div class="form-inner">
                    <h2>Создать аккаунт</h2>
                    <div id="registerError" class="error-message" style="display: none;"></div>
                    <input type="hidden" id="csrf_token" value="<?= escapeOutput($csrf_token) ?>">

                    <!-- 1. Никнейм -->
                    <div class="animate-item">
                        <div class="floating-group">
                            <i class="fas fa-user-tag field-icon"></i>
                            <input type="text" id="regUsername" required>
                            <label for="regUsername">Никнейм *</label>
                        </div>
                    </div>

                    <!-- 2. Email -->
                    <div class="animate-item">
                        <div class="floating-group">
                            <i class="fas fa-envelope field-icon"></i>
                            <input type="email" id="regEmail" required>
                            <label for="regEmail">Email *</label>
                        </div>
                    </div>

                    <!-- 3. Пароль -->
                    <div class="animate-item">
                        <div class="floating-group">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" id="regPassword" required>
                            <label for="regPassword">Пароль *</label>
                        </div>
                    </div>

                    <!-- 4. Подтверждение пароля -->
                    <div class="animate-item">
                        <div class="floating-group">
                            <i class="fas fa-check-circle field-icon"></i>
                            <input type="password" id="regConfirmPassword" required>
                            <label for="regConfirmPassword">Подтверждение пароля *</label>
                        </div>
                    </div>

                    <!-- 5. Полное имя -->
                    <div class="animate-item">
                        <div class="floating-group">
                            <i class="fas fa-user field-icon"></i>
                            <input type="text" id="regFullName">
                            <label for="regFullName">Полное имя</label>
                        </div>
                    </div>

                    <!-- 6. Телефон -->
                    <div class="animate-item">
                        <div class="floating-group">
                            <i class="fas fa-phone field-icon"></i>
                            <input type="tel" id="regPhone">
                            <label for="regPhone">Телефон</label>
                        </div>
                    </div>

                    <!-- 7. Кнопка -->
                    <div class="animate-item">
                        <button class="btn-submit" onclick="submitRegister()">Зарегистрироваться</button>
                    </div>
                </div>
            </div>

            <div class="diagonal-slider">
                <div class="slider-content left-content">
                    <h2>WELCOME BACK!</h2>
                    <p>Войдите в свой аккаунт<br>чтобы продолжить</p>
                    <button class="slider-btn" onclick="toggleAuthMode(true)">РЕГИСТРАЦИЯ</button>
                </div>
                <div class="slider-content right-content">
                    <h2>WELCOME!</h2>
                    <p>Присоединяйтесь к нам<br>и откройте мир китайской культуры</p>
                    <button class="slider-btn" onclick="toggleAuthMode(false)">ВОЙТИ</button>
                </div>
            </div>
        </div>

        <a href="javascript:history.back()" class="back-home"><i class="fas fa-arrow-left"></i> Назад</a>

        <script>
            let isBlocked = false;
            let blockTimerInterval = null;
            let globalOverlay = null;

            function toggleAuthMode(toRegister) {
                if (globalOverlay || isBlocked) return;
                const wrapper = document.getElementById('authWrapper');
                if (toRegister) {
                    wrapper.classList.add('active');
                } else {
                    wrapper.classList.remove('active');
                }
            }

            function submitLogin() {
                const usernameInput = document.getElementById('loginUsername');
                const passwordInput = document.getElementById('loginPassword');
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                const loginBtn = event.target;
                const errorDiv = document.getElementById('loginError');

                errorDiv.style.display = 'none';
                errorDiv.classList.remove('success');

                if (!username || !password) {
                    errorDiv.textContent = '❌ Заполните все поля';
                    errorDiv.style.display = 'block';
                    return;
                }

                if (isBlocked) {
                    errorDiv.textContent = '⚠️ Вход временно заблокирован. Подождите.';
                    errorDiv.style.display = 'block';
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'login');
                formData.append('username', username);
                formData.append('password', password);

                const originalText = loginBtn.innerHTML;
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Вход...';
                loginBtn.disabled = true;

                fetch('auth.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            if (data.safe_username) {
                                usernameInput.value = data.safe_username;
                            }
                            errorDiv.textContent = data.error;
                            errorDiv.style.display = 'block';
                            loginBtn.innerHTML = originalText;
                            loginBtn.disabled = false;
                            if (data.blocked && data.wait_seconds && !isBlocked) {
                                isBlocked = true;
                                startBlockTimer(data.wait_seconds);
                            }
                        }
                    })
                    .catch(error => {
                        errorDiv.textContent = '❌ Ошибка соединения с сервером';
                        errorDiv.style.display = 'block';
                        loginBtn.innerHTML = originalText;
                        loginBtn.disabled = false;
                    });
            }

            function startBlockTimer(seconds) {
                const loginBtn = document.querySelector('.form-box.login .btn-submit');
                const registerBtn = document.querySelector('.form-box.register .btn-submit');

                if (loginBtn) loginBtn.disabled = true;
                if (registerBtn) registerBtn.disabled = true;

                globalOverlay = document.createElement('div');
                globalOverlay.className = 'global-block-overlay';
                globalOverlay.innerHTML = `
                <div class="block-content">
                    <div class="timer-circle">
                        <svg width="120" height="120" viewBox="0 0 120 120" style="transform: rotate(-90deg);">
                            <circle cx="60" cy="60" r="54" fill="none" stroke-width="4" stroke="rgba(255,255,255,0.1)"/>
                            <circle cx="60" cy="60" r="54" fill="none" stroke-width="4" stroke="#FF4343" stroke-dasharray="339.29" stroke-dashoffset="339.29" id="globalTimerCircle"/>
                        </svg>
                        <div class="timer-text" id="globalTimerText">${seconds}</div>
                    </div>
                    <div class="wait-message">⚠️ Слишком много неудачных попыток</div>
                    <div class="wait-submessage">Подождите, вход будет разблокирован автоматически</div>
                </div>
            `;
                document.body.appendChild(globalOverlay);

                let remaining = seconds;
                const circumference = 339.29;
                const timerCircle = document.getElementById('globalTimerCircle');
                const timerText = document.getElementById('globalTimerText');

                if (timerCircle) {
                    timerCircle.style.strokeDasharray = circumference;
                    timerCircle.style.strokeDashoffset = circumference;
                }

                if (blockTimerInterval) clearInterval(blockTimerInterval);

                blockTimerInterval = setInterval(() => {
                    remaining--;
                    if (timerText) timerText.textContent = remaining;
                    if (timerCircle && seconds > 0) {
                        const offset = circumference - (remaining / seconds) * circumference;
                        timerCircle.style.strokeDashoffset = offset;
                    }
                    if (remaining <= 0) {
                        clearInterval(blockTimerInterval);
                        blockTimerInterval = null;
                        if (globalOverlay && globalOverlay.parentNode) globalOverlay.remove();
                        globalOverlay = null;
                        if (loginBtn) loginBtn.disabled = false;
                        if (registerBtn) registerBtn.disabled = false;
                        isBlocked = false;
                        const errorDiv = document.getElementById('loginError');
                        if (errorDiv) {
                            errorDiv.textContent = '✅ Блокировка снята! Теперь вы можете войти.';
                            errorDiv.classList.add('success');
                            errorDiv.style.display = 'block';
                            setTimeout(() => {
                                errorDiv.style.display = 'none';
                                errorDiv.classList.remove('success');
                            }, 3000);
                        }
                    }
                }, 1000);
            }

            function submitRegister() {
                const username = document.getElementById('regUsername');
                const email = document.getElementById('regEmail');
                const password = document.getElementById('regPassword');
                const confirm = document.getElementById('regConfirmPassword');
                const csrfToken = document.getElementById('csrf_token').value;

                if (!username.checkValidity()) {
                    username.reportValidity();
                    return;
                }
                if (!email.checkValidity()) {
                    email.reportValidity();
                    return;
                }
                if (!password.checkValidity()) {
                    password.reportValidity();
                    return;
                }

                if (password.value !== confirm.value) {
                    confirm.setCustomValidity('Пароли не совпадают');
                    confirm.reportValidity();
                    return;
                } else {
                    confirm.setCustomValidity('');
                }

                if (!confirm.checkValidity()) {
                    confirm.reportValidity();
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'register');
                formData.append('csrf_token', csrfToken);
                formData.append('username', username.value.trim());
                formData.append('email', email.value.trim());
                formData.append('password', password.value);
                formData.append('confirm_password', confirm.value);
                formData.append('full_name', document.getElementById('regFullName').value);
                formData.append('phone', document.getElementById('regPhone').value);

                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Регистрация...';
                btn.disabled = true;

                fetch('auth.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            const errorDiv = document.getElementById('registerError');
                            errorDiv.textContent = data.error;
                            errorDiv.style.display = 'block';
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    })
                    .catch(error => {
                        const errorDiv = document.getElementById('registerError');
                        errorDiv.textContent = '❌ Ошибка соединения с сервером';
                        errorDiv.style.display = 'block';
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
            }

            // Floating labels
            document.querySelectorAll('.floating-group input').forEach(input => {
                function updateFloatingLabel() {
                    const group = input.closest('.floating-group');
                    if (!group) return;
                    const hasValue = input.value.trim().length > 0;
                    const isFocused = document.activeElement === input;
                    if (hasValue || isFocused) {
                        group.classList.add('float-active');
                    } else {
                        group.classList.remove('float-active');
                    }
                }
                updateFloatingLabel();
                input.addEventListener('focus', updateFloatingLabel);
                input.addEventListener('blur', updateFloatingLabel);
                input.addEventListener('input', updateFloatingLabel);
            });

            // Маска для телефона
            const phoneInput = document.getElementById('regPhone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/[^\d]/g, '');
                    if (value.length > 11) value = value.slice(0, 11);
                    let formatted = '';
                    if (value.length > 0) {
                        formatted = '+7';
                        if (value.length > 1) formatted += ' (' + value.slice(1, 4);
                        if (value.length >= 5) formatted += ') ' + value.slice(4, 7);
                        if (value.length >= 8) formatted += '-' + value.slice(7, 9);
                        if (value.length >= 10) formatted += '-' + value.slice(9, 11);
                        e.target.value = formatted;
                    }
                    const group = phoneInput.closest('.floating-group');
                    if (group) {
                        if (phoneInput.value.trim().length > 0) {
                            group.classList.add('float-active');
                        } else {
                            group.classList.remove('float-active');
                        }
                    }
                });
            }

            // Сброс кастомной валидации при изменении полей
            const regPassword = document.getElementById('regPassword');
            const regConfirm = document.getElementById('regConfirmPassword');

            if (regPassword && regConfirm) {
                regPassword.addEventListener('input', function() {
                    regConfirm.setCustomValidity('');
                });
                regConfirm.addEventListener('input', function() {
                    if (regPassword.value !== regConfirm.value) {
                        regConfirm.setCustomValidity('Пароли не совпадают');
                    } else {
                        regConfirm.setCustomValidity('');
                    }
                });
            }
        </script>
</body>

</html>