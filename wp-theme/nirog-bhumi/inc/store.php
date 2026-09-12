<?php
/**
 * WooCommerce store behaviour for Nirog Bhumi.
 *
 * Covers the three things the theme needs beyond stock WooCommerce:
 *
 * 1. A launch gate, so the catalogue can be built and reviewed before anything
 *    is sellable.
 * 2. Per-product launch state, HSN and GST rate, feeding the existing
 *    financial-year invoice sequence in inc/invoice-pdf.php.
 * 3. A waitlist for products that are visible but not yet on sale, stored as
 *    ordinary form entries so the existing export and erasure tools apply.
 */

if (!defined('ABSPATH')) {
  exit;
}

/* -------------------------------------------------------------------------
 * Launch state
 * ---------------------------------------------------------------------- */

/**
 * Per-product launch state: sale, coming_soon or enquiry.
 */
function nirog_bhumi_product_launch_status($product) {
  $product = is_numeric($product) ? wc_get_product($product) : $product;
  if (!$product) {
    return 'coming_soon';
  }
  $status = get_post_meta($product->get_id(), '_nb_launch_status', true);
  if (in_array($status, ['sale', 'coming_soon', 'enquiry'], true)) {
    return $status;
  }
  return $product->get_price() > 0 ? 'sale' : 'coming_soon';
}

/**
 * A product is buyable only when the store is open, the product is on sale and
 * it carries a price. Everything else falls back to the waitlist or an enquiry.
 */
function nirog_bhumi_product_is_buyable($product) {
  $product = is_numeric($product) ? wc_get_product($product) : $product;
  if (!$product) {
    return false;
  }
  if (!nirog_bhumi_store_selling_is_open()) {
    return false;
  }
  if ('sale' !== nirog_bhumi_product_launch_status($product)) {
    return false;
  }
  return '' !== $product->get_price() && $product->get_price() > 0;
}

/**
 * Hold back purchases for anything that is not released yet.
 *
 * The consultation product is exempt: the consultation flow has its own
 * checkout and predates the store, and must keep working while the store is
 * still in Coming soon or Preview.
 */
function nirog_bhumi_filter_is_purchasable($purchasable, $product) {
  if (!$purchasable) {
    return false;
  }
  if (function_exists('nirog_bhumi_consultation_product_id')
    && $product->get_id() === nirog_bhumi_consultation_product_id()) {
    return true;
  }
  return nirog_bhumi_product_is_buyable($product);
}
add_filter('woocommerce_is_purchasable', 'nirog_bhumi_filter_is_purchasable', 20, 2);
add_filter('woocommerce_variation_is_purchasable', 'nirog_bhumi_filter_is_purchasable', 20, 2);

/**
 * Show a launch label instead of an empty price for unreleased products.
 */
function nirog_bhumi_launch_price_html($price_html, $product) {
  if (is_admin() && !wp_doing_ajax()) {
    return $price_html;
  }
  switch (nirog_bhumi_product_launch_status($product)) {
    case 'coming_soon':
      return '<span class="nb-launch-label">' . esc_html__('Coming soon', 'nirog-bhumi') . '</span>';
    case 'enquiry':
      return '<span class="nb-launch-label">' . esc_html__('Consult first', 'nirog-bhumi') . '</span>';
  }
  return $price_html;
}
add_filter('woocommerce_get_price_html', 'nirog_bhumi_launch_price_html', 20, 2);

/**
 * Keep the whole catalogue out of public view while the store is in Coming soon.
 */
function nirog_bhumi_gate_store_front_end() {
  if (is_admin() || nirog_bhumi_store_catalogue_is_visible()) {
    return;
  }
  if (!function_exists('is_woocommerce')) {
    return;
  }
  // is_woocommerce() covers the shop, product pages and product taxonomies
  // only. Cart and checkout are deliberately left alone: the consultation
  // payment flow runs through the same cart and predates the store.
  if (is_woocommerce()) {
    wp_safe_redirect(home_url('/store/'));
    exit;
  }
}
add_action('template_redirect', 'nirog_bhumi_gate_store_front_end', 8);

/**
 * Hide the catalogue from search and feeds while it is not launched.
 */
function nirog_bhumi_gate_product_queries($query) {
  if (is_admin() || !$query->is_main_query() || nirog_bhumi_store_catalogue_is_visible()) {
    return;
  }
  if ($query->is_search() || $query->is_feed()) {
    $types = (array) $query->get('post_type');
    $types = array_diff($types ?: ['post', 'page'], ['product']);
    $query->set('post_type', $types ?: ['post', 'page']);
  }
}
add_action('pre_get_posts', 'nirog_bhumi_gate_product_queries');

/* -------------------------------------------------------------------------
 * Product fields: launch state, HSN, GST, editorial copy
 * ---------------------------------------------------------------------- */

function nirog_bhumi_product_data_tab($tabs) {
  $tabs['nirog_bhumi'] = [
    'label' => __('Nirog Bhumi', 'nirog-bhumi'),
    'target' => 'nirog_bhumi_product_data',
    'class' => [],
    'priority' => 65,
  ];
  return $tabs;
}
add_filter('woocommerce_product_data_tabs', 'nirog_bhumi_product_data_tab');

function nirog_bhumi_product_data_panel() {
  global $post;
  $store = nirog_bhumi_get_store_settings();
  $invoice = function_exists('nirog_bhumi_get_settings') ? nirog_bhumi_get_settings() : [];
  $fallback_rate = '' !== $store['goods_gst_rate'] ? $store['goods_gst_rate'] : ($invoice['invoice_gst_rate'] ?? '');
  ?>
  <div id="nirog_bhumi_product_data" class="panel woocommerce_options_panel hidden">
    <div class="options_group">
      <?php
      woocommerce_wp_select([
        'id' => '_nb_launch_status',
        'label' => __('Launch state', 'nirog-bhumi'),
        'options' => [
          'sale' => __('On sale - can be bought when the store is open', 'nirog-bhumi'),
          'coming_soon' => __('Coming soon - visible, waitlist only', 'nirog-bhumi'),
          'enquiry' => __('Enquiry only - visible, links to a consultation or programme', 'nirog-bhumi'),
        ],
        'value' => nirog_bhumi_product_launch_status($post->ID),
        'desc_tip' => true,
        'description' => __('Nothing can be bought while the store status is Coming soon or Preview, whatever is set here.', 'nirog-bhumi'),
      ]);
      woocommerce_wp_text_input([
        'id' => '_nb_eyebrow',
        'label' => __('Shelf label', 'nirog-bhumi'),
        'placeholder' => __('Cleansing / Water', 'nirog-bhumi'),
        'desc_tip' => true,
        'description' => __('The small uppercase label above the product name on cards and the product page.', 'nirog-bhumi'),
      ]);
      woocommerce_wp_text_input([
        'id' => '_nb_enquiry_url',
        'label' => __('Enquiry link', 'nirog-bhumi'),
        'placeholder' => '/6-month-diabetes-reversal/',
        'desc_tip' => true,
        'description' => __('Where the button goes for enquiry-only products. Relative paths are resolved against the site address.', 'nirog-bhumi'),
      ]);
      woocommerce_wp_text_input([
        'id' => '_nb_enquiry_label',
        'label' => __('Enquiry button text', 'nirog-bhumi'),
        'placeholder' => __('View programme', 'nirog-bhumi'),
      ]);
      ?>
    </div>
    <div class="options_group">
      <?php
      woocommerce_wp_textarea_input([
        'id' => '_nb_ritual',
        'label' => __('Suggested ritual', 'nirog-bhumi'),
        'desc_tip' => true,
        'description' => __('How the item is used day to day. Shown in a panel on the product page.', 'nirog-bhumi'),
      ]);
      woocommerce_wp_textarea_input([
        'id' => '_nb_caution',
        'label' => __('Important note', 'nirog-bhumi'),
        'desc_tip' => true,
        'description' => __('Safety note, contraindications and who should avoid the item. Shown in a highlighted panel.', 'nirog-bhumi'),
      ]);
      ?>
    </div>
    <div class="options_group">
      <?php
      woocommerce_wp_text_input([
        'id' => '_nb_hsn',
        'label' => __('HSN code', 'nirog-bhumi'),
        'placeholder' => $store['default_hsn'] ?: __('Confirm with your accountant', 'nirog-bhumi'),
        'desc_tip' => true,
        'description' => __('Printed on the tax invoice for this item. Leave blank to use the store default. If both are blank, no HSN is printed.', 'nirog-bhumi'),
      ]);
      woocommerce_wp_text_input([
        'id' => '_nb_gst_rate',
        'label' => __('GST rate (%)', 'nirog-bhumi'),
        'type' => 'number',
        'custom_attributes' => ['min' => '0', 'max' => '100', 'step' => '0.01'],
        'placeholder' => $fallback_rate,
        'desc_tip' => true,
        'description' => __('Rate for this item. Leave blank to use the store default for goods. GST rates differ by goods category, so confirm each one rather than copying the services rate.', 'nirog-bhumi'),
      ]);
      ?>
    </div>
  </div>
  <?php
}
add_action('woocommerce_product_data_panels', 'nirog_bhumi_product_data_panel');

function nirog_bhumi_save_product_fields($product_id) {
  $text_fields = ['_nb_eyebrow', '_nb_enquiry_label', '_nb_hsn'];
  foreach ($text_fields as $field) {
    if (isset($_POST[$field])) {
      update_post_meta($product_id, $field, sanitize_text_field(wp_unslash($_POST[$field])));
    }
  }

  if (isset($_POST['_nb_launch_status'])) {
    $status = sanitize_key(wp_unslash($_POST['_nb_launch_status']));
    update_post_meta($product_id, '_nb_launch_status', in_array($status, ['sale', 'coming_soon', 'enquiry'], true) ? $status : 'coming_soon');
  }

  if (isset($_POST['_nb_enquiry_url'])) {
    $url = trim((string) wp_unslash($_POST['_nb_enquiry_url']));
    update_post_meta($product_id, '_nb_enquiry_url', 0 === strpos($url, '/') ? sanitize_text_field($url) : esc_url_raw($url));
  }

  foreach (['_nb_ritual', '_nb_caution'] as $field) {
    if (isset($_POST[$field])) {
      update_post_meta($product_id, $field, sanitize_textarea_field(wp_unslash($_POST[$field])));
    }
  }

  if (isset($_POST['_nb_gst_rate'])) {
    $rate = trim((string) wp_unslash($_POST['_nb_gst_rate']));
    update_post_meta($product_id, '_nb_gst_rate', '' === $rate ? '' : (string) max(0, min(100, (float) $rate)));
  }
}
add_action('woocommerce_process_product_meta', 'nirog_bhumi_save_product_fields');

/**
 * Resolve the HSN and GST rate that should appear on the invoice for a product.
 * Returns empty strings rather than a guess when nothing has been confirmed.
 */
function nirog_bhumi_product_tax_details($product_id) {
  $store = nirog_bhumi_get_store_settings();
  $invoice = function_exists('nirog_bhumi_get_settings') ? nirog_bhumi_get_settings() : [];

  $hsn = (string) get_post_meta($product_id, '_nb_hsn', true);
  if ('' === $hsn) {
    $hsn = (string) $store['default_hsn'];
  }

  $rate = (string) get_post_meta($product_id, '_nb_gst_rate', true);
  if ('' === $rate) {
    $rate = '' !== $store['goods_gst_rate'] ? $store['goods_gst_rate'] : (string) ($invoice['invoice_gst_rate'] ?? '');
  }

  return ['hsn' => $hsn, 'gst_rate' => $rate];
}

/**
 * Stamp the HSN and rate onto the order line at checkout so a later change to
 * the product does not rewrite the tax details of an invoice already issued.
 */
function nirog_bhumi_store_order_line_tax_meta($item, $cart_item_key, $values, $order) {
  $product_id = $item->get_variation_id() ?: $item->get_product_id();
  $details = nirog_bhumi_product_tax_details($product_id);
  if ('' !== $details['hsn']) {
    $item->add_meta_data('_nb_hsn', $details['hsn'], true);
  }
  if ('' !== $details['gst_rate']) {
    $item->add_meta_data('_nb_gst_rate', $details['gst_rate'], true);
  }
}
add_action('woocommerce_checkout_create_order_line_item', 'nirog_bhumi_store_order_line_tax_meta', 10, 4);

/**
 * Hide the internal meta keys from the customer-facing order line.
 */
function nirog_bhumi_hidden_order_item_meta($keys) {
  $keys[] = '_nb_hsn';
  $keys[] = '_nb_gst_rate';
  return $keys;
}
add_filter('woocommerce_hidden_order_itemmeta', 'nirog_bhumi_hidden_order_item_meta');

/* -------------------------------------------------------------------------
 * Waitlist
 * ---------------------------------------------------------------------- */

/**
 * Notify-me sign-ups reuse the existing nb_form_entry post type, so the export
 * and erasure tools in inc/data-admin.php cover them without extra work.
 */
function nirog_bhumi_handle_waitlist() {
  if (!isset($_POST['nirog_waitlist_nonce'])
    || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nirog_waitlist_nonce'])), 'nirog_waitlist_submit')) {
    wp_die(esc_html__('Security check failed. Please go back and submit the form again.', 'nirog-bhumi'));
  }

  if (!nirog_bhumi_store_waitlist_enabled()) {
    wp_safe_redirect(home_url('/store/'));
    exit;
  }

  $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
  $product = $product_id && function_exists('wc_get_product') ? wc_get_product($product_id) : null;
  $product_name = $product ? $product->get_name() : __('Store product', 'nirog-bhumi');
  $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
  $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

  $redirect = $product ? get_permalink($product_id) : home_url('/store/');

  if (!is_email($email)) {
    wp_safe_redirect(add_query_arg('nb_waitlist', 'invalid', $redirect));
    exit;
  }

  $entry_id = wp_insert_post([
    'post_type' => 'nb_form_entry',
    'post_status' => 'private',
    'post_title' => sprintf('%s - %s - %s', __('Store waitlist', 'nirog-bhumi'), $product_name, current_time('d M Y H:i')),
  ]);

  if (is_wp_error($entry_id) || !$entry_id) {
    wp_safe_redirect(add_query_arg('nb_waitlist', 'error', $redirect));
    exit;
  }

  update_post_meta($entry_id, 'form_type', __('Store waitlist', 'nirog-bhumi'));
  update_post_meta($entry_id, 'product', $product_name);
  update_post_meta($entry_id, 'product_id', $product_id);
  update_post_meta($entry_id, 'name', $name);
  update_post_meta($entry_id, 'email', $email);

  $admin_email = get_option('admin_email');
  if ($admin_email) {
    wp_mail(
      $admin_email,
      sprintf(__('Store waitlist - %s', 'nirog-bhumi'), $product_name),
      sprintf(
        "Product: %s\nName: %s\nEmail: %s\n\nView in WordPress dashboard: %s",
        $product_name,
        $name,
        $email,
        admin_url('post.php?post=' . $entry_id . '&action=edit')
      )
    );
  }

  wp_safe_redirect(add_query_arg('nb_waitlist', 'saved', $redirect));
  exit;
}
add_action('admin_post_nopriv_nirog_waitlist_submit', 'nirog_bhumi_handle_waitlist');
add_action('admin_post_nirog_waitlist_submit', 'nirog_bhumi_handle_waitlist');

/**
 * Render the waitlist form, or the enquiry button, under a product.
 */
function nirog_bhumi_render_launch_action($product, $context = 'single') {
  $status = nirog_bhumi_product_launch_status($product);

  if ('enquiry' === $status) {
    $url = (string) get_post_meta($product->get_id(), '_nb_enquiry_url', true);
    $label = (string) get_post_meta($product->get_id(), '_nb_enquiry_label', true);
    $url = $url ?: '/consultation/';
    $url = 0 === strpos($url, '/') ? home_url($url) : $url;
    printf(
      '<a class="pill primary nb-launch-action" href="%s">%s</a>',
      esc_url($url),
      esc_html($label ?: __('Enquire', 'nirog-bhumi'))
    );
    return;
  }

  if (!nirog_bhumi_store_waitlist_enabled()) {
    printf('<span class="nb-launch-label">%s</span>', esc_html__('Coming soon', 'nirog-bhumi'));
    return;
  }

  if ('loop' === $context) {
    printf(
      '<a class="nb-launch-action" href="%s">%s</a>',
      esc_url(get_permalink($product->get_id()) . '#nb-waitlist'),
      esc_html__('Notify me', 'nirog-bhumi')
    );
    return;
  }

  $state = isset($_GET['nb_waitlist']) ? sanitize_key(wp_unslash($_GET['nb_waitlist'])) : '';
  ?>
  <form class="nb-waitlist" id="nb-waitlist" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="nirog_waitlist_submit">
    <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">
    <?php wp_nonce_field('nirog_waitlist_submit', 'nirog_waitlist_nonce'); ?>
    <strong><?php esc_html_e('Tell me when this is ready', 'nirog-bhumi'); ?></strong>
    <p><?php esc_html_e('We will write to you once, when this product goes on sale. Nothing else.', 'nirog-bhumi'); ?></p>
    <div class="nb-waitlist-fields">
      <label class="screen-reader-text" for="nb-waitlist-name-<?php echo esc_attr($product->get_id()); ?>"><?php esc_html_e('Name', 'nirog-bhumi'); ?></label>
      <input id="nb-waitlist-name-<?php echo esc_attr($product->get_id()); ?>" type="text" name="name" placeholder="<?php esc_attr_e('Your name', 'nirog-bhumi'); ?>">
      <label class="screen-reader-text" for="nb-waitlist-email-<?php echo esc_attr($product->get_id()); ?>"><?php esc_html_e('Email', 'nirog-bhumi'); ?></label>
      <input id="nb-waitlist-email-<?php echo esc_attr($product->get_id()); ?>" type="email" name="email" required placeholder="<?php esc_attr_e('Email address', 'nirog-bhumi'); ?>">
      <button class="pill primary" type="submit"><?php esc_html_e('Notify me', 'nirog-bhumi'); ?></button>
    </div>
    <?php if ('saved' === $state) : ?>
      <p class="nb-waitlist-status is-good"><?php esc_html_e('Saved. We will let you know when this is on sale.', 'nirog-bhumi'); ?></p>
    <?php elseif ('invalid' === $state) : ?>
      <p class="nb-waitlist-status is-bad"><?php esc_html_e('That email address did not look right. Please try again.', 'nirog-bhumi'); ?></p>
    <?php elseif ('error' === $state) : ?>
      <p class="nb-waitlist-status is-bad"><?php esc_html_e('We could not save that. Please try again in a moment.', 'nirog-bhumi'); ?></p>
    <?php endif; ?>
  </form>
  <?php
}

/* -------------------------------------------------------------------------
 * Presentation
 * ---------------------------------------------------------------------- */

/**
 * The theme draws its own product cards and product page, so the default loop
 * and summary callbacks are removed rather than restyled.
 */
function nirog_bhumi_strip_default_woocommerce_output() {
  remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
  remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
  remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
  remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
  remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
  remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
  remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
}
add_action('init', 'nirog_bhumi_strip_default_woocommerce_output');

/**
 * Four products per shelf row, matching the store grid.
 */
function nirog_bhumi_loop_columns() {
  return 4;
}
add_filter('loop_shop_columns', 'nirog_bhumi_loop_columns', 20);

function nirog_bhumi_products_per_page() {
  return 24;
}
add_filter('loop_shop_per_page', 'nirog_bhumi_products_per_page', 20);

/**
 * Drop WooCommerce's own stylesheets. The theme provides the full design.
 */
function nirog_bhumi_dequeue_woocommerce_styles($styles) {
  unset($styles['woocommerce-general'], $styles['woocommerce-smallscreen'], $styles['woocommerce-layout']);
  return $styles;
}
add_filter('woocommerce_enqueue_styles', 'nirog_bhumi_dequeue_woocommerce_styles');

function nirog_bhumi_store_assets() {
  wp_enqueue_style(
    'nirog-bhumi-store',
    get_template_directory_uri() . '/assets/css/store.css',
    ['nirog-bhumi-overrides'],
    '0.5.0'
  );
  wp_enqueue_script(
    'nirog-bhumi-store-carousel',
    get_template_directory_uri() . '/assets/js/store-carousel.js',
    [],
    '0.1.0',
    true
  );
  if (function_exists('is_product') && is_product()) {
    wp_enqueue_script(
      'nirog-bhumi-product-gallery',
      get_template_directory_uri() . '/assets/js/product-gallery.js',
      [],
      '0.1.0',
      true
    );
  }
}
add_action('wp_enqueue_scripts', 'nirog_bhumi_store_assets', 20);

/**
 * Require the wellness acknowledgement on product orders as well as on the
 * existing consultation checkout.
 */
function nirog_bhumi_validate_medical_acknowledgement() {
  if (function_exists('nirog_bhumi_cart_is_consultation_only') && nirog_bhumi_cart_is_consultation_only()) {
    return;
  }
  if (empty($_POST['nb_medical_acknowledgement'])) {
    wc_add_notice(
      __('Please confirm you understand that Nirog Bhumi products support wellness routines and do not replace medical care.', 'nirog-bhumi'),
      'error'
    );
  }
}
add_action('woocommerce_after_checkout_validation', 'nirog_bhumi_validate_medical_acknowledgement');

/**
 * Record the acknowledgement against the order so it can be evidenced later.
 */
function nirog_bhumi_store_acknowledgement_on_order($order) {
  if (!empty($_POST['nb_medical_acknowledgement'])) {
    $order->update_meta_data('_nb_medical_acknowledgement', current_time('mysql'));
  }
  if (!empty($_POST['nb_order_note'])) {
    $order->update_meta_data('_nb_order_note', sanitize_textarea_field(wp_unslash($_POST['nb_order_note'])));
  }
}
add_action('woocommerce_checkout_create_order', 'nirog_bhumi_store_acknowledgement_on_order', 20);

/**
 * The dispatch and free-shipping messages, shown wherever the store needs them.
 */
function nirog_bhumi_store_dispatch_note() {
  $settings = nirog_bhumi_get_store_settings();
  $note = trim((string) $settings['store_note']);
  $threshold = nirog_bhumi_store_free_shipping_threshold();
  $lines = [];
  if ($note) {
    $lines[] = $note;
  }
  if ($threshold > 0 && function_exists('wc_price')) {
    $lines[] = sprintf(
      /* translators: %s: formatted order amount */
      __('Free delivery on orders above %s.', 'nirog-bhumi'),
      wp_strip_all_tags(wc_price($threshold))
    );
  }
  return implode(' ', $lines);
}

/**
 * A promotional card for the consultation/programmes, shown between shelves
 * on the store page. This is deliberately NOT a WooCommerce product - the
 * store sells physical goods only, so a consultation booking or a guided
 * programme never appears in the catalogue, cart or checkout. This card is
 * just a signpost pointing at the real, separate consultation flow.
 */
function nirog_bhumi_render_store_promo_card($variant = 'consultation', $image_side = 'left') {
  $copy = [
    'consultation' => [
      'eyebrow' => __('Still confused?', 'nirog-bhumi'),
      'heading' => __('Book a consultation and see what\'s right for you.', 'nirog-bhumi'),
      'body' => __('A 30-minute session with Gautam Khandelwal helps match the right tools, food staples and practice routine to your reports, medication and daily rhythm - before you spend on anything.', 'nirog-bhumi'),
      'primary_label' => __('Book Consultation', 'nirog-bhumi'),
      'primary_url' => home_url('/consultation/'),
      'secondary_label' => __('See Programs', 'nirog-bhumi'),
      'secondary_url' => home_url('/programmes/'),
      'image' => 'yoga-backbend-opt.jpg',
    ],
    'yoga_programme' => [
      'eyebrow' => __('Tools work best with practice', 'nirog-bhumi'),
      'heading' => __('Pair what you buy with our Yoga for Diabetes program.', 'nirog-bhumi'),
      'body' => __('A neti pot, a tumbler and a mat go further alongside guided asanas, pranayama and meditation built specifically for diabetes reversal. Most people who see real change do both together.', 'nirog-bhumi'),
      'primary_label' => __('Explore Yoga Program', 'nirog-bhumi'),
      'primary_url' => home_url('/yoga-programme/'),
      'secondary_label' => __('See All Programs', 'nirog-bhumi'),
      'secondary_url' => home_url('/programmes/'),
      'image' => 'yoga-twist-opt.jpg',
    ],
  ][$variant] ?? null;
  if (!$copy) {
    return;
  }
  $image_url = get_template_directory_uri() . '/assets/img/' . $copy['image'];
  $side_class = 'right' === $image_side ? ' image-right' : '';
  ?>
  <section class="store-promo-card<?php echo esc_attr($side_class); ?>">
    <figure class="store-promo-media">
      <img src="<?php echo esc_url($image_url); ?>" alt="" loading="lazy">
    </figure>
    <div class="store-promo-body-wrap">
      <p class="eyebrow"><?php echo esc_html($copy['eyebrow']); ?></p>
      <h2><?php echo esc_html($copy['heading']); ?></h2>
      <p class="store-promo-body"><?php echo esc_html($copy['body']); ?></p>
      <div class="store-promo-actions">
        <a class="pill primary" href="<?php echo esc_url($copy['primary_url']); ?>"><?php echo esc_html($copy['primary_label']); ?></a>
        <a class="pill ghost" href="<?php echo esc_url($copy['secondary_url']); ?>"><?php echo esc_html($copy['secondary_label']); ?></a>
      </div>
    </div>
  </section>
  <?php
}

/**
 * Hand-drawn line icon for a "shop by category" tile, in the style of a
 * bordered icon card rather than a photo. No photography (real or fetched)
 * is used here - these are plain inline SVGs so a tile never depends on a
 * category thumbnail being uploaded first. Falls back to a generic leaf
 * mark for any category slug not listed (e.g. one added later in wp-admin).
 */
function nirog_bhumi_store_category_icon($slug) {
  $icons = [
    'cure-kit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h16v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8Z"/><path d="M4 8 6 4h12l2 4"/><path d="M9 12h6M12 9v6"/></svg>',
    'diabetes-friendly-foods' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11a8 8 0 0 0 16 0Z"/><path d="M4 11h16"/><path d="M9 15v2M12 15v3M15 15v2"/><path d="M12 11V5c2 0 3 1.5 3 3"/></svg>',
    'cure-kit-essentials' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5.5" r="2.2"/><path d="M12 8v6M8 20l4-6 4 6M8.5 12.5h7"/></svg>',
    'acupressure' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12c0-3 1.5-8 6-8s6 5 6 8-2 6-6 6-6-3-6-6Z"/><circle cx="9.5" cy="11" r=".6" fill="currentColor" stroke="none"/><circle cx="12" cy="9" r=".6" fill="currentColor" stroke="none"/><circle cx="14.5" cy="11" r=".6" fill="currentColor" stroke="none"/><circle cx="12" cy="13.5" r=".6" fill="currentColor" stroke="none"/></svg>',
  ];
  return $icons[$slug] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c4 3 7 6 7 10a7 7 0 0 1-14 0c0-4 3-7 7-10Z"/></svg>';
}

/**
 * Plain inline "share" icon for the product page share button.
 */
function nirog_bhumi_share_icon() {
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="2.4"/><circle cx="6" cy="12" r="2.4"/><circle cx="18" cy="19" r="2.4"/><path d="m8.1 10.7 7.8-4.4M8.1 13.3l7.8 4.4"/></svg>';
}

/**
 * Cart link in the site header, shown only once the store is actually selling.
 */
function nirog_bhumi_header_cart_link() {
  $settings = nirog_bhumi_get_store_settings();
  if ('yes' !== $settings['show_cart_link'] || !nirog_bhumi_store_selling_is_open() || !WC()->cart) {
    return;
  }
  $count = WC()->cart->get_cart_contents_count();
  printf(
    '<a class="nb-cart-link" href="%s"><span>%s</span><b>%d</b></a>',
    esc_url(wc_get_cart_url()),
    esc_html__('Cart', 'nirog-bhumi'),
    (int) $count
  );
}
add_action('nirog_bhumi_header_end', 'nirog_bhumi_header_cart_link');

/**
 * The store is not linked from public navigation while store_status is
 * Coming soon (visitors would only reach a "Coming soon" panel there anyway).
 * Give shop managers/administrators - the only people who can see the live
 * catalogue - a direct link in the WP admin toolbar so they can jump to
 * /store/ and test it while browsing the site logged in.
 */
function nirog_bhumi_store_admin_bar_link($wp_admin_bar) {
  if (!nirog_bhumi_store_catalogue_is_visible() || is_admin()) {
    return;
  }
  $wp_admin_bar->add_node([
    'id' => 'nirog-bhumi-store-preview',
    'title' => __('Nirog Bhumi Store', 'nirog-bhumi'),
    'href' => home_url('/store/'),
  ]);
}
add_action('admin_bar_menu', 'nirog_bhumi_store_admin_bar_link', 90);
