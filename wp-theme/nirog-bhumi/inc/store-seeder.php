<?php
/**
 * One-click catalogue seeder.
 *
 * Creates the product categories and the launch products defined in
 * store-catalogue.php. The seeder is idempotent and matches on SKU, so it can
 * be run again after a theme update to pick up new catalogue entries without
 * duplicating or overwriting anything the team has edited in the dashboard.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Create the product categories, returning slug => term_id.
 */
function nirog_bhumi_seed_product_categories() {
  $map = [];
  foreach (nirog_bhumi_store_categories() as $slug => $category) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if ($term && !is_wp_error($term)) {
      $map[$slug] = (int) $term->term_id;
      // The catalogue is the source of truth for name/description - keep an
      // already-created term (e.g. from before a rename in this file) in
      // sync rather than only creating terms that don't exist yet.
      if ($term->name !== $category['name'] || $term->description !== $category['description']) {
        wp_update_term($term->term_id, 'product_cat', [
          'name' => $category['name'],
          'description' => $category['description'],
        ]);
      }
      continue;
    }
    $created = wp_insert_term($category['name'], 'product_cat', [
      'slug' => $slug,
      'description' => $category['description'],
    ]);
    if (!is_wp_error($created)) {
      $map[$slug] = (int) $created['term_id'];
    }
  }
  return $map;
}

/**
 * SKUs of products that were once part of the launch catalogue and have
 * since been retired (combos, programmes and consultations sold as
 * products, and any tool later dropped). Kept here so a site that already
 * ran "Create missing products" before these were removed from the
 * catalogue can clean them up with one click, instead of the products
 * silently staying live forever.
 */
function nirog_bhumi_store_retired_skus() {
  return [
    'NB-COMBO-01',
    'NB-COMBO-02',
    'NB-COMBO-03',
    'NB-PROG-06M',
    'NB-PROG-99D',
    'CONSULT-500',
    'NB-TOOL-03',
    // Retired when the catalogue was replaced with the five products from
    // the September 2026 website information pack: the placeholder kit
    // bundle, the food-mix placeholders and the yoga mat placeholder.
    'NB-KIT-01',
    'NB-FOOD-01',
    'NB-FOOD-02',
    'NB-FOOD-03',
    'NB-FOOD-04',
    'NB-TOOL-04',
  ];
}

/**
 * Trash (not permanently delete) any live product matching a retired SKU.
 * Returns a per-outcome tally. Trashing keeps the product recoverable from
 * WooCommerce > Products > Trash in case a SKU was reused by mistake.
 */
function nirog_bhumi_cleanup_retired_products() {
  $result = ['trashed' => 0, 'not_found' => 0];
  if (!function_exists('wc_get_product_id_by_sku')) {
    return $result;
  }
  foreach (nirog_bhumi_store_retired_skus() as $sku) {
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
      $result['not_found']++;
      continue;
    }
    wp_trash_post($product_id);
    $result['trashed']++;
  }
  return $result;
}

/**
 * Import a theme image into the media library once and reuse it afterwards.
 */
function nirog_bhumi_import_theme_image($filename) {
  $path = get_template_directory() . '/assets/img/' . $filename;
  if (!file_exists($path)) {
    return 0;
  }

  $existing = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_key' => '_nb_theme_image',
    'meta_value' => $filename,
  ]);
  if ($existing) {
    return (int) $existing[0];
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  $contents = file_get_contents($path);
  if (false === $contents) {
    return 0;
  }

  $upload = wp_upload_bits($filename, null, $contents);
  if (!empty($upload['error'])) {
    return 0;
  }

  $filetype = wp_check_filetype($upload['file'], null);
  $attachment_id = wp_insert_attachment([
    'post_mime_type' => $filetype['type'],
    'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
    'post_content' => '',
    'post_status' => 'inherit',
  ], $upload['file']);

  if (is_wp_error($attachment_id) || !$attachment_id) {
    return 0;
  }

  wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
  update_post_meta($attachment_id, '_nb_theme_image', $filename);

  return (int) $attachment_id;
}

/**
 * Create one catalogue entry as a WooCommerce product.
 *
 * Returns 'created', 'skipped' or 'failed'. Existing products are never
 * overwritten, so prices and copy edited in the dashboard survive a re-run.
 */
function nirog_bhumi_seed_product($entry, $category_map) {
  if (!function_exists('wc_get_product_id_by_sku')) {
    return 'failed';
  }

  if (wc_get_product_id_by_sku($entry['sku'])) {
    return 'skipped';
  }

  try {
    $product_id = nirog_bhumi_build_seed_product($entry, $category_map);
  } catch (Exception $e) {
    return 'failed';
  }

  if (!$product_id) {
    return 'failed';
  }

  nirog_bhumi_apply_catalogue_meta($product_id, $entry);

  return 'created';
}

/**
 * A catalogue list item can hold a single string or an array of lines (one
 * per bullet/step). Editorial fields on the product itself are always plain
 * text with one bullet or numbered step per line, so an array is joined
 * with newlines and a plain string is used as-is.
 */
function nirog_bhumi_catalogue_lines($value) {
  return is_array($value) ? implode("\n", $value) : (string) $value;
}

/**
 * Write every editorial and tax field a catalogue entry carries onto the
 * product's postmeta. Shared by both the create path (nirog_bhumi_seed_product)
 * and the update path (nirog_bhumi_update_seeded_products), so the two never
 * drift out of sync on which fields they know about.
 */
function nirog_bhumi_apply_catalogue_meta($product_id, $entry) {
  update_post_meta($product_id, '_nb_launch_status', $entry['status']);
  update_post_meta($product_id, '_nb_eyebrow', $entry['eyebrow']);
  if (!empty($entry['ritual'])) {
    update_post_meta($product_id, '_nb_ritual', nirog_bhumi_catalogue_lines($entry['ritual']));
  }
  if (!empty($entry['caution'])) {
    update_post_meta($product_id, '_nb_caution', nirog_bhumi_catalogue_lines($entry['caution']));
  }
  if (!empty($entry['benefits'])) {
    update_post_meta($product_id, '_nb_benefits', nirog_bhumi_catalogue_lines($entry['benefits']));
  }
  if (!empty($entry['diabetes_context'])) {
    update_post_meta($product_id, '_nb_diabetes_note', $entry['diabetes_context']);
  }
  if (!empty($entry['disclosure'])) {
    update_post_meta($product_id, '_nb_disclosure', $entry['disclosure']);
  }
  if (!empty($entry['hsn'])) {
    update_post_meta($product_id, '_nb_hsn', $entry['hsn']);
  }
  if (isset($entry['gst_rate']) && '' !== $entry['gst_rate']) {
    update_post_meta($product_id, '_nb_gst_rate', (string) $entry['gst_rate']);
  }
  if (!empty($entry['enquiry_url'])) {
    update_post_meta($product_id, '_nb_enquiry_url', $entry['enquiry_url']);
  }
  if (!empty($entry['enquiry_label'])) {
    update_post_meta($product_id, '_nb_enquiry_label', $entry['enquiry_label']);
  }
}

/**
 * Build and persist one product. Separated out so the WC_Data_Exception that
 * the CRUD setters can raise is caught in one place.
 */
function nirog_bhumi_build_seed_product($entry, $category_map) {
  $product = new WC_Product_Simple();
  $product->set_name($entry['name']);
  $product->set_slug($entry['slug']);
  $product->set_sku($entry['sku']);
  $product->set_status('publish');
  $product->set_short_description($entry['short']);
  $product->set_description(nirog_bhumi_catalogue_description($entry));
  $product->set_virtual(!empty($entry['virtual']));
  $product->set_sold_individually(!empty($entry['sold_individually']));
  $product->set_featured(!empty($entry['featured']));
  $product->set_catalog_visibility(!empty($entry['catalogue_visibility']) ? $entry['catalogue_visibility'] : 'visible');
  $product->set_reviews_allowed(false);

  if ('sale' === $entry['status'] && '' !== $entry['price']) {
    $product->set_regular_price($entry['price']);
  }

  if (!empty($entry['category']) && isset($category_map[$entry['category']])) {
    $product->set_category_ids([$category_map[$entry['category']]]);
  }

  if (!empty($entry['image'])) {
    $attachment_id = nirog_bhumi_import_theme_image($entry['image']);
    if ($attachment_id) {
      $product->set_image_id($attachment_id);
    }
  }

  return (int) $product->save();
}

/**
 * Seed the whole catalogue. Returns a per-outcome tally.
 */
function nirog_bhumi_seed_catalogue() {
  $category_map = nirog_bhumi_seed_product_categories();
  $result = ['created' => 0, 'skipped' => 0, 'failed' => 0];

  foreach (nirog_bhumi_store_catalogue() as $entry) {
    $outcome = nirog_bhumi_seed_product($entry, $category_map);
    $result[$outcome]++;
  }

  // Cross-sells ("buy it with") are set in a second pass, once every
  // product in the catalogue is guaranteed to exist, since an entry can
  // list a SKU that is created later in the same run.
  nirog_bhumi_seed_cross_sells();

  return $result;
}

/**
 * Wire up each catalogue entry's 'cross_sell' SKUs as WooCommerce
 * cross-sells, so the "Buy it with" section on the product page has
 * something to show without needing it set by hand in wp-admin first.
 * Re-running this never removes a cross-sell added manually afterwards -
 * it only adds the catalogue's own list if it isn't already there.
 */
function nirog_bhumi_seed_cross_sells() {
  if (!function_exists('wc_get_product_id_by_sku')) {
    return;
  }
  foreach (nirog_bhumi_store_catalogue() as $entry) {
    if (empty($entry['cross_sell'])) {
      continue;
    }
    $product_id = wc_get_product_id_by_sku($entry['sku']);
    if (!$product_id) {
      continue;
    }
    $product = wc_get_product($product_id);
    if (!$product) {
      continue;
    }
    $cross_sell_ids = $product->get_cross_sell_ids();
    $changed = false;
    foreach ($entry['cross_sell'] as $cross_sku) {
      $cross_id = wc_get_product_id_by_sku($cross_sku);
      if ($cross_id && !in_array($cross_id, $cross_sell_ids, true)) {
        $cross_sell_ids[] = $cross_id;
        $changed = true;
      }
    }
    if ($changed) {
      $product->set_cross_sell_ids($cross_sell_ids);
      $product->save();
    }
  }
}

/**
 * Refresh an already-created product's editorial content (name, slug,
 * descriptions, price, category and every _nb_* field) from the catalogue.
 * "Create missing products" deliberately skips a SKU that already exists so
 * a manual dashboard edit is never silently overwritten; this is the
 * opposite tool, for the moment a SKU's copy in store-catalogue.php changes
 * on purpose (a rewrite like this one) and the live product needs to catch
 * up. It never touches stock, images already set on the product, reviews or
 * order history - only content this file owns.
 */
function nirog_bhumi_update_seeded_products() {
  $result = ['updated' => 0, 'not_found' => 0];
  if (!function_exists('wc_get_product_id_by_sku')) {
    return $result;
  }
  $category_map = nirog_bhumi_seed_product_categories();
  foreach (nirog_bhumi_store_catalogue() as $entry) {
    $product_id = wc_get_product_id_by_sku($entry['sku']);
    if (!$product_id) {
      $result['not_found']++;
      continue;
    }
    $product = wc_get_product($product_id);
    if (!$product) {
      $result['not_found']++;
      continue;
    }

    $product->set_name($entry['name']);
    $product->set_slug($entry['slug']);
    $product->set_short_description($entry['short']);
    $product->set_description(nirog_bhumi_catalogue_description($entry));
    $product->set_featured(!empty($entry['featured']));

    if ('sale' === $entry['status'] && '' !== $entry['price']) {
      $product->set_regular_price($entry['price']);
    }

    if (!empty($entry['category']) && isset($category_map[$entry['category']])) {
      $product->set_category_ids([$category_map[$entry['category']]]);
    }

    // Only fill in an image if the product does not already have one - a
    // photo uploaded by hand in wp-admin is never replaced by this tool.
    if (!$product->get_image_id() && !empty($entry['image'])) {
      $attachment_id = nirog_bhumi_import_theme_image($entry['image']);
      if ($attachment_id) {
        $product->set_image_id($attachment_id);
      }
    }

    $product->save();
    nirog_bhumi_apply_catalogue_meta($product_id, $entry);
    $result['updated']++;
  }

  nirog_bhumi_seed_cross_sells();

  return $result;
}

/**
 * The store page normally sits under WooCommerce. Before WooCommerce is
 * activated that menu does not exist, so it falls back to Settings and the
 * pre-launch checklist stays reachable.
 */
function nirog_bhumi_store_admin_page() {
  $parent = nirog_bhumi_woocommerce_active() ? 'woocommerce' : 'options-general.php';
  $capability = nirog_bhumi_woocommerce_active() ? 'manage_woocommerce' : 'manage_options';
  add_submenu_page(
    $parent,
    __('Nirog Bhumi Store', 'nirog-bhumi'),
    __('Nirog Bhumi Store', 'nirog-bhumi'),
    $capability,
    'nirog-bhumi-store',
    'nirog_bhumi_render_store_admin_page'
  );
}
add_action('admin_menu', 'nirog_bhumi_store_admin_page', 20);

function nirog_bhumi_handle_seed_catalogue() {
  if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
    wp_die(esc_html__('You are not allowed to seed the catalogue.', 'nirog-bhumi'));
  }
  check_admin_referer('nirog_seed_catalogue');

  if (!nirog_bhumi_woocommerce_active()) {
    wp_safe_redirect(add_query_arg('nb_seed', 'no-woocommerce', nirog_bhumi_store_admin_url()));
    exit;
  }

  $result = nirog_bhumi_seed_catalogue();
  wp_safe_redirect(add_query_arg([
    'nb_seed' => 'done',
    'nb_created' => $result['created'],
    'nb_skipped' => $result['skipped'],
    'nb_failed' => $result['failed'],
  ], nirog_bhumi_store_admin_url()));
  exit;
}
add_action('admin_post_nirog_seed_catalogue', 'nirog_bhumi_handle_seed_catalogue');

function nirog_bhumi_handle_cleanup_retired_products() {
  if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
    wp_die(esc_html__('You are not allowed to clean up the catalogue.', 'nirog-bhumi'));
  }
  check_admin_referer('nirog_cleanup_retired_products');

  if (!nirog_bhumi_woocommerce_active()) {
    wp_safe_redirect(add_query_arg('nb_seed', 'no-woocommerce', nirog_bhumi_store_admin_url()));
    exit;
  }

  $result = nirog_bhumi_cleanup_retired_products();
  wp_safe_redirect(add_query_arg([
    'nb_cleanup' => 'done',
    'nb_trashed' => $result['trashed'],
  ], nirog_bhumi_store_admin_url()));
  exit;
}
add_action('admin_post_nirog_cleanup_retired_products', 'nirog_bhumi_handle_cleanup_retired_products');

function nirog_bhumi_handle_update_seeded_products() {
  if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
    wp_die(esc_html__('You are not allowed to update the catalogue.', 'nirog-bhumi'));
  }
  check_admin_referer('nirog_update_seeded_products');

  if (!nirog_bhumi_woocommerce_active()) {
    wp_safe_redirect(add_query_arg('nb_seed', 'no-woocommerce', nirog_bhumi_store_admin_url()));
    exit;
  }

  $result = nirog_bhumi_update_seeded_products();
  wp_safe_redirect(add_query_arg([
    'nb_update' => 'done',
    'nb_updated' => $result['updated'],
    'nb_update_missing' => $result['not_found'],
  ], nirog_bhumi_store_admin_url()));
  exit;
}
add_action('admin_post_nirog_update_seeded_products', 'nirog_bhumi_handle_update_seeded_products');

function nirog_bhumi_store_admin_url() {
  return nirog_bhumi_woocommerce_active()
    ? admin_url('admin.php?page=nirog-bhumi-store')
    : admin_url('options-general.php?page=nirog-bhumi-store');
}

function nirog_bhumi_render_store_admin_page() {
  $settings = nirog_bhumi_get_store_settings();
  $invoice = function_exists('nirog_bhumi_get_settings') ? nirog_bhumi_get_settings() : [];
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Nirog Bhumi Store', 'nirog-bhumi'); ?></h1>

    <?php if (!nirog_bhumi_woocommerce_active()) : ?>
      <div class="notice notice-error"><p><?php esc_html_e('WooCommerce is not active. Install and activate WooCommerce before seeding the catalogue.', 'nirog-bhumi'); ?></p></div>
    <?php endif; ?>

    <?php if (isset($_GET['nb_seed']) && 'no-woocommerce' === $_GET['nb_seed']) : ?>
      <div class="notice notice-error"><p><?php esc_html_e('Nothing was created: WooCommerce is not active.', 'nirog-bhumi'); ?></p></div>
    <?php endif; ?>

    <?php if (isset($_GET['nb_seed']) && 'done' === $_GET['nb_seed']) : ?>
      <div class="notice notice-success"><p>
        <?php printf(
          esc_html__('Catalogue seeded. Created: %1$d. Already present: %2$d. Failed: %3$d.', 'nirog-bhumi'),
          (int) ($_GET['nb_created'] ?? 0),
          (int) ($_GET['nb_skipped'] ?? 0),
          (int) ($_GET['nb_failed'] ?? 0)
        ); ?>
      </p></div>
    <?php endif; ?>

    <?php if (isset($_GET['nb_cleanup']) && 'done' === $_GET['nb_cleanup']) : ?>
      <div class="notice notice-success"><p>
        <?php printf(
          /* translators: %d: number of retired products trashed */
          esc_html__('Retired products cleaned up. Moved to trash: %d.', 'nirog-bhumi'),
          (int) ($_GET['nb_trashed'] ?? 0)
        ); ?>
      </p></div>
    <?php endif; ?>

    <?php if (isset($_GET['nb_update']) && 'done' === $_GET['nb_update']) : ?>
      <div class="notice notice-success"><p>
        <?php printf(
          /* translators: 1: number of products updated, 2: number not found */
          esc_html__('Catalogue copy refreshed. Updated: %1$d. Not created yet: %2$d.', 'nirog-bhumi'),
          (int) ($_GET['nb_updated'] ?? 0),
          (int) ($_GET['nb_update_missing'] ?? 0)
        ); ?>
      </p></div>
    <?php endif; ?>

    <form method="post" action="options.php">
      <?php settings_fields('nirog_bhumi_store_settings_group'); ?>
      <h2><?php esc_html_e('Store status', 'nirog-bhumi'); ?></h2>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row"><?php esc_html_e('Store status', 'nirog-bhumi'); ?></th>
          <td>
            <fieldset>
              <label><input type="radio" name="nirog_bhumi_store_settings[store_status]" value="coming_soon" <?php checked($settings['store_status'], 'coming_soon'); ?>> <strong><?php esc_html_e('Coming soon', 'nirog-bhumi'); ?></strong> &mdash; <?php esc_html_e('visitors see only the Coming soon panel. Shop managers still see the full catalogue so it can be checked before launch.', 'nirog-bhumi'); ?></label><br>
              <label><input type="radio" name="nirog_bhumi_store_settings[store_status]" value="preview" <?php checked($settings['store_status'], 'preview'); ?>> <strong><?php esc_html_e('Preview', 'nirog-bhumi'); ?></strong> &mdash; <?php esc_html_e('everyone can browse the catalogue, nothing can be bought, waitlist buttons are shown.', 'nirog-bhumi'); ?></label><br>
              <label><input type="radio" name="nirog_bhumi_store_settings[store_status]" value="open" <?php checked($settings['store_status'], 'open'); ?>> <strong><?php esc_html_e('Open', 'nirog-bhumi'); ?></strong> &mdash; <?php esc_html_e('products with a price can be added to the cart and bought.', 'nirog-bhumi'); ?></label>
            </fieldset>
            <p class="description"><?php esc_html_e('Do not switch to Open until payments, shipping, refunds and product labelling have been checked.', 'nirog-bhumi'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="nb-store-note"><?php esc_html_e('Dispatch note', 'nirog-bhumi'); ?></label></th>
          <td><input id="nb-store-note" class="large-text" type="text" name="nirog_bhumi_store_settings[store_note]" value="<?php echo esc_attr($settings['store_note']); ?>">
          <p class="description"><?php esc_html_e('Shown on the store and product pages. Keep it accurate to your actual dispatch times.', 'nirog-bhumi'); ?></p></td>
        </tr>
        <tr>
          <th scope="row"><label for="nb-shipping-fee"><?php esc_html_e('Shipping fee (Rs.)', 'nirog-bhumi'); ?></label></th>
          <td><input id="nb-shipping-fee" type="number" min="0" step="1" class="small-text" name="nirog_bhumi_store_settings[shipping_fee]" value="<?php echo esc_attr($settings['shipping_fee']); ?>">
          <p class="description"><?php esc_html_e('Added to every order automatically as a cart fee (no WooCommerce shipping zone needs to be configured). Waived once the order reaches the free-shipping threshold below. Set to 0 to never charge shipping.', 'nirog-bhumi'); ?></p></td>
        </tr>
        <tr>
          <th scope="row"><label for="nb-free-ship"><?php esc_html_e('Free shipping above (Rs.)', 'nirog-bhumi'); ?></label></th>
          <td><input id="nb-free-ship" type="number" min="0" step="1" class="small-text" name="nirog_bhumi_store_settings[free_shipping_threshold]" value="<?php echo esc_attr($settings['free_shipping_threshold']); ?>">
          <p class="description"><?php esc_html_e('Waives the shipping fee above once the cart subtotal reaches this amount. Set to 0 to always charge the shipping fee.', 'nirog-bhumi'); ?></p></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Waitlist', 'nirog-bhumi'); ?></th>
          <td><label><input type="checkbox" name="nirog_bhumi_store_settings[waitlist_enabled]" value="yes" <?php checked($settings['waitlist_enabled'], 'yes'); ?>> <?php esc_html_e('Collect Notify me sign-ups for products that are not on sale yet.', 'nirog-bhumi'); ?></label>
          <p class="description"><?php esc_html_e('Sign-ups are stored under Form Entries and can be exported or erased there.', 'nirog-bhumi'); ?></p></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Header cart link', 'nirog-bhumi'); ?></th>
          <td><label><input type="checkbox" name="nirog_bhumi_store_settings[show_cart_link]" value="yes" <?php checked($settings['show_cart_link'], 'yes'); ?>> <?php esc_html_e('Show the cart and item count in the site header when the store is open.', 'nirog-bhumi'); ?></label></td>
        </tr>
      </table>

      <h2><?php esc_html_e('Tax defaults for goods', 'nirog-bhumi'); ?></h2>
      <p class="description" style="max-width:46em">
        <?php esc_html_e('The existing invoice settings cover services and use SAC 999319. Physical goods are invoiced with an HSN code and the GST rate that applies to that goods category, which is not the same for a wooden tumbler, a steel pot and a packaged food. Leave these blank until your accountant confirms them; blank means nothing is printed rather than something wrong.', 'nirog-bhumi'); ?>
      </p>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row"><label for="nb-default-hsn"><?php esc_html_e('Default HSN code', 'nirog-bhumi'); ?></label></th>
          <td><input id="nb-default-hsn" type="text" class="regular-text" name="nirog_bhumi_store_settings[default_hsn]" value="<?php echo esc_attr($settings['default_hsn']); ?>">
          <p class="description"><?php esc_html_e('Used when a product has no HSN of its own. Each product can override it on the product edit screen.', 'nirog-bhumi'); ?></p></td>
        </tr>
        <tr>
          <th scope="row"><label for="nb-goods-gst"><?php esc_html_e('Default GST rate for goods (%)', 'nirog-bhumi'); ?></label></th>
          <td><input id="nb-goods-gst" type="number" min="0" max="100" step="0.01" class="small-text" name="nirog_bhumi_store_settings[goods_gst_rate]" value="<?php echo esc_attr($settings['goods_gst_rate']); ?>">
          <p class="description"><?php printf(
            esc_html__('Leave blank to fall back to the services rate currently set in Settings > Nirog Bhumi Setup (%s%%).', 'nirog-bhumi'),
            esc_html($invoice['invoice_gst_rate'] ?? '18')
          ); ?></p></td>
        </tr>
      </table>
      <?php submit_button(__('Save store settings', 'nirog-bhumi')); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Launch catalogue', 'nirog-bhumi'); ?></h2>
    <p><?php esc_html_e('Create the shelves and products that the store design was built around. Products are matched on SKU, so running this again only adds what is missing. Nothing you have already edited is overwritten.', 'nirog-bhumi'); ?></p>

    <table class="widefat striped" style="max-width:1100px">
      <thead><tr>
        <th><?php esc_html_e('SKU', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('Product', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('Shelf', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('HSN', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('GST', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('Price (incl. GST)', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('In store', 'nirog-bhumi'); ?></th>
      </tr></thead>
      <tbody>
      <?php
      $categories = nirog_bhumi_store_categories();
      foreach (nirog_bhumi_store_catalogue() as $entry) :
        $existing = function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku($entry['sku']) : 0;
        ?>
        <tr>
          <td><code><?php echo esc_html($entry['sku']); ?></code></td>
          <td><?php echo esc_html($entry['name']); ?></td>
          <td><?php echo esc_html($categories[$entry['category']]['name'] ?? $entry['category']); ?></td>
          <td><?php echo esc_html($entry['hsn'] ?? ''); ?></td>
          <td><?php echo isset($entry['gst_rate']) && '' !== $entry['gst_rate'] ? esc_html($entry['gst_rate'] . '%') : '&mdash;'; ?></td>
          <td><?php echo '' !== $entry['price'] ? esc_html('Rs. ' . $entry['price']) : '&mdash;'; ?></td>
          <td><?php if ($existing) : ?>
            <a href="<?php echo esc_url(get_edit_post_link($existing)); ?>"><?php esc_html_e('Edit', 'nirog-bhumi'); ?></a>
          <?php else : ?>
            <span style="color:#a00"><?php esc_html_e('Not created', 'nirog-bhumi'); ?></span>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="nirog_seed_catalogue">
        <?php wp_nonce_field('nirog_seed_catalogue'); ?>
        <?php submit_button(__('Create missing products', 'nirog-bhumi'), 'primary', 'submit', false); ?>
      </form>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="nirog_update_seeded_products">
        <?php wp_nonce_field('nirog_update_seeded_products'); ?>
        <?php submit_button(__('Refresh copy on existing products', 'nirog-bhumi'), 'secondary', 'submit', false); ?>
      </form>
    </div>
    <p class="description" style="max-width:46em;margin-top:6px"><?php esc_html_e('"Create missing products" only adds what is not there yet - it never touches an existing product. "Refresh copy on existing products" overwrites name, descriptions, price, category and the fields on this tab (how to use, benefits, precautions, HSN, GST) for every SKU above that already exists, from what is defined in the catalogue right now. It never changes stock, an already-set product photo, reviews or past orders. Use it after a copy update like this one so an already-created product actually shows the new text.', 'nirog-bhumi'); ?></p>

    <h2><?php esc_html_e('Retired products', 'nirog-bhumi'); ?></h2>
    <p style="max-width:46em"><?php esc_html_e('Combos, programmes and consultations sold as products have been removed from the catalogue above. If "Create missing products" was ever run before that change, those old products can still exist live on the site. This moves any of them to trash (recoverable from Products > Trash) - it never affects a product that is still part of the current catalogue.', 'nirog-bhumi'); ?></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px">
      <input type="hidden" name="action" value="nirog_cleanup_retired_products">
      <?php wp_nonce_field('nirog_cleanup_retired_products'); ?>
      <?php submit_button(__('Remove retired products', 'nirog-bhumi'), 'secondary', 'submit', false); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Before you switch the store to Open', 'nirog-bhumi'); ?></h2>
    <ol style="max-width:46em">
      <li><?php esc_html_e('Confirm HSN codes and GST rates per product with your accountant, and enter them on each product.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Check product names, packaging and page copy against FSSAI rules for packaged food and AYUSH rules for herbal products, and against the Drugs and Magic Remedies (Objectionable Advertisements) Act, which restricts claims to cure or treat diabetes. Have a regulatory advisor review the wording.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Set up shipping zones, rates and a courier in WooCommerce > Settings > Shipping.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Complete the PhonePe live keys and run one real low-value order end to end.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Publish shipping, returns, refunds and cancellation terms, which Indian payment gateways require before going live.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Set stock quantities on each product so the store cannot oversell.', 'nirog-bhumi'); ?></li>
    </ol>
  </div>
  <?php
}
