<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Необходимо войти в систему']);
    exit();
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$qualities = isset($_POST['qualities']) ? $_POST['qualities'] : '';
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Некорректная оценка']);
    exit();
}

$stmt = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, qualities, comment) VALUES (?, ?, ?, ?, ?)");
if ($stmt->execute([$_SESSION['user_id'], $product_id, $rating, $qualities, $comment])) {
    echo json_encode(['success' => true, 'message' => 'Отзыв добавлен! Спасибо за вашу оценку']);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка при добавлении отзыва']);
}