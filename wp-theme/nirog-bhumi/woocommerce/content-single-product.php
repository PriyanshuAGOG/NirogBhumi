<?php
/**
 * Single product page.
 *
 * Rebuilt as the theme's .product-page layout, matching the static product
 * pages under /store/products/. The add-to-cart form still goes through
 * woocommerce_template_single_add_to_cart() so gateways, variations and
 * third-party cart plugins keep working.
 */

defined('ABSPATH') || exit;

global $product;

$product_id = $product->get_id();
$status = nirog_bhumi_product_launch_status($product);
$buyable = nirog_bhumi_product_is_buyable($product);
$eyebrow = get_post_meta($product_id, '_nb_eyebrow', true);
$ritual = get_post_meta($product_id, '_nb_ritual', true);
$caution = get_post_meta($product_id, '_nb_caution', true);
$dispatch = nirog_bhumi_store_dispatch_note();
?>
<?php do_action('woocommerce_before_single_product'); ?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class('', $product); ?>>
  <section class="product-page">
    <div class="product-photo large">
      <?php echo $product->get_image('woocommerce_single'); ?>
    </div>
    <div class="product-detail">
      <?php if ($eyebrow) : ?><p class="eyebrow"><?php echo esc_html($eyebrow); ?></p><?php endif; ?>
      <h1><?php the_title(); ?></h1>
      <strong class="price"><?php echo wp_kses_post($product->get_price_html()); ?></strong>

      <?php if ($product->get_short_description()) : ?>
        <div class="product-summary"><?php echo wp_kses_post(wpautop($product->get_short_description())); ?></div>
      <?php endif; ?>

      <?php if ($buyable) : ?>
        <div class="product-buy">
          <?php woocommerce_template_single_add_to_cart(); ?>
          <a class="pill ghost" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php esc_html_e('View Cart', 'nirog-bhumi'); ?></a>
        </div>
        <?php if ($dispatch) : ?><p class="store-dispatch-note"><?php echo esc_html($dispatch); ?></p><?php endif; ?>
      <?php elseif ('sale' === $status) : ?>
        <p class="store-status-banner inline">
          <?php esc_html_e('Ordering is not open yet. This page is a preview of what will be on sale.', 'nirog-bhumi'); ?>
        </p>
      <?php endif; ?>

      <?php if ($product->get_description()) : ?>
        <div class="product-longform"><?php echo wp_kses_post(wpautop($product->get_description())); ?></div>
      <?php endif; ?>

      <?php if ($ritual) : ?>
        <div class="ritual">
          <strong><?php esc_html_e('Suggested ritual', 'nirog-bhumi'); ?></strong>
          <p><?php echo esc_html($ritual); ?></p>
        </div>
      <?php endif; ?>

      <?php if ($caution) : ?>
        <div class="ritual caution">
          <strong><?php esc_html_e('Important note', 'nirog-bhumi'); ?></strong>
          <p><?php echo esc_html($caution); ?></p>
        </div>
      <?php endif; ?>

      <div class="ritual disclaimer">
        <strong><?php esc_html_e('Wellness support, not medical treatment', 'nirog-bhumi'); ?></strong>
        <p><?php esc_html_e('Nirog Bhumi products support daily routines. They do not diagnose, treat or cure any condition, and they do not replace medical advice or prescribed medication. Speak with your doctor before changing medication, diet or activity.', 'nirog-bhumi'); ?></p>
      </div>

      <?php if (!$buyable && 'sale' !== $status) : ?>
        <div class="product-launch-action">
          <?php nirog_bhumi_render_launch_action($product, 'single'); ?>
        </div>
      <?php endif; ?>

      <?php if ($product->get_sku()) : ?>
        <p class="product-meta-line"><span><?php esc_html_e('SKU', 'nirog-bhumi'); ?></span> <?php echo esc_html($product->get_sku()); ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php
  $related_ids = wc_get_related_products($product_id, 4);
  if ($related_ids) :
    ?>
    <section class="store-shelf related-shelf">
      <div class="store-shelf-title">
        <p class="eyebrow"><?php esc_html_e('Also on the shelf', 'nirog-bhumi'); ?></p>
        <h2><?php esc_html_e('Often kept together.', 'nirog-bhumi'); ?></h2>
      </div>
      <div class="store-shelf-grid products">
        <?php
        foreach ($related_ids as $related_id) {
          $post_object = get_post($related_id);
          if (!$post_object) {
            continue;
          }
          setup_postdata($GLOBALS['post'] = $post_object); // phpcs:ignore
          wc_get_template_part('content', 'product');
        }
        wp_reset_postdata();
        ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php do_action('woocommerce_after_single_product'); ?>
