<?php
require __DIR__ . '/_bootstrap.php';
$user   = api_auth();
$db     = api_db();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

  case 'getStoreInfo':                       // → #storeBody / #storeBalance / #storeHistoryList
    $st = $db->prepare('SELECT points FROM users WHERE id=?');
    $st->execute([$user['id']]);
    $balance = (int)$st->fetchColumn();
    $catalog = $db->query(
        'SELECT id,name,description AS `desc`,type,
                point_cost AS pointCost, credit_amount AS creditAmount, image
         FROM store_catalog WHERE active=1 ORDER BY point_cost'
    )->fetchAll();
    $r = $db->prepare(
        'SELECT r.status,r.point_cost AS pointCost,r.payout,
                c.name AS itemName,c.type AS itemType
         FROM store_redemptions r JOIN store_catalog c ON c.id=r.item_id
         WHERE r.user_id=? ORDER BY r.created_at DESC LIMIT 20'
    );
    $r->execute([$user['id']]);
    api_json([
        'status' => 'success', 'balance' => $balance, 'currency' => 'MYR',
        'catalog' => $catalog, 'redemptions' => $r->fetchAll(),
        'previewMode' => false, 'requireMaintenance' => false, 'maintenanceMet' => true,
    ]);

  case 'checkin':                            // → btnCheckin
    api_csrf_check();
    $st = $db->prepare('SELECT id FROM checkins WHERE user_id=? AND DATE(created_at)=CURDATE()');
    $st->execute([$user['id']]);
    if ($st->fetch()) api_fail('Sudah check-in hari ini');
    $rewarded = (bool)random_int(0,1);
    $pts = $rewarded ? 10 : 0;
    $db->beginTransaction();
    $db->prepare('INSERT INTO checkins (user_id,points,created_at) VALUES (?,?,NOW())')
       ->execute([$user['id'], $pts]);
    if ($pts) $db->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$pts, $user['id']]);
    $db->commit();
    api_json(['success'=>true,'rewarded'=>$rewarded,
              'message'=>$rewarded ? "Check-in +{$pts} pts" : 'Check-in berjaya']);

  case 'claim':                              // → btn-{type}
    api_csrf_check();
    $type = $_POST['claimType'] ?? '';
    if (!in_array($type, ['daily','weekly','monthly'], true)) api_fail('Jenis tidak sah');
    $st = $db->prepare('SELECT id FROM claims WHERE user_id=? AND claim_type=? AND status IN ("claimed","pending")');
    $st->execute([$user['id'], $type]);
    if ($st->fetch()) api_fail('Sudah diclaim');
    $db->prepare('INSERT INTO claims (user_id,claim_type,status,created_at) VALUES (?,?,"claimed",NOW())')
       ->execute([$user['id'], $type]);
    api_json(['success'=>true,'message'=>'Bonus diclaim']);

  case 'redeem':                             // → .btn-redeem[data-item]
    api_csrf_check();
    $itemId = (int)($_POST['itemId'] ?? 0);
    $nonce  = $_POST['nonce'] ?? '';
    if ($itemId <= 0 || !$nonce) api_fail('Data tidak lengkap');

    $st = $db->prepare('SELECT status FROM store_redemptions WHERE user_id=? AND item_id=? AND nonce=?');
    $st->execute([$user['id'], $itemId, $nonce]);
    if ($exist = $st->fetch()) {
        api_json(['success'=>true,'pending'=>$exist['status']==='pending','message'=>'Sudah diproses']);
    }

    $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT point_cost,credit_amount FROM store_catalog WHERE id=? AND active=1');
        $st->execute([$itemId]);
        $item = $st->fetch() ?: throw new RuntimeException('Item tiada');
        $st = $db->prepare('SELECT points FROM users WHERE id=? FOR UPDATE');
        $st->execute([$user['id']]);
        $bal = (int)$st->fetchColumn();
        if ($bal < $item['point_cost']) api_fail('Poin tidak cukup');
        $db->prepare('UPDATE users SET points=points-? WHERE id=?')->execute([$item['point_cost'],$user['id']]);
        $db->prepare('INSERT INTO store_redemptions (user_id,item_id,point_cost,payout,status,nonce,created_at)
                      VALUES (?,?,?,?,"pending",?,NOW())')
           ->execute([$user['id'],$itemId,$item['point_cost'],$item['credit_amount'],$nonce]);
        $db->commit();
        api_json(['success'=>true,'balance'=>$bal-$item['point_cost'],'message'=>'Berjaya ditebus']);
    } catch (Throwable $e) { $db->rollBack(); api_fail($e->getMessage(),500); }

  case 'setBirthday':                        // → bdayInlineForm
    api_csrf_check();
    $m = (int)($_POST['month'] ?? 0); $d = (int)($_POST['day'] ?? 0);
    if ($m < 1 || $m > 12 || $d < 1 || $d > 31) api_fail('Tarikh tidak sah');
    $db->prepare('UPDATE users SET birthday_month=?,birthday_day=? WHERE id=?')
       ->execute([$m,$d,$user['id']]);
    api_json(['success'=>true,'message'=>'Tarikh disimpan']);

  default: api_fail('Unknown action', 404);
}