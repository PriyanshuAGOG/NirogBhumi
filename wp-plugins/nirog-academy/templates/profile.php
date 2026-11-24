<?php
/**
 * Profile and settings.
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
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;
?>
<header class="nba-page-head">
  <h1><?php esc_html_e('Profile', 'nirog-academy'); ?></h1>
  <p class="nba-muted"><?php echo esc_html($user->user_email); ?></p>
</header>

<div class="nba-grid nba-grid-2">
  <section class="nba-card">
    <h2 class="nba-card-title"><?php esc_html_e('Your details', 'nirog-academy'); ?></h2>
    <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('profile')); ?>">
      <input type="hidden" name="nba_action" value="profile">
      <?php wp_nonce_field('nba_profile', '_nbanonce'); ?>
      <div class="nba-row">
        <div><label for="nba-first"><?php esc_html_e('First name', 'nirog-academy'); ?></label><input id="nba-first" type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" autocomplete="given-name"></div>
        <div><label for="nba-last"><?php esc_html_e('Last name', 'nirog-academy'); ?></label><input id="nba-last" type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" autocomplete="family-name"></div>
      </div>
      <label for="nba-phone"><?php esc_html_e('Phone', 'nirog-academy'); ?></label>
      <input id="nba-phone" type="tel" name="phone" value="<?php echo esc_attr($phone); ?>" autocomplete="tel">
      <label for="nba-locale"><?php esc_html_e('Language', 'nirog-academy'); ?></label>
      <select id="nba-locale" name="locale">
        <?php foreach (I18n::LOCALES as $code => $label) : ?>
          <option value="<?php echo esc_attr($code); ?>"<?php selected($locale, $code); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
      <fieldset>
        <legend><?php esc_html_e('Email me about', 'nirog-academy'); ?></legend>
        <label class="nba-check"><input type="checkbox" name="pref_lessons" value="1"<?php checked(!empty($prefs['lessons'])); ?>> <?php esc_html_e('New lessons', 'nirog-academy'); ?></label>
        <label class="nba-check"><input type="checkbox" name="pref_reminders" value="1"<?php checked(!empty($prefs['reminders'])); ?>> <?php esc_html_e('Reminders and live sessions', 'nirog-academy'); ?></label>
        <label class="nba-check"><input type="checkbox" name="pref_announcements" value="1"<?php checked(!empty($prefs['announcements'])); ?>> <?php esc_html_e('Announcements from the team', 'nirog-academy'); ?></label>
        <p class="nba-muted nba-small"><?php esc_html_e('Account and security emails are always sent.', 'nirog-academy'); ?></p>
      </fieldset>
      <button class="nba-btn" type="submit"><?php esc_html_e('Save', 'nirog-academy'); ?></button>
    </form>
  </section>

  <div class="nba-stack">
    <section class="nba-card">
      <h2 class="nba-card-title"><?php echo View::icon('device', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('My device', 'nirog-academy'); ?></h2>
      <?php if (!$devices) : ?>
        <p class="nba-muted"><?php esc_html_e('Staff accounts are not tied to a device.', 'nirog-academy'); ?></p>
      <?php endif; ?>
      <?php foreach ($devices as $d) : ?>
        <p><strong><?php echo esc_html($d->label); ?></strong><?php echo $this_device && (int) $this_device->id === (int) $d->id ? ' · ' . esc_html__('this device', 'nirog-academy') : ''; ?><br>
          <span class="nba-muted"><?php echo esc_html(sprintf(
            /* translators: %s: date */
            __('Registered %s', 'nirog-academy'),
            Time::human($d->registered_at, 'j M Y')
          )); ?></span></p>
      <?php endforeach; ?>
      <a class="nba-link" href="<?php echo esc_url(Router::url('help/your-device')); ?>"><?php esc_html_e('Changing your device', 'nirog-academy'); ?> →</a>
    </section>

    <section class="nba-card">
      <h2 class="nba-card-title"><?php esc_html_e('My programmes', 'nirog-academy'); ?></h2>
      <ul class="nba-mini-list">
        <?php foreach ($enrollments as $e) : ?>
          <li><span><?php echo esc_html(I18n::field((int) $e->programme_id, 'title')); ?></span>
            <span class="nba-muted"><?php echo esc_html(Enrollments::status_label($e->status)); ?><?php echo !empty($e->ends_at) ? ' · ' . esc_html(sprintf(
              /* translators: %s: date */
              __('until %s', 'nirog-academy'),
              Time::human($e->ends_at, 'j M Y')
            )) : ''; ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($orders_url) : ?><a class="nba-link" href="<?php echo esc_url($orders_url); ?>"><?php esc_html_e('Orders and invoices', 'nirog-academy'); ?> →</a><?php endif; ?>
    </section>

    <section class="nba-card">
      <h2 class="nba-card-title"><?php esc_html_e('Password', 'nirog-academy'); ?></h2>
      <p class="nba-muted"><?php esc_html_e('We will email you a secure link to choose a new password.', 'nirog-academy'); ?></p>
      <form method="post" action="<?php echo esc_url(Router::url('forgot')); ?>">
        <input type="hidden" name="nba_action" value="forgot">
        <input type="hidden" name="email" value="<?php echo esc_attr($user->user_email); ?>">
        <?php wp_nonce_field('nba_forgot', '_nbanonce'); ?>
        <button class="nba-btn nba-btn-quiet" type="submit"><?php esc_html_e('Change password', 'nirog-academy'); ?></button>
      </form>
    </section>

    <a class="nba-btn nba-btn-quiet nba-logout-mobile" href="<?php echo esc_url(wp_nonce_url(Router::url('logout'), 'nba_logout')); ?>"><?php echo View::icon('logout', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Log out', 'nirog-academy'); ?></a>
  </div>
</div>
