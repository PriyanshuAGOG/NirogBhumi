<?php defined('ABSPATH') || exit; ?>
<section class="commerce-empty-page cart-empty-page">
  <div class="commerce-empty-copy">
    <p class="eyebrow"><?php esc_html_e('Cart', 'nirog-bhumi'); ?></p>
    <h1><?php esc_html_e('Your cart is waiting.', 'nirog-bhumi'); ?></h1>
    <p><?php esc_html_e('Add the diabetes reversal kit, food rhythm products or individual wellness tools when you are ready.', 'nirog-bhumi'); ?></p>
    <div class="hero-buttons">
      <a class="pill primary" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Browse Store', 'nirog-bhumi'); ?></a>
      <a class="pill ghost" href="<?php echo esc_url(home_url('/consultation/')); ?>"><?php esc_html_e('Book Consultation', 'nirog-bhumi'); ?></a>
    </div>
  </div>
  <aside class="commerce-ready-panel">
    <span><?php esc_html_e('Cart will support', 'nirog-bhumi'); ?></span>
    <ul>
      <li><?php esc_html_e('Product quantity updates', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Coupon application', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Shipping and pickup preference', 'nirog-bhumi'); ?></li>
      <li><?php esc_html_e('Wellness acknowledgement before checkout', 'nirog-bhumi'); ?></li>
    </ul>
  </aside>
</section>