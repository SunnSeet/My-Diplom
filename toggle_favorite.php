<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Необходимо войти в систему']);
    exit();
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID товара']);
    exit();
}

// Проверяем, есть ли товар в избранном
$stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
$stmt->execute([$_SESSION['user_id'], $product_id]);
$exists = $stmt->fetch();

if ($exists) {
    // Удаляем из избранного
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Удалено из избранного']);
} else {
    // Добавляем в избранное
    $stmt = $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Добавлено в избранное']);
}