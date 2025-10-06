<?php
// /lib/categories.php
function categories_fetch_all(PDO $pdo): array {
  $sql = "SELECT id, name, slug, parent_id, sort_order, is_active
          FROM categories ORDER BY parent_id IS NOT NULL, parent_id, sort_order, name";
  return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function categories_build_tree(array $rows): array {
  $byId = []; $tree = [];
  foreach ($rows as $r) { $r['children'] = []; $byId[$r['id']] = $r; }
  foreach ($byId as $id => &$n) {
    $pid = $n['parent_id'];
    if ($pid && isset($byId[$pid])) $byId[$pid]['children'][] = &$n;
    else $tree[] = &$n;
  }
  return $tree;
}

function categories_flat_options(array $rows, ?int $selected = null, ?int $excludeId = null): string {
  // 들여쓰기 표시용
  $byId = []; foreach ($rows as $r) { $byId[$r['id']] = $r; $byId[$r['id']]['children'] = []; }
  foreach ($rows as $r) {
    if (!empty($r['parent_id']) && isset($byId[$r['parent_id']])) {
      $byId[$r['parent_id']]['children'][] = $r['id'];
    }
  }
  $roots = array_filter($rows, fn($r)=>empty($r['parent_id']));

  $out = '<option value="">(상위 없음)</option>';
  $walk = function($id, $depth) use (&$walk, &$byId, &$out, $selected, $excludeId) {
    $n = $byId[$id];
    if ($excludeId && $id == $excludeId) return;
    $indent = str_repeat('— ', $depth);
    $sel = ($selected !== null && $selected == $id) ? ' selected' : '';
    $label = htmlspecialchars($indent.$n['name'], ENT_QUOTES, 'UTF-8');
    $out .= "<option value=\"{$id}\"{$sel}>{$label}</option>";
    foreach ($n['children'] as $cid) $walk($cid, $depth+1);
  };
  foreach ($roots as $r) $walk($r['id'], 0);
  return $out;
}