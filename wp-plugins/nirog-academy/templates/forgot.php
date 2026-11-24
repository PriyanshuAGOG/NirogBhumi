<?php
/**
 * Forgotten password.
 */
use NirogBhumi\Academy\Frontend\Router;

defined('ABSPATH') || exit;
?>
<section class="nba-auth">
  <h1><?php esc_html_e('Forgot your password?', 'nirog-academy'); ?></h1>
  <p class="nba-muted"><?php esc_html_e('Enter the email you used to join. We will send you a link to choose a new password.', 'nirog-academy'); ?></p>
  <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('forgot')); ?>">
    <input type="hidden" name="nba_action" value="forgot">
    <?php wp_nonce_field('nba_forgot', '_nbanonce'); ?>
    <label for="nba-forgot-email"><?php esc_html_e('Email', 'nirog-academy'); ?></label>
    <input id="nba-forgot-email" type="email" name="email" autocomplete="email" required autofocus>
    <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Send reset link', 'nirog-academy'); ?></button>
  </form>
  <p class="nba-center"><a href="<?php echo esc_url(Router::url('login')); ?>">← <?php esc_html_e('Back to log in', 'nirog-academy'); ?></a></p>
</section>
