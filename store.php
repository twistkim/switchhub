<?php
// /store.php — 파트너 매장 상세 + 상품 목록
declare(strict_types=1);

$pageTitle  = '파트너 매장';
$activeMenu = 'partner_stores';

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/i18n/bootstrap.php';
require_once __DIR__ . '/auth/session.php';
// Google Maps API 설정은 선택사항 (파일이 없어도 동작해야 함)
$mapsCfg = __DIR__ . '/config/maps.php';
if (is_file($mapsCfg)) {
  require_once $mapsCfg; // GOOGLE_MAPS_API_KEY 를 정의할 수 있음
}
if (!defined('GOOGLE_MAPS_API_KEY')) {
  define('GOOGLE_MAPS_API_KEY', '');
}

// i18n 헬퍼 안전 장치 (부재 시 폴백)
if (!function_exists('__')) {
  function __(string $key, array $vars = []) { return $key; }
}
if (!function_exists('lang_url')) {
  function lang_url(string $path): string { return $path; }
}

// 간단 이미지 출력 헬퍼 (webp 여부 무시, 있는 주소 그대로 렌더)
if (!function_exists('img_plain')) {
  function img_plain(string $src = '', string $alt = '', string $class = 'w-full h-40 object-cover'): void {
    if ($src === '') $src = '/assets/placeholder.jpg';
    // 상대경로 보정: / 로 시작하도록
    if (!preg_match('#^(https?:)?//#', $src) && $src[0] !== '/') $src = '/'.$src;
    $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    echo '<img src="' . $src . '" alt="' . $alt . '" class="' . $class . '" loading="lazy">';
  }
}

// 파라미터(id: partner_profiles.id)
$profileId = (int)($_GET['id'] ?? 0);
if ($profileId <= 0) {
  header('HTTP/1.1 400 Bad Request');
  echo 'Invalid store id';
  exit;
}

$pdo = db();

// partner_profiles 스키마 동적 탐색 (환경별 컬럼 차이 허용)
$pfCols = $pdo->query("SHOW COLUMNS FROM partner_profiles")->fetchAll(PDO::FETCH_COLUMN);
$has = function(string $c) use ($pfCols) { return in_array($c, $pfCols, true); };

$sel = [
  'pp.id', 'pp.user_id', 'pp.store_name', 'pp.intro', 'pp.phone', 'pp.is_published'
];
$optCols = [
  'address_line1','address_line2','district','province','postal_code','country_code',
  'lat','lng','place_id','hero_image_url','logo_image_url'
];
foreach ($optCols as $c) {
  if ($has($c)) $sel[] = "pp.$c"; else $sel[] = "NULL AS $c";
}
$selectSql = implode(",\n    ", $sel) . ",\n    u.name AS owner_name, u.email AS owner_email";

$sql = "
  SELECT
    $selectSql
  FROM partner_profiles pp
  JOIN users u ON u.id = pp.user_id
  WHERE pp.id = :id
  LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $profileId]);
$store = $st->fetch(PDO::FETCH_ASSOC);

// 공개 여부: NULL(초기데이터) 또는 1 이면 통과
if (!$store) {
  header('HTTP/1.1 404 Not Found');
  echo 'Store not found';
  exit;
}
if (isset($store['is_published']) && $store['is_published'] !== null && (int)$store['is_published'] !== 1) {
  header('HTTP/1.1 403 Forbidden');
  echo 'Store is not published';
  exit;
}

if (isset($_GET['debug'])) {
  try {
    $uid = (int)$store['user_id'];
    // A) 총 상품수 (seller_id 기준)
    $dbg1 = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id=:uid");
    $dbg1->execute([':uid'=>$uid]);
    $total_all = (int)$dbg1->fetchColumn();

    // B) 소프트 삭제 제외
    $dbg2 = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id=:uid AND (is_deleted IS NULL OR is_deleted=0)");
    $dbg2->execute([':uid'=>$uid]);
    $total_visible = (int)$dbg2->fetchColumn();

    // C) 상태별 카운트
    $dbg3 = $pdo->prepare("SELECT status, COUNT(*) c FROM products WHERE seller_id=:uid GROUP BY status");
    $dbg3->execute([':uid'=>$uid]);
    $by_status = $dbg3->fetchAll(PDO::FETCH_ASSOC);

    echo '<pre style="background:#fffbdd;border:1px solid #f6e05e;padding:8px;margin:8px 0;">'
       . 'DEBUG store.products: user_id=' . htmlspecialchars((string)$uid, ENT_QUOTES, 'UTF-8') . "\n"
       . '  total_all=' . $total_all . '  total_visible=' . $total_visible . "\n"
       . '  by_status=' . htmlspecialchars(json_encode($by_status, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')
       . '</pre>';
  } catch (Throwable $e) {
    echo '<pre style="background:#ffe3e3;border:1px solid #ffb3b3;padding:8px;margin:8px 0;">'
       . 'DEBUG ERROR: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
       . '</pre>';
  }
}

// 주소 문자열 조합
$addressParts = array_filter([
  $store['address_line1'] ?? '',
  $store['address_line2'] ?? '',
  trim(($store['district'] ?? '') . ' ' . ($store['province'] ?? '')),
  $store['postal_code'] ?? ''
]);
$fullAddress = trim(implode(', ', $addressParts));

// 상품 목록 (seller_id = 해당 매장 user_id)
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 24;
$off  = ($page - 1) * $per;

try {
  $sqlCnt = "
    SELECT COUNT(*)
    FROM products p
    WHERE p.seller_id = :uid
      AND (p.is_deleted IS NULL OR p.is_deleted = 0)
  ";
  $st = $pdo->prepare($sqlCnt);
  $st->execute([':uid' => (int)$store['user_id']]);
  $totalProducts = (int)($st->fetchColumn() ?: 0);

  // product_images 존재 여부 확인 + 컬럼 동적 구성 (image_url/url/file_path/path 등 환경차 대응)
  $hasPi = (bool)$pdo->query("SHOW TABLES LIKE 'product_images'")->fetchColumn();
  if ($hasPi) {
    $piCols = $pdo->query("SHOW COLUMNS FROM product_images")->fetchAll(PDO::FETCH_COLUMN);
    $candidates = [];
    foreach (['image_url','url','file_path','path','src'] as $c) {
      if (in_array($c, $piCols, true)) {
        $candidates[] = 'pi.' . $c;
      }
    }
    if (empty($candidates)) {
      // 테이블은 있으나 사용할 수 있는 열이 없으면 NULL 처리
      $imgSelect = 'NULL';
    } else {
      $imgSelect = 'COALESCE(' . implode(',', $candidates) . ')';
    }

    $imgExpr = "(SELECT $imgSelect FROM product_images pi WHERE pi.product_id = p.id ORDER BY ".
               (in_array('is_primary',$piCols,true) ? 'pi.is_primary DESC,' : '').
               (in_array('sort_order',$piCols,true) ? 'pi.sort_order ASC,' : '').
               " pi.id ASC LIMIT 1) AS primary_image_url";
  } else {
    $imgExpr = "NULL AS primary_image_url";
  }

  $sqlProducts = "
    SELECT
      p.id, p.name, p.price, p.status,
      $imgExpr
    FROM products p
    WHERE p.seller_id = :uid
      AND (p.is_deleted IS NULL OR p.is_deleted = 0)
    ORDER BY FIELD(p.status,'on_sale','for_sale','payment_confirmed','shipping','delivered','sold') ASC, p.created_at DESC
    LIMIT :limit OFFSET :offset
  ";
  $st = $pdo->prepare($sqlProducts);
  $st->bindValue(':uid', (int)$store['user_id'], PDO::PARAM_INT);
  $st->bindValue(':limit', $per, PDO::PARAM_INT);
  $st->bindValue(':offset', $off, PDO::PARAM_INT);
  $st->execute();
  $products = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // 문제 발생 시 제품 목록은 비우고, 디버그 요청이면 상세히 출력
  $totalProducts = 0;
  $products = [];
  if (isset($_GET['debug'])) {
    echo '<pre style="background:#fff3cd;border:1px solid #ffeeba;padding:8px;">'.htmlspecialchars('PRODUCTS SQL ERROR: '.$e->getMessage(), ENT_QUOTES, 'UTF-8').'</pre>';
  }
}

// 상태 배지 스타일
function product_status_badge(string $status): string {
  $map = [
    'on_sale'           => ['판매중', 'bg-green-600'],
    'for_sale'          => ['판매중', 'bg-green-600'],
    'payment_pending'   => ['입금 대기', 'bg-yellow-600'],
    'payment_confirmed' => ['입금 확인', 'bg-blue-600'],
    'shipping'          => ['배송중', 'bg-indigo-600'],
    'delivered'         => ['배송 완료', 'bg-emerald-700'],
    'sold'              => ['판매 완료', 'bg-gray-700'],
  ];
  $label = $map[$status][0] ?? $status;
  $color = $map[$status][1] ?? 'bg-gray-600';
  return '<span class="absolute top-2 left-2 text-white text-xs font-semibold px-2.5 py-1 rounded-full z-10 '.$color.'">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

include __DIR__ . '/partials/header.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <!-- 히어로 -->
  <section class="bg-white border rounded-xl overflow-hidden mb-8">
    <div class="relative">
      <?php img_plain($store['hero_image_url'] ?: '/assets/placeholder_wide.jpg', $store['store_name'] ?? '', 'w-full h-56 sm:h-72 object-cover'); ?>
      <?php if (!empty($store['logo_image_url'])): ?>
        <div class="absolute -bottom-8 left-6 w-16 h-16 rounded-2xl overflow-hidden border-4 border-white shadow">
          <?php img_plain($store['logo_image_url'], $store['store_name'] ?? '', 'w-full h-full object-cover'); ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="pt-10 px-6 pb-6 sm:px-8 sm:pb-8">
      <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-extrabold"><?= htmlspecialchars($store['store_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
          <?php if ($fullAddress): ?>
            <div class="mt-2 text-gray-600 flex items-center gap-2">
              <span class="text-sm"><?= htmlspecialchars(__('store.address') ?: 'Address', ENT_QUOTES, 'UTF-8') ?>:</span>
              <span class="text-sm"><?= htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>
          <?php if (!empty($store['phone'])): ?>
            <div class="mt-1 text-gray-600 flex items-center gap-2">
              <span class="text-sm"><?= htmlspecialchars(__('store.phone') ?: 'Phone', ENT_QUOTES, 'UTF-8') ?>:</span>
              <a class="text-sm text-primary hover:underline" href="tel:<?= htmlspecialchars($store['phone'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($store['phone'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          <?php endif; ?>
        </div>

        <div class="text-sm text-gray-600">
          <span class="inline-flex items-center px-3 py-1.5 rounded-md border bg-white">
            <?= htmlspecialchars(__('store.products') ?: 'Products', ENT_QUOTES, 'UTF-8') ?>:
            <strong class="ml-1"><?= number_format($totalProducts) ?></strong>
          </span>
        </div>
      </div>

      <?php if (!empty($store['intro'])): ?>
        <div class="mt-4 text-gray-800 leading-relaxed">
          <div class="font-semibold mb-1"><?= htmlspecialchars(__('store.about') ?: 'About the store', ENT_QUOTES, 'UTF-8') ?></div>
          <div class="whitespace-pre-line"><?= nl2br(htmlspecialchars($store['intro'], ENT_QUOTES, 'UTF-8')) ?></div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- 지도 -->
  <?php if (!empty($store['lat']) && !empty($store['lng'])): ?>
    <section class="bg-white border rounded-xl p-4 mb-8">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold"><?= htmlspecialchars(__('store.location') ?: 'Location', ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (!empty($store['place_id'])): ?>
          <a class="text-sm text-primary hover:underline"
             target="_blank"
             href="https://www.google.com/maps/search/?api=1&query=<?= urlencode((string)$store['lat'] . ',' . (string)$store['lng']) ?>&query_place_id=<?= urlencode($store['place_id']) ?>">
            Google Maps
          </a>
        <?php endif; ?>
      </div>
      <input type="hidden" id="store_lat" value="<?= htmlspecialchars((string)$store['lat'], ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" id="store_lng" value="<?= htmlspecialchars((string)$store['lng'], ENT_QUOTES, 'UTF-8') ?>">
      <div id="store_map" class="w-full h-72 rounded-lg border"></div>
    </section>
  <?php endif; ?>

  <!-- 상품 목록 -->
  <section class="mb-2">
    <h2 class="sr-only">Products</h2>
    <?php if (empty($products)): ?>
      <div class="bg-white border rounded-xl p-6 text-center text-gray-600">
        <?= htmlspecialchars(__('stores.no_results') ?: 'No products found.', ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach ($products as $p):
          $pid   = (int)$p['id'];
          $pname = $p['name'] ?? '';
          $plink = lang_url('/product.php?id=' . $pid);
          $pimg  = $p['primary_image_url'] ?: '/assets/placeholder.jpg';
          $pstatus = $p['status'] ?? '';
          $badge = product_status_badge($pstatus);
        ?>
        <a href="<?= htmlspecialchars($plink, ENT_QUOTES, 'UTF-8') ?>" class="block group bg-white border rounded-lg overflow-hidden hover:shadow-md transition">
          <div class="relative">
            <?php img_plain($pimg, $pname, 'w-full h-56 object-cover'); ?>
            <?= $badge ?>
          </div>
          <div class="p-4">
            <h3 class="font-semibold truncate"><?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="mt-2 flex items-center justify-between">
              <div class="font-bold text-primary"><?= number_format((float)($p['price'] ?? 0)) ?> THB</div>
              <span class="text-sm text-gray-500 group-hover:text-gray-700">자세히 보기 →</span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <?php
        $last = max(1, (int)ceil($totalProducts / $per));
        if ($last > 1):
          $base = lang_url('/store.php?id=' . $profileId);
          $prev = max(1, $page - 1);
          $next = min($last, $page + 1);
      ?>
      <div class="mt-8 flex items-center justify-center gap-2">
        <a class="px-3 py-1.5 rounded border <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
           href="<?= htmlspecialchars($base . '&page=' . $prev, ENT_QUOTES, 'UTF-8') ?>">«</a>
        <span class="text-sm text-gray-600"><?= $page ?> / <?= $last ?></span>
        <a class="px-3 py-1.5 rounded border <?= $page >= $last ? 'pointer-events-none opacity-50' : '' ?>"
           href="<?= htmlspecialchars($base . '&page=' . $next, ENT_QUOTES, 'UTF-8') ?>">»</a>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?php if (!empty($store['lat']) && !empty($store['lng'])): ?>
  <script>
    function initMapStore() {
      const lat = parseFloat(document.getElementById('store_lat').value);
      const lng = parseFloat(document.getElementById('store_lng').value);
      const center = { lat, lng };
      const map = new google.maps.Map(document.getElementById('store_map'), { center, zoom: 15 });
      new google.maps.Marker({ position: center, map });
    }
  </script>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&callback=initMapStore" async defer></script>
<?php endif; ?>