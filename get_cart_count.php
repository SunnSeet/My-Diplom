<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isLoggedIn()) {
    $count = getCartCount($_SESSION['user_id']);
    echo json_encode(['count' => $count]);
} else {
    echo json_encode(['count' => 0]);
}
?>