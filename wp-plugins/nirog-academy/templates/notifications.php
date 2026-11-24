<?php
/**
 * Notification list.
 *
 * @var object[] $items
 */
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$icons = ['lesson' => 'play', 'live' => 'live', 'announcement' => 'bell', 'device' => 'device', 'expiry' => 'clock', 'milestone' => 'check', 'welcome' => 'home', 'nudge' => 'calendar'];
?>
<header class="nba-page-head">
  <h1><?php esc_html_e('Notifications', 'nirog-academy'); ?></h1>
</header>
<?php if (!$items) : ?>
  <p class="nba-card"><?php esc_html_e('Nothing yet. New lessons, live session reminders and messages from the team will appear here.', 'nirog-academy'); ?></p>
<?php else : ?>
  <ul class="nba-notifications">
    <?php foreach ($items as $n) : ?>
      <li class="<?php echo $n->read_at ? '' : 'is-unread'; ?>">
        <span class="nba-notification-icon"><?php echo View::icon($icons[$n->type] ?? 'bell', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <div>
          <?php if ($n->url) : ?><a href="<?php echo esc_url($n->url); ?>"><strong><?php echo esc_html($n->title); ?></strong></a><?php else : ?><strong><?php echo esc_html($n->title); ?></strong><?php endif; ?>
          <?php if ($n->body) : ?><p><?php echo nl2br(esc_html($n->body)); ?></p><?php endif; ?>
          <span class="nba-muted nba-small"><?php echo esc_html(sprintf(
            /* translators: %s: time ago */
            __('%s ago', 'nirog-academy'),
            human_time_diff(Time::ts($n->created_at))
          )); ?></span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
