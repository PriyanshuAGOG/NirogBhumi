<?php
$entry_id = isset($_GET['entry']) ? absint(wp_unslash($_GET['entry'])) : 0;
$access = isset($_GET['access']) ? sanitize_text_field(wp_unslash($_GET['access'])) : '';
$cookie_entry = function_exists('nirog_bhumi_consultation_cookie_entry') ? nirog_bhumi_consultation_cookie_entry() : 0;
$allowed = function_exists('nirog_bhumi_consultation_status_access') && nirog_bhumi_consultation_status_access($entry_id, $access);
if (!$allowed && $cookie_entry !== $entry_id) {
  wp_safe_redirect(home_url('/consultation/'));
  exit;
}
nocache_headers();
$payment_status = (string) get_post_meta($entry_id, 'payment_status', true);
$slot_date = (string) get_post_meta($entry_id, 'slot_date', true);
$slot_time = (string) get_post_meta($entry_id, 'slot_time', true);
$meeting_details = (string) get_post_meta($entry_id, 'meeting_details', true);
$meeting_url = (string) get_post_meta($entry_id, 'meeting_url', true);
$invoice_number = (string) get_post_meta($entry_id, 'invoice_number', true);
$reference = nirog_bhumi_consultation_reference($entry_id);
$whatsapp_url = nirog_bhumi_consultation_whatsapp_url($entry_id);
$invoice_url = nirog_bhumi_consultation_invoice_url($entry_id);
get_header(); ?>
<main class="consultation-status-page">
  <section class="consultation-status-intro">
    <p class="eyebrow">Consultation status</p>
    <?php if ($payment_status === 'verified' && $slot_date) : ?>
      <h1>Your consultation is confirmed.</h1>
      <p>Your session details are available below.</p>
    <?php elseif ($payment_status === 'verified') : ?>
      <h1>Your payment is confirmed.</h1>
      <p>The Nirog Bhumi team is arranging your consultation time.</p>
    <?php else : ?>
      <h1>Payment confirmation is pending.</h1>
      <p>Share your payment confirmation on WhatsApp and the team will update your booking.</p>
    <?php endif; ?>
  </section>
  <section class="consultation-status-card">
    <div class="status-badge <?php echo $payment_status === 'verified' ? 'verified' : 'pending'; ?>"><?php echo esc_html($payment_status === 'verified' ? __('Payment verified', 'nirog-bhumi') : __('Awaiting confirmation', 'nirog-bhumi')); ?></div>
    <dl>
      <div><dt>Reference</dt><dd><?php echo esc_html($reference); ?></dd></div>
      <div><dt>Consultant</dt><dd>Gautam Khandelwal</dd></div>
      <div><dt>Duration</dt><dd>30 minutes</dd></div>
      <div><dt>Amount</dt><dd>Rs. 500</dd></div>
      <?php if ($payment_status === 'verified' && $slot_date) : ?><div><dt>Date</dt><dd><?php echo esc_html(wp_date(get_option('date_format'), strtotime($slot_date))); ?></dd></div><?php endif; ?>
      <?php if ($payment_status === 'verified' && $slot_time) : ?><div><dt>Time</dt><dd><?php echo esc_html(wp_date(get_option('time_format'), strtotime($slot_time))); ?> IST</dd></div><?php endif; ?>
      <?php if ($payment_status === 'verified' && $meeting_details) : ?><div><dt>Joining details</dt><dd><?php echo nl2br(esc_html($meeting_details)); ?></dd></div><?php endif; ?>
      <?php if ($payment_status === 'verified' && $invoice_number) : ?><div><dt>Invoice</dt><dd><?php echo esc_html($invoice_number); ?></dd></div><?php endif; ?>
    </dl>
    <div class="status-actions">
      <?php if ($payment_status !== 'verified') : ?><a class="pill primary" target="_blank" rel="noopener" href="<?php echo esc_url($whatsapp_url); ?>">Continue on WhatsApp</a><?php endif; ?>
      <?php if ($payment_status === 'verified' && $meeting_url) : ?><a class="pill primary" target="_blank" rel="noopener" href="<?php echo esc_url($meeting_url); ?>">Open meeting link</a><?php endif; ?>
      <?php if ($payment_status === 'verified' && $invoice_number) : ?><a class="pill ghost" href="<?php echo esc_url($invoice_url); ?>">Download invoice PDF</a><?php endif; ?>
    </div>
  </section>
</main>
<?php get_footer(); ?>