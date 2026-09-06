<?php
/**
 * Single product page.
 *
 * Rebuilt as the theme's .product-page layout. The add-to-cart form still
 * goes through woocommerce_template_single_add_to_cart() so gateways,
 * variations and third-party cart plugins keep working.
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
$categories = wc_get_product_category_list($product_id, ', ', '', '');
$primary_term = ($terms = get_the_terms($product_id, 'product_cat')) && !is_wp_error($terms) ? reset($terms) : null;
?>
<?php do_action('woocommerce_before_single_product'); ?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class('', $product); ?>>

  <nav class="store-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'nirog-bhumi'); ?>">
    <a href="<?php echo esc_url(home_url('/store/')); ?>"><?php esc_html_e('Store', 'nirog-bhumi'); ?></a>
    <?php if ($primary_term) : ?>
      <span>/</span>
      <a href="<?php echo esc_url(get_term_link($primary_term)); ?>"><?php echo esc_html($primary_term->name); ?></a>
    <?php endif; ?>
    <span>/</span>
    <span aria-current="page"><?php the_title(); ?></span>
  </nav>

  <section class="product-page">
    <div class="product-photo large">
      <?php echo $product->get_image('woocommerce_single'); ?>
    </div>
    <div class="product-detail">
      <?php if ($eyebrow || $categories) : ?><p class="eyebrow"><?php echo esc_html($eyebrow ?: wp_strip_all_tags($categories)); ?></p><?php endif; ?>
      <h1><?php the_title(); ?></h1>

      <?php if ($product->get_short_description()) : ?>
        <div class="product-summary"><?php echo wp_kses_post(wpautop($product->get_short_description())); ?></div>
      <?php endif; ?>

      <strong class="price"><?php echo wp_kses_post($product->get_price_html()); ?></strong>

      <?php if ($buyable) : ?>
        <div class="product-buy">
          <?php woocommerce_template_single_add_to_cart(); ?>
        </div>
        <a class="product-view-cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php esc_html_e('View Cart', 'nirog-bhumi'); ?></a>
        <?php if ($dispatch) : ?><p class="store-dispatch-note"><?php echo esc_html($dispatch); ?></p><?php endif; ?>
      <?php elseif ('sale' === $status) : ?>
        <p class="store-status-banner inline">
          <?php esc_html_e('Ordering is not open yet. This page is a preview of what will be on sale.', 'nirog-bhumi'); ?>
        </p>
      <?php elseif ('sale' !== $status) : ?>
        <div class="product-launch-action">
          <?php nirog_bhumi_render_launch_action($product, 'single'); ?>
        </div>
      <?php endif; ?>

      <div class="product-trust-row">
        <span><?php esc_html_e('Wellness support, not medical treatment', 'nirog-bhumi'); ?></span>
        <?php if ($product->get_sku()) : ?><span><?php esc_html_e('SKU', 'nirog-bhumi'); ?> <?php echo esc_html($product->get_sku()); ?></span><?php endif; ?>
      </div>

      <div class="product-accordion">
        <?php if ($product->get_description()) : ?>
          <details open>
            <summary><?php esc_html_e('Description', 'nirog-bhumi'); ?></summary>
            <div class="product-longform"><?php echo wp_kses_post(wpautop($product->get_description())); ?></div>
          </details>
        <?php endif; ?>

        <?php if ($ritual) : ?>
          <details>
            <summary><?php esc_html_e('Suggested ritual', 'nirog-bhumi'); ?></summary>
            <p><?php echo esc_html($ritual); ?></p>
          </details>
        <?php endif; ?>

        <?php if ($caution) : ?>
          <details class="is-caution">
            <summary><?php esc_html_e('Important note', 'nirog-bhumi'); ?></summary>
            <p><?php echo esc_html($caution); ?></p>
          </details>
        <?php endif; ?>

        <details>
          <summary><?php esc_html_e('Wellness disclaimer', 'nirog-bhumi'); ?></summary>
          <p><?php esc_html_e('Nirog Bhumi products support daily routines. They do not diagnose, treat or cure any condition, and they do not replace medical advice or prescribed medication. Speak with your doctor before changing medication, diet or activity.', 'nirog-bhumi'); ?></p>
        </details>
      </div>
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
          // See the matching note in page-store.php: setup_postdata() does not
          // set $GLOBALS['product'], which content-product.php relies on.
          wc_setup_product_data($post_object);
          wc_get_template_part('content', 'product');
        }
        wp_reset_postdata();
        ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php do_action('woocommerce_after_single_product'); ?>
