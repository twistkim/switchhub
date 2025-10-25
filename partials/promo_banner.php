<?php
// /partials/promo_banner.php
// Tailwind 기반 안내 배너 (검색 박스 위에 include 해서 사용)
?>
<div class="mb-6">
  <div class="relative p-5 md:p-6">
    <div class="flex items-center gap-4">
      <!-- 문구 -->
      <div class="min-w-0">
        <p class="text-[18px] leading-6 text-gray-700">
          <span class="font-semibold text-gray-900 block md:inline"><?= __('promo_banner.1') ?: '1' ?></span>
          <span class="font-semibold text-gray-900 block md:inline"><?= __('promo_banner.2') ?: '2' ?></span>
          <span class="block md:inline text-gray-700"><?= __('promo_banner.3') ?: '3' ?></span>
          <span class="block md:inline text-gray-700"><?= __('promo_banner.4') ?: '4' ?></span>
        </p>
      </div>

      <!-- 오른쪽 아이콘 -->
      <div class="ml-auto shrink-0">
        <div class="w-20 h-20 md:w-36 md:h-36 grid place-items-center">
          <!-- phone icon -->
          <img src="/img/banner_icon.svg" alt="banner icon" class="animate-float-y select-none" draggable="false">
        </div>
      </div>
    </div>
  <style>
  @keyframes float-y {
    0%   { transform: translateY(0); }
    50%  { transform: translateY(-6px); }
    100% { transform: translateY(0); }
  }
  .animate-float-y {
    animation: float-y 3.6s ease-in-out infinite;
    will-change: transform;
  }
  </style>
  </div>
</div>