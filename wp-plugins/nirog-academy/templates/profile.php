<?php
/**
 * Account.
 *
 * @var WP_User     $user
 * @var string      $phone
 * @var array       $prefs
 * @var object[]    $devices
 * @var object|null $this_device
 * @var object[]    $enrollments
 * @var string      $orders_url
 * @var string      $locale
 */
use NirogBhumi\Academy\Enrollment\Enrollments;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;
?>
<div class="nba-wrap nba-page">
  <header class="nba-pagehead">
    <span class="nba-eyebrow"><?php esc_html_e('Account', 'nirog-academy'); ?></span>
    <h1 class="nba-title"><?php echo esc_html($user->display_name); ?></h1>
    <p class="nba-lead"><?php echo esc_html($user->user_email); ?></p>
  </header>

  <div class="nba-account">
    <div>
      <section class="nba-account-block">
        <h2 class="nba-h3"><?php esc_html_e('Your details', 'nirog-academy'); ?></h2>
        <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('profile')); ?>">
          <input type="hidden" name="nba_action" value="profile">
          <?php wp_nonce_field('nba_profile', '_nbanonce'); ?>
          <div class="nba-row2">
            <div><label for="nba-first"><?php esc_html_e('First name', 'nirog-academy'); ?></label><input id="nba-first" type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" autocomplete="given-name"></div>
            <div><label for="nba-last"><?php esc_html_e('Last name', 'nirog-academy'); ?></label><input id="nba-last" type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" autocomplete="family-name"></div>
          </div>
          <label for="nba-phone"><?php esc_html_e('Phone', 'nirog-academy'); ?></label>
          <input id="nba-phone" type="tel" name="phone" value="<?php echo esc_attr($phone); ?>" autocomplete="tel">
          <label for="nba-locale"><?php esc_html_e('Language', 'nirog-academy'); ?></label>
          <select id="nba-locale" name="locale">
            <?php foreach (I18n::LOCALES as $code => $label) : ?>
              <option value="<?php echo esc_attr($code); ?>"<?php selected($locale, $code); ?>><?php echo esc_html($code === 'hi_IN' ? 'हिंदी' : 'English'); ?></option>
            <?php endforeach; ?>
          </select>
          <fieldset>
            <legend><?php esc_html_e('Email me about', 'nirog-academy'); ?></legend>
            <label class="nba-check"><input type="checkbox" name="pref_lessons" value="1"<?php checked(!empty($prefs['lessons'])); ?>> <?php esc_html_e('New lessons', 'nirog-academy'); ?></label>
            <label class="nba-check"><input type="checkbox" name="pref_reminders" value="1"<?php checked(!empty($prefs['reminders'])); ?>> <?php esc_html_e('Reminders and live sessions', 'nirog-academy'); ?></label>
            <label class="nba-check"><input type="checkbox" name="pref_announcements" value="1"<?php checked(!empty($prefs['announcements'])); ?>> <?php esc_html_e('Messages from the team', 'nirog-academy'); ?></label>
            <p class="nba-meta"><?php esc_html_e('Emails about your account and security are always sent.', 'nirog-academy'); ?></p>
          </fieldset>
          <div><button class="nba-btn" type="submit"><?php esc_html_e('Save', 'nirog-academy'); ?></button></div>
        </form>
      </section>
    </div>

    <div>
      <section class="nba-account-block">
        <h2 class="nba-h3"><?php esc_html_e('My programmes', 'nirog-academy'); ?></h2>
        <?php foreach ($enrollments as $e) : ?>
          <div class="nba-kv"><span><?php echo esc_html(I18n::field((int) $e->programme_id, 'title')); ?></span><span class="nba-muted"><?php echo esc_html(Enrollments::status_label($e->status)); ?><?php echo !empty($e->ends_at) ? ' · ' . esc_html(sprintf(
            /* translators: %s: date */
            __('until %s', 'nirog-academy'),
            Time::human($e->ends_at, 'j M Y')
          )) : ''; ?></span></div>
        <?php endforeach; ?>
        <?php if ($orders_url) : ?><p style="margin-top:14px"><a class="nba-link" href="<?php echo esc_url($orders_url); ?>"><?php esc_html_e('Orders and invoices', 'nirog-academy'); ?></a></p><?php endif; ?>
      </section>

      <section class="nba-account-block">
        <h2 class="nba-h3"><?php esc_html_e('My device', 'nirog-academy'); ?></h2>
        <?php if (!$devices) : ?>
          <p class="nba-muted"><?php esc_html_e('Staff accounts are not tied to a device.', 'nirog-academy'); ?></p>
        <?php endif; ?>
        <?php foreach ($devices as $d) : ?>
          <div class="nba-kv"><span><?php echo esc_html($d->label); ?><?php echo $this_device && (int) $this_device->id === (int) $d->id ? ' · ' . esc_html__('this device', 'nirog-academy') : ''; ?></span><span class="nba-muted"><?php echo esc_html(sprintf(
            /* translators: %s: date */
            __('since %s', 'nirog-academy'),
            Time::human($d->registered_at, 'j M Y')
          )); ?></span></div>
        <?php endforeach; ?>
        <p style="margin-top:14px"><a class="nba-link" href="<?php echo esc_url(Router::url('help/your-device')); ?>"><?php esc_html_e('How to change your device', 'nirog-academy'); ?></a></p>
      </section>

      <section class="nba-account-block">
        <h2 class="nba-h3"><?php esc_html_e('Password', 'nirog-academy'); ?></h2>
        <p class="nba-muted"><?php esc_html_e('We will email you a safe link to set a new password.', 'nirog-academy'); ?></p>
        <form method="post" action="<?php echo esc_url(Router::url('forgot')); ?>">
          <input type="hidden" name="nba_action" value="forgot">
          <input type="hidden" name="email" value="<?php echo esc_attr($user->user_email); ?>">
          <?php wp_nonce_field('nba_forgot', '_nbanonce'); ?>
          <button class="nba-btn nba-btn-line" type="submit"><?php esc_html_e('Change password', 'nirog-academy'); ?></button>
        </form>
      </section>

      <section class="nba-account-block">
        <a class="nba-link" href="<?php echo esc_url(wp_nonce_url(Router::url('logout'), 'nba_logout')); ?>"><?php esc_html_e('Log out', 'nirog-academy'); ?></a>
      </section>
    </div>
  </div>
</div>
