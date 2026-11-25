<?php
namespace NirogBhumi\Academy\Live;

use NirogBhumi\Academy\Content\MetaBoxes;
use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Install\Roles;
use NirogBhumi\Academy\Notify\Notifications;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Live sessions.
 *
 * The Academy does not run video calls itself. A session points to:
 *   meeting  a Zoom / Google Meet / Teams link; participants press Join and
 *            the meeting opens in that app. Two-way: good for Q&A.
 *   stream   a YouTube Live link (unlisted). The stream plays inside the
 *            Academy page. One-way: good for guided practice with many people.
 *
 * Joining goes through the Academy (/academy/live/{id}/?join=1), which checks
 * access, records attendance and then forwards to the meeting.
 */
final class LiveSessions {
  /** Join opens this many minutes before the start. */
  public const EARLY_MINUTES = 15;

  public static function url(int $session_id): string {
    return Router::url('live/' . $session_id);
  }

  public static function join_url(int $session_id): string {
    return wp_nonce_url(add_query_arg('join', 1, self::url($session_id)), 'nba_join_' . $session_id, '_nbajoin');
  }

  /**
   * @return array{id:int, title:string, programme:int, cohort:int, week:int, kind:string, link:string, youtube:string, host:int, host_name:string, notes:string, cancelled:bool, start:int, end:int, phase:string, recording:int, description:string}
   */
  public static function info(\WP_Post|int $session): array {
    $s = $session instanceof \WP_Post ? $session : get_post($session);
    $start = Time::ts((string) get_post_meta($s->ID, '_nba_starts_at', true));
    $end = $start + 60 * max(5, (int) get_post_meta($s->ID, '_nba_duration_min', true));
    $link = (string) get_post_meta($s->ID, '_nba_join_url', true);
    $kind = get_post_meta($s->ID, '_nba_live_kind', true) === 'stream' ? 'stream' : 'meeting';
    $host = (int) get_post_meta($s->ID, '_nba_host_id', true);
    $cancelled = get_post_meta($s->ID, '_nba_cancelled', true) === '1';
    $now = time();
    $phase = match (true) {
      $cancelled => 'cancelled',
      $now > $end => 'ended',
      $now >= $start => 'live',
      $now >= $start - self::EARLY_MINUTES * MINUTE_IN_SECONDS => 'open',
      default => 'upcoming',
    };
    return [
      'id' => $s->ID,
      'title' => $s->post_title,
      'description' => $s->post_content,
      'programme' => (int) get_post_meta($s->ID, '_nba_programme_id', true),
      'cohort' => (int) get_post_meta($s->ID, '_nba_cohort_id', true),
      'week' => (int) get_post_meta($s->ID, '_nba_module_id', true),
      'kind' => $kind,
      'link' => $link,
      'youtube' => $kind === 'stream' ? MetaBoxes::youtube_id($link) : '',
      'host' => $host,
      'host_name' => $host && ($u = get_userdata($host)) ? $u->display_name : '',
      'notes' => (string) get_post_meta($s->ID, '_nba_staff_notes', true),
      'cancelled' => $cancelled,
      'start' => $start,
      'end' => $end,
      'phase' => $phase,
      'recording' => (int) get_post_meta($s->ID, '_nba_recording_lesson_id', true),
    ];
  }

  /**
   * Can this person see the session? Staff always; participants when they
   * have open access to the programme and are in the session's batch.
   */
  public static function can_view(int $user_id, int $session_id): bool {
    if (get_post_type($session_id) !== PostTypes::LIVE || get_post_status($session_id) !== 'publish') {
      return Roles::is_staff($user_id) && get_post_type($session_id) === PostTypes::LIVE;
    }
    if (Roles::is_staff($user_id)) {
      return true;
    }
    $info = self::info($session_id);
    $enrollment = nba()->enrollments->current($user_id, $info['programme']);
    if (!$enrollment || !nba()->enrollments->is_open($enrollment)) {
      return false;
    }
    return !$info['cohort'] || (int) $enrollment->cohort_id === $info['cohort'];
  }

  public static function record_join(int $session_id, int $user_id): void {
    global $wpdb;
    $table = Installer::table('attendance');
    $now = Time::now();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query($wpdb->prepare("INSERT INTO $table (session_id, user_id, first_joined_at, last_joined_at, joins) VALUES (%d, %d, %s, %s, 1) ON DUPLICATE KEY UPDATE last_joined_at = VALUES(last_joined_at), joins = joins + 1", $session_id, $user_id, $now, $now));
  }

  /** @return array<int, array{user_id:int, name:string, email:string, joined:string, joins:int}> */
  public static function attendance(int $session_id): array {
    global $wpdb;
    $table = Installer::table('attendance');
    $rows = $wpdb->get_results($wpdb->prepare("SELECT a.*, u.display_name, u.user_email FROM $table a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id WHERE a.session_id = %d ORDER BY a.first_joined_at ASC", $session_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return array_map(static fn($r) => [
      'user_id' => (int) $r->user_id,
      'name' => (string) $r->display_name,
      'email' => (string) $r->user_email,
      'joined' => Time::human($r->first_joined_at, 'g:i a'),
      'joins' => (int) $r->joins,
    ], $rows ?: []);
  }

  public static function attendance_count(int $session_id): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Installer::table('attendance') . ' WHERE session_id = %d', $session_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
  }

  /** @return int[] Participants who should see this session. */
  public static function audience(int $session_id): array {
    $info = self::info($session_id);
    return array_values(array_unique(array_map(static fn($e) => (int) $e->user_id, nba()->enrollments->all_active($info['programme'], $info['cohort']))));
  }

  /**
   * Remind everyone now (in-app, and email for those who want reminders).
   *
   * @param string $stage '24h' | '15m' | 'now'
   */
  public static function remind(int $session_id, string $stage = 'now'): int {
    $info = self::info($session_id);
    if ($info['cancelled']) {
      return 0;
    }
    $users = self::audience($session_id);
    foreach ($users as $user_id) {
      I18n::with_user_locale($user_id, static function () use ($user_id, $info, $stage): void {
        $when = wp_date('l, j M · g:i a', $info['start']);
        $title = match ($stage) {
          '24h' => sprintf(
            /* translators: %s: session title */
            __('Tomorrow: %s', 'nirog-academy'),
            $info['title']
          ),
          '15m' => sprintf(
            /* translators: %s: session title */
            __('Starting soon: %s', 'nirog-academy'),
            $info['title']
          ),
          default => sprintf(
            /* translators: %s: session title */
            __('Live session: %s', 'nirog-academy'),
            $info['title']
          ),
        };
        nba()->notifications->create($user_id, 'live', $title, $when, self::url($info['id']), $stage === 'now' ? null : 'live-' . $info['id'] . '-' . $stage);
        if (Notifications::wants($user_id, 'reminders')) {
          $lines = [$when];
          if ($info['host_name']) {
            /* translators: %s: host name */
            $lines[] = sprintf(__('With %s.', 'nirog-academy'), $info['host_name']);
          }
          $lines[] = $info['kind'] === 'stream'
            ? __('The class plays on the session page in the Academy. Open it a few minutes before the start.', 'nirog-academy')
            : __('The join button appears on the session page 15 minutes before the start.', 'nirog-academy');
          nba()->notifications->email($user_id, $title, $info['title'], $lines, __('Open the session', 'nirog-academy'), self::url($info['id']));
        }
      });
    }
    return count($users);
  }

  public static function set_cancelled(int $session_id, bool $cancelled): int {
    $was = get_post_meta($session_id, '_nba_cancelled', true) === '1';
    update_post_meta($session_id, '_nba_cancelled', $cancelled ? '1' : '0');
    if (!$cancelled || $was) {
      return 0;
    }
    $info = self::info($session_id);
    $users = self::audience($session_id);
    foreach ($users as $user_id) {
      I18n::with_user_locale($user_id, static function () use ($user_id, $info): void {
        /* translators: %s: session title */
        $title = sprintf(__('Cancelled: %s', 'nirog-academy'), $info['title']);
        $when = wp_date('l, j M · g:i a', $info['start']);
        nba()->notifications->create($user_id, 'live', $title, $when, self::url($info['id']));
        nba()->notifications->email($user_id, $title, $title, [
          /* translators: %s: date and time */
          sprintf(__('The live session planned for %s will not take place. We are sorry for the change.', 'nirog-academy'), $when),
          __('We will let you know when a new time is set.', 'nirog-academy'),
        ], __('Open the Academy', 'nirog-academy'), Router::url());
      });
    }
    nba()->audit->log('live.cancelled', 0, 'live', $session_id, ['notified' => count($users)]);
    return count($users);
  }

  /** @return array<int, array{id:int, name:string}> Staff who can host. */
  public static function team(): array {
    $out = [];
    foreach (get_users(['role__in' => ['administrator', 'nba_manager', 'nba_coach'], 'orderby' => 'display_name', 'fields' => ['ID', 'display_name']]) as $u) {
      $out[] = ['id' => (int) $u->ID, 'name' => (string) $u->display_name];
    }
    return $out;
  }
}
