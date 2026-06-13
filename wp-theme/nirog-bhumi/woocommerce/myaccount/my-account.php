<?php defined('ABSPATH') || exit; ?>
<section class="account-portal woocommerce-account">
  <aside class="account-sidebar">
    <p class="eyebrow"><?php esc_html_e('My account', 'nirog-bhumi'); ?></p>
    <h1><?php esc_html_e('Your Nirog Bhumi space.', 'nirog-bhumi'); ?></h1>
    <p><?php esc_html_e('Manage orders, addresses, payment methods, program access and consultation notes in one calm dashboard.', 'nirog-bhumi'); ?></p>
    <?php do_action('woocommerce_account_navigation'); ?>
  </aside>
  <main class="account-main">
    <?php do_action('woocommerce_account_content'); ?>
  </main>
</section>