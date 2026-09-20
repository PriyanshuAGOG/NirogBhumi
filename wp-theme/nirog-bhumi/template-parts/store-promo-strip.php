<?php
/**
 * A short "shop the store" shelf for non-store pages (home, about, approach,
 * education) - reuses the same product card and shelf styling as the store
 * page itself. Renders nothing while the store is in Coming soon for
 * visitors, so it never points a visitor at a page they cannot actually
 * browse.
 */

defined('ABSPATH') || exit;

if (!function_exists('nirog_bhumi_woocommerce_active') || !nirog_bhumi_woocommerce_active()) {
  return;
}
if (!nirog_bhumi_store_catalogue_is_visible()) {
  return;
}

$nb_strip_products = wc_get_products([
  'status' => 'publish',
  'limit' => 4,
  'orderby' => 'menu_order',
  'order' => 'ASC',
  'visibility' => 'visible',
]);

if (!$nb_strip_products) {
  return;
}
?>
<section class="home-store-strip">
  <div class="store-shelf-title">
    <div>
      <p class="eyebrow"><?php esc_html_e('Nirog Bhumi Store', 'nirog-bhumi'); ?></p>
      <h2><?php esc_html_e('Bring the practice home.', 'nirog-bhumi'); ?></h2>
    </div>
    <a href="<?php echo esc_url(home_url('/store/')); ?>"><?php esc_html_e('Shop the Store', 'nirog-bhumi'); ?></a>
  </div>
  <div class="store-shelf-grid products">
    <?php
    foreach ($nb_strip_products as $nb_strip_product) {
      $post_object = get_post($nb_strip_product->get_id());
      if (!$post_object) {
        continue;
      }
      setup_postdata($GLOBALS['post'] = $post_object); // phpcs:ignore
      // setup_postdata() alone does not fire the `the_post` action, so
      // WooCommerce's wc_setup_product_data() has to be called explicitly -
      // see the matching note in page-store.php.
      wc_setup_product_data($post_object);
      wc_get_template_part('content', 'product');
    }
    wp_reset_postdata();
    ?>
  </div>
</section>
