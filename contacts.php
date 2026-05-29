<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Контакты - SUNNSET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="./style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Segoe+UI:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .contact-page {
            padding: 60px 80px;
        }

        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-top: 40px;
        }

        .contact-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 40px;
        }

        .contact-info h2 {
            font-size: 35px;
            margin-bottom: 30px;
            color: #FF4343;
        }

        .info-block {
            margin-bottom: 30px;
        }

        .info-block h3 {
            font-size: 24px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-block h3 i {
            color: #941607;
        }

        .info-block p {
            font-size: 18px;
            color: #C4C4C4;
            margin-bottom: 8px;
        }

        .work-days {
            list-style: none;
        }

        .map-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            overflow: hidden;
            height: 500px;
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        @media (max-width: 900px) {
            .contact-page {
                padding: 40px;
            }

            .contact-container {
                grid-template-columns: 1fr;
                gap: 30px;
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

        <section class="contact-page">
            <h1>Контакты</h1>
            <div class="contact-container">
                <div class="contact-info">
                    <h2>Свяжитесь с нами</h2>

                    <div class="info-block">
                        <h3> Режим работы</h3>
                        <ul class="work-days">
                            <li><span>Понедельник - Пятница:</span><span>10:00 - 20:00</span></li>
                            <li><span>Суббота:</span><span>11:00 - 18:00</span></li>
                            <li><span>Воскресенье:</span><span>Выходной</span></li>
                        </ul>
                    </div>

                    <div class="info-block">
                        <h3> Телефон</h3>
                        <p>+7 (916) 999-99-99</p>
                        <p>+7 (495) 123-45-67</p>
                    </div>

                    <div class="info-block">
                        <h3> Email</h3>
                        <p>info@sunnset.com</p>
                        <p>support@sunnset.com</p>
                    </div>

                    <div class="info-block">
                        <h3> Адрес</h3>
                        <p>г. Москва, улица Ильинка, 23с1</p>
                        <p>м. Китай-город, выход в сторону ул. Ильинка</p>
                    </div>
                </div>

                <div class="map-container">
                    <iframe
                        src="https://yandex.ru/map-widget/v1/?from=mapframe&indoorLevel=1&ll=37.629913%2C55.756503&mode=whatshere&utm_source=share&whatshere%5Bpoint%5D=37.629071%2C55.756726&whatshere%5Bzoom%5D=17&z=17"
                        width="100%"
                        height="100%"
                        frameborder="0"
                        allowfullscreen="true"
                        style="position:relative; border:0;">
                    </iframe>
                </div>
            </div>
        </section>

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