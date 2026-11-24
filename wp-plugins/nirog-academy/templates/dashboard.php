<?php
/**
 * Today: the participant's home.
 *
 * @var WP_User      $user
 * @var object|null  $enrollment
 * @var object[]     $others
 * @var int          $programme_id
 * @var array|null   $summary
 * @var int          $next
 * @var array|null   $next_state
 * @var object|null  $next_progress
 * @var int          $day
 * @var array|null   $week
 * @var int          $booklet
 * @var WP_Post|null $next_live
 * @var object|null  $announcement
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$hour = (int) wp_date('G');
$greeting = $hour < 12 ? __('Good morning', 'nirog-academy') : ($hour < 17 ? __('Good afternoon', 'nirog-academy') : __('Good evening', 'nirog-academy'));
$name = $user->first_name ?: $user->display_name;
$element = $programme_id ? ((string) get_post_meta($programme_id, '_nba_element', true) ?: 'earth') : 'earth';
$type_label = View::type_labels();
?>
<div class="nba-wrap nba-page">

  <header class="nba-pagehead">
    <span class="nba-eyebrow"><?php echo esc_html(wp_date('l, j F')); ?><?php if ($day) : ?> · <?php echo esc_html(sprintf(
      /* translators: %d: day number in the programme */
      __('Day %d', 'nirog-academy'),
      $day
    )); ?><?php endif; ?></span>
    <h1 class="nba-display"><?php echo esc_html($greeting); ?>, <em><?php echo esc_html($name); ?></em>.</h1>
    <?php if ($summary) : ?>
      <p class="nba-lead"><?php echo esc_html(I18n::field($programme_id, 'title')); ?></p>
      <div class="nba-progress">
        <div class="nba-progress-line"><span style="width:<?php echo (int) $summary['pct']; ?>%"></span></div>
        <span class="nba-progress-text"><?php echo esc_html(sprintf(
          /* translators: 1: completed, 2: total */
          __('%1$d of %2$d lessons done', 'nirog-academy'),
          $summary['completed'],
          $summary['total']
        )); ?></span>
      </div>
    <?php endif; ?>
  </header>

  <?php if (!$enrollment) : ?>
    <section class="nba-empty">
      <h2 class="nba-title"><?php esc_html_e('You have not joined a programme yet.', 'nirog-academy'); ?></h2>
      <p class="nba-lead"><?php esc_html_e('When you join a programme, your lessons, booklets and live sessions will appear here.', 'nirog-academy'); ?></p>
      <p><a class="nba-btn" href="<?php echo esc_url((string) apply_filters('nba_programmes_page_url', home_url('/programmes/'))); ?>"><?php esc_html_e('See our programmes', 'nirog-academy'); ?></a></p>
    </section>
  <?php else :
    $waiting = $enrollment->status === 'pending' || (empty($enrollment->staff_view) && Time::ts($enrollment->starts_at) > time());
    $cover = $programme_id ? (string) get_the_post_thumbnail_url($programme_id, 'large') : '';
    ?>
    <section class="nba-next">
      <div>
        <?php if ($enrollment->status === 'paused') : ?>
          <span class="nba-eyebrow"><?php esc_html_e('Access paused', 'nirog-academy'); ?></span>
          <h2><?php esc_html_e('Your access is paused.', 'nirog-academy'); ?></h2>
          <p class="nba-lead"><?php esc_html_e('Contact us when you are ready to continue. The paused days are added back to your access.', 'nirog-academy'); ?></p>
        <?php elseif ($enrollment->status === 'pending') : ?>
          <span class="nba-eyebrow"><?php esc_html_e('Almost there', 'nirog-academy'); ?></span>
          <h2><?php esc_html_e('Your place is confirmed.', 'nirog-academy'); ?></h2>
          <p class="nba-lead"><?php esc_html_e('We will email you your batch start date.', 'nirog-academy'); ?></p>
        <?php elseif ($waiting) : ?>
          <span class="nba-eyebrow"><?php esc_html_e('Starting soon', 'nirog-academy'); ?></span>
          <h2><?php echo esc_html(sprintf(
            /* translators: %s: date */
            __('Your batch begins on %s.', 'nirog-academy'),
            Time::human($enrollment->starts_at, 'l, j F')
          )); ?></h2>
          <p class="nba-lead"><?php esc_html_e('Lessons open from that day. You can look around and read what is coming.', 'nirog-academy'); ?></p>
          <a class="nba-btn nba-btn-line" href="<?php echo esc_url(Router::programme_url($programme_id)); ?>"><?php esc_html_e('See the programme', 'nirog-academy'); ?></a>
        <?php elseif ($next) :
          $locked = $next_state && $next_state['state'] === 'locked';
          $type = (string) get_post_meta($next, '_nba_type', true) ?: 'video';
          $min = (int) get_post_meta($next, '_nba_duration_min', true);
          $resume = $next_progress && $next_progress->status !== 'completed' && (int) $next_progress->position_sec > 5;
          ?>
          <span class="nba-eyebrow"><?php echo esc_html($resume ? __('Continue', 'nirog-academy') : __('Up next', 'nirog-academy')); ?></span>
          <h2><?php echo esc_html(I18n::field($next, 'title')); ?></h2>
          <span class="nba-meta"><?php echo esc_html(implode(' · ', array_filter([
            $week ? sprintf(
              /* translators: %d: week number */
              __('Week %d', 'nirog-academy'),
              $week['index']
            ) : '',
            $type_label[$type] ?? '',
            View::minutes($min),
          ]))); ?></span>
          <?php if ($resume && $type !== 'booklet') : ?>
            <p class="nba-next-note"><?php echo esc_html(sprintf(
              /* translators: %s: time like 7:40 */
              __('You stopped at %s.', 'nirog-academy'),
              gmdate((int) $next_progress->position_sec >= 3600 ? 'G:i:s' : 'i:s', (int) $next_progress->position_sec)
            )); ?></p>
          <?php elseif ($resume) : ?>
            <p class="nba-next-note"><?php echo esc_html(sprintf(
              /* translators: %d: page number */
              __('You stopped on page %d.', 'nirog-academy'),
              (int) $next_progress->position_sec
            )); ?></p>
          <?php endif; ?>
          <?php if ($locked) : ?>
            <p class="nba-lead"><?php echo esc_html($next_state['label']); ?></p>
            <a class="nba-btn nba-btn-line" href="<?php echo esc_url(Router::programme_url($programme_id)); ?>"><?php esc_html_e('See the programme', 'nirog-academy'); ?></a>
          <?php else : ?>
            <a class="nba-btn" href="<?php echo esc_url(Router::lesson_url($next)); ?>"><?php echo esc_html($resume ? __('Continue', 'nirog-academy') : ($type === 'booklet' ? __('Read the booklet', 'nirog-academy') : __('Start the lesson', 'nirog-academy'))); ?></a>
          <?php endif; ?>
        <?php else : ?>
          <span class="nba-eyebrow"><?php esc_html_e('All done', 'nirog-academy'); ?></span>
          <h2><?php esc_html_e('You have completed every lesson.', 'nirog-academy'); ?></h2>
          <p class="nba-lead"><?php esc_html_e('Keep practising. You can come back to any lesson while your access lasts.', 'nirog-academy'); ?></p>
          <a class="nba-btn nba-btn-line" href="<?php echo esc_url(Router::programme_url($programme_id)); ?>"><?php esc_html_e('See the programme', 'nirog-academy'); ?></a>
        <?php endif; ?>
      </div>
      <div class="nba-arch nba-el-<?php echo esc_attr($element); ?>" aria-hidden="true">
        <?php if ($cover) : ?>
          <img src="<?php echo esc_url($cover); ?>" alt="">
        <?php else : ?>
          <div class="nba-arch-text">
            <span class="nba-arch-num"><?php echo esc_html($week ? str_pad((string) $week['index'], 2, '0', STR_PAD_LEFT) : '01'); ?></span>
            <span class="nba-arch-label"><?php esc_html_e('Week', 'nirog-academy'); ?></span>
            <?php if ($week && $week['id'] && View::week_title($week['id'], $week['index']) !== sprintf(__('Week %d', 'nirog-academy'), $week['index'])) : ?><span class="nba-arch-sub"><?php echo esc_html(View::week_title($week['id'], $week['index'])); ?></span><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <div class="nba-cols">
      <div>
        <?php if ($week) : ?>
          <section class="nba-block">
            <span class="nba-eyebrow"><?php echo esc_html($week ? sprintf(
              /* translators: %d: week number */
              __('This week · Week %d', 'nirog-academy'),
              $week['index']
            ) : __('This week', 'nirog-academy')); ?></span>
            <h2 class="nba-h2" style="margin-bottom:22px"><?php echo esc_html($week['id'] ? View::week_title($week['id'], $week['index']) : I18n::field($programme_id, 'title')); ?></h2>
            <?php View::include('parts/items', ['items' => $week['items'], 'live' => $week['live'], 'highlight' => $next]); ?>
            <p style="margin-top:22px"><a class="nba-link" href="<?php echo esc_url(Router::programme_url($programme_id)); ?>"><?php esc_html_e('See all weeks', 'nirog-academy'); ?></a></p>
          </section>
        <?php endif; ?>
      </div>

      <div class="nba-aside">
        <?php if ($booklet) : ?>
          <section class="nba-block">
            <span class="nba-eyebrow"><?php esc_html_e('Booklet', 'nirog-academy'); ?></span>
            <?php View::include('parts/cover', ['lesson' => $booklet, 'element' => $element, 'small' => true]); ?>
          </section>
        <?php endif; ?>

        <?php if ($next_live) :
          $ts = Time::ts((string) get_post_meta($next_live->ID, '_nba_starts_at', true)); ?>
          <section class="nba-block">
            <span class="nba-eyebrow"><?php esc_html_e('Next live session', 'nirog-academy'); ?></span>
            <div style="display:flex;gap:22px;align-items:flex-start">
              <div class="nba-date nba-date-sm"><span class="nba-date-day"><?php echo esc_html(wp_date('j', $ts)); ?></span><span class="nba-date-month"><?php echo esc_html(wp_date('M', $ts)); ?></span></div>
              <div>
                <h3 class="nba-h3"><?php echo esc_html($next_live->post_title); ?></h3>
                <p class="nba-meta"><?php echo esc_html(wp_date('l, g:i a', $ts)); ?></p>
                <a class="nba-link" href="<?php echo esc_url(Router::url('live')); ?>"><?php esc_html_e('Details', 'nirog-academy'); ?></a>
              </div>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($announcement) : ?>
          <section class="nba-block">
            <span class="nba-eyebrow"><?php esc_html_e('From the team', 'nirog-academy'); ?></span>
            <h3 class="nba-h3" style="margin-bottom:8px"><?php echo esc_html($announcement->title); ?></h3>
            <p class="nba-muted"><?php echo esc_html(wp_trim_words((string) $announcement->body, 36)); ?></p>
            <a class="nba-link" href="<?php echo esc_url(Router::url('notifications')); ?>"><?php esc_html_e('Inbox', 'nirog-academy'); ?></a>
          </section>
        <?php endif; ?>

        <?php foreach ($others as $other) : ?>
          <section class="nba-block">
            <span class="nba-eyebrow"><?php esc_html_e('Also yours', 'nirog-academy'); ?></span>
            <a class="nba-h3" style="text-decoration:none" href="<?php echo esc_url(Router::programme_url((int) $other->programme_id)); ?>"><?php echo esc_html(I18n::field((int) $other->programme_id, 'title')); ?> →</a>
          </section>
        <?php endforeach; ?>

        <section class="nba-block">
          <span class="nba-eyebrow"><?php esc_html_e('Need help?', 'nirog-academy'); ?></span>
          <p class="nba-muted"><?php esc_html_e('Short guides on logging in, your device and watching lessons.', 'nirog-academy'); ?></p>
          <a class="nba-link" href="<?php echo esc_url(Router::url('help')); ?>"><?php esc_html_e('Help Center', 'nirog-academy'); ?></a>
        </section>
      </div>
    </div>
  <?php endif; ?>
</div>
