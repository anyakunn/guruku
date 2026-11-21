<?php
require __DIR__ . '/_bootstrap.php';
$cfg  = require __DIR__ . '/config.php';
$user = api_auth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = api_db()->prepare(
        'SELECT cash, min_bet_current, min_bet_total, vip_level, vip_subtitle, vip_current, vip_next
         FROM users WHERE id = ?'
    );
    $st->execute([$user['id']]);
    $r = $st->fetch() ?: [];
    $pct = ($r['min_bet_total'] > 0)
        ? min(100, (int)round($r['min_bet_current'] / $r['min_bet_total'] * 100)) : 0;

    api_json([
        'status'  => 'success',
        'cash'    => money((float)$r['cash']),
        'limits'  => [
            'minDeposit'  => money($cfg['limits']['minDeposit']),
            'minWithdraw' => money($cfg['limits']['minWithdraw']),
            'maxWithdraw' => money($cfg['limits']['maxWithdraw']),
        ],
        'rollover' => [
            'current' => money((float)$r['min_bet_current']),
            'total'   => money((float)$r['min_bet_total']),
            'percent' => $pct,
        ],
        'vip' => [
            'level'    => $r['vip_level'],
            'subtitle' => $r['vip_subtitle'],
            'current'  => money((float)$r['vip_current']),
            'next'     => money((float)$r['vip_next']),
        ],
    ]);
}

/* POST = UI.submit() → deposit / withdraw */
api_csrf_check();
$type = $_POST['type'] ?? '';
$amt  = (float)($_POST['amount'] ?? 0);
if ($amt <= 0) api_fail('Jumlah tidak sah');

$db = api_db();
$db->beginTransaction();
try {
    $st = $db->prepare('SELECT cash FROM users WHERE id = ? FOR UPDATE');
    $st->execute([$user['id']]);
    $cash = (float)$st->fetchColumn();

    if ($type === 'deposit') {
        if ($amt < $cfg['limits']['minDeposit']) api_fail('Min deposit ' . money($cfg['limits']['minDeposit']));
        $new = $cash + $amt;
        $db->prepare('UPDATE users SET cash=?, min_bet_current=LEAST(min_bet_total,min_bet_current+?) WHERE id=?')
           ->execute([$new, $amt, $user['id']]);
    } elseif ($type === 'withdraw') {
        if ($amt < $cfg['limits']['minWithdraw']) api_fail('Min withdraw ' . money($cfg['limits']['minWithdraw']));
        if ($amt > $cfg['limits']['maxWithdraw']) api_fail('Max withdraw ' . money($cfg['limits']['maxWithdraw']));
        if ($amt > $cash) api_fail('Baki tidak mencukupi');
        $new = $cash - $amt;
        $db->prepare('UPDATE users SET cash=? WHERE id=?')->execute([$new, $user['id']]);
    } else {
        api_fail('Jenis tidak sah');
    }

    $db->prepare('INSERT INTO wallet_ledger (user_id,type,amount,created_at) VALUES (?,?,?,NOW())')
       ->execute([$user['id'], $type, $amt]);
    $db->commit();
    api_json(['status' => 'success', 'amount' => money($amt), 'cash' => money($new)]);
} catch (Throwable $e) {
    $db->rollBack();
    api_fail('Gagal: ' . $e->getMessage(), 500);
}