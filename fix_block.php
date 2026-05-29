<?php
require_once 'config.php';

// Очищаем все попытки входа
$pdo->exec("DELETE FROM login_attempts");

// Проверяем, что очистилось
$stmt = $pdo->query("SELECT COUNT(*) as count FROM login_attempts");
$result = $stmt->fetch();

echo "✅ Записи очищены. Количество записей: " . $result['count'] . "<br>";
echo "<a href='login.php'>Вернуться на страницу входа</a>";
