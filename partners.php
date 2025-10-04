<?php
// /partners.php — 파트너 매장 목록
declare(strict_types=1);

$pageTitle  = '파트너 매장';
$activeMenu = 'partner_stores';

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/i18n/bootstrap.php';   // lang_url(), __() 사용
require_once __DIR__ . '/auth/session.php';     // 로그인 여부 표시용(필수 아님)

// 간단 이미지 출력 헬퍼 (webp든 jpg든 그대로 렌더)
if (!function_exists('img_plain')) {
  function img_plain(string $src = '', string $alt = '', string $class = 'w-full h-40 object-cover'): void {
    if ($src === '') $src = '/assets/placeholder.jpg';
    $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    echo '<img src="' . $src . '" alt="' . $alt . '" class="' . $class . '" loading="lazy">';
  }
}

// 쿼리 파라미터
$q    = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 24;
$off  = ($page - 1) * $per;

$pdo = db();

// 검색용 WHERE
$whereSql = "pp.is_published=1 AND u.role='partner'";
$params   = [];
if ($q !== '') {
  $whereSql .= " AND (pp.store_name LIKE :like OR pp.province LIKE :like OR pp.district LIKE :like)";
  $params[':like'] = '%' . $q . '%';
}

// 총 개수
$sqlCount = "
  SELECT COUNT(*) AS cnt
  FROM partner_profiles pp
  JOIN users u ON u.id = pp.user_id
  WHERE $whereSql
";
$st = $pdo->prepare($sqlCount);
$st->execute($params);
$total = (int)($st->fetchColumn() ?: 0);

// 목록
$sql = "
  SELECT
    pp.id, pp.user_id, pp.store_name, pp.logo_image_url, pp.hero_image_url,
    pp.province, pp.district,
    (SELECT COUNT(*) FROM products p
      WHERE p.seller_id = pp.user_id
        AND (p.is_deleted IS NULL OR p.is_deleted = 0)
    ) AS product_count
  FROM partner_profiles pp
  JOIN users u ON u.id = pp.user_id
  WHERE $whereSql
  ORDER BY pp.store_name ASC
  LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
$st->bindValue(':limit', $per, PDO::PARAM_INT);
$st->bindValue(':offset', $off, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex items-center justify-between gap-3 flex-wrap mb-6">
    <h1 class="text-2xl font-bold"><?= htmlspecialchars(__('stores.title') ?: 'Partner Stores', ENT_QUOTES, 'UTF-8') ?></h1>

    <form class="flex items-center gap-2 ml-auto" method="get" action="">
      <input
        type="text"
        name="q"
        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
        class="w-64 max-w-full border rounded-md px-3 py-2"
        placeholder="<?= htmlspecialchars(__('stores.search_placeholder') ?: 'Search store / area', ENT_QUOTES, 'UTF-8') ?>"
      >
      <button class="px-4 py-2 rounded-md bg-primary text-white">
        <?= htmlspecialchars(__('common.search') ?: 'Search', ENT_QUOTES, 'UTF-8') ?>
      </button>
    </form>
  </div>

  <?php if (empty($rows)): ?>
    <div class="bg-white border rounded-lg p-6 text-center text-gray-600">
      <?= htmlspecialchars(__('stores.no_results') ?: 'No partner stores found.', ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <?php foreach ($rows as $r):
        $storeId   = (int)$r['id'];
        $link      = lang_url('/store.php?id=' . $storeId);
        $storeName = $r['store_name'] ?? '';
        $area      = trim(implode(' ', array_filter([$r['province'] ?? '', $r['district'] ?? ''])));
        $logo      = $r['logo_image_url'] ?: '';
        $hero      = $r['hero_image_url'] ?: '';
        $productCount = (int)$r['product_count'];
      ?>
      <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" class="group block bg-white border rounded-lg overflow-hidden hover:shadow-md transition">
        <div class="relative">
          <?php img_plain($hero ?: '/assets/placeholder_wide.jpg', $storeName, 'w-full h-40 object-cover'); ?>
          <?php if ($logo): ?>
            <div class="absolute -bottom-6 left-4 w-12 h-12 rounded-xl overflow-hidden border-2 border-white shadow">
              <?php img_plain($logo, $storeName, 'w-full h-full object-cover'); ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="pt-8 px-4 pb-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="font-semibold truncate"><?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') ?></h3>
            <span class="text-xs inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700">
              <?= htmlspecialchars(__('stores.product_count') ?: 'Products', ENT_QUOTES, 'UTF-8') ?>: <?= number_format($productCount) ?>
            </span>
          </div>
          <?php if ($area): ?>
            <div class="mt-1 text-sm text-gray-500 truncate"><?= htmlspecialchars($area, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <div class="mt-3">
            <span class="inline-flex items-center px-3 py-1.5 text-sm rounded-md border bg-white group-hover:bg-gray-50">
              <?= htmlspecialchars(__('stores.view_store') ?: 'View store', ENT_QUOTES, 'UTF-8') ?> →
            </span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- 간단한 페이지네이션 -->
    <?php
      $last = max(1, (int)ceil($total / $per));
      if ($last > 1):
        // 페이지 링크 생성
        $base = lang_url('/partners.php');
        $qs   = $q !== '' ? '&q=' . urlencode($q) : '';
        $prev = max(1, $page - 1);
        $next = min($last, $page + 1);
    ?>
    <div class="mt-8 flex items-center justify-center gap-2">
      <a class="px-3 py-1.5 rounded border <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
         href="<?= htmlspecialchars($base . '?page=' . $prev . $qs, ENT_QUOTES, 'UTF-8') ?>">«</a>
      <span class="text-sm text-gray-600"><?= $page ?> / <?= $last ?></span>
      <a class="px-3 py-1.5 rounded border <?= $page >= $last ? 'pointer-events-none opacity-50' : '' ?>"
         href="<?= htmlspecialchars($base . '?page=' . $next . $qs, ENT_QUOTES, 'UTF-8') ?>">»</a>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>