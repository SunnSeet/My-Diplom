<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getUserData($_SESSION['user_id']);
$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Обработка AJAX запросов
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_ajax) {
    $response = ['success' => false, 'message' => ''];

    // Добавление в избранное через AJAX
    if (isset($_POST['favorite_action']) && $_POST['favorite_action'] == 'toggle') {
        $product_id = (int)$_POST['product_id'];

        $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $exists = $stmt->fetch();

        if ($exists) {
            $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            $response = ['success' => true, 'action' => 'removed', 'message' => 'Удалено из избранного'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            $response = ['success' => true, 'action' => 'added', 'message' => 'Добавлено в избранное'];
        }
        echo json_encode($response);
        exit();
    }

    // Обновление корзины (add / remove / update)
    if (isset($_POST['cart_action'])) {
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $action = $_POST['cart_action'];

        if ($action == 'add') {
            // Добавление товара в корзину
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1) 
                               ON DUPLICATE KEY UPDATE quantity = quantity + 1");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            $response = ['success' => true, 'message' => 'Товар добавлен в корзину!'];
        } elseif ($action == 'remove') {
            // Удаление товара из корзины
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            $response = ['success' => true, 'message' => 'Товар удалён из корзины'];
        } elseif ($action == 'update') {
            // Обновление количества
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            if ($quantity > 0) {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$quantity, $_SESSION['user_id'], $product_id]);
                $response = ['success' => true, 'message' => 'Количество обновлено'];
            } elseif ($quantity == 0) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$_SESSION['user_id'], $product_id]);
                $response = ['success' => true, 'message' => 'Товар удалён из корзины'];
            } else {
                $response = ['success' => false, 'message' => 'Неверное количество'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Неизвестное действие'];
        }

        // Получаем обновленные данные корзины (для счетчика и итога)
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total_count, SUM(p.price * c.quantity) as total_sum 
                           FROM cart c 
                           JOIN products p ON c.product_id = p.id 
                           WHERE c.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cart_data = $stmt->fetch();
        $response['cart_count'] = $cart_data['total_count'] ?? 0;
        $response['cart_total'] = $cart_data['total_sum'] ?? 0;

        echo json_encode($response);
        exit();
    }

    // Удаление из избранного (старый метод)
    if (isset($_POST['favorite_action']) && $_POST['favorite_action'] == 'remove') {
        $product_id = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        echo json_encode(['success' => true, 'message' => 'Удалено из избранного']);
        exit();
    }

    // Отправка отзыва
    if (isset($_POST['review_action'])) {
        $product_id = (int)$_POST['product_id'];
        $rating = (int)$_POST['rating'];
        $qualities = $_POST['qualities'];
        $comment = trim($_POST['comment']);

        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, qualities, comment) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $product_id, $rating, $qualities, $comment])) {
            echo json_encode(['success' => true, 'message' => 'Отзыв добавлен! Спасибо!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ошибка при добавлении отзыва']);
        }
        exit();
    }
}

// Обновление профиля (никнейм, email, полное имя, телефон, адрес)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $errors = [];

    // Проверка уникальности никнейма
    if ($new_username !== $user['username']) {
        if (!isUsernameUnique($new_username, $_SESSION['user_id'])) {
            $errors[] = 'Никнейм уже занят';
        } elseif (strlen($new_username) < 3) {
            $errors[] = 'Никнейм должен содержать минимум 3 символа';
        }
    }

    // Проверка уникальности email
    if ($new_email !== $user['email']) {
        if (!isEmailUnique($new_email, $_SESSION['user_id'])) {
            $errors[] = 'Email уже зарегистрирован';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        }
    }

    if (empty($errors)) {
        // Обновляем основные данные
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $address, $_SESSION['user_id']]);

        $updated = false;

        // Обновляем никнейм если изменился
        if ($new_username !== $user['username']) {
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$new_username, $_SESSION['user_id']]);
            $_SESSION['username'] = $new_username;
            $updated = true;
        }

        // Обновляем email если изменился (требует подтверждения по ссылке)
        if ($new_email !== $user['email']) {
            // Проверяем, не занят ли уже новый email другим пользователем
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$new_email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = '❌ Этот email уже зарегистрирован другим пользователем';
            } else {
                $verification_token = bin2hex(random_bytes(32));
                // Сохраняем новый email во временное поле new_email
                $stmt = $pdo->prepare("UPDATE users SET new_email = ?, email_verification_token = ? WHERE id = ?");
                $stmt->execute([$new_email, $verification_token, $_SESSION['user_id']]);

                // Отправляем ссылку для подтверждения на НОВЫЙ email
                if (sendNewEmailVerificationLink($new_email, $verification_token)) {
                    $success = '✅ На новый email (<strong>' . htmlspecialchars($new_email) . '</strong>) отправлена ссылка для подтверждения. Перейдите по ссылке, чтобы завершить смену email.';
                } else {
                    $error = '❌ Ошибка при отправке письма подтверждения. Попробуйте позже.';
                    // Откатываем изменения
                    $stmt = $pdo->prepare("UPDATE users SET new_email = NULL, email_verification_token = NULL WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                }
            }
        } else {
            $success = '✅ Профиль успешно обновлен!';
        }

        $user = getUserData($_SESSION['user_id']);
    } else {
        $error = implode('<br>', $errors);
    }
}

// Смена пароля
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $_SESSION['user_id']]);
                $success = '✅ Пароль успешно изменен!';
            } else {
                $error = '❌ Новый пароль должен содержать минимум 6 символов';
            }
        } else {
            $error = '❌ Новые пароли не совпадают';
        }
    } else {
        $error = '❌ Неверный текущий пароль';
    }
}

// Получение избранного
$stmt = $pdo->prepare("SELECT f.*, p.name, p.price, p.image_url, p.material FROM favorites f JOIN products p ON f.product_id = p.id WHERE f.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$favorites = $stmt->fetchAll();

// Получение корзины
$stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.image_url, p.material FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();
$cart_total = 0;
foreach ($cart_items as $item) {
    $cart_total += $item['price'] * $item['quantity'];
}
// Получение общей суммы корзины
if (isset($_POST['get_cart_total'])) {
    $stmt = $pdo->prepare("SELECT SUM(p.price * c.quantity) as total_sum 
                           FROM cart c 
                           JOIN products p ON c.product_id = p.id 
                           WHERE c.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetch()['total_sum'] ?? 0;
    echo json_encode(['cart_total' => $total]);
    exit();
}

// Отправка нового кода подтверждения
if (isset($_POST['resend_verification'])) {
    header('Content-Type: application/json');

    $user_id = $_SESSION['user_id'];
    $user = getUserData($user_id);

    if ($user['email_verified']) {
        echo json_encode(['success' => false, 'message' => 'Email уже подтверждён']);
        exit();
    }

    $new_code = sprintf("%06d", mt_rand(1, 999999));
    $new_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $stmt->execute([$new_code, $new_expires, $user_id]);

    if (sendVerificationCode($user['email'], $new_code)) {
        echo json_encode(['success' => true, 'message' => 'Новый код отправлен на почту']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка отправки письма']);
    }
    exit();
}

// Подтверждение email через код
if (isset($_POST['verify_email_code'])) {
    header('Content-Type: application/json');

    $code = trim($_POST['code']);
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT id, verification_code, verification_expires FROM users WHERE id = ? AND verification_code = ?");
    $stmt->execute([$user_id, $code]);
    $user = $stmt->fetch();

    if ($user) {
        if (strtotime($user['verification_expires']) > time()) {
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true, 'message' => 'Email успешно подтверждён!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Срок действия кода истёк. Запросите новый.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Неверный код подтверждения']);
    }
    exit();
}


// Получение заказов
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
foreach ($orders as $key => $order) {
    $stmtItems = $pdo->prepare("SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmtItems->execute([$order['id']]);
    $orders[$key]['items'] = $stmtItems->fetchAll();
}

// Получение товаров для оценки (со статусом "Выдан" или "completed")
$stmt = $pdo->prepare("SELECT DISTINCT oi.product_id, p.name, p.image_url, p.price 
                       FROM order_items oi 
                       JOIN orders o ON oi.order_id = o.id 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE o.user_id = ? AND o.status = 'completed'
                       ORDER BY o.order_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$completed_products = $stmt->fetchAll();

// Получение уже оставленных отзывов
$reviewed_products = [];
if (!empty($completed_products)) {
    $ids = array_column($completed_products, 'product_id');
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT product_id FROM reviews WHERE user_id = ? AND product_id IN ($placeholders)");
    $stmt->execute(array_merge([$_SESSION['user_id']], $ids));
    while ($row = $stmt->fetch()) {
        $reviewed_products[] = $row['product_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - SUNNSET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Segoe+UI:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <link rel="stylesheet" href="profile.css?v=<?= filemtime('profile.css') ?>">
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
                    <a href="profile.php" class="user-icon"><i class="fas fa-user-circle"></i></a>
                    <a href="logout.php" class="logout-btn">Выход</a>
                </div>
            </div>
        </header>

        <!-- <?php if (isAdmin()): ?>
            <div style="position: fixed; bottom: 20px; left: 20px; z-index: 1000;">
                <a href="admin_panel.php" style="background: #740000; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none;">
                    <i class="fas fa-crown"></i> Админ панель
                </a>
            </div>
        <?php endif; ?> -->

        <div class="profile-layout">
            <!-- Левая боковая панель -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <div class="avatar-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="profile-username">@<?= htmlspecialchars($user['username']) ?></div>
                </div>

                <div class="profile-nav">
                    <a href="?tab=profile" class="nav-tab <?= $active_tab == 'profile' ? 'active' : '' ?>" data-tab="profile">
                        <i class="fas fa-user"></i> Профиль
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="admin_panel.php" class="nav-tab" target="_blank">
                            <i class="fas fa-crown"></i> Админ-панель
                        </a>
                    <?php endif; ?>
                    <a href="?tab=favorites" class="nav-tab <?= $active_tab == 'favorites' ? 'active' : '' ?>" data-tab="favorites">
                        <i class="fas fa-heart"></i> Избранное
                    </a>
                    <a href="?tab=cart" class="nav-tab <?= $active_tab == 'cart' ? 'active' : '' ?>" data-tab="cart">
                        <i class="fas fa-shopping-cart"></i> Корзина
                    </a>
                    <a href="?tab=orders" class="nav-tab <?= $active_tab == 'orders' ? 'active' : '' ?>" data-tab="orders">
                        <i class="fas fa-box"></i> Мои заказы
                    </a>
                    <a href="?tab=reviews" class="nav-tab <?= $active_tab == 'reviews' ? 'active' : '' ?>" data-tab="reviews">
                        <i class="fas fa-star"></i> Оцените товары
                    </a>
                </div>
            </div>

            <!-- Правая основная область -->
            <div class="profile-content">
                <!-- Вкладка: Профиль -->
                <div id="tab-profile" class="content-section <?= $active_tab == 'profile' ? 'active' : '' ?>">
                    <div class="section-title"><i class="fas fa-user-edit"></i> Редактирование профиля</div>

                    <?php if ($success): ?><div class="alert-success"><?= $success ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert-error"><?= $error ?></div><?php endif; ?>
                    <?php if (!$user['email_verified'] && $user['email']): ?>
                        <div class="alert-error" id="emailVerificationAlert" style="color: aliceblue;">
                            <i style="color: #860000;" class="fas fa-exclamation-triangle"></i>
                            <strong style="color: #c70000;">Внимание!</strong> Ваш email <strong style="color: #FF4343;"><?= htmlspecialchars($user['email']) ?></strong> не подтверждён.
                            <br><small style="color: #8d8d8d;">Без подтверждения вы не сможете восстанавливать пароль и получать важные уведомления.</small>
                            <div style="margin-top: 12px;">
                                <button onclick="openVerifyCodeModal()" class="btn-save" style="padding: 8px 16px; font-size: 14px; margin-right: 10px;">
                                    <i class="fas fa-key"></i> Ввести код подтверждения
                                </button>
                                <button onclick="resendVerificationCode()" id="resendCodeBtn" class="btn-save" style="padding: 8px 16px; font-size: 14px; background: #444;">
                                    <i class="fas fa-envelope"></i> Отправить код повторно
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-user-tag"></i> Никнейм *</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                            <small style="color: #888;">Уникальный логин для входа (минимум 3 символа)</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            <small style="color: #888;">При смене email потребуется подтверждение</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Полное имя</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" placeholder="Как к вам обращаться">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Телефон</label>
                            <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="+7 (999) 999-99-99">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Адрес доставки</label>
                            <textarea name="address" rows="3" placeholder="Ваш полный адрес для доставки"><?= htmlspecialchars($user['address']) ?></textarea>
                        </div>
                        <button type="submit" name="update_profile" class="btn-save"><i class="fas fa-save"></i> Сохранить изменения</button>
                    </form>

                    <div class="section-title" style="margin-top: 40px;"><i class="fas fa-key"></i> Смена пароля</div>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Текущий пароль</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Новый пароль</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Подтверждение пароля</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn-save"><i class="fas fa-sync-alt"></i> Сменить пароль</button>
                    </form>
                </div>
                <!-- Вкладка: Админ-панель -->
                <div id="tab-admin" class="content-section <?= $active_tab == 'admin' ? 'active' : '' ?>">
                    <div class="section-title"><i class="fas fa-crown"></i> Админ-панель</div>
                    <iframe src="admin_panel.php" style="width: 100%; min-height: 600px; border: none; border-radius: 15px; background: rgba(0,0,0,0.3);"></iframe>
                </div>
                <!-- Вкладка: Избранное -->
                <div id="tab-favorites" class="content-section <?= $active_tab == 'favorites' ? 'active' : '' ?>">
                    <div class="section-title"><i class="fas fa-heart"></i> Избранные товары</div>

                    <?php if (count($favorites) > 0): ?>
                        <div class="products-grid-small">
                            <?php foreach ($favorites as $fav): ?>
                                <div class="product-card-small" id="fav-<?= $fav['product_id'] ?>">
                                    <div class="product-img-small" style="background-image: url('assets/img/<?= htmlspecialchars($fav['image_url']) ?>');"></div>
                                    <div class="product-info-small">
                                        <div class="product-name-small"><?= htmlspecialchars($fav['name']) ?></div>
                                        <div class="product-price-small"><?= number_format($fav['price'], 0, '', ' ') ?> ₽</div>
                                        <div class="product-material-small"><i class="fas fa-gem"></i> <?= htmlspecialchars($fav['material'] ?: 'Традиционные материалы') ?></div>
                                        <div>
                                            <button class="btn-cart-small toggle-cart-from-fav <?= isInCart($_SESSION['user_id'], $fav['product_id']) ? 'in-cart' : '' ?>"
                                                data-product-id="<?= $fav['product_id'] ?>">
                                                <i class="fas <?= isInCart($_SESSION['user_id'], $fav['product_id']) ? 'fa-trash-alt' : 'fa-cart-plus' ?>"></i>
                                                <?= isInCart($_SESSION['user_id'], $fav['product_id']) ? ' Удалить из корзины' : ' В корзину' ?>
                                            </button>
                                            <button class="btn-remove-fav remove-favorite" data-product-id="<?= $fav['product_id'] ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="far fa-heart"></i>
                            <p>У вас пока нет избранных товаров</p>
                            <a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Вкладка: Корзина -->
                <div id="tab-cart" class="content-section <?= $active_tab == 'cart' ? 'active' : '' ?>">
                    <div class="section-title"><i class="fas fa-shopping-cart"></i> Моя корзина</div>

                    <div id="cart-container">
                        <?php if (count($cart_items) > 0): ?>
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item" id="cart-item-<?= $item['product_id'] ?>">
                                    <div class="cart-item-img">
                                        <img src="assets/img/<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    </div>
                                    <div class="cart-item-info">
                                        <div class="cart-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="cart-item-price"><?= number_format($item['price'], 0, '', ' ') ?> ₽</div>
                                    </div>
                                    <div class="cart-item-actions">
                                        <div class="cart-quantity-control">
                                            <button class="cart-qty-btn cart-qty-minus" data-product-id="<?= $item['product_id'] ?>" data-current-qty="<?= $item['quantity'] ?>">−</button>
                                            <span class="cart-quantity-display" id="qty-display-<?= $item['product_id'] ?>"><?= $item['quantity'] ?></span>
                                            <button class="cart-qty-btn cart-qty-plus" data-product-id="<?= $item['product_id'] ?>" data-current-qty="<?= $item['quantity'] ?>">+</button>
                                        </div>
                                        <button class="cart-remove-item" data-product-id="<?= $item['product_id'] ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="cart-total">
                                Итого: <span id="cart-total-amount"><?= number_format($cart_total, 0, '', ' ') ?></span> ₽
                            </div>
                            <a href="checkout.php" class="btn-save" style="display: block; text-align: center; margin-top: 20px;">
                                <i class="fas fa-credit-card"></i> Оформить заказ
                            </a>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-basket"></i>
                                <p>Ваша корзина пуста</p>
                                <a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Вкладка: Мои заказы -->
                <div id="tab-orders" class="content-section <?= $active_tab == 'orders' ? 'active' : '' ?>">
                    <div class="section-title"><i class="fas fa-box"></i> Мои заказы</div>

                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-item">
                                <div class="order-header">
                                    <div class="order-number">Заказ #<?= $order['id'] ?></div>
                                    <div class="order-date"><?= date('d.m.Y', strtotime($order['order_date'])) ?></div>
                                    <div class="order-status status-<?= $order['status'] ?>">
                                        <?php $statuses = ['pending' => '🕐 Ожидание подтверждения', 'processing' => '🔄 Сбор заказа', 'ready' => '✅ Готов к выдаче', 'completed' => '📦 Выдан', 'cancelled' => '❌ Отменен'];
                                        echo $statuses[$order['status']] ?? $order['status']; ?>
                                    </div>
                                </div>
                                <div class="order-items-list">
                                    <?php foreach ($order['items'] as $item): ?>
                                        <div class="order-item-product">
                                            <div class="order-item-product-img">
                                                <img src="assets/img/<?= htmlspecialchars($item['image_url']) ?>" alt="">
                                            </div>
                                            <div class="order-item-product-name"><?= htmlspecialchars($item['name']) ?> (x<?= $item['quantity'] ?>)</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="order-total">
                                    Итого: <?= number_format($order['total_amount'], 0, '', ' ') ?> ₽
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <p>У вас пока нет заказов</p>
                            <a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Вкладка: Оценка товаров -->
                <div id="tab-reviews" class="content-section <?= $active_tab == 'reviews' ? 'active' : '' ?>">
                    <div class="section-title"><i class="fas fa-star"></i> Оцените полученные товары</div>

                    <?php
                    $has_unreviewed = false;
                    foreach ($completed_products as $product):
                        if (!in_array($product['product_id'], $reviewed_products)):
                            $has_unreviewed = true;
                    ?>
                            <div class="review-product" id="review-product-<?= $product['product_id'] ?>">
                                <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">
                                    <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden;">
                                        <img src="assets/img/<?= htmlspecialchars($product['image_url']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div>
                                        <h4><?= htmlspecialchars($product['name']) ?></h4>
                                        <div class="product-price-small"><?= number_format($product['price'], 0, '', ' ') ?> ₽</div>
                                    </div>
                                </div>

                                <div class="star-rating" data-product-id="<?= $product['product_id'] ?>">
                                    <span class="star" data-value="1">☆</span>
                                    <span class="star" data-value="2">☆</span>
                                    <span class="star" data-value="3">☆</span>
                                    <span class="star" data-value="4">☆</span>
                                    <span class="star" data-value="5">☆</span>
                                </div>
                                <input type="hidden" name="rating" value="0">

                                <div class="review-qualities">
                                    <select name="qualities">
                                        <option value="">Выберите, что понравилось</option>
                                        <option value="Качество">Качество</option>
                                        <option value="Дизайн">Дизайн</option>
                                        <option value="Цена">Цена</option>
                                        <option value="Доставка">Доставка</option>
                                        <option value="Упаковка">Упаковка</option>
                                    </select>
                                </div>
                                <div class="review-qualities">
                                    <textarea name="comment" rows="3" placeholder="Ваш комментарий"></textarea>
                                </div>
                                <button class="submit-review" data-product-id="<?= $product['product_id'] ?>">Оставить отзыв</button>
                            </div>
                    <?php endif;
                    endforeach; ?>

                    <?php if (!$has_unreviewed): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Все полученные товары уже оценены! Спасибо за ваши отзывы.</p>
                            <a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a>
                        </div>
                    <?php endif; ?>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#phone').mask('+7 (000) 000-00-00');
        });

        // Переключение вкладок без перезагрузки
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabId = tab.dataset.tab;
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);

                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
                document.getElementById(`tab-${tabId}`).classList.add('active');
            });
        });

        // Добавление в избранное (исправлено)
        // Добавление в избранное (исправленная версия)
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            // Удаляем старые обработчики, если есть
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            newBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const productId = newBtn.dataset.productId;
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('favorite_action', 'toggle');

                // Визуальный эффект
                const originalHtml = newBtn.innerHTML;
                newBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                newBtn.disabled = true;

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        if (result.action === 'added') {
                            newBtn.classList.add('active');
                            newBtn.innerHTML = '<i class="fas fa-heart"></i>';
                        } else {
                            newBtn.classList.remove('active');
                            newBtn.innerHTML = '<i class="far fa-heart"></i>';
                        }
                        showNotification(result.message, 'success');
                    } else {
                        showNotification(result.message, 'error');
                        newBtn.innerHTML = originalHtml;
                    }
                } catch (error) {
                    console.error('Ошибка:', error);
                    showNotification('Ошибка при добавлении в избранное', 'error');
                    newBtn.innerHTML = originalHtml;
                } finally {
                    newBtn.disabled = false;
                }
            });
        });

        // AJAX для обновления корзины
        document.querySelectorAll('.cart-update-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const productId = form.dataset.productId;
                const quantity = document.getElementById(`qty-${productId}`).value;
                const updateBtn = form.querySelector('.cart-update-btn');
                const originalHtml = updateBtn.innerHTML;
                updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                updateBtn.disabled = true;

                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('cart_action', 'update');
                formData.append('quantity', quantity);

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        const totalAmount = document.getElementById('cart-total-amount');
                        if (totalAmount && result.cart_total !== undefined) {
                            totalAmount.innerHTML = formatPrice(result.cart_total);
                        }
                        if (quantity == 0) {
                            const cartItem = document.getElementById(`cart-item-${productId}`);
                            if (cartItem) cartItem.remove();
                            if (document.querySelectorAll('.cart-item').length === 0) {
                                document.getElementById('cart-container').innerHTML = '<div class="empty-state"><i class="fas fa-shopping-basket"></i><p>Ваша корзина пуста</p><a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a></div>';
                            }
                        }
                        showNotification(result.message, 'success');
                    }
                } catch (error) {
                    showNotification('Ошибка', 'error');
                } finally {
                    updateBtn.innerHTML = originalHtml;
                    updateBtn.disabled = false;
                }
            });
        });

        document.querySelectorAll('.cart-remove-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const productId = form.dataset.productId;
                const removeBtn = form.querySelector('.cart-remove-btn');
                const originalHtml = removeBtn.innerHTML;
                removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                removeBtn.disabled = true;

                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('cart_action', 'remove');

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        const cartItem = document.getElementById(`cart-item-${productId}`);
                        if (cartItem) cartItem.remove();
                        const totalAmount = document.getElementById('cart-total-amount');
                        if (totalAmount && result.cart_total !== undefined) {
                            totalAmount.innerHTML = formatPrice(result.cart_total);
                        }
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            document.getElementById('cart-container').innerHTML = '<div class="empty-state"><i class="fas fa-shopping-basket"></i><p>Ваша корзина пуста</p><a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a></div>';
                        }
                        showNotification(result.message, 'success');
                    }
                } catch (error) {
                    showNotification('Ошибка', 'error');
                } finally {
                    removeBtn.innerHTML = originalHtml;
                    removeBtn.disabled = false;
                }
            });
        });

        // Добавление в корзину из избранного
        document.querySelectorAll('.add-to-cart').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const productId = btn.dataset.productId;
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('cart_action', 'add');

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    showNotification(result.message, result.success ? 'success' : 'error');
                    if (result.success) location.reload();
                } catch (error) {
                    showNotification('Ошибка', 'error');
                }
            });
        });

        // Удаление из избранного
        document.querySelectorAll('.remove-favorite').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const productId = btn.dataset.productId;
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('favorite_action', 'remove');

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    showNotification(result.message, 'success');
                    document.getElementById(`fav-${productId}`).remove();
                    if (document.querySelectorAll('#tab-favorites .product-card-small').length === 0) {
                        document.getElementById('tab-favorites').innerHTML = '<div class="section-title"><i class="fas fa-heart"></i> Избранные товары</div><div class="empty-state"><i class="far fa-heart"></i><p>У вас пока нет избранных товаров</p><a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a></div>';
                    }
                } catch (error) {
                    showNotification('Ошибка', 'error');
                }
            });
        });

        // Звездный рейтинг
        document.querySelectorAll('.star-rating').forEach(container => {
            const stars = container.querySelectorAll('.star');
            const ratingInput = container.closest('.review-product').querySelector('input[name="rating"]');
            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.dataset.value);
                    ratingInput.value = value;
                    stars.forEach(s => {
                        if (parseInt(s.dataset.value) <= value) {
                            s.classList.add('active');
                            s.textContent = '★';
                        } else {
                            s.classList.remove('active');
                            s.textContent = '☆';
                        }
                    });
                });
            });
        });

        // Отправка отзыва
        document.querySelectorAll('.submit-review').forEach(btn => {
            btn.addEventListener('click', async () => {
                const productId = btn.dataset.productId;
                const container = document.getElementById(`review-product-${productId}`);
                const rating = container.querySelector('input[name="rating"]').value;
                const qualities = container.querySelector('select[name="qualities"]').value;
                const comment = container.querySelector('textarea[name="comment"]').value;

                if (!rating) {
                    showNotification('Пожалуйста, поставьте оценку', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('review_action', 'submit');
                formData.append('rating', rating);
                formData.append('qualities', qualities);
                formData.append('comment', comment);

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    showNotification(result.message, result.success ? 'success' : 'error');
                    if (result.success) container.remove();
                } catch (error) {
                    showNotification('Ошибка при отправке отзыва', 'error');
                }
            });
        });

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = 'cart-notification';
            notification.style.cssText = `position: fixed; top: 100px; right: 20px; background: ${type === 'success' ? '#4caf50' : '#f44336'}; color: white; padding: 12px 20px; border-radius: 10px; z-index: 9999; animation: slideIn 0.3s ease;`;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('ru-RU').format(price);
        }

        // Переключение корзины из вкладки "Избранное"
        document.querySelectorAll('.toggle-cart-from-fav').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();

                const productId = btn.dataset.productId;
                const isInCart = btn.classList.contains('in-cart');
                const action = isInCart ? 'remove' : 'add';

                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('cart_action', action);

                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        showNotification(result.message, 'success');

                        if (action === 'add') {
                            // Товар добавлен в корзину
                            btn.classList.add('in-cart');
                            btn.innerHTML = '<i class="fas fa-trash-alt"></i> Удалить из корзины';
                        } else {
                            // Товар удалён из корзины
                            btn.classList.remove('in-cart');
                            btn.innerHTML = '<i class="fas fa-cart-plus"></i> В корзину';
                        }
                    } else {
                        showNotification(result.message, 'error');
                        btn.innerHTML = originalHtml;
                    }
                } catch (error) {
                    console.error('Ошибка:', error);
                    showNotification('Ошибка при обработке', 'error');
                    btn.innerHTML = originalHtml;
                } finally {
                    btn.disabled = false;
                }
            });
        });

        // Функция обновления отображения корзины
        async function updateCartUI(productId, newQuantity) {
            const displaySpan = document.getElementById(`qty-display-${productId}`);
            const minusBtn = document.querySelector(`.cart-qty-minus[data-product-id="${productId}"]`);
            const plusBtn = document.querySelector(`.cart-qty-plus[data-product-id="${productId}"]`);

            if (displaySpan) displaySpan.textContent = newQuantity;
            if (minusBtn) minusBtn.dataset.currentQty = newQuantity;
            if (plusBtn) plusBtn.dataset.currentQty = newQuantity;

            // Обновляем итоговую сумму
            const totalSpan = document.getElementById('cart-total-amount');
            if (totalSpan) {
                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            'get_cart_total': '1'
                        })
                    });
                    const data = await response.json();
                    if (data.cart_total !== undefined) {
                        totalSpan.textContent = formatPrice(data.cart_total);
                    }
                } catch (e) {}
            }
        }

        // Обработчик для кнопки "+"
        document.querySelectorAll('.cart-qty-plus').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const productId = btn.dataset.productId;
                let currentQty = parseInt(btn.dataset.currentQty);
                const newQty = currentQty + 1;

                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('cart_action', 'update');
                formData.append('quantity', newQty);

                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        await updateCartUI(productId, newQty);
                        showNotification(result.message, 'success');
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    showNotification('Ошибка', 'error');
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            });
        });

        // Обработчик для кнопки "-"
        document.querySelectorAll('.cart-qty-minus').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const productId = btn.dataset.productId;
                let currentQty = parseInt(btn.dataset.currentQty);
                const newQty = currentQty - 1;

                if (newQty <= 0) {
                    // Если количество становится 0 или меньше — удаляем товар из корзины
                    const formData = new FormData();
                    formData.append('product_id', productId);
                    formData.append('cart_action', 'remove');

                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    btn.disabled = true;

                    try {
                        const response = await fetch('profile.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            const cartItem = document.getElementById(`cart-item-${productId}`);
                            if (cartItem) cartItem.remove();
                            const totalSpan = document.getElementById('cart-total-amount');
                            if (totalSpan && result.cart_total !== undefined) {
                                totalSpan.textContent = formatPrice(result.cart_total);
                            }
                            showNotification(result.message, 'success');
                            // Проверяем, пуста ли корзина
                            if (document.querySelectorAll('.cart-item').length === 0) {
                                document.getElementById('cart-container').innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-shopping-basket"></i>
                                <p>Ваша корзина пуста</p>
                                <a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a>
                            </div>
                        `;
                            }
                        } else {
                            showNotification(result.message, 'error');
                        }
                    } catch (error) {
                        showNotification('Ошибка', 'error');
                    } finally {
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }
                    return;
                }

                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('cart_action', 'update');
                formData.append('quantity', newQty);

                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        await updateCartUI(productId, newQty);
                        showNotification(result.message, 'success');
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    showNotification('Ошибка', 'error');
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            });
        });

        // Кнопка удаления товара (корзина)
        document.querySelectorAll('.cart-remove-item').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const productId = btn.dataset.productId;
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('cart_action', 'remove');

                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        const cartItem = document.getElementById(`cart-item-${productId}`);
                        if (cartItem) cartItem.remove();
                        const totalSpan = document.getElementById('cart-total-amount');
                        if (totalSpan && result.cart_total !== undefined) {
                            totalSpan.textContent = formatPrice(result.cart_total);
                        }
                        showNotification(result.message, 'success');
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            document.getElementById('cart-container').innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-shopping-basket"></i>
                            <p>Ваша корзина пуста</p>
                            <a href="catalog.php" class="btn-save" style="display: inline-block; margin-top: 15px;">Перейти в каталог</a>
                        </div>
                    `;
                        }
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    showNotification('Ошибка', 'error');
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            });
        });
        <?php if (isset($_SESSION['order_success'])): ?>
            showNotification('<?= $_SESSION['order_success'] ?>', 'success');
            <?php unset($_SESSION['order_success']); ?>
        <?php endif; ?>
    </script>
    <!-- Модальное окно для подтверждения email -->
    <div id="verifyCodeModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 450px;">
            <span class="close-modal" onclick="closeVerifyCodeModal()">&times;</span>
            <h3><i class="fas fa-envelope"></i> Подтверждение email</h3>
            <p style="color: #C4C4C4; margin-bottom: 20px;">
                Введите 6-значный код, отправленный на ваш email:<br>
                <strong id="verifyEmailDisplay"></strong>
            </p>

            <div style="display: flex; gap: 12px; justify-content: center; margin-bottom: 25px;">
                <input type="text" maxlength="1" class="verify-code-input" id="vcode1" style="width: 50px; height: 60px; text-align: center; font-size: 24px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); border-radius: 10px; color: white;">
                <input type="text" maxlength="1" class="verify-code-input" id="vcode2" style="width: 50px; height: 60px; text-align: center; font-size: 24px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); border-radius: 10px; color: white;">
                <input type="text" maxlength="1" class="verify-code-input" id="vcode3" style="width: 50px; height: 60px; text-align: center; font-size: 24px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); border-radius: 10px; color: white;">
                <input type="text" maxlength="1" class="verify-code-input" id="vcode4" style="width: 50px; height: 60px; text-align: center; font-size: 24px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); border-radius: 10px; color: white;">
                <input type="text" maxlength="1" class="verify-code-input" id="vcode5" style="width: 50px; height: 60px; text-align: center; font-size: 24px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); border-radius: 10px; color: white;">
                <input type="text" maxlength="1" class="verify-code-input" id="vcode6" style="width: 50px; height: 60px; text-align: center; font-size: 24px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); border-radius: 10px; color: white;">
            </div>

            <button onclick="submitVerificationCode()" class="btn-save" style="width: 100%; padding: 12px;">
                <i class="fas fa-check"></i> Подтвердить
            </button>

            <div style="text-align: center; margin-top: 15px;">
                <a href="#" onclick="resendVerificationCode(); return false;" style="color: #FF4343;">
                    <i class="fas fa-redo"></i> Отправить код повторно
                </a>
            </div>
        </div>
    </div>

    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #1a1a1a;
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .close-modal {
            float: right;
            font-size: 28px;
            cursor: pointer;
            color: #C4C4C4;
        }

        .close-modal:hover {
            color: #FF4343;
        }

        .verify-code-input:focus {
            outline: none;
            border-color: #FF4343 !important;
        }
    </style>

    <script>
        // Функции для работы с модальным окном подтверждения email
        function openVerifyCodeModal() {
            const userEmail = '<?= htmlspecialchars($user['email']) ?>';
            document.getElementById('verifyEmailDisplay').textContent = userEmail;
            document.getElementById('verifyCodeModal').style.display = 'flex';
            // Очищаем поля
            for (let i = 1; i <= 6; i++) {
                document.getElementById(`vcode${i}`).value = '';
            }
            document.getElementById('vcode1').focus();
        }

        function closeVerifyCodeModal() {
            document.getElementById('verifyCodeModal').style.display = 'none';
        }

        // Автоматическое переключение между полями ввода кода
        const verifyInputs = document.querySelectorAll('.verify-code-input');
        verifyInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < verifyInputs.length - 1) {
                    verifyInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && index > 0 && e.target.value.length === 0) {
                    verifyInputs[index - 1].focus();
                }
            });
        });

        async function submitVerificationCode() {
            let fullCode = '';
            for (let i = 1; i <= 6; i++) {
                fullCode += document.getElementById(`vcode${i}`).value;
            }

            if (fullCode.length !== 6) {
                showNotification('Введите 6-значный код', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('verify_email_code', '1');
            formData.append('code', fullCode);

            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, 'success');
                    closeVerifyCodeModal();
                    // Скрываем блок предупреждения
                    const alertBlock = document.getElementById('emailVerificationAlert');
                    if (alertBlock) {
                        alertBlock.style.display = 'none';
                    }
                    // Обновляем страницу через 1.5 секунды
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('Ошибка при подтверждении', 'error');
            }
        }

        async function resendVerificationCode() {
            const btn = document.getElementById('resendCodeBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('resend_verification', '1');

            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, 'success');
                    // Если модальное окно открыто, не закрываем его
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('Ошибка при отправке', 'error');
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }

        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            const modal = document.getElementById('verifyCodeModal');
            if (event.target === modal) {
                closeVerifyCodeModal();
            }
        }
    </script>
</body>

</html>