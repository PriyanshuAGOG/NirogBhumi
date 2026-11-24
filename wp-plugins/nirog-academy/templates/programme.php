<?php
/**
 * Programme: every week, its lessons, booklets and live sessions.
 *
 * @var WP_Post     $programme
 * @var object|null $enrollment
 * @var array       $weeks
 * @var array       $summary
 * @var int         $bonus_count
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$next = $summary['resume_lesson'] ?: $summary['next_lesson'];
?>
<div class="nba-wrap nba-page">
  <header class="nba-pagehead">
    <span class="nba-eyebrow"><?php esc_html_e('Your programme', 'nirog-academy'); ?></span>
    <h1 class="nba-title"><?php echo esc_html(I18n::field($programme->ID, 'title')); ?></h1>
    <?php $text = I18n::field($programme->ID, 'summary'); if ($text) : ?><p class="nba-lead"><?php echo esc_html($text); ?></p><?php endif; ?>
    <div class="nba-progress">
      <div class="nba-progress-line"><span style="width:<?php echo (int) $summary['pct']; ?>%"></span></div>
      <span class="nba-progress-text"><?php echo esc_html(sprintf(
        /* translators: 1: completed, 2: total */
        __('%1$d of %2$d lessons done', 'nirog-academy'),
        $summary['completed'],
        $summary['total']
      )); ?><?php if ($enrollment && !empty($enrollment->ends_at)) : ?> · <?php echo esc_html(sprintf(
        /* translators: %s: date */
        __('Access until %s', 'nirog-academy'),
        Time::human($enrollment->ends_at, 'j M Y')
      )); ?><?php endif; ?></span>
    </div>
  </header>

  <?php if ($enrollment && $enrollment->status === 'paused') : ?>
    <div class="nba-flash nba-flash-error"><?php esc_html_e('Your access is paused. You can see the lessons, but they open again when access is resumed.', 'nirog-academy'); ?></div>
  <?php endif; ?>

  <?php if (!$weeks) : ?>
    <p class="nba-lead"><?php esc_html_e('Lessons are being prepared. Please check back soon.', 'nirog-academy'); ?></p>
  <?php endif; ?>

  <?php foreach ($weeks as $week) : ?>
    <section class="nba-week" id="week-<?php echo (int) $week['index']; ?>">
      <div class="nba-week-side">
        <span class="nba-eyebrow"><?php echo esc_html(sprintf(
          /* translators: %d: week number */
          __('Week %d', 'nirog-academy'),
          $week['index']
        )); ?></span>
        <h2 class="nba-h2"><?php echo esc_html($week['id'] ? View::week_title($week['id'], $week['index']) : __('More lessons', 'nirog-academy')); ?></h2>
        <span class="nba-week-count"><?php echo esc_html(sprintf(
          /* translators: 1: done, 2: total */
          __('%1$d of %2$d done', 'nirog-academy'),
          $week['done'],
          $week['total']
        )); ?></span>
      </div>
      <div>
        <?php View::include('parts/items', ['items' => $week['items'], 'live' => $week['live'], 'highlight' => $next]); ?>
      </div>
    </section>
  <?php endforeach; ?>

  <?php if ($bonus_count) : ?>
    <a class="nba-next-block" href="<?php echo esc_url(add_query_arg('tab', 'bonus', Router::url('library'))); ?>">
      <span><span class="nba-eyebrow"><?php esc_html_e('Bonus', 'nirog-academy'); ?></span><span class="nba-h3"><?php echo esc_html(sprintf(
        /* translators: %d: count */
        _n('%d extra talk, recipe or recording', '%d extra talks, recipes and recordings', $bonus_count, 'nirog-academy'),
        $bonus_count
      )); ?></span></span>
      <span aria-hidden="true">→</span>
    </a>
  <?php endif; ?>
</div>
