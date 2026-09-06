<?php
/**
 * Store settings for the Nirog Bhumi WooCommerce shop.
 *
 * Kept in their own option so the existing consultation and invoice identity
 * settings in functions.php are not disturbed.
 */

if (!defined('ABSPATH')) {
  exit;
}

function nirog_bhumi_store_settings_defaults() {
  return [
    'store_status' => 'coming_soon',
    'store_note' => 'Dispatch from Jaipur within 3 working days. Delivery across India.',
    'free_shipping_threshold' => '1500',
    'goods_gst_rate' => '',
    'default_hsn' => '',
    'waitlist_enabled' => 'yes',
    'show_cart_link' => 'yes',
  ];
}

function nirog_bhumi_get_store_settings() {
  $saved = get_option('nirog_bhumi_store_settings', []);
  return wp_parse_args(is_array($saved) ? $saved : [], nirog_bhumi_store_settings_defaults());
}

function nirog_bhumi_sanitize_store_settings($input) {
  $defaults = nirog_bhumi_store_settings_defaults();
  $status = isset($input['store_status']) ? sanitize_key($input['store_status']) : $defaults['store_status'];

  return [
    'store_status' => in_array($status, ['open', 'preview', 'coming_soon'], true) ? $status : 'coming_soon',
    'store_note' => isset($input['store_note']) ? sanitize_text_field($input['store_note']) : $defaults['store_note'],
    'free_shipping_threshold' => isset($input['free_shipping_threshold']) ? (string) max(0, (float) $input['free_shipping_threshold']) : '',
    'goods_gst_rate' => isset($input['goods_gst_rate']) && '' !== trim((string) $input['goods_gst_rate'])
      ? (string) max(0, min(100, (float) $input['goods_gst_rate']))
      : '',
    'default_hsn' => isset($input['default_hsn']) ? preg_replace('/[^0-9]/', '', (string) $input['default_hsn']) : '',
    'waitlist_enabled' => !empty($input['waitlist_enabled']) ? 'yes' : 'no',
    'show_cart_link' => !empty($input['show_cart_link']) ? 'yes' : 'no',
  ];
}

function nirog_bhumi_register_store_settings() {
  register_setting('nirog_bhumi_store_settings_group', 'nirog_bhumi_store_settings', [
    'sanitize_callback' => 'nirog_bhumi_sanitize_store_settings',
    'default' => nirog_bhumi_store_settings_defaults(),
  ]);
}
add_action('admin_init', 'nirog_bhumi_register_store_settings');

/**
 * Store status.
 *
 * open        - catalogue is live, prices and Add to Cart are shown to everyone.
 * preview     - catalogue is visible but nothing can be bought; waitlist only.
 * coming_soon - visitors only see the Coming soon panel. Administrators still
 *               see the catalogue so it can be checked before launch.
 */
function nirog_bhumi_store_status() {
  $settings = nirog_bhumi_get_store_settings();
  return $settings['store_status'];
}

function nirog_bhumi_store_catalogue_is_visible() {
  $status = nirog_bhumi_store_status();
  if ('coming_soon' === $status) {
    return current_user_can('manage_woocommerce');
  }
  return true;
}

function nirog_bhumi_store_selling_is_open() {
  return 'open' === nirog_bhumi_store_status() && nirog_bhumi_woocommerce_active();
}

function nirog_bhumi_woocommerce_active() {
  return class_exists('WooCommerce');
}

function nirog_bhumi_store_waitlist_enabled() {
  $settings = nirog_bhumi_get_store_settings();
  return 'yes' === $settings['waitlist_enabled'];
}

function nirog_bhumi_store_free_shipping_threshold() {
  $settings = nirog_bhumi_get_store_settings();
  $value = (float) $settings['free_shipping_threshold'];
  return $value > 0 ? $value : 0;
}
