<?php
namespace NirogBhumi\Academy\Enrollment;

use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Batches (cohorts). A batch has a start date. In "Batch" programmes every
 * lesson's "Opens on day N" counts from that date, so everyone moves through
 * the programme together.
 */
final class Cohorts {
  private function table(): string {
    return Installer::table('cohorts');
  }

  public function get(int $id): ?object {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return $row ?: null;
  }

  /** @return object[] */
  public function all(int $programme_id = 0, bool $include_archived = false): array {
    global $wpdb;
    $sql = "SELECT c.*, (SELECT COUNT(*) FROM " . Installer::table('enrollments') . " e WHERE e.cohort_id = c.id AND e.status IN ('active','paused','pending')) AS members FROM {$this->table()} c WHERE 1=1";
    $params = [];
    if ($programme_id) {
      $sql .= ' AND c.programme_id = %d';
      $params[] = $programme_id;
    }
    if (!$include_archived) {
      $sql .= " AND c.status <> 'archived'";
    }
    $sql .= ' ORDER BY c.starts_at DESC';
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    return ($params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql)) ?: [];
  }

  public function save(array $data, int $id = 0): int|\WP_Error {
    global $wpdb;
    $programme_id = (int) ($data['programme_id'] ?? 0);
    $name = sanitize_text_field((string) ($data['name'] ?? ''));
    $starts_at = Time::local_to_utc((string) ($data['starts_at'] ?? ''));
    if (!$programme_id || $name === '' || !$starts_at) {
      return new \WP_Error('nba_cohort', __('A batch needs a programme, a name and a start date.', 'nirog-academy'));
    }
    $row = [
      'programme_id' => $programme_id,
      'name' => $name,
      'starts_at' => $starts_at,
      'enroll_opens_at' => Time::local_to_utc((string) ($data['enroll_opens_at'] ?? '')),
      'enroll_closes_at' => Time::local_to_utc((string) ($data['enroll_closes_at'] ?? '')),
      'capacity' => !empty($data['capacity']) ? max(1, (int) $data['capacity']) : null,
      'status' => in_array($data['status'] ?? '', ['open', 'closed', 'archived'], true) ? $data['status'] : 'open',
      'coach_id' => !empty($data['coach_id']) ? (int) $data['coach_id'] : null,
    ];
    if ($id) {
      $wpdb->update($this->table(), $row, ['id' => $id]);
      return $id;
    }
    $row['created_at'] = Time::now();
    $wpdb->insert($this->table(), $row);
    return (int) $wpdb->insert_id;
  }

  public function members(int $cohort_id): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . Installer::table('enrollments') . " WHERE cohort_id = %d AND status IN ('active','paused','pending')", $cohort_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
  }

  /**
   * The batch a new buyer joins: open for enrollment now, not full, starting
   * soonest. Batches already running are included while their enrollment
   * window is open.
   */
  public function next_open(int $programme_id): ?object {
    $now = time();
    $candidates = array_filter($this->all($programme_id), function ($c) use ($now) {
      if ($c->status !== 'open') {
        return false;
      }
      if ($c->enroll_opens_at && Time::ts($c->enroll_opens_at) > $now) {
        return false;
      }
      // Without an explicit closing date, enrollment closes when the batch starts.
      $closes = $c->enroll_closes_at ? Time::ts($c->enroll_closes_at) : Time::ts($c->starts_at) + DAY_IN_SECONDS;
      if ($closes <= $now) {
        return false;
      }
      return !$c->capacity || (int) $c->members < (int) $c->capacity;
    });
    usort($candidates, static fn($a, $b) => Time::ts($a->starts_at) <=> Time::ts($b->starts_at));
    return $candidates[0] ?? null;
  }
}
