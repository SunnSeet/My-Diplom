<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));

        // Устанавливаем время истечения (сейчас + 1 час)
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Обновляем токен
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        if ($stmt->execute([$token, $expires, $user['id']])) {
            if (sendResetEmail($email, $token)) {
                $success = "Инструкции по восстановлению пароля отправлены на ваш email.";
            } else {
                $error = "Ошибка отправки письма. Попробуйте позже.";
            }
        } else {
            $error = "Ошибка при сохранении токена.";
        }
    } else {
        $error = "Пользователь с таким email не найден.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля - SUNNSET</title>
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

        .auth-link {
            text-align: center;
            margin-top: 20px;
            color: #C4C4C4;
        }

        .auth-link a {
            color: #FF4343;
            text-decoration: none;
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
                <h2><i class="fas fa-key"></i> Восстановление пароля</h2>
                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success"><?= $success ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Ваш email" required>
                    </div>
                    <button type="submit" class="btn-submit">Отправить инструкции</button>
                </form>
                <div class="auth-link">
                    <a href="auth.php">Вернуться к входу</a>
                </div>
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