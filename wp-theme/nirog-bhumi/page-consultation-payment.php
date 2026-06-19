<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-payment.html.
 */
get_header(); ?>
<main>
<section class="consultation-checkout">
  <div class="payment-card"><span>Consultation checkout</span><h1>Rs. 500</h1><p>Confirm your 30-minute consultation with founder Gautam Khandelwal, who brings over 26 years of personal practice, study, and lived experience in naturopathy and natural healing.</p><div class="payment-content-slot" data-payment-content><?php while (have_posts()) : the_post(); $payment_content = trim(get_the_content()); if ($payment_content) { the_content(); } else { $checkout_url = function_exists('nirog_bhumi_consultation_checkout_url') ? nirog_bhumi_consultation_checkout_url() : ''; $edit_url = function_exists('nirog_bhumi_consultation_edit_url') ? nirog_bhumi_consultation_edit_url() : home_url('/consultation/#consultation-form'); ?><div class="hero-buttons"><?php if ($checkout_url) { ?><a class="pill primary" href="<?php echo esc_url($checkout_url); ?>">Proceed to Rs. 500 Payment</a><?php } else { ?><span class="pill ghost"><?php esc_html_e('Booking is temporarily unavailable', 'nirog-bhumi'); ?></span><?php } ?><a class="pill ghost" href="<?php echo esc_url($edit_url); ?>">Edit response</a></div><?php } endwhile; ?></div></div>
  <aside class="summary"><h2>Booking summary</h2><div><span>Service</span><strong>30-minute consultation</strong></div><div><span>Consultant</span><strong>Gautam Khandelwal</strong></div><div><span>Experience</span><strong>Over 26 years in naturopathy</strong></div><div><span>Amount</span><strong>Rs. 500</strong></div><div><span>Scheduling</span><strong>Choose a convenient time</strong></div><p>Your booking amount confirms your session and helps the team prepare your case before the call.</p></aside>
</section>
</main>
<?php get_footer(); ?>