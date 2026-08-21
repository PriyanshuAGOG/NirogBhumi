<?php
/**
 * Editable customer email templates.
 *
 * Nirog Bhumi sends two distinct kinds of customer-facing purchase emails:
 *   - Consultation payment confirmation (booking a session with Gautam)
 *   - Store order confirmation (buying a physical product/kit)
 * These are different purchases and must not share generic "thank you for
 * your order" wording. Both templates are editable from
 * NB Dashboard -> Email Templates without touching code, using {{tokens}}
 * that get replaced with the real values when an email is sent.
 *
 * WooCommerce's own built-in customer emails are disabled site-wide (see
 * nirog_bhumi_disable_native_woocommerce_customer_emails() below) so these
 * two templates are the only customer purchase emails that go out, and both
 * are editable from one place.
 */

if (!defined('ABSPATH')) {
  exit;
}

// ─── Defaults ───────────────────────────────────────────────────────────────

function nirog_bhumi_email_template_defaults() {
  return [
    'consultation_subject' => 'Payment confirmed - {{invoice_number}}',
    'consultation_body' => "<p>Hello {{name}},</p>\n<p>Thank you for booking your 30-minute consultation with Gautam Khandelwal. We have received your payment.</p>\n<div style=\"border:1px solid #d8d0c0;padding:20px;margin:24px 0\">\n<p><strong>Invoice:</strong> {{invoice_number}}</p>\n<p><strong>Consultation reference:</strong> {{reference}}</p>\n<p><strong>Amount received:</strong> Rs. {{amount}}</p>\n<p><strong>Payment date:</strong> {{payment_date}}</p>\n<p><strong>Service:</strong> 30-minute consultation</p>\n</div>\n{{slot_line}}\n<p><a href=\"{{invoice_url}}\" style=\"display:inline-block;background:#314936;color:#fff;padding:12px 20px;text-decoration:none;border-radius:24px\">Download invoice PDF</a></p>\n<p><a href=\"{{status_url}}\">View consultation status</a></p>\n<p>Questions? Write to us at priyanshu@nirogbhumi.com.</p>\n<p>Regards,<br>Nirog Bhumi</p>",
    'store_order_subject' => 'Your Nirog Bhumi order {{order_number}} is confirmed',
    'store_order_body' => "<p>Hello {{name}},</p>\n<p>Thank you for your purchase from Nirog Bhumi. Your order is confirmed and being prepared.</p>\n<div style=\"border:1px solid #d8d0c0;padding:20px;margin:24px 0\">\n<p><strong>Order number:</strong> {{order_number}}</p>\n<p><strong>Order date:</strong> {{order_date}}</p>\n<p><strong>Items:</strong></p>\n{{order_items}}\n<p><strong>Total paid:</strong> Rs. {{order_total}}</p>\n</div>\n<p>We will notify you once your order ships.</p>\n<p>Questions? Write to us at priyanshu@nirogbhumi.com.</p>\n<p>Regards,<br>Nirog Bhumi</p>",
  ];
}

function nirog_bhumi_get_email_templates() {
  $saved = get_option('nirog_bhumi_email_templates', []);
  return wp_parse_args(is_array($saved) ? $saved : [], nirog_bhumi_email_template_defaults());
}

function nirog_bhumi_sanitize_email_templates($input) {
  $defaults = nirog_bhumi_email_template_defaults();
  $allowed_html = [
    'p' => ['style' => true], 'a' => ['href' => true, 'style' => true, 'target' => true, 'rel' => true],
    'div' => ['style' => true], 'strong' => [], 'em' => [], 'br' => [], 'span' => ['style' => true],
    'ul' => [], 'ol' => [], 'li' => [],
  ];
  return [
    'consultation_subject' => isset($input['consultation_subject']) ? sanitize_text_field($input['consultation_subject']) : $defaults['consultation_subject'],
    'consultation_body' => isset($input['consultation_body']) ? wp_kses(wp_unslash($input['consultation_body']), $allowed_html) : $defaults['consultation_body'],
    'store_order_subject' => isset($input['store_order_subject']) ? sanitize_text_field($input['store_order_subject']) : $defaults['store_order_subject'],
    'store_order_body' => isset($input['store_order_body']) ? wp_kses(wp_unslash($input['store_order_body']), $allowed_html) : $defaults['store_order_body'],
  ];
}

function nirog_bhumi_register_email_template_settings() {
  register_setting('nirog_bhumi_email_templates_group', 'nirog_bhumi_email_templates', 'nirog_bhumi_sanitize_email_templates');
}
add_action('admin_init', 'nirog_bhumi_register_email_template_settings');

/** Replace {{token}} placeholders in a template string with real values. */
function nirog_bhumi_render_email_template($template, $tokens) {
  $search = [];
  $replace = [];
  foreach ($tokens as $key => $value) {
    $search[] = '{{' . $key . '}}';
    $replace[] = (string) $value;
  }
  return str_replace($search, $replace, (string) $template);
}

// ─── Admin page ─────────────────────────────────────────────────────────────

function nirog_bhumi_register_email_templates_page() {
  add_submenu_page(
    'nirog-bhumi-dashboard',
    __('Email Templates', 'nirog-bhumi'),
    __('Email Templates', 'nirog-bhumi'),
    'manage_options',
    'nirog-bhumi-email-templates',
    'nirog_bhumi_render_email_templates_page'
  );
}
add_action('admin_menu', 'nirog_bhumi_register_email_templates_page');

function nirog_bhumi_render_email_templates_page() {
  if (!current_user_can('manage_options')) {
    return;
  }
  $templates = nirog_bhumi_get_email_templates();
  $defaults = nirog_bhumi_email_template_defaults();
  $reset = isset($_GET['nb_reset_template']) ? sanitize_key(wp_unslash($_GET['nb_reset_template'])) : '';
  if ($reset && current_user_can('manage_options') && check_admin_referer('nirog_reset_email_template_' . $reset, 'nirog_reset_nonce', false)) {
    $saved = get_option('nirog_bhumi_email_templates', []);
    $saved = is_array($saved) ? $saved : [];
    if ($reset === 'consultation') {
      $saved['consultation_subject'] = $defaults['consultation_subject'];
      $saved['consultation_body'] = $defaults['consultation_body'];
    } elseif ($reset === 'store_order') {
      $saved['store_order_subject'] = $defaults['store_order_subject'];
      $saved['store_order_body'] = $defaults['store_order_body'];
    }
    update_option('nirog_bhumi_email_templates', $saved);
    $templates = nirog_bhumi_get_email_templates();
    echo '<div class="notice notice-success"><p>' . esc_html__('Template reset to the default wording.', 'nirog-bhumi') . '</p></div>';
  }
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Email Templates', 'nirog-bhumi'); ?></h1>
    <p><?php esc_html_e('These are the only two customer-facing purchase emails Nirog Bhumi sends. Edit the wording below - the tokens in double curly braces are replaced automatically with the real booking or order details when each email is sent.', 'nirog-bhumi'); ?></p>

    <form method="post" action="options.php">
      <?php settings_fields('nirog_bhumi_email_templates_group'); ?>

      <h2><?php esc_html_e('1. Consultation payment confirmation', 'nirog-bhumi'); ?></h2>
      <p class="description"><?php esc_html_e('Sent the moment a consultation payment is verified. Available tokens:', 'nirog-bhumi'); ?> <code>{{name}}</code> <code>{{invoice_number}}</code> <code>{{reference}}</code> <code>{{amount}}</code> <code>{{payment_date}}</code> <code>{{slot_line}}</code> <code>{{invoice_url}}</code> <code>{{status_url}}</code></p>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row"><label for="nb-consultation-subject"><?php esc_html_e('Subject', 'nirog-bhumi'); ?></label></th>
          <td><input id="nb-consultation-subject" name="nirog_bhumi_email_templates[consultation_subject]" type="text" class="large-text" value="<?php echo esc_attr($templates['consultation_subject']); ?>"></td>
        </tr>
        <tr>
          <th scope="row"><label for="nb-consultation-body"><?php esc_html_e('Body (HTML)', 'nirog-bhumi'); ?></label></th>
          <td><textarea id="nb-consultation-body" name="nirog_bhumi_email_templates[consultation_body]" rows="14" class="large-text code"><?php echo esc_textarea($templates['consultation_body']); ?></textarea></td>
        </tr>
      </table>
      <p><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['nb_reset_template' => 'consultation']), 'nirog_reset_email_template_consultation', 'nirog_reset_nonce')); ?>" onclick="return confirm('<?php echo esc_js(__('Reset the consultation email to the default wording? Unsaved edits above will be lost.', 'nirog-bhumi')); ?>');"><?php esc_html_e('Reset to default wording', 'nirog-bhumi'); ?></a></p>

      <hr style="margin:32px 0">

      <h2><?php esc_html_e('2. Store order confirmation', 'nirog-bhumi'); ?></h2>
      <p class="description"><?php esc_html_e('Sent when a customer pays for a physical product/kit order (not a consultation). Available tokens:', 'nirog-bhumi'); ?> <code>{{name}}</code> <code>{{order_number}}</code> <code>{{order_date}}</code> <code>{{order_items}}</code> <code>{{order_total}}</code></p>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row"><label for="nb-store-subject"><?php esc_html_e('Subject', 'nirog-bhumi'); ?></label></th>
          <td><input id="nb-store-subject" name="nirog_bhumi_email_templates[store_order_subject]" type="text" class="large-text" value="<?php echo esc_attr($templates['store_order_subject']); ?>"></td>
        </tr>
        <tr>
          <th scope="row"><label for="nb-store-body"><?php esc_html_e('Body (HTML)', 'nirog-bhumi'); ?></label></th>
          <td><textarea id="nb-store-body" name="nirog_bhumi_email_templates[store_order_body]" rows="14" class="large-text code"><?php echo esc_textarea($templates['store_order_body']); ?></textarea></td>
        </tr>
      </table>
      <p><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['nb_reset_template' => 'store_order']), 'nirog_reset_email_template_store_order', 'nirog_reset_nonce')); ?>" onclick="return confirm('<?php echo esc_js(__('Reset the store order email to the default wording? Unsaved edits above will be lost.', 'nirog-bhumi')); ?>');"><?php esc_html_e('Reset to default wording', 'nirog-bhumi'); ?></a></p>

      <?php submit_button(__('Save email templates', 'nirog-bhumi')); ?>
    </form>
  </div>
  <?php
}

// ─── Disable WooCommerce's own native customer purchase emails ─────────────

/**
 * WooCommerce's built-in "Processing order" / "Completed order" customer
 * emails use generic "thank you for your order" language regardless of what
 * was purchased, and are not editable from this dashboard. Both purchase
 * paths (consultation and store product) now send their own dashboard-
 * editable email instead (see below), so the native ones are switched off
 * to avoid the customer getting a second, generic, unbranded email.
 */
function nirog_bhumi_disable_native_woocommerce_customer_emails($enabled) {
  return false;
}
add_filter('woocommerce_email_enabled_customer_processing_order', 'nirog_bhumi_disable_native_woocommerce_customer_emails');
add_filter('woocommerce_email_enabled_customer_completed_order', 'nirog_bhumi_disable_native_woocommerce_customer_emails');

// ─── Store order confirmation email ────────────────────────────────────────

/**
 * Send the dashboard-editable store order confirmation email for a paid
 * WooCommerce order that is NOT a consultation booking (i.e. a genuine
 * physical product/kit purchase). Consultation orders are handled entirely
 * by nirog_bhumi_send_consultation_invoice() instead.
 */
function nirog_bhumi_send_store_order_email($order_id) {
  if (!function_exists('wc_get_order')) {
    return;
  }
  $order = wc_get_order($order_id);
  if (!$order || nirog_bhumi_order_has_consultation_product($order)) {
    return;
  }
  if (!$order->is_paid() || $order->get_meta('_nb_store_email_sent')) {
    return;
  }
  $email = $order->get_billing_email();
  if (!$email) {
    return;
  }
  $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: __('there', 'nirog-bhumi');
  $items_html = '<ul>';
  foreach ($order->get_items() as $item) {
    $items_html .= '<li>' . esc_html($item->get_name()) . ' &times; ' . esc_html($item->get_quantity()) . ' - Rs. ' . esc_html(number_format((float) $item->get_total(), 2)) . '</li>';
  }
  $items_html .= '</ul>';

  $templates = nirog_bhumi_get_email_templates();
  $tokens = [
    'name' => esc_html($name),
    'order_number' => esc_html($order->get_order_number()),
    'order_date' => esc_html(nirog_bhumi_local_date(get_option('date_format'), $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '')),
    'order_items' => $items_html,
    'order_total' => esc_html(number_format((float) $order->get_total(), 2)),
  ];
  $subject = nirog_bhumi_render_email_template($templates['store_order_subject'], $tokens);
  $body = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#263126">' . nirog_bhumi_render_email_template($templates['store_order_body'], $tokens) . '</div>';

  $sent = wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
  if ($sent) {
    $order->update_meta_data('_nb_store_email_sent', current_time('mysql'));
    $order->save();
  }
}
add_action('woocommerce_payment_complete', 'nirog_bhumi_send_store_order_email', 25);
