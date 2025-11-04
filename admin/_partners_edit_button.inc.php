<?php
// 관리자 목록에서 [수정] 버튼만 깔끔히 추가하는 스니펫
// 사용법(기존 partners.php의 각 행 렌더링 부근):
//   include_once __DIR__ . '/_partners_edit_button.inc.php';
//   echo render_partner_edit_button($row['id'] ?? null, $row['application_id'] ?? null);

if (!function_exists('render_partner_edit_button')) {
  function render_partner_edit_button($profileId = null, $applicationId = null): string {
    // 우선순위: partner_profiles.id > partner_applications.id
    if ($profileId) {
      $url = '/admin/partner_edit.php?id='.(int)$profileId;
    } elseif ($applicationId) {
      $url = '/admin/partner_edit.php?app_id='.(int)$applicationId;
    } else {
      return ''; // 둘 다 없으면 버튼 표시 안 함
    }
    return '<a href="'.$url.'" class="inline-block border rounded px-2 py-1 ml-2 hover:bg-gray-50">수정</a>';
  }
}