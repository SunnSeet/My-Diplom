<?php
require_once 'config.php';

// Если пользователь уже вошел, перенаправляем
if (isLoggedIn()) {
    header('Location: profile.php');
    exit();
}

// Проверяем, есть ли временный пользователь
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_email'])) {
    header('Location: register.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = trim($_POST['code']);
    $user_id = $_SESSION['temp_user_id'];

    // Проверяем код
    $stmt = $pdo->prepare("SELECT id, email, verification_code, verification_expires FROM users WHERE id = ? AND verification_code = ? AND email_verified = 0");
    $stmt->execute([$user_id, $code]);
    $user = $stmt->fetch();

    if ($user) {
        // Проверяем, не истек ли код
        if (strtotime($user['verification_expires']) > time()) {
            // Код верный - подтверждаем email
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
            $stmt->execute([$user_id]);

            // Автоматически входим в систему
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'] ?? '';
            $_SESSION['role'] = $user['role'] ?? 'user';

            // Очищаем временные данные
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_email']);

            $_SESSION['verify_success'] = "✅ Email успешно подтвержден! Добро пожаловать!";
            header('Location: profile.php');
            exit();
        } else {
            $error = "❌ Срок действия кода истек. Запросите новый код.";
        }
    } else {
        $error = "❌ Неверный код подтверждения. Попробуйте еще раз.";
    }
}

// Отправка нового кода
if (isset($_GET['resend'])) {
    $user_id = $_SESSION['temp_user_id'];
    $new_code = sprintf("%06d", mt_rand(1, 999999));
    $new_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
        $stmt->execute([$new_code, $new_expires, $user_id]);

        sendVerificationCode($user['email'], $new_code);
        $success = "✅ Новый код отправлен на ваш email!";
    }
}

$email = $_SESSION['temp_email'];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение email - SUNNSET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Segoe+UI:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .verify-page {
            min-height: calc(100vh - 300px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 20px;
        }

        .verify-container {
            max-width: 450px;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .verify-container h2 {
            font-size: 35px;
            margin-bottom: 20px;
            color: #FF4343;
        }

        .verify-container p {
            color: #C4C4C4;
            margin-bottom: 30px;
        }

        .code-inputs {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 30px;
        }

        .code-input {
            width: 60px;
            height: 70px;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            transition: all 0.3s;
        }

        .code-input:focus {
            outline: none;
            border-color: #FF4343;
            background: rgba(255, 255, 255, 0.15);
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
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #941607;
            transform: translateY(-2px);
        }

        .resend-link {
            margin-top: 20px;
            color: #C4C4C4;
        }

        .resend-link a {
            color: #FF4343;
            text-decoration: none;
        }

        .error {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #f44336;
        }

        .success {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #4caf50;
        }

        @media (max-width: 550px) {
            .code-input {
                width: 45px;
                height: 55px;
                font-size: 22px;
            }
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
                <div class="auth-icons">
                    <a href="login.php" class="login-icon" title="Вход/Регистрация">
                        <svg width="35" height="35" viewBox="0 0 35 35" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5.8335 17.5C5.8335 17.8868 5.98714 18.2577 6.26063 18.5312C6.53412 18.8047 6.90506 18.9583 7.29183 18.9583H18.3606L15.0064 22.2979C14.8697 22.4335 14.7612 22.5948 14.6872 22.7725C14.6132 22.9502 14.575 23.1408 14.575 23.3333C14.575 23.5259 14.6132 23.7165 14.6872 23.8942C14.7612 24.0719 14.8697 24.2332 15.0064 24.3688C15.142 24.5054 15.3033 24.6139 15.481 24.688C15.6587 24.762 15.8493 24.8001 16.0418 24.8001C16.2343 24.8001 16.425 24.762 16.6027 24.688C16.7804 24.6139 16.9417 24.5054 17.0772 24.3688L22.9106 18.5354C23.0433 18.3967 23.1474 18.2332 23.2168 18.0542C23.3627 17.6991 23.3627 17.3009 23.2168 16.9458C23.1474 16.7668 23.0433 16.6033 22.9106 16.4646L17.0772 10.6313C16.9413 10.4953 16.7799 10.3874 16.6022 10.3138C16.4245 10.2402 16.2341 10.2024 16.0418 10.2024C15.8495 10.2024 15.6591 10.2402 15.4815 10.3138C15.3038 10.3874 15.1424 10.4953 15.0064 10.6313C14.8704 10.7672 14.7626 10.9286 14.689 11.1063C14.6154 11.284 14.5775 11.4744 14.5775 11.6667C14.5775 11.859 14.6154 12.0494 14.689 12.227C14.7626 12.4047 14.8704 12.5661 15.0064 12.7021L18.3606 16.0417H7.29183C6.90506 16.0417 6.53412 16.1953 6.26063 16.4688C5.98714 16.7423 5.8335 17.1132 5.8335 17.5ZM24.7918 2.91667H10.2085C9.04817 2.91667 7.93538 3.3776 7.1149 4.19808C6.29443 5.01855 5.8335 6.13135 5.8335 7.29167V11.6667C5.8335 12.0534 5.98714 12.4244 6.26063 12.6979C6.53412 12.9714 6.90506 13.125 7.29183 13.125C7.6786 13.125 8.04954 12.9714 8.32303 12.6979C8.59652 12.4244 8.75016 12.0534 8.75016 11.6667V7.29167C8.75016 6.90489 8.90381 6.53396 9.1773 6.26047C9.45079 5.98698 9.82172 5.83333 10.2085 5.83333H24.7918C25.1786 5.83333 25.5495 5.98698 25.823 6.26047C26.0965 6.53396 26.2502 6.90489 26.2502 7.29167V27.7083C26.2502 28.0951 26.0965 28.466 25.823 28.7395C25.5495 29.013 25.1786 29.1667 24.7918 29.1667H10.2085C9.82172 29.1667 9.45079 29.013 9.1773 28.7395C8.90381 28.466 8.75016 28.0951 8.75016 27.7083V23.3333C8.75016 22.9466 8.59652 22.5756 8.32303 22.3021C8.04954 22.0286 7.6786 21.875 7.29183 21.875C6.90506 21.875 6.53412 22.0286 6.26063 22.3021C5.98714 22.5756 5.8335 22.9466 5.8335 23.3333V27.7083C5.8335 28.8687 6.29443 29.9815 7.1149 30.8019C7.93538 31.6224 9.04817 32.0833 10.2085 32.0833H24.7918C25.9522 32.0833 27.0649 31.6224 27.8854 30.8019C28.7059 29.9815 29.1668 28.8687 29.1668 27.7083V7.29167C29.1668 6.13135 28.7059 5.01855 27.8854 4.19808C27.0649 3.3776 25.9522 2.91667 24.7918 2.91667Z" fill="white" />
                        </svg>
                    </a>
                </div>
            </div>
        </header>

        <div class="verify-page">
            <div class="verify-container">
                <h2><i class="fas fa-envelope"></i> Подтверждение email</h2>
                <p>Мы отправили 6-значный код на адрес<br><strong><?= htmlspecialchars($email) ?></strong></p>

                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success"><?= $success ?></div>
                <?php endif; ?>

                <form method="POST" id="verifyForm">
                    <div class="code-inputs">
                        <input type="text" maxlength="1" class="code-input" id="code1" autofocus>
                        <input type="text" maxlength="1" class="code-input" id="code2">
                        <input type="text" maxlength="1" class="code-input" id="code3">
                        <input type="text" maxlength="1" class="code-input" id="code4">
                        <input type="text" maxlength="1" class="code-input" id="code5">
                        <input type="text" maxlength="1" class="code-input" id="code6">
                    </div>
                    <input type="hidden" name="code" id="fullCode">
                    <button type="submit" class="btn-submit">Подтвердить</button>
                </form>

                <div class="resend-link">
                    Не пришел код? <a href="?resend=1">Отправить повторно</a>
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

    <script>
        // Автоматическое переключение между полями ввода кода
        const inputs = document.querySelectorAll('.code-input');

        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && index > 0 && e.target.value.length === 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Сборка полного кода перед отправкой
        document.getElementById('verifyForm').addEventListener('submit', (e) => {
            let fullCode = '';
            inputs.forEach(input => {
                fullCode += input.value;
            });
            document.getElementById('fullCode').value = fullCode;

            if (fullCode.length !== 6) {
                e.preventDefault();
                alert('Пожалуйста, введите 6-значный код');
            }
        });
    </script>
</body>

</html>