<?php
// Define WordPress constants and stub functions for tests
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Stub WordPress hook functions
if (!function_exists('add_filter')) {
    function add_filter(...$args) {}
}
if (!function_exists('add_action')) {
    function add_action(...$args) {}
}

// Stub sanitization
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string {
        return $value;
    }
}

// Storage for stubbed meta and products
$GLOBALS['test_post_meta'] = [];
$GLOBALS['test_products'] = [];

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key, $single = false) {
        return $GLOBALS['test_post_meta'][$post_id][$key] ?? null;
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product(int $product_id) {
        return $GLOBALS['test_products'][$product_id] ?? null;
    }
}
