<?php
/**
 * Login.
 *
 * @var string $redirect_to
 */
use NirogBhumi\Academy\Frontend\Router;

defined('ABSPATH') || exit;
?>
<section class="nba-auth">
  <h1><?php esc_html_e('Welcome back', 'nirog-academy'); ?></h1>
  <p class="nba-muted"><?php esc_html_e('Log in to continue your programme.', 'nirog-academy'); ?></p>

  <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('login')); ?>">
    <input type="hidden" name="nba_action" value="login">
    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
    <?php wp_nonce_field('nba_login', '_nbanonce'); ?>
    <label for="nba-log"><?php esc_html_e('Email', 'nirog-academy'); ?></label>
    <input id="nba-log" type="text" name="log" autocomplete="username" inputmode="email" required autofocus>
    <label for="nba-pwd"><?php esc_html_e('Password', 'nirog-academy'); ?></label>
    <div class="nba-password">
      <input id="nba-pwd" type="password" name="pwd" autocomplete="current-password" required>
      <button type="button" class="nba-show-password" aria-controls="nba-pwd" data-show="<?php esc_attr_e('Show', 'nirog-academy'); ?>" data-hide="<?php esc_attr_e('Hide', 'nirog-academy'); ?>"><?php esc_html_e('Show', 'nirog-academy'); ?></button>
    </div>
    <label class="nba-check"><input type="checkbox" name="rememberme" value="1" checked> <?php esc_html_e('Keep me logged in', 'nirog-academy'); ?></label>
    <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Log in', 'nirog-academy'); ?></button>
  </form>

  <p class="nba-center"><a href="<?php echo esc_url(Router::url('forgot')); ?>"><?php esc_html_e('Forgot password?', 'nirog-academy'); ?></a></p>

  <div class="nba-divider"><span><?php esc_html_e('or', 'nirog-academy'); ?></span></div>

  <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('login')); ?>">
    <input type="hidden" name="nba_action" value="magic">
    <?php wp_nonce_field('nba_magic', '_nbanonce'); ?>
    <label for="nba-magic-email"><?php esc_html_e('Log in without a password', 'nirog-academy'); ?></label>
    <input id="nba-magic-email" type="email" name="email" autocomplete="email" placeholder="<?php esc_attr_e('Your email', 'nirog-academy'); ?>" required>
    <button class="nba-btn nba-btn-quiet nba-btn-block" type="submit"><?php esc_html_e('Email me a login link', 'nirog-academy'); ?></button>
  </form>

  <p class="nba-muted nba-small"><?php esc_html_e('Your programme works on one device. The first device you log in from becomes your registered device.', 'nirog-academy'); ?>
    <a href="<?php echo esc_url(Router::url('help/your-device')); ?>"><?php esc_html_e('Learn more', 'nirog-academy'); ?></a></p>
</section>
