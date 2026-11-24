<?php
/**
 * Device screen.
 *
 * @var bool        $blocked
 * @var object[]    $devices
 * @var bool        $can_self_reset
 * @var int         $next_reset
 * @var object|null $pending
 * @var bool        $code_sent
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$registered = $devices[0] ?? null;
?>
<div class="nba-wrap nba-page">
  <div class="nba-split">
    <section class="nba-split-intro">
      <span class="nba-eyebrow"><?php esc_html_e('Your device', 'nirog-academy'); ?></span>
      <?php if ($blocked) : ?>
        <h1 class="nba-title"><?php echo wp_kses(__('Your programme is on <em>another device</em>', 'nirog-academy'), ['em' => []]); ?></h1>
        <?php if ($registered) : ?>
          <p class="nba-lead"><?php echo esc_html(sprintf(
            /* translators: 1: device, 2: date */
            __('It is set up on %1$s (since %2$s). Each programme works on one device, so it stays only yours.', 'nirog-academy'),
            $registered->label,
            Time::human($registered->registered_at, 'j M Y')
          )); ?></p>
        <?php endif; ?>
        <p class="nba-muted"><?php esc_html_e('This also happens if you cleared your browser data, used a private window, or changed browser on the same phone.', 'nirog-academy'); ?></p>
      <?php else : ?>
        <h1 class="nba-title"><?php esc_html_e('All good on this device', 'nirog-academy'); ?></h1>
        <p class="nba-lead"><?php esc_html_e('This device is set up for your programme.', 'nirog-academy'); ?></p>
        <p><a class="nba-btn" href="<?php echo esc_url(Router::url()); ?>"><?php esc_html_e('Go to Today', 'nirog-academy'); ?></a></p>
      <?php endif; ?>
    </section>

    <?php if ($blocked) : ?>
      <section class="nba-panel">
        <?php if ($pending) : ?>
          <span class="nba-eyebrow"><?php esc_html_e('Request sent', 'nirog-academy'); ?></span>
          <p><?php echo esc_html(sprintf(
            /* translators: %s: date */
            __('Your request from %s is with our team. We usually reply by email within one working day.', 'nirog-academy'),
            Time::human($pending->created_at, 'j M')
          )); ?></p>
        <?php elseif ($can_self_reset) : ?>
          <span class="nba-eyebrow"><?php esc_html_e('Use this device instead', 'nirog-academy'); ?></span>
          <?php if (!$code_sent) : ?>
            <p class="nba-muted"><?php esc_html_e('We will email you a 6-digit code. Your other device will be logged out.', 'nirog-academy'); ?></p>
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
              <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Use this device', 'nirog-academy'); ?></button>
            </form>
            <form method="post" action="<?php echo esc_url(Router::url('device')); ?>">
              <input type="hidden" name="nba_action" value="device_code">
              <?php wp_nonce_field('nba_device_code', '_nbanonce'); ?>
              <button class="nba-linkbtn" type="submit"><?php esc_html_e('Send a new code', 'nirog-academy'); ?></button>
            </form>
          <?php endif; ?>
          <p class="nba-meta"><?php echo esc_html(sprintf(
            /* translators: %d: days */
            __('You can change device yourself once every %d days.', 'nirog-academy'),
            nba()->settings->int('self_reset_interval_days')
          )); ?></p>
        <?php else : ?>
          <span class="nba-eyebrow"><?php esc_html_e('Ask our team', 'nirog-academy'); ?></span>
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
            <textarea id="nba-reason" name="reason" rows="3" placeholder="<?php esc_attr_e('For example: I got a new phone', 'nirog-academy'); ?>"></textarea>
            <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Send request', 'nirog-academy'); ?></button>
          </form>
        <?php endif; ?>
        <p class="nba-meta" style="margin-top:18px"><a href="<?php echo esc_url(Router::url('help/your-device')); ?>"><?php esc_html_e('How the one-device rule works', 'nirog-academy'); ?></a> ·
          <a href="<?php echo esc_url(wp_nonce_url(Router::url('logout'), 'nba_logout')); ?>"><?php esc_html_e('Log out', 'nirog-academy'); ?></a></p>
      </section>
    <?php endif; ?>
  </div>
</div>
