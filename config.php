<?php
session_start();

// $max_requests = 30;      // максимум запросов
// $time_window = 60;       // за 60 секунд

// if (!isset($_SESSION['requests'])) {
//     $_SESSION['requests'] = [];
// }

// $current_time = time();

// // Удаляем старые записи (старше $time_window секунд)
// $_SESSION['requests'] = array_filter(
//     $_SESSION['requests'],
//     function ($timestamp) use ($current_time, $time_window) {
//         return ($current_time - $timestamp) < $time_window;
//     }
// );

// // Если превышен лимит — блокируем
// if (count($_SESSION['requests']) >= $max_requests) {
//     http_response_code(429);
//     die('⚠️ Слишком много запросов. Подождите немного.');
// }

// // Добавляем текущий запрос
// $_SESSION['requests'][] = $current_time;

$host = 'localhost';
$dbname = 'sunset_shop';
$username = 'root';
$password = '';

// Защита от XSS - функция очистки ввода
function sanitizeInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Защита от XSS - для вывода данных
function escapeOutput($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// DDOS защита - проверка лимита попыток входа
function checkLoginAttempts($ip)
{
    global $pdo;

    // Очищаем старые записи (старше 1 часа)
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();

    // Получаем время последней попытки
    $stmt = $pdo->prepare("SELECT attempt_time FROM login_attempts WHERE ip = ? ORDER BY attempt_time DESC LIMIT 1");
    $stmt->execute([$ip]);
    $last_attempt = $stmt->fetch();

    if ($last_attempt) {
        $last_time = strtotime($last_attempt['attempt_time']);
        $now = time();
        $minutes_passed = floor(($now - $last_time) / 60);

        // Если последняя попытка была меньше минуты назад и было 5 ошибок - блокируем
        if ($minutes_passed < 1) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$ip]);
            $result = $stmt->fetch();

            if ($result['attempts'] >= 5) {
                return false; // Заблокирован
            }
        }
    }

    return true;
}

// Получение информации о блокировке
function getBlockInfo($ip)
{
    global $pdo;

    // Удаляем всё, что старше 1 минуты
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $stmt->execute();

    // Считаем попытки за последнюю минуту
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $result = $stmt->fetch();
    $attempts = (int)($result['attempts'] ?? 0);

    $is_blocked = false;
    $wait_seconds = 0;

    if ($attempts >= 5) {
        $is_blocked = true;
        // Получаем время самой первой из 5 попыток
        $stmt = $pdo->prepare("SELECT attempt_time FROM login_attempts WHERE ip = ? ORDER BY attempt_time ASC LIMIT 1 OFFSET 4");
        $stmt->execute([$ip]);
        $first_attempt = $stmt->fetch();

        if ($first_attempt) {
            $block_until = strtotime($first_attempt['attempt_time']) + 60;
            $now = time();
            $wait_seconds = $block_until - $now;
            if ($wait_seconds < 0) $wait_seconds = 0;
            if ($wait_seconds > 60) $wait_seconds = 60;
        }
    }

    return [
        'is_blocked' => $is_blocked,
        'wait_seconds' => $wait_seconds,
        'attempts' => $attempts,
        'remaining_attempts' => 5 - $attempts
    ];
}

function recordLoginAttempt($ip, $success = false)
{
    global $pdo;

    if (!$success) {
        // Удаляем старые
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmt->execute();

        // Добавляем новую попытку
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, attempt_time, success) VALUES (?, NOW(), 0)");
        $stmt->execute([$ip]);
    } else {
        // При успешном входе - чистим всё
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?");
        $stmt->execute([$ip]);
    }
}
function getRemainingAttempts($ip)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                           WHERE ip = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$ip]);
    $result = $stmt->fetch();
    $attempts = (int)($result['attempts'] ?? 0);
    $remaining = 5 - $attempts;
    return $remaining < 0 ? 0 : $remaining;
}

// CSRF защита
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
        return true;
    }
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user && $user['role'] == 'admin') {
            $_SESSION['role'] = 'admin';
            return true;
        }
    }
    return false;
}

function getUserData($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getCartCount($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

function getFavoriteCount($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM favorites WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

require_once 'send_mail.php';

function isUsernameUnique($username, $exclude_id = null)
{
    global $pdo;
    if ($exclude_id) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $exclude_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
    }
    return !$stmt->fetch();
}

function isEmailUnique($email, $exclude_id = null)
{
    global $pdo;
    if ($exclude_id) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $exclude_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
    }
    return !$stmt->fetch();
}
function isInCart($user_id, $product_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    return $stmt->fetch() ? true : false;
}
function isInFavorites($user_id, $product_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    return $stmt->fetch() ? true : false;
}
