<?php
namespace NirogBhumi\Academy\Audit;

use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Support\Crypto;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Append-only record of sensitive actions: enrollment changes, device
 * changes, refunds, settings changes and blocked access attempts.
 */
final class AuditLog {
  public function log(string $action, int $subject_user_id = 0, string $target_type = '', int $target_id = 0, array $context = []): void {
    global $wpdb;
    $wpdb->insert(Installer::table('audit_log'), [
      'actor_id' => get_current_user_id(),
      'action' => substr($action, 0, 60),
      'subject_user_id' => $subject_user_id,
      'target_type' => substr($target_type, 0, 30),
      'target_id' => $target_id,
      'context' => $context ? wp_json_encode($context) : null,
      'ip_hash' => Crypto::ip_hash(),
      'created_at' => Time::now(),
    ]);
    do_action('nba_audit', $action, $subject_user_id, $target_type, $target_id, $context);
  }

  /**
   * @return array{rows: array<int, object>, total: int}
   */
  public function query(array $args = []): array {
    global $wpdb;
    $table = Installer::table('audit_log');
    $where = ['1=1'];
    $params = [];
    if (!empty($args['subject_user_id'])) {
      $where[] = 'subject_user_id = %d';
      $params[] = (int) $args['subject_user_id'];
    }
    if (!empty($args['action'])) {
      $where[] = 'action = %s';
      $params[] = (string) $args['action'];
    }
    $per_page = max(1, min(200, (int) ($args['per_page'] ?? 50)));
    $offset = max(0, ((int) ($args['page'] ?? 1) - 1) * $per_page);
    $where_sql = implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
    $rows_sql = "SELECT * FROM $table WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table name is internal; values are prepared.
    $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));
    $rows = $wpdb->get_results($wpdb->prepare($rows_sql, array_merge($params, [$per_page, $offset])));
    // phpcs:enable
    return ['rows' => $rows ?: [], 'total' => $total];
  }

  public function prune(int $days): void {
    global $wpdb;
    $table = Installer::table('audit_log');
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", Time::add_days(Time::now(), -$days))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  public static function label(string $action): string {
    $labels = [
      'enrollment.created' => __('Enrollment created', 'nirog-academy'),
      'enrollment.status' => __('Enrollment status changed', 'nirog-academy'),
      'enrollment.extended' => __('Access extended', 'nirog-academy'),
      'enrollment.cohort' => __('Moved to another batch', 'nirog-academy'),
      'enrollment.partial_refund' => __('Partial refund, needs review', 'nirog-academy'),
      'device.registered' => __('Device registered', 'nirog-academy'),
      'device.blocked' => __('Blocked: another device', 'nirog-academy'),
      'device.self_reset' => __('Device changed by participant', 'nirog-academy'),
      'device.admin_reset' => __('Device reset by staff', 'nirog-academy'),
      'device.request' => __('Device change requested', 'nirog-academy'),
      'device.request_decided' => __('Device request decided', 'nirog-academy'),
      'access.denied' => __('Access denied', 'nirog-academy'),
      'login.failed' => __('Failed login', 'nirog-academy'),
      'login.magic_link' => __('Login link sent', 'nirog-academy'),
      'offer.created' => __('Payment offer created', 'nirog-academy'),
      'announcement.sent' => __('Announcement sent', 'nirog-academy'),
      'settings.updated' => __('Settings updated', 'nirog-academy'),
      'ack.accepted' => __('Safety acknowledgement accepted', 'nirog-academy'),
    ];
    return $labels[$action] ?? $action;
  }
}
