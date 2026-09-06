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
 * Build the long description shown on the product page.
 */
function nirog_bhumi_catalogue_description($entry) {
  $html = '<p>' . esc_html($entry['description']) . '</p>';
  if (!empty($entry['includes'])) {
    $html .= '<ul>';
    foreach ($entry['includes'] as $item) {
      $html .= '<li>' . esc_html($item) . '</li>';
    }
    $html .= '</ul>';
  }
  return $html;
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

  update_post_meta($product_id, '_nb_launch_status', $entry['status']);
  update_post_meta($product_id, '_nb_eyebrow', $entry['eyebrow']);
  if (!empty($entry['ritual'])) {
    update_post_meta($product_id, '_nb_ritual', $entry['ritual']);
  }
  if (!empty($entry['caution'])) {
    update_post_meta($product_id, '_nb_caution', $entry['caution']);
  }
  if (!empty($entry['enquiry_url'])) {
    update_post_meta($product_id, '_nb_enquiry_url', $entry['enquiry_url']);
  }
  if (!empty($entry['enquiry_label'])) {
    update_post_meta($product_id, '_nb_enquiry_label', $entry['enquiry_label']);
  }

  return 'created';
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
          <th scope="row"><label for="nb-free-ship"><?php esc_html_e('Free shipping above (Rs.)', 'nirog-bhumi'); ?></label></th>
          <td><input id="nb-free-ship" type="number" min="0" step="1" class="small-text" name="nirog_bhumi_store_settings[free_shipping_threshold]" value="<?php echo esc_attr($settings['free_shipping_threshold']); ?>">
          <p class="description"><?php esc_html_e('Display only. Configure the actual rule in WooCommerce > Settings > Shipping. Set to 0 to hide the message.', 'nirog-bhumi'); ?></p></td>
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

    <table class="widefat striped" style="max-width:1000px">
      <thead><tr>
        <th><?php esc_html_e('SKU', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('Product', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('Shelf', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('Launch state', 'nirog-bhumi'); ?></th>
        <th><?php esc_html_e('Price', 'nirog-bhumi'); ?></th>
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
          <td><?php echo esc_html($entry['status']); ?></td>
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

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px">
      <input type="hidden" name="action" value="nirog_seed_catalogue">
      <?php wp_nonce_field('nirog_seed_catalogue'); ?>
      <?php submit_button(__('Create missing products', 'nirog-bhumi'), 'primary', 'submit', false); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Before you switch the store to Open', 'nirog-bhumi'); ?></h2>
    <ol style="max-width:46em">
      <li><?php esc_html_e('Confirm HSN codes and GST rates per product with your accountant, and enter them on each product.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Check product names, packaging and page copy against FSSAI rules for packaged food and AYUSH rules for herbal products, and against the Drugs and Magic Remedies (Objectionable Advertisements) Act, which restricts claims to cure or treat diabetes. Have a regulatory advisor review the wording.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Set up shipping zones, rates and a courier in WooCommerce > Settings > Shipping.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Complete the Razorpay live keys and run one real low-value order end to end.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Publish shipping, returns, refunds and cancellation terms, which Indian payment gateways require before going live.', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Set stock quantities on each product so the store cannot oversell.', 'nirog-bhumi'); ?></li>
    </ol>
  </div>
  <?php
}
