<?php
// /admin/partner_action.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php'; // require_role()

require_role('admin'); // 최고관리자/관리자만

// 리다이렉트 헬퍼: 기존 err/msg 제거하고 새 파라미터만 부착
function back_to_list(string $qs = ''): void {
  $ref = $_SERVER['HTTP_REFERER'] ?? '/admin/partners.php';
  $parts = parse_url($ref);
  $scheme = $parts['scheme'] ?? '';
  $host   = $parts['host']   ?? '';
  $path   = $parts['path']   ?? '/admin/partners.php';
  $query  = [];
  if (!empty($parts['query'])) {
    parse_str($parts['query'], $query);
    unset($query['err'],$query['msg']);
  }
  if ($qs !== '') {
    $extra = [];
    parse_str($qs, $extra);
    $query = array_merge($query, $extra);
  }
  $to = ($scheme && $host) ? "$scheme://$host$path" : $path;
  if (!empty($query)) $to .= '?' . http_build_query($query);
  header('Location: ' . $to);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  back_to_list('err=method_not_allowed');
}

if (!csrf_verify($_POST['csrf'] ?? null)) {
  back_to_list('err=csrf');
}

$action = $_POST['action'] ?? '';
$appId  = (int)($_POST['id'] ?? 0);

if ($appId <= 0) back_to_list('err=bad_id');

$pdo = db();

// 신청서 로드
$st = $pdo->prepare("SELECT * FROM partner_applications WHERE id=:id LIMIT 1");
$st->execute([':id' => $appId]);
$app = $st->fetch(PDO::FETCH_ASSOC);
if (!$app) {
  back_to_list('err=not_found');
}

// users.id 찾기: 스키마에 user_id가 있으면 우선, 없으면 email 매칭
$userId = 0;
if (array_key_exists('user_id', $app) && !empty($app['user_id'])) {
  $userId = (int)$app['user_id'];
} else {
  $st = $pdo->prepare("SELECT id FROM users WHERE email=:email LIMIT 1");
  $st->execute([':email' => $app['email'] ?? '']);
  $userId = (int)($st->fetchColumn() ?: 0);
}
if ($userId <= 0) {
  back_to_list('err=user_not_found');
}

try {
  $pdo->beginTransaction();

  if ($action === 'approve') {
    // 1) 신청 상태 승인
    $pdo->prepare("UPDATE partner_applications SET status='approved', updated_at=CURRENT_TIMESTAMP WHERE id=:id")
        ->execute([':id'=>$appId]);

    // 2) 사용자 role 승격 (이미 partner/owner/admin이면 유지)
    $st = $pdo->prepare("SELECT role FROM users WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$userId]);
    $role = (string)$st->fetchColumn();
    if (!in_array($role, ['admin','owner','partner'], true)) {
      $pdo->prepare("UPDATE users SET role='partner', updated_at=CURRENT_TIMESTAMP WHERE id=:id")
          ->execute([':id'=>$userId]);
    }

    // 3) partner_profiles upsert
    //    - 실제 존재하는 컬럼만 업데이트 (환경차 대응)
    $cols = $pdo->query("SHOW COLUMNS FROM partner_profiles")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map(fn($r)=>$r['Field'], $cols);
    $now = date('Y-m-d H:i:s');

    // 신청서에서 가져올 후보 값들(컬럼명이 설치마다 달라서 COALESCE로 셋업)
    $store_name = $app['business_name']
      ?? ($app['businessName'] ?? ($app['name'] ?? ('Store #'.$userId)));

    $address_line1 = $app['store_address'] ?? ($app['business_address'] ?? '');
    $phone         = $app['contact_phone'] ?? ($app['phone'] ?? null);
    $lat           = isset($app['store_lat']) && $app['store_lat'] !== '' ? (float)$app['store_lat'] : null;
    $lng           = isset($app['store_lng']) && $app['store_lng'] !== '' ? (float)$app['store_lng'] : null;
    $place_id      = $app['store_place_id'] ?? null;
    $intro         = $app['intro'] ?? null;
    $hero          = $app['hero_image_url'] ?? null;

    // upsert 대상 맵 (실제 컬럼만 적용됨)
    $profile = [
      'user_id'        => $userId,
      'store_name'     => $store_name,
      'intro'          => $intro,
      'phone'          => $phone,
      'address_line1'  => $address_line1,
      'lat'            => $lat,
      'lng'            => $lng,
      'place_id'       => $place_id,
      'hero_image_url' => $hero,
      'is_published'   => 1,
      'updated_at'     => $now,
    ];
    // created_at은 insert 시에만
    $profileInsert = $profile + ['created_at' => $now];

    // 이미 존재?
    $exists = $pdo->prepare("SELECT id FROM partner_profiles WHERE user_id=:uid LIMIT 1");
    $exists->execute([':uid'=>$userId]);
    $pid = (int)($exists->fetchColumn() ?: 0);

    if ($pid === 0) {
      // INSERT (존재 컬럼만)
      $ins = [];
      foreach ($profileInsert as $k=>$v) {
        if (in_array($k, $colNames, true)) $ins[$k] = $v;
      }
      $cols = array_keys($ins);
      $phs  = array_map(fn($c)=>':'.$c, $cols);
      $sql  = "INSERT INTO partner_profiles (" . implode(',', $cols) . ") VALUES (" . implode(',', $phs) . ")";
      $stmt = $pdo->prepare($sql);
      foreach ($ins as $k=>$v) $stmt->bindValue(':'.$k, $v);
      $stmt->execute();
    } else {
      // UPDATE (존재 컬럼만)
      $upd = [];
      foreach ($profile as $k=>$v) {
        if ($k==='user_id') continue;
        if (in_array($k, $colNames, true)) $upd[$k] = $v;
      }
      $sets = [];
      foreach (array_keys($upd) as $k) $sets[] = "$k=:$k";
      if (!empty($sets)) {
        $sql = "UPDATE partner_profiles SET " . implode(',', $sets) . " WHERE user_id=:uid";
        $stmt = $pdo->prepare($sql);
        foreach ($upd as $k=>$v) $stmt->bindValue(':'.$k, $v);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
      }
    }

    $pdo->commit();
    back_to_list('msg=approved');

  } elseif ($action === 'reject') {
    // 반려
    $pdo->prepare("UPDATE partner_applications SET status='rejected', updated_at=CURRENT_TIMESTAMP WHERE id=:id")
        ->execute([':id'=>$appId]);
    $pdo->commit();
    back_to_list('msg=rejected');

  } else {
    $pdo->rollBack();
    back_to_list('err=unknown_action');
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // 개발 중에는 에러 이유도 함께
  back_to_list('err=exception&reason=' . urlencode($e->getMessage()));
}