<?php
/**
 * Help Center index.
 *
 * @var array $docs
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;

defined('ABSPATH') || exit;

$support_email = (string) nba()->settings->get('support_email');
$whatsapp = (string) nba()->settings->get('support_whatsapp');
?>
<header class="nba-page-head">
  <h1><?php esc_html_e('Help Center', 'nirog-academy'); ?></h1>
  <p class="nba-lead"><?php esc_html_e('Short guides to common questions.', 'nirog-academy'); ?></p>
  <input type="search" class="nba-help-search" placeholder="<?php esc_attr_e('Search the guides', 'nirog-academy'); ?>" aria-label="<?php esc_attr_e('Search the guides', 'nirog-academy'); ?>">
</header>
<div class="nba-tiles nba-help-list">
  <?php foreach ($docs as $doc) : ?>
    <a class="nba-tile nba-help-item" href="<?php echo esc_url(Router::url('help/' . $doc['slug'])); ?>" data-search="<?php echo esc_attr(mb_strtolower($doc['title'] . ' ' . $doc['summary'])); ?>">
      <span class="nba-tile-title"><?php echo esc_html($doc['title']); ?></span>
      <span class="nba-muted"><?php echo esc_html($doc['summary']); ?></span>
    </a>
  <?php endforeach; ?>
</div>
<section class="nba-card nba-card-soft">
  <h2 class="nba-card-title"><?php echo View::icon('help', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Still stuck?', 'nirog-academy'); ?></h2>
  <p><?php esc_html_e('Write to us and tell us what you were trying to do. A screenshot helps.', 'nirog-academy'); ?></p>
  <p>
    <?php if ($support_email) : ?><a class="nba-btn nba-btn-quiet" href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a><?php endif; ?>
    <?php if ($whatsapp) : ?><a class="nba-btn nba-btn-quiet" href="<?php echo esc_url('https://wa.me/' . $whatsapp); ?>" target="_blank" rel="noopener"><?php esc_html_e('Chat on WhatsApp', 'nirog-academy'); ?></a><?php endif; ?>
  </p>
</section>
