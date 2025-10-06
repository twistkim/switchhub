<?php
// /admin/product_update.php (with DEBUG switch)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';

require_once __DIR__ . '/../auth/csrf.php';

// --- CSRF fallback (if functions are missing) --------------------------------
if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }
}
if (!function_exists('csrf_check_or_die')) {
  function csrf_check_or_die(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $tok = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    $ok = $tok && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $tok);
    if (!$ok) {
      http_response_code(400);
      header('Content-Type: text/plain; charset=UTF-8');
      echo 'CSRF validation failed';
      exit;
    }
  }
}

// ---- DEBUG TOGGLE ---------------------------------------------------------
$DEBUG = isset($_GET['debug']) || isset($_POST['debug']) || (!empty($_SESSION['ADMIN_DEBUG']));
if ($DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

require_role('admin');        // 관리자만

// --- CSRF relaxed verify (accept multiple session keys) --------------------
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$__tok   = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
$__cands = [];
$__cands[] = $_SESSION['csrf_token'] ?? null; // common key
$__cands[] = $_SESSION['csrf']       ?? null; // alt key
$__ok = false;
foreach ($__cands as $__sk) {
  if (!$__sk) continue;
  if (hash_equals((string)$__sk, (string)$__tok)) { $__ok = true; break; }
}
if (!$__ok) {
  if (isset($_GET['debug']) || isset($_POST['debug']) || (!empty($_SESSION['ADMIN_DEBUG']))) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "CSRF validation failed\n";
    echo "token(submitted)=".var_export($__tok,true)."\n";
    echo "session_keys=".var_export(array_values(array_filter($__cands)), true)."\n";
    exit;
  }
  header('Location: /admin/product_edit.php?'.http_build_query(['id'=>(int)($_POST['id']??0),'err'=>'csrf']));
  exit;
}

function back_to_edit($id, $key = 'err', $msg = 'invalid')
{
    global $DEBUG;
    $q = http_build_query(['id' => (int)$id, $key => $msg]);
    if ($DEBUG) {
        http_response_code(200);
        echo "<pre>BACK_TO_EDIT → /admin/product_edit.php?$q</pre>";
        exit;
    }
    header('Location: /admin/product_edit.php?' . $q);
    exit;
}

$pdo = db();
try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $__) {}

// 입력 수집
$id     = (int)($_POST['id'] ?? 0);
$name   = trim($_POST['name'] ?? '');
$desc   = trim($_POST['description'] ?? '');
$price  = (int)($_POST['price'] ?? 0);
$cond   = trim($_POST['condition'] ?? '');
$year   = $_POST['release_year'] ?? null; // 빈문자열 허용
$catId  = (int)($_POST['category_id'] ?? 0);
$payN   = isset($_POST['payment_normal']) ? 1 : 0;
$payC   = isset($_POST['payment_cod'])    ? 1 : 0;
$status   = trim($_POST['status'] ?? 'on_sale');
$approval = trim($_POST['approval_status'] ?? 'pending');

if ($DEBUG) {
  echo "<pre>DBG INPUT\n";
  echo "id=$id\nname=$name\nprice=$price\ncond=$cond\nyear=".var_export($year, true)."\ncatId=$catId\npayN=$payN\npayC=$payC\nstatus=$status\napproval=$approval\n</pre>";
}

// 1) 기본 검증
if ($id <= 0)                     back_to_edit($id, 'err', 'bad_id');
if ($name === '' || $desc === '') back_to_edit($id, 'err', 'required');
if ($price <= 0 || $catId <= 0)   back_to_edit($id, 'err', 'invalid_price_or_category');
if ($payN === 0 && $payC === 0)   back_to_edit($id, 'err', 'need_payment');

// 출시년도: 빈 값 허용(NULL), 숫자면 정수로
if ($year === '' || $year === null) {
    $year = null;
} else {
    $year = (int)$year;
    if ($year < 2000 || $year > 2100) $year = null;
}

// 2) 존재 확인 (없으면 not_found)
$check = $pdo->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
$check->execute([':id' => $id]);
if (!$check->fetch()) {
    back_to_edit($id, 'err', 'not_found');
}

try {
    // 3) UPDATE
    // 3) UPDATE
    $sql = "
    UPDATE products
    SET name = :name,
        description = :desc,
        price = :price,
        `condition` = :cond,
        release_year = :yr,
        category_id = :cat,
        payment_normal = :pn,
        payment_cod = :pc,
        status = :st,
        approval_status = :ap,
        updated_at = NOW()
    WHERE id = :id
    LIMIT 1
    ";
    if ($DEBUG) {
    echo "<pre>DBG SQL\n$sql</pre>";
    }

    $stmt = $pdo->prepare($sql);

    // 모든 파라미터를 bindValue로 통일 (HY093 회피)
    $stmt->bindValue(':name',  $name,  PDO::PARAM_STR);
    $stmt->bindValue(':desc',  $desc,  PDO::PARAM_STR);
    $stmt->bindValue(':price', (int)$price, PDO::PARAM_INT);
    $stmt->bindValue(':cond',  $cond,  PDO::PARAM_STR);
    if ($year === null) {
    $stmt->bindValue(':yr', null, PDO::PARAM_NULL);
    } else {
    $stmt->bindValue(':yr', (int)$year, PDO::PARAM_INT);
    }
    $stmt->bindValue(':cat',  (int)$catId, PDO::PARAM_INT);
    $stmt->bindValue(':pn',   (int)$payN,  PDO::PARAM_INT);
    $stmt->bindValue(':pc',   (int)$payC,  PDO::PARAM_INT);
    $stmt->bindValue(':st',    $status,    PDO::PARAM_STR);
    $stmt->bindValue(':ap',    $approval,  PDO::PARAM_STR);
    $stmt->bindValue(':id',   (int)$id,    PDO::PARAM_INT);

    $ok = $stmt->execute();

    if (!$ok) back_to_edit($id, 'err', 'update_failed');

    if ($DEBUG) {
      echo "<pre>OK: updated product #$id</pre>";
      exit;
    }

    // 성공
    header('Location: /admin/index.php?tab=products&msg=updated');
    exit;

} catch (Throwable $e) {
    if ($DEBUG) {
        http_response_code(500);
        echo '<h2>EXCEPTION</h2>';
        echo '<pre>'.htmlspecialchars($e->getMessage().' @ '.$e->getFile().':'.$e->getLine(), ENT_QUOTES, 'UTF-8')."\n";
        echo $e->getTraceAsString();
        echo "</pre>";
        exit;
    }
    $reason = urlencode($e->getMessage());
    back_to_edit($id, 'err', "exception&reason=$reason");
}