<?php
/**
 * Choose a password (from the welcome or reset email).
 *
 * @var bool   $valid
 * @var string $key
 * @var string $login
 */
use NirogBhumi\Academy\Frontend\Router;

defined('ABSPATH') || exit;
?>
<div class="nba-wrap nba-page">
  <div class="nba-split">
    <section class="nba-split-intro">
      <span class="nba-eyebrow"><?php esc_html_e('Welcome', 'nirog-academy'); ?></span>
      <?php if ($valid) : ?>
        <h1 class="nba-title"><?php echo wp_kses(__('Choose your <em>password</em>', 'nirog-academy'), ['em' => []]); ?></h1>
        <p class="nba-lead"><?php esc_html_e('At least 8 characters. A short sentence you will remember works well.', 'nirog-academy'); ?></p>
      <?php else : ?>
        <h1 class="nba-title"><?php esc_html_e('This link has expired', 'nirog-academy'); ?></h1>
        <p class="nba-lead"><?php esc_html_e('Password links work once, for 24 hours. Ask for a new one.', 'nirog-academy'); ?></p>
      <?php endif; ?>
    </section>
    <section class="nba-panel">
      <?php if (!$valid) : ?>
        <a class="nba-btn nba-btn-block" href="<?php echo esc_url(Router::url('forgot')); ?>"><?php esc_html_e('Send me a new link', 'nirog-academy'); ?></a>
      <?php else : ?>
        <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('set-password')); ?>">
          <input type="hidden" name="nba_action" value="set_password">
          <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
          <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">
          <?php wp_nonce_field('nba_set_password', '_nbanonce'); ?>
          <label for="nba-pass1"><?php esc_html_e('New password', 'nirog-academy'); ?></label>
          <div class="nba-password">
            <input id="nba-pass1" type="password" name="pass1" autocomplete="new-password" minlength="8" required>
            <button type="button" class="nba-show-password" aria-controls="nba-pass1" data-show="<?php esc_attr_e('Show', 'nirog-academy'); ?>" data-hide="<?php esc_attr_e('Hide', 'nirog-academy'); ?>"><?php esc_html_e('Show', 'nirog-academy'); ?></button>
          </div>
          <label for="nba-pass2"><?php esc_html_e('Type it again', 'nirog-academy'); ?></label>
          <input id="nba-pass2" type="password" name="pass2" autocomplete="new-password" minlength="8" required>
          <button class="nba-btn nba-btn-block" type="submit"><?php esc_html_e('Save and continue', 'nirog-academy'); ?></button>
        </form>
        <p class="nba-meta"><?php esc_html_e('The phone or computer you continue on becomes your device for the programme.', 'nirog-academy'); ?></p>
      <?php endif; ?>
    </section>
  </div>
</div>
