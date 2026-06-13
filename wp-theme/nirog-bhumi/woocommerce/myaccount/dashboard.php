<?php defined('ABSPATH') || exit; ?>
<section class="account-hero-card">
  <div>
    <span><?php esc_html_e('Welcome', 'nirog-bhumi'); ?></span>
    <h2><?php printf(esc_html__('Hello %s', 'nirog-bhumi'), esc_html(wp_get_current_user()->display_name ?: wp_get_current_user()->user_login)); ?></h2>
    <p><?php esc_html_e('From your account dashboard you can view recent orders, manage addresses and payment methods, and return to your Nirog Bhumi resources.', 'nirog-bhumi'); ?></p>
  </div>
  <a class="pill primary" href="<?php echo esc_url(home_url('/consultation/')); ?>"><?php esc_html_e('Book Consultation', 'nirog-bhumi'); ?></a>
</section>
<section class="account-stats">
  <article><span><?php esc_html_e('Orders', 'nirog-bhumi'); ?></span><strong><?php echo esc_html(wc_get_customer_order_count(get_current_user_id())); ?></strong><p><?php esc_html_e('Completed orders linked to this account.', 'nirog-bhumi'); ?></p></article>
  <article><span><?php esc_html_e('Downloads', 'nirog-bhumi'); ?></span><strong><?php echo esc_html(count(wc_get_customer_available_downloads(get_current_user_id()))); ?></strong><p><?php esc_html_e('Program resources available to you.', 'nirog-bhumi'); ?></p></article>
  <article><span><?php esc_html_e('Support', 'nirog-bhumi'); ?></span><strong><?php esc_html_e('Ready', 'nirog-bhumi'); ?></strong><p><?php esc_html_e('Book consultation when you need guidance.', 'nirog-bhumi'); ?></p></article>
</section>
<?php do_action('woocommerce_account_dashboard'); ?>