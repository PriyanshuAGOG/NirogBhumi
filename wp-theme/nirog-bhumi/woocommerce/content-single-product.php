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

// Main image plus any gallery images, so a shopper can browse the product
// the way they would on any modern store instead of seeing one photo only.
$gallery_ids = array_values(array_unique(array_filter(array_merge(
  [$product->get_image_id()],
  $product->get_gallery_image_ids()
))));

// The catalogue description wraps an "includes" list in <ul><li> for the
// product carousel to read (see page-store.php); split it out here into its
// own accordion section instead of leaving it buried inside "Description".
$description_html = $product->get_description();
$includes = [];
if (preg_match_all('/<li>(.*?)<\/li>/s', $description_html, $description_matches)) {
  $includes = array_map('wp_strip_all_tags', $description_matches[1]);
}
$description_text = trim(preg_replace('/<ul>.*?<\/ul>/s', '', $description_html));

// "Buy it with": WooCommerce's own Cross-sells field (Product data > Linked
// Products), set by hand in wp-admin or pre-wired for launch SKUs in
// inc/store-catalogue.php / nirog_bhumi_seed_cross_sells(). Never invented
// here - if nothing is set, the section simply does not render.
$cross_sell_products = array_filter(
  array_map('wc_get_product', $product->get_cross_sell_ids()),
  function ($cross_sell_product) {
    return $cross_sell_product && $cross_sell_product->is_visible();
  }
);
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
    <div class="product-gallery"<?php echo count($gallery_ids) > 1 ? ' data-nb-gallery' : ''; ?>>
      <div class="product-photo large" data-nb-gallery-main>
        <?php if ($gallery_ids) : ?>
          <?php echo wp_get_attachment_image($gallery_ids[0], 'woocommerce_single'); ?>
        <?php else : ?>
          <?php echo $product->get_image('woocommerce_single'); ?>
        <?php endif; ?>
        <button type="button" class="product-share-btn" data-nb-share data-url="<?php echo esc_url(get_permalink($product_id)); ?>" data-title="<?php echo esc_attr($product->get_name()); ?>" aria-label="<?php esc_attr_e('Share this product', 'nirog-bhumi'); ?>">
          <?php echo nirog_bhumi_share_icon(); ?>
        </button>
        <span class="product-share-status" data-nb-share-status hidden><?php esc_html_e('Link copied', 'nirog-bhumi'); ?></span>
      </div>
      <?php if (count($gallery_ids) > 1) : ?>
        <div class="product-gallery-thumbs">
          <?php foreach ($gallery_ids as $gallery_index => $gallery_id) :
            $full_url = wp_get_attachment_image_url($gallery_id, 'woocommerce_single');
            $alt_text = get_post_meta($gallery_id, '_wp_attachment_image_alt', true);
            ?>
            <button type="button" class="product-gallery-thumb<?php echo 0 === $gallery_index ? ' is-active' : ''; ?>" data-nb-gallery-thumb data-full="<?php echo esc_url($full_url); ?>" data-alt="<?php echo esc_attr($alt_text); ?>">
              <?php echo wp_get_attachment_image($gallery_id, 'woocommerce_gallery_thumbnail'); ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
        <?php if ($description_text) : ?>
          <details open>
            <summary><?php esc_html_e('Description', 'nirog-bhumi'); ?></summary>
            <div class="product-longform"><?php echo wp_kses_post(wpautop($description_text)); ?></div>
          </details>
        <?php endif; ?>

        <?php if ($includes) : ?>
          <details open>
            <summary><?php esc_html_e("What's included", 'nirog-bhumi'); ?></summary>
            <ul class="product-includes-list">
              <?php foreach ($includes as $include_item) : ?>
                <li><?php echo esc_html($include_item); ?></li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>

        <?php if ($ritual) : ?>
          <details>
            <summary><?php esc_html_e('How to use', 'nirog-bhumi'); ?></summary>
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

  <?php if (!empty($cross_sell_products)) : ?>
    <section class="store-shelf buy-with-shelf">
      <div class="store-shelf-title">
        <div>
          <p class="eyebrow"><?php esc_html_e('Complete the ritual', 'nirog-bhumi'); ?></p>
          <h2><?php esc_html_e('Buy it with', 'nirog-bhumi'); ?></h2>
        </div>
      </div>
      <div class="buy-with-grid">
        <?php foreach ($cross_sell_products as $cross_sell_product) :
          $cross_sell_buyable = nirog_bhumi_product_is_buyable($cross_sell_product);
          $cross_sell_permalink = get_permalink($cross_sell_product->get_id());
          ?>
          <div class="buy-with-card">
            <a class="buy-with-media" href="<?php echo esc_url($cross_sell_permalink); ?>">
              <?php echo $cross_sell_product->get_image('woocommerce_thumbnail'); ?>
            </a>
            <div class="buy-with-body">
              <a class="buy-with-name" href="<?php echo esc_url($cross_sell_permalink); ?>"><?php echo esc_html($cross_sell_product->get_name()); ?></a>
              <strong class="buy-with-price"><?php echo wp_kses_post($cross_sell_product->get_price_html()); ?></strong>
              <?php if ($cross_sell_buyable) : ?>
                <a href="<?php echo esc_url(add_query_arg('add-to-cart', $cross_sell_product->get_id(), wc_get_cart_url())); ?>"
                  data-quantity="1"
                  data-product_id="<?php echo esc_attr($cross_sell_product->get_id()); ?>"
                  data-product_sku="<?php echo esc_attr($cross_sell_product->get_sku()); ?>"
                  class="buy-with-add ajax_add_to_cart add_to_cart_button"
                  rel="nofollow"><?php esc_html_e('Add', 'nirog-bhumi'); ?></a>
              <?php else : ?>
                <a class="buy-with-add" href="<?php echo esc_url($cross_sell_permalink); ?>"><?php esc_html_e('View', 'nirog-bhumi'); ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

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
