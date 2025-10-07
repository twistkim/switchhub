<?php
// /partials/splash_logo.php
// 로고 경로가 다르면 아래 src 수정
?>
<style>
  /* 전체 화면 흰 배경 오버레이 */
  .splash-overlay {
    position: fixed; inset: 0; z-index: 9999;
    display: grid; place-items: center;
    background: #fff;
    /* 애니메이션 총 길이: 나타남 0.6s + 정지 0.6s + 사라짐 0.6s = 1.8s */
    animation: splash-seq 1.8s ease forwards;
  }
  /* 로고 등장/퇴장 */
  .splash-logo {
    width: clamp(96px, 18vw, 220px);
    opacity: 0;
    transform: scale(.92);
    animation: logo-pop 1.8s ease forwards;
  }

  /* ✅ 모바일(최대 768px)에서는 2배로 키우기 */
@media (max-width: 768px) {
  .splash-logo {
    width: clamp(160px, 36vw, 400px);
  }
}

  @keyframes splash-seq {
    0%   { opacity: 1; }
    85%  { opacity: 1; }
    100% { opacity: 0; visibility: hidden; }
  }
  @keyframes logo-pop {
    /* 등장 */
    0%   { opacity: 0; transform: scale(.92); filter: blur(2px); }
    20%  { opacity: 1; transform: scale(1.0); filter: blur(0); }
    /* 잠깐 유지 */
    60%  { opacity: 1; transform: scale(1.0); }
    /* 퇴장 */
    100% { opacity: 0; transform: scale(1.04); }
  }

  /* 모션 줄이기 설정 존중 */
  @media (prefers-reduced-motion: reduce) {
    .splash-overlay, .splash-logo { animation: none !important; }
  }
</style>

<div class="splash-overlay" role="presentation" aria-hidden="true">
  <img class="splash-logo" src="/img/logo.svg" alt="Logo">
</div>

<script>
  (function () {
    const KEY = 'splashSeen_v1';
    if (localStorage.getItem(KEY)) return; // 이미 봤으면 즉시 종료

    const splash = document.createElement('div');
    splash.className = 'splash-overlay';
    splash.innerHTML = '<img class="splash-logo" src="/img/logo.svg" alt="Logo">';
    document.body.appendChild(splash);

    // 애니메이션이 끝나면 제거 + 기록
    const remove = () => {
      splash.remove();
      localStorage.setItem(KEY, '1'); // 기록 저장 (다음엔 안보이게)
    };
    splash.addEventListener('animationend', remove, { once: true });
    setTimeout(remove, 2500);
  })();
</script>