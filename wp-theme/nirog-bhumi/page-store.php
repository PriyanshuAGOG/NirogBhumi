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
$dispatch = $woo_ready ? nirog_bhumi_store_dispatch_note() : '';
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
  // One hero slide per product category, so the carousel automatically
  // grows as new categories (acupressure, nutrition, etc.) are created and
  // stocked - nothing here is hardcoded to today's three categories. Each
  // slide's product is that category's featured product if it has one,
  // otherwise its first product by menu order.
  $nb_carousel_terms = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
    'exclude' => [(int) get_option('default_product_cat')],
    'orderby' => 'menu_order',
    'order' => 'ASC',
  ]);
  $nb_carousel_slides = [];
  if (!is_wp_error($nb_carousel_terms)) {
    foreach ($nb_carousel_terms as $nb_term) {
      $nb_term_products = wc_get_products([
        'status' => 'publish',
        'featured' => true,
        'limit' => 1,
        'category' => [$nb_term->slug],
        'orderby' => 'menu_order',
        'order' => 'ASC',
      ]);
      if (!$nb_term_products) {
        $nb_term_products = wc_get_products([
          'status' => 'publish',
          'limit' => 1,
          'category' => [$nb_term->slug],
          'orderby' => 'menu_order',
          'order' => 'ASC',
        ]);
      }
      if ($nb_term_products) {
        $nb_carousel_slides[] = ['product' => $nb_term_products[0], 'term' => $nb_term];
      }
    }
  }
  // A shelf below that would show only the same single product already on
  // full display in its carousel slide is not a shelf worth browsing -
  // skip any category with one product only when building the shelves.
  $nb_single_product_categories = [];
  foreach ($nb_carousel_slides as $nb_slide) {
    if ((int) $nb_slide['term']->count <= 1) {
      $nb_single_product_categories[] = (int) $nb_slide['term']->term_id;
    }
  }
  ?>

  <?php if ($nb_carousel_slides) : ?>
    <section class="store-hero-carousel" data-nb-carousel>
      <div class="store-hero-track" data-nb-carousel-track>
        <?php foreach ($nb_carousel_slides as $nb_slide) :
          $nb_slide_product = $nb_slide['product'];
          $nb_slide_term = $nb_slide['term'];
          $nb_slide_includes = [];
          if (preg_match_all('/<li>(.*?)<\/li>/s', $nb_slide_product->get_description(), $nb_slide_matches)) {
            $nb_slide_includes = array_map('wp_strip_all_tags', $nb_slide_matches[1]);
          }
          $nb_slide_buyable = nirog_bhumi_product_is_buyable($nb_slide_product);
          ?>
          <div class="store-hero-slide" data-nb-carousel-slide>
            <figure class="store-hero-media">
              <?php echo $nb_slide_product->get_image('woocommerce_single'); ?>
              <span class="store-hero-badge"><?php echo $nb_slide_product->is_featured() ? esc_html__('Star product', 'nirog-bhumi') : esc_html($nb_slide_term->name); ?></span>
            </figure>
            <div class="store-hero-copy">
              <p class="eyebrow"><?php echo esc_html(get_post_meta($nb_slide_product->get_id(), '_nb_eyebrow', true) ?: $nb_slide_term->name); ?></p>
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
        <?php foreach ($nb_carousel_slides as $nb_dot_index => $nb_slide) : ?>
          <button type="button" class="<?php echo 0 === $nb_dot_index ? 'is-active' : ''; ?>" data-nb-carousel-dot aria-label="<?php echo esc_attr(sprintf(__('Slide %d', 'nirog-bhumi'), $nb_dot_index + 1)); ?>"></button>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php get_template_part('template-parts/store-category-tiles'); ?>

  <?php if ($dispatch) : ?>
    <p class="store-dispatch-note store-dispatch-strip"><?php echo esc_html($dispatch); ?></p>
  <?php endif; ?>

  <?php
  $shelves = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
    'exclude' => [(int) get_option('default_product_cat')],
    'orderby' => 'menu_order',
    'order' => 'ASC',
  ]);

  $nb_promo_variants = ['consultation', 'yoga_programme'];
  if (!is_wp_error($shelves)) :
    $nb_shelf_index = 0;
    foreach ($shelves as $shelf) :
      if (in_array((int) $shelf->term_id, $nb_single_product_categories, true)) {
        continue;
      }
      $products = wc_get_products([
        'status' => 'publish',
        'limit' => 4,
        'category' => [$shelf->slug],
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'visibility' => 'visible',
      ]);
      if (!$products) {
        continue;
      }
      $nb_shelf_index++;
      ?>
      <section class="store-shelf">
        <div class="store-shelf-title">
          <div>
            <p class="eyebrow"><?php echo esc_html($shelf->name); ?></p>
            <?php if ($shelf->description) : ?><h2><?php echo esc_html($shelf->description); ?></h2><?php endif; ?>
          </div>
          <a href="<?php echo esc_url(get_term_link($shelf)); ?>"><?php esc_html_e('See all', 'nirog-bhumi'); ?></a>
        </div>
        <div class="store-shelf-grid products">
          <?php
          foreach ($products as $shelf_product) {
            $post_object = get_post($shelf_product->get_id());
            if (!$post_object) {
              continue;
            }
            setup_postdata($GLOBALS['post'] = $post_object); // phpcs:ignore
            // setup_postdata() alone does not fire the `the_post` action, so
            // WooCommerce's own wc_setup_product_data() hook (which sets
            // $GLOBALS['product']) never runs - content-product.php reads
            // global $product, so without this every card in the shelf would
            // render the wrong product (or nothing, on the first shelf).
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
        nirog_bhumi_render_store_promo_card($nb_promo_variant);
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
