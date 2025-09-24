<?php
// lib/cart.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function cart_get(): array {
  return $_SESSION['cart'] ?? [];
}

function cart_save(array $cart): void {
  $_SESSION['cart'] = $cart;
}

function cart_add(int $productId): void {
  $cart = cart_get();
  // 휴대폰은 일반적으로 1개 단위라 수량 개념 없이 unique 보관
  $cart[$productId] = 1;
  cart_save($cart);
}

function cart_remove(int $productId): void {
  $cart = cart_get();
  unset($cart[$productId]);
  cart_save($cart);
}

function cart_clear(): void {
  unset($_SESSION['cart']);
}