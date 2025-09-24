<?php
// /admin/partners.php
$pageTitle = '관리자 대시보드 - 파트너 관리';
$activeMenu = 'partners';

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

$pdo = db();

// 필터/검색
$q        = trim($_GET['q'] ?? ''); // 상호/이메일/담당자/사업자번호
$status   = $_GET['status'] ?? '';  // pending/approved/rejected
$perPage  = max(10, min(100, (int)($_GET['per'] ?? 20)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($q !== '') {
  $where[] = "(pa.business_name LIKE :q OR pa.email LIKE :q OR pa.name LIKE :q OR pa.business_reg_number LIKE :q)";
  $params[':q'] = '%'.$q.'%';
}
if (in_array($status, ['pending','approved','rejected'], true)) {
  $where[] = "pa.status = :status";
  $params[':status'] = $status;
}
$whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

// count
$cntSql = "SELECT COUNT(*) FROM partner_applications pa $whereSql";
$st = $pdo->prepare($cntSql); $st->execute($params);
$total = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($total/$perPage));

// list
$sql = "
  SELECT
    pa.*,
    r.name AS reviewer_name, r.email AS reviewer_email
  FROM partner_applications pa
  LEFT JOIN users r ON r.id = pa.reviewer_id
  $whereSql
  ORDER BY pa.created_at DESC
  LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':lim', $perPage, PDO::PARAM_INT);
$st->bindValue(':off', $offset,  PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

include __DIR__ . '/../partials/header_admin.php';
?>

<?php if (isset($_GET['msg']) || isset($_GET['err'])): ?>
  <div class="mb-4">
    <?php if (isset($_GET['msg'])): ?>
      <div class="p-3 rounded bg-green-50 text-green-700 text-sm"><?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['err'])): ?>
      <div class="p-3 rounded bg-red-50 text-red-700 text-sm mt-2"><?= htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<section class="mb-6">
  <h1 class="text-2xl font-bold">파트너 관리</h1>
  <p class="text-gray-600 mt-2">신청서를 검토하고 승인/거절을 처리합니다.</p>
</section>

<form method="get" action="/admin/partners.php" class="bg-white border rounded-xl shadow-sm p-4 mb-6">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div>
      <label class="block text-xs text-gray-600 mb-1">검색</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" class="w-full border rounded px-3 py-2" placeholder="상호/이메일/담당자/사업자번호">
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">상태</label>
      <select name="status" class="w-full border rounded px-3 py-2">
        <option value="">전체</option>
        <option value="pending"  <?= $status==='pending'?'selected':''  ?>>대기중</option>
        <option value="approved" <?= $status==='approved'?'selected':'' ?>>승인</option>
        <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>거절</option>
      </select>
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">페이지당</label>
      <select name="per" class="w-full border rounded px-3 py-2">
        <?php foreach ([10,20,30,50,100] as $n): ?>
          <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?>개</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-end">
      <button class="px-4 py-2 rounded bg-primary text-white font-semibold">검색</button>
    </div>
  </div>
</form>

<section class="space-y-4">
  <div class="text-sm text-gray-600">총 <span class="font-semibold"><?= number_format($total) ?></span>건 · <?= $page ?>/<?= $totalPages ?> 페이지</div>

  <?php if (empty($rows)): ?>
    <div class="bg-white border rounded-xl shadow-sm p-8 text-center text-gray-500">신청 내역이 없습니다.</div>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <div class="bg-white border rounded-xl shadow-sm p-4">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <span class="text-lg font-semibold"><?= htmlspecialchars($r['business_name'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="px-2 py-0.5 rounded text-xs font-semibold
                <?= $r['status']==='approved'?'bg-green-100 text-green-700':($r['status']==='rejected'?'bg-red-100 text-red-700':'bg-yellow-100 text-yellow-700') ?>">
                <?= $r['status']==='pending'?'대기중':($r['status']==='approved'?'승인':'거절') ?>
              </span>
            </div>
            <div class="mt-1 text-sm text-gray-700">
              <div>사업자번호: <span class="font-medium"><?= htmlspecialchars($r['business_reg_number'], ENT_QUOTES, 'UTF-8') ?></span></div>
              <div>신청자(담당자): <span class="font-medium"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></span> · <?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?></div>
              <div>연락처: <span class="font-medium"><?= htmlspecialchars($r['contact_phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></div>
              <div>정산계좌: <?= htmlspecialchars(($r['bank_name']??''). ' / '. ($r['bank_account']??''), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="text-gray-600">주소: <?= htmlspecialchars($r['business_address'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
              <div class="text-gray-500">신청일: <?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php if ($r['reviewer_id']): ?>
                <div class="text-gray-500">검토: <?= htmlspecialchars(($r['reviewer_name']??''). ' ('.($r['reviewer_email']??'').')', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($r['reviewed_at'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="w-full md:w-60">
            <div class="bg-gray-50 border rounded-lg p-3">
              <div class="text-xs text-gray-500 mb-1">사업자등록증</div>
              <?php if (!empty($r['business_cert_path'])): ?>
                <a href="<?= htmlspecialchars($r['business_cert_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="inline-flex items-center gap-1 text-primary hover:underline">
                  파일 열기
                </a>
              <?php else: ?>
                <div class="text-sm text-gray-500">첨부 없음</div>
              <?php endif; ?>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
              <?php if ($r['status']==='pending'): ?>
                <form method="post" action="/admin/partner_action.php" onsubmit="return confirm('승인 처리하시겠습니까?');">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                  <button class="px-3 py-1.5 rounded bg-green-600 text-white text-sm">승인</button>
                </form>
                <form method="post" action="/admin/partner_action.php" onsubmit="return confirm('거절 처리하시겠습니까?');">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                  <button class="px-3 py-1.5 rounded bg-red-600 text-white text-sm">거절</button>
                </form>
              <?php else: ?>
                <form method="post" action="/admin/partner_action.php" onsubmit="return confirm('대기중으로 되돌리시겠습니까?');">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="reset">
                  <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                  <button class="px-3 py-1.5 rounded border text-sm">대기중으로 되돌리기</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php
  // pagination
  $qs = $_GET; unset($qs['page']);
  $mk = fn($p)=>'/admin/partners.php?'.http_build_query(array_merge($qs,['page'=>$p]));
  $first=1; $prev=max(1,$page-1); $next=min($totalPages,$page+1); $last=$totalPages;
?>
<nav class="mt-6 flex justify-center">
  <ul class="inline-flex items-center gap-1">
    <li><a class="px-3 py-1.5 border rounded <?= $page==1?'opacity-50 pointer-events-none':'' ?>" href="<?= $mk($first) ?>">« 처음</a></li>
    <li><a class="px-3 py-1.5 border rounded <?= $page==1?'opacity-50 pointer-events-none':'' ?>" href="<?= $mk($prev) ?>">‹ 이전</a></li>
    <?php for ($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
      <li><a class="px-3 py-1.5 border rounded <?= $p==$page?'bg-primary text-white border-primary':'' ?>" href="<?= $mk($p) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
    <li><a class="px-3 py-1.5 border rounded <?= $page==$totalPages?'opacity-50 pointer-events-none':'' ?>" href="<?= $mk($next) ?>">다음 ›</a></li>
    <li><a class="px-3 py-1.5 border rounded <?= $page==$totalPages?'opacity-50 pointer-events-none':'' ?>" href="<?= $mk($last) ?>">끝 »</a></li>
  </ul>
</nav>

<?php include __DIR__ . '/../partials/footer.php'; ?>