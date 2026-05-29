<?php
require_once 'config.php';

// Получаем ID товара из URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: catalog.php');
    exit();
}

// Получаем отзывы для этого товара
$stmt = $pdo->prepare("SELECT r.*, u.username 
                       FROM reviews r 
                       JOIN users u ON r.user_id = u.id 
                       WHERE r.product_id = ? 
                       ORDER BY r.created_at DESC");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Получаем информацию о товаре
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       WHERE p.id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: catalog.php');
    exit();
}

// Получаем похожие товары из той же категории
$stmt = $pdo->prepare("SELECT * FROM products 
                       WHERE category_id = ? AND id != ? 
                       LIMIT 3");
$stmt->execute([$product['category_id'], $product_id]);
$similar_products = $stmt->fetchAll();

// Проверяем, находится ли товар в избранном у текущего пользователя
$is_favorite = false;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    $is_favorite = $stmt->fetch();
}

// Обработка добавления в корзину
$cart_success = '';
// Обработка AJAX запросов для корзины (add/remove)
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cart_action'])) {
    $action = $_POST['cart_action'];
    $product_id = (int)$_POST['product_id'];

    if (!isLoggedIn()) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Необходимо войти в систему']);
            exit();
        }
        header('Location: login.php');
        exit();
    }

    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1) 
                               ON DUPLICATE KEY UPDATE quantity = quantity + 1");
        $success = $stmt->execute([$_SESSION['user_id'], $product_id]);
        $message = 'Товар добавлен в корзину!';
    } elseif ($action == 'remove') {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $success = $stmt->execute([$_SESSION['user_id'], $product_id]);
        $message = 'Товар удалён из корзины';
    } else {
        $success = false;
        $message = 'Неизвестное действие';
    }

    if ($is_ajax) {
        echo json_encode(['success' => $success, 'message' => $message]);
        exit();
    }

    if ($success) {
        $_SESSION['cart_success'] = $message;
    }
    header('Location: product.php?id=' . $product_id);
    exit();
}

// Проверяем, есть ли товар в корзине
$is_in_cart = false;
if (isLoggedIn()) {
    $is_in_cart = isInCart($_SESSION['user_id'], $product_id);
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - SUNNSET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Segoe+UI:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Стили для отзывов */
        .reviews-section {
            margin-top: 60px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
        }

        .reviews-section h3 {
            font-size: 28px;
            margin-bottom: 25px;
            color: #FF4343;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .reviews-list {
            margin-bottom: 30px;
        }

        .review-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .review-item:hover {
            border-color: #FF4343;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .review-header strong {
            font-size: 16px;
            color: #FF4343;
        }

        .review-date {
            font-size: 12px;
            color: #C4C4C4;
        }

        .review-stars {
            margin-bottom: 10px;
            font-size: 18px;
            color: #FFD700;
            letter-spacing: 2px;
        }

        .review-qualities {
            font-size: 14px;
            color: #C4C4C4;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            display: inline-block;
        }

        .review-comment {
            font-size: 14px;
            color: #FFFFFF;
            line-height: 1.5;
            margin-top: 10px;
        }

        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #C4C4C4;
        }

        .no-reviews i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .btn-cart-container {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-top: 10px;
            margin-right: 20px;
        }
    </style>
</head>

<body>
    <?php if (isset($_SESSION['cart_success'])): ?>
        <div class="cart-notification" style="position: fixed; top: 100px; right: 20px; background: #4caf50; color: white; padding: 12px 20px; border-radius: 10px; z-index: 9999;">
            <i class="fas fa-check-circle"></i> <?= $_SESSION['cart_success'] ?>
        </div>
        <?php unset($_SESSION['cart_success']); ?>
        <script>
            setTimeout(() => {
                const notification = document.querySelector('.cart-notification');
                if (notification) notification.remove();
            }, 3000);
        </script>
    <?php endif; ?>

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
                        <a href="auth.php" class="login-icon" title="Вход/Регистрация">
                            <svg width="35" height="35" viewBox="0 0 35 35" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5.8335 17.5C5.8335 17.8868 5.98714 18.2577 6.26063 18.5312C6.53412 18.8047 6.90506 18.9583 7.29183 18.9583H18.3606L15.0064 22.2979C14.8697 22.4335 14.7612 22.5948 14.6872 22.7725C14.6132 22.9502 14.575 23.1408 14.575 23.3333C14.575 23.5259 14.6132 23.7165 14.6872 23.8942C14.7612 24.0719 14.8697 24.2332 15.0064 24.3688C15.142 24.5054 15.3033 24.6139 15.481 24.688C15.6587 24.762 15.8493 24.8001 16.0418 24.8001C16.2343 24.8001 16.425 24.762 16.6027 24.688C16.7804 24.6139 16.9417 24.5054 17.0772 24.3688L22.9106 18.5354C23.0433 18.3967 23.1474 18.2332 23.2168 18.0542C23.3627 17.6991 23.3627 17.3009 23.2168 16.9458C23.1474 16.7668 23.0433 16.6033 22.9106 16.4646L17.0772 10.6313C16.9413 10.4953 16.7799 10.3874 16.6022 10.3138C16.4245 10.2402 16.2341 10.2024 16.0418 10.2024C15.8495 10.2024 15.6591 10.2402 15.4815 10.3138C15.3038 10.3874 15.1424 10.4953 15.0064 10.6313C14.8704 10.7672 14.7626 10.9286 14.689 11.1063C14.6154 11.284 14.5775 11.4744 14.5775 11.6667C14.5775 11.859 14.6154 12.0494 14.689 12.227C14.7626 12.4047 14.8704 12.5661 15.0064 12.7021L18.3606 16.0417H7.29183C6.90506 16.0417 6.53412 16.1953 6.26063 16.4688C5.98714 16.7423 5.8335 17.1132 5.8335 17.5ZM24.7918 2.91667H10.2085C9.04817 2.91667 7.93538 3.3776 7.1149 4.19808C6.29443 5.01855 5.8335 6.13135 5.8335 7.29167V11.6667C5.8335 12.0534 5.98714 12.4244 6.26063 12.6979C6.53412 12.9714 6.90506 13.125 7.29183 13.125C7.6786 13.125 8.04954 12.9714 8.32303 12.6979C8.59652 12.4244 8.75016 12.0534 8.75016 11.6667V7.29167C8.75016 6.90489 8.90381 6.53396 9.1773 6.26047C9.45079 5.98698 9.82172 5.83333 10.2085 5.83333H24.7918C25.1786 5.83333 25.5495 5.98698 25.823 6.26047C26.0965 6.53396 26.2502 6.90489 26.2502 7.29167V27.7083C26.2502 28.0951 26.0965 28.466 25.823 28.7395C25.5495 29.013 25.1786 29.1667 24.7918 29.1667H10.2085C9.82172 29.1667 9.45079 29.013 9.1773 28.7395C8.90381 28.466 8.75016 28.0951 8.75016 27.7083V23.3333C8.75016 22.9466 8.59652 22.5756 8.32303 22.3021C8.04954 22.0286 7.6786 21.875 7.29183 21.875C6.90506 21.875 6.53412 22.0286 6.26063 22.3021C5.98714 22.5756 5.8335 22.9466 5.8335 23.3333V27.7083C5.8335 28.8687 6.29443 29.9815 7.1149 30.8019C7.93538 31.6224 9.04817 32.0833 10.2085 32.0833H24.7918C25.9522 32.0833 27.0649 31.6224 27.8854 30.8019C28.7059 29.9815 29.1668 28.8687 29.1668 27.7083V7.29167C29.1668 6.13135 28.7059 5.01855 27.8854 4.19808C27.0649 3.3776 25.9522 2.91667 24.7918 2.91667Z" fill="white" />
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="product-page">
            <div class="product-container">
                <!-- Хлебные крошки -->
                <div class="breadcrumb">
                    <a href="index.php">Главная</a> /
                    <a href="catalog.php">Каталог</a> /
                    <a href="catalog.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a> /
                    <span><?= htmlspecialchars($product['name']) ?></span>
                </div>

                <!-- Основная информация -->
                <div class="product-main">
                    <div class="product-gallery">
                        <div class="product-main-image">
                            <img src="assets/img/<?= htmlspecialchars($product['image_url']) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?>"
                                onerror="this.src='https://placehold.co/600x600/333/FF4343?text=<?= urlencode($product['name']) ?>'">
                        </div>
                    </div>

                    <div class="product-info">
                        <span class="product-category"><?= htmlspecialchars($product['category_name']) ?></span>
                        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                        <div class="product-price"><?= number_format($product['price'], 0, '', ' ') ?> ₽</div>

                        <div class="product-description">
                            <p><?= htmlspecialchars($product['description'] ?: 'Уникальный предмет китайской культуры, созданный с любовью и уважением к традициям.') ?></p>
                        </div>

                        <div class="product-details">
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Регион происхождения</span>
                                <span class="detail-value"><?= htmlspecialchars($product['origin'] ?: 'Китай') ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-gem"></i> Материал</span>
                                <span class="detail-value"><?= htmlspecialchars($product['material'] ?: 'Традиционные материалы') ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-box"></i> Наличие</span>
                                <span class="detail-value" style="color: #4caf50;">В наличии</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-star"></i> Ручная работа</span>
                                <span class="detail-value">✅ Да</span>
                            </div>
                        </div>

                        <div class="product-origin">
                            <h4><i class="fas fa-history"></i> История создания</h4>
                            <p><?= htmlspecialchars($product['long_description'] ?: 'Каждый предмет в нашем магазине создается мастерами, которые бережно хранят традиции китайского ремесла. Технологии изготовления передаются из поколения в поколение на протяжении сотен лет. Мы лично отбираем каждый товар, чтобы вы могли прикоснуться к настоящей культуре Поднебесной.') ?></p>
                        </div>

                        <!-- Кнопки: добавление/удаление из корзины (AJAX) и избранное -->
                        <div class="btn-cart-container">
                            <button class="btn-add-to-cart <?= $is_in_cart ? 'btn-remove' : '' ?>"
                                data-product-id="<?= $product['id'] ?>"
                                data-action="<?= $is_in_cart ? 'remove' : 'add' ?>">
                                <i class="fas <?= $is_in_cart ? 'fa-trash-alt' : 'fa-shopping-cart' ?>"></i>
                                <?= $is_in_cart ? ' Удалить из корзины' : ' Добавить в корзину' ?>
                            </button>
                            <button class="favorite-btn <?= $is_favorite ? 'active' : '' ?>" data-product-id="<?= $product['id'] ?>">
                                <i class="<?= $is_favorite ? 'fas fa-heart' : 'far fa-heart' ?>"></i>
                            </button>
                        </div>

                        <a href="catalog.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Вернуться в каталог
                        </a>
                    </div>
                </div>

                <!-- Блок отзывов -->
                <div class="reviews-section">
                    <h3><i class="fas fa-comments"></i> Отзывы покупателей</h3>

                    <div class="reviews-list" id="reviews-list">
                        <?php if (count($reviews) > 0): ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <strong><i class="fas fa-user"></i> <?= htmlspecialchars($review['username']) ?></strong>
                                        <span class="review-date"><i class="far fa-calendar-alt"></i> <?= date('d.m.Y', strtotime($review['created_at'])) ?></span>
                                    </div>
                                    <div class="review-stars">
                                        <?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?>
                                    </div>
                                    <?php if (!empty($review['qualities'])): ?>
                                        <div class="review-qualities">
                                            <i class="fas fa-thumbs-up"></i> Понравилось: <?= htmlspecialchars($review['qualities']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($review['comment'])): ?>
                                        <div class="review-comment">
                                            <i class="fas fa-quote-left"></i> <?= nl2br(htmlspecialchars($review['comment'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-reviews">
                                <i class="fas fa-comment-slash"></i>
                                <p>Пока нет отзывов. Будьте первым, кто оценит этот товар!</p>
                                <?php if (isLoggedIn()): ?>
                                    <a href="profile.php?tab=reviews" class="btn-add-to-cart" style="display: inline-block; width: auto; padding: 10px 25px; margin-top: 15px;">Оставить отзыв</a>
                                <?php else: ?>
                                    <a href="auth.php" class="btn-add-to-cart" style="display: inline-block; width: auto; padding: 10px 25px; margin-top: 15px;">Войдите, чтобы оставить отзыв</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Похожие товары -->
                <?php if (count($similar_products) > 0): ?>
                    <div class="similar-section">
                        <h2 class="similar-title"><i class="fas fa-heart"></i> Вам также может понравиться</h2>
                        <div class="similar-grid">
                            <?php foreach ($similar_products as $similar): ?>
                                <a href="product.php?id=<?= $similar['id'] ?>" class="similar-card">
                                    <div class="similar-img">
                                        <img src="assets/img/<?= htmlspecialchars($similar['image_url']) ?>"
                                            alt="<?= htmlspecialchars($similar['name']) ?>"
                                            onerror="this.src='https://placehold.co/250x180/333/FF4343?text=<?= urlencode($similar['name']) ?>'">
                                    </div>
                                    <div class="similar-info">
                                        <div class="similar-name"><?= htmlspecialchars($similar['name']) ?></div>
                                        <div class="similar-price"><?= number_format($similar['price'], 0, '', ' ') ?> ₽</div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
    <script>
        // Добавление в избранное на странице товара
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();

                const productId = btn.dataset.productId;
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('favorite_action', 'toggle');

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
                        if (result.action === 'added') {
                            btn.classList.add('active');
                            btn.innerHTML = '<i class="fas fa-heart"></i>';
                        } else {
                            btn.classList.remove('active');
                            btn.innerHTML = '<i class="far fa-heart"></i>';
                        }
                        showNotification(result.message, 'success');
                    } else {
                        showNotification(result.message, 'error');
                        btn.innerHTML = originalHtml;
                    }
                } catch (error) {
                    console.error('Ошибка:', error);
                    showNotification('Необходимо войти в систему', 'error');
                    btn.innerHTML = originalHtml;
                } finally {
                    btn.disabled = false;
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

        // Добавление/удаление из корзины на странице товара (AJAX)
        const cartBtn = document.querySelector('.btn-add-to-cart');
        if (cartBtn) {
            cartBtn.addEventListener('click', async (e) => {
                e.preventDefault();

                const productId = cartBtn.dataset.productId;
                let action = cartBtn.dataset.action;
                const originalHtml = cartBtn.innerHTML;

                cartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Обработка...';
                cartBtn.disabled = true;

                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('cart_action', action);

                try {
                    const response = await fetch('product.php?id=' + productId, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        showNotification(result.message, 'success');

                        // Меняем кнопку
                        if (action === 'add') {
                            // Товар добавлен → меняем на "Удалить"
                            cartBtn.dataset.action = 'remove';
                            cartBtn.classList.add('btn-remove');
                            cartBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Удалить из корзины';
                        } else {
                            // Товар удалён → меняем на "Добавить"
                            cartBtn.dataset.action = 'add';
                            cartBtn.classList.remove('btn-remove');
                            cartBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Добавить в корзину';
                        }
                    } else {
                        showNotification(result.message, 'error');
                        cartBtn.innerHTML = originalHtml;
                    }
                } catch (error) {
                    console.error('Ошибка:', error);
                    showNotification('Ошибка при обработке', 'error');
                    cartBtn.innerHTML = originalHtml;
                } finally {
                    cartBtn.disabled = false;
                }
            });
        }
    </script>
</body>

</html>