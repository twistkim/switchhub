<?php
// 관리자용 “신청 폼과 유사한” 입력 폼 파셜
// 입력 name들은 partner_profiles 업데이트 기준으로 설정 (필요 시 변경)
// 값 바인딩: $pp(현재값) > $pa(신청서) > '' 순
$pp = $ctx['pp'] ?? null;
$pa = $ctx['pa'] ?? null;

function val($key, $pp, $pa, $fallback=''){
  if (is_array($pp) && array_key_exists($key,$pp) && $pp[$key] !== null && $pp[$key] !== '') return $pp[$key];
  // 신청서 → 프로필 매핑 예시
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
    <input name="store_name" value="<?= e(val('store_name',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <label class="block">
    <span class="text-gray-600">대표전화 (phone)</span>
    <input name="phone" value="<?= e(val('phone',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <label class="block md:col-span-2">
    <span class="text-gray-600">매장주소 (address_line1)</span>
    <input name="address_line1" value="<?= e(val('address_line1',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
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
    <input name="lat" value="<?= e(val('lat',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <label class="block">
    <span class="text-gray-600">경도 (lng)</span>
    <input name="lng" value="<?= e(val('lng',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <label class="block md:col-span-2">
    <span class="text-gray-600">Google Place ID (place_id)</span>
    <input name="place_id" value="<?= e(val('place_id',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <label class="block md:col-span-2">
    <span class="text-gray-600">Hero 이미지 URL (hero_image_url)</span>
    <input name="hero_image_url" value="<?= e(val('hero_image_url',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <label class="block md:col-span-2">
    <span class="text-gray-600">로고 이미지 URL (logo_image_url)</span>
    <input name="logo_image_url" value="<?= e(val('logo_image_url',$pp,$pa)) ?>" class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <label class="block md:col-span-2">
    <span class="text-gray-600">소개 (intro)</span>
    <textarea name="intro" rows="4" class="mt-1 w-full border rounded px-3 py-2"><?= e(val('intro',$pp,$pa)) ?></textarea>
  </label>

  <label class="inline-flex items-center gap-2 md:col-span-2">
    <input type="checkbox" name="is_published" value="1" <?= !empty($pp['is_published']) ? 'checked' : '' ?>>
    <span>공개 (is_published)</span>
  </label>
</div>