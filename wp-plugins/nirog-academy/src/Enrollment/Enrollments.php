<?php
namespace NirogBhumi\Academy\Enrollment;

use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Install\Roles;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Enrollments: who has access to which programme, from when, until when.
 *
 * Statuses:
 *   active   access is open (within starts_at … ends_at)
 *   paused   access is stopped; the paused time is added back on resume
 *   expired  ends_at has passed
 *   revoked  refunded, cancelled or removed by staff
 *   pending  waiting for something (for example a batch to be assigned)
 */
final class Enrollments {
  public const STATUSES = ['active', 'paused', 'expired', 'revoked', 'pending'];

  /** @var array<string, ?object> Per-request cache for current(). */
  private array $memo = [];

  public function register(): void {
    add_action('nba_daily', [$this, 'expire_due']);
  }

  private function table(): string {
    return Installer::table('enrollments');
  }

  public static function status_label(string $status): string {
    return match ($status) {
      'active' => __('Active', 'nirog-academy'),
      'paused' => __('Paused', 'nirog-academy'),
      'expired' => __('Ended', 'nirog-academy'),
      'revoked' => __('Revoked', 'nirog-academy'),
      'pending' => __('Pending', 'nirog-academy'),
      default => $status,
    };
  }

  public function get(int $id): ?object {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return $row ?: null;
  }

  /** @return object[] */
  public function for_user(int $user_id): array {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE user_id = %d ORDER BY created_at DESC", $user_id)) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  /** @return object[] */
  public function for_order(int $order_id): array {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE order_id = %d", $order_id)) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  /**
   * The enrollment that decides access for this user and programme: the
   * active one if there is one, otherwise the most recent.
   */
  public function current(int $user_id, int $programme_id): ?object {
    global $wpdb;
    $memo_key = $user_id . ':' . $programme_id;
    if (array_key_exists($memo_key, $this->memo)) {
      return $this->memo[$memo_key];
    }
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$this->table()} WHERE user_id = %d AND programme_id = %d ORDER BY (status = 'active') DESC, (status = 'paused') DESC, ends_at DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      $user_id,
      $programme_id
    ));
    return $this->memo[$memo_key] = ($row ?: null);
  }

  /** Clear the per-request cache after writes. */
  public function forget(): void {
    $this->memo = [];
  }

  public function is_open(?object $row): bool {
    if (!$row || $row->status !== 'active') {
      return false;
    }
    $now = time();
    if (Time::ts($row->starts_at) > $now && !$this->is_cohort_wait($row)) {
      return false;
    }
    return !$row->ends_at || Time::ts($row->ends_at) > $now;
  }

  /**
   * A participant who joined a batch that has not started yet still has an
   * open enrollment: they can log in, see the welcome and orientation, and
   * lessons unlock when the batch begins.
   */
  private function is_cohort_wait(object $row): bool {
    return (int) $row->cohort_id > 0;
  }

  public static function access_days(int $programme_id): int {
    $days = (int) get_post_meta($programme_id, '_nba_access_days', true);
    return $days > 0 ? $days : nba()->settings->int('default_access_days');
  }

  /**
   * Create an enrollment, or extend the open one if the person already has
   * access to this programme (a renewal or a second purchase).
   *
   * @param array{user_id:int, programme_id:int, source?:string, order_id?:int|null, cohort_id?:int|null, starts_at?:string|null, status?:string, note?:string} $args
   * @return int|\WP_Error Enrollment ID.
   */
  public function create(array $args): int|\WP_Error {
    global $wpdb;
    $user_id = (int) ($args['user_id'] ?? 0);
    $programme_id = (int) ($args['programme_id'] ?? 0);
    if (!$user_id || !get_userdata($user_id)) {
      return new \WP_Error('nba_user', __('That user does not exist.', 'nirog-academy'));
    }
    if (!$programme_id || get_post_type($programme_id) !== 'nba_programme') {
      return new \WP_Error('nba_programme', __('That programme does not exist.', 'nirog-academy'));
    }
    $order_id = !empty($args['order_id']) ? (int) $args['order_id'] : null;
    if ($order_id) {
      foreach ($this->for_order($order_id) as $existing) {
        if ((int) $existing->programme_id === $programme_id) {
          return (int) $existing->id; // Idempotent: the same order never enrolls twice.
        }
      }
    }

    $access_days = self::access_days($programme_id) + nba()->settings->int('grace_days');
    $open = $this->current($user_id, $programme_id);
    if ($open && $this->is_open($open)) {
      $from = max(time(), Time::ts($open->ends_at));
      $new_end = gmdate('Y-m-d H:i:s', $from + $access_days * DAY_IN_SECONDS);
      $wpdb->update($this->table(), [
        'ends_at' => $new_end,
        'note' => trim(($open->note ?? '') . "\n" . sprintf('Extended by %d days (%s%s).', $access_days, $args['source'] ?? 'manual', $order_id ? ' order #' . $order_id : '')),
        'updated_at' => Time::now(),
      ], ['id' => $open->id]);
      $this->forget();
      if ($order_id) {
        $this->remember_order($order_id, (int) $open->id);
      }
      nba()->audit->log('enrollment.extended', $user_id, 'enrollment', (int) $open->id, ['days' => $access_days, 'order_id' => $order_id, 'new_end' => $new_end]);
      do_action('nba_enrollment_extended', (int) $open->id, $access_days);
      return (int) $open->id;
    }

    $cohort_id = isset($args['cohort_id']) ? (int) $args['cohort_id'] : 0;
    $drip = (string) get_post_meta($programme_id, '_nba_drip_mode', true);
    $status = in_array($args['status'] ?? 'active', self::STATUSES, true) ? ($args['status'] ?? 'active') : 'active';
    if (!$cohort_id && $drip === 'cohort' && get_post_meta($programme_id, '_nba_cohort_assignment', true) !== 'manual') {
      $next = nba()->cohorts->next_open($programme_id);
      $cohort_id = $next ? (int) $next->id : 0;
    }
    if ($drip === 'cohort' && !$cohort_id) {
      $status = 'pending'; // Staff will place them in a batch.
    }

    $starts_at = !empty($args['starts_at']) ? (string) $args['starts_at'] : Time::now();
    if ($cohort_id) {
      $cohort = nba()->cohorts->get($cohort_id);
      // Access runs a full term from the later of today or the batch start.
      if ($cohort && Time::ts($cohort->starts_at) > Time::ts($starts_at)) {
        $starts_at = $cohort->starts_at;
      }
    }
    $ends_at = Time::add_days($starts_at, $access_days);

    $ok = $wpdb->insert($this->table(), [
      'user_id' => $user_id,
      'programme_id' => $programme_id,
      'cohort_id' => $cohort_id ?: null,
      'order_id' => $order_id,
      'source' => sanitize_key($args['source'] ?? 'manual'),
      'status' => $status,
      'starts_at' => $starts_at,
      'ends_at' => $ends_at,
      'paused_days' => 0,
      'needs_review' => $status === 'pending' ? 1 : 0,
      'note' => isset($args['note']) ? sanitize_textarea_field($args['note']) : null,
      'created_at' => Time::now(),
      'updated_at' => Time::now(),
    ]);
    if (!$ok) {
      return new \WP_Error('nba_db', __('Could not save the enrollment.', 'nirog-academy'));
    }
    $id = (int) $wpdb->insert_id;
    $this->forget();
    Roles::make_participant($user_id);
    if ($order_id) {
      $this->remember_order($order_id, $id);
    }
    nba()->audit->log('enrollment.created', $user_id, 'enrollment', $id, [
      'programme_id' => $programme_id, 'source' => $args['source'] ?? 'manual', 'order_id' => $order_id, 'cohort_id' => $cohort_id, 'status' => $status,
    ]);
    do_action('nba_enrollment_created', $id, $user_id, $programme_id);
    return $id;
  }

  private function remember_order(int $order_id, int $enrollment_id): void {
    if (!function_exists('wc_get_order')) {
      return;
    }
    $order = wc_get_order($order_id);
    if (!$order) {
      return;
    }
    $ids = array_map('intval', (array) $order->get_meta('_nba_enrollment_ids'));
    if (!in_array($enrollment_id, $ids, true)) {
      $ids[] = $enrollment_id;
      $order->update_meta_data('_nba_enrollment_ids', $ids);
      $order->save_meta_data();
    }
  }

  public function set_status(int $id, string $status, string $reason = ''): bool {
    global $wpdb;
    $row = $this->get($id);
    if (!$row || !in_array($status, self::STATUSES, true) || $row->status === $status) {
      return false;
    }
    $data = ['status' => $status, 'updated_at' => Time::now()];
    if ($status === 'paused') {
      $data['paused_at'] = Time::now();
    }
    if ($row->status === 'paused' && $status === 'active' && $row->paused_at) {
      // Give back the paused time.
      $days = max(0, (int) ceil((time() - Time::ts($row->paused_at)) / DAY_IN_SECONDS));
      $data['paused_days'] = (int) $row->paused_days + $days;
      $data['ends_at'] = Time::add_days((string) $row->ends_at, $days);
      $data['paused_at'] = null;
    }
    if ($status === 'active') {
      $data['needs_review'] = 0;
    }
    $wpdb->update($this->table(), $data, ['id' => $id]);
    $this->forget();
    nba()->audit->log('enrollment.status', (int) $row->user_id, 'enrollment', $id, ['from' => $row->status, 'to' => $status, 'reason' => $reason]);
    do_action('nba_enrollment_status_changed', $id, $row->status, $status);
    return true;
  }

  public function extend(int $id, int $days): bool {
    global $wpdb;
    $row = $this->get($id);
    if (!$row || $days === 0) {
      return false;
    }
    $base = $row->ends_at ?: Time::now();
    if ($days > 0 && Time::ts($base) < time()) {
      $base = Time::now(); // Extending an ended enrollment counts from today.
    }
    $data = ['ends_at' => Time::add_days($base, $days), 'updated_at' => Time::now()];
    if ($row->status === 'expired' && Time::ts($data['ends_at']) > time()) {
      $data['status'] = 'active';
    }
    $wpdb->update($this->table(), $data, ['id' => $id]);
    $this->forget();
    nba()->audit->log('enrollment.extended', (int) $row->user_id, 'enrollment', $id, ['days' => $days, 'new_end' => $data['ends_at']]);
    return true;
  }

  public function move_cohort(int $id, int $cohort_id): bool {
    global $wpdb;
    $row = $this->get($id);
    if (!$row) {
      return false;
    }
    $data = ['cohort_id' => $cohort_id ?: null, 'updated_at' => Time::now()];
    if ($row->status === 'pending' && $cohort_id) {
      $data['status'] = 'active';
      $data['needs_review'] = 0;
      $cohort = nba()->cohorts->get($cohort_id);
      if ($cohort && Time::ts($cohort->starts_at) > Time::ts($row->starts_at)) {
        $data['starts_at'] = $cohort->starts_at;
        $data['ends_at'] = Time::add_days($cohort->starts_at, self::access_days((int) $row->programme_id));
      }
    }
    $wpdb->update($this->table(), $data, ['id' => $id]);
    $this->forget();
    nba()->audit->log('enrollment.cohort', (int) $row->user_id, 'enrollment', $id, ['from' => $row->cohort_id, 'to' => $cohort_id]);
    return true;
  }

  public function flag_review(int $id, string $reason): void {
    global $wpdb;
    $row = $this->get($id);
    if (!$row) {
      return;
    }
    $wpdb->update($this->table(), ['needs_review' => 1, 'note' => trim(($row->note ?? '') . "\n" . $reason), 'updated_at' => Time::now()], ['id' => $id]);
  }

  /**
   * Admin listing with filters.
   *
   * @return array{rows: object[], total: int}
   */
  public function query(array $args): array {
    global $wpdb;
    $users = $wpdb->users;
    $where = ['1=1'];
    $params = [];
    foreach (['programme_id', 'cohort_id', 'user_id'] as $key) {
      if (!empty($args[$key])) {
        $where[] = "e.$key = %d";
        $params[] = (int) $args[$key];
      }
    }
    if (!empty($args['status']) && in_array($args['status'], self::STATUSES, true)) {
      $where[] = 'e.status = %s';
      $params[] = $args['status'];
    }
    if (!empty($args['needs_review'])) {
      $where[] = 'e.needs_review = 1';
    }
    if (!empty($args['search'])) {
      $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
      $where[] = '(u.user_email LIKE %s OR u.display_name LIKE %s OR u.user_login LIKE %s)';
      array_push($params, $like, $like, $like);
    }
    $orderby = in_array($args['orderby'] ?? '', ['created_at', 'ends_at', 'starts_at'], true) ? $args['orderby'] : 'created_at';
    $order = strtoupper((string) ($args['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
    $per_page = max(1, min(200, (int) ($args['per_page'] ?? 25)));
    $offset = max(0, ((int) ($args['page'] ?? 1) - 1) * $per_page);
    $where_sql = implode(' AND ', $where);
    $from = "{$this->table()} e LEFT JOIN $users u ON u.ID = e.user_id";

    // phpcs:disable WordPress.DB.PreparedSQL -- identifiers are internal; values are prepared.
    $count_sql = "SELECT COUNT(*) FROM $from WHERE $where_sql";
    $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT e.*, u.user_email, u.display_name FROM $from WHERE $where_sql ORDER BY e.$orderby $order, e.id DESC LIMIT %d OFFSET %d",
      array_merge($params, [$per_page, $offset])
    ));
    // phpcs:enable
    return ['rows' => $rows ?: [], 'total' => $total];
  }

  /** @return object[] Active, open enrollments (for scheduled jobs). */
  public function all_active(int $programme_id = 0, int $cohort_id = 0): array {
    global $wpdb;
    $sql = "SELECT * FROM {$this->table()} WHERE status = 'active' AND (ends_at IS NULL OR ends_at > %s)";
    $params = [Time::now()];
    if ($programme_id) {
      $sql .= ' AND programme_id = %d';
      $params[] = $programme_id;
    }
    if ($cohort_id) {
      $sql .= ' AND cohort_id = %d';
      $params[] = $cohort_id;
    }
    return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
  }

  public function count_by_status(): array {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT status, COUNT(*) n FROM {$this->table()} GROUP BY status"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $out = array_fill_keys(self::STATUSES, 0);
    foreach ($rows ?: [] as $r) {
      $out[$r->status] = (int) $r->n;
    }
    return $out;
  }

  public function expire_due(): void {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$this->table()} WHERE status = 'active' AND ends_at IS NOT NULL AND ends_at <= %s", Time::now())); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    foreach ($rows ?: [] as $r) {
      $this->set_status((int) $r->id, 'expired', 'Access period ended');
    }
  }

  /**
   * The day lessons count from: the batch start date for batch programmes,
   * the participant's own start date for self-paced ones, null when
   * everything is available at once.
   */
  public function drip_base(object $row): ?string {
    $mode = (string) get_post_meta((int) $row->programme_id, '_nba_drip_mode', true) ?: 'enrollment';
    if ($mode === 'none') {
      return null;
    }
    if ($mode === 'cohort') {
      $cohort = $row->cohort_id ? nba()->cohorts->get((int) $row->cohort_id) : null;
      return $cohort ? $cohort->starts_at : $row->starts_at;
    }
    return $row->starts_at;
  }

  /**
   * Programme IDs this user can open right now.
   *
   * @return int[]
   */
  public function open_programmes(int $user_id): array {
    $ids = [];
    foreach ($this->for_user($user_id) as $row) {
      if ($this->is_open($row) && get_post_status((int) $row->programme_id) === 'publish') {
        $ids[(int) $row->programme_id] = (int) $row->programme_id;
      }
    }
    return array_values($ids);
  }
}
