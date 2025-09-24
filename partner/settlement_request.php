<?php
// /partner/settlement_request.php — 파트너 정산 요청 처리 (삭제/스키마 차이 대응 · 안정화)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_role('partner');

$__DBG = (isset($_GET['debug']) && $_GET['debug']=='1');

$append = function(string $url, string $q) {
  return (strpos($url, '?') !== false) ? ($url . '&' . $q) : ($url . '?' . $q);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $append('/partner/index.php', 'err=bad_request')); exit;
}

// CSRF 체크 (프로젝트별 함수명 호환 + 세션 fallback)
$csrfToken = $_POST['csrf'] ?? null;
$validCsrf = false;
if (function_exists('csrf_verify')) {
  $validCsrf = csrf_verify($csrfToken);
} elseif (function_exists('csrf_check')) {
  $validCsrf = csrf_check($csrfToken ?? '');
} else {
  $sessionCsrf = $_SESSION['csrf'] ?? ($_SESSION['csrf_token'] ?? null);
  $validCsrf = $sessionCsrf && hash_equals((string)$sessionCsrf, (string)$csrfToken);
}
if (!$validCsrf) {
  header('Location: ' . $append('/partner/index.php', 'err=bad_csrf')); exit;
}

$pdo     = db();

function table_columns(PDO $pdo, string $table): array {
  $cols = [];
  try {
    $st = $pdo->prepare("SHOW COLUMNS FROM `" . str_replace("`","``", $table) . "`");
    $st->execute();
    foreach ($st->fetchAll() as $row) {
      // Row keys: Field, Type, Null, Key, Default, Extra
      $name = (string)$row['Field'];
      $cols[$name] = [
        'type'    => (string)$row['Type'],
        'null'    => (strtoupper((string)$row['Null']) === 'YES'),
        'default' => $row['Default'],
        'extra'   => (string)$row['Extra'],
      ];
    }
  } catch (Throwable $e) { /* ignore */ }
  return $cols;
}

function enum_values_from_type(?string $type): array {
  // expects e.g. "enum('requested','approved','paid','rejected')"
  if (!$type) return [];
  if (stripos($type, 'enum(') !== 0) return [];
  $inside = trim($type);
  $inside = substr($inside, 5); // remove "enum("
  $inside = rtrim($inside, ")");
  // split by comma respecting quotes
  $vals = [];
  $cur = '';
  $inQ = false;
  $len = strlen($inside);
  for ($i=0; $i<$len; $i++) {
    $ch = $inside[$i];
    if ($ch === "'") { $inQ = !$inQ; continue; }
    if ($ch === ',' && !$inQ) { $vals[] = $cur; $cur = ''; continue; }
    $cur .= $ch;
  }
  if ($cur !== '') $vals[] = $cur;
  // unescape
  $vals = array_map(function($s){ return stripcslashes($s); }, $vals);
  return array_map('trim', $vals);
}

$settleCols = table_columns($pdo, 'partner_settlements');
$hasPartnerId = array_key_exists('partner_id', $settleCols);
$hasAmount    = array_key_exists('amount', $settleCols);
$hasStatus    = array_key_exists('status', $settleCols);
$hasNote      = array_key_exists('note', $settleCols);

$me      = $_SESSION['user'];
$return  = (isset($_POST['return']) && is_string($_POST['return']) && $_POST['return']!=='') ? $_POST['return'] : '/partner/index.php';

// 입력 정규화
$orderId  = (int)($_POST['order_id'] ?? 0);
$amountIn = trim((string)($_POST['amount'] ?? ''));
if ($amountIn !== '') { $amountIn = str_replace([',',' '], ['.',''], $amountIn); }
$amount = is_numeric($amountIn) ? (float)$amountIn : 0.0;
// clamp/round to avoid DECIMAL warnings
if ($amount < 0) { $amount = 0.0; }
$amount = (float)number_format($amount, 2, '.', '');
if ($orderId <= 0) { header('Location: ' . $append($return, 'err=missing_order')); exit; }

try {

  // 주문 + 상품(LEFT) 조회: 상품이 하드 삭제된 경우도 커버
  $st = $pdo->prepare("SELECT 
  o.id AS oid,
  o.status AS o_status,
  o.product_id,
  p.id AS pid,
  p.seller_id AS p_seller_id,
  p.price AS p_price
  FROM orders o
  LEFT JOIN products p ON p.id = o.product_id
  WHERE o.id = :oid
  LIMIT 1");
  $st->execute([':oid'=>$orderId]);
  $o = $st->fetch();
  if (!$o) { header('Location: ' . $append($return, 'err=order_not_found')); exit; }

  // 소유권 검증: products.seller_id == 내 id
  $ownerByProduct = isset($o['p_seller_id']) && (int)$o['p_seller_id'] === (int)$me['id'];
  if (!$ownerByProduct) {
    header('Location: ' . $append($return, 'err=not_owner')); exit;
  }

  // 배송완료 상태 허용(다국어/별칭)
  $rawStatus = (string)$o['o_status'];
  $norm = strtolower(trim($rawStatus));
  $deliveredAliases = ['delivered','배송완료','배송 완료','delivery_complete','completed','complete'];
  if (!in_array($norm, $deliveredAliases, true)) {
    header('Location: ' . $append($return, 'err=invalid_state_' . urlencode($rawStatus))); exit;
  }

  // 금액 결정: 폼 입력 > 상품가 > 0 검증
  if ($amount <= 0) {
    $amount = isset($o['p_price']) ? (float)$o['p_price'] : 0.0;
  }
  if ($amount <= 0) {
    header('Location: ' . $append($return, 'err=amount_required')); exit;
  }

  // 중복 요청 방지
  if ($hasPartnerId) {
    $dup = $pdo->prepare("SELECT `id` FROM `partner_settlements` WHERE `order_id`=:oid AND `partner_id`=:pid LIMIT 1");
    $dup->execute([':oid'=>$orderId, ':pid'=>(int)$me['id']]);
  } else {
    $dup = $pdo->prepare("SELECT `id` FROM `partner_settlements` WHERE `order_id`=:oid LIMIT 1");
    $dup->execute([':oid'=>$orderId]);
  }
  if ($dup->fetch()) { header('Location: ' . $append($return, 'err=already_requested')); exit; }

  // 동적 INSERT 구성 (NOT NULL & DEFAULT 없음인 컬럼은 반드시 포함)
  $cols = ['`order_id`'];
  $vals = [':oid'];
  $args = [ ':oid' => $orderId ];

  // helper to detect required columns
  $is_required = function(string $col) use ($settleCols): bool {
    if (!isset($settleCols[$col])) return false;
    $meta = $settleCols[$col];
    return ($meta['null'] === false) && ($meta['default'] === null) && stripos((string)$meta['extra'], 'auto_increment') === false;
  };

  // partner_id
  if ($hasPartnerId || $is_required('partner_id')) {
    $cols[]='`partner_id`'; $vals[]=':pid'; $args[':pid']=(int)$me['id'];
  }
  // amount
  if ($hasAmount || $is_required('amount')) {
    $cols[]='`amount`';
    $vals[]=':amt';
    $args[':amt'] = number_format((float)$amount, 2, '.', '');
  }
  // status (choose a valid ENUM if needed)
  if ($hasStatus || $is_required('status')) {
    $safeStatus = 'requested';
    $statusType = isset($settleCols['status']['type']) ? (string)$settleCols['status']['type'] : '';
    $opts = enum_values_from_type($statusType);
    if (!empty($opts)) {
      // if 'requested' is not allowed, fall back to the first allowed value
      if (!in_array('requested', $opts, true)) {
        $safeStatus = $opts[0];
      }
    }
    $cols[]='`status`';
    $vals[]=':st';
    $args[':st'] = $safeStatus;
  }
  // note (nullable이면 굳이 넣지 않아도 되지만 있으면 명시적으로 NULL)
  if ($hasNote && !$is_required('note')) {
    $cols[]='`note`';       $vals[]=':note'; $args[':note']=null;
  }

  $sqlIns = "INSERT INTO `partner_settlements` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
  $ins = $pdo->prepare($sqlIns);
  $ins->execute($args);
  if (!($pdo->lastInsertId())) { header('Location: ' . $append($return, 'err=insert_failed')); exit; }

  if ($__DBG) {
    header('Location: ' . $append($return, 'msg=settlement_requested&dbg_cols=' . urlencode(json_encode(array_keys($settleCols))))); exit;
  }
  header('Location: ' . $append($return, 'msg=settlement_requested')); exit;

} catch (PDOException $e) {
  error_log('[settlement_request] PDOException ' . $e->getCode() . ' ' . $e->getMessage());
  $code = preg_replace('/[^0-9A-Za-z_\-]/','', (string)$e->getCode());
  $msg  = substr($e->getMessage(), 0, 120);
  $msg  = preg_replace('/[^0-9A-Za-z_\-\. ]/','', $msg);
  $hint = '';
  $lc = strtolower($msg);
  if ($code === '01000' || strpos($lc,'enum') !== false || strpos($lc,'data truncated') !== false) {
    $hint = '&hint=enum_or_decimal';
  }
  header('Location: ' . $append($return, 'err=pdo_' . $code . '&errm=' . urlencode($msg) . $hint)); exit;
} catch (Throwable $e) {
  error_log('[settlement_request] Throwable ' . $e->getMessage());
  header('Location: ' . $append($return, 'err=exception')); exit;
}