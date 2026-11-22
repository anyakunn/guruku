<?php
// ============================================
// API2 — PHP Agent
// Terima dari API1 -> forward ke API3 -> balik
// ============================================

// --- HEADER ---
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");        // test dulu
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- 1. Terima data dari API1 ---
$input = json_decode(file_get_contents('php://input'), true);
$nama  = $input['nama'] ?? '';

// --- 2. Forward ke API3 (server-ke-server, tiada CORS) ---
$ch = curl_init('https://letup33.co/api/v1/index.php');   // <-- isi URL API3
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['nama' => $nama]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- 3. Balik ke API1 ---
http_response_code($httpCode ?: 502);
echo $response;   // terus pass-through respons API3
