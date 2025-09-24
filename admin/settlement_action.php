<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

$append = function(string $url, string $q) {
  return (strpos($url, '?') !== false) ? ($url . '&' . $q) : ($url . '?' . $q);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $append('/admin/index.php', 'tab=settlements&err=bad_request')); exit;
}
if (!csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: ' . $append('/admin/index.php', 'tab=settlements&err=bad_csrf')); exit;
}

$id = (int)($_POST['id'] ?? 0);
$act = trim((string)($_POST['action'] ?? ''));
$amountIn = (string)($_POST['amount'] ?? '');
$amount = is_numeric($amountIn) ? (float)$amountIn : 0.0;
$amount = (float)number_format(max(0, $amount), 2, '.', '');

if ($id <= 0) {
  header('Location: ' . $append('/admin/index.php', 'tab=settlements&err=missing_id')); exit;
}

$pdo = db();

// --- schema probe helpers ---
function table_columns(PDO $pdo, string $table): array {
  $cols = [];
  try {
    $st = $pdo->prepare("SHOW COLUMNS FROM `".str_replace("`","``",$table)."`");
    $st->execute();
    foreach ($st->fetchAll() as $row) {
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

$cols = table_columns($pdo, 'partner_settlements');
$hasStatus  = array_key_exists('status', $cols);
$hasAmount  = array_key_exists('amount', $cols);
$hasPaidAt  = array_key_exists('paid_at', $cols);
$hasUpdAt   = array_key_exists('updated_at', $cols);
$hasNote    = array_key_exists('note', $cols);

try {
  // 현재 행
  $base = $pdo->prepare("SELECT * FROM partner_settlements WHERE id=? LIMIT 1");
  $base->execute([$id]);
  $cur = $base->fetch(PDO::FETCH_ASSOC);
  if (!$cur) {
    header('Location: ' . $append('/admin/index.php', 'tab=settlements&err=not_found')); exit;
  }

  // 상태 정규화 (레거시 NULL/pending => requested)
  $curStatus = $hasStatus ? (string)($cur['status'] ?? '') : '';
  $norm = strtolower(trim($curStatus));
  if ($norm === '' || $norm === 'null' || $norm === 'pending') $norm = 'requested';

  // UPDATE 구성
  $sets = [];
  $args = [':id' => $id];

  if ($act === 'approve') {
    if ($hasStatus) { $sets[] = "`status` = 'approved'"; }
    if ($hasAmount) { $sets[] = "`amount` = :amt"; $args[':amt'] = number_format($amount, 2, '.', ''); }
    if ($hasUpdAt)  { $sets[] = "`updated_at` = NOW()"; }
  } elseif ($act === 'reject') {
    if ($hasStatus) { $sets[] = "`status` = 'rejected'"; }
    if ($hasUpdAt)  { $sets[] = "`updated_at` = NOW()"; }
    if ($hasNote && isset($_POST['note'])) { $sets[] = "`note` = :nt"; $args[':nt'] = (string)$_POST['note']; }
  } elseif ($act === 'mark_paid') {
    if ($hasStatus) { $sets[] = "`status` = 'paid'"; }
    if ($hasAmount) { $sets[] = "`amount` = :amt"; $args[':amt'] = number_format($amount, 2, '.', ''); }
    if ($hasPaidAt) { $sets[] = "`paid_at` = NOW()"; }
    if ($hasUpdAt)  { $sets[] = "`updated_at` = NOW()"; }
  } else {
    header('Location: ' . $append('/admin/index.php', 'tab=settlements&err=unknown_action')); exit;
  }

  if (empty($sets)) {
    // 스키마 상 업데이트할 컬럼이 없으면 성공으로 간주
    header('Location: ' . $append('/admin/index.php', 'tab=settlements&msg=ok_noop')); exit;
  }

  // 유연 전이: requested(null/pending) → approved/rejected/paid, approved → paid
  // invalid_state로 막지 않음 (레거시 데이터 대응)
  $sql = "UPDATE `partner_settlements` SET " . implode(', ', $sets) . " WHERE `id` = :id LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute($args);

  $msg = ($act === 'approve') ? 'approved' : (($act === 'reject') ? 'rejected' : 'paid');
  header('Location: ' . $append('/admin/index.php', 'tab=settlements&msg=' . $msg)); exit;

} catch (PDOException $e) {
  $code = preg_replace('/[^0-9A-Za-z_\\-]/','', (string)$e->getCode());
  $msg  = substr($e->getMessage(), 0, 120);
  $msg  = preg_replace('/[^0-9A-Za-z_\\-\\. ]/','', $msg);
  header('Location: ' . $append('/admin/index.php', 'tab=settlements&err=pdo_' . $code . '&errm=' . urlencode($msg))); exit;
} catch (Throwable $e) {
  header('Location: ' . $append('/admin/index.php', 'tab=settlements&err=exception')); exit;
}