<?php
namespace NirogBhumi\Academy\Notify;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Background jobs.
 *
 * Uses Action Scheduler (shipped with WooCommerce) when available, otherwise
 * WP-Cron. On Hostinger, add a real cron job (hPanel → Advanced → Cron Jobs)
 * that loads wp-cron.php every 15 minutes so reminders are sent on time even
 * when the site is quiet. See docs/admin/setup-checklist.md.
 */
final class Scheduler {
  private const ANNOUNCE_QUEUE = 'nba_announcement_queue';
  private const BATCH = 40;

  public static function register(): void {
    add_filter('cron_schedules', static function (array $s): array {
      $s['nba_15min'] = ['interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes (Academy)'];
      return $s;
    });
    add_action('init', [self::class, 'ensure_scheduled'], 20);
    add_action('nba_daily', [self::class, 'daily']);
    add_action('nba_quarter_hourly', [self::class, 'quarter_hourly']);
  }

  public static function ensure_scheduled(): void {
    if (get_transient('nba_schedule_checked')) {
      return;
    }
    set_transient('nba_schedule_checked', 1, HOUR_IN_SECONDS);
    $use_as = function_exists('as_has_scheduled_action') && function_exists('as_schedule_recurring_action');
    if ($use_as) {
      if (!as_has_scheduled_action('nba_daily')) {
        as_schedule_recurring_action(self::next_local_time(6), DAY_IN_SECONDS, 'nba_daily', [], 'nirog-academy');
      }
      if (!as_has_scheduled_action('nba_quarter_hourly')) {
        as_schedule_recurring_action(time() + 60, 15 * MINUTE_IN_SECONDS, 'nba_quarter_hourly', [], 'nirog-academy');
      }
      return;
    }
    if (!wp_next_scheduled('nba_daily')) {
      wp_schedule_event(self::next_local_time(6), 'daily', 'nba_daily');
    }
    if (!wp_next_scheduled('nba_quarter_hourly')) {
      wp_schedule_event(time() + 60, 'nba_15min', 'nba_quarter_hourly');
    }
  }

  /** Next occurrence of a local hour (6 am: lesson emails arrive before morning practice). */
  private static function next_local_time(int $hour): int {
    $tz = wp_timezone();
    $next = (new \DateTimeImmutable('now', $tz))->setTime($hour, 0);
    if ($next->getTimestamp() <= time()) {
      $next = $next->modify('+1 day');
    }
    return $next->getTimestamp();
  }

  public static function daily(): void {
    self::lesson_unlocks();
    self::nudges();
    self::expiry_warnings();
    nba()->audit->prune(nba()->settings->int('audit_retention_days'));
  }

  public static function quarter_hourly(): void {
    self::live_reminders();
    self::process_announcements();
  }

  // ------------------------------------------------------------ lessons unlocked

  public static function lesson_unlocks(): void {
    $by_user = [];
    foreach (nba()->enrollments->all_active() as $enrollment) {
      $base = nba()->enrollments->drip_base($enrollment);
      if ($base === null || Time::ts($base) > time()) {
        continue;
      }
      $today = Time::days_between($base, Time::now());
      $tree = nba()->curriculum->tree((int) $enrollment->programme_id);
      foreach (array_merge($tree['lessons'], $tree['bonus']) as $lesson_id) {
        $day = (int) get_post_meta($lesson_id, '_nba_release_day', true);
        // Catch up if a run was missed, but never announce old lessons.
        if ($day <= 0 || $day > $today || $day < $today - 2) {
          continue;
        }
        $dedupe = 'unlock-' . $enrollment->id . '-' . $lesson_id;
        $user_id = (int) $enrollment->user_id;
        if (nba()->notifications->already_sent($user_id, $dedupe)) {
          continue;
        }
        I18n::with_user_locale($user_id, static function () use ($user_id, $lesson_id, $dedupe): void {
          nba()->notifications->create(
            $user_id,
            'lesson',
            /* translators: %s: lesson title */
            sprintf(__('New lesson: %s', 'nirog-academy'), I18n::field($lesson_id, 'title')),
            __('It is ready whenever you are.', 'nirog-academy'),
            Router::url('lesson/' . get_post_field('post_name', $lesson_id)),
            $dedupe
          );
        });
        $by_user[$user_id][] = $lesson_id;
      }
    }

    if (!nba()->settings->int('email_lesson_unlocked')) {
      return;
    }
    foreach ($by_user as $user_id => $lessons) {
      if (!Notifications::wants($user_id, 'lessons')) {
        continue;
      }
      I18n::with_user_locale($user_id, static function () use ($user_id, $lessons): void {
        $titles = array_map(static fn($id) => '• ' . I18n::field($id, 'title'), $lessons);
        nba()->notifications->email(
          $user_id,
          count($lessons) === 1
            /* translators: %s: lesson title */
            ? sprintf(__('New lesson: %s', 'nirog-academy'), I18n::field($lessons[0], 'title'))
            : __('New lessons are ready', 'nirog-academy'),
          __('Today\'s practice is ready', 'nirog-academy'),
          array_merge([__('These lessons opened today:', 'nirog-academy')], $titles),
          __('Start the lesson', 'nirog-academy'),
          Router::url('lesson/' . get_post_field('post_name', $lessons[0]))
        );
      });
    }
  }

  // ------------------------------------------------------------ gentle nudges

  public static function nudges(): void {
    $days = nba()->settings->int('nudge_after_days');
    if ($days <= 0) {
      return;
    }
    $seen = [];
    foreach (nba()->enrollments->all_active() as $enrollment) {
      $user_id = (int) $enrollment->user_id;
      if (isset($seen[$user_id]) || Time::ts($enrollment->starts_at) > time()) {
        continue;
      }
      $seen[$user_id] = true;
      $last_active = Time::ts((string) get_user_meta($user_id, 'nba_last_active', true)) ?: Time::ts($enrollment->starts_at);
      $last_nudge = Time::ts((string) get_user_meta($user_id, 'nba_last_nudge', true));
      if ($last_active > time() - $days * DAY_IN_SECONDS || $last_nudge > time() - $days * DAY_IN_SECONDS) {
        continue;
      }
      update_user_meta($user_id, 'nba_last_nudge', Time::now());
      I18n::with_user_locale($user_id, static function () use ($user_id): void {
        nba()->notifications->create($user_id, 'nudge', __('Your practice is waiting for you', 'nirog-academy'), __('Even ten minutes today keeps the rhythm going.', 'nirog-academy'), Router::url());
        if (Notifications::wants($user_id, 'reminders')) {
          nba()->notifications->email($user_id, __('Your practice is waiting for you', 'nirog-academy'), __('A gentle reminder', 'nirog-academy'), [
            __('We noticed you have not opened the Academy for a few days. That is completely normal — life gets busy.', 'nirog-academy'),
            __('Even one short lesson today helps you keep the rhythm. Your place is saved, so you can continue exactly where you stopped.', 'nirog-academy'),
          ], __('Continue where I left off', 'nirog-academy'), Router::url());
        }
      });
    }
  }

  // ------------------------------------------------------------ expiry

  public static function expiry_warnings(): void {
    $days = nba()->settings->int('expiry_warning_days');
    if ($days <= 0) {
      return;
    }
    $limit = time() + $days * DAY_IN_SECONDS;
    foreach (nba()->enrollments->all_active() as $enrollment) {
      $end = Time::ts($enrollment->ends_at);
      if (!$end || $end > $limit) {
        continue;
      }
      $user_id = (int) $enrollment->user_id;
      $dedupe = 'expiry-' . $enrollment->id . '-' . substr((string) $enrollment->ends_at, 0, 10);
      if (nba()->notifications->already_sent($user_id, $dedupe)) {
        continue;
      }
      I18n::with_user_locale($user_id, static function () use ($user_id, $enrollment, $dedupe): void {
        $title = I18n::field((int) $enrollment->programme_id, 'title');
        /* translators: 1: programme name, 2: date */
        $msg = sprintf(__('Your access to %1$s ends on %2$s.', 'nirog-academy'), $title, Time::human($enrollment->ends_at, 'j F Y'));
        nba()->notifications->create($user_id, 'expiry', __('Your access ends soon', 'nirog-academy'), $msg, Router::url(), $dedupe);
        nba()->notifications->email($user_id, __('Your access ends soon', 'nirog-academy'), __('Your access ends soon', 'nirog-academy'), [
          $msg,
          __('Download any worksheets you want to keep, and contact us if you would like to continue.', 'nirog-academy'),
        ], __('Open the Academy', 'nirog-academy'), Router::url());
      });
    }
  }

  // ------------------------------------------------------------ live sessions

  public static function live_reminders(): void {
    $sessions = get_posts([
      'post_type' => PostTypes::LIVE,
      'post_status' => 'publish',
      'numberposts' => 50,
      'meta_query' => [
        ['key' => '_nba_starts_at', 'value' => [Time::now(), gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS)], 'compare' => 'BETWEEN', 'type' => 'DATETIME'],
      ],
    ]);
    foreach ($sessions as $session) {
      if (get_post_meta($session->ID, '_nba_cancelled', true) === '1') {
        continue;
      }
      $starts = Time::ts((string) get_post_meta($session->ID, '_nba_starts_at', true));
      $minutes_left = ($starts - time()) / 60;
      $stage = null;
      if ($minutes_left <= 30 && !get_post_meta($session->ID, '_nba_reminded_15m', true)) {
        $stage = '15m';
      } elseif ($minutes_left > 30 && !get_post_meta($session->ID, '_nba_reminded_24h', true)) {
        $stage = '24h';
      }
      if (!$stage) {
        continue;
      }
      update_post_meta($session->ID, '_nba_reminded_' . $stage, Time::now());
      if ($stage === '15m') {
        update_post_meta($session->ID, '_nba_reminded_24h', Time::now());
      }
      \NirogBhumi\Academy\Live\LiveSessions::remind($session->ID, $stage);
    }
  }

  // ------------------------------------------------------------ announcements

  public static function queue_announcement(int $announcement_id): void {
    $queue = get_option(self::ANNOUNCE_QUEUE, []);
    $queue = is_array($queue) ? $queue : [];
    $queue[$announcement_id] = 0;
    update_option(self::ANNOUNCE_QUEUE, $queue, false);
    self::process_announcements();
  }

  /** @return int[] */
  public static function audience(int $announcement_id): array {
    $programme_id = (int) get_post_meta($announcement_id, '_nba_programme_id', true);
    $cohort_id = (int) get_post_meta($announcement_id, '_nba_cohort_id', true);
    $ids = array_unique(array_map(static fn($e) => (int) $e->user_id, nba()->enrollments->all_active($programme_id, $cohort_id)));
    sort($ids);
    return $ids;
  }

  public static function process_announcements(): void {
    $queue = get_option(self::ANNOUNCE_QUEUE, []);
    if (!is_array($queue) || !$queue) {
      return;
    }
    foreach ($queue as $announcement_id => $offset) {
      $post = get_post((int) $announcement_id);
      if (!$post) {
        unset($queue[$announcement_id]);
        continue;
      }
      $users = self::audience((int) $announcement_id);
      $batch = array_slice($users, (int) $offset, self::BATCH);
      $email = get_post_meta($post->ID, '_nba_channel_email', true) === '1';
      foreach ($batch as $user_id) {
        nba()->notifications->create($user_id, 'announcement', $post->post_title, wp_strip_all_tags($post->post_content), Router::url('notifications'), 'ann-' . $post->ID);
        if ($email && Notifications::wants($user_id, 'announcements')) {
          nba()->notifications->email($user_id, $post->post_title, $post->post_title, preg_split('/\n\s*\n/', wp_strip_all_tags($post->post_content)) ?: [], __('Open the Academy', 'nirog-academy'), Router::url());
        }
      }
      $new_offset = (int) $offset + count($batch);
      update_post_meta($post->ID, '_nba_sent_count', $new_offset);
      if ($new_offset >= count($users)) {
        unset($queue[$announcement_id]);
        update_post_meta($post->ID, '_nba_sent_at', Time::now());
      } else {
        $queue[$announcement_id] = $new_offset;
      }
      break; // One batch per run keeps each run short on shared hosting.
    }
    update_option(self::ANNOUNCE_QUEUE, $queue, false);
  }
}
