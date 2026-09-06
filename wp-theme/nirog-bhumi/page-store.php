<?php
/**
 * Store landing page.
 *
 * Previously a hard-coded "Coming soon" panel with the real shelves hidden
 * behind aria-hidden markup. It now reads the live WooCommerce catalogue and
 * honours the store status set in WooCommerce > Nirog Bhumi Store, so the same
 * page carries the site from pre-launch through to a working shop.
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
  ?>

  <?php if ($kit) : ?>
    <section class="cure-kit-section">
      <div class="kit-viewport-card">
        <div class="cure-kit-copy">
          <p class="eyebrow"><?php echo esc_html(get_post_meta($kit->get_id(), '_nb_eyebrow', true) ?: __('Nirog Bhumi Cure Kit', 'nirog-bhumi')); ?></p>
          <h1><?php
          // .cure-kit-copy h1 span is styled as a stacked line, so the name is
          // split after its first word, matching the original hero markup.
          $kit_name_parts = explode(' ', $kit->get_name(), 2);
          foreach ($kit_name_parts as $kit_name_part) {
            echo '<span>' . esc_html($kit_name_part) . '</span>';
          }
          ?></h1>
          <a class="pill primary" href="<?php echo esc_url(get_permalink($kit->get_id())); ?>">
            <?php echo nirog_bhumi_product_is_buyable($kit) ? esc_html__('Buy Now', 'nirog-bhumi') : esc_html__('View the kit', 'nirog-bhumi'); ?>
          </a>
        </div>
        <figure><?php echo $kit->get_image('woocommerce_single'); ?></figure>
        <div class="kit-panel">
          <span><?php esc_html_e('Complete bundle', 'nirog-bhumi'); ?></span>
          <h2><?php esc_html_e('Daily rhythm essentials', 'nirog-bhumi'); ?></h2>
          <strong><?php echo wp_kses_post($kit->get_price_html()); ?></strong>
        </div>
        <?php
        $includes = [];
        if (preg_match_all('/<li>(.*?)<\/li>/s', $kit->get_description(), $matches)) {
          $includes = array_map('wp_strip_all_tags', $matches[1]);
        }
        ?>
        <?php if ($includes) : ?>
          <ol class="kit-includes">
            <?php foreach (array_slice($includes, 0, 5) as $index => $item) : ?>
              <li><b><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></b><span><?php echo esc_html($item); ?></span></li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($dispatch) : ?>
    <p class="store-dispatch-note store-dispatch-strip"><?php echo esc_html($dispatch); ?></p>
  <?php endif; ?>

  <?php
  $shelves = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
    'exclude' => [(int) get_option('default_product_cat')],
  ]);

  if (!is_wp_error($shelves)) :
    foreach ($shelves as $shelf) :
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
