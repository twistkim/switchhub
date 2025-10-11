<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../lib/product_payment.php'; // 추가: 판매방식 유틸
require_role('partner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /partner/product_new.php'); exit;
}
$returnUrl = '/partner/product_new.php';

$me  = $_SESSION['user'];
$pdo = db();

$name   = trim($_POST['name'] ?? '');
$catId  = (int)($_POST['category_id'] ?? 0);
$price  = (float)($_POST['price'] ?? 0);
$year   = $_POST['release_year'] !== '' ? (int)$_POST['release_year'] : null;
$cond   = $_POST['condition'] ?? 'excellent';
$desc   = trim($_POST['description'] ?? '');

if ($name==='' || $catId<=0 || $price<0) {
  header('Location: /partner/product_new.php?err=' . urlencode('필수 항목을 확인하세요.')); exit;
}

// 판매 방식 파싱 및 최소 1개 검증
$allow = payment_parse_post($_POST); // ['normal'=>0|1, 'cod'=>0|1]
if ((int)$allow['normal'] === 0 && (int)$allow['cod'] === 0) {
  header('Location: ' . $returnUrl . '?err=' . urlencode('판매 방식을 최소 1개 선택하세요.')); exit;
}

$pdo->beginTransaction();
try {
  // 파트너 등록 → 항상 승인 대기 + 판매중으로 올려도 노출은 안 됨(approval_status)
  $ins = $pdo->prepare("
    INSERT INTO products (name, description, price, seller_id, category_id, detail_image_url, status, `condition`, release_year,
                          payment_normal, payment_cod, approval_status, created_at, updated_at)
    VALUES (:n,:d,:p,:s,:c,NULL,'on_sale',:cd,:y,:pn,:pc,'pending',NOW(),NOW())
  ");
  $ins->execute([
    ':n'=>$name, ':d'=>$desc, ':p'=>$price, ':s'=>$me['id'], ':c'=>$catId, ':cd'=>$cond, ':y'=>$year,
    ':pn'=>(int)$allow['normal'], ':pc'=>(int)$allow['cod']
  ]);
  $pid = (int)$pdo->lastInsertId();

  $dir = __DIR__ . '/../uploads/products/' . $pid;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  // 메인 이미지(최대 5장, 필수) — 단일/복수 업로드 모두 지원 + 대체 필드명(images) 지원 + 대표 이미지 선택
  $primaryIndex = isset($_POST['primary_image_index']) ? (int)$_POST['primary_image_index'] : 0;
  $filesField = null;
  if (!empty($_FILES['main_images'])) {
    $filesField = $_FILES['main_images'];
  } elseif (!empty($_FILES['images'])) { // 호환용
    $filesField = $_FILES['images'];
  }
  
  if ($filesField) {
    // 배열/단일 업로드 형태 통일
    $names  = $filesField['name'];
    $tmps   = $filesField['tmp_name'];
    $errors = $filesField['error'];
    if (!is_array($names)) {
      $names  = [$names];
      $tmps   = [$tmps];
      $errors = [$errors];
    }
  
    // 실제 업로드된 파일만 카운트
    $validIdx = [];
    foreach ($names as $i => $nm) {
      if ($nm !== '' && isset($errors[$i]) && $errors[$i] === UPLOAD_ERR_OK && is_uploaded_file($tmps[$i])) {
        $validIdx[] = $i;
      }
    }
    if (empty($validIdx)) {
      throw new Exception('메인 이미지는 1장 이상 업로드해야 합니다.');
    }
  
    $total = min(5, count($validIdx));
    $order = 0;
    foreach ($validIdx as $k => $i) {
      if ($order >= $total) break;
      $ext = pathinfo($names[$i], PATHINFO_EXTENSION) ?: 'jpg';
      $nameOnDisk = 'main_'.$order.'_'.bin2hex(random_bytes(4)).'.'.strtolower($ext);
      $dest = $dir.'/'.$nameOnDisk;
      if (move_uploaded_file($tmps[$i], $dest)) {
        $url = '/uploads/products/'.$pid.'/'.$nameOnDisk;
        $isPrimary = ((int)$i === $primaryIndex) ? 1 : 0;
        $pdo->prepare("INSERT INTO product_images(product_id,image_url,sort_order,is_primary) VALUES (:pid,:u,:so,:pr)")
            ->execute([':pid'=>$pid, ':u'=>$url, ':so'=>$order, ':pr'=>$isPrimary]);
        $order++;
      }
    }
  
    // 만약 어떤 이유로 대표가 하나도 지정되지 않았다면 첫 번째를 대표로 승격
    $chk = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id=:pid AND is_primary=1");
    $chk->execute([':pid'=>$pid]);
    if ((int)$chk->fetchColumn() === 0) {
      $pdo->prepare("UPDATE product_images SET is_primary=1 WHERE product_id=:pid ORDER BY sort_order ASC, id ASC LIMIT 1")
          ->execute([':pid'=>$pid]);
    }
  
  } else {
    throw new Exception('메인 이미지는 1장 이상 업로드해야 합니다.');
  }

  // 상세 이미지(선택)
  if (!empty($_FILES['detail_image']['name'])) {
    $f = $_FILES['detail_image'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $ext = pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg';
      $nameOnDisk = 'detail_'.bin2hex(random_bytes(4)).'.'.strtolower($ext);
      $dest = $dir.'/'.$nameOnDisk;
      if (move_uploaded_file($f['tmp_name'], $dest)) {
        $url = '/uploads/products/'.$pid.'/'.$nameOnDisk;
        $pdo->prepare("UPDATE products SET detail_image_url=:u WHERE id=:id")->execute([':u'=>$url, ':id'=>$pid]);
      }
    }
  }

  $pdo->commit();
  header('Location: /partner/index.php?msg=' . urlencode('등록 요청이 접수되었습니다. 관리자 승인 후 노출됩니다.'));
} catch(Exception $e) {
  $pdo->rollBack();
  header('Location: /partner/product_new.php?err=' . urlencode('저장 실패: '.$e->getMessage()));
}