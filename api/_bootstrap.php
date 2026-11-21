<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Lax',
]);

/* JSON output — selaras dengan semua JSON parse dalam index.js & action.js */
function api_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function api_fail(string $msg, int $code = 400, array $x = []): void {
    api_json(array_merge(['status' => 'error', 'msg' => $msg], $x), $code);
}
function api_auth(): array {
    if (empty($_SESSION['user'])) api_fail('Unauthorized', 401);
    return $_SESSION['user'];
}
function api_csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) api_fail('Invalid CSRF', 403);
}

function api_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/config.php';
        $pdo = new PDO(
            "mysql:host={$c['db']['host']};dbname={$c['db']['name']};charset=utf8mb4",
            $c['db']['user'], $c['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

/* Sama format dgn fmtMoney() dalam index.js → el tak 'flash' bila hydrate */
function money(float $n): string {
    return 'RM ' . number_format($n, 2, '.', ',');
}