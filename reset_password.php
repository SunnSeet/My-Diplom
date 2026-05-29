<?php
require_once 'config.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error = '';
$success = '';

if (!$token) {
    header('Location: login.php');
    exit();
}

// Исправленный запрос - используем CONVERT_TZ если нужно
// Для MySQL 8+ можно просто сравнивать
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > ?");
$stmt->execute([$token, $now]);
$user = $stmt->fetch();

if (!$user) {
    // Проверяем, существует ли токен, но истек
    $stmt = $pdo->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $expired = $stmt->fetch();

    if ($expired) {
        $error = "Срок действия ссылки истек. Запросите восстановление пароля заново.";
        // Очищаем истекший токен
        $stmt = $pdo->prepare("UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$expired['id']]);
    } else {
        $error = "Неверная ссылка для восстановления пароля.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $user) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Пароль должен содержать минимум 6 символов";
    } elseif ($password !== $confirm) {
        $error = "Пароли не совпадают";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        if ($stmt->execute([$hashed, $user['id']])) {
            $success = "✅ Пароль успешно изменен! Теперь вы можете войти.";
            header('refresh:2;url=login.php');
        } else {
            $error = "Ошибка при изменении пароля";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля - SUNNSET</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .auth-page {
            min-height: calc(100vh - 300px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 20px;
        }

        .auth-container {
            max-width: 450px;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 40px;
        }

        .auth-container h2 {
            font-size: 35px;
            margin-bottom: 30px;
            text-align: center;
            color: #FF4343;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #740000;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-submit:hover {
            background: #941607;
        }

        .error {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="main">
        <header class="header">
            <div class="header-content">
                <a href="index.php" class="logo">SUNNSET</a>
                <div class="nav-links">
                    <a href="catalog.php">Каталог</a>
                    <a href="contacts.php">Контакты</a>
                </div>
            </div>
        </header>

        <div class="auth-page">
            <div class="auth-container">
                <h2><i class="fas fa-lock"></i> Создание нового пароля</h2>
                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success"><?= $success ?></div>
                <?php endif; ?>
                <?php if (!$error && !$success && $user): ?>
                    <form method="POST">
                        <div class="form-group">
                            <input type="password" name="password" placeholder="Новый пароль (мин. 6 символов)" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="confirm_password" placeholder="Подтвердите пароль" required>
                        </div>
                        <button type="submit" class="btn-submit">Сбросить пароль</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <footer class="footer">
            <div class="footer-content">
                <div class="footer-nav-lofo"><a href="index.php" class="logo">SUNNSET</a></div>
                <div class="footer-nav">
                    <a href="index.php">Главная</a>
                    <a href="catalog.php">Каталог</a>
                    <a href="contacts.php">Контакты</a>
                </div>
                <div class="footer-contacts">
                    <div>Email: info@sunnset.com</div>
                    <div>Телефон: +7(916) 999-99-99</div>
                </div>
            </div>
        </footer>
    </div>
</body>

</html>