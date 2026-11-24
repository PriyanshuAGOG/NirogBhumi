<?php
/**
 * Live sessions.
 *
 * @var WP_Post[] $upcoming
 * @var WP_Post[] $past
 */
use NirogBhumi\Academy\Frontend\Router;
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
<div class="nba-wrap nba-page">
  <header class="nba-pagehead" style="border-bottom:0">
    <span class="nba-eyebrow"><?php esc_html_e('Live', 'nirog-academy'); ?></span>
    <h1 class="nba-title"><?php echo wp_kses(__('Practise <em>together</em>', 'nirog-academy'), ['em' => []]); ?></h1>
    <p class="nba-lead"><?php esc_html_e('The join button appears 15 minutes before each session. We remind you the day before.', 'nirog-academy'); ?></p>
  </header>

  <?php if (!$upcoming) : ?>
    <p class="nba-lead" style="padding:28px 0;border-top:1px solid var(--nb-line)"><?php esc_html_e('No live sessions are planned right now. We will let you know when one is added.', 'nirog-academy'); ?></p>
  <?php endif; ?>

  <div>
    <?php foreach ($upcoming as $s) :
      $start = Time::ts((string) get_post_meta($s->ID, '_nba_starts_at', true));
      $end = $start + 60 * max(5, (int) get_post_meta($s->ID, '_nba_duration_min', true));
      $join = (string) get_post_meta($s->ID, '_nba_join_url', true);
      $open = time() >= $start - 15 * MINUTE_IN_SECONDS && time() <= $end; ?>
      <article class="nba-live-row<?php echo $open ? ' is-live' : ''; ?>">
        <div class="nba-date"><span class="nba-date-day"><?php echo esc_html(wp_date('j', $start)); ?></span><span class="nba-date-month"><?php echo esc_html(wp_date('M · D', $start)); ?></span></div>
        <div>
          <?php if ($open) : ?><span class="nba-live-now"><?php esc_html_e('Happening now', 'nirog-academy'); ?></span><?php endif; ?>
          <h2 class="nba-h3"><?php echo esc_html($s->post_title); ?></h2>
          <p class="nba-meta"><?php echo esc_html(wp_date('g:i a', $start) . ' – ' . wp_date('g:i a', $end)); ?></p>
          <?php if ($s->post_content) : ?><div class="nba-prose nba-muted"><?php echo wp_kses_post(wpautop($s->post_content)); ?></div><?php endif; ?>
        </div>
        <div>
          <?php if ($open && $join) : ?>
            <a class="nba-btn" href="<?php echo esc_url($join); ?>" target="_blank" rel="noopener"><?php esc_html_e('Join now', 'nirog-academy'); ?></a>
          <?php else : ?>
            <a class="nba-link" href="<?php echo esc_url($calendar_link($s)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add to calendar', 'nirog-academy'); ?></a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($past) : ?>
    <h2 class="nba-h2" style="margin:64px 0 18px"><?php esc_html_e('Past sessions', 'nirog-academy'); ?></h2>
    <ul class="nba-list">
      <?php foreach ($past as $s) :
        $recording = (int) get_post_meta($s->ID, '_nba_recording_lesson_id', true); ?>
        <li><div class="nba-list-row">
          <span class="nba-meta"><?php echo esc_html(Time::human((string) get_post_meta($s->ID, '_nba_starts_at', true), 'j M Y')); ?></span>
          <span class="nba-h3"><?php echo esc_html($s->post_title); ?></span>
          <?php if ($recording) : ?><a class="nba-link" href="<?php echo esc_url(Router::lesson_url($recording)); ?>"><?php esc_html_e('Watch recording', 'nirog-academy'); ?></a><?php else : ?><span class="nba-meta"><?php esc_html_e('Recording coming soon', 'nirog-academy'); ?></span><?php endif; ?>
        </div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
