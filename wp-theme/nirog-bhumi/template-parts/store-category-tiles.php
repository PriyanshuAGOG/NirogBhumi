<?php
/**
 * Small "shop by category" icon tiles for the store landing page. Each tile
 * uses the category's own WooCommerce thumbnail (Products > Categories >
 * edit > Thumbnail) when set, then a representative product's image, then a
 * piece of existing theme photography (nirog_bhumi_store_category_fallback_image)
 * so a tile is never left blank while real category photography is added.
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
  <?php foreach ($nb_shelf_terms as $nb_term) :
    $nb_thumb_id = get_term_meta($nb_term->term_id, 'thumbnail_id', true);
    $nb_image_html = $nb_thumb_id ? wp_get_attachment_image($nb_thumb_id, 'thumbnail') : '';
    if (!$nb_image_html) {
      $nb_fallback = wc_get_products([
        'status' => 'publish',
        'limit' => 1,
        'category' => [$nb_term->slug],
        'orderby' => 'menu_order',
        'order' => 'ASC',
      ]);
      if ($nb_fallback) {
        $nb_image_html = $nb_fallback[0]->get_image('thumbnail');
      }
    }
    if (!$nb_image_html && function_exists('nirog_bhumi_store_category_fallback_image')) {
      $nb_fallback_file = nirog_bhumi_store_category_fallback_image($nb_term->slug);
      if ($nb_fallback_file) {
        $nb_image_html = '<img src="' . esc_url(get_template_directory_uri() . '/assets/img/' . $nb_fallback_file) . '" alt="" loading="lazy">';
      }
    }
    ?>
    <a class="store-category-tile" href="<?php echo esc_url(get_term_link($nb_term)); ?>">
      <span class="store-category-tile-media"><?php echo $nb_image_html ?: ''; ?></span>
      <span class="store-category-tile-name"><?php echo esc_html($nb_term->name); ?></span>
    </a>
  <?php endforeach; ?>
</nav>
