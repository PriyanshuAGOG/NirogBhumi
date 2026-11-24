<?php
/**
 * One Help Center article.
 *
 * @var array  $doc
 * @var string $html
 */
use NirogBhumi\Academy\Frontend\Router;

defined('ABSPATH') || exit;
?>
<article class="nba-wrap nba-page">
  <a class="nba-crumb" href="<?php echo esc_url(Router::url('help')); ?>">← <?php esc_html_e('Help', 'nirog-academy'); ?></a>
  <header class="nba-pagehead">
    <h1 class="nba-title"><?php echo esc_html($doc['title']); ?></h1>
    <?php if ($doc['summary']) : ?><p class="nba-lead"><?php echo esc_html($doc['summary']); ?></p><?php endif; ?>
  </header>
  <div class="nba-prose"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passed through wp_kses_post in Markdown::to_html(). ?></div>
</article>
