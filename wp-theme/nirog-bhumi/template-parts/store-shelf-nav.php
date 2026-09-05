<?php
/**
 * Shelf navigation across the store.
 */

defined('ABSPATH') || exit;

if (!function_exists('is_product_category') || !function_exists('wc_get_page_permalink')) {
  return;
}

$terms = get_terms([
  'taxonomy' => 'product_cat',
  'hide_empty' => true,
  'exclude' => [(int) get_option('default_product_cat')],
]);

if (is_wp_error($terms) || count($terms) < 2) {
  return;
}

$current = is_product_category() ? get_queried_object_id() : 0;
?>
<nav class="store-shelf-nav" aria-label="<?php esc_attr_e('Store shelves', 'nirog-bhumi'); ?>">
  <a class="<?php echo $current ? '' : 'is-current'; ?>" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Everything', 'nirog-bhumi'); ?></a>
  <?php foreach ($terms as $term) : ?>
    <a class="<?php echo (int) $term->term_id === $current ? 'is-current' : ''; ?>" href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a>
  <?php endforeach; ?>
</nav>
