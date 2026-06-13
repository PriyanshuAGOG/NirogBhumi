<?php defined('ABSPATH') || exit; ?>
<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_attr_e('Account pages', 'woocommerce'); ?>">
  <?php foreach (wc_get_account_menu_items() as $endpoint => $label) : ?>
    <a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>" class="<?php echo wc_get_account_menu_item_classes($endpoint); ?>"><?php echo esc_html($label); ?></a>
  <?php endforeach; ?>
</nav>