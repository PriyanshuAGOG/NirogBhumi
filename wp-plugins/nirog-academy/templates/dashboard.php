<?php
/**
 * Participant dashboard.
 *
 * @var WP_User      $user
 * @var array        $programmes
 * @var int[]        $today
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
$first_name = $user->first_name ?: $user->display_name;
?>
<header class="nba-page-head">
  <p class="nba-eyebrow"><?php echo esc_html(wp_date('l, j F')); ?></p>
  <h1><?php echo esc_html($greeting . ', ' . $first_name); ?></h1>
</header>

<?php if (!$programmes) : ?>
  <section class="nba-card nba-empty">
    <?php echo View::icon('book', 32); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <h2><?php esc_html_e('You have not joined a programme yet', 'nirog-academy'); ?></h2>
    <p><?php esc_html_e('When you join a programme, your lessons will appear here.', 'nirog-academy'); ?></p>
    <a class="nba-btn" href="<?php echo esc_url((string) apply_filters('nba_programmes_page_url', home_url('/programmes/'))); ?>"><?php esc_html_e('See our programmes', 'nirog-academy'); ?></a>
  </section>
<?php endif; ?>

<?php foreach ($programmes as $p) :
  $pid = $p['id'];
  $enrollment = $p['enrollment'];
  $summary = $p['summary'];
  $continue = $p['continue'];
  $waiting = $enrollment->status === 'pending' || (empty($enrollment->staff_view) && Time::ts($enrollment->starts_at) > time());
  ?>
  <section class="nba-hero nba-el-<?php echo esc_attr($p['element']); ?>">
    <div class="nba-hero-text">
      <p class="nba-eyebrow"><?php esc_html_e('Your programme', 'nirog-academy'); ?></p>
      <h2><?php echo esc_html(I18n::field($pid, 'title')); ?></h2>
      <?php if ($enrollment->status === 'paused') : ?>
        <p><?php esc_html_e('Your access is paused. Contact us when you are ready to continue.', 'nirog-academy'); ?></p>
      <?php elseif ($enrollment->status === 'pending') : ?>
        <p><?php esc_html_e('Your place is confirmed. We will email you your batch start date.', 'nirog-academy'); ?></p>
      <?php elseif ($waiting) : ?>
        <p><?php echo esc_html(sprintf(
          /* translators: %s: date */
          __('Your batch begins on %s. Lessons open from that day.', 'nirog-academy'),
          Time::human($enrollment->starts_at, 'l, j F')
        )); ?></p>
        <a class="nba-btn" href="<?php echo esc_url(Router::programme_url($pid)); ?>"><?php esc_html_e('See what is coming', 'nirog-academy'); ?></a>
      <?php elseif ($continue) :
        $label = $summary['resume_lesson'] ? __('Continue where you left off', 'nirog-academy') : ($summary['completed'] ? __('Next lesson', 'nirog-academy') : __('Start your first lesson', 'nirog-academy'));
        $locked = $p['continue_state'] && $p['continue_state']['state'] === 'locked';
        ?>
        <p class="nba-hero-lesson"><?php echo esc_html(I18n::field($continue, 'title')); ?></p>
        <?php if ($locked) : ?>
          <p class="nba-muted"><?php echo esc_html($p['continue_state']['label']); ?></p>
          <a class="nba-btn nba-btn-quiet" href="<?php echo esc_url(Router::programme_url($pid)); ?>"><?php esc_html_e('View programme', 'nirog-academy'); ?></a>
        <?php else : ?>
          <a class="nba-btn nba-btn-large" href="<?php echo esc_url(Router::lesson_url($continue)); ?>"><?php echo View::icon('play', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html($label); ?></a>
        <?php endif; ?>
      <?php else : ?>
        <p><?php esc_html_e('You have completed every lesson. Keep practising — you can revisit any lesson while your access lasts.', 'nirog-academy'); ?></p>
        <a class="nba-btn nba-btn-quiet" href="<?php echo esc_url(Router::programme_url($pid)); ?>"><?php esc_html_e('View programme', 'nirog-academy'); ?></a>
      <?php endif; ?>
    </div>
    <div class="nba-hero-progress">
      <?php echo View::ring($summary['pct'], 112); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <p><?php echo esc_html(sprintf(
        /* translators: 1: completed, 2: total */
        __('%1$d of %2$d lessons', 'nirog-academy'),
        $summary['completed'],
        $summary['total']
      )); ?></p>
      <?php if (!empty($enrollment->ends_at)) : ?>
        <p class="nba-muted"><?php echo esc_html(sprintf(
          /* translators: %s: date */
          __('Access until %s', 'nirog-academy'),
          Time::human($enrollment->ends_at, 'j M Y')
        )); ?></p>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>

<div class="nba-grid">
  <?php if ($today) : ?>
    <section class="nba-card">
      <h2 class="nba-card-title"><?php echo View::icon('calendar', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Opened today', 'nirog-academy'); ?></h2>
      <ul class="nba-mini-list">
        <?php foreach ($today as $lesson_id) : ?>
          <li><a href="<?php echo esc_url(Router::lesson_url($lesson_id)); ?>"><?php echo esc_html(I18n::field($lesson_id, 'title')); ?></a>
            <?php $min = (int) get_post_meta($lesson_id, '_nba_duration_min', true); if ($min) : ?><span class="nba-muted"><?php echo esc_html(View::minutes($min)); ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if ($next_live) :
    $starts = (string) get_post_meta($next_live->ID, '_nba_starts_at', true); ?>
    <section class="nba-card nba-card-water">
      <h2 class="nba-card-title"><?php echo View::icon('live', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Next live session', 'nirog-academy'); ?></h2>
      <p class="nba-strong"><?php echo esc_html($next_live->post_title); ?></p>
      <p class="nba-muted"><?php echo esc_html(Time::human_time($starts)); ?></p>
      <a class="nba-link" href="<?php echo esc_url(Router::url('live')); ?>"><?php esc_html_e('Details', 'nirog-academy'); ?> →</a>
    </section>
  <?php endif; ?>

  <?php if ($announcement) : ?>
    <section class="nba-card nba-card-air">
      <h2 class="nba-card-title"><?php echo View::icon('bell', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('From the team', 'nirog-academy'); ?></h2>
      <p class="nba-strong"><?php echo esc_html($announcement->title); ?></p>
      <p><?php echo esc_html(wp_trim_words((string) $announcement->body, 40)); ?></p>
      <a class="nba-link" href="<?php echo esc_url(Router::url('notifications')); ?>"><?php esc_html_e('All messages', 'nirog-academy'); ?> →</a>
    </section>
  <?php endif; ?>

  <section class="nba-card nba-card-soft">
    <h2 class="nba-card-title"><?php echo View::icon('help', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Need help?', 'nirog-academy'); ?></h2>
    <p><?php esc_html_e('Short guides for logging in, your device, watching lessons and more.', 'nirog-academy'); ?></p>
    <a class="nba-link" href="<?php echo esc_url(Router::url('help')); ?>"><?php esc_html_e('Open the Help Center', 'nirog-academy'); ?> →</a>
  </section>
</div>
