<?php
$pageTitle = '폰스위치허브 - 홈';
$activeMenu = 'home';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/auth/session.php';      // 있으면
require_once __DIR__ . '/i18n/bootstrap.php';

// 입력 파라미터
$q   = isset($_GET['q'])   ? trim($_GET['q'])   : '';
$cat = isset($_GET['cat']) ? (int)$_GET['cat']  : 0;

// 카테고리 조회
$pdo = db();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// 상품 조회: 판매중(on_sale)만
$sql = "
  SELECT
    p.id, p.name, p.description, p.price, p.status, p.release_year, p.category_id, p.seller_id,
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

<!-- 헤드라인 & 검색 -->
<section class="text-center mb-10">
  <h1 class="text-3xl sm:text-4xl font-extrabold"><?= __('home.headline') ?></h1>
  <p class="mt-3 text-gray-600"><?= __('home.subhead') ?></p>

  <!-- 검색폼 -->
  <form id="searchForm" method="get" action="/index.php" class="mt-6 max-w-2xl mx-auto">
    <div class="flex items-stretch gap-2">
      <input
        type="text"
        name="q"
        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="<?= __('home.search_placeholder') ?>"
        class="flex-1 px-5 py-3 border border-gray-300 rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-primary"
      />
      <?php if ($cat > 0): ?>
        <input type="hidden" name="cat" value="<?= (int)$cat ?>">
      <?php endif; ?>
      <button type="submit" class="px-5 py-3 rounded-full bg-primary text-white font-semibold"><?= __('home.search_button') ?></button>
    </div>
  </form>
</section>

<!-- 카테고리 필터 -->
<section class="mb-6">
  <div class="flex flex-wrap gap-2 justify-center">
    <?php
      $link = '/index.php';
      $isAll = ($cat === 0);
    ?>
    <a href="<?= $link ?>" class="px-4 py-2 rounded-full border <?= $isAll ? 'bg-primary text-white border-primary' : 'bg-white hover:bg-gray-50' ?>">전체</a>
    <?php foreach ($categories as $c): 
      $isActive = ($cat === (int)$c['id']);
      $qs = http_build_query(array_filter(['q'=>$q, 'cat'=>(int)$c['id']], fn($v)=>$v!=='' && $v!==0));
    ?>
      <a href="/index.php?<?= $qs ?>"
         class="px-4 py-2 rounded-full border <?= $isActive ? 'bg-primary text-white border-primary' : 'bg-white hover:bg-gray-50' ?>">
        <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

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
        $img = $p['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
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
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $name ?>" class="w-full h-56 object-cover<?= $imgExtra ?>">
            <span class="absolute top-2 left-2 <?= $badgeClass ?> text-white text-xs font-semibold px-2.5 py-1 rounded-full z-10"><?= $badgeLabel ?></span>
          </a>
          <!-- 정보 -->
          <div class="p-4">
            <h3 class="text-lg font-bold text-gray-800 truncate"><a href="/product.php?id=<?= (int)$p['id'] ?>" class="hover:underline"><?= $name ?></a></h3>
            <p class="text-gray-600 mt-1 h-10 overflow-hidden text-ellipsis text-sm"><?= $desc ?></p>
 

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