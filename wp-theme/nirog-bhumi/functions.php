<?php
function nirog_bhumi_setup() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('woocommerce');
  add_theme_support('wc-product-gallery-zoom');
  add_theme_support('wc-product-gallery-lightbox');
  register_nav_menus(['primary' => __('Primary Menu', 'nirog-bhumi')]);
}
add_action('after_setup_theme', 'nirog_bhumi_setup');

function nirog_bhumi_assets() {
  wp_enqueue_style('nirog-bhumi-style', get_template_directory_uri() . '/assets/css/styles.css', [], '0.1.0');
  wp_enqueue_script('nirog-bhumi-main', get_template_directory_uri() . '/assets/js/main.js', [], '0.1.0', true);
}
add_action('wp_enqueue_scripts', 'nirog_bhumi_assets');

function nirog_bhumi_settings_defaults() {
  return [
    'consultation_product_id' => 0,
    'consultation_calendar_url' => home_url('/consultation-calendar/'),
    'consultation_clear_cart' => 'yes',
  ];
}

function nirog_bhumi_get_settings() {
  $saved = get_option('nirog_bhumi_settings', []);
  return wp_parse_args(is_array($saved) ? $saved : [], nirog_bhumi_settings_defaults());
}

function nirog_bhumi_sanitize_settings($input) {
  return [
    'consultation_product_id' => isset($input['consultation_product_id']) ? absint($input['consultation_product_id']) : 0,
    'consultation_calendar_url' => !empty($input['consultation_calendar_url']) ? esc_url_raw($input['consultation_calendar_url']) : home_url('/consultation-calendar/'),
    'consultation_clear_cart' => !empty($input['consultation_clear_cart']) ? 'yes' : 'no',
  ];
}

function nirog_bhumi_register_settings() {
  register_setting('nirog_bhumi_settings_group', 'nirog_bhumi_settings', 'nirog_bhumi_sanitize_settings');
}
add_action('admin_init', 'nirog_bhumi_register_settings');

function nirog_bhumi_admin_settings_page() {
  add_options_page(
    __('Nirog Bhumi Setup', 'nirog-bhumi'),
    __('Nirog Bhumi Setup', 'nirog-bhumi'),
    'manage_options',
    'nirog-bhumi-setup',
    'nirog_bhumi_render_settings_page'
  );
}
add_action('admin_menu', 'nirog_bhumi_admin_settings_page');

function nirog_bhumi_render_settings_page() {
  $settings = nirog_bhumi_get_settings();
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Nirog Bhumi Setup', 'nirog-bhumi'); ?></h1>
    <p><?php esc_html_e('Use these settings to connect the consultation form to WooCommerce payment and your booking calendar.', 'nirog-bhumi'); ?></p>
    <form method="post" action="options.php">
      <?php settings_fields('nirog_bhumi_settings_group'); ?>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row"><label for="nirog-consultation-product-id"><?php esc_html_e('Consultation product ID', 'nirog-bhumi'); ?></label></th>
          <td>
            <input id="nirog-consultation-product-id" name="nirog_bhumi_settings[consultation_product_id]" type="number" min="0" class="regular-text" value="<?php echo esc_attr($settings['consultation_product_id']); ?>">
            <p class="description"><?php esc_html_e('Enter the WooCommerce product ID for the Rs. 500 consultation product.', 'nirog-bhumi'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="nirog-calendar-url"><?php esc_html_e('Calendar page URL', 'nirog-bhumi'); ?></label></th>
          <td>
            <input id="nirog-calendar-url" name="nirog_bhumi_settings[consultation_calendar_url]" type="url" class="regular-text code" value="<?php echo esc_attr($settings['consultation_calendar_url']); ?>">
            <p class="description"><?php esc_html_e('Keep the protected scheduling page at /consultation-calendar/. Add the booking calendar inside that page.', 'nirog-bhumi'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Empty cart before consultation checkout', 'nirog-bhumi'); ?></th>
          <td>
            <label><input name="nirog_bhumi_settings[consultation_clear_cart]" type="checkbox" value="yes" <?php checked($settings['consultation_clear_cart'], 'yes'); ?>> <?php esc_html_e('Recommended for the consultation-only checkout flow.', 'nirog-bhumi'); ?></label>
          </td>
        </tr>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

function nirog_bhumi_consultation_product_id() {
  $settings = nirog_bhumi_get_settings();
  return (int) $settings['consultation_product_id'];
}

function nirog_bhumi_cart_is_consultation_only() {
  if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
    return false;
  }
  $product_id = nirog_bhumi_consultation_product_id();
  if (!$product_id) {
    return false;
  }
  foreach (WC()->cart->get_cart() as $cart_item) {
    if (empty($cart_item['product_id']) || (int) $cart_item['product_id'] !== $product_id) {
      return false;
    }
  }
  return true;
}

function nirog_bhumi_consultation_checkout_fields($fields) {
  if (!nirog_bhumi_cart_is_consultation_only()) {
    return $fields;
  }
  $allowed = ['billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone'];
  foreach (array_keys($fields['billing'] ?? []) as $key) {
    if (!in_array($key, $allowed, true)) {
      unset($fields['billing'][$key]);
    }
  }
  $fields['shipping'] = [];
  $fields['order'] = [];
  if (isset($fields['billing']['billing_first_name'])) {
    $fields['billing']['billing_first_name']['label'] = __('First name', 'nirog-bhumi');
    $fields['billing']['billing_first_name']['priority'] = 10;
  }
  if (isset($fields['billing']['billing_last_name'])) {
    $fields['billing']['billing_last_name']['label'] = __('Last name', 'nirog-bhumi');
    $fields['billing']['billing_last_name']['priority'] = 20;
  }
  if (isset($fields['billing']['billing_email'])) {
    $fields['billing']['billing_email']['label'] = __('Email', 'nirog-bhumi');
    $fields['billing']['billing_email']['priority'] = 30;
  }
  if (isset($fields['billing']['billing_phone'])) {
    $fields['billing']['billing_phone']['label'] = __('Phone / WhatsApp', 'nirog-bhumi');
    $fields['billing']['billing_phone']['priority'] = 40;
  }
  return $fields;
}
add_filter('woocommerce_checkout_fields', 'nirog_bhumi_consultation_checkout_fields', 30);

function nirog_bhumi_consultation_is_virtual($needs_shipping, $product) {
  return $product && (int) $product->get_id() === nirog_bhumi_consultation_product_id() ? false : $needs_shipping;
}
add_filter('woocommerce_product_needs_shipping', 'nirog_bhumi_consultation_is_virtual', 10, 2);

function nirog_bhumi_consultation_calendar_url() {
  return home_url('/consultation-calendar/');
}

function nirog_bhumi_consultation_checkout_url() {
  if (!function_exists('wc_get_checkout_url') || !nirog_bhumi_consultation_product_id()) {
    return '';
  }
  return add_query_arg('nb_start_consultation_checkout', '1', home_url('/consultation-payment/'));
}

function nirog_bhumi_ensure_checkout_page() {
  if (!class_exists('WooCommerce')) {
    return 0;
  }
  $checkout_id = (int) get_option('woocommerce_checkout_page_id');
  if ($checkout_id > 0 && get_post_status($checkout_id) === 'publish') {
    return $checkout_id;
  }
  $checkout_page = get_page_by_path('checkout');
  if ($checkout_page && $checkout_page->post_status === 'publish') {
    update_option('woocommerce_checkout_page_id', $checkout_page->ID);
    return (int) $checkout_page->ID;
  }
  $checkout_id = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => __('Checkout', 'nirog-bhumi'),
    'post_name' => 'checkout',
    'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
  ]);
  if (!is_wp_error($checkout_id) && $checkout_id) {
    update_option('woocommerce_checkout_page_id', $checkout_id);
    return (int) $checkout_id;
  }
  return 0;
}
add_action('init', 'nirog_bhumi_ensure_checkout_page', 30);

function nirog_bhumi_register_consultations() {
  register_post_type('nb_consultation', [
    'labels' => [
      'name' => __('Consultation Entries', 'nirog-bhumi'),
      'singular_name' => __('Consultation Entry', 'nirog-bhumi'),
      'menu_name' => __('Consultations', 'nirog-bhumi'),
      'view_item' => __('View Consultation Entry', 'nirog-bhumi'),
    ],
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_icon' => 'dashicons-clipboard',
    'capability_type' => 'post',
    'supports' => ['title'],
  ]);
}
add_action('init', 'nirog_bhumi_register_consultations');

function nirog_bhumi_clean_field($key) {
  return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
}

function nirog_bhumi_clean_textarea($key) {
  return isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
}

function nirog_bhumi_consultation_edit_entry() {
  $entry_id = isset($_REQUEST['consultation_entry_id']) ? absint(wp_unslash($_REQUEST['consultation_entry_id'])) : 0;
  if (!$entry_id && isset($_GET['entry'])) {
    $entry_id = absint(wp_unslash($_GET['entry']));
  }
  if (!$entry_id || get_post_type($entry_id) !== 'nb_consultation' || empty($_COOKIE['nb_consultation_edit_token'])) {
    return 0;
  }
  $token = sanitize_text_field(wp_unslash($_COOKIE['nb_consultation_edit_token']));
  $token_hash = (string) get_post_meta($entry_id, '_nb_edit_token_hash', true);
  return $token_hash && wp_check_password($token, $token_hash) ? $entry_id : 0;
}

function nirog_bhumi_consultation_cookie_entry() {
  if (empty($_COOKIE['nb_consultation_entry']) || empty($_COOKIE['nb_consultation_edit_token'])) {
    return 0;
  }
  $entry_id = absint(wp_unslash($_COOKIE['nb_consultation_entry']));
  if (!$entry_id || get_post_type($entry_id) !== 'nb_consultation') {
    return 0;
  }
  $token = sanitize_text_field(wp_unslash($_COOKIE['nb_consultation_edit_token']));
  $token_hash = (string) get_post_meta($entry_id, '_nb_edit_token_hash', true);
  return $token_hash && wp_check_password($token, $token_hash) ? $entry_id : 0;
}

function nirog_bhumi_consultation_edit_url() {
  $entry_id = !empty($_COOKIE['nb_consultation_entry']) ? absint(wp_unslash($_COOKIE['nb_consultation_entry'])) : 0;
  if (!$entry_id || empty($_COOKIE['nb_consultation_edit_token'])) {
    return home_url('/consultation/#consultation-form');
  }
  return add_query_arg(['edit_consultation' => '1', 'entry' => $entry_id], home_url('/consultation/')) . '#consultation-form';
}

function nirog_bhumi_consultation_edit_data() {
  $entry_id = nirog_bhumi_consultation_edit_entry();
  if (!$entry_id) {
    return [];
  }
  $keys = ['name', 'email', 'phone', 'age', 'concern', 'fasting', 'postmeal', 'hba1c', 'bp', 'body', 'medicines', 'conditions', 'food', 'lifestyle', 'goal', 'consultation_disclaimer', 'data_processing_consent', 'followup_consent'];
  $data = ['consultation_entry_id' => $entry_id];
  foreach ($keys as $key) {
    $data[$key] = (string) get_post_meta($entry_id, $key, true);
  }
  return $data;
}

function nirog_bhumi_ajax_consultation_edit_data() {
  nocache_headers();
  $data = nirog_bhumi_consultation_edit_data();
  if (!$data) {
    wp_send_json_error(['message' => __('This edit link has expired. Please complete the form again.', 'nirog-bhumi')], 403);
  }
  wp_send_json_success($data);
}
add_action('wp_ajax_nopriv_nirog_consultation_edit_data', 'nirog_bhumi_ajax_consultation_edit_data');
add_action('wp_ajax_nirog_consultation_edit_data', 'nirog_bhumi_ajax_consultation_edit_data');

function nirog_bhumi_handle_consultation_form() {
  if (!isset($_POST['nirog_consultation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nirog_consultation_nonce'])), 'nirog_consultation_submit')) {
    wp_die(esc_html__('Security check failed. Please go back and submit the consultation form again.', 'nirog-bhumi'));
  }

  $name = nirog_bhumi_clean_field('name');
  $email = sanitize_email(nirog_bhumi_clean_field('email'));
  $phone = nirog_bhumi_clean_field('phone');

  if (!$name || !$email || !$phone) {
    wp_safe_redirect(add_query_arg('consultation_error', 'missing', wp_get_referer() ?: home_url('/consultation/')));
    exit;
  }

  $post_id = nirog_bhumi_consultation_edit_entry();
  $is_update = (bool) $post_id;
  $post_data = [
    'post_type' => 'nb_consultation',
    'post_status' => 'private',
    'post_title' => sprintf('%s - %s', $name, current_time('d M Y H:i')),
  ];
  if ($is_update) {
    $post_data['ID'] = $post_id;
    $post_id = wp_update_post($post_data, true);
  } else {
    $post_id = wp_insert_post($post_data, true);
  }

  if (is_wp_error($post_id) || !$post_id) {
    wp_die(esc_html__('Could not save the consultation request. Please try again.', 'nirog-bhumi'));
  }

  $fields = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'age' => nirog_bhumi_clean_field('age'),
    'concern' => nirog_bhumi_clean_field('concern'),
    'fasting' => nirog_bhumi_clean_field('fasting'),
    'postmeal' => nirog_bhumi_clean_field('postmeal'),
    'hba1c' => nirog_bhumi_clean_field('hba1c'),
    'bp' => nirog_bhumi_clean_field('bp'),
    'body' => nirog_bhumi_clean_field('body'),
    'medicines' => nirog_bhumi_clean_textarea('medicines'),
    'conditions' => nirog_bhumi_clean_textarea('conditions'),
    'food' => nirog_bhumi_clean_textarea('food'),
    'lifestyle' => nirog_bhumi_clean_textarea('lifestyle'),
    'goal' => nirog_bhumi_clean_textarea('goal'),
    'consultation_disclaimer' => isset($_POST['consultation_disclaimer']) ? 'yes' : 'no',
    'data_processing_consent' => isset($_POST['data_processing_consent']) ? 'yes' : 'no',
    'followup_consent' => isset($_POST['followup_consent']) ? 'yes' : 'no',
  ];

  foreach ($fields as $key => $value) {
    update_post_meta($post_id, $key, $value);
  }

  if (!empty($_FILES['reports']['name'][0])) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachment_ids = (array) get_post_meta($post_id, 'report_attachment_ids', true);
    foreach ($_FILES['reports']['name'] as $index => $filename) {
      if (!$filename) {
        continue;
      }
      $file = [
        'name' => $_FILES['reports']['name'][$index],
        'type' => $_FILES['reports']['type'][$index],
        'tmp_name' => $_FILES['reports']['tmp_name'][$index],
        'error' => $_FILES['reports']['error'][$index],
        'size' => $_FILES['reports']['size'][$index],
      ];
      $_FILES['nb_report_upload'] = $file;
      $attachment_id = media_handle_upload('nb_report_upload', $post_id);
      if (!is_wp_error($attachment_id)) {
        $attachment_ids[] = $attachment_id;
      }
    }
    if ($attachment_ids) {
      update_post_meta($post_id, 'report_attachment_ids', $attachment_ids);
    }
  }

  $admin_email = get_option('admin_email');
  if ($admin_email) {
    $subject = $is_update ? __('Updated consultation response - %s', 'nirog-bhumi') : __('New consultation request - %s', 'nirog-bhumi');
    wp_mail($admin_email, sprintf($subject, $name), sprintf("Name: %s
Email: %s
Phone: %s
Concern: %s

View in WordPress dashboard: %s", $name, $email, $phone, $fields['concern'], admin_url('post.php?post=' . $post_id . '&action=edit')));
  }

  $prefill = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
  ];
  $cookie_value = rawurlencode(base64_encode(wp_json_encode($prefill)));
  $edit_token = !empty($_COOKIE['nb_consultation_edit_token']) && $is_update ? sanitize_text_field(wp_unslash($_COOKIE['nb_consultation_edit_token'])) : wp_generate_password(32, false, false);
  if (!$is_update) {
    update_post_meta($post_id, '_nb_edit_token_hash', wp_hash_password($edit_token));
  }
  setcookie('nb_consultation_entry', (string) $post_id, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
  setcookie('nb_consultation_prefill', $cookie_value, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
  setcookie('nb_consultation_edit_token', $edit_token, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);

  wp_safe_redirect(add_query_arg([
    'consultation_saved' => '1',
    'entry' => $post_id,
  ], home_url('/consultation-payment/')));
  exit;
}
add_action('admin_post_nopriv_nirog_consultation_submit', 'nirog_bhumi_handle_consultation_form');
add_action('admin_post_nirog_consultation_submit', 'nirog_bhumi_handle_consultation_form');

function nirog_bhumi_get_consultation_prefill() {
  $entry_id = nirog_bhumi_consultation_cookie_entry();
  if ($entry_id) {
    return [
      'name' => (string) get_post_meta($entry_id, 'name', true),
      'email' => (string) get_post_meta($entry_id, 'email', true),
      'phone' => (string) get_post_meta($entry_id, 'phone', true),
    ];
  }
  if (empty($_COOKIE['nb_consultation_prefill'])) {
    return [];
  }
  $decoded = json_decode(base64_decode(rawurldecode(wp_unslash($_COOKIE['nb_consultation_prefill']))), true);
  return is_array($decoded) ? $decoded : [];
}

function nirog_bhumi_prefill_checkout_value($value, $input) {
  if ($value || !nirog_bhumi_cart_is_consultation_only()) {
    return $value;
  }
  $prefill = nirog_bhumi_get_consultation_prefill();
  if (!$prefill) {
    return $value;
  }
  if ($input === 'billing_email' && !empty($prefill['email'])) {
    return $prefill['email'];
  }
  if ($input === 'billing_phone' && !empty($prefill['phone'])) {
    return $prefill['phone'];
  }
  if ($input === 'billing_first_name' && !empty($prefill['name'])) {
    $parts = preg_split('/s+/', trim($prefill['name']));
    return $parts ? $parts[0] : $value;
  }
  if ($input === 'billing_last_name' && !empty($prefill['name'])) {
    $parts = preg_split('/s+/', trim($prefill['name']));
    if (count($parts) > 1) {
      array_shift($parts);
      return implode(' ', $parts);
    }
  }
  return $value;
}
add_filter('woocommerce_checkout_get_value', 'nirog_bhumi_prefill_checkout_value', 10, 2);

function nirog_bhumi_order_has_consultation_product($order) {
  if (!$order || !is_a($order, 'WC_Order')) {
    return false;
  }
  $product_id = nirog_bhumi_consultation_product_id();
  if (!$product_id) {
    return false;
  }
  foreach ($order->get_items() as $item) {
    if ((int) $item->get_product_id() === $product_id) {
      return true;
    }
  }
  return false;
}

function nirog_bhumi_attach_consultation_entry_to_order($order, $data) {
  if (!is_a($order, 'WC_Order') || !function_exists('WC') || !WC()->cart) {
    return;
  }
  $product_id = nirog_bhumi_consultation_product_id();
  if (!$product_id) {
    return;
  }
  $has_consultation = false;
  foreach (WC()->cart->get_cart() as $cart_item) {
    if (!empty($cart_item['product_id']) && (int) $cart_item['product_id'] === $product_id) {
      $has_consultation = true;
      break;
    }
  }
  if (!$has_consultation) {
    return;
  }
  $entry_id = nirog_bhumi_consultation_cookie_entry();
  if ($entry_id) {
    $order->update_meta_data('_nb_consultation_entry_id', $entry_id);
  }
}
add_action('woocommerce_checkout_create_order', 'nirog_bhumi_attach_consultation_entry_to_order', 10, 2);

function nirog_bhumi_maybe_start_consultation_checkout() {
  if (empty($_GET['nb_start_consultation_checkout'])) {
    return;
  }
  if (!nirog_bhumi_consultation_cookie_entry()) {
    wp_safe_redirect(add_query_arg('consultation_step', 'form-required', home_url('/consultation/#consultation-form')));
    exit;
  }
  if (!function_exists('WC') || !function_exists('wc_get_checkout_url')) {
    wp_safe_redirect(add_query_arg('payment_setup', 'woocommerce-missing', home_url('/consultation-payment/')));
    exit;
  }
  $product_id = nirog_bhumi_consultation_product_id();
  if (!$product_id) {
    wp_safe_redirect(add_query_arg('payment_setup', 'missing-product', home_url('/consultation-payment/')));
    exit;
  }
  if (null === WC()->cart) {
    wc_load_cart();
  }
  $prefill = nirog_bhumi_get_consultation_prefill();
  if (WC()->customer && $prefill) {
    $parts = preg_split('/s+/', trim($prefill['name'] ?? ''));
    $first_name = $parts ? array_shift($parts) : '';
    WC()->customer->set_billing_first_name($first_name);
    WC()->customer->set_billing_last_name($parts ? implode(' ', $parts) : '');
    WC()->customer->set_billing_email($prefill['email'] ?? '');
    WC()->customer->set_billing_phone($prefill['phone'] ?? '');
    WC()->customer->save();
  }
  $settings = nirog_bhumi_get_settings();
  if (!empty($settings['consultation_clear_cart']) && $settings['consultation_clear_cart'] === 'yes') {
    WC()->cart->empty_cart();
  }
  $cart_id = WC()->cart->generate_cart_id($product_id);
  if (!WC()->cart->find_product_in_cart($cart_id)) {
    WC()->cart->add_to_cart($product_id, 1);
  }
  $checkout_id = nirog_bhumi_ensure_checkout_page();
  $checkout_url = $checkout_id ? get_permalink($checkout_id) : wc_get_checkout_url();
  wp_safe_redirect($checkout_url);
  exit;
}
add_action('template_redirect', 'nirog_bhumi_maybe_start_consultation_checkout', 5);

function nirog_bhumi_paid_consultation_order_from_request() {
  if (empty($_GET['order']) || empty($_GET['key']) || !function_exists('wc_get_order')) {
    return false;
  }
  $order = wc_get_order(absint(wp_unslash($_GET['order'])));
  $key = sanitize_text_field(wp_unslash($_GET['key']));
  if (!$order || !hash_equals((string) $order->get_order_key(), $key)) {
    return false;
  }
  if (!$order->is_paid() && !in_array($order->get_status(), ['processing', 'completed'], true)) {
    return false;
  }
  return nirog_bhumi_order_has_consultation_product($order) ? $order : false;
}

function nirog_bhumi_protect_consultation_flow() {
  if (is_admin() || current_user_can('manage_woocommerce')) {
    return;
  }
  if (is_page('consultation-payment') && !nirog_bhumi_consultation_cookie_entry()) {
    wp_safe_redirect(add_query_arg('consultation_step', 'form-required', home_url('/consultation/#consultation-form')));
    exit;
  }
  if (function_exists('is_checkout') && is_checkout() && !is_wc_endpoint_url('order-received') && nirog_bhumi_cart_is_consultation_only() && !nirog_bhumi_consultation_cookie_entry()) {
    WC()->cart->empty_cart();
    wp_safe_redirect(add_query_arg('consultation_step', 'form-required', home_url('/consultation/#consultation-form')));
    exit;
  }
  if (is_page('consultation-calendar') && !nirog_bhumi_paid_consultation_order_from_request()) {
    wp_safe_redirect(add_query_arg('consultation_step', 'payment-required', home_url('/consultation/')));
    exit;
  }
  if (is_page('consultation-calendar')) {
    nocache_headers();
  }
}
add_action('template_redirect', 'nirog_bhumi_protect_consultation_flow', 12);

function nirog_bhumi_maybe_redirect_consultation_to_calendar() {
  if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received')) {
    return;
  }
  $order_id = absint(get_query_var('order-received'));
  if (!$order_id) {
    return;
  }
  $order = wc_get_order($order_id);
  if (!$order || !nirog_bhumi_order_has_consultation_product($order)) {
    return;
  }
  if (!$order->is_paid() && !in_array($order->get_status(), ['processing', 'completed'], true)) {
    return;
  }
  $calendar_url = nirog_bhumi_consultation_calendar_url();
  if (!$calendar_url) {
    return;
  }
  wp_safe_redirect(add_query_arg([
    'booking' => 'paid',
    'order' => $order_id,
    'key' => $order->get_order_key(),
  ], $calendar_url));
  exit;
}
add_action('template_redirect', 'nirog_bhumi_maybe_redirect_consultation_to_calendar', 20);

function nirog_bhumi_consultation_metaboxes() {
  add_meta_box('nb_consultation_details', __('Consultation Details', 'nirog-bhumi'), 'nirog_bhumi_render_consultation_metabox', 'nb_consultation', 'normal', 'high');
}
add_action('add_meta_boxes', 'nirog_bhumi_consultation_metaboxes');

function nirog_bhumi_render_consultation_metabox($post) {
  $fields = [
    'name' => 'Name',
    'email' => 'Email',
    'phone' => 'Phone / WhatsApp',
    'age' => 'Age',
    'concern' => 'Primary concern',
    'fasting' => 'Fasting sugar',
    'postmeal' => 'Post-meal sugar',
    'hba1c' => 'HbA1c',
    'bp' => 'Blood pressure',
    'body' => 'Weight / waist',
    'medicines' => 'Current medication or insulin',
    'conditions' => 'Other health conditions',
    'food' => 'Food routine',
    'lifestyle' => 'Sleep, stress and activity',
    'goal' => 'Main goal',
    'consultation_disclaimer' => 'Consultation disclaimer',
    'data_processing_consent' => 'Data consent',
    'followup_consent' => 'Follow-up consent',
  ];
  echo '<div class="nb-admin-details">';
  foreach ($fields as $key => $label) {
    $value = get_post_meta($post->ID, $key, true);
    echo '<p><strong>' . esc_html($label) . ':</strong><br>' . nl2br(esc_html($value ?: '-')) . '</p>';
  }
  $attachments = (array) get_post_meta($post->ID, 'report_attachment_ids', true);
  if ($attachments) {
    echo '<p><strong>' . esc_html__('Uploaded reports:', 'nirog-bhumi') . '</strong><br>';
    foreach ($attachments as $attachment_id) {
      echo '<a href="' . esc_url(wp_get_attachment_url($attachment_id)) . '" target="_blank" rel="noopener">' . esc_html(get_the_title($attachment_id)) . '</a><br>';
    }
    echo '</p>';
  }
  echo '</div>';
}

function nirog_bhumi_consultation_columns($columns) {
  return [
    'cb' => $columns['cb'],
    'title' => __('Entry', 'nirog-bhumi'),
    'nb_phone' => __('Phone', 'nirog-bhumi'),
    'nb_concern' => __('Concern', 'nirog-bhumi'),
    'date' => $columns['date'],
  ];
}
add_filter('manage_nb_consultation_posts_columns', 'nirog_bhumi_consultation_columns');

function nirog_bhumi_consultation_column_content($column, $post_id) {
  if ($column === 'nb_phone') {
    echo esc_html(get_post_meta($post_id, 'phone', true));
  }
  if ($column === 'nb_concern') {
    echo esc_html(get_post_meta($post_id, 'concern', true));
  }
}
add_action('manage_nb_consultation_posts_custom_column', 'nirog_bhumi_consultation_column_content', 10, 2);

function nirog_bhumi_register_form_entries() {
  register_post_type('nb_form_entry', [
    'labels' => [
      'name' => __('Form Entries', 'nirog-bhumi'),
      'singular_name' => __('Form Entry', 'nirog-bhumi'),
      'menu_name' => __('Form Entries', 'nirog-bhumi'),
    ],
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_icon' => 'dashicons-feedback',
    'supports' => ['title'],
  ]);
}
add_action('init', 'nirog_bhumi_register_form_entries');

function nirog_bhumi_handle_form_entry() {
  if (!isset($_POST['nirog_form_entry_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nirog_form_entry_nonce'])), 'nirog_form_entry_submit')) {
    wp_die(esc_html__('Security check failed. Please go back and submit the form again.', 'nirog-bhumi'));
  }

  $form_type = nirog_bhumi_clean_field('form_type') ?: 'Website form';
  $name = nirog_bhumi_clean_field('name');
  $email = sanitize_email(nirog_bhumi_clean_field('email'));
  $phone = nirog_bhumi_clean_field('phone');
  $title_name = $name ?: ($email ?: __('Website entry', 'nirog-bhumi'));

  $post_id = wp_insert_post([
    'post_type' => 'nb_form_entry',
    'post_status' => 'private',
    'post_title' => sprintf('%s - %s - %s', $form_type, $title_name, current_time('d M Y H:i')),
  ]);

  if (is_wp_error($post_id) || !$post_id) {
    wp_die(esc_html__('Could not save the form entry. Please try again.', 'nirog-bhumi'));
  }

  update_post_meta($post_id, 'form_type', $form_type);
  foreach ($_POST as $key => $value) {
    if (in_array($key, ['action', 'nirog_form_entry_nonce', '_wp_http_referer'], true)) {
      continue;
    }
    $clean_key = sanitize_key($key);
    if (is_array($value)) {
      update_post_meta($post_id, $clean_key, array_map('sanitize_text_field', wp_unslash($value)));
    } else {
      update_post_meta($post_id, $clean_key, sanitize_textarea_field(wp_unslash($value)));
    }
  }

  foreach ($_FILES as $field => $file_group) {
    if (empty($file_group['name'])) {
      continue;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachment_ids = [];
    if (is_array($file_group['name'])) {
      foreach ($file_group['name'] as $index => $filename) {
        if (!$filename) {
          continue;
        }
        $_FILES['nb_generic_upload'] = [
          'name' => $file_group['name'][$index],
          'type' => $file_group['type'][$index],
          'tmp_name' => $file_group['tmp_name'][$index],
          'error' => $file_group['error'][$index],
          'size' => $file_group['size'][$index],
        ];
        $attachment_id = media_handle_upload('nb_generic_upload', $post_id);
        if (!is_wp_error($attachment_id)) {
          $attachment_ids[] = $attachment_id;
        }
      }
    } else {
      $attachment_id = media_handle_upload($field, $post_id);
      if (!is_wp_error($attachment_id)) {
        $attachment_ids[] = $attachment_id;
      }
    }
    if ($attachment_ids) {
      update_post_meta($post_id, sanitize_key($field) . '_attachment_ids', $attachment_ids);
    }
  }

  $admin_email = get_option('admin_email');
  if ($admin_email) {
    wp_mail($admin_email, sprintf(__('New %s entry - %s', 'nirog-bhumi'), $form_type, $title_name), sprintf("Form: %s
Name: %s
Email: %s
Phone: %s

View in WordPress dashboard: %s", $form_type, $name, $email, $phone, admin_url('post.php?post=' . $post_id . '&action=edit')));
  }

  wp_safe_redirect(add_query_arg('form_saved', '1', wp_get_referer() ?: home_url('/')));
  exit;
}
add_action('admin_post_nopriv_nirog_form_entry_submit', 'nirog_bhumi_handle_form_entry');
add_action('admin_post_nirog_form_entry_submit', 'nirog_bhumi_handle_form_entry');

function nirog_bhumi_form_entry_metaboxes() {
  add_meta_box('nb_form_entry_details', __('Entry Details', 'nirog-bhumi'), 'nirog_bhumi_render_form_entry_metabox', 'nb_form_entry', 'normal', 'high');
}
add_action('add_meta_boxes', 'nirog_bhumi_form_entry_metaboxes');

function nirog_bhumi_render_form_entry_metabox($post) {
  $meta = get_post_meta($post->ID);
  echo '<div class="nb-admin-details">';
  foreach ($meta as $key => $values) {
    if (str_starts_with($key, '_')) {
      continue;
    }
    $value = maybe_unserialize($values[0]);
    echo '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong><br>';
    if (str_ends_with($key, '_attachment_ids')) {
      foreach ((array) $value as $attachment_id) {
        echo '<a href="' . esc_url(wp_get_attachment_url($attachment_id)) . '" target="_blank" rel="noopener">' . esc_html(get_the_title($attachment_id)) . '</a><br>';
      }
    } else {
      echo nl2br(esc_html(is_array($value) ? implode(', ', $value) : $value));
    }
    echo '</p>';
  }
  echo '</div>';
}

function nirog_bhumi_form_entry_columns($columns) {
  return [
    'cb' => $columns['cb'],
    'title' => __('Entry', 'nirog-bhumi'),
    'nb_form_type' => __('Form', 'nirog-bhumi'),
    'nb_phone' => __('Phone', 'nirog-bhumi'),
    'date' => $columns['date'],
  ];
}
add_filter('manage_nb_form_entry_posts_columns', 'nirog_bhumi_form_entry_columns');

function nirog_bhumi_form_entry_column_content($column, $post_id) {
  if ($column === 'nb_form_type') {
    echo esc_html(get_post_meta($post_id, 'form_type', true));
  }
  if ($column === 'nb_phone') {
    echo esc_html(get_post_meta($post_id, 'phone', true));
  }
}
add_action('manage_nb_form_entry_posts_custom_column', 'nirog_bhumi_form_entry_column_content', 10, 2);
