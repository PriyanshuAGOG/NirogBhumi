<?php
/**
 * Bonus library.
 *
 * @var array<string, array> $groups
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;
?>
<header class="nba-page-head nba-el-space">
  <h1><?php esc_html_e('Bonus library', 'nirog-academy'); ?></h1>
  <p class="nba-lead"><?php esc_html_e('Extra talks, recipes and recordings to support your practice.', 'nirog-academy'); ?></p>
</header>
<?php if (!$groups) : ?>
  <p class="nba-card"><?php esc_html_e('Bonus items will appear here as they are added.', 'nirog-academy'); ?></p>
<?php endif; ?>
<?php foreach ($groups as $category => $items) : ?>
  <section class="nba-section">
    <h2 class="nba-section-title"><?php echo esc_html($category); ?></h2>
    <div class="nba-tiles">
      <?php foreach ($items as $item) :
        $lid = $item['id'];
        $state = $item['state'];
        $locked = $state['state'] === 'locked';
        $tag = $locked ? 'div' : 'a';
        $type = (string) get_post_meta($lid, '_nba_type', true) ?: 'video'; ?>
        <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="nba-tile<?php echo $locked ? ' is-locked' : ''; ?>"<?php echo $locked ? '' : ' href="' . esc_url(Router::lesson_url($lid)) . '"'; ?>>
          <span class="nba-tile-icon"><?php echo View::icon($locked ? 'lock' : ($state['state'] === 'completed' ? 'check' : ($type === 'audio' ? 'audio' : ($type === 'reading' ? 'file' : 'play'))), 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <span class="nba-tile-title"><?php echo esc_html(I18n::field($lid, 'title')); ?></span>
          <span class="nba-muted"><?php echo esc_html($locked ? $state['label'] : View::minutes((int) get_post_meta($lid, '_nba_duration_min', true))); ?></span>
        </<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
