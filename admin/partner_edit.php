<?php
// 관리자 전용 — 파트너 전체 수정 (신청 폼과 동일 구성)
// GET: ?id=partner_profiles.id  (권장)  |  또는 ?app_id=partner_applications.id (대체)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
$pageTitle = '관리자 대시보드 - 파트너 수정';
$activeMenu = 'partners';
require_role('admin');

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$pdo = db();
// 테이블 컬럼 존재 확인 (스키마 차이 방어)
function _has_column(PDO $pdo, string $table, string $column): bool {
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $st->execute([':t'=>$table, ':c'=>$column]);
    return (int)$st->fetchColumn() > 0;
  } catch (Throwable $e) {
    return false;
  }
}
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$appId     = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;

$pp = null;  // partner_profiles
$pa = null;  // partner_applications (최신 승인건 or 지정 app_id)

try {
  if ($profileId > 0) {
    $st = $pdo->prepare("SELECT * FROM partner_profiles WHERE id=:id LIMIT 1"); // ✅ 스키마에 맞게 조정 가능
    $st->execute([':id'=>$profileId]);
    $pp = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pp) throw new RuntimeException('파트너 프로필을 찾을 수 없습니다.');
  }

  if ($appId > 0) {
    $st = $pdo->prepare("SELECT * FROM partner_applications WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$appId]);
    $pa = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    // If application has a linked profile, load it so the form reflects latest profile values
    if ($pa) {
      if (!empty($pa['profile_id'])) {
        $st2 = $pdo->prepare("SELECT * FROM partner_profiles WHERE id=:pid LIMIT 1");
        $st2->execute([':pid'=>$pa['profile_id']]);
        $pp = $st2->fetch(PDO::FETCH_ASSOC) ?: $pp;
      } elseif (!empty($pa['partner_id'])) {
        // Some schemas may store the profile id under partner_id
        $st2 = $pdo->prepare("SELECT * FROM partner_profiles WHERE id=:pid LIMIT 1");
        $st2->execute([':pid'=>$pa['partner_id']]);
        $pp = $st2->fetch(PDO::FETCH_ASSOC) ?: $pp;
      } elseif (_has_column($pdo, 'partner_profiles', 'user_id') && !empty($pa['user_id'])) {
        // Fallback: match by user if both sides have the column
        $st2 = $pdo->prepare("SELECT * FROM partner_profiles WHERE user_id=:uid ORDER BY id DESC LIMIT 1");
        $st2->execute([':uid'=>$pa['user_id']]);
        $pp = $st2->fetch(PDO::FETCH_ASSOC) ?: $pp;
      }
    }
  } elseif ($pp) {
    // 프로필과 연결된 최신 승인 신청서 보조 로드(있으면 폼 참고용)
    if (_has_column($pdo, 'partner_applications', 'profile_id')) {
      $st = $pdo->prepare("
        SELECT * FROM partner_applications
        WHERE profile_id = :pid AND status='approved'
        ORDER BY reviewed_at DESC, id DESC
        LIMIT 1
      ");
      $st->execute([':pid'=>$pp['id']]);
      $pa = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } elseif (isset($pp['user_id']) && _has_column($pdo, 'partner_applications', 'user_id')) {
      $st = $pdo->prepare("
        SELECT * FROM partner_applications
        WHERE user_id = :uid AND status='approved'
        ORDER BY reviewed_at DESC, id DESC
        LIMIT 1
      ");
      $st->execute([':uid'=>$pp['user_id']]);
      $pa = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } else {
      // 연결 가능한 컬럼이 없으면 조용히 스킵 (폼은 $pp 값만으로 표시)
      $pa = null;
    }
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo '<pre>'.e($e->getMessage()).'</pre>';
  exit;
}

// preserve dbg across POST/redirects
$back = $_GET['return'] ?? '/admin/partners.php';
$dbg = (isset($_GET['dbg']) && $_GET['dbg'] == '1');
if ($dbg) {
  if (strpos($back, 'dbg=1') === false) {
    $back .= (strpos($back, '?') !== false ? '&' : '?') . 'dbg=1';
  }
}
include __DIR__ . '/../partials/header_admin.php'; ?>
<?php
  if ($dbg && isset($_SESSION['__PU_DEBUG_READY']) && !empty($_SESSION['__PU_DEBUG'])) {
    $logs = $_SESSION['__PU_DEBUG'];
    unset($_SESSION['__PU_DEBUG_READY'], $_SESSION['__PU_DEBUG']);
    echo "<script>(function(){try{var L=".json_encode($logs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).";if(Array.isArray(L)){for(var i=0;i<L.length;i++){console.log(L[i]);}}}catch(e){console.warn('debug render error', e);}})();</script>";
  }
?>
<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl sm:text-3xl font-bold">파트너 정보 수정</h1>
    <a href="<?= e($back) ?>" class="text-sm underline">← 목록으로</a>
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="mt-4 rounded bg-green-50 border border-green-200 text-green-800 px-4 py-3">저장되었습니다.</div>
  <?php endif; ?>

  <form method="post" action="/admin/partner_update.php<?= $dbg ? '?dbg=1' : '' ?>" enctype="multipart/form-data" class="mt-6 border rounded-lg p-4">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="return" value="<?= e($back) ?>">
    <?php if ($dbg): ?><input type="hidden" name="dbg" value="1"><?php endif; ?>
    <?php if ($pp): ?>
      <input type="hidden" name="id" value="<?= (int)$pp['id'] ?>">
    <?php elseif ($appId): ?>
      <input type="hidden" name="app_id" value="<?= (int)$appId ?>">
    <?php endif; ?>

    <?php
      // 폼 파셜 include — $pp(현재값), $pa(신청서 보조값)를 넘겨서 프리필
      $formPath = __DIR__ . '/_partner_form_apply_like.php';
      if (is_file($formPath)) {
        $ctx = ['pp'=>$pp, 'pa'=>$pa];
        include $formPath;
    ?>
    <?php
      } else {
        // Fallback: 간이 폼 렌더(신청폼 유사)
        function f_val($key, $pp, $pa, $fallback=''){
          if (is_array($pp) && array_key_exists($key,$pp) && $pp[$key] !== null && $pp[$key] !== '') return $pp[$key];
          $map = [
            'store_name'     => ['business_name','name'],
            'phone'          => ['contact_phone','phone'],
            'address_line1'  => ['store_address','business_address','address'],
            'lat'            => ['store_lat','lat'],
            'lng'            => ['store_lng','lng'],
            'place_id'       => ['store_place_id','place_id'],
            'hero_image_url' => ['hero_image_url'],
            'logo_image_url' => ['logo_image_url'],
            'intro'          => ['intro','description'],
          ];
          if (is_array($pa) && isset($map[$key])) {
            foreach ($map[$key] as $cand) {
              if (array_key_exists($cand,$pa) && $pa[$cand] !== null && $pa[$cand] !== '') return $pa[$cand];
            }
          }
          return $fallback;
        }
        ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <label class="block">
            <span class="text-gray-600">상호 (store_name)</span>
            <input name="store_name" value="<?= e(f_val('store_name',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block">
            <span class="text-gray-600">대표전화 (phone)</span>
            <input name="phone" value="<?= e(f_val('phone',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block md:col-span-2">
            <span class="text-gray-600">매장주소 (address_line1)</span>
            <input name="address_line1" value="<?= e(f_val('address_line1',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block md:col-span-2">
            <span class="text-gray-600">주소2 (address_line2)</span>
            <input name="address_line2" value="<?= e($pp['address_line2'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block">
            <span class="text-gray-600">지역 (province)</span>
            <input name="province" value="<?= e($pp['province'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block">
            <span class="text-gray-600">우편번호 (postal_code)</span>
            <input name="postal_code" value="<?= e($pp['postal_code'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block">
            <span class="text-gray-600">위도 (lat)</span>
            <input name="lat" value="<?= e(f_val('lat',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block">
            <span class="text-gray-600">경도 (lng)</span>
            <input name="lng" value="<?= e(f_val('lng',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block md:col-span-2">
            <span class="text-gray-600">Google Place ID (place_id)</span>
            <input name="place_id" value="<?= e(f_val('place_id',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block md:col-span-2">
            <span class="text-gray-600">Hero 이미지 URL (hero_image_url)</span>
            <input name="hero_image_url" value="<?= e(f_val('hero_image_url',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block md:col-span-2">
            <span class="text-gray-600">로고 이미지 URL (logo_image_url)</span>
            <input name="logo_image_url" value="<?= e(f_val('logo_image_url',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
          </label>

          <label class="block md:col-span-2">
            <span class="text-gray-600">소개 (intro)</span>
            <textarea name="intro" rows="4" class="mt-1 w-full border rounded px-3 py-2"><?= e(f_val('intro',$pp,$pa)) ?></textarea>
          </label>

          <label class="inline-flex items-center gap-2 md:col-span-2">
            <input type="checkbox" name="is_published" value="1" <?= !empty($pp['is_published']) ? 'checked' : '' ?>>
            <span>공개 (is_published)</span>
          </label>
        </div>
        <?php
      }
    ?>

    <?php
      // Prepare current image previews (prefer profile, fallback to application if present)
      $hero_preview = '';
      $logo_preview = '';
      if (is_array($pp)) {
        $hero_preview = $pp['hero_image_url'] ?? '';
        $logo_preview = $pp['logo_image_url'] ?? '';
      }
      if (!$hero_preview && is_array($pa)) { $hero_preview = $pa['hero_image_url'] ?? ''; }
      if (!$logo_preview && is_array($pa)) { $logo_preview = $pa['logo_image_url'] ?? ''; }
    ?>

    <section class="mt-8">
      <h2 class="text-lg font-semibold mb-3">이미지</h2>
      <input type="hidden" name="MAX_FILE_SIZE" value="5242880"><!-- 5MB -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        <div>
          <div class="text-gray-600">현재 Hero 이미지</div>
          <?php if (!empty($hero_preview)): ?>
            <img src="<?= e($hero_preview) ?>" alt="Hero" class="mt-2 max-h-32 rounded border">
          <?php else: ?>
            <div class="mt-2 text-gray-400">이미지 없음</div>
          <?php endif; ?>
          <input type="hidden" name="hero_image_url_existing" value="<?= e($hero_preview) ?>">
          <label class="block mt-3">
            <span class="text-gray-600">Hero 이미지 업로드</span>
            <input type="file" name="hero_image_file" accept="image/*" class="mt-1">
          </label>
        </div>
        <div>
          <div class="text-gray-600">현재 Logo 이미지</div>
          <?php if (!empty($logo_preview)): ?>
            <img src="<?= e($logo_preview) ?>" alt="Logo" class="mt-2 max-h-32 rounded border">
          <?php else: ?>
            <div class="mt-2 text-gray-400">이미지 없음</div>
          <?php endif; ?>
          <input type="hidden" name="logo_image_url_existing" value="<?= e($logo_preview) ?>">
          <label class="block mt-3">
            <span class="text-gray-600">Logo 이미지 업로드</span>
            <input type="file" name="logo_image_file" accept="image/*" class="mt-1">
          </label>
        </div>
      </div>
      <p class="mt-2 text-xs text-gray-500">* 새 파일을 선택하면 업로드된 파일로 교체되며, 선택하지 않으면 기존 URL이 유지됩니다. (최대 5MB)</p>
    </section>

    <div class="mt-4 flex items-center gap-3">
      <button type="submit" class="bg-black text-white px-4 py-2 rounded">수정 저장</button>
      <a href="<?= e($back) ?>" class="px-4 py-2 rounded border">취소</a>
    </div>
  </form>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>