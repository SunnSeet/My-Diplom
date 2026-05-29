<?php
require_once 'config.php';

// Получаем популярные товары (первые 3)
$stmt = $pdo->query("SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     ORDER BY p.id DESC LIMIT 3");
$products = $stmt->fetchAll();

// Добавляем флаг is_in_cart для каждого товара (если пользователь авторизован)
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
foreach ($products as &$product) {
    $product['is_in_cart'] = ($user_id && isInCart($user_id, $product['id']));
    $product['is_favorite'] = ($user_id && isInFavorites($user_id, $product['id']));
}
unset($product);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Sunnset | Китайская культура и сувениры</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Segoe+UI:wght@400;700&display=swap" rel="stylesheet">
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> -->

    <!-- Критически важная предзагрузка -->
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </noscript>

    <!-- Асинхронная загрузка остальных стилей -->
    <link rel="preload" as="style" href="style.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
    </noscript>
</head>

<body>
    <div class="main">
        <!-- Header (шапка) -->
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
                            <i class="fas fa-user-circle" style="font-size: 35px; color: white;"></i>
                        </a>
                        <a href="logout.php" class="logout-btn">Выйти</a>
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

        <!-- Hero секция -->
        <section class="hero">
            <div class="hero-bg"></div>
            <div class="hero-content">
                <h1 class="hero-title">Мир китайской культуры</h1>
                <p class="hero-subtitle">Уникальные сувениры, традиционные изделия и эстетика Востока в каждом товаре</p>
                <a href="catalog.php" class="btn-primary">Перейти в каталог</a>
            </div>
        </section>

        <!-- О нас -->
        <section>
            <div class="container">
                <div class="About">
                    <div class="textwithimg">
                        <h1>О нашем магазине</h1>
                        <img src="./assets/img/our.png" alt="">
                    </div>
                    <div class="abouttext">
                        <p>SunnSet — это интернет-магазин, посвящённый китайской культуре. Мы предлагаем уникальные
                            сувениры, вдохновлённые традициями Востока: от декоративных элементов до практичных
                            изделий.<br>
                            Наша миссия — передать атмосферу Китая через детали, эстетику и качество.</p>
                        <div class="quality">
                            <div class="market">
                                <p>10 лет</p>
                                <p>на рынке</p>
                            </div>
                            <div class="order">
                                <p>500 +</p>
                                <p>заказов</p>
                            </div>
                            <div class="client" style="display: flex;align-items: flex-start;">
                                <p>99.9%</p>
                                <p>довольных<br>клиентов</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Почему выбирают нас -->
        <section class="why-us">
            <h2 class="section-title">Почему выбирают нас</h2>
            <div class="features-row">
                <div class="feature-card">
                    <div class="feature-number">01</div>
                    <div class="feature-title">Аутентичность</div>
                    <div class="feature-desc">Товары вдохновлены традиционной китайской культурой</div>
                </div>
                <div class="feature-card">
                    <div class="feature-number">02</div>
                    <div class="feature-title">Качество</div>
                    <div class="feature-desc">Мы тщательно отбираем каждый продукт</div>
                </div>
                <div class="feature-card">
                    <div class="feature-number">03</div>
                    <div class="feature-title">Уникальность</div>
                    <div class="feature-desc">Редкие и необычные сувениры для подарков</div>
                </div>
            </div>
        </section>

        <!-- Популярные товары -->
        <section class="popular-products">
            <h2 class="section-title">Популярные товары</h2>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <a href="product.php?id=<?= $product['id'] ?>" style="text-decoration: none; color: inherit;">
                            <div class="product-img" style="background-image: url('assets/img/<?= htmlspecialchars($product['image_url']) ?>'); background-size: cover;">
                            </div>
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="product-price"><?= number_format($product['price'], 0, '', ' ') ?>₽</div>
                            </div>
                        </a>
                        <form method="POST" action="profile.php" class="add-to-cart-form" data-product-id="<?= $product['id'] ?>" style="display: flex; gap: 30px; padding: 0 30px 20px 20px; align-items: center;">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="cart_action" value="<?= $product['is_in_cart'] ? 'remove' : 'add' ?>">
                            <button type="submit" class="btn-cart <?= $product['is_in_cart'] ? 'btn-remove' : '' ?>">
                                <?= $product['is_in_cart'] ? '🗑️ Удалить из корзины' : '🛒 В корзину' ?>
                            </button>
                            <button class="favorite-btn <?= ($product['is_favorite'] ?? false) ? 'active' : '' ?>" data-product-id="<?= $product['id'] ?>">
                                <i class="<?= ($product['is_favorite'] ?? false) ? 'fas fa-heart' : 'far fa-heart' ?>"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="center-btn">
                <a href="catalog.php" class="btn-secondary" style="background: #740000; color: white; text-decoration: none;padding: 20px;border-radius: 20px;font-size: 25px;">Смотреть все товары</a>
            </div>
        </section>

        <!-- Подвал -->
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
        // Проверка авторизации (прямая, без fetch)
        const isLoggedIn = <?= isLoggedIn() ? 'true' : 'false' ?>;

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = 'cart-notification';
            notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#ff9800'};
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
            font-size: 14px;
            max-width: 350px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        `;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // Добавление/удаление из корзины
        document.querySelectorAll('.add-to-cart-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (!isLoggedIn) {
                    showNotification('🔐 Вам необходимо войти в аккаунт или зарегистрироваться!', 'warning');
                    return;
                }

                const formData = new FormData(form);
                const button = form.querySelector('.btn-cart');
                const originalText = button.innerHTML;
                let currentAction = formData.get('cart_action');

                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Обработка...';
                button.disabled = true;

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

                        // Меняем кнопку
                        if (currentAction === 'add') {
                            formData.set('cart_action', 'remove');
                            button.classList.add('btn-remove');
                            button.innerHTML = '🗑️ Удалить из корзины';
                        } else {
                            formData.set('cart_action', 'add');
                            button.classList.remove('btn-remove');
                            button.innerHTML = '🛒 В корзину';
                        }
                        const hiddenInput = form.querySelector('input[name="cart_action"]');
                        if (hiddenInput) hiddenInput.value = formData.get('cart_action');
                    } else {
                        showNotification(result.message, 'error');
                        button.innerHTML = originalText;
                    }
                } catch (error) {
                    console.error('Ошибка:', error);
                    showNotification('Ошибка при обработке', 'error');
                    button.innerHTML = originalText;
                } finally {
                    button.disabled = false;
                }
            });
        });

        // Добавление в избранное
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();

                if (!isLoggedIn) {
                    showNotification('🔐 Вам необходимо войти в аккаунт или зарегистрироваться!', 'warning');
                    return;
                }

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
                            showNotification('❤️ Добавлено в избранное!', 'success');
                        } else {
                            btn.classList.remove('active');
                            btn.innerHTML = '<i class="far fa-heart"></i>';
                            showNotification('💔 Удалено из избранного', 'success');
                        }
                    } else {
                        showNotification(result.message, 'error');
                        btn.innerHTML = originalHtml;
                    }
                } catch (error) {
                    console.error('Ошибка:', error);
                    showNotification('Ошибка при добавлении в избранное', 'error');
                    btn.innerHTML = originalHtml;
                } finally {
                    btn.disabled = false;
                }
            });
        });
    </script>
</body>

</html>