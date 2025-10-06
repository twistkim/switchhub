<?php
// /partner/product_update.php (debug-friendly)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';

// ---- DEBUG SWITCH ---------------------------------------------------------
$DEBUG = isset($_GET['debug']) || isset($_POST['debug']);
if ($DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

require_role('partner');

// ---- CSRF verify (fallback if project helper absent) ----------------------
if (!function_exists('csrf_require')) {
  function csrf_require(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $tok = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    $cands = [ $_SESSION['csrf_token'] ?? null, $_SESSION['csrf'] ?? null ];
    $ok = false;
    foreach ($cands as $s) { if ($s && hash_equals((string)$s, (string)$tok)) { $ok = true; break; } }
    if (!$ok) {
      if (isset($_GET['debug']) || isset($_POST['debug'])) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "CSRF validation failed\n";
        echo 'token=' . var_export($tok, true) . "\n";
        echo 'session_keys=' . var_export(array_values(array_filter($cands)), true) . "\n";
        exit;
      }
      http_response_code(400);
      header('Location: /partner/index.php?err=csrf');
      exit;
    }
  }
}
csrf_require();

$pdo = db();
try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $__) {}
$uid = (int)($_SESSION['user']['id'] ?? 0);

// ---- INPUT ----------------------------------------------------------------
$id    = (int)($_POST['id'] ?? 0);
$name  = trim($_POST['name'] ?? '');
$desc  = trim($_POST['description'] ?? '');
$price = (int)($_POST['price'] ?? 0);
$cond  = trim($_POST['condition'] ?? '');
$year  = $_POST['release_year'] ?? null; // allow empty -> NULL
$catId = (int)($_POST['category_id'] ?? 0);
$payN  = isset($_POST['payment_normal']) ? 1 : 0;
$payC  = isset($_POST['payment_cod'])    ? 1 : 0;

if ($DEBUG) {
  echo "<pre>DBG INPUT\n";
  echo "id=$id\nname=$name\nprice=$price\ncond=$cond\nyear=".var_export($year,true)."\ncatId=$catId\npayN=$payN\npayC=$payC\nuid=$uid\n</pre>";
}

// ---- BASIC VALIDATION -----------------------------------------------------
if ($id<=0 || $name==='' || $desc==='' || $price<=0 || $catId<=0) {
  $to = '/partner/product_edit.php?' . http_build_query(['id'=>$id,'err'=>'invalid']);
  if ($DEBUG) { echo "<pre>REDIRECT $to</pre>"; exit; }
  header('Location: '.$to); exit;
}
if ($payN===0 && $payC===0) {
  $to = '/partner/product_edit.php?' . http_build_query(['id'=>$id,'err'=>'need_payment']);
  if ($DEBUG) { echo "<pre>REDIRECT $to</pre>"; exit; }
  header('Location: '.$to); exit;
}

// normalize year
if ($year === '' || $year === null) {
  $year = null;
} else {
  $year = (int)$year;
  if ($year < 2000 || $year > 2100) $year = null;
}

// ---- OWNERSHIP CHECK ------------------------------------------------------
$stmt = $pdo->prepare('SELECT seller_id FROM products WHERE id=:id LIMIT 1');
$stmt->execute([':id'=>$id]);
$row = $stmt->fetch();
if (!$row || (int)$row['seller_id'] !== $uid) {
  $to = '/partner/index.php?err=forbidden';
  if ($DEBUG) { echo "<pre>REDIRECT $to</pre>"; exit; }
  header('Location: '.$to); exit;
}

try {
  // ---- UPDATE (bindValue only) -------------------------------------------
  $sql = "
    UPDATE products
       SET name=:name,
           description=:desc,
           price=:price,
           `condition`=:cond,
           release_year=:yr,
           category_id=:cat,
           payment_normal=:pn,
           payment_cod=:pc,
           updated_at=NOW()
     WHERE id=:id
     LIMIT 1
  ";
  if ($DEBUG) { echo "<pre>DBG SQL\n$sql</pre>"; }

  $st = $pdo->prepare($sql);
  $st->bindValue(':name',  $name,  PDO::PARAM_STR);
  $st->bindValue(':desc',  $desc,  PDO::PARAM_STR);
  $st->bindValue(':price', (int)$price, PDO::PARAM_INT);
  $st->bindValue(':cond',  $cond,  PDO::PARAM_STR);
  if ($year === null) $st->bindValue(':yr', null, PDO::PARAM_NULL); else $st->bindValue(':yr', (int)$year, PDO::PARAM_INT);
  $st->bindValue(':cat',   (int)$catId, PDO::PARAM_INT);
  $st->bindValue(':pn',    (int)$payN,  PDO::PARAM_INT);
  $st->bindValue(':pc',    (int)$payC,  PDO::PARAM_INT);
  $st->bindValue(':id',    (int)$id,    PDO::PARAM_INT);

  $ok = $st->execute();

  if ($DEBUG) {
    echo $ok ? '<pre>OK: updated</pre>' : '<pre>NG: fail</pre>';
    exit;
  }

  header('Location: /partner/index.php?msg=' . ($ok ? 'updated' : 'fail'));
  exit;

} catch (Throwable $e) {
  if ($DEBUG) {
    http_response_code(500);
    echo '<h2>EXCEPTION</h2><pre>' . htmlspecialchars($e->getMessage().' @ '.$e->getFile().':'.$e->getLine(), ENT_QUOTES, 'UTF-8') . "\n";
    echo $e->getTraceAsString();
    echo '</pre>';
    exit;
  }
  $reason = urlencode($e->getMessage());
  header('Location: /partner/product_edit.php?' . http_build_query(['id'=>$id,'err'=>'exception','reason'=>$reason]));
  exit;
}