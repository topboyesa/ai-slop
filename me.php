<?php
// Tells the main page who is logged in, so the menu can show the username and a logout button.
require __DIR__ . '/config.php';
start_session();

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!empty($_SESSION['user_id'])) {
    echo json_encode(['user' => $_SESSION['username'], 'csrf' => csrf_token()]);
} else {
    echo json_encode(['user' => null]);
}
