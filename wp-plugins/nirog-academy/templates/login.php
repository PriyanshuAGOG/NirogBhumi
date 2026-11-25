<?php
/**
 * The front door: log in. Phone first — photo, a short welcome, the form,
 * then what is inside.
 *
 * @var string $redirect_to
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;

defined('ABSPATH') || exit;

$photo = (string) apply_filters('nba_landing_image', (string) nba()->settings->get('landing_image') ?: NBA_URL . 'assets/img/landing.jpg');
$programmes_url = (string) apply_filters('nba_programmes_url', home_url('/'));
?>
<div class="nba-wrap nba-landing">
  <figure class="nba-landing-photo">
    <img src="<?php echo esc_url($photo); ?>" alt="" width="900" height="600" fetchpriority="high" decoding="async">
  </figure>

  <section class="nba-landing-main">
    <span class="nba-eyebrow"><?php esc_html_e('Nirog Bhumi Courses', 'nirog-academy'); ?></span>
    <h1 class="nba-landing-title"><?php esc_html_e('Welcome back', 'nirog-academy'); ?></h1>
    <p class="nba-lead"><?php esc_html_e('Your lessons, booklets and live sessions, in one place. Log in to continue where you left off.', 'nirog-academy'); ?></p>

    <form method="post" class="nba-form nba-landing-form" action="<?php echo esc_url(Router::url('login')); ?>">
      <input type="hidden" name="nba_action" value="login">
      <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
      <?php wp_nonce_field('nba_login', '_nbanonce'); ?>
      <label for="nba-log"><?php esc_html_e('Email', 'nirog-academy'); ?></label>
      <input id="nba-log" type="text" name="log" autocomplete="username" inputmode="email" autocapitalize="off" spellcheck="false" required>
      <label for="nba-pwd"><?php esc_html_e('Password', 'nirog-academy'); ?></label>
      <div class="nba-password">
        <input id="nba-pwd" type="password" name="pwd" autocomplete="current-password" required>
        <button type="button" class="nba-show-password" aria-controls="nba-pwd" data-show="<?php esc_attr_e('Show', 'nirog-academy'); ?>" data-hide="<?php esc_attr_e('Hide', 'nirog-academy'); ?>"><?php esc_html_e('Show', 'nirog-academy'); ?></button>
      </div>
      <div class="nba-landing-row">
        <label class="nba-check"><input type="checkbox" name="rememberme" value="1" checked> <?php esc_html_e('Keep me logged in', 'nirog-academy'); ?></label>
        <a class="nba-link" href="<?php echo esc_url(Router::url('forgot')); ?>"><?php esc_html_e('Forgot password?', 'nirog-academy'); ?></a>
      </div>
      <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Log in', 'nirog-academy'); ?></button>
    </form>

    <details class="nba-landing-magic">
      <summary><?php esc_html_e('Log in without a password', 'nirog-academy'); ?></summary>
      <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('login')); ?>">
        <input type="hidden" name="nba_action" value="magic">
        <?php wp_nonce_field('nba_magic', '_nbanonce'); ?>
        <label for="nba-magic-email"><?php esc_html_e('We email you a link that logs you in once.', 'nirog-academy'); ?></label>
        <input id="nba-magic-email" type="email" name="email" autocomplete="email" placeholder="<?php esc_attr_e('Your email', 'nirog-academy'); ?>" required>
        <button class="nba-btn nba-btn-line nba-btn-block" type="submit"><?php esc_html_e('Email me a login link', 'nirog-academy'); ?></button>
      </form>
    </details>

    <p class="nba-meta nba-landing-device"><?php echo View::icon('device', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php esc_html_e('Your programme works on one device. The first device you log in from becomes your device.', 'nirog-academy'); ?>
      <a href="<?php echo esc_url(Router::url('help/your-device')); ?>"><?php esc_html_e('Learn more', 'nirog-academy'); ?></a></span></p>
  </section>

  <ul class="nba-landing-inside" aria-label="<?php esc_attr_e('Inside your course', 'nirog-academy'); ?>">
    <li><?php echo View::icon('play', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div><strong><?php esc_html_e('Recorded lessons', 'nirog-academy'); ?></strong><span><?php esc_html_e('Watch at your own pace. We remember where you stopped.', 'nirog-academy'); ?></span></div></li>
    <li><?php echo View::icon('book', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div><strong><?php esc_html_e('Weekly booklets', 'nirog-academy'); ?></strong><span><?php esc_html_e('Read them like a book, on your phone or computer.', 'nirog-academy'); ?></span></div></li>
    <li><?php echo View::icon('live', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div><strong><?php esc_html_e('Live sessions', 'nirog-academy'); ?></strong><span><?php esc_html_e('Practise and ask questions with our team on Google Meet.', 'nirog-academy'); ?></span></div></li>
  </ul>

  <p class="nba-landing-new"><?php esc_html_e('New to Nirog Bhumi?', 'nirog-academy'); ?> <a class="nba-link" href="<?php echo esc_url($programmes_url); ?>"><?php esc_html_e('See our programmes', 'nirog-academy'); ?> →</a></p>
</div>
