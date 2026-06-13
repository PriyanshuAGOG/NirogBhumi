<?php defined('ABSPATH') || exit; do_action('woocommerce_before_customer_login_form'); ?>
<section class="account-portal logged-out-account">
  <aside class="account-sidebar"><p class="eyebrow"><?php esc_html_e('My account', 'nirog-bhumi'); ?></p><h1><?php esc_html_e('Access your Nirog Bhumi space.', 'nirog-bhumi'); ?></h1><p><?php esc_html_e('Login or create an account to track orders, addresses, downloads and consultation details.', 'nirog-bhumi'); ?></p></aside>
  <main class="account-main account-login-grid">
    <form class="woocommerce-form woocommerce-form-login login account-login-card" method="post">
      <p class="eyebrow"><?php esc_html_e('Login', 'woocommerce'); ?></p><h2><?php esc_html_e('Welcome back.', 'nirog-bhumi'); ?></h2>
      <?php do_action('woocommerce_login_form_start'); ?>
      <label><?php esc_html_e('Username or email address', 'woocommerce'); ?><input type="text" class="woocommerce-Input input-text" name="username" autocomplete="username" required></label>
      <label><?php esc_html_e('Password', 'woocommerce'); ?><input class="woocommerce-Input input-text" type="password" name="password" autocomplete="current-password" required></label>
      <?php do_action('woocommerce_login_form'); ?>
      <label class="check"><input name="rememberme" type="checkbox" value="forever"> <?php esc_html_e('Remember me', 'woocommerce'); ?></label>
      <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
      <button type="submit" class="pill primary" name="login" value="<?php esc_attr_e('Log in', 'woocommerce'); ?>"><?php esc_html_e('Log in', 'woocommerce'); ?></button>
      <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Lost your password?', 'woocommerce'); ?></a>
      <?php do_action('woocommerce_login_form_end'); ?>
    </form>
    <?php if ('yes' === get_option('woocommerce_enable_myaccount_registration')) : ?>
    <form method="post" class="woocommerce-form woocommerce-form-register register account-login-card">
      <p class="eyebrow"><?php esc_html_e('New account', 'nirog-bhumi'); ?></p><h2><?php esc_html_e('Create access.', 'nirog-bhumi'); ?></h2>
      <?php do_action('woocommerce_register_form_start'); ?>
      <label><?php esc_html_e('Email address', 'woocommerce'); ?><input type="email" class="woocommerce-Input input-text" name="email" autocomplete="email" required></label>
      <?php do_action('woocommerce_register_form'); ?>
      <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
      <button type="submit" class="pill ghost" name="register" value="<?php esc_attr_e('Register', 'woocommerce'); ?>"><?php esc_html_e('Register', 'woocommerce'); ?></button>
      <?php do_action('woocommerce_register_form_end'); ?>
    </form>
    <?php endif; ?>
  </main>
</section>
<?php do_action('woocommerce_after_customer_login_form'); ?>