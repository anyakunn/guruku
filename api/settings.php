<?php
require __DIR__ . '/_bootstrap.php';
$user = api_auth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = api_db()->prepare('SELECT cash,bank,bank_acc_name,bank_acc_number FROM users WHERE id=?');
    $st->execute([$user['id']]);
    $r = $st->fetch() ?: [];
    api_json([
        'status' => 'success',
        'wallet' => money((float)$r['cash']),
        'bank'   => [
            'bank'      => $r['bank'],
            'accName'   => $r['bank_acc_name'],
            'accNumber' => $r['bank_acc_number'],
            'masked'    => '**** ' . substr((string)$r['bank_acc_number'], -4),
        ],
    ]);
}

api_csrf_check();
$action = $_POST['action'] ?? '';

if ($action === 'changeBank') {
    $bank = trim($_POST['bank'] ?? '');
    $name = trim($_POST['accName'] ?? '');
    $num  = preg_replace('/\D/', '', $_POST['accNumber'] ?? '');
    if (!$bank || !$name || strlen($num) < 8) api_fail('Data tidak lengkap');
    api_db()->prepare('UPDATE users SET bank=?,bank_acc_name=?,bank_acc_number=? WHERE id=?')
            ->execute([$bank,$name,$num,$user['id']]);
    api_json(['success'=>true,'message'=>'Bank dikemaskini']);
}
if ($action === 'changePassword') {
    $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? '';
    if (strlen($new) < 6 || strlen($new) > 20) api_fail('Kata laluan 6-20 aksara');
    $st = api_db()->prepare('SELECT password_hash FROM users WHERE id=?');
    $st->execute([$user['id']]);
    if (!password_verify($cur, $st->fetchColumn())) api_fail('Kata laluan semasa salah');
    api_db()->prepare('UPDATE users SET password_hash=? WHERE id=?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
    api_json(['success'=>true,'message'=>'Kata laluan dikemaskini']);
}
api_fail('Unknown action', 404);