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
  $featured = wc_get_products([
    'status' => 'publish',
    'featured' => true,
    'limit' => 1,
    'orderby' => 'menu_order',
    'order' => 'ASC',
  ]);
  $kit = $featured ? $featured[0] : null;
  // The hero above already shows the star product in full. If its category
  // holds nothing else, showing that same category again as a near-empty
  // shelf right underneath would just duplicate the hero.
  $nb_hero_only_category = 0;
  if ($kit) {
    $kit_terms = get_the_terms($kit->get_id(), 'product_cat');
    if ($kit_terms && !is_wp_error($kit_terms)) {
      $kit_term = reset($kit_terms);
      if ((int) $kit_term->count <= 1) {
        $nb_hero_only_category = (int) $kit_term->term_id;
      }
    }
  }
  ?>

  <?php if ($kit) :
    $kit_includes = [];
    if (preg_match_all('/<li>(.*?)<\/li>/s', $kit->get_description(), $kit_matches)) {
      $kit_includes = array_map('wp_strip_all_tags', $kit_matches[1]);
    }
    ?>
    <section class="store-hero">
      <figure class="store-hero-media">
        <?php echo $kit->get_image('woocommerce_single'); ?>
        <span class="store-hero-badge"><?php esc_html_e('Star product', 'nirog-bhumi'); ?></span>
      </figure>
      <div class="store-hero-copy">
        <p class="eyebrow"><?php echo esc_html(get_post_meta($kit->get_id(), '_nb_eyebrow', true) ?: __('Nirog Bhumi Cure Kit', 'nirog-bhumi')); ?></p>
        <h1><?php echo esc_html($kit->get_name()); ?></h1>
        <p class="store-hero-summary"><?php echo esc_html(wp_strip_all_tags($kit->get_short_description())); ?></p>
        <div class="store-hero-price"><?php echo wp_kses_post($kit->get_price_html()); ?></div>
        <a class="pill primary" href="<?php echo esc_url(get_permalink($kit->get_id())); ?>">
          <?php echo nirog_bhumi_product_is_buyable($kit) ? esc_html__('Buy Now', 'nirog-bhumi') : esc_html__('View the kit', 'nirog-bhumi'); ?>
        </a>
        <?php if ($kit_includes) : ?>
          <ul class="store-hero-includes">
            <?php foreach (array_slice($kit_includes, 0, 5) as $kit_item) : ?>
              <li><?php echo esc_html($kit_item); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
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

  if (!is_wp_error($shelves)) :
    $nb_shelf_index = 0;
    foreach ($shelves as $shelf) :
      if ($nb_hero_only_category && (int) $shelf->term_id === $nb_hero_only_category) {
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
      if (2 === $nb_shelf_index && function_exists('nirog_bhumi_render_store_promo_card')) {
        nirog_bhumi_render_store_promo_card('consultation');
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
