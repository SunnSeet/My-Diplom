<?php
require_once 'config.php';

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$limit = 5;

$stmt = $pdo->prepare("SELECT r.*, u.username 
                       FROM reviews r 
                       JOIN users u ON r.user_id = u.id 
                       WHERE r.product_id = ? 
                       ORDER BY r.created_at DESC 
                       LIMIT ? OFFSET ?");
$stmt->execute([$product_id, $limit, $offset]);
$reviews = $stmt->fetchAll();

$html = '';
foreach ($reviews as $review) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $review['rating'] ? '★' : '☆';
    }
    $html .= '
    <div class="review-item">
        <div class="review-header">
            <strong>' . htmlspecialchars($review['username']) . '</strong>
            <span class="review-date">' . date('d.m.Y', strtotime($review['created_at'])) . '</span>
        </div>
        <div class="review-stars">' . $stars . '</div>
        <div class="review-qualities">Понравилось: ' . htmlspecialchars($review['qualities']) . '</div>
        <div class="review-comment">' . nl2br(htmlspecialchars($review['comment'])) . '</div>
    </div>';
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE product_id = ?");
$stmt->execute([$product_id]);
$total = $stmt->fetchColumn();
$has_more = ($offset + $limit) < $total;

echo json_encode(['html' => $html, 'has_more' => $has_more]);