<?php
/**
 * One-time health and safety acknowledgement.
 *
 * @var WP_Post $programme
 * @var string  $text
 * @var int     $next
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;
?>
<section class="nba-card nba-orientation">
  <?php echo View::icon('alert', 32); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <p class="nba-eyebrow"><?php echo esc_html(I18n::field($programme->ID, 'title')); ?></p>
  <h1><?php esc_html_e('Before your first lesson', 'nirog-academy'); ?></h1>
  <div class="nba-prose nba-ack-text"><?php echo wp_kses_post(wpautop($text)); ?></div>
  <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('orientation/' . $programme->post_name)); ?>">
    <input type="hidden" name="nba_action" value="ack">
    <input type="hidden" name="programme_id" value="<?php echo (int) $programme->ID; ?>">
    <input type="hidden" name="next" value="<?php echo (int) $next; ?>">
    <?php wp_nonce_field('nba_ack', '_nbanonce'); ?>
    <label class="nba-check"><input type="checkbox" name="accept" value="1" required> <?php esc_html_e('I have read and understood this note.', 'nirog-academy'); ?></label>
    <button class="nba-btn" type="submit"><?php esc_html_e('Continue to the lesson', 'nirog-academy'); ?></button>
  </form>
</section>
