<?php
/**
 * Device screen: blocked state, self-service change, request to team.
 *
 * @var bool        $blocked
 * @var object[]    $devices
 * @var bool        $can_self_reset
 * @var int         $next_reset
 * @var object|null $pending
 * @var bool        $code_sent
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$registered = $devices[0] ?? null;
?>
<section class="nba-auth nba-device">
  <?php echo View::icon('device', 36); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <?php if ($blocked) : ?>
    <h1><?php esc_html_e('Your programme is on another device', 'nirog-academy'); ?></h1>
    <?php if ($registered) : ?>
      <p><?php echo esc_html(sprintf(
        /* translators: 1: device label, 2: date */
        __('It is registered to %1$s (since %2$s). Each programme works on one device, so it stays private to you.', 'nirog-academy'),
        $registered->label,
        Time::human($registered->registered_at, 'j M Y')
      )); ?></p>
    <?php endif; ?>
    <p class="nba-muted"><?php esc_html_e('This can also happen if you cleared your browser data, used a private window, or switched browsers on the same phone.', 'nirog-academy'); ?></p>
  <?php else : ?>
    <h1><?php esc_html_e('Your device', 'nirog-academy'); ?></h1>
    <p><?php esc_html_e('This device is registered for your programme. Everything is working.', 'nirog-academy'); ?></p>
    <a class="nba-btn nba-btn-block" href="<?php echo esc_url(Router::url()); ?>"><?php esc_html_e('Go to dashboard', 'nirog-academy'); ?></a>
  <?php endif; ?>

  <?php if ($blocked) : ?>
    <?php if ($pending) : ?>
      <div class="nba-flash nba-flash-ok"><?php echo esc_html(sprintf(
        /* translators: %s: date */
        __('Your request from %s is with our team. We will email you, usually within one working day.', 'nirog-academy'),
        Time::human($pending->created_at, 'j M')
      )); ?></div>
    <?php elseif ($can_self_reset) : ?>
      <h2><?php esc_html_e('Use this device instead', 'nirog-academy'); ?></h2>
      <?php if (!$code_sent) : ?>
        <p class="nba-muted"><?php esc_html_e('We will email you a 6-digit code. Your other device will be signed out.', 'nirog-academy'); ?></p>
        <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('device')); ?>">
          <input type="hidden" name="nba_action" value="device_code">
          <?php wp_nonce_field('nba_device_code', '_nbanonce'); ?>
          <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Email me a code', 'nirog-academy'); ?></button>
        </form>
      <?php else : ?>
        <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('device')); ?>">
          <input type="hidden" name="nba_action" value="device_confirm">
          <?php wp_nonce_field('nba_device_confirm', '_nbanonce'); ?>
          <label for="nba-code"><?php esc_html_e('6-digit code from the email', 'nirog-academy'); ?></label>
          <input id="nba-code" class="nba-code-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus>
          <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Confirm this device', 'nirog-academy'); ?></button>
        </form>
        <form method="post" action="<?php echo esc_url(Router::url('device')); ?>">
          <input type="hidden" name="nba_action" value="device_code">
          <?php wp_nonce_field('nba_device_code', '_nbanonce'); ?>
          <button class="nba-linkbtn" type="submit"><?php esc_html_e('Send a new code', 'nirog-academy'); ?></button>
        </form>
      <?php endif; ?>
      <p class="nba-muted nba-small"><?php echo esc_html(sprintf(
        /* translators: %d: days */
        __('You can change device yourself once every %d days.', 'nirog-academy'),
        nba()->settings->int('self_reset_interval_days')
      )); ?></p>
    <?php else : ?>
      <h2><?php esc_html_e('Ask our team to move your programme', 'nirog-academy'); ?></h2>
      <?php if (nba()->settings->int('self_reset_interval_days') > 0) : ?>
        <p class="nba-muted"><?php echo esc_html(sprintf(
          /* translators: %s: date */
          __('You changed device recently. You can do it yourself again from %s, or ask us now.', 'nirog-academy'),
          wp_date('j M', $next_reset)
        )); ?></p>
      <?php endif; ?>
      <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('device')); ?>">
        <input type="hidden" name="nba_action" value="device_request">
        <?php wp_nonce_field('nba_device_request', '_nbanonce'); ?>
        <label for="nba-reason"><?php esc_html_e('What happened? (optional)', 'nirog-academy'); ?></label>
        <textarea id="nba-reason" name="reason" rows="3" placeholder="<?php esc_attr_e('For example: my phone was replaced', 'nirog-academy'); ?>"></textarea>
        <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Send request', 'nirog-academy'); ?></button>
      </form>
    <?php endif; ?>
    <p class="nba-center nba-small"><a href="<?php echo esc_url(Router::url('help/your-device')); ?>"><?php esc_html_e('How the one-device rule works', 'nirog-academy'); ?></a> ·
      <a href="<?php echo esc_url(wp_nonce_url(Router::url('logout'), 'nba_logout')); ?>"><?php esc_html_e('Log out', 'nirog-academy'); ?></a></p>
  <?php endif; ?>
</section>
