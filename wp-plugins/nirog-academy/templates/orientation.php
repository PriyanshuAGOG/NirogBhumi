<?php
/**
 * One-time health and safety note.
 *
 * @var WP_Post $programme
 * @var string  $text
 * @var int     $next
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;
?>
<div class="nba-wrap nba-page" style="max-width:860px">
  <span class="nba-eyebrow"><?php echo esc_html(I18n::field($programme->ID, 'title')); ?></span>
  <h1 class="nba-title"><?php echo wp_kses(__('Before your <em>first lesson</em>', 'nirog-academy'), ['em' => []]); ?></h1>
  <p class="nba-lead" style="margin-top:18px"><?php esc_html_e('Please read this once. It helps you practise safely.', 'nirog-academy'); ?></p>
  <div class="nba-ack nba-prose"><?php echo wp_kses_post(wpautop($text)); ?></div>
  <form method="post" class="nba-form" action="<?php echo esc_url(Router::url('orientation/' . $programme->post_name)); ?>">
    <input type="hidden" name="nba_action" value="ack">
    <input type="hidden" name="programme_id" value="<?php echo (int) $programme->ID; ?>">
    <input type="hidden" name="next" value="<?php echo (int) $next; ?>">
    <?php wp_nonce_field('nba_ack', '_nbanonce'); ?>
    <label class="nba-check"><input type="checkbox" name="accept" value="1" required> <?php esc_html_e('I have read this and I understand it.', 'nirog-academy'); ?></label>
    <div><button class="nba-btn" type="submit"><?php esc_html_e('Continue', 'nirog-academy'); ?></button></div>
  </form>
</div>
