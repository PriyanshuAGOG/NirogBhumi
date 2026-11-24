<?php
/**
 * One Help Center article.
 *
 * @var array  $doc
 * @var string $html
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;

defined('ABSPATH') || exit;
?>
<article class="nba-doc">
  <a class="nba-back" href="<?php echo esc_url(Router::url('help')); ?>"><?php echo View::icon('arrow-left', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Help Center', 'nirog-academy'); ?></a>
  <h1><?php echo esc_html($doc['title']); ?></h1>
  <div class="nba-prose"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passed through wp_kses_post in Markdown::to_html(). ?></div>
</article>
