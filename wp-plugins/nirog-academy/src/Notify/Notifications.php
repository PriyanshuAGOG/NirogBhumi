<?php
namespace NirogBhumi\Academy\Notify;

use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * In-app notifications and branded emails.
 *
 * Every participant-facing message is written in the participant's chosen
 * language. Email preferences let participants turn off lesson, reminder and
 * announcement emails; account and security emails are always sent.
 */
final class Notifications {
  public const PREFS_META = 'nba_email_prefs';

  public function register(): void {
    add_action('nba_enrollment_created', [$this, 'on_enrollment_created'], 20, 3);
    add_action('nba_device_changed', [$this, 'on_device_changed'], 10, 2);
    add_action('nba_device_request_created', [$this, 'on_device_request'], 10, 2);
    add_action('nba_device_request_decided', [$this, 'on_device_request_decided'], 10, 3);
    add_action('nba_programme_completed', [$this, 'on_programme_completed'], 10, 2);
  }

  private function table(): string {
    return Installer::table('notifications');
  }

  // ------------------------------------------------------------ in-app

  public function create(int $user_id, string $type, string $title, string $body = '', string $url = '', ?string $dedupe = null): int {
    global $wpdb;
    if ($dedupe && $this->already_sent($user_id, substr($dedupe, 0, 120))) {
      return 0;
    }
    $ok = $wpdb->insert($this->table(), [
      'user_id' => $user_id,
      'type' => substr($type, 0, 40),
      'title' => mb_substr($title, 0, 250),
      'body' => $body,
      'url' => mb_substr($url, 0, 250),
      'dedupe_key' => $dedupe ? substr($dedupe, 0, 120) : null,
      'created_at' => Time::now(),
    ]);
    return $ok ? (int) $wpdb->insert_id : 0;
  }

  public function already_sent(int $user_id, string $dedupe): bool {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$this->table()} WHERE user_id = %d AND dedupe_key = %s", $user_id, $dedupe)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  /** @return object[] */
  public function list(int $user_id, int $limit = 30): array {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, $limit)) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  public function unread_count(int $user_id): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table()} WHERE user_id = %d AND read_at IS NULL", $user_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  public function mark_read(int $user_id, ?int $id = null): void {
    global $wpdb;
    if ($id) {
      $wpdb->update($this->table(), ['read_at' => Time::now()], ['id' => $id, 'user_id' => $user_id]);
      return;
    }
    $wpdb->query($wpdb->prepare("UPDATE {$this->table()} SET read_at = %s WHERE user_id = %d AND read_at IS NULL", Time::now(), $user_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  // ------------------------------------------------------------ preferences

  public static function prefs(int $user_id): array {
    $stored = get_user_meta($user_id, self::PREFS_META, true);
    return array_merge(['lessons' => 1, 'reminders' => 1, 'announcements' => 1], is_array($stored) ? $stored : []);
  }

  public static function wants(int $user_id, string $kind): bool {
    return !empty(self::prefs($user_id)[$kind]);
  }

  // ------------------------------------------------------------ email

  /**
   * Send a branded email. $paragraphs are plain text; they are escaped here.
   *
   * @param string[] $paragraphs
   */
  public function email(int $user_id, string $subject, string $heading, array $paragraphs, string $button_label = '', string $button_url = ''): bool {
    $user = get_userdata($user_id);
    if (!$user || !is_email($user->user_email)) {
      return false;
    }
    $html = $this->render_email($user->first_name ?: $user->display_name, $heading, $paragraphs, $button_label, $button_url);
    return $this->send_raw($user->user_email, $subject, $html);
  }

  public function send_raw(string $to, string $subject, string $html): bool {
    $from_name = (string) nba()->settings->get('email_from_name');
    $set_name = static fn() => $from_name;
    $set_type = static fn() => 'text/html';
    add_filter('wp_mail_from_name', $set_name);
    add_filter('wp_mail_content_type', $set_type);
    $sent = wp_mail($to, $subject, $html);
    remove_filter('wp_mail_from_name', $set_name);
    remove_filter('wp_mail_content_type', $set_type);
    return (bool) $sent;
  }

  public function render_email(string $name, string $heading, array $paragraphs, string $button_label = '', string $button_url = ''): string {
    $support = (string) nba()->settings->get('support_email');
    $area = (string) nba()->settings->get('area_name');
    ob_start();
    $template = locate_template('nirog-academy/email.php') ?: NBA_DIR . 'templates/email.php';
    include $template;
    return (string) ob_get_clean();
  }

  // ------------------------------------------------------------ event handlers

  public function on_enrollment_created(int $enrollment_id, int $user_id, int $programme_id): void {
    $enrollment = nba()->enrollments->get($enrollment_id);
    if (!$enrollment) {
      return;
    }
    I18n::with_user_locale($user_id, function () use ($enrollment, $user_id, $programme_id): void {
      $title = I18n::field($programme_id, 'title');
      $needs_password = (bool) get_user_meta($user_id, 'nba_needs_password', true);
      $url = $needs_password ? $this->set_password_url($user_id) : Router::url();
      $start_note = '';
      if (Time::ts($enrollment->starts_at) > time() + HOUR_IN_SECONDS) {
        /* translators: %s: date */
        $start_note = sprintf(__('Your batch begins on %s. You can log in now and finish the orientation.', 'nirog-academy'), Time::human($enrollment->starts_at, 'l, j F Y'));
      }
      if ($enrollment->status === 'pending') {
        $start_note = __('We will place you in the next batch and email you the start date.', 'nirog-academy');
      }
      $this->create(
        $user_id,
        'welcome',
        /* translators: %s: programme name */
        sprintf(__('Welcome to %s', 'nirog-academy'), $title),
        $start_note ?: __('Your lessons are ready. Start with the first one when you have a quiet moment.', 'nirog-academy'),
        Router::url('programme/' . get_post_field('post_name', $programme_id)),
        'welcome-' . $enrollment->id
      );
      $paragraphs = [
        /* translators: %s: programme name */
        sprintf(__('You are enrolled in %s. Everything you need is in the Academy: your lessons, bonus videos, downloads and live session times.', 'nirog-academy'), $title),
        /* translators: %s: date */
        sprintf(__('Your access lasts until %s.', 'nirog-academy'), Time::human($enrollment->ends_at, 'j F Y')),
      ];
      if ($start_note) {
        $paragraphs[] = $start_note;
      }
      $paragraphs[] = __('Important: your programme works on one device. The first phone or computer you log in from becomes your device, so please use the one you plan to practise with.', 'nirog-academy');
      if ($needs_password) {
        $paragraphs[] = __('Start by choosing your password. The button below works for 24 hours; after that, use "Forgot password" on the login page.', 'nirog-academy');
      }
      $this->email(
        $user_id,
        /* translators: %s: programme name */
        sprintf(__('Welcome to %s', 'nirog-academy'), $title),
        __('Your programme is ready', 'nirog-academy'),
        $paragraphs,
        $needs_password ? __('Choose your password', 'nirog-academy') : __('Open the Academy', 'nirog-academy'),
        $url
      );
    });
  }

  public function set_password_url(int $user_id): string {
    $user = get_userdata($user_id);
    if (!$user) {
      return Router::url('login');
    }
    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
      return Router::url('login');
    }
    return add_query_arg(['key' => $key, 'login' => rawurlencode($user->user_login)], Router::url('set-password'));
  }

  public function email_device_code(int $user_id, string $code): bool|\WP_Error {
    $sent = I18n::with_user_locale($user_id, fn() => $this->email(
      $user_id,
      __('Your device change code', 'nirog-academy'),
      __('Confirm your new device', 'nirog-academy'),
      [
        /* translators: %s: 6-digit code */
        sprintf(__('Your code is: %s', 'nirog-academy'), $code),
        /* translators: %d: minutes */
        sprintf(__('It works for %d minutes. Enter it on the device you want to use from now on.', 'nirog-academy'), nba()->settings->int('device_code_ttl_minutes')),
        __('If you did not ask for this, ignore this email and consider changing your password.', 'nirog-academy'),
      ]
    ));
    return $sent ? true : new \WP_Error('nba_mail', __('We could not send the email. Please contact us.', 'nirog-academy'));
  }

  public function on_device_changed(int $user_id, string $by): void {
    I18n::with_user_locale($user_id, function () use ($user_id, $by): void {
      $this->create($user_id, 'device', __('Your programme moved to a new device', 'nirog-academy'), __('If this was not you, contact us straight away.', 'nirog-academy'), Router::url('profile'));
      $this->email(
        $user_id,
        __('Your programme moved to a new device', 'nirog-academy'),
        __('Device changed', 'nirog-academy'),
        [
          $by === 'admin'
            ? __('Our team has reset your registered device. The next phone or computer you log in from will become your device.', 'nirog-academy')
            : __('Your programme is now registered to the device you just confirmed. Your previous device has been signed out.', 'nirog-academy'),
          __('If this was not you, reply to this email or contact us straight away.', 'nirog-academy'),
        ],
        __('Open the Academy', 'nirog-academy'),
        Router::url()
      );
    });
  }

  public function on_device_request(int $request_id, int $user_id): void {
    $user = get_userdata($user_id);
    $to = (string) nba()->settings->get('support_email');
    if (!$user || !is_email($to)) {
      return;
    }
    $html = $this->render_email(
      __('team', 'nirog-academy'),
      __('Device change request', 'nirog-academy'),
      [
        /* translators: 1: name, 2: email */
        sprintf(__('%1$s (%2$s) has asked to change their registered device.', 'nirog-academy'), $user->display_name, $user->user_email),
      ],
      __('Review the request', 'nirog-academy'),
      admin_url('admin.php?page=nba-devices')
    );
    $this->send_raw($to, __('Academy: device change request', 'nirog-academy'), $html);
  }

  public function on_device_request_decided(int $request_id, int $user_id, bool $approved): void {
    if ($approved) {
      return; // on_device_changed() already tells them.
    }
    I18n::with_user_locale($user_id, function () use ($user_id): void {
      $this->create($user_id, 'device', __('Your device change request was not approved', 'nirog-academy'), __('Please contact us so we can help.', 'nirog-academy'), Router::url('help'));
      $this->email($user_id, __('About your device change request', 'nirog-academy'), __('Request not approved', 'nirog-academy'), [
        __('We could not approve your device change request this time. Please reply to this email so we can help you continue your programme.', 'nirog-academy'),
      ]);
    });
  }

  public function on_programme_completed(int $user_id, int $programme_id): void {
    I18n::with_user_locale($user_id, function () use ($user_id, $programme_id): void {
      $this->create(
        $user_id,
        'milestone',
        /* translators: %s: programme name */
        sprintf(__('You completed every lesson in %s', 'nirog-academy'), I18n::field($programme_id, 'title')),
        __('Keep practising daily. You can revisit any lesson while your access lasts.', 'nirog-academy'),
        Router::url(),
        'completed-' . $programme_id
      );
    });
  }
}
