<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /admin/product_new.php'); exit;
}

$pdo = db();

$name   = trim($_POST['name'] ?? '');
$catId  = (int)($_POST['category_id'] ?? 0);
$seller = (int)($_POST['seller_id'] ?? 0);
$price  = (float)($_POST['price'] ?? 0);
$year   = $_POST['release_year'] !== '' ? (int)$_POST['release_year'] : null;
$cond   = $_POST['condition'] ?? 'excellent';
$desc   = trim($_POST['description'] ?? '');
$status = $_POST['status'] ?? 'on_sale';
$appv   = $_POST['approval_status'] ?? 'approved';

if ($name==='' || $catId<=0 || $seller<=0 || $price<0) {
  header('Location: /admin/product_new.php?err=' . urlencode('필수 항목을 확인하세요.')); exit;
}

// 트랜잭션
$pdo->beginTransaction();
try {
  $ins = $pdo->prepare("
    INSERT INTO products (name, description, price, seller_id, category_id, detail_image_url, status, `condition`, release_year, approval_status, created_at, updated_at)
    VALUES (:n,:d,:p,:s,:c,NULL,:st,:cd,:y,:ap,NOW(),NOW())
  ");
  $ins->execute([
    ':n'=>$name, ':d'=>$desc, ':p'=>$price, ':s'=>$seller, ':c'=>$catId,
    ':st'=>$status, ':cd'=>$cond, ':y'=>$year, ':ap'=>$appv
  ]);
  $pid = (int)$pdo->lastInsertId();

  // 업로드 디렉토리
  $dir = __DIR__ . '/../uploads/products/' . $pid;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  // 메인 이미지 (최대 5)
  if (!empty($_FILES['main_images']['name'][0])) {
    $files = $_FILES['main_images'];
    $count = min(5, count(array_filter($files['name'])));
    $order = 0;
    for ($i=0; $i<$count; $i++) {
      if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
      $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION) ?: 'jpg';
      $nameOnDisk = 'main_'.$order.'_'.bin2hex(random_bytes(4)).'.'.strtolower($ext);
      $dest = $dir.'/'.$nameOnDisk;
      if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
        $url = '/uploads/products/'.$pid.'/'.$nameOnDisk;
        $pdo->prepare("INSERT INTO product_images(product_id,image_url,sort_order,is_primary) VALUES (:pid,:u,:so,:pr)")
            ->execute([':pid'=>$pid, ':u'=>$url, ':so'=>$order, ':pr'=>$order===0?1:0]);
        $order++;
      }
    }
  }

  // 상세 이미지 1장
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
  header('Location: /product.php?id='.$pid);
} catch(Exception $e) {
  $pdo->rollBack();
  header('Location: /admin/product_new.php?err=' . urlencode('저장 실패: '.$e->getMessage()));
}