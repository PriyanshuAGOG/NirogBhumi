<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-payment.html.
 */
get_header(); ?>
<main>
<section class="consultation-checkout">
  <div class="payment-card"><span>Consultation checkout</span><h1>Rs. 500</h1><p>Pay the booking amount for your 30-minute consultation with founder Gautam Khandelwal, who brings over 26 years of personal practice, study, and lived experience in naturopathy and natural healing. After payment, choose your preferred calendar slot.</p><div class="payment-content-slot" data-payment-content><?php while (have_posts()) : the_post(); $payment_content = trim(get_the_content()); if ($payment_content) { the_content(); } else { $checkout_url = function_exists('nirog_bhumi_consultation_checkout_url') ? nirog_bhumi_consultation_checkout_url() : ''; $calendar_url = function_exists('nirog_bhumi_consultation_calendar_url') ? nirog_bhumi_consultation_calendar_url() : home_url('/consultation-calendar/'); ?><div class="hero-buttons"><?php if ($checkout_url) { ?><a class="pill primary" href="<?php echo esc_url($checkout_url); ?>">Proceed to Rs. 500 Payment</a><?php } else { ?><a class="pill primary" href="<?php echo esc_url(admin_url('options-general.php?page=nirog-bhumi-setup')); ?>"><?php esc_html_e('Configure Consultation Payment', 'nirog-bhumi'); ?></a><?php } ?><a class="pill ghost" href="<?php echo esc_url(home_url('/consultation/')); ?>">Edit Form</a><?php if (!$checkout_url) { ?><a class="pill ghost" href="<?php echo esc_url($calendar_url); ?>"><?php esc_html_e('Open Calendar Page', 'nirog-bhumi'); ?></a><?php } ?></div><?php } endwhile; ?></div></div>
  <aside class="summary"><h2>Booking summary</h2><div><span>Service</span><strong>30-minute consultation</strong></div><div><span>Consultant</span><strong>Gautam Khandelwal</strong></div><div><span>Experience</span><strong>Over 26 years in naturopathy</strong></div><div><span>Amount</span><strong>Rs. 500</strong></div><div><span>Next step</span><strong>Calendar booking after payment</strong></div><p>Your booking amount confirms seriousness and helps the team prepare your case before the call.</p></aside>
</section>
</main>
<?php get_footer(); ?>