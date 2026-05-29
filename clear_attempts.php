<?php
require_once 'config.php';

// Только для администратора
if (!isAdmin()) {
    die('Доступ запрещен');
}

// Очищаем все попытки входа
$pdo->exec("DELETE FROM login_attempts");
echo "✅ Все попытки входа очищены. <a href='login.php'>Вернуться к входу</a>";
