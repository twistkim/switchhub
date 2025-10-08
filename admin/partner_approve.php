<?php
// 승인 = partner_applications.status='approved'
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_role('admin');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /admin/partners.php?err=method'); exit; }
if (!csrf_verify($_POST['csrf'] ?? null))           { header('Location: /admin/partners.php?err=csrf');   exit; }

$appId = (int)($_POST['id'] ?? 0);
if ($appId <= 0) { header('Location: /admin/partners.php?err=bad_id'); exit; }


$pdo = db();
// 확장된 컬럼 포함
$appStmt = $pdo->prepare("
  SELECT
    id,
    email,
    business_name,
    name,
    contact_phone,
    store_address,
    business_address,
    store_lat,
    store_lng,
    store_place_id,
    hero_image_url
  FROM partner_applications
  WHERE id = :id
");
$appStmt->execute([':id'=>$appId]);
$app = $appStmt->fetch(PDO::FETCH_ASSOC);
if (!$app) { header('Location: /admin/partners.php?err=not_found'); exit; }

try {
  $pdo->beginTransaction();

  // 1) 신청서 승인
  $st = $pdo->prepare("UPDATE partner_applications SET status='approved', updated_at=NOW() WHERE id=:id");
  $st->execute([':id'=>$appId]);

  // 2) users.role = 'partner'로 승격 (admin은 유지) — email 기준
  if (!empty($app['email'])) {
    $up = $pdo->prepare("UPDATE users SET role='partner', updated_at=NOW() WHERE email=:email AND role <> 'admin'");
    $up->execute([':email' => $app['email']]);

    // 3) partner_profiles 자동 생성/갱신(승인 시 공개) — email로 users.id 매핑
    $uid = null;
    $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $uStmt->execute([':email' => $app['email']]);
    $uid = ($row = $uStmt->fetch(PDO::FETCH_ASSOC)) ? (int)$row['id'] : null;

    if ($uid) {
      // 소스에서 안전하게 값 정리
      $storeName = ($app['business_name'] ?? '') !== '' ? $app['business_name'] : ($app['name'] ?? null);
      $intro     = null; // intro 컬럼이 없다면 비워둠 (추후 확장)
      $phone     = $app['contact_phone']     ?? null;
      $addr1     = ($app['store_address']    ?? '') !== '' ? $app['store_address'] : ($app['business_address'] ?? null);
      $latRaw    = $app['store_lat']         ?? null;
      $lngRaw    = $app['store_lng']         ?? null;
      $lat       = is_numeric($latRaw) ? (float)$latRaw : null;
      $lng       = is_numeric($lngRaw) ? (float)$lngRaw : null;
      $placeId   = $app['store_place_id']    ?? null;
      $hero      = $app['hero_image_url']    ?? null;

      // store_name이 비어있으면 사용자 이름으로 백업
      if (!$storeName) {
        $unameStmt = $pdo->prepare("SELECT name FROM users WHERE id=:uid");
        $unameStmt->execute([':uid' => $uid]);
        $uname = $unameStmt->fetchColumn();
        $storeName = $uname ?: ('Store #' . $uid);
      }

      $ins = $pdo->prepare("
        INSERT INTO partner_profiles
          (user_id, store_name, intro, phone, address_line1, lat, lng, place_id, hero_image_url, is_published, created_at, updated_at)
        VALUES
          (:uid, :store_name, :intro, :phone, :addr1, :lat, :lng, :place_id, :hero, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
          store_name     = VALUES(store_name),
          intro          = VALUES(intro),
          phone          = VALUES(phone),
          address_line1  = VALUES(address_line1),
          lat            = VALUES(lat),
          lng            = VALUES(lng),
          place_id       = VALUES(place_id),
          hero_image_url = VALUES(hero_image_url),
          is_published   = 1,
          updated_at     = NOW()
      ");
      $ins->execute([
        ':uid'        => $uid,
        ':store_name' => $storeName,
        ':intro'      => $intro,
        ':phone'      => $phone,
        ':addr1'      => $addr1,
        ':lat'        => $lat,
        ':lng'        => $lng,
        ':place_id'   => $placeId,
        ':hero'       => $hero,
      ]);
    }
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: /admin/partners.php?err=exception&reason=' . rawurlencode($e->getMessage()));
  exit;
}

// (선택) 리다이렉트 복귀
$back = $_POST['return'] ?? '/admin/partners.php';
header('Location: ' . $back . (str_contains($back,'?')?'&':'?') . 'msg=approved');