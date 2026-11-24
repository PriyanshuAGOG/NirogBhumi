<?php
namespace NirogBhumi\Academy\Admin;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Enrollment\WooBridge;
use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Media\Importer;
use NirogBhumi\Academy\Notify\Scheduler;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * admin-post.php handlers. Each action has a nonce named "nba_{action}" and
 * a required capability.
 */
final class Handlers {
  public const ACTIONS = [
    'enroll' => 'nba_manage_enrollments',
    'enrollment' => 'nba_manage_enrollments',
    'offer' => 'nba_manage_enrollments',
    'batch' => 'edit_nba_items',
    'device_decide' => 'nba_manage_devices',
    'device_reset' => 'nba_manage_devices',
    'announce' => 'nba_send_announcements',
    'import' => 'nba_manage_academy',
    'export' => 'nba_view_reports',
    'demo' => 'nba_manage_academy',
  ];

  public static function dispatch(string $action, string $cap): void {
    if (!current_user_can($cap)) {
      wp_die(esc_html__('You do not have permission to do that.', 'nirog-academy'), '', ['response' => 403]);
    }
    check_admin_referer('nba_' . $action);
    $method = 'do_' . $action;
    self::$method();
  }

  private static function post(string $key): string {
    return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- verified in dispatch().
  }

  private static function back(string $fallback_page): string {
    return isset($_POST['_back']) ? sanitize_key(wp_unslash($_POST['_back'])) : $fallback_page; // phpcs:ignore WordPress.Security.NonceVerification
  }

  private static function do_enroll(): void {
    $email = sanitize_email(self::post('email'));
    $programme_id = (int) self::post('programme_id');
    $user = get_user_by('email', $email);
    if (!$user) {
      if (!is_email($email)) {
        Admin::redirect('nba-enrollments', __('Enter a valid email address.', 'nirog-academy'), true);
      }
      $user_id = WooBridge::create_user($email, self::post('first_name'), self::post('last_name'), self::post('phone'));
      if (!$user_id) {
        Admin::redirect('nba-enrollments', __('Could not create the account.', 'nirog-academy'), true);
      }
    } else {
      $user_id = $user->ID;
    }
    $starts = Time::local_to_utc(self::post('starts_at'));
    $result = nba()->enrollments->create([
      'user_id' => $user_id,
      'programme_id' => $programme_id,
      'source' => self::post('source') === 'complimentary' ? 'complimentary' : 'manual',
      'cohort_id' => (int) self::post('cohort_id') ?: null,
      'starts_at' => $starts,
      'note' => self::post('note'),
    ]);
    if (is_wp_error($result)) {
      Admin::redirect('nba-enrollments', $result->get_error_message(), true);
    }
    Admin::redirect('nba-participant', __('Enrolled. A welcome email has been sent.', 'nirog-academy'), false, ['user' => $user_id]);
  }

  private static function do_enrollment(): void {
    $id = (int) self::post('id');
    $op = self::post('op');
    $row = nba()->enrollments->get($id);
    if (!$row) {
      Admin::redirect('nba-enrollments', __('Enrollment not found.', 'nirog-academy'), true);
    }
    $ok = match ($op) {
      'pause' => nba()->enrollments->set_status($id, 'paused', self::post('reason')),
      'resume', 'reactivate' => nba()->enrollments->set_status($id, 'active', self::post('reason')),
      'revoke' => nba()->enrollments->set_status($id, 'revoked', self::post('reason') ?: 'Revoked by staff'),
      'extend' => nba()->enrollments->extend($id, (int) self::post('days')),
      'cohort' => nba()->enrollments->move_cohort($id, (int) self::post('cohort_id')),
      'clear_review' => (bool) $GLOBALS['wpdb']->update(Installer::table('enrollments'), ['needs_review' => 0], ['id' => $id]),
      default => false,
    };
    Admin::redirect(
      self::back('nba-participant'),
      $ok ? __('Enrollment updated.', 'nirog-academy') : __('Nothing changed.', 'nirog-academy'),
      !$ok,
      ['user' => (int) $row->user_id]
    );
  }

  private static function do_offer(): void {
    $price = self::post('price');
    $result = WooBridge::create_offer(
      self::post('email'),
      self::post('first_name'),
      self::post('last_name'),
      self::post('phone'),
      (int) self::post('product_id'),
      $price === '' ? null : (float) $price,
      self::post('note')
    );
    if (is_wp_error($result)) {
      Admin::redirect('nba-enrollments', $result->get_error_message(), true, ['tab' => 'offer']);
    }
    /* translators: %d: order number */
    Admin::redirect('nba-enrollments', sprintf(__('Payment link emailed (order #%d). Access opens automatically when they pay.', 'nirog-academy'), $result['order_id']), false, ['tab' => 'offer', 'pay_url' => rawurlencode($result['pay_url'])]);
  }

  private static function do_batch(): void {
    $id = (int) self::post('id');
    $result = nba()->cohorts->save([
      'programme_id' => (int) self::post('programme_id'),
      'name' => self::post('name'),
      'starts_at' => self::post('starts_at'),
      'enroll_opens_at' => self::post('enroll_opens_at'),
      'enroll_closes_at' => self::post('enroll_closes_at'),
      'capacity' => self::post('capacity'),
      'status' => self::post('status'),
      'coach_id' => (int) self::post('coach_id'),
    ], $id);
    if (is_wp_error($result)) {
      Admin::redirect('nba-batches', $result->get_error_message(), true);
    }
    Admin::redirect('nba-batches', __('Batch saved.', 'nirog-academy'));
  }

  private static function do_device_decide(): void {
    $approve = self::post('decision') === 'approve';
    $ok = nba()->devices->decide_request((int) self::post('request_id'), $approve);
    Admin::redirect('nba-devices', $ok ? ($approve ? __('Approved. The participant can now log in on their new device.', 'nirog-academy') : __('Request declined. The participant has been emailed.', 'nirog-academy')) : __('That request was already decided.', 'nirog-academy'), !$ok);
  }

  private static function do_device_reset(): void {
    $user_id = (int) self::post('user_id');
    nba()->devices->admin_reset($user_id, self::post('note'));
    Admin::redirect('nba-participant', __('Device reset. The next device the participant logs in from will be registered.', 'nirog-academy'), false, ['user' => $user_id]);
  }

  private static function do_announce(): void {
    $title = self::post('title');
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $programme_id = (int) self::post('programme_id');
    $cohort_id = (int) self::post('cohort_id');
    if ($title === '' || $message === '') {
      Admin::redirect('nba-announcements', __('Add a title and a message.', 'nirog-academy'), true);
    }
    // Coaches can only write to their own batches.
    if (!current_user_can('nba_manage_enrollments')) {
      $cohort = $cohort_id ? nba()->cohorts->get($cohort_id) : null;
      if (!$cohort || (int) $cohort->coach_id !== get_current_user_id()) {
        Admin::redirect('nba-announcements', __('Coaches can send announcements only to their own batches.', 'nirog-academy'), true);
      }
    }
    $id = wp_insert_post([
      'post_type' => PostTypes::ANNOUNCEMENT,
      'post_status' => 'publish',
      'post_title' => $title,
      'post_content' => $message,
    ]);
    if (is_wp_error($id)) {
      Admin::redirect('nba-announcements', $id->get_error_message(), true);
    }
    update_post_meta($id, '_nba_programme_id', $programme_id);
    update_post_meta($id, '_nba_cohort_id', $cohort_id);
    update_post_meta($id, '_nba_channel_email', empty($_POST['email']) ? '0' : '1'); // phpcs:ignore WordPress.Security.NonceVerification
    $count = count(Scheduler::audience($id));
    update_post_meta($id, '_nba_audience_count', $count);
    Scheduler::queue_announcement($id);
    nba()->audit->log('announcement.sent', 0, 'announcement', $id, ['audience' => $count]);
    /* translators: %d: number of people */
    Admin::redirect('nba-announcements', sprintf(_n('Sending to %d participant.', 'Sending to %d participants. Large groups are sent in batches every 15 minutes.', $count, 'nirog-academy'), $count));
  }

  private static function do_import(): void {
    $results = Importer::run();
    set_transient('nba_import_results_' . get_current_user_id(), $results, 10 * MINUTE_IN_SECONDS);
    $imported = count(array_filter($results, static fn($r) => $r['status'] === 'imported'));
    /* translators: %d: number of videos */
    Admin::redirect('nba-import', sprintf(_n('%d new video imported.', '%d new videos imported.', $imported, 'nirog-academy'), $imported));
  }

  private static function do_export(): void {
    $programme_id = (int) self::post('programme_id');
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="academy-progress-' . gmdate('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['enrollment_id', 'name', 'email', 'phone', 'programme', 'batch', 'status', 'source', 'order_id', 'starts', 'ends', 'lessons_completed', 'lessons_total', 'progress_pct', 'last_active']);
    $page = 1;
    do {
      $result = nba()->enrollments->query(['programme_id' => $programme_id, 'per_page' => 200, 'page' => $page]);
      foreach ($result['rows'] as $row) {
        $summary = nba()->progress->summary((int) $row->user_id, (int) $row->programme_id);
        $cohort = $row->cohort_id ? nba()->cohorts->get((int) $row->cohort_id) : null;
        fputcsv($out, array_map([self::class, 'csv_safe'], [
          $row->id,
          $row->display_name,
          $row->user_email,
          get_user_meta((int) $row->user_id, 'billing_phone', true),
          get_the_title((int) $row->programme_id),
          $cohort->name ?? '',
          $row->status,
          $row->source,
          $row->order_id,
          Time::human($row->starts_at, 'Y-m-d'),
          Time::human($row->ends_at, 'Y-m-d'),
          $summary['completed'],
          $summary['total'],
          $summary['pct'],
          Time::human((string) get_user_meta((int) $row->user_id, 'nba_last_active', true), 'Y-m-d H:i'),
        ]));
      }
      $page++;
    } while (count($result['rows']) === 200);
    fclose($out);
    exit;
  }

  /** Stop spreadsheet formula injection. */
  public static function csv_safe(mixed $value): string {
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
  }

  private static function do_demo(): void {
    $programme_id = Demo::create();
    if (is_wp_error($programme_id)) {
      Admin::redirect('nba-settings', $programme_id->get_error_message(), true);
    }
    wp_safe_redirect(admin_url('post.php?action=edit&post=' . $programme_id));
    exit;
  }
}
