<?php
// /product.php
$pageTitle = '상품 상세';
$activeMenu = 'home';


require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/i18n/bootstrap.php';
require_once __DIR__ . '/lib/db.php';

 

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header('Location: /'); exit;
}

$me = $_SESSION['user'] ?? null;
$pdo = db();

// 상품 조회 (+ 접근 허용: 승인된 상품 or (관리자) or (해당 파트너 본인))
$sql = "
  SELECT p.*, c.name AS category_name, u.name AS seller_name, u.role AS seller_role
  FROM products p
  JOIN categories c ON c.id = p.category_id
  JOIN users u ON u.id = p.seller_id
  WHERE p.id = :id
  LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $id]);
$p = $st->fetch();
if (!$p) { header('Location: /'); exit; }

// 소프트 삭제 상품 접근 제어: 일반 사용자는 차단, 관리자/해당 파트너는 열람 허용
$isAdmin = $me && ($me['role'] === 'admin');
$isOwner = $me && ($me['role'] === 'partner') && ((int)$me['id'] === (int)$p['seller_id']);
if (!empty($p['is_deleted']) && !$isAdmin && !$isOwner) {
  http_response_code(404);
  $pageTitle = __('product.not_found_title');
  include __DIR__ . '/partials/header.php';
  echo '<div class="bg-white border rounded-xl p-8 text-center text-gray-600">' . htmlspecialchars(__('product.not_found'), ENT_QUOTES, 'UTF-8') . '</div>';
  include __DIR__ . '/partials/footer.php';
  exit;
}

$canView = ($p['approval_status'] === 'approved') || $isAdmin || $isOwner;

if (!$canView) {
  http_response_code(403);
  $pageTitle = __('product.private_title');
  include __DIR__ . '/partials/header.php';
  echo '<div class="bg-white border rounded-xl p-8 text-center text-gray-600">' . htmlspecialchars(__('product.not_approved'), ENT_QUOTES, 'UTF-8') . '</div>';
  include __DIR__ . '/partials/footer.php';
  exit;
}

// 이미지 목록
$imgs = $pdo->prepare("
  SELECT image_url, is_primary, sort_order
  FROM product_images
  WHERE product_id = :pid
  ORDER BY is_primary DESC, sort_order ASC, id ASC
  LIMIT 5
");
$imgs->execute([':pid' => $id]);
$images = $imgs->fetchAll();

include __DIR__ . '/partials/header.php';
?>
<div class="bg-white border rounded-xl shadow-sm p-6">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- 좌측: 대표 이미지 + 썸네일 -->
    <div>
      <div class="aspect-[4/3] w-full overflow-hidden rounded-lg border bg-gray-50">
        <img id="mainImage" src="<?= htmlspecialchars($images[0]['image_url'] ?? $p['detail_image_url'] ?? 'https://placehold.co/800x600?text=No+Image', ENT_QUOTES, 'UTF-8') ?>"
             class="w-full h-full object-cover" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <?php if (!empty($images)): ?>
        <div class="mt-3 grid grid-cols-5 gap-2">
          <?php foreach ($images as $i => $im): ?>
            <button type="button" class="border rounded-md overflow-hidden hover:opacity-80"
                    onclick="switchImage('<?= htmlspecialchars($im['image_url'], ENT_QUOTES, 'UTF-8') ?>')">
              <img src="<?= htmlspecialchars($im['image_url'], ENT_QUOTES, 'UTF-8') ?>" class="w-full h-20 object-cover" />
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- 우측: 정보 -->
    <div>
      <h1 class="text-2xl font-bold"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="mt-1 text-sm text-gray-500">
        <?= htmlspecialchars(__('product.category'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($p['category_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(__('product.release_year'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($p['release_year'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="mt-4 text-3xl font-extrabold text-primary"><?= number_format((float)$p['price']) ?> <?= htmlspecialchars(__('product.price.unit'), ENT_QUOTES, 'UTF-8') ?></div>

      <div class="mt-2">
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
          <?= $p['status']==='on_sale'?'bg-green-100 text-green-700':($p['status']==='negotiating'?'bg-yellow-100 text-yellow-700':'bg-gray-100 text-gray-700') ?>">
          <?= $p['status']==='on_sale' 
                ? __('product.status.for_sale') 
                : ($p['status']==='negotiating' 
                    ? __('product.status.pending') 
                    : __('product.status.sold')) ?>
        </span>
        <?php if ($p['approval_status'] !== 'approved'): ?>
          <span class="ml-2 inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700"><?= htmlspecialchars(__('product.approval'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($p['approval_status'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>

      <p class="mt-4 whitespace-pre-wrap text-gray-700"><?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

      <?php $canAddToCart = (empty($p['is_deleted']) && $p['approval_status'] === 'approved' && $p['status'] === 'on_sale'); ?>
      <?php $showMessageBtn = (!$isOwner && !$isAdmin); ?>
      <div class="mt-6 flex gap-2">
        <?php if ($canAddToCart): ?>
          <a href="/cart_add.php?product_id=<?= (int)$p['id'] ?>" class="px-5 py-2.5 bg-primary text-white rounded-lg font-semibold"><?= htmlspecialchars(__('product.add_to_cart'), ENT_QUOTES, 'UTF-8') ?></a>
        <?php else: ?>
          <button type="button" class="px-5 py-2.5 rounded-lg font-semibold border text-gray-400 cursor-not-allowed" title="<?= htmlspecialchars(__('product.add_to_cart_unavailable'), ENT_QUOTES, 'UTF-8') ?>" disabled><?= htmlspecialchars(__('product.add_to_cart_disabled'), ENT_QUOTES, 'UTF-8') ?></button>
        <?php endif; ?>
        <?php if ($showMessageBtn): ?>
          <a href="/message_start.php?product_id=<?= (int)$p['id'] ?>"
             class="px-5 py-2.5 border border-primary text-primary rounded-lg font-semibold hover:bg-primary/5">
            <?= htmlspecialchars(__('product.message_partner') ?: '파트너에게 문의', ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endif; ?>
        <a href="/index.php" class="px-5 py-2.5 border rounded-lg"><?= htmlspecialchars(__('common.back_to_list'), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </div>
  </div>

  <?php if (!empty($p['detail_image_url'])): ?>
    <div class="mt-8">
      <img src="<?= htmlspecialchars($p['detail_image_url'], ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border" alt="<?= htmlspecialchars(__('product.detail_image_alt'), ENT_QUOTES, 'UTF-8') ?>">
    </div>
  <?php endif; ?>
</div>

<script>
  function switchImage(url) {
    const el = document.getElementById('mainImage');
    if (el) el.src = url;
  }
</script>

<?php
$productId = (int)($_GET['id'] ?? 0);

 
 
try {
  include __DIR__ . '/partials/reviews_block.php';
} catch (Throwable $e) {
  error_log('[product.php reviews_block] ' . $e->getMessage());
  echo '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:8px;margin-top:8px">'
     . '<strong>REVIEWS BLOCK ERROR</strong><br>'
     . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
     . '</div>';
}
 
?>

<?php include __DIR__ . '/partials/footer.php'; ?>