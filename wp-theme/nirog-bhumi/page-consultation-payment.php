<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-payment.html.
 */
get_header(); ?>
<main>
<section class="consultation-checkout minimal-consultation-payment">
  <div class="payment-card"><h1>Consultation Payment</h1><div class="payment-amount"><strong>Rs. 500</strong><small>+ GST</small></div><div class="payment-content-slot" data-payment-content><?php $entry_id = function_exists('nirog_bhumi_consultation_cookie_entry') ? nirog_bhumi_consultation_cookie_entry() : 0; if (function_exists('nirog_bhumi_render_consultation_payment_actions')) { echo nirog_bhumi_render_consultation_payment_actions($entry_id); } $edit_url = function_exists('nirog_bhumi_consultation_edit_url') ? nirog_bhumi_consultation_edit_url() : home_url('/consultation/#consultation-form'); ?><a class="payment-edit-link" href="<?php echo esc_url($edit_url); ?>">Edit response</a></div></div>
</section>
</main>
<?php get_footer(); ?>