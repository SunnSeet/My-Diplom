<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

$path = $_GET['path'] ?? '';

switch ($path) {
    case 'products':
        $stmt = $pdo->query("SELECT id, name, price, image_url FROM products LIMIT 20");
        echo json_encode($stmt->fetchAll());
        break;

    case 'product':
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
        break;

    case 'categories':
        $stmt = $pdo->query("SELECT * FROM categories");
        echo json_encode($stmt->fetchAll());
        break;

    default:
        echo json_encode([
            'status' => 'ok',
            'message' => 'SUNNSET API',
            'endpoints' => [
                '/api/?path=products' => 'Список товаров',
                '/api/?path=product&id=1' => 'Товар по ID',
                '/api/?path=categories' => 'Список категорий'
            ]
        ]);
        break;
}
