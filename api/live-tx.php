<?php
require __DIR__ . '/_bootstrap.php';
$cfg = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_fail('POST only', 405);
$mId = $_POST['mId'] ?? '';
if (!in_array($mId, array_column($cfg['liveTx']['sources'], 'mId'), true)) api_fail('Invalid mId');

$cache = sys_get_temp_dir() . "/livetx_{$mId}.json";
if (is_file($cache) && time() - filemtime($cache) < 5) {
    header('Content-Type: application/json');
    readfile($cache);
    exit;
}

$ch = curl_init('https://upstream.example/getLiveStat.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['mId' => $mId]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 4,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code === 200 && $body) file_put_contents($cache, $body);

http_response_code($code ?: 502);
header('Content-Type: application/json');
echo $body ?: '{"status":"FAIL"}';