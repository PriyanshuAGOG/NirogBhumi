<?php
/**
 * Product card.
 *
 * Rendered as the theme's own .store-tile rather than WooCommerce's default
 * markup, so cards match the shelves the store design was built around. The
 * standard loop-item hooks are deliberately not fired here: their default
 * callbacks output a second title, price and button, which would duplicate
 * everything below. See inc/store.php for where those callbacks are removed.
 */

defined('ABSPATH') || exit;

global $product;

if (empty($product) || !$product->is_visible()) {
  return;
}

$status = nirog_bhumi_product_launch_status($product);
$buyable = nirog_bhumi_product_is_buyable($product);
$eyebrow = get_post_meta($product->get_id(), '_nb_eyebrow', true);
$classes = ['store-tile'];
if ($product->is_featured()) {
  $classes[] = 'featured';
}
?>
<article <?php wc_product_class(implode(' ', $classes), $product); ?>>
  <figure>
    <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>" aria-label="<?php echo esc_attr($product->get_name()); ?>">
      <?php echo $product->get_image('woocommerce_thumbnail'); ?>
    </a>
  </figure>
  <div>
    <span><?php echo esc_html($eyebrow ?: wp_strip_all_tags(wc_get_product_category_list($product->get_id(), ', ', '', ''))); ?></span>
    <h3><a href="<?php echo esc_url(get_permalink($product->get_id())); ?>"><?php echo esc_html($product->get_name()); ?></a></h3>
    <p><?php echo esc_html(wp_strip_all_tags($product->get_short_description())); ?></p>
    <div class="price-row">
      <strong><?php echo wp_kses_post($product->get_price_html()); ?></strong>
      <?php if ($buyable) : ?>
        <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>"><?php esc_html_e('View product', 'nirog-bhumi'); ?></a>
      <?php elseif ('sale' === $status) : ?>
        <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>"><?php esc_html_e('View product', 'nirog-bhumi'); ?></a>
      <?php else : ?>
        <?php nirog_bhumi_render_launch_action($product, 'loop'); ?>
      <?php endif; ?>
    </div>
  </div>
</article>
