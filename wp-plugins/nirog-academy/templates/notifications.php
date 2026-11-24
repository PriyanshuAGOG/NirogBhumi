<?php
/**
 * Inbox.
 *
 * @var object[] $items
 */
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$kinds = [
  'lesson' => __('New lesson', 'nirog-academy'),
  'live' => __('Live session', 'nirog-academy'),
  'announcement' => __('From the team', 'nirog-academy'),
  'device' => __('Your device', 'nirog-academy'),
  'expiry' => __('Your access', 'nirog-academy'),
  'milestone' => __('Well done', 'nirog-academy'),
  'welcome' => __('Welcome', 'nirog-academy'),
  'nudge' => __('Reminder', 'nirog-academy'),
];
?>
<div class="nba-wrap nba-page">
  <header class="nba-pagehead" style="border-bottom:0">
    <span class="nba-eyebrow"><?php esc_html_e('Inbox', 'nirog-academy'); ?></span>
    <h1 class="nba-title"><?php esc_html_e('Messages', 'nirog-academy'); ?></h1>
  </header>
  <?php if (!$items) : ?>
    <p class="nba-lead" style="padding:28px 0;border-top:1px solid var(--nb-line)"><?php esc_html_e('Nothing yet. New lessons, live session reminders and messages from the team will show up here.', 'nirog-academy'); ?></p>
  <?php else : ?>
    <ul class="nba-list">
      <?php foreach ($items as $n) :
        $tag = $n->url ? 'a' : 'div'; ?>
        <li>
          <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="nba-list-row<?php echo $n->read_at ? '' : ' is-unread'; ?>"<?php echo $n->url ? ' href="' . esc_url($n->url) . '"' : ''; ?>>
            <span class="nba-meta"><?php echo esc_html($kinds[$n->type] ?? ''); ?><br><?php echo esc_html(sprintf(
              /* translators: %s: time ago */
              __('%s ago', 'nirog-academy'),
              human_time_diff(Time::ts($n->created_at))
            )); ?></span>
            <span>
              <span class="nba-h3" style="display:block"><?php echo esc_html($n->title); ?></span>
              <?php if ($n->body) : ?><p><?php echo nl2br(esc_html($n->body)); ?></p><?php endif; ?>
            </span>
            <span aria-hidden="true"><?php echo $n->url ? '→' : ''; ?></span>
          </<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
