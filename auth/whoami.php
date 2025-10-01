<?php
require_once __DIR__ . '/session.php';
header('Content-Type: text/plain; charset=utf-8');
echo "session_status=" . session_status() . PHP_EOL;
echo "session_name=" . session_name() . PHP_EOL;
echo "user_id=" . (isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0) . PHP_EOL;
echo "cookie_path_hint=/ (required)" . PHP_EOL;