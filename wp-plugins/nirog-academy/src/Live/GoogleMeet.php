<?php
namespace NirogBhumi\Academy\Live;

use NirogBhumi\Academy\Admin\Admin;
use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Google Meet links for live sessions, created through the Google Calendar
 * API with the organisation's own Google account.
 *
 * One-time set-up (Settings → Google Meet): the team creates an OAuth client
 * in their own Google Cloud project (free), pastes its Client ID and secret,
 * and presses "Connect Google account". From then on "Create Google Meet
 * link" on a live session creates a Calendar event with a Meet link in that
 * account's calendar (the host is invited), and later changes to the
 * session's title, time or length update the event.
 *
 * Only the refresh token is stored, encrypted with the site's secret keys.
 * Scope: calendar.events (events only, not the whole calendar) + email.
 */
final class GoogleMeet {
  public const OPTION = 'nba_google';
  private const SCOPE = 'openid email https://www.googleapis.com/auth/calendar.events';
  private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
  private const TOKEN = 'https://oauth2.googleapis.com/token';
  private const REVOKE = 'https://oauth2.googleapis.com/revoke';
  private const EVENTS = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

  public static function register(): void {
    add_action('admin_post_nba_google_callback', [self::class, 'callback']);
    add_action('wp_trash_post', [self::class, 'on_trash']);
  }

  // ------------------------------------------------------------ settings

  /** @return array{client_id:string,client_secret:string,refresh:string,email:string,connected_at:string} */
  public static function settings(): array {
    $s = get_option(self::OPTION, []);
    $s = is_array($s) ? $s : [];
    return [
      'client_id' => (string) ($s['client_id'] ?? ''),
      'client_secret' => self::decrypt((string) ($s['client_secret'] ?? '')),
      'refresh' => self::decrypt((string) ($s['refresh'] ?? '')),
      'email' => (string) ($s['email'] ?? ''),
      'connected_at' => (string) ($s['connected_at'] ?? ''),
    ];
  }

  private static function store(array $values): void {
    $raw = get_option(self::OPTION, []);
    $raw = is_array($raw) ? $raw : [];
    foreach ($values as $k => $v) {
      $raw[$k] = in_array($k, ['client_secret', 'refresh'], true) && $v !== '' ? self::encrypt((string) $v) : $v;
    }
    update_option(self::OPTION, $raw, false);
  }

  public static function configured(): bool {
    $s = self::settings();
    return $s['client_id'] !== '' && $s['client_secret'] !== '';
  }

  public static function connected(): bool {
    return self::configured() && self::settings()['refresh'] !== '';
  }

  public static function redirect_uri(): string {
    return admin_url('admin-post.php?action=nba_google_callback');
  }

  public static function save_client(string $id, string $secret): void {
    $values = ['client_id' => trim($id)];
    if (trim($secret) !== '') {
      $values['client_secret'] = trim($secret);
    }
    self::store($values);
  }

  public static function auth_url(): string {
    $state = wp_generate_password(24, false);
    set_transient('nba_google_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS);
    return add_query_arg(array_map('rawurlencode', [
      'client_id' => self::settings()['client_id'],
      'redirect_uri' => self::redirect_uri(),
      'response_type' => 'code',
      'scope' => self::SCOPE,
      'access_type' => 'offline',
      'prompt' => 'consent',
      'include_granted_scopes' => 'true',
      'state' => $state,
    ]), self::AUTH);
  }

  /** Google sends the team member back here after they allow access. */
  public static function callback(): void {
    if (!current_user_can('nba_manage_academy')) {
      wp_die(esc_html__('You do not have permission to do that.', 'nirog-academy'), '', ['response' => 403]);
    }
    $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
    $expected = (string) get_transient('nba_google_state_' . get_current_user_id());
    delete_transient('nba_google_state_' . get_current_user_id());
    if ($expected === '' || !hash_equals($expected, $state)) {
      Admin::redirect('nba-settings', __('Google connection expired. Please press "Connect Google account" again.', 'nirog-academy'), true, ['tab' => 'google']);
    }
    if (isset($_GET['error'])) {
      Admin::redirect('nba-settings', __('Google access was not allowed, so nothing was connected.', 'nirog-academy'), true, ['tab' => 'google']);
    }
    $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
    $s = self::settings();
    $res = wp_remote_post(self::TOKEN, ['timeout' => 15, 'body' => [
      'code' => $code,
      'client_id' => $s['client_id'],
      'client_secret' => $s['client_secret'],
      'redirect_uri' => self::redirect_uri(),
      'grant_type' => 'authorization_code',
    ]]);
    $data = self::json($res);
    if (empty($data['refresh_token'])) {
      $why = is_wp_error($res) ? $res->get_error_message() : (string) ($data['error_description'] ?? $data['error'] ?? '');
      Admin::redirect('nba-settings', sprintf(
        /* translators: %s: error from Google */
        __('Google did not connect: %s', 'nirog-academy'),
        $why ?: __('no permission was returned', 'nirog-academy')
      ), true, ['tab' => 'google']);
    }
    self::store([
      'refresh' => (string) $data['refresh_token'],
      'email' => self::email_from_id_token((string) ($data['id_token'] ?? '')),
      'connected_at' => Time::now(),
    ]);
    if (!empty($data['access_token'])) {
      set_transient('nba_google_at', (string) $data['access_token'], max(60, (int) ($data['expires_in'] ?? 3600) - 120));
    }
    nba()->audit->log('google.connected', get_current_user_id(), 'settings', 0);
    Admin::redirect('nba-settings', __('Google account connected. You can now create Google Meet links from any live session.', 'nirog-academy'), false, ['tab' => 'google']);
  }

  public static function disconnect(): void {
    $s = self::settings();
    if ($s['refresh'] !== '') {
      wp_remote_post(self::REVOKE, ['timeout' => 10, 'body' => ['token' => $s['refresh']]]);
    }
    self::store(['refresh' => '', 'email' => '', 'connected_at' => '']);
    delete_transient('nba_google_at');
    nba()->audit->log('google.disconnected', get_current_user_id(), 'settings', 0);
  }

  // ------------------------------------------------------------ meet links

  /**
   * Create a Calendar event with a Google Meet link for a live session and
   * use its link as the session's join link.
   *
   * @return string|\WP_Error The Meet link.
   */
  public static function create_for_session(int $session_id): string|\WP_Error {
    if (!self::connected()) {
      return new \WP_Error('nba_google', __('Connect a Google account first: Settings → Google Meet.', 'nirog-academy'), ['status' => 400]);
    }
    $existing = (string) get_post_meta($session_id, '_nba_gcal_event_id', true);
    if ($existing !== '') {
      $link = (string) get_post_meta($session_id, '_nba_join_url', true);
      if ($link !== '') {
        return $link;
      }
    }
    $body = self::event_body($session_id);
    $body['conferenceData'] = ['createRequest' => [
      'requestId' => 'nba-' . $session_id . '-' . wp_generate_password(8, false),
      'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
    ]];
    $event = self::request('POST', self::EVENTS . '?conferenceDataVersion=1&sendUpdates=all', $body);
    if (is_wp_error($event)) {
      return $event;
    }
    $link = self::meet_link($event);
    // Google can take a moment to attach the Meet; ask once more.
    for ($i = 0; $link === '' && !empty($event['id']) && $i < 3; $i++) {
      usleep(700000);
      $again = self::request('GET', self::EVENTS . '/' . rawurlencode((string) $event['id']));
      $link = is_wp_error($again) ? '' : self::meet_link($again);
    }
    if (empty($event['id'])) {
      return new \WP_Error('nba_google', __('Google did not create the event. Please try again.', 'nirog-academy'), ['status' => 502]);
    }
    update_post_meta($session_id, '_nba_gcal_event_id', (string) $event['id']);
    update_post_meta($session_id, '_nba_gcal_fingerprint', self::fingerprint($session_id));
    if ($link === '') {
      return new \WP_Error('nba_google', __('The calendar event was created, but Google has not added a Meet link yet. Press the button again in a minute.', 'nirog-academy'), ['status' => 502]);
    }
    update_post_meta($session_id, '_nba_join_url', esc_url_raw($link));
    update_post_meta($session_id, '_nba_live_kind', 'meeting');
    nba()->audit->log('live.meet_created', get_current_user_id(), 'live', $session_id);
    return $link;
  }

  /** After a session is saved: keep its Calendar event's title and time in step. */
  public static function sync_session(int $session_id): void {
    $event_id = (string) get_post_meta($session_id, '_nba_gcal_event_id', true);
    if ($event_id === '' || !self::connected()) {
      return;
    }
    $print = self::fingerprint($session_id);
    if ($print === (string) get_post_meta($session_id, '_nba_gcal_fingerprint', true)) {
      return;
    }
    $result = self::request('PATCH', self::EVENTS . '/' . rawurlencode($event_id) . '?sendUpdates=all', self::event_body($session_id));
    if (!is_wp_error($result)) {
      update_post_meta($session_id, '_nba_gcal_fingerprint', $print);
    }
  }

  /** A deleted session removes its Calendar event (the Meet link stops being needed). */
  public static function on_trash(int $post_id): void {
    if (get_post_type($post_id) !== PostTypes::LIVE) {
      return;
    }
    $event_id = (string) get_post_meta($post_id, '_nba_gcal_event_id', true);
    if ($event_id !== '' && self::connected()) {
      self::request('DELETE', self::EVENTS . '/' . rawurlencode($event_id) . '?sendUpdates=all');
      delete_post_meta($post_id, '_nba_gcal_event_id');
    }
  }

  /** @return array<string,mixed> */
  private static function event_body(int $session_id): array {
    $post = get_post($session_id);
    $start = Time::ts((string) get_post_meta($session_id, '_nba_starts_at', true));
    $minutes = max(5, (int) get_post_meta($session_id, '_nba_duration_min', true) ?: 60);
    $cancelled = get_post_meta($session_id, '_nba_cancelled', true) === '1';
    $tz = wp_timezone_string();
    $body = [
      'summary' => ($cancelled ? '[Cancelled] ' : '') . ($post ? $post->post_title : '') . ' · Nirog Bhumi',
      'description' => trim(($post ? wp_strip_all_tags($post->post_content) : '') . "\n\n" . sprintf(
        /* translators: %s: link */
        __('Participants join from their course page: %s', 'nirog-academy'),
        LiveSessions::url($session_id)
      )),
      'start' => ['dateTime' => gmdate('c', $start), 'timeZone' => preg_match('~^[A-Za-z_]+/[A-Za-z_]+~', $tz) ? $tz : 'UTC'],
      'end' => ['dateTime' => gmdate('c', $start + $minutes * MINUTE_IN_SECONDS), 'timeZone' => preg_match('~^[A-Za-z_]+/[A-Za-z_]+~', $tz) ? $tz : 'UTC'],
      'guestsCanInviteOthers' => false,
      'guestsCanSeeOtherGuests' => false,
    ];
    $host = (int) get_post_meta($session_id, '_nba_host_id', true);
    $host_user = $host ? get_userdata($host) : null;
    if ($host_user && is_email($host_user->user_email) && strcasecmp($host_user->user_email, self::settings()['email']) !== 0) {
      $body['attendees'] = [['email' => $host_user->user_email]];
    }
    return $body;
  }

  private static function fingerprint(int $session_id): string {
    return md5(wp_json_encode(self::event_body($session_id)));
  }

  /** @param array<string,mixed> $event */
  private static function meet_link(array $event): string {
    if (!empty($event['hangoutLink'])) {
      return (string) $event['hangoutLink'];
    }
    foreach ((array) ($event['conferenceData']['entryPoints'] ?? []) as $entry) {
      if (($entry['entryPointType'] ?? '') === 'video' && !empty($entry['uri'])) {
        return (string) $entry['uri'];
      }
    }
    return '';
  }

  // ------------------------------------------------------------ http

  private static function access_token(): string|\WP_Error {
    $cached = (string) get_transient('nba_google_at');
    if ($cached !== '') {
      return $cached;
    }
    $s = self::settings();
    $data = self::json(wp_remote_post(self::TOKEN, ['timeout' => 15, 'body' => [
      'client_id' => $s['client_id'],
      'client_secret' => $s['client_secret'],
      'refresh_token' => $s['refresh'],
      'grant_type' => 'refresh_token',
    ]]));
    if (empty($data['access_token'])) {
      if (($data['error'] ?? '') === 'invalid_grant') {
        // Access was removed in the Google account: ask the team to reconnect.
        self::store(['refresh' => '', 'email' => '', 'connected_at' => '']);
        return new \WP_Error('nba_google', __('Google access has ended. Connect the Google account again in Settings → Google Meet.', 'nirog-academy'), ['status' => 400]);
      }
      return new \WP_Error('nba_google', __('Could not reach Google. Please try again in a minute.', 'nirog-academy'), ['status' => 502]);
    }
    set_transient('nba_google_at', (string) $data['access_token'], max(60, (int) ($data['expires_in'] ?? 3600) - 120));
    return (string) $data['access_token'];
  }

  /** @return array<string,mixed>|\WP_Error */
  private static function request(string $method, string $url, ?array $body = null): array|\WP_Error {
    $token = self::access_token();
    if (is_wp_error($token)) {
      return $token;
    }
    $args = ['method' => $method, 'timeout' => 20, 'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json']];
    if ($body !== null) {
      $args['body'] = wp_json_encode($body);
    }
    $res = wp_remote_request($url, $args);
    if (is_wp_error($res)) {
      return new \WP_Error('nba_google', __('Could not reach Google. Please try again in a minute.', 'nirog-academy'), ['status' => 502]);
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code === 401) {
      delete_transient('nba_google_at');
    }
    if ($code === 204 || ($method === 'DELETE' && $code === 410)) {
      return [];
    }
    $data = self::json($res);
    if ($code < 200 || $code >= 300) {
      $msg = (string) ($data['error']['message'] ?? '');
      return new \WP_Error('nba_google', sprintf(
        /* translators: %s: message from Google */
        __('Google said: %s', 'nirog-academy'),
        $msg ?: 'HTTP ' . $code
      ), ['status' => 502]);
    }
    return $data;
  }

  /** @return array<string,mixed> */
  private static function json(mixed $res): array {
    if (is_wp_error($res)) {
      return [];
    }
    $data = json_decode((string) wp_remote_retrieve_body($res), true);
    return is_array($data) ? $data : [];
  }

  private static function email_from_id_token(string $jwt): string {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
      return '';
    }
    $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4)), true);
    return is_array($payload) && !empty($payload['email']) ? sanitize_email((string) $payload['email']) : '';
  }

  // ------------------------------------------------------------ secrets at rest

  private static function key(): string {
    return hash('sha256', wp_salt('auth') . '|nba-google', true);
  }

  private static function encrypt(string $plain): string {
    if (!function_exists('openssl_encrypt')) {
      return 'plain:' . base64_encode($plain);
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $cipher);
  }

  private static function decrypt(string $stored): string {
    if ($stored === '') {
      return '';
    }
    if (str_starts_with($stored, 'plain:')) {
      return (string) base64_decode(substr($stored, 6));
    }
    if (!str_starts_with($stored, 'v1:') || !function_exists('openssl_decrypt')) {
      return '';
    }
    $raw = (string) base64_decode(substr($stored, 3));
    if (strlen($raw) < 29) {
      return '';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
  }
}
