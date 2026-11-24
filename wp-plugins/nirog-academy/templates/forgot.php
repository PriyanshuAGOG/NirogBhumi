<?php
/**
 * Forgotten password.
 */
use NirogBhumi\Academy\Frontend\Router;

defined('ABSPATH') || exit;
?>
<div class="nba-wrap nba-page">
  <div class="nba-split">
    <section class="nba-split-intro">
      <span class="nba-eyebrow"><?php esc_html_e('Password', 'nirog-academy'); ?></span>
      <h1 class="nba-title"><?php echo wp_kses(__('Forgot your <em>password</em>?', 'nirog-academy'), ['em' => []]); ?></h1>
      <p class="nba-lead"><?php esc_html_e('Enter the email you joined with. We will send you a link to set a new password.', 'nirog-academy'); ?></p>
    </section>
    <section class="nba-panel">
      <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('forgot')); ?>">
        <input type="hidden" name="nba_action" value="forgot">
        <?php wp_nonce_field('nba_forgot', '_nbanonce'); ?>
        <label for="nba-forgot-email"><?php esc_html_e('Email', 'nirog-academy'); ?></label>
        <input id="nba-forgot-email" type="email" name="email" autocomplete="email" required>
        <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Send the link', 'nirog-academy'); ?></button>
      </form>
      <a class="nba-link" href="<?php echo esc_url(Router::url('login')); ?>">← <?php esc_html_e('Back to log in', 'nirog-academy'); ?></a>
    </section>
  </div>
</div>
