<?php
/**
 * One live session.
 *
 * @var array $info LiveSessions::info()
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Live\LiveSessions;

defined('ABSPATH') || exit;

$phase = $info['phase'];
$calendar = add_query_arg([
  'action' => 'TEMPLATE',
  'text' => rawurlencode($info['title']),
  'dates' => gmdate('Ymd\THis\Z', $info['start']) . '/' . gmdate('Ymd\THis\Z', $info['end']),
  'details' => rawurlencode(LiveSessions::url($info['id'])),
], 'https://calendar.google.com/calendar/render');
$minutes_to_start = (int) ceil(($info['start'] - time()) / 60);
?>
<div class="nba-wrap nba-page">
  <a class="nba-crumb" href="<?php echo esc_url(Router::url('live')); ?>">← <?php esc_html_e('Live sessions', 'nirog-academy'); ?></a>

  <header class="nba-pagehead">
    <span class="nba-eyebrow">
      <?php
      echo esc_html(match ($phase) {
        'live' => __('Happening now', 'nirog-academy'),
        'open' => __('Starting soon', 'nirog-academy'),
        'ended' => __('Past session', 'nirog-academy'),
        'cancelled' => __('Cancelled', 'nirog-academy'),
        default => __('Live session', 'nirog-academy'),
      });
      ?>
    </span>
    <h1 class="nba-title"><?php echo esc_html($info['title']); ?></h1>
    <p class="nba-lead">
      <?php echo esc_html(wp_date('l, j F · g:i a', $info['start']) . ' – ' . wp_date('g:i a', $info['end'])); ?>
      <?php if ($info['host_name']) : ?><br><?php echo esc_html(sprintf(
        /* translators: %s: host name */
        __('With %s', 'nirog-academy'),
        $info['host_name']
      )); ?><?php endif; ?>
    </p>
  </header>

  <?php if ($phase === 'cancelled') : ?>
    <p class="nba-lead"><?php esc_html_e('This session will not take place. We will let you know when a new time is set.', 'nirog-academy'); ?></p>

  <?php elseif ($info['kind'] === 'stream' && in_array($phase, ['open', 'live'], true) && $info['youtube']) : ?>
    <div class="nba-player">
      <div class="nba-player-frame">
        <iframe src="<?php echo esc_url('https://www.youtube-nocookie.com/embed/' . rawurlencode($info['youtube']) . '?autoplay=1&rel=0&modestbranding=1&playsinline=1'); ?>" title="<?php echo esc_attr($info['title']); ?>" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
      </div>
      <p class="nba-meta" style="margin-top:12px"><?php esc_html_e('If the class has not started yet, it will appear here at the start time. Reload the page if it does not.', 'nirog-academy'); ?></p>
    </div>

  <?php elseif ($info['kind'] === 'meeting' && in_array($phase, ['open', 'live'], true)) : ?>
    <section class="nba-next-panel" style="margin-bottom:32px">
      <div class="nba-next-main">
        <span class="nba-eyebrow"><?php echo esc_html($phase === 'live' ? __('The session has started', 'nirog-academy') : sprintf(
          /* translators: %d: minutes */
          _n('Starts in %d minute', 'Starts in %d minutes', max(1, $minutes_to_start), 'nirog-academy'),
          max(1, $minutes_to_start)
        )); ?></span>
        <p class="nba-lead" style="margin:0 0 24px"><?php echo esc_html(str_contains((string) $info['link'], 'meet.google.com')
          ? __('The session opens in Google Meet. On a phone, install the Google Meet app first. Keep your microphone off unless you are asked to speak.', 'nirog-academy')
          : __('The meeting opens in Zoom or Google Meet. Keep your microphone off unless you are asked to speak.', 'nirog-academy')); ?></p>
        <a class="nba-btn" href="<?php echo esc_url(LiveSessions::join_url($info['id'])); ?>" target="_blank" rel="noopener"><?php esc_html_e('Join the session', 'nirog-academy'); ?></a>
      </div>
    </section>

  <?php elseif ($phase === 'upcoming') : ?>
    <section class="nba-next-panel" style="margin-bottom:32px">
      <div class="nba-next-main">
        <span class="nba-eyebrow"><?php esc_html_e('How to join', 'nirog-academy'); ?></span>
        <p class="nba-lead" style="margin:0 0 24px"><?php echo esc_html($info['kind'] === 'stream'
          ? __('Come back to this page at the start time. The class will play here.', 'nirog-academy')
          : __('Come back to this page 15 minutes before the start. The join button will appear here.', 'nirog-academy')); ?></p>
        <a class="nba-btn nba-btn-line" href="<?php echo esc_url($calendar); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add to calendar', 'nirog-academy'); ?></a>
      </div>
    </section>

  <?php else : ?>
    <?php if ($info['recording']) : ?>
      <p><a class="nba-btn" href="<?php echo esc_url(Router::lesson_url($info['recording'])); ?>"><?php esc_html_e('Watch the recording', 'nirog-academy'); ?></a></p>
    <?php else : ?>
      <p class="nba-lead"><?php esc_html_e('This session has ended. The recording will appear here when it is ready.', 'nirog-academy'); ?></p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($info['description']) : ?>
    <div class="nba-prose" style="margin-top:32px"><?php echo wp_kses_post(wpautop($info['description'])); ?></div>
  <?php endif; ?>
</div>
