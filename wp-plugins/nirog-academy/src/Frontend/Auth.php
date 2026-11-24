<?php
namespace NirogBhumi\Academy\Frontend;

use NirogBhumi\Academy\Install\Roles;
use NirogBhumi\Academy\Support\Crypto;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\RateLimiter;

defined('ABSPATH') || exit;

/**
 * Login, one-time login links, forgotten password and set password, styled as
 * part of the Academy. Participants never see /wp-admin/.
 */
final class Auth {
  private const MAGIC_META = 'nba_magic_login';

  public static function register(): void {
    add_action('admin_init', [self::class, 'keep_participants_out_of_admin'], 1);
    add_filter('show_admin_bar', [self::class, 'admin_bar']);
    add_filter('login_redirect', [self::class, 'login_redirect'], 20, 3);
    add_filter('woocommerce_login_redirect', [self::class, 'woo_login_redirect'], 20, 2);
    add_filter('rest_endpoints', [self::class, 'hide_user_endpoints']);
  }

  private static function is_participant_only(int $user_id): bool {
    if (!$user_id || Roles::is_staff($user_id)) {
      return false;
    }
    $user = get_userdata($user_id);
    return $user && !$user->has_cap('edit_posts') && in_array('nba_participant', (array) $user->roles, true);
  }

  public static function keep_participants_out_of_admin(): void {
    if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
      return;
    }
    if (self::is_participant_only(get_current_user_id())) {
      wp_safe_redirect(Router::url());
      exit;
    }
  }

  public static function admin_bar(bool $show): bool {
    return self::is_participant_only(get_current_user_id()) ? false : $show;
  }

  public static function login_redirect(string $redirect_to, string $requested, $user): string {
    if ($user instanceof \WP_User && self::is_participant_only($user->ID) && (!$requested || str_contains($requested, 'wp-admin'))) {
      return Router::url();
    }
    return $redirect_to;
  }

  public static function woo_login_redirect(string $redirect, $user): string {
    if ($user instanceof \WP_User && self::is_participant_only($user->ID) && nba()->enrollments->open_programmes($user->ID)) {
      return Router::url();
    }
    return $redirect;
  }

  /**
   * Stop anonymous visitors listing user accounts through the REST API.
   */
  public static function hide_user_endpoints(array $endpoints): array {
    if (!is_user_logged_in()) {
      unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
  }

  private static function redirect_target(): string {
    $raw = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $target = $raw !== '' ? wp_validate_redirect($raw, Router::url()) : '';
    if ($target === '' || str_contains($target, '/' . Router::slug() . '/login')) {
      return Router::url();
    }
    return $target;
  }

  // ------------------------------------------------------------ views

  public static function view(string $view): void {
    switch ($view) {
      case 'logout':
        if (is_user_logged_in() && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'nba_logout')) {
          wp_logout();
        }
        wp_safe_redirect(add_query_arg('loggedout', '1', Router::url('login')));
        exit;

      case 'login':
        self::maybe_magic_login();
        if (is_user_logged_in()) {
          wp_safe_redirect(self::redirect_target());
          exit;
        }
        View::page('login', [
          'redirect_to' => self::redirect_target(),
          'error' => Actions::flash('error'),
          'notice' => Actions::flash('notice') ?: (isset($_GET['loggedout']) ? __('You have logged out.', 'nirog-academy') : ''), // phpcs:ignore WordPress.Security.NonceVerification
        ], ['title' => __('Log in', 'nirog-academy'), 'bare' => true]);

      case 'forgot':
        View::page('forgot', ['notice' => Actions::flash('notice'), 'error' => Actions::flash('error')], ['title' => __('Forgot password', 'nirog-academy'), 'bare' => true]);

      case 'set-password':
        // phpcs:disable WordPress.Security.NonceVerification -- the reset key is the credential.
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $login = isset($_GET['login']) ? sanitize_user(rawurldecode(wp_unslash($_GET['login']))) : '';
        // phpcs:enable
        $user = $key && $login ? check_password_reset_key($key, $login) : new \WP_Error('invalid');
        View::page('set-password', [
          'valid' => !is_wp_error($user),
          'key' => $key,
          'login' => $login,
          'error' => Actions::flash('error'),
        ], ['title' => __('Choose your password', 'nirog-academy'), 'bare' => true]);
    }
  }

  // ------------------------------------------------------------ handlers

  public static function handle_login(): void {
    $email = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- verified in Actions.
    $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitised.
    $ip = Crypto::ip();
    $generic = __('That email and password do not match. Try again, or use "Email me a login link".', 'nirog-academy');

    if (RateLimiter::too_many('login_ip', $ip, 20) || RateLimiter::too_many('login_user', strtolower($email), 6)) {
      Actions::redirect_with('error', __('Too many attempts. Please wait 15 minutes, or use "Forgot password".', 'nirog-academy'), Router::url('login'));
    }
    if ($email === '' || $password === '') {
      Actions::redirect_with('error', $generic, Router::url('login'));
    }
    $user = wp_signon(['user_login' => $email, 'user_password' => $password, 'remember' => !empty($_POST['rememberme'])], is_ssl()); // phpcs:ignore WordPress.Security.NonceVerification
    if (is_wp_error($user)) {
      RateLimiter::hit('login_ip', $ip, 20, 15 * MINUTE_IN_SECONDS);
      RateLimiter::hit('login_user', strtolower($email), 6, 15 * MINUTE_IN_SECONDS);
      $found = get_user_by('email', $email) ?: get_user_by('login', $email);
      nba()->audit->log('login.failed', $found ? $found->ID : 0, '', 0, ['ip_attempts' => true]);
      Actions::redirect_with('error', $generic, add_query_arg('redirect_to', rawurlencode(self::redirect_target()), Router::url('login')));
    }
    RateLimiter::clear('login_user', strtolower($email));
    wp_set_current_user($user->ID);
    self::after_login($user->ID);
    wp_safe_redirect(self::redirect_target());
    exit;
  }

  /**
   * After any login: carry over the language chosen before logging in, and
   * register this browser if the account has a free device slot.
   */
  private static function after_login(int $user_id): void {
    $cookie_locale = isset($_COOKIE[I18n::COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[I18n::COOKIE])) : '';
    if ($cookie_locale && !get_user_meta($user_id, I18n::USER_META, true)) {
      update_user_meta($user_id, I18n::USER_META, $cookie_locale);
    }
    nba()->devices->verify($user_id);
    delete_user_meta($user_id, 'nba_needs_password');
  }

  public static function handle_magic_request(): void {
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $notice = __('If that email belongs to an Academy account, a login link is on its way. It works once, for 15 minutes.', 'nirog-academy');
    if (!RateLimiter::hit('magic_ip', Crypto::ip(), 10, HOUR_IN_SECONDS) || !RateLimiter::hit('magic_email', strtolower($email), 3, HOUR_IN_SECONDS)) {
      Actions::redirect_with('error', __('Too many requests. Please try again in an hour.', 'nirog-academy'), Router::url('login'));
    }
    $user = $email ? get_user_by('email', $email) : false;
    if ($user) {
      $token = Crypto::token(32);
      update_user_meta($user->ID, self::MAGIC_META, ['hash' => Crypto::hash($token, 'magic'), 'expires' => time() + 15 * MINUTE_IN_SECONDS]);
      $url = add_query_arg(['nba_magic' => $token, 'u' => $user->ID], Router::url('login'));
      I18n::with_user_locale($user->ID, static fn() => nba()->notifications->email(
        $user->ID,
        __('Your login link', 'nirog-academy'),
        __('Log in to the Academy', 'nirog-academy'),
        [
          __('Use the button below to log in. It works once, for 15 minutes.', 'nirog-academy'),
          __('If you did not ask for this, you can ignore this email.', 'nirog-academy'),
        ],
        __('Log me in', 'nirog-academy'),
        $url
      ));
      nba()->audit->log('login.magic_link', $user->ID);
    }
    Actions::redirect_with('notice', $notice, Router::url('login'));
  }

  private static function maybe_magic_login(): void {
    // phpcs:disable WordPress.Security.NonceVerification -- the one-time token is the credential.
    if (empty($_GET['nba_magic']) || empty($_GET['u'])) {
      return;
    }
    $token = sanitize_text_field(wp_unslash($_GET['nba_magic']));
    $user_id = absint($_GET['u']);
    // phpcs:enable
    $data = get_user_meta($user_id, self::MAGIC_META, true);
    delete_user_meta($user_id, self::MAGIC_META); // Single use, success or not.
    if (!is_array($data) || ($data['expires'] ?? 0) < time() || !hash_equals((string) ($data['hash'] ?? ''), Crypto::hash($token, 'magic'))) {
      Actions::redirect_with('error', __('That login link has expired or was already used. Request a new one below.', 'nirog-academy'), Router::url('login'));
    }
    wp_set_auth_cookie($user_id, true, is_ssl());
    wp_set_current_user($user_id);
    do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
    self::after_login($user_id);
    wp_safe_redirect(Router::url());
    exit;
  }

  public static function handle_forgot(): void {
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    if (!RateLimiter::hit('forgot_ip', Crypto::ip(), 10, HOUR_IN_SECONDS) || !RateLimiter::hit('forgot_email', strtolower($email), 3, HOUR_IN_SECONDS)) {
      Actions::redirect_with('error', __('Too many requests. Please try again in an hour.', 'nirog-academy'), Router::url('forgot'));
    }
    $user = $email ? get_user_by('email', $email) : false;
    if ($user) {
      I18n::with_user_locale($user->ID, static fn() => nba()->notifications->email(
        $user->ID,
        __('Reset your password', 'nirog-academy'),
        __('Choose a new password', 'nirog-academy'),
        [__('Someone asked to reset the password for your Academy account. If it was you, use the button below. If not, you can ignore this email.', 'nirog-academy')],
        __('Choose a new password', 'nirog-academy'),
        nba()->notifications->set_password_url($user->ID)
      ));
    }
    Actions::redirect_with('notice', __('If that email belongs to an Academy account, we have sent a link to reset the password.', 'nirog-academy'), Router::url('login'));
  }

  public static function handle_set_password(): void {
    // phpcs:disable WordPress.Security.NonceVerification -- verified in Actions.
    $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
    $login = isset($_POST['login']) ? sanitize_user(wp_unslash($_POST['login'])) : '';
    $pass1 = isset($_POST['pass1']) ? (string) wp_unslash($_POST['pass1']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $pass2 = isset($_POST['pass2']) ? (string) wp_unslash($_POST['pass2']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:enable
    $back = add_query_arg(['key' => $key, 'login' => rawurlencode($login)], Router::url('set-password'));
    $user = check_password_reset_key($key, $login);
    if (is_wp_error($user)) {
      Actions::redirect_with('error', __('This link has expired. Use "Forgot password" to get a new one.', 'nirog-academy'), Router::url('forgot'));
    }
    if (strlen($pass1) < 8) {
      Actions::redirect_with('error', __('Please use at least 8 characters.', 'nirog-academy'), $back);
    }
    if ($pass1 !== $pass2) {
      Actions::redirect_with('error', __('The two passwords do not match.', 'nirog-academy'), $back);
    }
    reset_password($user, $pass1);
    wp_set_auth_cookie($user->ID, true, is_ssl());
    wp_set_current_user($user->ID);
    self::after_login($user->ID);
    wp_safe_redirect(Router::url());
    exit;
  }
}
