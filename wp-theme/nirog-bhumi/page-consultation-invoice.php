<?php
$entry_id = isset($_GET['entry']) ? absint(wp_unslash($_GET['entry'])) : 0;
$access = isset($_GET['access']) ? sanitize_text_field(wp_unslash($_GET['access'])) : '';
$cookie_entry = function_exists('nirog_bhumi_consultation_cookie_entry') ? nirog_bhumi_consultation_cookie_entry() : 0;
$allowed = function_exists('nirog_bhumi_consultation_status_access') && nirog_bhumi_consultation_status_access($entry_id, $access);
$payment_status = (string) get_post_meta($entry_id, 'payment_status', true);
$invoice_number = (string) get_post_meta($entry_id, 'invoice_number', true);
if ((!$allowed && $cookie_entry !== $entry_id) || $payment_status !== 'verified' || !$invoice_number) {
  wp_safe_redirect(home_url('/consultation/'));
  exit;
}
nocache_headers();
$settings = nirog_bhumi_get_settings();
$name = (string) get_post_meta($entry_id, 'name', true);
$email = (string) get_post_meta($entry_id, 'email', true);
$phone = (string) get_post_meta($entry_id, 'phone', true);
$payment_reference = (string) get_post_meta($entry_id, 'payment_reference', true);
$verified_at = (string) get_post_meta($entry_id, 'payment_verified_at', true);
$invoice_date = $verified_at ? wp_date(get_option('date_format'), strtotime($verified_at)) : wp_date(get_option('date_format'));
get_header(); ?>
<main class="invoice-page-shell">
  <div class="invoice-toolbar"><a href="<?php echo esc_url(nirog_bhumi_consultation_status_url($entry_id)); ?>">Back to consultation status</a><button class="pill primary" type="button" onclick="window.print()">Print or save PDF</button></div>
  <article class="invoice-document">
    <header class="invoice-header"><div><p class="eyebrow">Invoice</p><h1><?php echo esc_html($settings['invoice_legal_name']); ?></h1><?php if ($settings['invoice_address']) : ?><p><?php echo nl2br(esc_html($settings['invoice_address'])); ?></p><?php endif; ?><?php if ($settings['invoice_gstin']) : ?><p><strong>GSTIN:</strong> <?php echo esc_html($settings['invoice_gstin']); ?></p><?php endif; ?></div><div class="invoice-number"><span>Invoice number</span><strong><?php echo esc_html($invoice_number); ?></strong><span>Invoice date</span><strong><?php echo esc_html($invoice_date); ?></strong></div></header>
    <section class="invoice-parties"><div><span>Issued by</span><strong><?php echo esc_html($settings['invoice_legal_name']); ?></strong><?php if ($settings['invoice_email']) : ?><p><?php echo esc_html($settings['invoice_email']); ?></p><?php endif; ?><?php if ($settings['invoice_phone']) : ?><p><?php echo esc_html($settings['invoice_phone']); ?></p><?php endif; ?></div><div><span>Issued to</span><strong><?php echo esc_html($name); ?></strong><p><?php echo esc_html($email); ?></p><p><?php echo esc_html($phone); ?></p></div></section>
    <table class="invoice-items"><thead><tr><th>Description</th><?php if ($settings['invoice_sac']) : ?><th>SAC</th><?php endif; ?><th>Qty</th><th>Amount</th></tr></thead><tbody><tr><td>30-minute consultation with Gautam Khandelwal</td><?php if ($settings['invoice_sac']) : ?><td><?php echo esc_html($settings['invoice_sac']); ?></td><?php endif; ?><td>1</td><td>Rs. 500</td></tr></tbody><tfoot><tr><th colspan="<?php echo $settings['invoice_sac'] ? '3' : '2'; ?>">Total received</th><th>Rs. 500</th></tr></tfoot></table>
    <section class="invoice-meta"><div><span>Consultation reference</span><strong><?php echo esc_html(nirog_bhumi_consultation_reference($entry_id)); ?></strong></div><?php if ($payment_reference) : ?><div><span>Payment reference</span><strong><?php echo esc_html($payment_reference); ?></strong></div><?php endif; ?><div><span>Payment status</span><strong>Paid</strong></div></section>
    <footer class="invoice-footer"><p>Thank you for choosing Nirog Bhumi.</p><p>This document records the payment received for the service listed above.</p></footer>
  </article>
</main>
<?php get_footer(); ?>