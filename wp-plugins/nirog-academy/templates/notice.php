<?php
/**
 * A simple message page (locked lesson, not found, opening soon…).
 *
 * @var string     $icon
 * @var string     $heading
 * @var string     $message
 * @var array|null $action  ['label' => '', 'url' => '']
 * @var bool       $support
 */
use NirogBhumi\Academy\Frontend\View;

defined('ABSPATH') || exit;

$support_email = (string) nba()->settings->get('support_email');
$whatsapp = (string) nba()->settings->get('support_whatsapp');
?>
<section class="nba-card nba-empty">
  <?php echo View::icon($icon ?? 'help', 36); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <h1><?php echo esc_html($heading); ?></h1>
  <p><?php echo esc_html($message); ?></p>
  <?php if (!empty($action)) : ?>
    <a class="nba-btn" href="<?php echo esc_url($action['url']); ?>"><?php echo esc_html($action['label']); ?></a>
  <?php endif; ?>
  <?php if (!empty($support)) : ?>
    <p class="nba-muted nba-support-line">
      <?php esc_html_e('Questions?', 'nirog-academy'); ?>
      <?php if ($support_email) : ?><a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a><?php endif; ?>
      <?php if ($whatsapp) : ?> · <a href="<?php echo esc_url('https://wa.me/' . $whatsapp); ?>" rel="noopener" target="_blank">WhatsApp</a><?php endif; ?>
    </p>
  <?php endif; ?>
</section>
