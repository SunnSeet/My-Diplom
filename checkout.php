<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Получение корзины пользователя
$stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.image_url 
                       FROM cart c 
                       JOIN products p ON c.product_id = p.id 
                       WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

// Если корзина пуста, перенаправляем в каталог
if (count($cart_items) == 0) {
    $_SESSION['checkout_error'] = 'Ваша корзина пуста. Добавьте товары перед оформлением заказа.';
    header('Location: catalog.php');
    exit();
}

// Получаем данные пользователя
$user = getUserData($_SESSION['user_id']);
$_SESSION['username'] = $user['username'];

// Рассчитываем итоговую сумму
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

$error = '';
$success = '';



// Обработка оформления заказа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $comment = trim($_POST['comment']);
    $payment_method = $_POST['payment_method'];
    $delivery_method = $_POST['delivery_method'];

    // Валидация
    $errors = [];
    if (empty($full_name)) $errors[] = 'Укажите полное имя';
    if (empty($phone)) $errors[] = 'Укажите телефон';
    if (empty($email)) $errors[] = 'Укажите email';
    if (empty($address)) $errors[] = 'Укажите адрес доставки';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Создаем заказ
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address, phone, delivery_method, payment_method, comment) 
                                   VALUES (?, ?, 'pending', ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $total,
                $address,
                $phone,
                $delivery_method,
                $payment_method,
                $comment
            ]);

            $order_id = $pdo->lastInsertId();

            // Добавляем товары в заказ
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($cart_items as $item) {
                $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
            }

            // Очищаем корзину
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);

            $pdo->commit();

            $_SESSION['order_success'] = "Заказ успешно оформлен! Наш менеджер свяжется с вами в ближайшее время.";
            header('Location: profile.php');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при оформлении заказа. Попробуйте позже.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа - SUNNSET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Segoe+UI:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                    <?php if (isLoggedIn()): ?>
                        <a href="profile.php" class="user-icon" title="Личный кабинет">
                            <i class="fas fa-user-circle"></i>
                        </a>
                        <a href="logout.php" class="logout-btn">Выход</a>
                    <?php else: ?>
                        <a href="login.php" class="login-icon" title="Вход/Регистрация">
                            <svg width="35" height="35" viewBox="0 0 35 35" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5.8335 17.5C5.8335 17.8868 5.98714 18.2577 6.26063 18.5312C6.53412 18.8047 6.90506 18.9583 7.29183 18.9583H18.3606L15.0064 22.2979C14.8697 22.4335 14.7612 22.5948 14.6872 22.7725C14.6132 22.9502 14.575 23.1408 14.575 23.3333C14.575 23.5259 14.6132 23.7165 14.6872 23.8942C14.7612 24.0719 14.8697 24.2332 15.0064 24.3688C15.142 24.5054 15.3033 24.6139 15.481 24.688C15.6587 24.762 15.8493 24.8001 16.0418 24.8001C16.2343 24.8001 16.425 24.762 16.6027 24.688C16.7804 24.6139 16.9417 24.5054 17.0772 24.3688L22.9106 18.5354C23.0433 18.3967 23.1474 18.2332 23.2168 18.0542C23.3627 17.6991 23.3627 17.3009 23.2168 16.9458C23.1474 16.7668 23.0433 16.6033 22.9106 16.4646L17.0772 10.6313C16.9413 10.4953 16.7799 10.3874 16.6022 10.3138C16.4245 10.2402 16.2341 10.2024 16.0418 10.2024C15.8495 10.2024 15.6591 10.2402 15.4815 10.3138C15.3038 10.3874 15.1424 10.4953 15.0064 10.6313C14.8704 10.7672 14.7626 10.9286 14.689 11.1063C14.6154 11.284 14.5775 11.4744 14.5775 11.6667C14.5775 11.859 14.6154 12.0494 14.689 12.227C14.7626 12.4047 14.8704 12.5661 15.0064 12.7021L18.3606 16.0417H7.29183C6.90506 16.0417 6.53412 16.1953 6.26063 16.4688C5.98714 16.7423 5.8335 17.1132 5.8335 17.5ZM24.7918 2.91667H10.2085C9.04817 2.91667 7.93538 3.3776 7.1149 4.19808C6.29443 5.01855 5.8335 6.13135 5.8335 7.29167V11.6667C5.8335 12.0534 5.98714 12.4244 6.26063 12.6979C6.53412 12.9714 6.90506 13.125 7.29183 13.125C7.6786 13.125 8.04954 12.9714 8.32303 12.6979C8.59652 12.4244 8.75016 12.0534 8.75016 11.6667V7.29167C8.75016 6.90489 8.90381 6.53396 9.1773 6.26047C9.45079 5.98698 9.82172 5.83333 10.2085 5.83333H24.7918C25.1786 5.83333 25.5495 5.98698 25.823 6.26047C26.0965 6.53396 26.2502 6.90489 26.2502 7.29167V27.7083C26.2502 28.0951 26.0965 28.466 25.823 28.7395C25.5495 29.013 25.1786 29.1667 24.7918 29.1667H10.2085C9.82172 29.1667 9.45079 29.013 9.1773 28.7395C8.90381 28.466 8.75016 28.0951 8.75016 27.7083V23.3333C8.75016 22.9466 8.59652 22.5756 8.32303 22.3021C8.04954 22.0286 7.6786 21.875 7.29183 21.875C6.90506 21.875 6.53412 22.0286 6.26063 22.3021C5.98714 22.5756 5.8335 22.9466 5.8335 23.3333V27.7083C5.8335 28.8687 6.29443 29.9815 7.1149 30.8019C7.93538 31.6224 9.04817 32.0833 10.2085 32.0833H24.7918C25.9522 32.0833 27.0649 31.6224 27.8854 30.8019C28.7059 29.9815 29.1668 28.8687 29.1668 27.7083V7.29167C29.1668 6.13135 28.7059 5.01855 27.8854 4.19808C27.0649 3.3776 25.9522 2.91667 24.7918 2.91667Z" fill="white" />
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="checkout-page">
            <div class="checkout-container">
                <div class="checkout-header">
                    <h1><i class="fas fa-clipboard-list"></i> Оформление заказа</h1>
                    <p>Заполните данные для доставки и подтвердите заказ</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="checkout-grid">
                    <!-- Форма -->
                    <div class="checkout-form">
                        <form method="POST">
                            <div class="form-section">
                                <h3><i class="fas fa-user"></i> Контактная информация</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-user"></i> Полное имя *</label>
                                        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?: '') ?>" required placeholder="Иван Иванов">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-phone"></i> Телефон *</label>
                                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?: '') ?>" required placeholder="+7 (999) 999-99-99">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email *</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required placeholder="example@mail.com">
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-truck"></i> Доставка</h3>
                                <div class="form-group">
                                    <label><i class="fas fa-map-marker-alt"></i> Адрес доставки *</label>
                                    <textarea name="address" rows="3" required placeholder="Город, улица, дом, квартира"><?= htmlspecialchars($user['address'] ?: '') ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-shipping-fast"></i> Способ доставки</label>
                                    <div class="radio-group">
                                        <label class="radio-option">
                                            <input type="radio" name="delivery_method" value="courier" checked> Курьерская доставка
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="delivery_method" value="pickup"> Самовывоз
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="delivery_method" value="post"> Почта России
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-credit-card"></i> Оплата</h3>
                                <div class="form-group">
                                    <label><i class="fas fa-money-bill-wave"></i> Способ оплаты</label>
                                    <div class="radio-group">
                                        <label class="radio-option">
                                            <input type="radio" name="payment_method" value="card" checked> Банковская карта
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="payment_method" value="cash"> Наличные при получении
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="payment_method" value="sberpay"> СБП / СберPay
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-comment"></i> Комментарий к заказу</h3>
                                <div class="form-group">
                                    <textarea name="comment" rows="3" placeholder="Пожелания к заказу, удобное время доставки и т.д."></textarea>
                                </div>
                            </div>

                            <button type="submit" name="place_order" class="btn-place-order">
                                <i class="fas fa-check-circle"></i> Подтвердить заказ
                            </button>
                        </form>
                        <a href="profile.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Вернуться в корзину
                        </a>
                    </div>

                    <!-- Корзина -->
                    <div class="order-summary">
                        <h3><i class="fas fa-shopping-cart"></i> Ваш заказ</h3>
                        <div class="summary-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="summary-item">
                                    <div class="summary-item-img">
                                        <img src="assets/img/<?= htmlspecialchars($item['image_url']) ?>"
                                            alt="<?= htmlspecialchars($item['name']) ?>"
                                            onerror="this.src='https://placehold.co/60x60/333/FF4343?text=Product'">
                                    </div>
                                    <div class="summary-item-info">
                                        <div class="summary-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="summary-item-price"><?= number_format($item['price'], 0, '', ' ') ?> ₽</div>
                                        <div class="summary-item-quantity">x <?= $item['quantity'] ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="summary-total">
                            <div class="summary-row">
                                <span>Товары (<?= count($cart_items) ?> позиций):</span>
                                <span><?= number_format($total, 0, '', ' ') ?> ₽</span>
                            </div>
                            <div class="summary-row">
                                <span>Доставка:</span>
                                <span>Бесплатно</span>
                            </div>
                            <div class="summary-row total">
                                <span>Итого к оплате:</span>
                                <span><?= number_format($total, 0, '', ' ') ?> ₽</span>
                            </div>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <small>Нажимая "Подтвердить заказ", вы соглашаетесь с условиями обработки персональных данных</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="footer">
            <div class="footer-content">
                <div class="footer-nav-lofo">
                    <a href="index.php" class="logo">SUNNSET</a>
                </div>
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

    <script src="script.js"></script>
</body>

</html>