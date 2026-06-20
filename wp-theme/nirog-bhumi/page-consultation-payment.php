<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-payment.html.
 */
get_header(); ?>
<main>
<section class="consultation-checkout">
  <div class="payment-card"><span>Consultation booking</span><h1>Rs. 500</h1><p>Confirm your 30-minute consultation with founder Gautam Khandelwal, who brings over 26 years of personal practice, study, and lived experience in naturopathy and natural healing.</p><div class="payment-content-slot" data-payment-content><?php $entry_id = function_exists('nirog_bhumi_consultation_cookie_entry') ? nirog_bhumi_consultation_cookie_entry() : 0; if (function_exists('nirog_bhumi_render_consultation_payment_actions')) { echo nirog_bhumi_render_consultation_payment_actions($entry_id); } $edit_url = function_exists('nirog_bhumi_consultation_edit_url') ? nirog_bhumi_consultation_edit_url() : home_url('/consultation/#consultation-form'); ?><a class="payment-edit-link" href="<?php echo esc_url($edit_url); ?>">Edit response</a></div></div>
  <aside class="summary"><h2>Booking summary</h2><div><span>Service</span><strong>30-minute consultation</strong></div><div><span>Consultant</span><strong>Gautam Khandelwal</strong></div><div><span>Experience</span><strong>Over 26 years in naturopathy</strong></div><div><span>Amount</span><strong>Rs. 500</strong></div><div><span>Confirmation</span><strong>Shared personally by our team</strong></div><p>Your booking amount confirms your session and helps the team prepare your case before the call.</p></aside>
</section>
</main>
<?php get_footer(); ?>