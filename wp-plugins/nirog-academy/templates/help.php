<?php
/**
 * Help Center.
 *
 * @var array $docs
 */
use NirogBhumi\Academy\Frontend\Router;

defined('ABSPATH') || exit;

$support_email = (string) nba()->settings->get('support_email');
$whatsapp = (string) nba()->settings->get('support_whatsapp');
?>
<div class="nba-wrap nba-page">
  <header class="nba-pagehead" style="border-bottom:0">
    <span class="nba-eyebrow"><?php esc_html_e('Help', 'nirog-academy'); ?></span>
    <h1 class="nba-title"><?php echo wp_kses(__('How can we <em>help</em>?', 'nirog-academy'), ['em' => []]); ?></h1>
    <input type="search" class="nba-help-search" placeholder="<?php esc_attr_e('Search the guides', 'nirog-academy'); ?>" aria-label="<?php esc_attr_e('Search the guides', 'nirog-academy'); ?>">
  </header>
  <ul class="nba-list">
    <?php $i = 0; foreach ($docs as $doc) : $i++; ?>
      <li class="nba-help-item" data-search="<?php echo esc_attr(mb_strtolower($doc['title'] . ' ' . $doc['summary'])); ?>">
        <a class="nba-list-row" href="<?php echo esc_url(Router::url('help/' . $doc['slug'])); ?>">
          <span class="nba-item-no"><?php echo esc_html(str_pad((string) $i, 2, '0', STR_PAD_LEFT)); ?></span>
          <span><span class="nba-h3" style="display:block"><?php echo esc_html($doc['title']); ?></span><p><?php echo esc_html($doc['summary']); ?></p></span>
          <span aria-hidden="true">→</span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <section class="nba-contact">
    <div>
      <h2 class="nba-h2"><?php esc_html_e('Still stuck?', 'nirog-academy'); ?></h2>
      <p class="nba-muted"><?php esc_html_e('Write to us and tell us what you were trying to do. A screenshot helps.', 'nirog-academy'); ?></p>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <?php if ($support_email) : ?><a class="nba-btn nba-btn-line" href="mailto:<?php echo esc_attr($support_email); ?>"><?php esc_html_e('Email us', 'nirog-academy'); ?></a><?php endif; ?>
      <?php if ($whatsapp) : ?><a class="nba-btn" href="<?php echo esc_url('https://wa.me/' . $whatsapp); ?>" target="_blank" rel="noopener"><?php esc_html_e('Chat on WhatsApp', 'nirog-academy'); ?></a><?php endif; ?>
    </div>
  </section>
</div>
