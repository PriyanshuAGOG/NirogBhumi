<?php
namespace NirogBhumi\Academy\Progress;

use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Lesson progress.
 *
 * Watching is recorded in 5-second buckets, so a lesson counts as watched
 * only when enough of it has really played. Jumping to the end does not
 * complete it. Reading lessons are completed with a button.
 */
final class Progress {
  public const BUCKET = 5;

  private function table(): string {
    return Installer::table('progress');
  }

  public function get(int $user_id, int $lesson_id): ?object {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE user_id = %d AND lesson_id = %d", $user_id, $lesson_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return $row ?: null;
  }

  public function is_complete(int $user_id, int $lesson_id): bool {
    $row = $this->get($user_id, $lesson_id);
    return $row && $row->status === 'completed';
  }

  /** @return array<int, object> keyed by lesson ID */
  public function for_programme(int $user_id, int $programme_id): array {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE user_id = %d AND programme_id = %d", $user_id, $programme_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $out = [];
    foreach ($rows ?: [] as $r) {
      $out[(int) $r->lesson_id] = $r;
    }
    return $out;
  }

  /**
   * Record playback.
   *
   * @param array<int, array{0: float, 1: float}> $ranges Played ranges (seconds) since the last report.
   * @return array{status: string, watched_pct: int, completed: bool}
   */
  public function heartbeat(int $user_id, int $lesson_id, int $programme_id, float $position, float $duration, array $ranges): array {
    global $wpdb;
    $duration = max(0, min(6 * 3600, $duration));
    $row = $this->get($user_id, $lesson_id);
    $buckets_total = $duration > 0 ? (int) ceil($duration / self::BUCKET) : 0;
    $map = $row && $row->watched ? (string) $row->watched : '';
    if ($buckets_total > 0) {
      $map = str_pad(substr($map, 0, $buckets_total), $buckets_total, '0');
    }

    // Do not accept more watched time than could really have passed since the
    // last report (allowing for 1.25× speed and network delays).
    $elapsed = $row ? max(1, time() - Time::ts($row->updated_at)) : 60;
    $budget = min(180, $elapsed * 1.5 + 10);
    $credited = 0.0;
    foreach (array_slice($ranges, 0, 50) as $range) {
      if (!is_array($range) || count($range) < 2) {
        continue;
      }
      $start = max(0, (float) $range[0]);
      $end = min($duration ?: PHP_FLOAT_MAX, (float) $range[1]);
      if ($end <= $start) {
        continue;
      }
      $length = min($end - $start, $budget - $credited);
      if ($length <= 0) {
        break;
      }
      $credited += $length;
      $end = $start + $length;
      if ($buckets_total > 0) {
        for ($b = (int) floor($start / self::BUCKET); $b < min($buckets_total, (int) ceil($end / self::BUCKET)); $b++) {
          $map[$b] = '1';
        }
      }
    }
    $pct = $buckets_total > 0 ? (int) floor(substr_count($map, '1') * 100 / $buckets_total) : 0;
    $was_complete = $row && $row->status === 'completed';
    $now_complete = $was_complete || ($buckets_total > 0 && $pct >= nba()->settings->int('completion_threshold'));

    $data = [
      'user_id' => $user_id,
      'lesson_id' => $lesson_id,
      'programme_id' => $programme_id,
      'status' => $now_complete ? 'completed' : 'in_progress',
      'position_sec' => (int) max(0, min($position, $duration ?: $position)),
      'duration_sec' => (int) $duration,
      'watched' => $map,
      'watched_pct' => max($pct, $row ? (int) $row->watched_pct : 0),
      'completed_at' => $was_complete ? $row->completed_at : ($now_complete ? Time::now() : null),
      'updated_at' => Time::now(),
    ];
    $wpdb->replace($this->table(), $data);
    $this->touch($user_id);
    if ($now_complete && !$was_complete) {
      $this->after_completion($user_id, $lesson_id, $programme_id);
    }
    return ['status' => $data['status'], 'watched_pct' => (int) $data['watched_pct'], 'completed' => $now_complete];
  }

  /**
   * Manual completion. Video and audio lessons can only be marked complete
   * once enough has been watched; reading lessons any time.
   */
  public function complete(int $user_id, int $lesson_id, int $programme_id): bool|\WP_Error {
    global $wpdb;
    $row = $this->get($user_id, $lesson_id);
    if ($row && $row->status === 'completed') {
      return true;
    }
    $type = (string) get_post_meta($lesson_id, '_nba_type', true) ?: 'video';
    $threshold = nba()->settings->int('completion_threshold');
    if ($type === 'booklet' && nba()->stream->booklet_file($lesson_id) && (!$row || (int) $row->watched_pct < $threshold)) {
      return new \WP_Error('nba_not_read', __('Read the booklet to the last page to complete it.', 'nirog-academy'));
    }
    $has_media = in_array($type, ['video', 'audio'], true) && nba()->stream->trackable(nba()->stream->media_for_lesson($lesson_id));
    if ($has_media && (!$row || (int) $row->watched_pct < $threshold)) {
      return new \WP_Error('nba_not_watched', __('Watch the lesson to the end to complete it.', 'nirog-academy'));
    }
    $wpdb->replace($this->table(), [
      'user_id' => $user_id,
      'lesson_id' => $lesson_id,
      'programme_id' => $programme_id,
      'status' => 'completed',
      'position_sec' => $row ? (int) $row->position_sec : 0,
      'duration_sec' => $row ? (int) $row->duration_sec : 0,
      'watched' => $row ? $row->watched : null,
      'watched_pct' => $row ? max(100, (int) $row->watched_pct) : 100,
      'completed_at' => Time::now(),
      'updated_at' => Time::now(),
    ]);
    $this->touch($user_id);
    $this->after_completion($user_id, $lesson_id, $programme_id);
    return true;
  }

  /**
   * Booklet reading: one bucket per page. Complete when most pages were seen.
   *
   * @return array{status: string, watched_pct: int, completed: bool}
   */
  public function page_view(int $user_id, int $lesson_id, int $programme_id, int $page, int $total): array {
    global $wpdb;
    $total = max(1, min(2000, $total));
    $page = max(1, min($total, $page));
    $row = $this->get($user_id, $lesson_id);
    $map = str_pad(substr($row && $row->watched ? (string) $row->watched : '', 0, $total), $total, '0');
    $map[$page - 1] = '1';
    $pct = (int) floor(substr_count($map, '1') * 100 / $total);
    $was_complete = $row && $row->status === 'completed';
    $now_complete = $was_complete || $pct >= nba()->settings->int('completion_threshold') || ($total <= 3 && $page === $total);
    $wpdb->replace($this->table(), [
      'user_id' => $user_id,
      'lesson_id' => $lesson_id,
      'programme_id' => $programme_id,
      'status' => $now_complete ? 'completed' : 'in_progress',
      'position_sec' => $page,
      'duration_sec' => $total,
      'watched' => $map,
      'watched_pct' => $pct,
      'completed_at' => $was_complete ? $row->completed_at : ($now_complete ? Time::now() : null),
      'updated_at' => Time::now(),
    ]);
    if ((int) get_post_meta($lesson_id, '_nba_pages', true) !== $total) {
      update_post_meta($lesson_id, '_nba_pages', $total);
    }
    $this->touch($user_id);
    if ($now_complete && !$was_complete) {
      $this->after_completion($user_id, $lesson_id, $programme_id);
    }
    return ['status' => $now_complete ? 'completed' : 'in_progress', 'watched_pct' => $pct, 'completed' => $now_complete];
  }

  private function after_completion(int $user_id, int $lesson_id, int $programme_id): void {
    do_action('nba_lesson_completed', $user_id, $lesson_id, $programme_id);
    $summary = $this->summary($user_id, $programme_id);
    if ($summary['total'] > 0 && $summary['completed'] >= $summary['total'] && !get_user_meta($user_id, 'nba_completed_' . $programme_id, true)) {
      update_user_meta($user_id, 'nba_completed_' . $programme_id, Time::now());
      do_action('nba_programme_completed', $user_id, $programme_id);
    }
  }

  /**
   * Last activity, for "needs attention" lists and nudges. Written at most
   * every five minutes.
   */
  public function touch(int $user_id): void {
    $last = Time::ts((string) get_user_meta($user_id, 'nba_last_active', true));
    if ($last < time() - 300) {
      update_user_meta($user_id, 'nba_last_active', Time::now());
    }
  }

  /**
   * @return array{completed:int, total:int, pct:int, resume_lesson:int, next_lesson:int}
   */
  public function summary(int $user_id, int $programme_id): array {
    $tree = nba()->curriculum->tree($programme_id);
    $rows = $this->for_programme($user_id, $programme_id);
    $required = $tree['required'];
    $completed = 0;
    foreach ($required as $id) {
      if (isset($rows[$id]) && $rows[$id]->status === 'completed') {
        $completed++;
      }
    }
    // Resume: most recently touched, unfinished curriculum lesson.
    $resume = 0;
    $latest = 0;
    foreach ($rows as $id => $r) {
      if ($r->status !== 'completed' && in_array($id, $tree['lessons'], true) && Time::ts($r->updated_at) > $latest) {
        $latest = Time::ts($r->updated_at);
        $resume = $id;
      }
    }
    // Next: first curriculum lesson not completed.
    $next = 0;
    foreach ($tree['lessons'] as $id) {
      if (!isset($rows[$id]) || $rows[$id]->status !== 'completed') {
        $next = $id;
        break;
      }
    }
    $total = count($required);
    return [
      'completed' => $completed,
      'total' => $total,
      'pct' => $total ? (int) floor($completed * 100 / $total) : 0,
      'resume_lesson' => $resume,
      'next_lesson' => $next,
    ];
  }

  // ------------------------------------------------------- one stream at a time

  /**
   * The newest player to start becomes the only one allowed to play.
   */
  public function claim_stream(int $user_id, string $stream_id): void {
    set_transient('nba_stream_' . $user_id, ['id' => $stream_id, 't' => time()], 120);
  }

  public function stream_allowed(int $user_id, string $stream_id): bool {
    $owner = get_transient('nba_stream_' . $user_id);
    if (!is_array($owner) || ($owner['t'] ?? 0) < time() - 90 || $owner['id'] === $stream_id) {
      $this->claim_stream($user_id, $stream_id);
      return true;
    }
    return false;
  }

  // ------------------------------------------------------- notes

  public function note(int $user_id, int $lesson_id): string {
    global $wpdb;
    return (string) $wpdb->get_var($wpdb->prepare('SELECT body FROM ' . Installer::table('notes') . ' WHERE user_id = %d AND lesson_id = %d', $user_id, $lesson_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
  }

  public function save_note(int $user_id, int $lesson_id, string $body): void {
    global $wpdb;
    $body = sanitize_textarea_field(mb_substr($body, 0, 10000));
    if ($body === '') {
      $wpdb->delete(Installer::table('notes'), ['user_id' => $user_id, 'lesson_id' => $lesson_id]);
      return;
    }
    $wpdb->replace(Installer::table('notes'), ['user_id' => $user_id, 'lesson_id' => $lesson_id, 'body' => $body, 'updated_at' => Time::now()]);
  }

  // ------------------------------------------------------- reports

  /**
   * Completion count per lesson, for the drop-off report.
   *
   * @return array<int, array{started:int, completed:int}>
   */
  public function lesson_stats(int $programme_id): array {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT lesson_id, COUNT(*) started, SUM(status = 'completed') completed FROM {$this->table()} WHERE programme_id = %d GROUP BY lesson_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      $programme_id
    ));
    $out = [];
    foreach ($rows ?: [] as $r) {
      $out[(int) $r->lesson_id] = ['started' => (int) $r->started, 'completed' => (int) $r->completed];
    }
    return $out;
  }
}
