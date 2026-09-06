<?php
/**
 * "Shop by category" icon tiles for the store landing page: bordered
 * squares with a plain line icon and a label, not photography - matching
 * the clean "shop by concern" pattern used across Ayurvedic D2C stores.
 */

defined('ABSPATH') || exit;

$nb_shelf_terms = get_terms([
  'taxonomy' => 'product_cat',
  'hide_empty' => true,
  'exclude' => [(int) get_option('default_product_cat')],
  'orderby' => 'menu_order',
  'order' => 'ASC',
]);

if (is_wp_error($nb_shelf_terms) || !$nb_shelf_terms) {
  return;
}
?>
<nav class="store-category-tiles" aria-label="<?php esc_attr_e('Shop by category', 'nirog-bhumi'); ?>">
  <?php foreach ($nb_shelf_terms as $nb_term) : ?>
    <a class="store-category-tile" href="<?php echo esc_url(get_term_link($nb_term)); ?>">
      <span class="store-category-tile-icon"><?php echo nirog_bhumi_store_category_icon($nb_term->slug); ?></span>
      <span class="store-category-tile-name"><?php echo esc_html($nb_term->name); ?></span>
    </a>
  <?php endforeach; ?>
</nav>
