<?php
namespace NirogBhumi\Academy\Device;

use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Install\Roles;
use NirogBhumi\Academy\Support\Crypto;
use NirogBhumi\Academy\Support\RateLimiter;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * One-device rule.
 *
 * A browser cannot reveal which physical phone or laptop it runs on, so a
 * "device" here is one browser on one device, identified by a random secret in
 * an HttpOnly cookie. Only a keyed hash of that secret is stored.
 *
 *  - The first browser a participant uses is registered automatically.
 *  - Any other browser is blocked until the device is changed.
 *  - Participants can change device themselves (email code) once per
 *    interval; beyond that, staff approve the change.
 *  - Logging in on the registered device ends sessions everywhere else.
 */
final class Devices {
  public const COOKIE = 'nba_device';

  public function register(): void {
    add_action('set_logged_in_cookie', [$this, 'single_session'], 10, 6);
  }

  private function table(): string {
    return Installer::table('devices');
  }

  public function is_exempt(int $user_id): bool {
    return Roles::is_staff($user_id) && nba()->settings->int('staff_device_exempt') === 1;
  }

  private function cookie_token(): string {
    $raw = isset($_COOKIE[self::COOKIE]) ? (string) wp_unslash($_COOKIE[self::COOKIE]) : '';
    return preg_match('/^[A-Za-z0-9_-]{40,64}$/', $raw) ? $raw : '';
  }

  private function hash(string $token): string {
    return Crypto::hash($token, 'device');
  }

  /**
   * Whether this request can still set the device cookie. Filterable for
   * tests run from the command line.
   */
  private function can_set_cookie(): bool {
    return (bool) apply_filters('nba_can_set_device_cookie', !headers_sent());
  }

  private function set_cookie(string $token): void {
    $_COOKIE[self::COOKIE] = $token;
    if (headers_sent()) {
      return;
    }
    setcookie(self::COOKIE, $token, [
      'expires' => time() + 5 * YEAR_IN_SECONDS,
      'path' => COOKIEPATH ?: '/',
      'domain' => COOKIE_DOMAIN ?: '',
      'secure' => is_ssl(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }

  /** @return object[] */
  public function active(int $user_id): array {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE user_id = %d AND revoked_at IS NULL ORDER BY registered_at ASC", $user_id)) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  /** @return object[] */
  public function history(int $user_id, int $limit = 20): array {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, $limit)) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  /**
   * The registered device matching this browser, if any.
   */
  public function current_device(int $user_id): ?object {
    $token = $this->cookie_token();
    if ($token === '') {
      return null;
    }
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE token_hash = %s AND user_id = %d AND revoked_at IS NULL", $this->hash($token), $user_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return $row ?: null;
  }

  /**
   * Decide whether this browser may be used, registering it if the account
   * still has a free device slot.
   *
   * @return array{ok: bool, device: ?object, registered_now: bool}
   */
  public function verify(int $user_id, bool $allow_register = true): array {
    if (isset($this->verified[$user_id]) && $this->verified[$user_id]['ok']) {
      return $this->verified[$user_id];
    }
    return $this->verified[$user_id] = $this->do_verify($user_id, $allow_register);
  }

  /** @var array<int, array> Per-request cache for verify(). */
  private array $verified = [];

  public function forget(): void {
    $this->verified = [];
  }

  private function do_verify(int $user_id, bool $allow_register): array {
    if ($this->is_exempt($user_id)) {
      return ['ok' => true, 'device' => null, 'registered_now' => false];
    }
    $device = $this->current_device($user_id);
    if ($device) {
      if (Time::ts($device->last_seen_at) < time() - 300) {
        global $wpdb;
        $wpdb->update($this->table(), ['last_seen_at' => Time::now()], ['id' => $device->id]);
      }
      return ['ok' => true, 'device' => $device, 'registered_now' => false];
    }
    if ($allow_register && count($this->active($user_id)) < nba()->settings->int('max_devices') && $this->can_set_cookie()) {
      $device = $this->register_this_browser($user_id);
      return ['ok' => (bool) $device, 'device' => $device, 'registered_now' => (bool) $device];
    }
    return ['ok' => false, 'device' => null, 'registered_now' => false];
  }

  public function register_this_browser(int $user_id): ?object {
    global $wpdb;
    $token = Crypto::token(32);
    $label = self::describe_user_agent(isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '');
    $ok = $wpdb->insert($this->table(), [
      'user_id' => $user_id,
      'token_hash' => $this->hash($token),
      'label' => $label,
      'registered_at' => Time::now(),
      'last_seen_at' => Time::now(),
    ]);
    if (!$ok) {
      return null;
    }
    $this->set_cookie($token);
    $id = (int) $wpdb->insert_id;
    nba()->audit->log('device.registered', $user_id, 'device', $id, ['label' => $label]);
    do_action('nba_device_registered', $user_id, $id);
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  public function revoke_all(int $user_id, string $reason, int $by = 0): int {
    global $wpdb;
    $count = (int) $wpdb->query($wpdb->prepare(
      "UPDATE {$this->table()} SET revoked_at = %s, revoked_by = %d, revoke_reason = %s WHERE user_id = %d AND revoked_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      Time::now(),
      $by,
      substr($reason, 0, 40),
      $user_id
    ));
    // Sign the old device out.
    \WP_Session_Tokens::get_instance($user_id)->destroy_all();
    $this->forget();
    return $count;
  }

  public function revoke(int $device_id, string $reason, int $by = 0): void {
    global $wpdb;
    $wpdb->update($this->table(), ['revoked_at' => Time::now(), 'revoked_by' => $by, 'revoke_reason' => substr($reason, 0, 40)], ['id' => $device_id, 'revoked_at' => null]);
  }

  /**
   * Staff reset: frees the slot so the next browser the participant uses is
   * registered.
   */
  public function admin_reset(int $user_id, string $note = ''): void {
    $this->revoke_all($user_id, 'admin_reset', get_current_user_id());
    update_user_meta($user_id, 'nba_last_device_reset', Time::now());
    nba()->audit->log('device.admin_reset', $user_id, 'user', $user_id, ['note' => $note]);
    do_action('nba_device_changed', $user_id, 'admin');
  }

  /**
   * When a participant logs in, end their sessions everywhere else, so an
   * account cannot stay logged in on two devices.
   */
  public function single_session($cookie, $expire, $expiration, $user_id, $scheme, $token): void {
    $user_id = (int) $user_id;
    if (!$user_id || Roles::is_staff($user_id) || !$token) {
      return;
    }
    $user = get_userdata($user_id);
    if (!$user || !in_array('nba_participant', (array) $user->roles, true)) {
      return;
    }
    \WP_Session_Tokens::get_instance($user_id)->destroy_others((string) $token);
  }

  // ------------------------------------------------------------ self-service

  public function last_reset(int $user_id): int {
    return Time::ts((string) get_user_meta($user_id, 'nba_last_device_reset', true));
  }

  public function can_self_reset(int $user_id): bool {
    $interval = nba()->settings->int('self_reset_interval_days');
    if ($interval <= 0) {
      return false;
    }
    $last = $this->last_reset($user_id);
    return !$last || $last + $interval * DAY_IN_SECONDS <= time();
  }

  public function next_self_reset(int $user_id): int {
    $last = $this->last_reset($user_id);
    return $last ? $last + nba()->settings->int('self_reset_interval_days') * DAY_IN_SECONDS : time();
  }

  /**
   * Email a 6-digit code that confirms the device change.
   */
  public function send_code(int $user_id): bool|\WP_Error {
    if (!RateLimiter::hit('device_code', (string) $user_id, 3, HOUR_IN_SECONDS)) {
      return new \WP_Error('nba_rate', __('Too many codes requested. Please try again in an hour.', 'nirog-academy'));
    }
    $code = Crypto::code(6);
    update_user_meta($user_id, 'nba_device_code', [
      'hash' => Crypto::hash($code, 'device_code_' . $user_id),
      'expires' => time() + nba()->settings->int('device_code_ttl_minutes') * MINUTE_IN_SECONDS,
      'attempts' => 0,
    ]);
    return nba()->notifications->email_device_code($user_id, $code);
  }

  public function confirm_code(int $user_id, string $code): bool|\WP_Error {
    $data = get_user_meta($user_id, 'nba_device_code', true);
    if (!is_array($data) || ($data['expires'] ?? 0) < time()) {
      return new \WP_Error('nba_code', __('This code has expired. Request a new one.', 'nirog-academy'));
    }
    if (($data['attempts'] ?? 0) >= 5) {
      delete_user_meta($user_id, 'nba_device_code');
      return new \WP_Error('nba_code', __('Too many wrong attempts. Request a new code.', 'nirog-academy'));
    }
    $code = preg_replace('/\D+/', '', $code);
    if (!hash_equals((string) $data['hash'], Crypto::hash((string) $code, 'device_code_' . $user_id))) {
      $data['attempts'] = ($data['attempts'] ?? 0) + 1;
      update_user_meta($user_id, 'nba_device_code', $data);
      return new \WP_Error('nba_code', __('That code is not right. Check the email and try again.', 'nirog-academy'));
    }
    delete_user_meta($user_id, 'nba_device_code');
    if (!$this->can_self_reset($user_id)) {
      return new \WP_Error('nba_limit', __('You have already changed device recently. Your request has been sent to our team instead.', 'nirog-academy'));
    }

    $old = $this->active($user_id);
    $this->revoke_all($user_id, 'self_reset', $user_id);
    // revoke_all() ended every session; log this browser back in.
    wp_set_auth_cookie($user_id, true, is_ssl());
    $device = $this->register_this_browser($user_id);
    update_user_meta($user_id, 'nba_last_device_reset', Time::now());
    nba()->audit->log('device.self_reset', $user_id, 'device', $device ? (int) $device->id : 0, [
      'old' => array_map(static fn($d) => $d->label, $old),
      'new' => $device->label ?? '',
    ]);
    do_action('nba_device_changed', $user_id, 'self');
    return true;
  }

  // ------------------------------------------------------------ staff requests

  public function create_request(int $user_id, string $reason): int {
    global $wpdb;
    $table = Installer::table('device_requests');
    $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d AND status = 'pending'", $user_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ($existing) {
      return $existing;
    }
    $wpdb->insert($table, [
      'user_id' => $user_id,
      'status' => 'pending',
      'reason' => sanitize_textarea_field($reason),
      'device_label' => self::describe_user_agent(isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : ''),
      'created_at' => Time::now(),
    ]);
    $id = (int) $wpdb->insert_id;
    nba()->audit->log('device.request', $user_id, 'device_request', $id, ['reason' => $reason]);
    do_action('nba_device_request_created', $id, $user_id);
    return $id;
  }

  public function pending_request(int $user_id): ?object {
    global $wpdb;
    $table = Installer::table('device_requests');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d AND status = 'pending' ORDER BY id DESC LIMIT 1", $user_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return $row ?: null;
  }

  /** @return object[] */
  public function requests(string $status = 'pending', int $limit = 100): array {
    global $wpdb;
    $table = Installer::table('device_requests');
    return $wpdb->get_results($wpdb->prepare("SELECT r.*, u.user_email, u.display_name FROM $table r LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id WHERE r.status = %s ORDER BY r.id DESC LIMIT %d", $status, $limit)) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  public function decide_request(int $request_id, bool $approve): bool {
    global $wpdb;
    $table = Installer::table('device_requests');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $request_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if (!$row || $row->status !== 'pending') {
      return false;
    }
    $wpdb->update($table, [
      'status' => $approve ? 'approved' : 'denied',
      'decided_at' => Time::now(),
      'decided_by' => get_current_user_id(),
    ], ['id' => $request_id]);
    if ($approve) {
      $this->admin_reset((int) $row->user_id, 'request #' . $request_id);
    }
    nba()->audit->log('device.request_decided', (int) $row->user_id, 'device_request', $request_id, ['approved' => $approve]);
    do_action('nba_device_request_decided', $request_id, (int) $row->user_id, $approve);
    return true;
  }

  public function reset_count(int $user_id, int $days = 365): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->table()} WHERE user_id = %d AND revoked_at IS NOT NULL AND revoked_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      $user_id,
      Time::add_days(Time::now(), -$days)
    ));
  }

  /**
   * "Chrome on Android" style label so people recognise their device.
   */
  public static function describe_user_agent(string $ua): string {
    $browser = match (true) {
      str_contains($ua, 'Edg/') => 'Edge',
      str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
      str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
      str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
      str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome/') => 'Chrome',
      str_contains($ua, 'Safari/') => 'Safari',
      default => __('Browser', 'nirog-academy'),
    };
    $os = match (true) {
      str_contains($ua, 'iPhone') => 'iPhone',
      str_contains($ua, 'iPad') => 'iPad',
      str_contains($ua, 'Android') => 'Android',
      str_contains($ua, 'Windows') => 'Windows',
      str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'Mac',
      str_contains($ua, 'CrOS') => 'Chromebook',
      str_contains($ua, 'Linux') => 'Linux',
      default => __('unknown device', 'nirog-academy'),
    };
    /* translators: 1: browser name, 2: operating system */
    return sprintf(__('%1$s on %2$s', 'nirog-academy'), $browser, $os);
  }
}
