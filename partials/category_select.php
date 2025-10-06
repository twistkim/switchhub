<?php
// /partials/category_select.php
// 사용법: require 이 파일 전 $categories = categories_fetch_all($pdo);
// echo category_select($categories, $selectedId);
if (!function_exists('category_select')) {
  function category_select(array $categories, ?int $selectedId = null, string $name = 'category_id'): string {
    require_once __DIR__ . '/../lib/categories.php';
    $opts = categories_flat_options($categories, $selectedId, null);
    return '<select name="'.htmlspecialchars($name,ENT_QUOTES,'UTF-8').'" class="mt-1 w-full border rounded px-3 py-2" required>'.$opts.'</select>';
  }
}