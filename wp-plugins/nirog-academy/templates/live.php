<?php
/**
 * Live sessions list.
 *
 * @var WP_Post[] $upcoming
 * @var WP_Post[] $past
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Live\LiveSessions;

defined('ABSPATH') || exit;
?>
<div class="nba-wrap nba-page">
  <header class="nba-pagehead" style="border-bottom:0">
    <span class="nba-eyebrow"><?php esc_html_e('Live', 'nirog-academy'); ?></span>
    <h1 class="nba-title"><?php esc_html_e('Live sessions', 'nirog-academy'); ?></h1>
    <p class="nba-lead"><?php esc_html_e('Open a session to see how to join. We remind you the day before and shortly before it starts.', 'nirog-academy'); ?></p>
  </header>

  <?php if (!$upcoming) : ?>
    <p class="nba-lead" style="padding:28px 0;border-top:1px solid var(--nb-line)"><?php esc_html_e('No live sessions are planned right now. We will let you know when one is added.', 'nirog-academy'); ?></p>
  <?php endif; ?>

  <div>
    <?php foreach ($upcoming as $s) :
      $info = LiveSessions::info($s);
      $state = match ($info['phase']) {
        'live' => __('Happening now', 'nirog-academy'),
        'open' => __('Starting soon', 'nirog-academy'),
        'cancelled' => __('Cancelled', 'nirog-academy'),
        default => '',
      }; ?>
      <article class="nba-live-row<?php echo in_array($info['phase'], ['open', 'live'], true) ? ' is-live' : ''; ?>">
        <div class="nba-date"><span class="nba-date-day"><?php echo esc_html(wp_date('j', $info['start'])); ?></span><span class="nba-date-month"><?php echo esc_html(wp_date('M · D', $info['start'])); ?></span></div>
        <div>
          <?php if ($state) : ?><span class="nba-live-now"><?php echo esc_html($state); ?></span><?php endif; ?>
          <h2 class="nba-h3"><?php echo esc_html($info['title']); ?></h2>
          <p class="nba-meta"><?php echo esc_html(implode(' · ', array_filter([
            wp_date('g:i a', $info['start']) . ' – ' . wp_date('g:i a', $info['end']),
            $info['host_name'] ? sprintf(
              /* translators: %s: host name */
              __('With %s', 'nirog-academy'),
              $info['host_name']
            ) : '',
          ]))); ?></p>
        </div>
        <div>
          <a class="<?php echo in_array($info['phase'], ['open', 'live'], true) ? 'nba-btn' : 'nba-link'; ?>" href="<?php echo esc_url(LiveSessions::url($info['id'])); ?>"><?php echo esc_html(in_array($info['phase'], ['open', 'live'], true) ? __('Join now', 'nirog-academy') : __('Details', 'nirog-academy')); ?></a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($past) : ?>
    <h2 class="nba-h2" style="margin:64px 0 18px"><?php esc_html_e('Past sessions', 'nirog-academy'); ?></h2>
    <ul class="nba-list">
      <?php foreach ($past as $s) :
        $info = LiveSessions::info($s); ?>
        <li><div class="nba-list-row">
          <span class="nba-meta"><?php echo esc_html(wp_date('j M Y', $info['start'])); ?></span>
          <span class="nba-h3"><?php echo esc_html($info['title']); ?></span>
          <?php if ($info['recording']) : ?><a class="nba-link" href="<?php echo esc_url(Router::lesson_url($info['recording'])); ?>"><?php esc_html_e('Watch recording', 'nirog-academy'); ?></a><?php elseif ($info['cancelled']) : ?><span class="nba-meta"><?php esc_html_e('Cancelled', 'nirog-academy'); ?></span><?php else : ?><span class="nba-meta"><?php esc_html_e('Recording coming soon', 'nirog-academy'); ?></span><?php endif; ?>
        </div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
