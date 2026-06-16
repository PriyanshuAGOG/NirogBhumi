<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-payment.html.
 */
get_header(); ?>
<main>
<section class="consultation-checkout">
  <div class="payment-card"><span>Consultation checkout</span><h1>Rs. 500</h1><p>Pay the booking amount for your 30-minute consultation with founder Gautam Khandelwal, who brings over 26 years of personal practice, study, and lived experience in naturopathy and natural healing. After payment, choose your preferred calendar slot.</p><div class="payment-content-slot" data-payment-content><?php while (have_posts()) : the_post(); $payment_content = trim(get_the_content()); if ($payment_content) { the_content(); } else { ?><div class="hero-buttons"><a class="pill primary" href="<?php echo esc_url(home_url('/consultation-calendar/')); ?>">Pay Rs. 500</a><a class="pill ghost" href="<?php echo esc_url(home_url('/consultation/')); ?>">Edit Form</a></div><?php } endwhile; ?></div></div>
  <aside class="summary"><h2>Booking summary</h2><div><span>Service</span><strong>30-minute consultation</strong></div><div><span>Consultant</span><strong>Gautam Khandelwal</strong></div><div><span>Experience</span><strong>Over 26 years in naturopathy</strong></div><div><span>Amount</span><strong>Rs. 500</strong></div><div><span>Next step</span><strong>Calendar booking after payment</strong></div><p>Your booking amount confirms seriousness and helps the team prepare your case before the call.</p></aside>
</section>
</main>
<?php get_footer(); ?>