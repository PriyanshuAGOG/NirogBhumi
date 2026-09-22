<?php
/**
 * Store landing page.
 *
 * Reads the live WooCommerce catalogue and honours the store status set in
 * WooCommerce > Nirog Bhumi Store, so the same page carries the site from
 * pre-launch through to a working shop. Only physical goods are sold here -
 * consultations and programmes are promoted with a plain signpost card
 * between shelves, never as catalogue products.
 */

get_header();

$woo_ready = function_exists('nirog_bhumi_woocommerce_active') && nirog_bhumi_woocommerce_active();
$show_catalogue = $woo_ready && nirog_bhumi_store_catalogue_is_visible();
$selling = $woo_ready && nirog_bhumi_store_selling_is_open();
?>
<main>
<?php if (!$show_catalogue) : ?>

  <section class="store-coming-soon">
    <div>
      <p class="eyebrow"><?php esc_html_e('Store', 'nirog-bhumi'); ?></p>
      <h1><?php esc_html_e('Coming soon.', 'nirog-bhumi'); ?></h1>
      <p class="store-coming-soon-note"><?php esc_html_e('The cure kit, food staples and practice tools we use in our programmes are being prepared for sale. Until then, the consultation and programme pages are the place to start.', 'nirog-bhumi'); ?></p>
      <div class="hero-buttons">
        <a class="pill primary" href="<?php echo esc_url(home_url('/consultation/')); ?>"><?php esc_html_e('Book Consultation', 'nirog-bhumi'); ?></a>
        <a class="pill ghost" href="<?php echo esc_url(home_url('/programmes/')); ?>"><?php esc_html_e('See Programs', 'nirog-bhumi'); ?></a>
      </div>
    </div>
  </section>

<?php else : ?>

<section class="store-page-minimal">

  <?php if (!$selling) : ?>
    <p class="store-status-banner">
      <?php echo 'preview' === nirog_bhumi_store_status()
        ? esc_html__('The store is in preview. Everything can be browsed, but ordering is not open yet.', 'nirog-bhumi')
        : esc_html__('Store preview, visible to shop managers only. Visitors still see the Coming soon page.', 'nirog-bhumi'); ?>
    </p>
  <?php endif; ?>

  <?php
  // The whole launch catalogue is only five products, so every product
  // gets its own hero slide and its own place in the Featured Products
  // shelf below - nothing here is hardcoded to today's five, it is simply
  // every published, visible product in menu-order.
  $nb_all_products = wc_get_products([
    'status' => 'publish',
    'limit' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC',
    'visibility' => 'visible',
  ]);
  ?>

  <?php if ($nb_all_products) : ?>
    <section class="store-hero-carousel" data-nb-carousel>
      <div class="store-hero-track" data-nb-carousel-track>
        <?php foreach ($nb_all_products as $nb_slide_product) :
          $nb_slide_terms = get_the_terms($nb_slide_product->get_id(), 'product_cat');
          $nb_slide_term = (!is_wp_error($nb_slide_terms) && $nb_slide_terms) ? reset($nb_slide_terms) : null;
          $nb_slide_includes = [];
          if (preg_match_all('/<li>(.*?)<\/li>/s', $nb_slide_product->get_description(), $nb_slide_matches)) {
            $nb_slide_includes = array_map('wp_strip_all_tags', $nb_slide_matches[1]);
          }
          $nb_slide_buyable = nirog_bhumi_product_is_buyable($nb_slide_product);
          ?>
          <div class="store-hero-slide" data-nb-carousel-slide>
            <figure class="store-hero-media">
              <?php echo $nb_slide_product->get_image('woocommerce_single'); ?>
              <span class="store-hero-badge"><?php echo $nb_slide_product->is_featured() ? esc_html__('Star product', 'nirog-bhumi') : esc_html($nb_slide_term ? $nb_slide_term->name : ''); ?></span>
            </figure>
            <div class="store-hero-copy">
              <p class="eyebrow"><?php echo esc_html(get_post_meta($nb_slide_product->get_id(), '_nb_eyebrow', true) ?: ($nb_slide_term ? $nb_slide_term->name : '')); ?></p>
              <h1><?php echo esc_html($nb_slide_product->get_name()); ?></h1>
              <p class="store-hero-summary"><?php echo esc_html(wp_strip_all_tags($nb_slide_product->get_short_description())); ?></p>
              <div class="store-hero-price"><?php echo wp_kses_post($nb_slide_product->get_price_html()); ?></div>
              <a class="pill primary" href="<?php echo esc_url(get_permalink($nb_slide_product->get_id())); ?>">
                <?php echo $nb_slide_buyable ? esc_html__('Buy Now', 'nirog-bhumi') : esc_html__('View Product', 'nirog-bhumi'); ?>
              </a>
              <?php if ($nb_slide_includes) : ?>
                <ul class="store-hero-includes">
                  <?php foreach (array_slice($nb_slide_includes, 0, 5) as $nb_slide_item) : ?>
                    <li><?php echo esc_html($nb_slide_item); ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="store-hero-arrow prev" data-nb-carousel-prev aria-label="<?php esc_attr_e('Previous', 'nirog-bhumi'); ?>">&larr;</button>
      <button type="button" class="store-hero-arrow next" data-nb-carousel-next aria-label="<?php esc_attr_e('Next', 'nirog-bhumi'); ?>">&rarr;</button>
      <div class="store-hero-dots" data-nb-carousel-dots>
        <?php foreach ($nb_all_products as $nb_dot_index => $nb_dot_product) : ?>
          <button type="button" class="<?php echo 0 === $nb_dot_index ? 'is-active' : ''; ?>" data-nb-carousel-dot aria-label="<?php echo esc_attr(sprintf(__('Slide %d', 'nirog-bhumi'), $nb_dot_index + 1)); ?>"></button>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php get_template_part('template-parts/store-category-tiles'); ?>

  <?php if ($nb_all_products) : ?>
    <section class="store-shelf">
      <div class="store-shelf-title">
        <div>
          <p class="eyebrow"><?php esc_html_e('Featured', 'nirog-bhumi'); ?></p>
          <h2><?php esc_html_e('The full launch range.', 'nirog-bhumi'); ?></h2>
        </div>
      </div>
      <div class="store-shelf-carousel" data-nb-shelf-carousel>
        <div class="store-shelf-grid store-shelf-track products" data-nb-shelf-track>
          <?php
          foreach ($nb_all_products as $nb_featured_product) {
            $post_object = get_post($nb_featured_product->get_id());
            if (!$post_object) {
              continue;
            }
            setup_postdata($GLOBALS['post'] = $post_object); // phpcs:ignore
            // setup_postdata() alone does not fire the `the_post` action, so
            // WooCommerce's own wc_setup_product_data() hook (which sets
            // $GLOBALS['product']) never runs - content-product.php reads
            // global $product, so without this every card would render the
            // wrong product (or nothing, on the first one).
            wc_setup_product_data($post_object);
            wc_get_template_part('content', 'product');
          }
          wp_reset_postdata();
          ?>
        </div>
        <button type="button" class="store-shelf-arrow prev" data-nb-shelf-prev aria-label="<?php esc_attr_e('Previous products', 'nirog-bhumi'); ?>">&larr;</button>
        <button type="button" class="store-shelf-arrow next" data-nb-shelf-next aria-label="<?php esc_attr_e('More products', 'nirog-bhumi'); ?>">&rarr;</button>
      </div>
    </section>
  <?php endif; ?>

  <?php
  // Category shelves below Featured - Lifestyle, Acupressure, and any
  // future category, each its own browsable shelf rather than just the
  // single combined Featured grid above.
  $nb_shelf_terms = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
    'exclude' => [(int) get_option('default_product_cat')],
    'orderby' => 'menu_order',
    'order' => 'ASC',
  ]);

  $nb_promo_variants = ['consultation', 'yoga_programme'];
  if (!is_wp_error($nb_shelf_terms)) :
    $nb_shelf_index = 0;
    foreach ($nb_shelf_terms as $nb_shelf) :
      $nb_shelf_products = wc_get_products([
        'status' => 'publish',
        'limit' => 4,
        'category' => [$nb_shelf->slug],
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'visibility' => 'visible',
      ]);
      if (!$nb_shelf_products) {
        continue;
      }
      $nb_shelf_index++;
      ?>
      <section class="store-shelf">
        <div class="store-shelf-title">
          <div>
            <p class="eyebrow"><?php echo esc_html($nb_shelf->name); ?></p>
            <?php if ($nb_shelf->description) : ?><h2><?php echo esc_html($nb_shelf->description); ?></h2><?php endif; ?>
          </div>
          <a href="<?php echo esc_url(get_term_link($nb_shelf)); ?>"><?php esc_html_e('See all', 'nirog-bhumi'); ?></a>
        </div>
        <div class="store-shelf-grid products">
          <?php
          foreach ($nb_shelf_products as $nb_shelf_product) {
            $post_object = get_post($nb_shelf_product->get_id());
            if (!$post_object) {
              continue;
            }
            setup_postdata($GLOBALS['post'] = $post_object); // phpcs:ignore
            wc_setup_product_data($post_object);
            wc_get_template_part('content', 'product');
          }
          wp_reset_postdata();
          ?>
        </div>
      </section>
      <?php
      if (function_exists('nirog_bhumi_render_store_promo_card')) {
        $nb_promo_variant = $nb_promo_variants[($nb_shelf_index - 1) % count($nb_promo_variants)];
        $nb_promo_side = 0 === ($nb_shelf_index - 1) % 2 ? 'left' : 'right';
        nirog_bhumi_render_store_promo_card($nb_promo_variant, $nb_promo_side);
      }
    endforeach;
  endif;
  ?>

  <section class="store-legal-note">
    <p><?php esc_html_e('Nirog Bhumi products support daily wellness routines. They do not diagnose, treat or cure any condition, and they do not replace medical advice, diagnosis or prescribed medication. Speak with your doctor before changing medication, diet or activity.', 'nirog-bhumi'); ?></p>
  </section>

</section>

<?php endif; ?>
</main>
<?php
get_footer();
