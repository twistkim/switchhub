<?php
// /reviews/save.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../lib/reviews.php';

require_login();

$pdo = db();
$me  = $_SESSION['user'];
$userId = (int)$me['id'];

$append = function(string $url, array $params){
  if ($url === '' || stripos($url, 'http') === 0) $url = '/index.php';
  $q = array_filter($params, function($v){ return $v !== null && $v !== ''; });
  $qs = http_build_query($q);
  // 해시(#)는 쿼리와 별도로 붙인다
  if (isset($q['#'])) {
    $hash = '#' . ltrim((string)$q['#'], '#');
    unset($q['#']);
    $qs = http_build_query($q);
    return $url . (strpos($url,'?')!==false ? '&' : '?') . $qs . $hash;
  }
  return $url . (strpos($url,'?')!==false ? '&' : '?') . $qs;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  $back = $_POST['return'] ?? '/index.php';
  header('Location: ' . $append($back, ['err'=>'bad_request']));
  exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$orderId   = (int)($_POST['order_id'] ?? 0);
$rating    = (int)($_POST['rating'] ?? 0);
$body      = trim((string)($_POST['body'] ?? ''));
$back      = (string)($_POST['return'] ?? '/product.php?id='.$productId);

try {
  if ($productId <= 0) {
    header('Location: ' . $append($back, ['err'=>'invalid_product'])); exit;
  }

  // 권한: 배송완료 주문 보유자만
  $can = reviews_can_review($pdo, $userId, $productId);
  if (!$can['allowed']) {
    header('Location: ' . $append($back, ['err'=>'not_allowed'])); exit;
  }
  // form에서 준 order_id가 있으면 우선, 없으면 can의 order 사용
  if ($orderId <= 0) $orderId = (int)$can['order_id'];

  // 1) 텍스트/별점 리뷰 저장 (리뷰 ID 획득)
  $reviewId = reviews_save($pdo, $userId, $productId, $orderId, $rating, $body);
  if (empty($reviewId)) {
    // 라이브러리가 id를 반환하지 않는 경우, 같은 연결의 마지막 insert id를 활용
    $last = (int)$pdo->lastInsertId();
    if ($last > 0) $reviewId = $last;
  }

  // 2) 이미지 업로드 처리 (선택)
  $savedImages = 0;
  if (!empty($_FILES['photos']) && $reviewId) {
    $savedImages = save_review_uploaded_images($pdo, $reviewId, $_FILES['photos']);
    if ($savedImages > 0) {
      $pdo->prepare('UPDATE product_reviews SET has_images=1 WHERE id=?')->execute([$reviewId]);
    }
  }

  header('Location: ' . $append($back, ['msg'=>'review_saved', '#'=>'reviews']));
  exit;

} catch (Throwable $e) {
  error_log('[reviews/save] '.$e->getMessage());
  header('Location: ' . $append($back, ['err'=>'exception']));
  exit;
}

/**
 * 업로드 이미지 저장 + DB insert
 * @return int 저장된 이미지 개수
 */
function save_review_uploaded_images(PDO $pdo, int $reviewId, array $files): int {
  // 재구성
  $items = [];
  $names = $files['name'] ?? [];
  $types = $files['type'] ?? [];
  $tmps  = $files['tmp_name'] ?? [];
  $errs  = $files['error'] ?? [];
  $sizes = $files['size'] ?? [];
  $count = is_array($names) ? count($names) : 0;

  for ($i=0; $i<$count; $i++) {
    $items[] = [
      'name' => $names[$i] ?? '',
      'type' => $types[$i] ?? '',
      'tmp'  => $tmps[$i] ?? '',
      'err'  => $errs[$i] ?? UPLOAD_ERR_NO_FILE,
      'size' => (int)($sizes[$i] ?? 0),
    ];
  }

  if (!$items) return 0;

  // 제한
  $MAX_FILES = 5;
  $MAX_SIZE  = 5 * 1024 * 1024; // 5MB
  $ALLOWED   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

  // 저장 경로 준비
  $root = realpath(__DIR__ . '/..'); // 웹 루트(대부분 /newswitchhub/www)
  $sub  = '/uploads/reviews/' . date('Y/m');
  $dir  = $root . $sub;
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }

  $fin = new finfo(FILEINFO_MIME_TYPE);
  $saved = 0;

  foreach (array_slice($items, 0, $MAX_FILES) as $it) {
    if ($it['err'] !== UPLOAD_ERR_OK) continue;
    if ($it['size'] <= 0 || $it['size'] > $MAX_SIZE) continue;
    if (!is_uploaded_file($it['tmp'])) continue;

    $mime = $fin->file($it['tmp']);
    if (!isset($ALLOWED[$mime])) continue;
    $ext  = $ALLOWED[$mime];

    $basename = 'r' . $reviewId . '_' . bin2hex(random_bytes(6));
    $filename = $basename . '.' . $ext;
    $path     = $dir . '/' . $filename;
    $url      = $sub . '/' . $filename; // public url

    if (!@move_uploaded_file($it['tmp'], $path)) continue;

    // 크기 추출 (선택)
    $w = null; $h = null;
    if (function_exists('getimagesize')) {
      $dim = @getimagesize($path);
      if (is_array($dim)) { $w = $dim[0] ?? null; $h = $dim[1] ?? null; }
    }

    // DB insert
    $st = $pdo->prepare('INSERT INTO review_images (review_id, image_url, width, height) VALUES (:rid, :url, :w, :h)');
    $st->execute([
      ':rid' => $reviewId,
      ':url' => $url,
      ':w'   => $w,
      ':h'   => $h,
    ]);

    $saved++;
  }

  return $saved;
}