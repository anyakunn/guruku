<?php
session_start();
$cfg = require __DIR__ . '/api/config.php';
$isLogin = !empty($_SESSION['user']);
$user    = $_SESSION['user'] ?? null;
$csrf    = $_SESSION['csrf'] ?? '';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <!-- ...head sama... -->
  <script id="lp33-boot-config" type="application/json"><?= json_encode([
    'endpoints' => $cfg['endpoints'],
    'liveTx'    => $cfg['liveTx'],
    'platform'  => $cfg['platform'],
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

  <!-- HANYA TAMBAH 1 baris: CSRF untuk fetch POST -->
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
</head>

<!-- BODY: class di-render dari PHP, bukan JS -->
<body class="<?= $isLogin ? 'ahw-after-login' : '' ?>">

<!-- Header pill — nilai awal di-render PHP supaya no-flash -->
<span class="amt" id="letup33Amt"><?= $isLogin ? 'RM0.00' : 'RM0.00' ?></span>

<!-- Guest greeting awal -->
<h1>👋 <span id="heroUsername"><?= $user ? htmlspecialchars($user['name']) : '…' ?></span></h1>
<p>Account ID: <span class="acct" id="heroAccount"><?= $user ? htmlspecialchars($user['username']) : '…' ?></span></p>

<!-- Baki markup & CSS kekal 100% — tiada ID berubah -->