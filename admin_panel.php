<?php
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: index.php');
    exit();
}

$active_table = isset($_GET['table']) ? $_GET['table'] : 'orders';
$filter_category = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : 0;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_role = isset($_GET['filter_role']) ? $_GET['filter_role'] : '';
$filter_rating = isset($_GET['filter_rating']) ? (int)$_GET['filter_rating'] : 0;
$filter_username = isset($_GET['filter_username']) ? trim($_GET['filter_username']) : '';
$filter_email_verified = isset($_GET['filter_email_verified']) ? (int)$_GET['filter_email_verified'] : -1;
$filter_product = isset($_GET['filter_product']) ? (int)$_GET['filter_product'] : 0;
// Получаем список всех таблиц
$tables = [
    'users' => 'Пользователи',
    'products' => 'Товары',
    'categories' => 'Категории',
    'orders' => 'Заказы',
    'order_items' => 'Элементы заказов',
    'cart' => 'Корзины',
    'favorites' => 'Избранное',
    'reviews' => 'Отзывы'
];

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ==================== УПРАВЛЕНИЕ ЗАКАЗАМИ ====================
    if (isset($_POST['update_order_status'])) {
        $order_id = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        $_SESSION['admin_success'] = "Статус заказа #{$order_id} обновлен";
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // ==================== УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ ====================
    // Добавление пользователя
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $role = $_POST['role'];

        $errors = [];
        if (strlen($username) < 3) $errors[] = 'Никнейм должен быть не менее 3 символов';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email';
        if (strlen($password) < 6) $errors[] = 'Пароль должен быть не менее 6 символов';

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $_SESSION['admin_error'] = "Пользователь с таким никнеймом или email уже существует";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed, $full_name, $phone, $address, $role]);
                $_SESSION['admin_success'] = "Пользователь {$username} успешно добавлен";
            }
        } else {
            $_SESSION['admin_error'] = implode('<br>', $errors);
        }
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // Обновление пользователя
    if (isset($_POST['update_user'])) {
        $user_id = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $role = $_POST['role'];

        $errors = [];
        if (strlen($username) < 3) $errors[] = 'Никнейм должен быть не менее 3 символов';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email';

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $user_id]);
            if ($stmt->fetch()) {
                $_SESSION['admin_error'] = "Пользователь с таким никнеймом или email уже существует";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, address = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $email, $full_name, $phone, $address, $role, $user_id]);

                if ($user_id == $_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                }
                $_SESSION['admin_success'] = "Пользователь {$username} успешно обновлен";
            }
        } else {
            $_SESSION['admin_error'] = implode('<br>', $errors);
        }
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // Удаление пользователя
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];

        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['admin_error'] = "Нельзя удалить самого себя";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['admin_success'] = "Пользователь удален";
        }
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // Смена пароля пользователя
    if (isset($_POST['reset_user_password'])) {
        $user_id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];

        if (strlen($new_password) >= 6) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
            $_SESSION['admin_success'] = "Пароль пользователя изменен";
        } else {
            $_SESSION['admin_error'] = "Пароль должен быть не менее 6 символов";
        }
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // ==================== УПРАВЛЕНИЕ ТОВАРАМИ ====================
    // Добавление товара
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $category_id = (int)$_POST['category_id'];
        $price = (float)$_POST['price'];
        $description = trim($_POST['description']);
        $long_description = trim($_POST['long_description']);
        $origin = trim($_POST['origin']);
        $material = trim($_POST['material']);
        $stock = (int)$_POST['stock'];
        $image_url = trim($_POST['image_url']);

        $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, description, long_description, origin, material, stock, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $category_id, $price, $description, $long_description, $origin, $material, $stock, $image_url]);

        
        $_SESSION['admin_success'] = "Товар добавлен";
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // Обновление товара
    if (isset($_POST['update_product'])) {
        $product_id = (int)$_POST['product_id'];
        $name = trim($_POST['name']);
        $category_id = (int)$_POST['category_id'];
        $price = (float)$_POST['price'];
        $description = trim($_POST['description']);
        $long_description = trim($_POST['long_description']);
        $origin = trim($_POST['origin']);
        $material = trim($_POST['material']);
        $stock = (int)$_POST['stock'];
        $image_url = trim($_POST['image_url']);

        $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, price = ?, description = ?, long_description = ?, origin = ?, material = ?, stock = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $category_id, $price, $description, $long_description, $origin, $material, $stock, $image_url, $product_id]);
        $_SESSION['admin_success'] = "Товар обновлен";
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // Удаление товара
    if (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $_SESSION['admin_success'] = "Товар удален";
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // ==================== УПРАВЛЕНИЕ КАТЕГОРИЯМИ ====================
    // Добавление категории
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $description = trim($_POST['description']);

        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $slug, $description]);
        $_SESSION['admin_success'] = "Категория добавлена";
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // Обновление категории
    // Обновление категории
    if (isset($_POST['update_category'])) {

        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $description = trim($_POST['description']);

        // Валидация
        if (empty($name)) {
            $_SESSION['admin_error'] = "Название категории не может быть пустым";
        } elseif (empty($slug)) {
            $_SESSION['admin_error'] = "Slug категории не может быть пустым";
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?");
            if ($stmt->execute([$name, $slug, $description, $category_id])) {
                $_SESSION['admin_success'] = "Категория «{$name}» успешно обновлена";
            } else {
                $_SESSION['admin_error'] = "Ошибка при обновлении категории: " . implode(" ", $stmt->errorInfo());
            }
        }

        header("Location: admin_panel.php?table=categories");
        exit();
    }

    // Удаление категории
    if (isset($_POST['delete_category'])) {
        $category_id = (int)$_POST['category_id'];
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $_SESSION['admin_success'] = "Категория удалена";
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }

    // Обновление роли пользователя
    if (isset($_POST['update_user_role'])) {
        $user_id = (int)$_POST['user_id'];
        $role = $_POST['role'];

        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);

        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['role'] = $role;
        }

        $_SESSION['admin_success'] = "Роль пользователя обновлена";
        header("Location: admin_panel.php?table={$active_table}");
        exit();
    }
}

// Если пришёл POST с active_table — используем его
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['active_table'])) {
    $active_table = $_POST['active_table'];
} else {
    $active_table = isset($_GET['table']) ? $_GET['table'] : 'orders';
}

// Получение данных для текущей таблицы
$data = [];
$columns = [];
$categories = [];

switch ($active_table) {
    case 'users':
        $sql = "SELECT id, username, email, full_name, phone, address, role, email_verified, created_at FROM users WHERE 1=1";
        $params = [];

        if (!empty($filter_role)) {
            $sql .= " AND role = ?";
            $params[] = $filter_role;
        }
        if (!empty($filter_username)) {
            $sql .= " AND username = ?";
            $params[] = $filter_username;
        }
        if ($filter_email_verified >= 0) {
            $sql .= " AND email_verified = ?";
            $params[] = $filter_email_verified;
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Никнейм', 'Email', 'Полное имя', 'Телефон', 'Адрес', 'Роль', 'Email подтвержден', 'Дата регистрации'];
        break;

    case 'products':
        if ($filter_category > 0) {
            $stmt = $pdo->prepare("SELECT p.id, p.category_id, c.name as category_name, p.name, p.description, p.long_description, p.origin, p.material, p.price, p.image_url, p.stock, p.created_at 
                               FROM products p 
                               LEFT JOIN categories c ON p.category_id = c.id 
                               WHERE p.category_id = ?
                               ORDER BY p.id DESC");
            $stmt->execute([$filter_category]);
        } else {
            $stmt = $pdo->query("SELECT p.id, p.category_id, c.name as category_name, p.name, p.description, p.long_description, p.origin, p.material, p.price, p.image_url, p.stock, p.created_at 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             ORDER BY p.id DESC");
        }
        $data = $stmt->fetchAll();
        $columns = ['ID', 'ID кат.', 'Категория', 'Название', 'Описание', 'История', 'Место', 'Материал', 'Цена', 'Изобр.', 'Наличие', 'Дата'];
        $stmt_cat = $pdo->query("SELECT * FROM categories WHERE slug != 'all' ORDER BY name");
        $categories = $stmt_cat->fetchAll();
        break;

    case 'categories':
        $stmt = $pdo->query("SELECT id, name, slug, description FROM categories ORDER BY id");
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Название', 'Slug', 'Описание'];
        break;

    case 'orders':
        if (!empty($filter_status)) {
            $stmt = $pdo->prepare("SELECT o.id, o.user_id, u.username, u.email, o.order_date, o.total_amount, o.status, o.shipping_address, o.phone, o.delivery_method, o.payment_method, o.comment 
                               FROM orders o 
                               LEFT JOIN users u ON o.user_id = u.id 
                               WHERE o.status = ?
                               ORDER BY o.order_date DESC");
            $stmt->execute([$filter_status]);
        } else {
            $stmt = $pdo->query("SELECT o.id, o.user_id, u.username, u.email, o.order_date, o.total_amount, o.status, o.shipping_address, o.phone, o.delivery_method, o.payment_method, o.comment 
                             FROM orders o 
                             LEFT JOIN users u ON o.user_id = u.id 
                             ORDER BY o.order_date DESC");
        }
        $data = $stmt->fetchAll();
        $columns = ['ID', 'ID польз.', 'Пользователь', 'Email', 'Дата', 'Сумма', 'Статус', 'Адрес', 'Телефон', 'Доставка', 'Оплата', 'Коммент.'];
        break;

    case 'order_items':
        $stmt = $pdo->query("SELECT oi.id, oi.order_id, oi.product_id, p.name as product_name, oi.quantity, oi.price 
                             FROM order_items oi 
                             JOIN orders o ON oi.order_id = o.id 
                             JOIN products p ON oi.product_id = p.id 
                             ORDER BY oi.id DESC");
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Заказ ID', 'ID товара', 'Товар', 'Кол-во', 'Цена'];
        break;

    case 'cart':
        $sql = "SELECT c.id, c.user_id, u.username, c.product_id, p.name as product_name, c.quantity, c.added_at 
                FROM cart c 
                JOIN users u ON c.user_id = u.id 
                JOIN products p ON c.product_id = p.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filter_username)) {
            $sql .= " AND u.username = ?";
            $params[] = $filter_username;
        }
        if (!empty($filter_product)) {
            $sql .= " AND c.product_id = ?";
            $params[] = $filter_product;
        }
        $sql .= " ORDER BY c.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        $columns = ['ID', 'ID польз.', 'Пользователь', 'ID товара', 'Товар', 'Кол-во', 'Дата'];
        break;

    case 'favorites':
        if (!empty($filter_username)) {
            $stmt = $pdo->prepare("SELECT f.id, f.user_id, u.username, f.product_id, p.name as product_name, f.created_at 
                           FROM favorites f 
                           JOIN users u ON f.user_id = u.id 
                           JOIN products p ON f.product_id = p.id 
                           WHERE u.username = ?
                           ORDER BY f.id DESC");
            $stmt->execute([$filter_username]);
        } else {
            $stmt = $pdo->query("SELECT f.id, f.user_id, u.username, f.product_id, p.name as product_name, f.created_at 
                             FROM favorites f 
                             JOIN users u ON f.user_id = u.id 
                             JOIN products p ON f.product_id = p.id 
                             ORDER BY f.id DESC");
        }
        $data = $stmt->fetchAll();
        $columns = ['ID', 'ID польз.', 'Пользователь', 'ID товара', 'Товар', 'Дата'];
        break;

    case 'reviews':
        if ($filter_rating > 0) {
            $stmt = $pdo->prepare("SELECT r.id, r.user_id, u.username, r.product_id, p.name as product_name, r.rating, r.qualities, r.comment, r.created_at 
                               FROM reviews r 
                               JOIN users u ON r.user_id = u.id 
                               JOIN products p ON r.product_id = p.id 
                               WHERE r.rating = ?
                               ORDER BY r.id DESC");
            $stmt->execute([$filter_rating]);
        } else {
            $stmt = $pdo->query("SELECT r.id, r.user_id, u.username, r.product_id, p.name as product_name, r.rating, r.qualities, r.comment, r.created_at 
                             FROM reviews r 
                             JOIN users u ON r.user_id = u.id 
                             JOIN products p ON r.product_id = p.id 
                             ORDER BY r.id DESC");
        }
        $data = $stmt->fetchAll();
        $columns = ['ID', 'ID польз.', 'Пользователь', 'ID товара', 'Товар', 'Оценка', 'Понравилось', 'Комментарий', 'Дата'];
        break;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель - SUNNSET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Segoe+UI:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', 'Segoe UI', sans-serif;
            background: #0F0E0E;
            color: #FFFFFF;
        }

        .admin-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .admin-header h1 {
            font-size: 32px;
            color: #FF4343;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-to-profile {
            background: #740000;
            color: white;
            text-decoration: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-size: 16px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-to-profile:hover {
            background: #941607;
            transform: translateY(-2px);
        }

        .tabs-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.05);
            padding: 15px 20px;
            border-radius: 15px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 8px;
            color: #C4C4C4;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .tab-btn.active {
            background: #740000;
            color: white;
        }

        .table-container {
            overflow-x: auto;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 800px;
        }

        .data-table th {
            background: rgba(255, 255, 255, 0.1);
            color: #FF4343;
            font-weight: 600;
            padding: 14px 12px;
            text-align: left;
            border-bottom: 2px solid #FF4343;
            white-space: nowrap;
        }

        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
        }

        .data-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        select,
        .status-select,
        .role-select {
            background: #000000;
            color: #ffffff;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #FF4343;
            font-size: 13px;
            cursor: pointer;
        }

        select option,
        .status-select option,
        .role-select option {
            background: #000000;
            color: #ffffff;
        }

        .btn-update {
            background: #740000;
            color: white;
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: 0.2s;
        }

        .btn-update:hover {
            background: #941607;
        }

        .btn-delete {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: 0.2s;
            margin-left: 8px;
        }

        .btn-delete:hover {
            background: rgba(244, 67, 54, 0.4);
        }

        .btn-add {
            background: #4caf50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 20px;
            font-size: 14px;
            transition: 0.2s;
        }

        .btn-add:hover {
            background: #45a049;
        }

        .form-inline {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
        }

        .product-img-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .text-truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .modal {
            display: none;
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
            max-width: 550px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-content h3 {
            color: #FF4343;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .modal-content input,
        .modal-content textarea,
        .modal-content select {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 15px;
            background: #000000;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }

        .modal-content select option {
            background: #000000;
            color: white;
        }

        .modal-content input:focus,
        .modal-content textarea:focus,
        .modal-content select:focus {
            outline: none;
            border-color: #FF4343;
        }

        .close-modal {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: #C4C4C4;
            transition: 0.2s;
        }

        .close-modal:hover {
            color: #FF4343;
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                text-align: center;
            }

            .tabs-container {
                flex-direction: row;
                overflow-x: auto;
                flex-wrap: nowrap;
            }

            .tab-btn {
                white-space: nowrap;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-crown"></i> Админ-панель</h1>
            <a href="profile.php" class="back-to-profile"><i class="fas fa-arrow-left"></i> Вернуться в профиль</a>
        </div>

        <?php if (isset($_SESSION['admin_success'])): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['admin_success'] ?></div>
            <?php unset($_SESSION['admin_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['admin_error'])): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['admin_error'] ?></div>
            <?php unset($_SESSION['admin_error']); ?>
        <?php endif; ?>

        <div class="tabs-container">
            <?php foreach ($tables as $key => $name): ?>
                <a href="?table=<?= $key ?>" class="tab-btn <?= $active_table == $key ? 'active' : '' ?>">
                    <i class="fas fa-table"></i> <?= $name ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="table-container">
            <!-- БЛОК ФИЛЬТРОВ И КНОПОК ДОБАВЛЕНИЯ (ВНЕ ТАБЛИЦЫ) -->
            <?php if ($active_table == 'users'): ?>
                <div style="padding: 20px 20px 0 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <button class="btn-add" onclick="openModal('addUserModal')"><i class="fas fa-user-plus"></i> Добавить пользователя</button>
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="color: #C4C4C4;"><i class="fas fa-filter"></i> Никнейм:</label>
                            <input type="text" id="usernameFilterUsers" placeholder="Точный никнейм" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 8px 15px; width: 150px;" value="<?= htmlspecialchars($filter_username) ?>">
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="color: #C4C4C4;"><i class="fas fa-verify"></i> Email:</label>
                            <select id="emailVerifiedFilter" style="background: rgba(255,255,255,0.1); color: white; border-radius: 8px; padding: 8px 15px;">
                                <option value="-1" <?= $filter_email_verified == -1 ? 'selected' : '' ?>>Все</option>
                                <option value="1" <?= $filter_email_verified == 1 ? 'selected' : '' ?>>✅ Подтверждён</option>
                                <option value="0" <?= $filter_email_verified == 0 ? 'selected' : '' ?>>❌ Не подтверждён</option>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="color: #C4C4C4;"><i class="fas fa-user-tag"></i> Роль:</label>
                            <select id="roleFilter" style="background: rgba(255,255,255,0.1); color: white; border-radius: 8px; padding: 8px 15px;">
                                <option value="">Все роли</option>
                                <option value="user" <?= $filter_role == 'user' ? 'selected' : '' ?>>Пользователь</option>
                                <option value="admin" <?= $filter_role == 'admin' ? 'selected' : '' ?>>Администратор</option>
                            </select>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($active_table == 'products'): ?>
                <div style="padding: 20px 20px 0 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <button class="btn-add" onclick="openModal('addProductModal')"><i class="fas fa-plus"></i> Добавить товар</button>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <label style="color: #C4C4C4;"><i class="fas fa-filter"></i> Категория:</label>
                        <select id="categoryFilter" style="background: rgba(255,255,255,0.1); color: white; border-radius: 8px; padding: 8px 15px;">
                            <option value="0">Все категории</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($active_table == 'categories'): ?>
                <div style="padding: 20px 20px 0 20px; display: flex; justify-content: flex-start;">
                    <button class="btn-add" onclick="openModal('addCategoryModal')"><i class="fas fa-plus"></i> Добавить категорию</button>
                </div>
            <?php endif; ?>

            <?php if ($active_table == 'orders'): ?>
                <div style="padding: 20px 20px 0 20px; display: flex; justify-content: flex-end; align-items: center; gap: 15px;">
                    <label style="color: #C4C4C4;"><i class="fas fa-filter"></i> Статус заказа:</label>
                    <select id="statusFilter" style="background: rgba(255,255,255,0.1); color: white; border-radius: 8px; padding: 8px 15px;">
                        <option value="">Все статусы</option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>🕐 Ожидание</option>
                        <option value="processing" <?= $filter_status == 'processing' ? 'selected' : '' ?>>🔄 Сбор заказа</option>
                        <option value="ready" <?= $filter_status == 'ready' ? 'selected' : '' ?>>✅ Готов</option>
                        <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>📦 Выдан</option>
                        <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>❌ Отменен</option>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($active_table == 'reviews'): ?>
                <div style="padding: 20px 20px 0 20px; display: flex; justify-content: flex-end; align-items: center; gap: 15px;">
                    <label style="color: #C4C4C4;"><i class="fas fa-filter"></i> Оценка:</label>
                    <select id="ratingFilter" style="background: rgba(255,255,255,0.1); color: white; border-radius: 8px; padding: 8px 15px;">
                        <option value="0">Все оценки</option>
                        <option value="5" <?= $filter_rating == 5 ? 'selected' : '' ?>>★★★★★ (5)</option>
                        <option value="4" <?= $filter_rating == 4 ? 'selected' : '' ?>>★★★★☆ (4)</option>
                        <option value="3" <?= $filter_rating == 3 ? 'selected' : '' ?>>★★★☆☆ (3)</option>
                        <option value="2" <?= $filter_rating == 2 ? 'selected' : '' ?>>★★☆☆☆ (2)</option>
                        <option value="1" <?= $filter_rating == 1 ? 'selected' : '' ?>>★☆☆☆☆ (1)</option>
                    </select>
                </div>
            <?php endif; ?>

            <!-- ФИЛЬТРАЦИЯ КОРЗИНА И ИЗБРАННОЕ -->
            <?php if ($active_table == 'cart' || $active_table == 'favorites'): ?>
                <div style="padding: 20px 20px 0 20px;">
                    <!-- Фильтры -->
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label style="color: #C4C4C4;"><i class="fas fa-filter"></i> Пользователь:</label>
                                <input type="text" id="usernameFilterCart" placeholder="Точный никнейм" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 8px 15px; width: 200px;" value="<?= htmlspecialchars($filter_username) ?>">
                            </div>
                            
                        </div>
                        <?php if (!empty($filter_username) || !empty($filter_product)): ?>
                            <a href="?table=<?= $active_table ?>" style="color: #f44336; text-decoration: none;"><i class="fas fa-times"></i> Сбросить фильтры</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- САМА ТАБЛИЦА -->
            <table class="data-table">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th><?= $col ?></th>
                        <?php endforeach; ?>
                        <?php if (in_array($active_table, ['users', 'products', 'categories'])): ?>
                            <th>Действия</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($row as $key => $value): ?>
                                <?php if ($key == 'status'): ?>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                            <select name="status" class="status-select">
                                                <option value="pending" <?= $value == 'pending' ? 'selected' : '' ?>>🕐 Ожидание</option>
                                                <option value="processing" <?= $value == 'processing' ? 'selected' : '' ?>>🔄 Сбор заказа</option>
                                                <option value="ready" <?= $value == 'ready' ? 'selected' : '' ?>>✅ Готов</option>
                                                <option value="completed" <?= $value == 'completed' ? 'selected' : '' ?>>📦 Выдан</option>
                                                <option value="cancelled" <?= $value == 'cancelled' ? 'selected' : '' ?>>❌ Отменен</option>
                                            </select>
                                            <button type="submit" name="update_order_status" class="btn-update">Обновить</button>
                                        </form>
                                    </td>
                                <?php elseif ($key == 'role'): ?>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                            <select name="role" class="role-select">
                                                <option value="user" <?= $value == 'user' ? 'selected' : '' ?>>Пользователь</option>
                                                <option value="admin" <?= $value == 'admin' ? 'selected' : '' ?>>Администратор</option>
                                            </select>
                                            <button type="submit" name="update_user_role" class="btn-update">Обновить</button>
                                        </form>
                                    </td>
                                <?php elseif ($key == 'email_verified'): ?>
                                    <td><?= $value ? '<span style="color: #4caf50;">✅ Да</span>' : '<span style="color: #f44336;">❌ Нет</span>' ?></td>
                                <?php elseif ($key == 'price'): ?>
                                    <td><strong><?= number_format($value, 0, '', ' ') ?> ₽</strong></td>
                                <?php elseif ($key == 'rating'): ?>
                                    <td><span style="color: #FFD700;"><?= str_repeat('★', $value) . str_repeat('☆', 5 - $value) ?></span></td>
                                <?php elseif ($key == 'created_at' || $key == 'order_date' || $key == 'added_at'): ?>
                                    <td><?= date('d.m.Y H:i', strtotime($value)) ?></td>
                                <?php elseif ($key == 'image_url'): ?>
                                    <td><img src="assets/img/<?= htmlspecialchars($value) ?>" class="product-img-thumb" onerror="this.src='https://placehold.co/50x50/333/FF4343?text=No'"></td>
                                <?php elseif ($key == 'description' || $key == 'long_description' || $key == 'comment'): ?>
                                    <td class="text-truncate" title="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars(mb_substr($value, 0, 50)) ?>...</td>
                                <?php elseif ($key == 'address' || $key == 'shipping_address'): ?>
                                    <td class="text-truncate" title="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars(mb_substr($value, 0, 40)) ?>...</td>
                                <?php elseif ($key == 'qualities'): ?>
                                    <td><?= htmlspecialchars($value ?: '—') ?></td>
                                <?php else: ?>
                                    <td><?= htmlspecialchars($value ?? '—') ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <!-- Действия для пользователей -->
                            <?php if ($active_table == 'users'): ?>
                                <td class="action-buttons">
                                    <button class="btn-update" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)"><i class="fas fa-edit"></i> Редакт.</button>
                                    <button class="btn-update" onclick="openResetPasswordModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username']) ?>')"><i class="fas fa-key"></i> Сброс пароля</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn-delete" onclick="return confirm('Удалить пользователя «<?= htmlspecialchars($row['username']) ?>»? Это удалит все его данные!')"><i class="fas fa-trash"></i> Удалить</button>
                                    </form>
                                </td>
                            <?php endif; ?>

                            <!-- Действия для товаров -->
                            <?php if ($active_table == 'products'): ?>
                                <td class="action-buttons">
                                    <button class="btn-update" onclick="openEditProductModal(<?= htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)"><i class="fas fa-edit"></i> Редакт.</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="delete_product" class="btn-delete" onclick="return confirm('Удалить товар «<?= htmlspecialchars($row['name']) ?>»?')"><i class="fas fa-trash"></i> Удалить</button>
                                    </form>
                                </td>
                            <?php endif; ?>

                            <!-- Действия для категорий -->
                            <?php if ($active_table == 'categories'): ?>
                                <td class="action-buttons">
                                    <button class="btn-update"
                                        data-id="<?= $row['id'] ?>"
                                        data-name="<?= htmlspecialchars($row['name']) ?>"
                                        data-slug="<?= htmlspecialchars($row['slug']) ?>"
                                        data-description="<?= htmlspecialchars($row['description']) ?>"
                                        onclick="openEditCategoryModal(this)">
                                        <i class="fas fa-edit"></i> Редактирование
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="category_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="delete_category" class="btn-delete" onclick="return confirm('Удалить категорию «<?= htmlspecialchars($row['name']) ?>»?')"><i class="fas fa-trash"></i> Удалить</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ==================== МОДАЛЬНЫЕ ОКНА ДЛЯ ПОЛЬЗОВАТЕЛЕЙ ==================== -->

    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('addUserModal')">&times;</span>
            <h3><i class="fas fa-user-plus"></i> Добавить пользователя</h3>
            <form method="POST">
                <input type="text" name="username" placeholder="Никнейм *" required>
                <input type="email" name="email" placeholder="Email *" required>
                <input type="password" name="password" placeholder="Пароль * (мин. 6 символов)" required>
                <input type="text" name="full_name" placeholder="Полное имя">
                <input type="tel" name="phone" id="phoneAddUser" placeholder="Телефон">
                <textarea name="address" placeholder="Адрес" rows="2"></textarea>
                <select name="role">
                    <option value="user">Пользователь</option>
                    <option value="admin">Администратор</option>
                </select>
                <button type="submit" name="add_user" class="btn-update" style="width: 100%; padding: 12px;">Добавить пользователя</button>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('editUserModal')">&times;</span>
            <h3><i class="fas fa-user-edit"></i> Редактировать пользователя</h3>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="text" name="username" id="edit_username" placeholder="Никнейм *" required>
                <input type="email" name="email" id="edit_email" placeholder="Email *" required>
                <input type="text" name="full_name" id="edit_full_name" placeholder="Полное имя">
                <input type="tel" name="phone" id="edit_phone" placeholder="Телефон">
                <textarea name="address" id="edit_address" placeholder="Адрес" rows="2"></textarea>
                <select name="role" id="edit_role">
                    <option value="user">Пользователь</option>
                    <option value="admin">Администратор</option>
                </select>
                <button type="submit" name="update_user" class="btn-update" style="width: 100%; padding: 12px;">Сохранить изменения</button>
            </form>
        </div>
    </div>

    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('resetPasswordModal')">&times;</span>
            <h3><i class="fas fa-key"></i> Сброс пароля</h3>
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="user_id" id="reset_user_id">
                <p id="reset_username" style="margin-bottom: 15px; color: #FF4343;"></p>
                <input type="password" name="new_password" placeholder="Новый пароль (мин. 6 символов)" required>
                <button type="submit" name="reset_user_password" class="btn-update" style="width: 100%; padding: 12px;">Сменить пароль</button>
            </form>
        </div>
    </div>

    <!-- ==================== МОДАЛЬНЫЕ ОКНА ДЛЯ ТОВАРОВ ==================== -->

    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('addProductModal')">&times;</span>
            <h3><i class="fas fa-plus-circle"></i> Добавить товар</h3>
            <form method="POST">
                <input type="text" name="name" placeholder="Название товара" required>
                <select name="category_id" required>
                    <option value="">Выберите категорию</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="price" placeholder="Цена" step="0.01" required>
                <textarea name="description" placeholder="Краткое описание" rows="2"></textarea>
                <textarea name="long_description" placeholder="История создания" rows="3"></textarea>
                <input type="text" name="origin" placeholder="Место происхождения">
                <input type="text" name="material" placeholder="Материал">
                <input type="number" name="stock" placeholder="Количество на складе" value="0">
                <input type="text" name="image_url" placeholder="Имя файла (например: product.jpg)">
                <button type="submit" name="add_product" class="btn-update" style="width: 100%; padding: 12px;">Добавить товар</button>
            </form>
        </div>
    </div>

    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('editProductModal')">&times;</span>
            <h3><i class="fas fa-edit"></i> Редактировать товар</h3>
            <form method="POST" id="editProductForm">
                <input type="hidden" name="product_id" id="edit_product_id">
                <input type="text" name="name" id="edit_name" placeholder="Название товара" required>
                <select name="category_id" id="edit_product_category_id" required>
                    <option value="">Выберите категорию</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="price" id="edit_price" placeholder="Цена" step="0.01" required>
                <textarea name="description" id="edit_description" placeholder="Краткое описание" rows="2"></textarea>
                <textarea name="long_description" id="edit_long_description" placeholder="История создания" rows="3"></textarea>
                <input type="text" name="origin" id="edit_origin" placeholder="Место происхождения">
                <input type="text" name="material" id="edit_material" placeholder="Материал">
                <input type="number" name="stock" id="edit_stock" placeholder="Количество на складе">
                <input type="text" name="image_url" id="edit_image_url" placeholder="Имя файла изображения">
                <button type="submit" name="update_product" class="btn-update" style="width: 100%; padding: 12px;">Сохранить изменения</button>
            </form>
        </div>
    </div>

    <!-- ==================== МОДАЛЬНЫЕ ОКНА ДЛЯ КАТЕГОРИЙ ==================== -->

    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('addCategoryModal')">&times;</span>
            <h3><i class="fas fa-plus-circle"></i> Добавить категорию</h3>
            <form method="POST">
                <input type="text" name="name" placeholder="Название категории" required>
                <input type="text" name="slug" placeholder="Slug (латиницей, например: new-category)" required>
                <textarea name="description" placeholder="Описание категории" rows="3"></textarea>
                <button type="submit" name="add_category" class="btn-update" style="width: 100%; padding: 12px;">Добавить категорию</button>
            </form>
        </div>
    </div>

    <!-- Модальное окно редактирования категории -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('editCategoryModal')">&times;</span>
            <h3><i class="fas fa-edit"></i> Редактировать категорию</h3>
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_category_id">
                <input type="hidden" name="active_table" value="categories">

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: #C4C4C4; font-size: 13px;">Название категории</label>
                    <input type="text" name="name" id="edit_cat_name" placeholder="Название категории" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: #C4C4C4; font-size: 13px;">Slug (латиницей)</label>
                    <input type="text" name="slug" id="edit_cat_slug" placeholder="Slug" required style="width: 100%; background: #000000; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 10px 12px; color: white;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: #C4C4C4; font-size: 13px;">Описание категории</label>
                    <textarea name="description" id="edit_cat_description" placeholder="Описание категории" rows="3" style="width: 100%;"></textarea>
                </div>

                <button type="submit" name="update_category" class="btn-update" style="width: 100%; padding: 12px;">Сохранить изменения</button>
            </form>
        </div>
    </div>

    <script>
        // // Поиск товаров с автоподстановкой для корзины
        // const productSearchInput = document.getElementById('productSearchInput');
        // const productSuggestions = document.getElementById('productSuggestions');
        // const selectedProductId = document.getElementById('selectedProductId');

        // if (productSearchInput) {
        //     // Загружаем список всех товаров при загрузке страницы
        //     let allProducts = [];

        //     // Получаем список товаров через AJAX
        //     fetch('get_products_list.php')
        //         .then(response => response.json())
        //         .then(data => {
        //             allProducts = data;
        //         })
        //         .catch(error => console.error('Ошибка загрузки товаров:', error));

        //     // Поиск при вводе текста
        //     productSearchInput.addEventListener('input', function() {
        //         const searchText = this.value.toLowerCase().trim();

        //         if (searchText.length < 2) {
        //             productSuggestions.style.display = 'none';
        //             return;
        //         }

        //         // Фильтруем товары по введенному тексту
        //         const filtered = allProducts.filter(product =>
        //             product.name.toLowerCase().includes(searchText)
        //         );

        //         if (filtered.length > 0) {
        //             showSuggestions(filtered);
        //         } else {
        //             productSuggestions.style.display = 'none';
        //         }
        //     });

        //     function showSuggestions(products) {
        //         productSuggestions.innerHTML = '';
        //         products.forEach(product => {
        //             const div = document.createElement('div');
        //             div.textContent = product.name;
        //             div.style.padding = '10px 15px';
        //             div.style.cursor = 'pointer';
        //             div.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
        //             div.style.color = '#FFFFFF';
        //             div.onmouseover = function() {
        //                 this.style.background = 'rgba(255,255,255,0.1)';
        //             };
        //             div.onmouseout = function() {
        //                 this.style.background = 'transparent';
        //             };
        //             div.onclick = function() {
        //                 productSearchInput.value = product.name;
        //                 selectedProductId.value = product.id;
        //                 productSuggestions.style.display = 'none';
        //                 // Применяем фильтр
        //                 applyProductFilter();
        //             };
        //             productSuggestions.appendChild(div);
        //         });
        //         productSuggestions.style.display = 'block';
        //     }

        //     function applyProductFilter() {
        //         const url = new URL(window.location.href);
        //         const productId = selectedProductId.value;
        //         if (productId && productId != 0) {
        //             url.searchParams.set('filter_product', productId);
        //         } else {
        //             url.searchParams.delete('filter_product');
        //         }
        //         window.location.href = url.toString();
        //     }

        //     // Скрываем подсказки при клике вне области
        //     document.addEventListener('click', function(e) {
        //         if (!productSearchInput.contains(e.target) && !productSuggestions.contains(e.target)) {
        //             productSuggestions.style.display = 'none';
        //         }
        //     });

        //     // Если уже выбран товар, показываем его название в поле
        //     const currentProductId = selectedProductId.value;
        //     if (currentProductId && currentProductId != 0) {
        //         // Загружаем название товара по ID
        //         fetch(`get_product_name.php?id=${currentProductId}`)
        //             .then(response => response.json())
        //             .then(data => {
        //                 if (data.name) {
        //                     productSearchInput.value = data.name;
        //                 }
        //             })
        //             .catch(error => console.error('Ошибка:', error));
        //     }
        // }
        
        // Фильтры
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                const url = new URL(window.location.href);
                if (this.value && this.value != 0) url.searchParams.set('filter_category', this.value);
                else url.searchParams.delete('filter_category');
                window.location.href = url.toString();
            });
        }

        // Фильтр по товару в корзине
        const productFilterCart = document.getElementById('productFilterCart');
        if (productFilterCart) {
            productFilterCart.addEventListener('change', function() {
                const url = new URL(window.location.href);
                if (this.value && this.value != '') {
                    url.searchParams.set('filter_product', this.value);
                } else {
                    url.searchParams.delete('filter_product');
                }
                window.location.href = url.toString();
            });
        }

        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                const url = new URL(window.location.href);
                if (this.value) url.searchParams.set('filter_status', this.value);
                else url.searchParams.delete('filter_status');
                window.location.href = url.toString();
            });
        }

        const roleFilter = document.getElementById('roleFilter');
        if (roleFilter) {
            roleFilter.addEventListener('change', function() {
                const url = new URL(window.location.href);
                if (this.value) url.searchParams.set('filter_role', this.value);
                else url.searchParams.delete('filter_role');
                window.location.href = url.toString();
            });
        }

        const ratingFilter = document.getElementById('ratingFilter');
        if (ratingFilter) {
            ratingFilter.addEventListener('change', function() {
                const url = new URL(window.location.href);
                if (this.value && this.value != 0) url.searchParams.set('filter_rating', this.value);
                else url.searchParams.delete('filter_rating');
                window.location.href = url.toString();
            });
        }

        const emailVerifiedFilter = document.getElementById('emailVerifiedFilter');
        if (emailVerifiedFilter) {
            emailVerifiedFilter.addEventListener('change', function() {
                const url = new URL(window.location.href);
                if (this.value !== '-1') url.searchParams.set('filter_email_verified', this.value);
                else url.searchParams.delete('filter_email_verified');
                window.location.href = url.toString();
            });
        }

        // Фильтр по никнейму для пользователей
        const usernameFilterUsers = document.getElementById('usernameFilterUsers');
        if (usernameFilterUsers) {
            let timeoutUsers = null;
            usernameFilterUsers.addEventListener('input', function() {
                clearTimeout(timeoutUsers);
                timeoutUsers = setTimeout(() => {
                    const url = new URL(window.location.href);
                    if (this.value.trim() !== '') url.searchParams.set('filter_username', this.value.trim());
                    else url.searchParams.delete('filter_username');
                    window.location.href = url.toString();
                }, 500);
            });
        }

        // Фильтр по никнейму для корзины/избранного
        const usernameFilterCart = document.getElementById('usernameFilterCart');
        if (usernameFilterCart) {
            let timeoutCart = null;
            usernameFilterCart.addEventListener('input', function() {
                clearTimeout(timeoutCart);
                timeoutCart = setTimeout(() => {
                    const url = new URL(window.location.href);
                    if (this.value.trim() !== '') url.searchParams.set('filter_username', this.value.trim());
                    else url.searchParams.delete('filter_username');
                    window.location.href = url.toString();
                }, 500);
            });
        }

        // Маски для телефона
        $(document).ready(function() {
            $('#phoneAddUser').mask('+7 (000) 000-00-00');
        });

        function applyPhoneMask() {
            $('#edit_phone').mask('+7 (000) 000-00-00');
        }

        // Модальные окна
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openEditUserModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_address').value = user.address || '';
            document.getElementById('edit_role').value = user.role;
            openModal('editUserModal');
            setTimeout(function() {
                $('#edit_phone').mask('+7 (000) 000-00-00');
            }, 100);
        }

        function openResetPasswordModal(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').innerHTML = 'Пользователь: <strong>' + username + '</strong>';
            openModal('resetPasswordModal');
        }

        function openEditProductModal(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_product_category_id').value = product.category_id;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_long_description').value = product.long_description || '';
            document.getElementById('edit_origin').value = product.origin || '';
            document.getElementById('edit_material').value = product.material || '';
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('edit_image_url').value = product.image_url || '';
            openModal('editProductModal');
        }

        function openEditCategoryModal(button) {
            // Получаем данные из data-атрибутов кнопки
            var categoryId = button.getAttribute('data-id');
            var categoryName = button.getAttribute('data-name');
            var categorySlug = button.getAttribute('data-slug');
            var categoryDescription = button.getAttribute('data-description');

            console.log('Редактирование категории:', {
                id: categoryId,
                name: categoryName,
                slug: categorySlug,
                description: categoryDescription
            });

            // Устанавливаем значения в модальном окне
            var idField = document.getElementById('edit_category_id');
            var nameField = document.getElementById('edit_cat_name');
            var slugField = document.getElementById('edit_cat_slug');
            var descField = document.getElementById('edit_cat_description');

            if (idField) idField.value = categoryId;
            if (nameField) nameField.value = categoryName;
            if (slugField) slugField.value = categorySlug;
            if (descField) descField.value = categoryDescription || '';

            // Дополнительная проверка
            console.log('Заполнено поле slug:', slugField ? slugField.value : 'поле не найдено');

            if (!categoryId || categoryId == '') {
                alert('Ошибка: ID категории не найден!');
                return;
            }

            openModal('editCategoryModal');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>

</html>