<?php
session_start();
header('Content-Type: application/json');

$logged_in = isset($_SESSION['user_id']);
echo json_encode(['logged_in' => $logged_in]);
