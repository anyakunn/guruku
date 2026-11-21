<?php
require __DIR__ . '/_bootstrap.php';
$action = $_GET['action'] ?? '';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile   = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!preg_match('/^60\d{9,11}$/', $mobile)) api_fail('Format mobile tidak sah');
    if (strlen($password) < 6 || strlen($password) > 20) api_fail('Kata laluan 6-20 aksara');

    $st = api_db()->prepare('SELECT id,username,name,password_hash FROM users WHERE mobile=? LIMIT 1');
    $st->execute([$mobile]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) api_fail('Kelayakan tidak sah', 401);

    $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'name' => $u['name']];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    api_json(['status' => 'success', 'user' => $_SESSION['user'], 'csrf' => $_SESSION['csrf']]);
}

if ($action === 'logout') {
    $_SESSION = []; session_destroy();
    api_json(['status' => 'success']);
}

if ($action === 'me') {
    if (empty($_SESSION['user'])) api_fail('Guest', 401);
    api_json(['status' => 'success', 'user' => $_SESSION['user'], 'csrf' => $_SESSION['csrf']]);
}

api_fail('Unknown action', 404);