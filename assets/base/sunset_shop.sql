-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Май 29 2026 г., 02:08
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `sunset_shop`
--

-- --------------------------------------------------------

--
-- Структура таблицы `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `added_at`) VALUES
(156, 5, 9, 1, '2026-05-14 11:54:44'),
(157, 5, 8, 1, '2026-05-14 11:54:46'),
(158, 5, 7, 1, '2026-05-14 11:54:47'),
(166, 10, 6, 1, '2026-05-14 23:10:29'),
(167, 10, 5, 1, '2026-05-14 23:10:30'),
(175, 6, 8, 1, '2026-05-25 17:21:06');

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Все', 'all', 'Все товары'),
(2, 'Чай', 'tea', 'Чайные наборы и аксессуары'),
(3, 'Декор', 'decor', 'Декоративные элементы интерьера'),
(4, 'Сувениры', 'souvenirs', 'Традиционные китайские сувениры');

-- --------------------------------------------------------

--
-- Структура таблицы `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(17, 5, 8, '2026-05-13 11:40:58'),
(29, 5, 7, '2026-05-13 14:26:24'),
(37, 6, 10, '2026-05-14 21:41:38'),
(38, 10, 9, '2026-05-14 23:10:27'),
(43, 14, 9, '2026-05-28 22:40:00'),
(44, 14, 8, '2026-05-28 22:40:02');

-- --------------------------------------------------------

--
-- Структура таблицы `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL,
  `success` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','processing','ready','completed','cancelled') DEFAULT 'pending',
  `shipping_address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `delivery_method` varchar(50) DEFAULT 'courier',
  `payment_method` varchar(50) DEFAULT 'card',
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_date`, `total_amount`, `status`, `shipping_address`, `phone`, `delivery_method`, `payment_method`, `comment`) VALUES
(4, 6, '2026-05-11 19:27:49', 6950.00, 'completed', 'г. Железнодорожный, ул. Андрея Белого, д. 8', '+7 (916) 807-58-70', 'courier', 'card', ''),
(5, 5, '2026-05-13 14:32:05', 4850.00, 'completed', 'город Москва, Новодевичий проезд, дом 4', '+7 (916) 807-58-70', 'pickup', 'cash', ''),
(9, 6, '2026-05-13 19:50:38', 10300.00, 'completed', 'город Балашиха, улица Андрея Белого, д. 8, квартира 86', '+7 (916) 807-58-70', 'pickup', 'cash', 'только аккуратнее!!!'),
(10, 6, '2026-05-14 11:46:20', 10790.00, 'completed', 'город Балашиха, улица Андрея Белого, дом 8, кв 86', '+7 (916) 807-58-70', 'pickup', 'cash', 'тест'),
(11, 6, '2026-05-14 12:56:50', 9000.00, 'pending', 'заберу сам', '+7 (916) 807-58-70', 'pickup', 'cash', ''),
(12, 9, '2026-05-14 15:10:23', 350.00, 'completed', 'заберу сам', '+7 (999) 999-99-99', 'pickup', 'cash', ''),
(14, 9, '2026-05-24 17:12:55', 350.00, 'pending', '123123', '+7 (999) 999-99-99', 'courier', 'cash', ''),
(15, 9, '2026-05-24 17:16:58', 4200.00, 'completed', '123123', '+7 (999) 999-99-99', 'pickup', 'card', ''),
(16, 9, '2026-05-24 17:21:13', 890.00, 'pending', 'йцуйцу', '+7 (999) 999-99-99', 'courier', 'cash', ''),
(19, 14, '2026-05-28 22:40:25', 3600.00, 'completed', 'Москва', '+7 (916) 807-58-70', 'courier', 'cash', '');

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(10, 4, 9, 7, 350.00),
(11, 4, 8, 1, 4500.00),
(12, 5, 8, 1, 4500.00),
(13, 5, 9, 1, 350.00),
(18, 9, 7, 1, 1500.00),
(19, 9, 3, 1, 1800.00),
(20, 9, 1, 1, 2500.00),
(21, 9, 8, 1, 4500.00),
(22, 10, 7, 1, 1500.00),
(23, 10, 5, 1, 890.00),
(24, 10, 8, 1, 4500.00),
(25, 10, 6, 1, 2100.00),
(26, 10, 3, 1, 1800.00),
(27, 11, 8, 2, 4500.00),
(28, 12, 9, 1, 350.00),
(30, 14, 9, 1, 350.00),
(31, 15, 6, 2, 2100.00),
(32, 16, 5, 1, 890.00),
(36, 19, 10, 3, 1200.00);

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `long_description` text DEFAULT NULL,
  `origin` varchar(100) DEFAULT NULL,
  `material` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `long_description`, `origin`, `material`, `price`, `image_url`, `stock`, `created_at`) VALUES
(1, 2, 'Чайный набор \"Дракон\"', 'Изысканный чайный набор из фарфора с драконом', 'Чайный набор \"Дракон\" создан по древним технологиям китайского фарфора, которые насчитывают более 1700 лет. Город Цзиндэчжэнь называют \"фарфоровой столицей\" мира. Мастера используют только натуральные материалы: каолиновую глину, полевой шпат и кварц. Ручная роспись кобальтом и золотом делает каждый предмет уникальным. Дракон в китайской культуре символизирует мудрость, силу и удачу. Набор проходит 72 этапа производства, включая 3 обжига при температуре до 1300°C.', 'Город Цзиндэчжэнь, провинция Цзянси, Китай', 'Высококачественный фарфор, ручная роспись кобальтом и золотом', 2500.00, 'tea_set.jpg', 15, '2026-03-21 02:03:19'),
(2, 2, 'Чайный набор \"Бамбук\"', 'Традиционный чайный набор с бамбуковым узором', 'Фонари изготавливаются вручную в городе Цзыгун, известном как \"город фонарей\" уже более 1000 лет. Каждый год здесь проходит фестиваль фонарей, который привлекает миллионы туристов. Каркас делается из бамбука, который собирают в определенное время года для максимальной гибкости и прочности. Шелк для фонаря окрашивается натуральными красителями, рецепты которых передаются из поколения в поколение. Каждый фонарь проходит 5 этапов создания: формирование каркаса, натяжение шелка, роспись, сборка и проверка. Традиционно такие фонари зажигают во время Праздника фонарей для привлечения удачи и благополучия в дом.', 'Город Цзыгун, провинция Сычуань, Китай', 'Натуральный шелк, бамбуковый каркас, ручная роспись', 3200.00, 'tea_bamboo.jpg', 10, '2026-03-21 02:03:19'),
(3, 2, 'Чайник \"Гунфу Ча\"', 'Керамический чайник для китайской чайной церемонии', 'Каждая фигурка создается мастером-резчиком с 20-летним опытом работы. Город Дунъян славится своими традициями резьбы по дереву, которые внесены в список нематериального культурного наследия Китая. Используется красное дерево из провинции Хайнань, которое ценится за свою плотность, красивую текстуру и долговечность. Процесс создания занимает от 2 до 4 недель: от выбора древесины до финишной полировки натуральным пчелиным воском. Дракон изображается с пятью когтями, что в древнем Китае было символом императорской власти. Каждая чешуйка прорабатывается вручную специальными резцами, и на одну фигурку может уйти до 5000 отдельных движений резцом.', 'Город Дунъян, провинция Чжэцзян, Китай', 'Красное дерево (хуанхуали), натуральный пчелиный воск', 1800.00, 'teapot.jpg', 20, '2026-03-21 02:03:19'),
(4, 3, 'Китайский фонарь', 'Традиционный красный фонарь из шелка', 'Веер создается по техникам династии Мин (1368-1644). Город Сучжоу называют \"Китайской Венецией\", он славится своими садами и традиционными ремеслами. Каркас изготавливается из бамбука, который выдерживается в течение года для достижения идеальной гибкости. Бумага для веера делается вручную из волокон сандалового дерева, что придает изделию тонкий, едва уловимый аромат. Роспись выполняется тушью и акварелью по древним канонам китайской живописи \"гохуа\". Каждый веер уникален - мастера не повторяют рисунки, создавая неповторимые композиции с цветами сливы (символ стойкости), бамбуком (символ благородства), хризантемами (символ долголетия) или пейзажами.', 'Город Сучжоу, провинция Цзянсу, Китай', 'Из бамбуковых палочек, покрытых тканью', 1500.00, 'lantern.jpg', 25, '2026-03-21 02:03:19'),
(5, 3, 'Настенный веер', 'Декоративный веер ручной работы', 'Каждый предмет в нашем магазине создается мастерами, которые бережно хранят традиции китайского ремесла. Технологии изготовления передаются из поколения в поколение на протяжении сотен лет. В основе каждого изделия лежит древняя философия гармонии человека и природы. Мастера используют только натуральные материалы и традиционные техники обработки. Мы лично отбираем каждый товар, чтобы вы могли прикоснуться к настоящей культуре Поднебесной.', 'Китай', 'Бамбук, шёлк и бумага', 890.00, 'fan.jpg', 30, '2026-03-21 02:03:19'),
(6, 3, 'Фигурка Будды', 'Статуэтка Будды из полистоуна', 'Каждый предмет в нашем магазине создается мастерами, которые бережно хранят традиции китайского ремесла. Технологии изготовления передаются из поколения в поколение на протяжении сотен лет. В основе каждого изделия лежит древняя философия гармонии человека и природы. Мастера используют только натуральные материалы и традиционные техники обработки. Мы лично отбираем каждый товар, чтобы вы могли прикоснуться к настоящей культуре Поднебесной.', 'Китай', 'Железо, медь, латунь', 2100.00, 'buddha.jpg', 12, '2026-03-21 02:03:19'),
(7, 4, 'Декор дракон', 'Резная фигурка дракона из дерева', 'Каждый предмет в нашем магазине создается мастерами, которые бережно хранят традиции китайского ремесла. Технологии изготовления передаются из поколения в поколение на протяжении сотен лет. В основе каждого изделия лежит древняя философия гармонии человека и природы. Мастера используют только натуральные материалы и традиционные техники обработки. Мы лично отбираем каждый товар, чтобы вы могли прикоснуться к настоящей культуре Поднебесной.', 'Китай', 'Глина, латунь или дерево', 1500.00, 'dragon.jpg', 18, '2026-03-21 02:03:19'),
(8, 4, 'Фарфоровая ваза', 'Китайская ваза ручной росписи', 'Каждый предмет в нашем магазине создается мастерами, которые бережно хранят традиции китайского ремесла. Технологии изготовления передаются из поколения в поколение на протяжении сотен лет. В основе каждого изделия лежит древняя философия гармонии человека и природы. Мастера используют только натуральные материалы и традиционные техники обработки. Мы лично отбираем каждый товар, чтобы вы могли прикоснуться к настоящей культуре Поднебесной.', 'Китай', 'Фарфор', 4500.00, 'vase.jpg', 8, '2026-03-21 02:03:19'),
(9, 4, 'Магнит \"Иероглиф\"', 'Набор магнитов с китайскими иероглифами', 'Каждый предмет в нашем магазине создается мастерами, которые бережно хранят традиции китайского ремесла. Технологии изготовления передаются из поколения в поколение на протяжении сотен лет. В основе каждого изделия лежит древняя философия гармонии человека и природы. Мастера используют только натуральные материалы и традиционные техники обработки. Мы лично отбираем каждый товар, чтобы вы могли прикоснуться к настоящей культуре Поднебесной.', 'Китай', 'Ювелирный сплав', 350.00, 'magnet.jpg', 50, '2026-03-21 02:03:19'),
(10, 2, 'Чай \"Те Гуань Инь\"', 'Китайский улун высшего сорта', 'Каждый предмет в нашем магазине создается мастерами, которые бережно хранят традиции китайского ремесла. Технологии изготовления передаются из поколения в поколение на протяжении сотен лет. В основе каждого изделия лежит древняя философия гармонии человека и природы. Мастера используют только натуральные материалы и традиционные техники обработки. Мы лично отбираем каждый товар, чтобы вы могли прикоснуться к настоящей культуре Поднебесной.', 'Китай', 'Из крупных зрелых чайных листьев одноимённого сорта', 1200.00, 'tea_tgy.jpg', 40, '2026-03-21 02:03:19');

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `qualities` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `reviews`
--

INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `rating`, `qualities`, `comment`, `created_at`) VALUES
(8, 6, 9, 5, 'Цена', 'Красивый магнит, хорошо магнитится', '2026-05-12 14:03:20'),
(9, 6, 5, 4, 'Упаковка', 'нормальный веер, не подошел под интерьер', '2026-05-14 12:19:52'),
(10, 6, 6, 2, 'Доставка', 'слишком маленькая фигурка, на фото кажется больше', '2026-05-14 12:19:53'),
(11, 6, 7, 4, 'Дизайн', 'красивая фигурка, но хрупкая', '2026-05-14 12:19:54'),
(12, 6, 3, 2, '', 'не выдержал нагрева', '2026-05-14 12:19:55'),
(13, 6, 1, 3, 'Дизайн', 'красивый набор, но хрупкие чашки', '2026-05-14 12:19:56'),
(14, 6, 8, 5, 'Дизайн', 'Очень красивая и большая ваза', '2026-05-14 12:20:44'),
(17, 14, 10, 4, 'Дизайн', 'красиво', '2026-05-28 22:40:49');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified` tinyint(4) DEFAULT 0,
  `verification_token` varchar(100) DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `new_email` varchar(100) DEFAULT NULL,
  `email_verification_token` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `address`, `created_at`, `email_verified`, `verification_token`, `verification_code`, `verification_expires`, `reset_token`, `reset_expires`, `role`, `new_email`, `email_verification_token`) VALUES
(5, 'SunnSet', 'isip_v.e.kazakova@mpt.ru', '$2y$10$fqYcD9PQ5R/spAzsp3Xs8.gljTbTcQZKpKJW9y3XtI.g9be/WnuK6', 'Елена Владимировна', '+7 (916) 807-58-70', NULL, '2026-05-06 11:48:35', 1, '6ec6d3a1d3ae31e7a8e4c7a58331fb46b67f445b11a522c14bac36d4044a1208', NULL, NULL, NULL, NULL, 'admin', NULL, NULL),
(6, 'SunnSet1', 'sunnset960@gmail.com', '$2y$10$KhYcRq.cpAAp9aiG.JONyexGRDXoUIWoohRAOAyQYoz0V5JedTUPK', 'Казакова Валерия', '+7 (916) 807-58-70', '', '2026-05-06 12:37:53', 1, NULL, NULL, NULL, 'f07370d453a3326345b8abc910171027361a9a55435738cb1c5f478c74414e45', '2026-05-12 17:30:02', 'user', NULL, NULL),
(9, 'Lera', 'klera986@mail.ru', '$2y$10$WhpRfiXTy8E.AgRTe30qBejNgiCck5Bwpr6KTTWCcL2JO6rPm1x.a', 'Валерия', '+7 (999) 999-99-99', '', '2026-05-14 14:55:11', 1, NULL, NULL, NULL, NULL, NULL, 'user', NULL, NULL),
(10, 'Test', 'testirovaniecoda@mail.ru', '$2y$10$N.Ieo9naMjoKO80SqO1I..YdJTv.6ufKVBYJcEwJ4tJ2fsW3ggQUO', 'Иван', '+7 (123) 456-78-90', NULL, '2026-05-14 23:09:50', 1, NULL, NULL, NULL, NULL, NULL, 'user', NULL, NULL),
(11, 'Valeriya1', 'klera@mail.ru', '$2y$10$iCkAj4/eleRxBnw66fgk0ujBT.gxB.SIkY1/PHxQ3sO684SskRU0.', 'Казакова Валерия', '+7 (916) 807-58-70', NULL, '2026-05-24 14:43:38', 0, NULL, '897347', '2026-05-24 16:58:38', NULL, NULL, 'user', NULL, NULL),
(14, 'Valeriya', 'kazakova075@mail.ru', '$2y$10$PgFHLqM7er4bFBOTggS97Ot32q1MIFL5d4IcK/Exff4Co.G7teS02', 'Казакова Валерия', '+7 (916) 807-58-70', NULL, '2026-05-28 22:39:35', 1, NULL, NULL, NULL, NULL, NULL, 'user', NULL, NULL);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Индексы таблицы `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip`,`attempt_time`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT для таблицы `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
