<?php
/**
 * Live sessions.
 *
 * @var WP_Post[] $upcoming
 * @var WP_Post[] $past
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$calendar_link = static function (WP_Post $s): string {
  $start = Time::ts((string) get_post_meta($s->ID, '_nba_starts_at', true));
  $end = $start + 60 * max(5, (int) get_post_meta($s->ID, '_nba_duration_min', true));
  return add_query_arg([
    'action' => 'TEMPLATE',
    'text' => rawurlencode($s->post_title),
    'dates' => gmdate('Ymd\THis\Z', $start) . '/' . gmdate('Ymd\THis\Z', $end),
    'details' => rawurlencode(Router::url('live')),
  ], 'https://calendar.google.com/calendar/render');
};
?>
<header class="nba-page-head nba-el-water">
  <h1><?php esc_html_e('Live sessions', 'nirog-academy'); ?></h1>
  <p class="nba-lead"><?php esc_html_e('The join button appears 15 minutes before each session starts.', 'nirog-academy'); ?></p>
</header>

<?php if (!$upcoming) : ?>
  <p class="nba-card"><?php esc_html_e('No live sessions are scheduled right now. We will notify you when one is added.', 'nirog-academy'); ?></p>
<?php endif; ?>

<div class="nba-stack">
  <?php foreach ($upcoming as $s) :
    $starts = (string) get_post_meta($s->ID, '_nba_starts_at', true);
    $start_ts = Time::ts($starts);
    $end_ts = $start_ts + 60 * max(5, (int) get_post_meta($s->ID, '_nba_duration_min', true));
    $join = (string) get_post_meta($s->ID, '_nba_join_url', true);
    $is_open = time() >= $start_ts - 15 * MINUTE_IN_SECONDS && time() <= $end_ts; ?>
    <article class="nba-card nba-live<?php echo $is_open ? ' is-live' : ''; ?>">
      <div class="nba-live-date">
        <span class="nba-live-day"><?php echo esc_html(wp_date('j', $start_ts)); ?></span>
        <span><?php echo esc_html(wp_date('M', $start_ts)); ?></span>
      </div>
      <div class="nba-live-body">
        <h2><?php echo esc_html($s->post_title); ?></h2>
        <p class="nba-muted"><?php echo esc_html(wp_date('l, g:i a', $start_ts)); ?> · <?php echo esc_html(View::minutes((int) get_post_meta($s->ID, '_nba_duration_min', true))); ?></p>
        <?php if ($s->post_content) : ?><div class="nba-prose"><?php echo wp_kses_post(wpautop($s->post_content)); ?></div><?php endif; ?>
        <p>
          <?php if ($is_open && $join) : ?>
            <a class="nba-btn" href="<?php echo esc_url($join); ?>" target="_blank" rel="noopener"><?php echo View::icon('live', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Join now', 'nirog-academy'); ?></a>
          <?php else : ?>
            <a class="nba-btn nba-btn-quiet" href="<?php echo esc_url($calendar_link($s)); ?>" target="_blank" rel="noopener"><?php echo View::icon('calendar', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Add to Google Calendar', 'nirog-academy'); ?></a>
          <?php endif; ?>
        </p>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($past) : ?>
  <h2 class="nba-section-title"><?php esc_html_e('Past sessions', 'nirog-academy'); ?></h2>
  <ul class="nba-card nba-mini-list">
    <?php foreach ($past as $s) :
      $recording = (int) get_post_meta($s->ID, '_nba_recording_lesson_id', true); ?>
      <li>
        <span><?php echo esc_html($s->post_title); ?> <span class="nba-muted">· <?php echo esc_html(Time::human((string) get_post_meta($s->ID, '_nba_starts_at', true), 'j M')); ?></span></span>
        <?php if ($recording) : ?><a href="<?php echo esc_url(Router::lesson_url($recording)); ?>"><?php esc_html_e('Watch recording', 'nirog-academy'); ?></a><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
