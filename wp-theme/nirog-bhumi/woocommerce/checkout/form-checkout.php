<?php defined('ABSPATH') || exit; ?>
<?php do_action('woocommerce_before_checkout_form', $checkout); ?>
<?php if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) : ?>
  <?php echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('You must be logged in to checkout.', 'woocommerce'))); ?>
  <?php return; ?>
<?php endif; ?>
<?php $consultation_only = function_exists('nirog_bhumi_cart_is_consultation_only') && nirog_bhumi_cart_is_consultation_only(); ?>
<form name="checkout" method="post" class="checkout woocommerce-checkout commerce-checkout<?php echo $consultation_only ? ' consultation-only-checkout' : ''; ?>" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
  <div class="checkout-head">
    <p class="eyebrow"><?php esc_html_e('Checkout', 'nirog-bhumi'); ?></p>
    <h1><?php echo esc_html($consultation_only ? __('Complete your consultation booking.', 'nirog-bhumi') : __('Secure checkout.', 'nirog-bhumi')); ?></h1>
    <p><?php echo esc_html($consultation_only ? __('Confirm your contact details and pay the Rs. 590 consultation amount.', 'nirog-bhumi') : __('Confirm contact, billing, delivery, order notes and payment details before placing your Nirog Bhumi order.', 'nirog-bhumi')); ?></p>
  </div>
  <div class="checkout-flow">
    <?php if ($checkout->get_checkout_fields()) : ?>
      <?php do_action('woocommerce_checkout_before_customer_details'); ?>
      <section class="checkout-panel" id="customer_details">
        <div class="panel-title"><span>01</span><h2><?php echo esc_html($consultation_only ? __('Contact details', 'nirog-bhumi') : __('Billing details', 'nirog-bhumi')); ?></h2></div>
        <div class="field-grid two">
          <?php foreach ($checkout->get_checkout_fields('billing') as $key => $field) { woocommerce_form_field($key, $field, $checkout->get_value($key)); } ?>
        </div>
      </section>
      <?php if (WC()->cart && WC()->cart->needs_shipping_address()) : ?>
      <section class="checkout-panel">
        <div class="panel-title"><span>02</span><h2><?php esc_html_e('Shipping details', 'nirog-bhumi'); ?></h2></div>
        <?php do_action('woocommerce_checkout_shipping'); ?>
      </section>
      <?php endif; ?>
      <?php if (!$consultation_only) : ?><section class="checkout-panel">
        <div class="panel-title"><span>03</span><h2><?php esc_html_e('Additional information', 'nirog-bhumi'); ?></h2></div>
        <?php foreach ($checkout->get_checkout_fields('order') as $key => $field) { woocommerce_form_field($key, $field, $checkout->get_value($key)); } ?>
        <label class="check"><input required type="checkbox" name="nb_medical_acknowledgement"> <?php esc_html_e('I understand Nirog Bhumi products and programs support wellness routines and do not replace medical care.', 'nirog-bhumi'); ?></label>
      </section><?php endif; ?>
      <?php do_action('woocommerce_checkout_after_customer_details'); ?>
    <?php endif; ?>
  </div>
  <aside class="checkout-summary">
    <div class="summary-card">
      <span><?php esc_html_e('Order review', 'nirog-bhumi'); ?></span>
      <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>
      <h2 id="order_review_heading"><?php esc_html_e('Your order', 'woocommerce'); ?></h2>
      <?php do_action('woocommerce_checkout_before_order_review'); ?>
      <div id="order_review" class="woocommerce-checkout-review-order">
        <?php do_action('woocommerce_checkout_order_review'); ?>
      </div>
      <?php do_action('woocommerce_checkout_after_order_review'); ?>
    </div>
  </aside>
</form>
<?php do_action('woocommerce_after_checkout_form', $checkout); ?>