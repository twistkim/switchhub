<?php
$pageTitle = '파트너 신청';
$activeMenu = 'partner';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/i18n/bootstrap.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/config/maps.php';
if (!defined('GOOGLE_MAPS_API_KEY')) { define('GOOGLE_MAPS_API_KEY', ''); }
include __DIR__ . '/partials/header.php';

$err = $_GET['err'] ?? '';
$msg = $_GET['msg'] ?? '';
?>
<div class="max-w-2xl mx-auto bg-white border rounded-xl shadow-sm p-6">
  <h1 class="text-2xl font-bold"><?= __('partner_apply.1') ?: '파트너 신청' ?></h1>
  <p class="text-gray-600 mt-1">
    <?= __('partner_apply.2') ?: '사업자 정보를 입력해 주세요. 관리자가 검토 후 승인합니다.' ?>
  </p>

  <?php if ($err): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($msg): ?>
    <div class="mt-4 p-3 rounded bg-green-50 text-green-700 text-sm"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form class="mt-6 space-y-5" method="post" action="/partner_apply_process.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <!-- 1) 사업자등록증 업로드 -->
    <div>
      <label class="block text-sm font-medium">
        <?= __('partner_apply.3') ?: '사업자등록증 (이미지 또는 PDF, 최대 5MB)' ?>
      </label>
      <input type="file" name="business_cert" accept=".pdf,image/*" required
             class="mt-1 w-full border rounded-lg px-3 py-2">
      <p class="text-xs text-gray-500 mt-1">
        <?= __('partner_apply.4') ?: '허용: JPG/PNG/PDF' ?>
      </p>
    </div>

    <!-- 2) 상호 / 사업자등록번호 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">
          <?= __('partner_apply.5') ?: '상호' ?>
        </label>
        <input type="text" name="business_name" required class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium">
          <?= __('partner_apply.6') ?: '사업자 등록번호' ?>
        </label>
        <input type="text" name="business_reg_number" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="예: TH-0123456789">
      </div>
    </div>

    <!-- 3) 담당자 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">
          <?= __('partner_apply.7') ?: '담당자 이름' ?>
        </label>
        <input type="text" name="contact_name" required class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium">
          <?= __('partner_apply.8') ?: '담당자 연락처' ?>
        </label>
        <input type="text" name="contact_phone" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="+66-xxx-xxxx">
      </div>
    </div>

    <!-- 4) 정산 계좌 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">
          <?= __('partner_apply.9') ?: '은행명' ?>
        </label>
        <input type="text" name="bank_name" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="예: Kasikornbank">
      </div>
      <div>
        <label class="block text-sm font-medium">
          <?= __('partner_apply.10') ?: '계좌번호' ?>
        </label>
        <input type="text" name="bank_account" required class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium">
        <?= __('partner_apply.11') ?: '매장 소개' ?>
      </label>
      <textarea name="store_intro" rows="4" class="mt-1 w-full border rounded-lg px-3 py-2"
                placeholder="매장 특징, 취급 기종 등"></textarea>
    </div>

    <!-- (선택) 매장 대표 이미지 -->
    <div>
      <label class="block text-sm font-medium">
        <?= __('partner_apply.12') ?: '매장 대표 이미지 (JPG/PNG, 최대 5MB)' ?>
      </label>
      <input type="file" name="store_hero" accept="image/*"
            class="mt-1 w-full border rounded-lg px-3 py-2">
    </div>

    <!-- 5) 이메일 -->
    <div>
      <label class="block text-sm font-medium">
        <?= __('partner_apply.13') ?: '이메일' ?>
      </label>
      <input type="email" name="email" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="partner@example.co.th">
    </div>

    <!-- 6) 사업장 주소 + 지도 + 좌표(자동완성) -->
    <div>
      <label class="block text-sm font-medium">
        <?= __('partner_apply.14') ?: '사업장 주소' ?>
      </label>
      <input type="text" id="store_address" name="store_address" required
             class="mt-1 w-full border rounded-lg px-3 py-2"
             placeholder="도로명/지번, 구/군/도, 우편번호">
      <input type="hidden" id="store_lat" name="store_lat" value="">
      <input type="hidden" id="store_lng" name="store_lng" value="">
      <input type="hidden" id="store_place_id" name="store_place_id" value="">
      <p class="text-xs text-gray-500 mt-1">
        <?= __('partner_apply.15') ?: '주소 자동완성 후 지도에서 위치를 확인하세요.' ?>
      </p>
      <div id="store_map" class="mt-3 w-full h-64 rounded-lg border"></div>
    </div>

    <div class="pt-4 flex gap-3">
      <button class="px-5 py-2.5 bg-primary text-white rounded-lg font-semibold">
        <?= __('partner_apply.16') ?: '신청 제출' ?>
      </button>
      <a href="/" class="px-5 py-2.5 border rounded-lg">
        <?= __('partner_apply.17') ?: '취소' ?>
      </a>
    </div>
  </form>
</div>

<!-- (옵션) 태국 주소 자동완성 훅: 여기서 JS로 postcodes/province 데이터셋 연결 가능 -->
<script>
  // 구글 지도/자동완성 초기화
  function initMapApply() {
    const input  = document.getElementById('store_address');
    const latEl  = document.getElementById('store_lat');
    const lngEl  = document.getElementById('store_lng');
    const pidEl  = document.getElementById('store_place_id');
    const mapEl  = document.getElementById('store_map');

    // 기본: 방콕 중심
    const center = { lat: 13.7563, lng: 100.5018 };
    const map = new google.maps.Map(mapEl, { center, zoom: 12 });
    const marker = new google.maps.Marker({ map, position: center });

    // Places 자동완성
    const ac = new google.maps.places.Autocomplete(input, {
      fields: ['formatted_address', 'geometry', 'place_id']
    });
    ac.addListener('place_changed', () => {
      const place = ac.getPlace();
      if (!place || !place.geometry) return;
      const loc = place.geometry.location;
      map.setCenter(loc); map.setZoom(15);
      marker.setPosition(loc);
      latEl.value = loc.lat();
      lngEl.value = loc.lng();
      pidEl.value = place.place_id || '';
      if (place.formatted_address) input.value = place.formatted_address;
    });

    // 사용자가 수동으로 값 입력 후 포커스아웃 시에도 지오코딩 시도 (선택적 고도화 가능)
    input.addEventListener('change', () => {
      // 필요시 서버측 지오코딩 API 준비 후 확장
    });
  }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=places&callback=initMapApply" async defer></script>

<?php include __DIR__ . '/partials/footer.php'; ?>