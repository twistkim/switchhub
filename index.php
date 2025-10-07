<?php
$pageTitle = '폰스위치허브 - 홈';
$activeMenu = 'home';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/auth/session.php';      // 있으면
require_once __DIR__ . '/i18n/bootstrap.php';
require_once __DIR__ . '/partials/splash_logo.php';

// 결제방식 배지 렌더러 로드 (partial 우선, 없으면 폴백)
@include_once __DIR__ . '/partials/product_payment_badges.php';
if (!function_exists('render_payment_badges')) {
  function render_payment_badges(?array $product): void {
    if (!$product) return;
    $badges = [];
    if ((int)($product['payment_normal'] ?? 0) === 1) $badges[] = '일반판매';
    if ((int)($product['payment_cod'] ?? 0) === 1)    $badges[] = 'COD';
    if (!$badges) return;
    ?>
    <div class="mt-2 flex flex-wrap gap-1.5" id="payment-badges">
      <?php foreach ($badges as $b): ?>
        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-800 text-[11px] font-medium">
          <?= htmlspecialchars($b, ENT_QUOTES, 'UTF-8') ?>
        </span>
      <?php endforeach; ?>
    </div>
    <?php
  }
}

// === 이미지 경로 보정: 상대경로 -> 절대경로(/로 시작) ===
if (!function_exists('fix_img_src')) {
  function fix_img_src(string $src): string {
    $src = trim($src);
    if ($src === '') return '/assets/placeholder.jpg';
    // 이미 절대 URL이면 그대로
    $scheme = parse_url($src, PHP_URL_SCHEME);
    if ($scheme === 'http' || $scheme === 'https' || str_starts_with($src, '//')) {
      return $src;
    }
    // 쿼리/해시 제거 없이, 경로만 보정
    // ./ 또는 ../ 제거
    $src = preg_replace('#^\./#', '', $src);
    while (strpos($src, '../') === 0) {
      $src = substr($src, 3);
    }
    // 언어 프리픽스(/ko, /en 등) 아래에서 상대경로가 /ko/uploads로 잘못 붙는 문제 방지
    if ($src[0] !== '/') {
      $src = '/' . $src; // 절대경로로 변환
    }
    // 중복 슬래시 축소 (http:// 패턴은 위에서 걸러서 안전)
    $src = preg_replace('#/{2,}#', '/', $src);
    return $src;
  }
}

// === webp 표시 + jpg 폴백 인라인 헬퍼 ===
if (!function_exists('img_with_webp_fallback')) {
  function img_with_webp_fallback(string $src, string $alt = '', string $class = 'w-full h-56 object-cover'): void {
    // 1) 경로 보정
    $fixed = fix_img_src($src);

    // 2) serve_img 엔드포인트 사용 조건
    //   - .webp 확장자이거나
    //   - /uploads/ 아래 자산(운영상 업로드 자산은 동적 폴백을 거치도록 일괄 처리)
    $useServe = false;
    if (preg_match('/\.webp(\?|$)/i', $fixed)) {
      $useServe = true;
    } elseif (str_starts_with($fixed, '/uploads/')) {
      $useServe = true;
    }

    // 3) 최종 src 결정
    $finalSrc = $useServe
      ? ('/tools/serve_img.php?src=' . rawurlencode($fixed))
      : $fixed;

    // 4) 출력 (에러 시 플레이스홀더 폴백)
    $altEsc = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    $srcEsc = htmlspecialchars($finalSrc, ENT_QUOTES, 'UTF-8');
    $classEsc = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    echo '<img src="' . $srcEsc . '" alt="' . $altEsc . '" class="' . $classEsc . '" loading="lazy" decoding="async"'
       . ' onerror="this.onerror=null;this.src=\'/assets/placeholder.jpg\';"' 
       . '>';
  }
}
// === /webp helper ===

// 입력 파라미터
$q   = isset($_GET['q'])   ? trim($_GET['q'])   : '';
$cat = isset($_GET['cat']) ? (int)$_GET['cat']  : 0;

// 카테고리 조회 (강화판): parent_id 포함 + 컬럼/데이터 상황에 따른 폴백
$pdo = db();
$categories = [];

function load_categories_strong(PDO $pdo): array {
  // 1) 기본 쿼리: is_deleted 지원 + parent_id 정규화
  try {
    $q1 = "SELECT id, name, IFNULL(parent_id, 0) AS parent_id
             FROM categories
            WHERE (is_deleted IS NULL OR is_deleted = 0)
            ORDER BY COALESCE(parent_id, 0), name";
    $rows = $pdo->query($q1)->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) return $rows;
  } catch (Throwable $e) {
    error_log('CATS q1 fail: ' . $e->getMessage());
  }

  // 2) 폴백: is_deleted 없는 스키마
  try {
    $q2 = "SELECT id, name, IFNULL(parent_id, 0) AS parent_id
             FROM categories
            ORDER BY COALESCE(parent_id, 0), name";
    $rows = $pdo->query($q2)->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) return $rows;
  } catch (Throwable $e) {
    error_log('CATS q2 fail: ' . $e->getMessage());
  }

  // 3) 폴백: parent_id 컬럼명이 다른 경우 (SHOW COLUMNS로 탐색)
  try {
    $cols = $pdo->query('SHOW COLUMNS FROM categories')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_map(fn($c) => strtolower((string)($c['Field'] ?? '')), $cols);
    $parentKeys = ['parent_id','parent','pid','p_id','parent_category_id','parentcategoryid','parentcategory','parentid'];
    $parentKey = null;
    foreach ($parentKeys as $pk) {
      if (in_array($pk, $names, true)) { $parentKey = $pk; break; }
    }
    if ($parentKey === null) {
      // parent 키가 없으면 전부 루트 취급하되 최소 셀렉터가 동작하도록 반환
      $rows = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rows as &$r) { $r['parent_id'] = 0; }
      return $rows;
    }

    // 부모키를 찾아서 별칭 parent_id로 정규화
    $q3 = "SELECT id, name, IFNULL(`{$parentKey}`, 0) AS parent_id FROM categories ORDER BY COALESCE(`{$parentKey}`,0), name";
    $rows = $pdo->query($q3)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $rows;
  } catch (Throwable $e) {
    error_log('CATS q3 fail: ' . $e->getMessage());
  }

  return [];
}

$categories = load_categories_strong($pdo);

// 상품 조회: 판매중(on_sale)만
$sql = "
  SELECT
    p.id, p.name, p.description, p.price, p.status, p.release_year, p.category_id, p.seller_id,
    p.payment_normal, p.payment_cod,
    (
      SELECT pi.image_url
      FROM product_images pi
      WHERE pi.product_id = p.id
      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
      LIMIT 1
    ) AS primary_image_url
  FROM products p
  WHERE p.is_deleted = 0
    AND p.status IN ('on_sale','sold')
";
$params = [];

if ($cat > 0) {
  $sql .= " AND p.category_id = :cat";
  $params[':cat'] = $cat;
}
if ($q !== '') {
  $sql .= " AND (p.name LIKE :q1 OR p.description LIKE :q2)";
  $params[':q1'] = '%' . $q . '%';
  $params[':q2'] = '%' . $q . '%';
}

$sql .= " ORDER BY FIELD(p.status,'on_sale','sold') ASC, p.created_at DESC LIMIT 48";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include __DIR__ . '/partials/header.php';
?>






<?php require __DIR__ . '/partials/search_section.php'; ?>

<!-- 상품 그리드 -->
<section>
  <?php if (empty($products)): ?>
    <div class="text-center text-gray-500 py-20"><?= __('home.no_products') ?></div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <?php foreach ($products as $p): 
        $name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 90, '...', 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $price = number_format((float)$p['price']);
        $imgRaw = $p['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
        $img = fix_img_src($imgRaw);
      ?>
      <?php
        // 메시지 버튼 노출 조건: 판매자 본인/관리자는 숨김
        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        $userRole = isset($_SESSION['user']['role']) ? strtolower((string)$_SESSION['user']['role']) : '';
        $sellerId = isset($p['seller_id']) ? (int)$p['seller_id'] : 0;
        $isOwner = ($userId > 0 && $userId === $sellerId);
        $isAdmin = ($userRole === 'admin');
        $showMessageBtn = (!$isOwner && !$isAdmin);
      ?>
      <?php
        $status = $p['status'];
        $badgeLabel = __('home.badge_on_sale');
        $badgeClass = 'bg-green-500';
        $imgExtra = '';
        if ($status === 'sold') { $badgeLabel = __('home.badge_sold'); $badgeClass = 'bg-red-600'; $imgExtra = ' opacity-60'; }
        elseif ($status === 'negotiating') { $badgeLabel = __('home.badge_negotiating'); $badgeClass = 'bg-yellow-500'; }
      ?>
      <div class="group">
        <div class="bg-white border rounded-lg shadow-sm overflow-hidden transform group-hover:scale-[1.01] group-hover:shadow-md transition-all duration-200">
          <!-- 이미지 -->
          <a href="/product.php?id=<?= (int)$p['id'] ?>" class="block relative">
            <?php img_with_webp_fallback($img, $name, 'w-full h-56 object-cover' . $imgExtra); ?>
            <div class="absolute top-2 left-2 flex flex-wrap items-center gap-1 z-10">
              <span class="<?= $badgeClass ?> text-white text-xs font-semibold px-2.5 py-1 rounded-full"><?= $badgeLabel ?></span>
              <?php if ((int)($p['payment_normal'] ?? 0) === 1): ?>
                <span class="bg-white/90 text-gray-800 text-[11px] font-medium px-2 py-0.5 rounded">일반판매</span>
              <?php endif; ?>
              <?php if ((int)($p['payment_cod'] ?? 0) === 1): ?>
                <span class="bg-white/90 text-gray-800 text-[11px] font-medium px-2 py-0.5 rounded">COD</span>
              <?php endif; ?>
            </div>
          </a>
          <!-- 정보 -->
          <div class="p-4">
            <h3 class="text-lg font-bold text-gray-800 truncate"><a href="/product.php?id=<?= (int)$p['id'] ?>" class="hover:underline"><?= $name ?></a></h3>
            <p class="text-gray-600 mt-1 h-10 overflow-hidden text-ellipsis text-sm"><?= $desc ?></p>
            <?php if (function_exists('render_payment_badges')) render_payment_badges($p ?? null); ?>
 

            <div class="mt-4 flex items-center justify-between gap-2">
              <p class="text-xl font-bold text-primary"><?= $price ?> THB</p>
              <div class="flex items-center gap-2">
                <a href="/product.php?id=<?= (int)$p['id'] ?>" class="px-4 py-2 bg-primary-50 text-primary text-sm font-semibold rounded-md">
                  <?= __('home.details') ?>
                </a>
                <?php if ($showMessageBtn): ?>
                  <a href="/message_start.php?product_id=<?= (int)$p['id'] ?>"
                     class="px-4 py-2 border border-primary text-primary text-sm font-semibold rounded-md hover:bg-primary/5">
                    <?= htmlspecialchars(__('product.message_partner') ?: '파트너에게 문의', ENT_QUOTES, 'UTF-8') ?>
                  </a>
                <?php endif; ?>
              </div>
            </div>
            
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- 입력 즉시 검색 폼 자동 제출(300ms 디바운스) -->
<script>
(function(){
  const form = document.getElementById('searchForm');
  if (!form) return;
  const input = form.querySelector('input[name="q"]');
  let t = null;
  input.addEventListener('input', () => {
    if (t) clearTimeout(t);
    t = setTimeout(() => form.submit(), 300);
  });
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>