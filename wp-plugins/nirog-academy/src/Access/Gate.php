<?php
namespace NirogBhumi\Academy\Access;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Install\Roles;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * The single access check.
 *
 * Every protected thing — lesson pages, REST calls, downloads, video
 * playlists and decryption keys — asks this class. Hiding links is never the
 * protection; this is.
 *
 * Order of checks (stops at the first failure):
 *   1. launch      the Academy is open (or the user is staff)
 *   2. login       the user is logged in
 *   3. device      this browser is the registered device
 *   4. enrollment  an active enrollment exists
 *   5. window      today is inside the access period
 *   6. release     the lesson's opening day has arrived
 *   7. sequence    the previous lesson is complete (sequential programmes)
 *   8. ack         the health and safety acknowledgement is accepted
 */
final class Gate {
  /** @var array<string, AccessResult> */
  private array $memo = [];

  public function check_lesson(int $user_id, int $lesson_id, bool $register_device = true): AccessResult {
    $key = $user_id . ':' . $lesson_id;
    if (isset($this->memo[$key])) {
      return $this->memo[$key];
    }
    $result = $this->evaluate($user_id, $lesson_id, $register_device);
    $result = apply_filters('nba_access_check', $result, $user_id, $lesson_id);
    if (!$result->allowed && $user_id && !in_array($result->code, ['release', 'sequence', 'ack'], true)) {
      $this->log_denial($user_id, $lesson_id, $result->code);
    }
    return $this->memo[$key] = $result;
  }

  private function evaluate(int $user_id, int $lesson_id, bool $register_device): AccessResult {
    $lesson = get_post($lesson_id);
    // Staff can open drafts too, so they can check content before publishing.
    if ($lesson && $lesson->post_type === PostTypes::LESSON && $lesson->post_status === 'draft' && Roles::is_staff($user_id)) {
      return AccessResult::allow((int) get_post_meta($lesson_id, '_nba_programme_id', true));
    }
    if (!$lesson || $lesson->post_type !== PostTypes::LESSON || $lesson->post_status !== 'publish') {
      return AccessResult::deny('not_found', __('This lesson is not available.', 'nirog-academy'));
    }
    $programme_id = (int) get_post_meta($lesson_id, '_nba_programme_id', true);
    if (!$programme_id || get_post_status($programme_id) !== 'publish') {
      return AccessResult::deny('not_found', __('This lesson is not available.', 'nirog-academy'));
    }
    $is_staff = Roles::is_staff($user_id);

    // 1. Launch switch.
    $mode = (string) nba()->settings->get('launch_mode');
    $is_preview = get_post_meta($lesson_id, '_nba_preview', true) === '1';
    // Same rule as the page router: staff and the demo participant may look
    // around before launch.
    if (\NirogBhumi\Academy\Frontend\Router::is_locked_for($user_id)) {
      return AccessResult::deny('launch', __('The Academy opens soon. We will email you when it is ready.', 'nirog-academy'));
    }

    // 2. Login.
    if (!$user_id) {
      return AccessResult::deny('login', __('Please log in to continue.', 'nirog-academy'));
    }

    // 3. Device.
    $device = nba()->devices->verify($user_id, $register_device);
    if (!$device['ok']) {
      return AccessResult::deny('device', __('Your programme is registered to another device.', 'nirog-academy'));
    }

    if ($is_staff) {
      return AccessResult::allow($programme_id);
    }

    // Free preview lessons need only a login (and the Academy to be in preview or open).
    if ($is_preview) {
      return AccessResult::allow($programme_id);
    }
    if ($mode === 'preview') {
      return AccessResult::deny('launch', __('Only free preview lessons are open right now. Full access starts soon.', 'nirog-academy'));
    }

    // 4–5. Enrollment and access window.
    $enrollment = nba()->enrollments->current($user_id, $programme_id);
    if (!$enrollment) {
      return AccessResult::deny('enrollment', __('This lesson is part of a programme you have not joined yet.', 'nirog-academy'), ['programme_id' => $programme_id]);
    }
    if ($enrollment->status === 'paused') {
      return AccessResult::deny('paused', __('Your access is paused. Contact us when you are ready to continue.', 'nirog-academy'), ['enrollment' => $enrollment]);
    }
    if ($enrollment->status === 'pending') {
      return AccessResult::deny('pending', __('Your place is confirmed. We are assigning you to a batch and will email you the start date.', 'nirog-academy'), ['enrollment' => $enrollment]);
    }
    if (!nba()->enrollments->is_open($enrollment)) {
      $msg = $enrollment->status === 'revoked'
        ? __('Access to this programme has been closed. Contact us if you think this is a mistake.', 'nirog-academy')
        : __('Your access period for this programme has ended.', 'nirog-academy');
      return AccessResult::deny('window', $msg, ['enrollment' => $enrollment]);
    }

    // 6. Release date.
    $opens = $this->opens_at($enrollment, $lesson_id);
    if ($opens && Time::ts($opens) > time()) {
      return AccessResult::deny('release', sprintf(
        /* translators: %s: date */
        __('This lesson opens on %s.', 'nirog-academy'),
        Time::human($opens, 'l, j M')
      ), ['opens_at' => $opens]);
    }

    // 7. Sequence.
    if (get_post_meta($programme_id, '_nba_sequential', true) === '1' && get_post_meta($lesson_id, '_nba_placement', true) !== 'bonus') {
      [$prev] = nba()->curriculum->neighbours($lesson_id);
      if ($prev && get_post_meta($prev, '_nba_required', true) !== '0' && !nba()->progress->is_complete($user_id, $prev)) {
        return AccessResult::deny('sequence', sprintf(
          /* translators: %s: lesson title */
          __('Complete "%s" first.', 'nirog-academy'),
          get_the_title($prev)
        ), ['previous' => $prev]);
      }
    }

    // 8. Health and safety acknowledgement (checked last, so locked lessons
    // still show as locked rather than asking for the acknowledgement).
    if (!$this->has_acknowledged($user_id, $programme_id)) {
      return AccessResult::deny('ack', __('Please read and accept the health and safety note before your first lesson.', 'nirog-academy'), ['programme_id' => $programme_id]);
    }

    return AccessResult::allow($programme_id, $enrollment);
  }

  /**
   * When a lesson opens for this enrollment, in UTC. Null = already open.
   */
  public function opens_at(object $enrollment, int $lesson_id): ?string {
    $base = nba()->enrollments->drip_base($enrollment);
    if ($base === null) {
      return null;
    }
    $day = (int) get_post_meta($lesson_id, '_nba_release_day', true);
    if ($day <= 0) {
      // Day 0 opens at the start itself (the batch start for batch programmes).
      return Time::ts($base) > time() ? $base : null;
    }
    return Time::local_day_start_after($base, $day);
  }

  /**
   * Can this user see the programme overview (curriculum) at all?
   */
  public function check_programme(int $user_id, int $programme_id): AccessResult {
    if (get_post_status($programme_id) !== 'publish') {
      return AccessResult::deny('not_found', __('This programme is not available.', 'nirog-academy'));
    }
    if (!$user_id) {
      return AccessResult::deny('login', __('Please log in to continue.', 'nirog-academy'));
    }
    if (!nba()->devices->verify($user_id)['ok']) {
      return AccessResult::deny('device', __('Your programme is registered to another device.', 'nirog-academy'));
    }
    if (Roles::is_staff($user_id)) {
      return AccessResult::allow($programme_id);
    }
    if (\NirogBhumi\Academy\Frontend\Router::is_locked_for($user_id)) {
      return AccessResult::deny('launch', __('The Academy opens soon. We will email you when it is ready.', 'nirog-academy'));
    }
    $enrollment = nba()->enrollments->current($user_id, $programme_id);
    if (!$enrollment || !in_array($enrollment->status, ['active', 'paused', 'pending'], true) || ($enrollment->status === 'active' && !nba()->enrollments->is_open($enrollment))) {
      return AccessResult::deny('enrollment', __('You have not joined this programme yet.', 'nirog-academy'), ['programme_id' => $programme_id]);
    }
    return AccessResult::allow($programme_id, $enrollment);
  }

  public function has_acknowledged(int $user_id, int $programme_id): bool {
    $version = (string) get_post_meta($programme_id, '_nba_ack_version', true);
    if ($version === '') {
      return true; // No acknowledgement configured for this programme.
    }
    global $wpdb;
    $table = \NirogBhumi\Academy\Install\Installer::table('acknowledgements');
    return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM $table WHERE user_id = %d AND programme_id = %d AND version = %s", $user_id, $programme_id, $version)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  public function acknowledge(int $user_id, int $programme_id): void {
    $version = (string) get_post_meta($programme_id, '_nba_ack_version', true);
    if ($version === '') {
      return;
    }
    global $wpdb;
    $table = \NirogBhumi\Academy\Install\Installer::table('acknowledgements');
    $wpdb->replace($table, [
      'user_id' => $user_id,
      'programme_id' => $programme_id,
      'version' => $version,
      'accepted_at' => Time::now(),
      'ip_hash' => \NirogBhumi\Academy\Support\Crypto::ip_hash(),
    ]);
    nba()->audit->log('ack.accepted', $user_id, 'programme', $programme_id, ['version' => $version]);
  }

  private function log_denial(int $user_id, int $lesson_id, string $code): void {
    // Log device blocks and window denials once per hour per user, to keep the log useful.
    $key = 'nba_denied_' . $user_id . '_' . $code;
    if (get_transient($key)) {
      return;
    }
    set_transient($key, 1, HOUR_IN_SECONDS);
    nba()->audit->log($code === 'device' ? 'device.blocked' : 'access.denied', $user_id, 'lesson', $lesson_id, ['code' => $code]);
  }
}
