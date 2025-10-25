<?php
// /admin/product_update.php — 간편 저장 처리기
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
require_role('admin');

$DEBUG = isset($_GET['debug']) || (!empty($_POST['debug'])) || (!empty($_SESSION['ADMIN_DEBUG']));
if ($DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

/* ✅ CSRF 검사: 필드명 기반으로 단 한 번 */
try {
  csrf_check_or_die(null, 'csrf', $DEBUG);
} catch (Throwable $e) {
  if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "CSRF FAIL\n";
    echo "posted: " . (string)($_POST['csrf'] ?? '(missing)') . "\n";
    echo "sess[csrf]      : " . (string)($_SESSION['csrf'] ?? '(missing)') . "\n";
    echo "sess[csrf_token]: " . (string)($_SESSION['csrf_token'] ?? '(missing)') . "\n";
    echo "session_id: " . (session_id() ?: '(none)') . "\n";
    echo "reason: " . $e->getMessage() . "\n";
    exit;
  }
  http_response_code(400);
  echo 'CSRF validation failed';
  exit;
}
 
// 입력값
$id              = (int)($_POST['id'] ?? 0);
$name            = trim((string)($_POST['name'] ?? ''));
$price           = (int)($_POST['price'] ?? 0);
$description     = (string)($_POST['description'] ?? '');
$condition       = (string)($_POST['condition'] ?? '');
$release_year_in = trim((string)($_POST['release_year'] ?? ''));
$category_id     = (int)($_POST['category_id'] ?? 0);
$status          = (string)($_POST['status'] ?? 'on_sale');
$approval_status = (string)($_POST['approval_status'] ?? 'pending');
$pay_normal      = isset($_POST['payment_normal']) ? 1 : 0;
$pay_cod         = isset($_POST['payment_cod']) ? 1 : 0;

if ($id <= 0 || $name === '' || $price < 0 || $category_id <= 0) {
  header('Location: /admin/product_edit.php?id='.$id.'&err=bad_input');
  exit;
}
if ($pay_normal === 0 && $pay_cod === 0) {
  header('Location: /admin/product_edit.php?id='.$id.'&err=payment_required');
  exit;
}

$release_year = null;
if ($release_year_in !== '') {
  $ry = (int)$release_year_in;
  if ($ry >= 2000 && $ry <= 2100) { $release_year = $ry; }
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $pdo->beginTransaction();

  // 존재 확인
  $st = $pdo->prepare("SELECT id FROM products WHERE id=:id LIMIT 1");
  $st->execute([':id' => $id]);
  if (!$st->fetchColumn()) {
    throw new RuntimeException('product_not_found');
  }

  // 기본필드 업데이트
  $sql = "UPDATE products
             SET name=:name,
                 price=:price,
                 description=:description,
                 `condition`=:cond,
                 release_year=:release_year,
                 category_id=:category_id,
                 status=:status,
                 approval_status=:approval_status,
                 payment_normal=:p_normal,
                 payment_cod=:p_cod,
                 updated_at=NOW()
           WHERE id=:id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':name'            => $name,
    ':price'           => $price,
    ':description'     => $description,
    ':cond'            => $condition,
    ':release_year'    => $release_year,
    ':category_id'     => $category_id,
    ':status'          => $status,
    ':approval_status' => $approval_status,
    ':p_normal'        => $pay_normal,
    ':p_cod'           => $pay_cod,
    ':id'              => $id,
  ]);

  //
  // 업로드 경로 유틸
  //
  function ensure_dir(string $path): void {
    if (!is_dir($path)) {
      @mkdir($path, 0777, true);
    }
  }
  function save_image_upload(array $file, string $baseSubdir): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;

    $tmp  = $file['tmp_name'];
    $type = (string)@mime_content_type($tmp);
    // 간단 MIME 체크
    if (!preg_match('~^image/(jpe?g|png|webp|gif)$~i', $type)) {
      return null;
    }
    $ext = '.jpg';
    if (stripos($type, 'png')  !== false) $ext = '.png';
    if (stripos($type, 'webp') !== false) $ext = '.webp';
    if (stripos($type, 'gif')  !== false) $ext = '.gif';

    $ymd   = date('Ymd');
    $dir   = __DIR__ . "/../uploads/{$baseSubdir}/{$ymd}";
    ensure_dir($dir);

    $fname = bin2hex(random_bytes(8)) . $ext;
    $dest  = $dir . '/' . $fname;
    if (!move_uploaded_file($tmp, $dest)) {
      return null;
    }
    // 웹 경로 반환
    return "/uploads/{$baseSubdir}/{$ymd}/{$fname}";
  }

  //
  // 상세 이미지 교체 (선택)
  //
  if (!empty($_FILES['detail_image']) && is_array($_FILES['detail_image'])) {
    $url = save_image_upload($_FILES['detail_image'], 'products/detail');
    if ($url) {
      $u = $pdo->prepare("UPDATE products SET detail_image_url=:u, updated_at=NOW() WHERE id=:id");
      $u->execute([':u'=>$url, ':id'=>$id]);
    }
  }

  //
  // 대표 썸네일 / 추가 썸네일 처리
  //
  $main_existing_id = isset($_POST['main_existing_id']) ? (int)$_POST['main_existing_id'] : 0;
  $current_main_existing_id = isset($_POST['current_main_existing_id']) ? (int)$_POST['current_main_existing_id'] : 0;

  // 대표 이미지 새 업로드 시 우선 처리
  if (!empty($_FILES['main_image']) && is_array($_FILES['main_image'])) {
    $url = save_image_upload($_FILES['main_image'], 'products/main');
    if ($url) {
      // sort_order=0 유니크 충돌 방지: 현재 0인 row를 맨 뒤로 이동
      $nextSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM product_images WHERE product_id={$id}")->fetchColumn();
      $pdo->prepare("UPDATE product_images SET sort_order=:ns WHERE product_id=:pid AND sort_order=0")
        ->execute([':ns'=>$nextSort, ':pid'=>$id]);
      // 기존 대표 리셋 후 신규 대표 등록
      $pdo->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=:pid")->execute([':pid'=>$id]);
      $ins = $pdo->prepare("
        INSERT INTO product_images (product_id, image_url, is_primary, sort_order, created_at)
        VALUES (:pid, :url, 1, 0, NOW())
      ");
      $ins->execute([':pid'=>$id, ':url'=>$url]);
      // 신규 대표 업로드 시 제품 대표 이미지도 동기화
      $pdo->prepare("UPDATE products SET detail_image_url=:u, updated_at=NOW() WHERE id=:pid")
          ->execute([':u'=>$url, ':pid'=>$id]);
    }
  } elseif ($main_existing_id > 0) {
    // 기존 대표 선택 변경
    $pdo->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=:pid")->execute([':pid'=>$id]);
    // sort_order=0을 가진 row가 있다면 1로 이동 (0을 비움)
    $pdo->prepare("UPDATE product_images SET sort_order=1 WHERE product_id=:pid AND sort_order=0")
      ->execute([':pid'=>$id]);
    // 선택된 이미지를 대표로, sort_order=0으로
    $pdo->prepare("UPDATE product_images SET is_primary=1, sort_order=0 WHERE id=:id AND product_id=:pid LIMIT 1")
        ->execute([':id'=>$main_existing_id, ':pid'=>$id]);
    // 대표 썸네일로 제품 대표 이미지도 동기화
    $selUrl = $pdo->prepare("SELECT image_url FROM product_images WHERE id=:id AND product_id=:pid");
    $selUrl->execute([':id'=>$main_existing_id, ':pid'=>$id]);
    if ($urow = $selUrl->fetch(PDO::FETCH_ASSOC)) {
      $pdo->prepare("UPDATE products SET detail_image_url=:u, updated_at=NOW() WHERE id=:pid")
          ->execute([':u'=>$urow['image_url'], ':pid'=>$id]);
    }
  } elseif ($current_main_existing_id > 0) {
    // 아무것도 지정 안 했을 경우 현재 대표 유지 (유일성 보장)
    $pdo->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=:pid")
        ->execute([':pid'=>$id]);
    $pdo->prepare("UPDATE product_images SET is_primary=1 WHERE id=:id AND product_id=:pid LIMIT 1")
        ->execute([':id'=>$current_main_existing_id, ':pid'=>$id]);
  }

  // 추가 썸네일 업로드
  if (!empty($_FILES['sub_images']) && is_array($_FILES['sub_images']['name'])) {
    $count = count($_FILES['sub_images']['name']);
    $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id={$id}")->fetchColumn();
    for ($i=0; $i<$count; $i++) {
      $file = [
        'name'     => $_FILES['sub_images']['name'][$i] ?? '',
        'type'     => $_FILES['sub_images']['type'][$i] ?? '',
        'tmp_name' => $_FILES['sub_images']['tmp_name'][$i] ?? '',
        'error'    => $_FILES['sub_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
        'size'     => $_FILES['sub_images']['size'][$i] ?? 0,
      ];
      if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      $url = save_image_upload($file, 'products');
      if (!$url) continue;
      $maxSort++;
      $pdo->prepare("
        INSERT INTO product_images (product_id, image_url, is_primary, sort_order, created_at)
        VALUES (:pid, :url, 0, :sort, NOW())
      ")->execute([':pid'=>$id, ':url'=>$url, ':sort'=>$maxSort]);
    }
  }

  $pdo->commit();

  header('Location: /admin/product_edit.php?id='.$id.'&msg=updated');
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "EXCEPTION: ".$e->getMessage()."\n\n";
    echo $e->getTraceAsString();
    exit;
  }
  $reason = rawurlencode($e->getMessage());
  header('Location: /admin/product_edit.php?id='.$id.'&err=exception&reason='.$reason);
  exit;
}