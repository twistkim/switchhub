<?php
// /admin/categories.php
$pageTitle = '카테고리 관리';
$activeMenu = 'categories';

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/categories.php';

require_role('admin');

$pdo = db();
$cats = categories_fetch_all($pdo);
$tree = categories_build_tree($cats);
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit = null;
if ($editId) {
  $st = $pdo->prepare("SELECT * FROM categories WHERE id=:id");
  $st->execute([':id'=>$editId]); $edit = $st->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../partials/header_admin.php';
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <h1 class="text-2xl font-bold mb-6">카테고리 관리</h1>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- 목록 -->
    <section class="lg:col-span-2">
      <div class="bg-white border rounded-xl p-5">
        <h2 class="font-semibold mb-3">카테고리 트리</h2>
        <?php
        $render = function($nodes, $depth=0) use (&$render) {
          if (!$nodes) return;
          echo '<ul class="space-y-1 '.($depth?'pl-4 border-l':'').'">';
          foreach ($nodes as $n) {
            $badge = $n['is_active'] ? '' : '<span class="ml-2 text-xs text-rose-600">(숨김)</span>';
            echo '<li class="flex items-center justify-between">';
            echo '<div><span class="text-sm">'.htmlspecialchars($n['name'],ENT_QUOTES,'UTF-8').'</span>'.$badge.'</div>';
            echo '<div class="flex gap-2">';
            echo '<a class="px-2 py-1 text-xs border rounded" href="/admin/categories.php?id='.(int)$n['id'].'">수정</a>';
            echo '<form method="post" action="/admin/category_delete.php" onsubmit="return confirm(\'숨김 처리하시겠습니까?\');">';
            echo '<input type="hidden" name="csrf" value="'.csrf_token().'">';
            echo '<input type="hidden" name="id" value="'.(int)$n['id'].'">';
            echo '<button class="px-2 py-1 text-xs rounded bg-rose-600 text-white">숨김</button>';
            echo '</form>';
            echo '</div>';
            echo '</li>';
            if (!empty($n['children'])) $render($n['children'], $depth+1);
          }
          echo '</ul>';
        };
        $render($tree);
        ?>
      </div>
    </section>

    <!-- 등록/수정 폼 -->
    <section>
      <div class="bg-white border rounded-xl p-5">
        <h2 class="font-semibold mb-3"><?= $edit ? '카테고리 수정' : '새 카테고리' ?></h2>
        <form method="post" action="/admin/category_save.php" class="space-y-4">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
          <?php endif; ?>

          <div>
            <label class="block text-sm font-medium">이름</label>
            <input name="name" class="mt-1 w-full border rounded px-3 py-2" required
                   value="<?= htmlspecialchars($edit['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div>
            <label class="block text-sm font-medium">슬러그 (선택)</label>
            <input name="slug" class="mt-1 w-full border rounded px-3 py-2"
                   value="<?= htmlspecialchars($edit['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div>
            <label class="block text-sm font-medium">상위 카테고리</label>
            <select name="parent_id" class="mt-1 w-full border rounded px-3 py-2">
              <?php
                echo categories_flat_options($cats, $edit['parent_id'] ?? null, $edit['id'] ?? null);
              ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">자기 자신을 상위로 지정할 수 없습니다.</p>
          </div>

          <div>
            <label class="block text-sm font-medium">정렬 순서</label>
            <input name="sort_order" type="number" class="mt-1 w-full border rounded px-3 py-2"
                   value="<?= htmlspecialchars((string)($edit['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= (!isset($edit) || (int)($edit['is_active'] ?? 1)===1)?'checked':''; ?>>
            <label for="is_active" class="text-sm">활성화</label>
          </div>

          <div class="pt-2 flex gap-2">
            <button class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700"><?= $edit ? '수정 저장' : '등록' ?></button>
            <a class="px-4 py-2 rounded border" href="/admin/categories.php">새로 만들기</a>
          </div>
        </form>
      </div>
    </section>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>