<?php
/**
 * Programme overview: modules and lessons with their state.
 *
 * @var WP_Post     $programme
 * @var object|null $enrollment
 * @var array       $modules
 * @var array       $summary
 * @var bool        $has_bonus
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$element = (string) get_post_meta($programme->ID, '_nba_element', true) ?: 'earth';
$icons = ['completed' => 'check', 'progress' => 'play', 'available' => 'play', 'locked' => 'lock'];
?>
<header class="nba-page-head nba-el-<?php echo esc_attr($element); ?>">
  <a class="nba-back" href="<?php echo esc_url(Router::url()); ?>"><?php echo View::icon('arrow-left', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Dashboard', 'nirog-academy'); ?></a>
  <div class="nba-head-row">
    <div>
      <h1><?php echo esc_html(I18n::field($programme->ID, 'title')); ?></h1>
      <?php $summary_text = I18n::field($programme->ID, 'summary'); if ($summary_text) : ?><p class="nba-lead"><?php echo esc_html($summary_text); ?></p><?php endif; ?>
      <?php if ($enrollment && !empty($enrollment->ends_at)) : ?>
        <p class="nba-muted"><?php echo esc_html(sprintf(
          /* translators: %s: date */
          __('Access until %s', 'nirog-academy'),
          Time::human($enrollment->ends_at, 'j F Y')
        )); ?></p>
      <?php endif; ?>
    </div>
    <?php echo View::ring($summary['pct'], 88); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  </div>
</header>

<?php if ($enrollment && $enrollment->status === 'paused') : ?>
  <div class="nba-flash nba-flash-error"><?php esc_html_e('Your access is paused. Lessons are visible but cannot be opened until it is resumed.', 'nirog-academy'); ?></div>
<?php endif; ?>

<ol class="nba-modules">
  <?php foreach ($modules as $i => $module) :
    $title = $module['id'] ? I18n::field($module['id'], 'title') : __('More lessons', 'nirog-academy');
    $count = count($module['lessons']);
    $open = $module['done'] < $count; ?>
    <li class="nba-module">
      <details<?php echo $open || $i === 0 ? ' open' : ''; ?>>
        <summary>
          <span class="nba-module-title"><?php echo esc_html($title); ?></span>
          <span class="nba-module-meta"><?php echo esc_html(sprintf(
            /* translators: 1: done, 2: total */
            __('%1$d/%2$d done', 'nirog-academy'),
            $module['done'],
            $count
          )); ?></span>
        </summary>
        <ol class="nba-lessons">
          <?php foreach ($module['lessons'] as $item) :
            $lid = $item['id'];
            $state = $item['state'];
            $type = (string) get_post_meta($lid, '_nba_type', true) ?: 'video';
            $minutes = (int) get_post_meta($lid, '_nba_duration_min', true);
            $is_locked = $state['state'] === 'locked';
            $tag = $is_locked ? 'div' : 'a'; ?>
            <li>
              <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="nba-lesson nba-lesson-<?php echo esc_attr($state['state']); ?>"<?php echo $is_locked ? ' aria-disabled="true"' : ' href="' . esc_url(Router::lesson_url($lid)) . '"'; ?>>
                <span class="nba-lesson-icon"><?php echo View::icon($state['state'] === 'available' && $type === 'audio' ? 'audio' : ($state['state'] === 'available' && $type === 'reading' ? 'file' : $icons[$state['state']]), 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="nba-lesson-body">
                  <span class="nba-lesson-title"><?php echo esc_html(I18n::field($lid, 'title')); ?></span>
                  <span class="nba-lesson-meta">
                    <?php echo esc_html(implode(' · ', array_filter([View::minutes($minutes), $state['state'] !== 'available' ? $state['label'] : '']))); ?>
                  </span>
                  <?php if ($state['state'] === 'progress') : ?><span class="nba-mini-bar"><span style="width:<?php echo (int) $state['pct']; ?>%"></span></span><?php endif; ?>
                </span>
              </<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            </li>
          <?php endforeach; ?>
        </ol>
      </details>
    </li>
  <?php endforeach; ?>
</ol>

<?php if (!$modules) : ?>
  <p class="nba-card"><?php esc_html_e('Lessons are being prepared. Please check back soon.', 'nirog-academy'); ?></p>
<?php endif; ?>

<?php if ($has_bonus) : ?>
  <a class="nba-card nba-card-space nba-card-link" href="<?php echo esc_url(Router::url('bonus')); ?>">
    <?php echo View::icon('gift', 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <span><strong><?php esc_html_e('Bonus library', 'nirog-academy'); ?></strong><br><span class="nba-muted"><?php esc_html_e('Extra talks, recipes and recordings', 'nirog-academy'); ?></span></span>
  </a>
<?php endif; ?>
