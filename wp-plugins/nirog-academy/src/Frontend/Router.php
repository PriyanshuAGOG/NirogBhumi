<?php
namespace NirogBhumi\Academy\Frontend;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Install\Roles;
use NirogBhumi\Academy\Notify\Notifications;
use NirogBhumi\Academy\Support\Docs;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * The participant area at /academy/ (the slug is a setting).
 *
 * Each view is prepared here (data and access) and drawn by a template in
 * templates/. All views are private, uncached and not indexed.
 */
final class Router {
  public const VIEWS = ['dashboard', 'login', 'logout', 'set-password', 'forgot', 'device', 'help', 'bonus', 'resources', 'live', 'profile', 'notifications', 'programme', 'lesson', 'orientation'];
  private const PUBLIC_VIEWS = ['login', 'logout', 'set-password', 'forgot', 'help'];

  public static function register(): void {
    add_action('init', [self::class, 'rewrites']);
    add_filter('query_vars', static function (array $vars): array {
      $vars[] = 'nba_view';
      $vars[] = 'nba_slug';
      return $vars;
    });
    add_action('template_redirect', [self::class, 'dispatch'], 1);
    add_action('wp_enqueue_scripts', [self::class, 'assets'], 100);
    // Styles some plugins add late (for example WooCommerce blocks) are removed too.
    add_action('wp_print_styles', [self::class, 'strip_foreign_assets'], 1);
    add_action('wp_print_footer_scripts', [self::class, 'strip_foreign_assets'], 1);
  }

  public static function strip_foreign_assets(): void {
    if (!self::current_view()) {
      return;
    }
    global $wp_styles, $wp_scripts;
    foreach ((array) ($wp_styles->queue ?? []) as $handle) {
      if (!str_starts_with($handle, 'nba-')) {
        wp_dequeue_style($handle);
      }
    }
    foreach ((array) ($wp_scripts->queue ?? []) as $handle) {
      if (!str_starts_with($handle, 'nba-') && $handle !== 'wp-emoji') {
        wp_dequeue_script($handle);
      }
    }
  }

  public static function slug(): string {
    return (string) nba()->settings->get('base_slug', 'academy');
  }

  public static function url(string $path = ''): string {
    return home_url(user_trailingslashit(self::slug() . ($path !== '' ? '/' . trim($path, '/') : '')));
  }

  public static function lesson_url(int $lesson_id): string {
    return self::url('lesson/' . get_post_field('post_name', $lesson_id));
  }

  public static function programme_url(int $programme_id): string {
    return self::url('programme/' . get_post_field('post_name', $programme_id));
  }

  public static function rewrites(): void {
    $s = preg_quote(self::slug(), '#');
    add_rewrite_rule("^$s/?$", 'index.php?nba_view=dashboard', 'top');
    add_rewrite_rule("^$s/(programme|lesson|help|orientation)/([^/]+)/?$", 'index.php?nba_view=$matches[1]&nba_slug=$matches[2]', 'top');
    add_rewrite_rule("^$s/([a-z-]+)/?$", 'index.php?nba_view=$matches[1]', 'top');
  }

  public static function current_view(): string {
    $view = (string) get_query_var('nba_view');
    return in_array($view, self::VIEWS, true) ? $view : '';
  }

  public static function assets(): void {
    if (!self::current_view()) {
      return;
    }
    // The Academy is its own calm space: drop theme and plugin front-end styles.
    self::strip_foreign_assets();
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    wp_enqueue_style('nba-academy', NBA_URL . 'assets/css/academy.css', [], NBA_VERSION);
    wp_register_script('nba-hls', NBA_URL . 'assets/vendor/hls.light.min.js', [], '1.5.20', true);
    wp_enqueue_script('nba-academy', NBA_URL . 'assets/js/academy.js', [], NBA_VERSION, true);
    wp_localize_script('nba-academy', 'NBA', [
      'rest' => esc_url_raw(rest_url('nirog-academy/v1/')),
      'nonce' => wp_create_nonce('wp_rest'),
      'hls' => NBA_URL . 'assets/vendor/hls.light.min.js?ver=1.5.20',
      'i18n' => [
        'saved' => __('Saved', 'nirog-academy'),
        'saving' => __('Saving…', 'nirog-academy'),
        'completed' => __('Completed', 'nirog-academy'),
        'stopped' => __('Playback stopped because this account started playing somewhere else.', 'nirog-academy'),
        'expired' => __('This page has been open a long time. Reload to continue watching.', 'nirog-academy'),
        'error' => __('Something went wrong. Check your connection and try again.', 'nirog-academy'),
        'notWatched' => __('Watch the lesson to the end to complete it.', 'nirog-academy'),
      ],
    ]);
  }

  public static function dispatch(): void {
    $view = self::current_view();
    if (!$view) {
      return;
    }
    View::send_headers();
    $user_id = get_current_user_id();

    // Language switch (?nba_lang=hi_IN).
    if (isset($_GET['nba_lang'], $_GET['_nbalang']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_nbalang'])), 'nba_lang')) {
      I18n::set_user_locale($user_id, sanitize_text_field(wp_unslash($_GET['nba_lang'])));
      wp_safe_redirect(remove_query_arg(['nba_lang', '_nbalang']));
      exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      Actions::handle($view);
    }

    if (in_array($view, self::PUBLIC_VIEWS, true)) {
      Auth::view($view);
      if ($view === 'help') {
        self::help();
      }
      return;
    }

    if (!$user_id) {
      wp_safe_redirect(add_query_arg('redirect_to', rawurlencode(self::request_url()), self::url('login')));
      exit;
    }

    nba()->progress->touch($user_id);

    // The device screen must stay reachable for blocked users.
    if ($view === 'device') {
      self::device();
    }
    $device = nba()->devices->verify($user_id);
    if (!$device['ok']) {
      wp_safe_redirect(self::url('device'));
      exit;
    }

    if (!Roles::is_staff($user_id) && nba()->settings->get('launch_mode') === 'closed') {
      View::page('notice', [
        'icon' => 'calendar',
        'heading' => __('The Academy opens soon', 'nirog-academy'),
        'message' => __('Your account is ready. We will email you as soon as the lessons are open.', 'nirog-academy'),
      ], ['title' => __('Opening soon', 'nirog-academy')]);
    }

    match ($view) {
      'dashboard' => self::dashboard($user_id),
      'programme' => self::programme($user_id),
      'lesson' => self::lesson($user_id),
      'orientation' => self::orientation($user_id),
      'bonus' => self::bonus($user_id),
      'resources' => self::resources($user_id),
      'live' => self::live($user_id),
      'profile' => self::profile($user_id),
      'notifications' => self::notifications($user_id),
      default => self::not_found(),
    };
  }

  public static function request_url(): string {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
    return home_url($uri);
  }

  public static function not_found(): never {
    status_header(404);
    View::page('notice', [
      'icon' => 'help',
      'heading' => __('We could not find that page', 'nirog-academy'),
      'message' => __('It may have moved. Go back to your dashboard to continue.', 'nirog-academy'),
      'action' => ['label' => __('Go to dashboard', 'nirog-academy'), 'url' => self::url()],
    ], ['title' => __('Not found', 'nirog-academy')]);
  }

  // ------------------------------------------------------------ helpers

  /**
   * Enrollments that should appear on the dashboard (open, paused, pending).
   *
   * @return object[]
   */
  public static function visible_enrollments(int $user_id): array {
    $out = [];
    if (Roles::is_staff($user_id)) {
      // Staff see every published programme, to check content.
      foreach (get_posts(['post_type' => PostTypes::PROGRAMME, 'post_status' => 'publish', 'numberposts' => -1]) as $p) {
        $out[$p->ID] = (object) ['id' => 0, 'programme_id' => $p->ID, 'status' => 'active', 'starts_at' => Time::now(), 'ends_at' => null, 'cohort_id' => 0, 'staff_view' => true];
      }
      return array_values($out);
    }
    foreach (nba()->enrollments->for_user($user_id) as $row) {
      $pid = (int) $row->programme_id;
      if (isset($out[$pid]) || get_post_status($pid) !== 'publish') {
        continue;
      }
      if (in_array($row->status, ['paused', 'pending'], true) || nba()->enrollments->is_open($row)) {
        $out[$pid] = nba()->enrollments->current($user_id, $pid);
      }
    }
    return array_values(array_filter($out));
  }

  /**
   * State of one lesson for the curriculum list.
   *
   * @return array{state: string, label: string, opens_at: ?string, pct: int}
   */
  public static function lesson_state(int $user_id, int $lesson_id, array $progress_rows): array {
    $row = $progress_rows[$lesson_id] ?? null;
    if ($row && $row->status === 'completed') {
      return ['state' => 'completed', 'label' => __('Completed', 'nirog-academy'), 'opens_at' => null, 'pct' => 100];
    }
    $access = nba()->gate->check_lesson($user_id, $lesson_id);
    if (!$access->allowed) {
      return match ($access->code) {
        'release' => ['state' => 'locked', 'label' => sprintf(
          /* translators: %s: date */
          __('Opens %s', 'nirog-academy'),
          Time::human($access->data['opens_at'] ?? null, 'D, j M')
        ), 'opens_at' => $access->data['opens_at'] ?? null, 'pct' => 0],
        'sequence' => ['state' => 'locked', 'label' => __('Finish the previous lesson first', 'nirog-academy'), 'opens_at' => null, 'pct' => 0],
        'ack' => ['state' => 'available', 'label' => __('Start', 'nirog-academy'), 'opens_at' => null, 'pct' => 0],
        default => ['state' => 'locked', 'label' => $access->message, 'opens_at' => null, 'pct' => 0],
      };
    }
    if ($row) {
      return ['state' => 'progress', 'label' => sprintf(
        /* translators: %d: percent */
        __('%d%% watched', 'nirog-academy'),
        (int) $row->watched_pct
      ), 'opens_at' => null, 'pct' => (int) $row->watched_pct];
    }
    return ['state' => 'available', 'label' => __('Start', 'nirog-academy'), 'opens_at' => null, 'pct' => 0];
  }

  /**
   * Translate an access denial into the right page or redirect.
   */
  private static function deny(\NirogBhumi\Academy\Access\AccessResult $access, int $lesson_id = 0): never {
    switch ($access->code) {
      case 'login':
        wp_safe_redirect(add_query_arg('redirect_to', rawurlencode(self::request_url()), self::url('login')));
        exit;
      case 'device':
        wp_safe_redirect(self::url('device'));
        exit;
      case 'ack':
        wp_safe_redirect(add_query_arg('next', $lesson_id, self::url('orientation/' . get_post_field('post_name', $access->programme_id))));
        exit;
      case 'not_found':
        self::not_found();
    }
    $action = ['label' => __('Back to dashboard', 'nirog-academy'), 'url' => self::url()];
    if ($access->code === 'sequence' && !empty($access->data['previous'])) {
      $action = ['label' => __('Go to the previous lesson', 'nirog-academy'), 'url' => self::lesson_url((int) $access->data['previous'])];
    }
    if ($access->code === 'enrollment') {
      $action = ['label' => __('See our programmes', 'nirog-academy'), 'url' => (string) apply_filters('nba_programmes_page_url', home_url('/programmes/'))];
    }
    View::page('notice', [
      'icon' => in_array($access->code, ['release', 'sequence'], true) ? 'clock' : 'lock',
      'heading' => match ($access->code) {
        'release' => __('Not open yet', 'nirog-academy'),
        'sequence' => __('One step at a time', 'nirog-academy'),
        'paused' => __('Access paused', 'nirog-academy'),
        'pending' => __('Almost there', 'nirog-academy'),
        'window' => __('Access ended', 'nirog-academy'),
        'launch' => __('Opening soon', 'nirog-academy'),
        default => __('This lesson is locked', 'nirog-academy'),
      },
      'message' => $access->message,
      'action' => $action,
      'support' => true,
    ], ['title' => __('Locked', 'nirog-academy')]);
  }

  // ------------------------------------------------------------ views

  private static function dashboard(int $user_id): never {
    $programmes = [];
    foreach (self::visible_enrollments($user_id) as $enrollment) {
      $pid = (int) $enrollment->programme_id;
      $summary = nba()->progress->summary($user_id, $pid);
      $continue = $summary['resume_lesson'] ?: $summary['next_lesson'];
      $programmes[] = [
        'id' => $pid,
        'enrollment' => $enrollment,
        'summary' => $summary,
        'continue' => $continue,
        'continue_state' => $continue ? self::lesson_state($user_id, $continue, nba()->progress->for_programme($user_id, $pid)) : null,
        'element' => (string) get_post_meta($pid, '_nba_element', true) ?: 'earth',
      ];
    }

    $today = [];
    $next_live = null;
    foreach ($programmes as $p) {
      $enrollment = $p['enrollment'];
      if (!empty($enrollment->staff_view) || $enrollment->status !== 'active') {
        continue;
      }
      $base = nba()->enrollments->drip_base($enrollment);
      if ($base !== null && Time::ts($base) <= time()) {
        $day = Time::days_between($base, Time::now());
        foreach (nba()->curriculum->tree($p['id'])['lessons'] as $lesson_id) {
          if ((int) get_post_meta($lesson_id, '_nba_release_day', true) === $day) {
            $today[] = $lesson_id;
          }
        }
      }
      foreach (nba()->curriculum->live_sessions($p['id'], (int) $enrollment->cohort_id, true, 1) as $session) {
        if (!$next_live || Time::ts((string) get_post_meta($session->ID, '_nba_starts_at', true)) < Time::ts((string) get_post_meta($next_live->ID, '_nba_starts_at', true))) {
          $next_live = $session;
        }
      }
    }

    $announcement = null;
    foreach (nba()->notifications->list($user_id, 20) as $n) {
      if ($n->type === 'announcement') {
        $announcement = $n;
        break;
      }
    }

    View::page('dashboard', [
      'user' => wp_get_current_user(),
      'programmes' => $programmes,
      'today' => array_slice(array_unique($today), 0, 4),
      'next_live' => $next_live,
      'announcement' => $announcement,
    ], ['title' => __('Dashboard', 'nirog-academy')]);
  }

  private static function programme(int $user_id): never {
    $programme = nba()->curriculum->programme_by_slug((string) get_query_var('nba_slug'));
    if (!$programme) {
      self::not_found();
    }
    $access = nba()->gate->check_programme($user_id, $programme->ID);
    if (!$access->allowed) {
      self::deny($access);
    }
    $tree = nba()->curriculum->tree($programme->ID);
    $rows = nba()->progress->for_programme($user_id, $programme->ID);
    $modules = [];
    foreach ($tree['modules'] as $module) {
      $lessons = [];
      foreach ($module['lessons'] as $lesson_id) {
        $lessons[] = ['id' => $lesson_id, 'state' => self::lesson_state($user_id, $lesson_id, $rows)];
      }
      $done = count(array_filter($lessons, static fn($l) => $l['state']['state'] === 'completed'));
      $modules[] = ['id' => $module['id'], 'lessons' => $lessons, 'done' => $done];
    }
    View::page('programme', [
      'programme' => $programme,
      'enrollment' => $access->enrollment,
      'modules' => $modules,
      'summary' => nba()->progress->summary($user_id, $programme->ID),
      'has_bonus' => (bool) $tree['bonus'],
    ], ['title' => I18n::field($programme->ID, 'title')]);
  }

  private static function lesson(int $user_id): never {
    $lesson = nba()->curriculum->lesson_by_slug((string) get_query_var('nba_slug'));
    if (!$lesson) {
      self::not_found();
    }
    $access = nba()->gate->check_lesson($user_id, $lesson->ID);
    if (!$access->allowed) {
      self::deny($access, $lesson->ID);
    }
    $programme_id = $access->programme_id;
    [$prev, $next] = nba()->curriculum->neighbours($lesson->ID);
    $resources = [];
    foreach (nba()->curriculum->resources($lesson->ID) as $r) {
      $r['url'] = nba()->stream->resource_url($lesson->ID, $r['id']);
      $resources[] = $r;
    }
    $user = wp_get_current_user();
    $phone = function_exists('WC') ? (string) get_user_meta($user_id, 'billing_phone', true) : '';
    $watermark = nba()->settings->int('watermark') ? trim($user->display_name . ' · ' . ($phone ? '••' . substr(preg_replace('/\D/', '', $phone), -4) : self::mask_email($user->user_email))) : '';

    View::page('lesson', [
      'lesson' => $lesson,
      'programme_id' => $programme_id,
      'module_id' => nba()->curriculum->module_of($lesson->ID),
      'player' => nba()->stream->player_config($lesson->ID),
      'progress' => nba()->progress->get($user_id, $lesson->ID),
      'note' => nba()->progress->note($user_id, $lesson->ID),
      'resources' => $resources,
      'prev' => $prev && nba()->gate->check_lesson($user_id, $prev)->allowed ? $prev : 0,
      'next' => $next,
      'next_open' => $next && nba()->gate->check_lesson($user_id, $next)->allowed,
      'watermark' => $watermark,
      'is_bonus' => get_post_meta($lesson->ID, '_nba_placement', true) === 'bonus',
    ], ['title' => I18n::field($lesson->ID, 'title')]);
  }

  private static function mask_email(string $email): string {
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($local, 0, 2) . '•••@' . $domain;
  }

  private static function orientation(int $user_id): never {
    $programme = nba()->curriculum->programme_by_slug((string) get_query_var('nba_slug'));
    if (!$programme) {
      self::not_found();
    }
    $access = nba()->gate->check_programme($user_id, $programme->ID);
    if (!$access->allowed) {
      self::deny($access);
    }
    $next = isset($_GET['next']) ? absint($_GET['next']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
    if (nba()->gate->has_acknowledged($user_id, $programme->ID)) {
      wp_safe_redirect($next ? self::lesson_url($next) : self::programme_url($programme->ID));
      exit;
    }
    View::page('orientation', [
      'programme' => $programme,
      'text' => I18n::field($programme->ID, 'ack_text'),
      'next' => $next,
    ], ['title' => __('Before you begin', 'nirog-academy')]);
  }

  private static function bonus(int $user_id): never {
    $groups = [];
    foreach (self::visible_enrollments($user_id) as $enrollment) {
      $pid = (int) $enrollment->programme_id;
      $rows = nba()->progress->for_programme($user_id, $pid);
      foreach (nba()->curriculum->tree($pid)['bonus'] as $lesson_id) {
        $category = (string) get_post_meta($lesson_id, '_nba_category', true) ?: __('More', 'nirog-academy');
        $groups[$category][] = ['id' => $lesson_id, 'state' => self::lesson_state($user_id, $lesson_id, $rows)];
      }
    }
    ksort($groups);
    View::page('bonus', ['groups' => $groups], ['title' => __('Bonus library', 'nirog-academy')]);
  }

  private static function resources(int $user_id): never {
    $sections = [];
    foreach (self::visible_enrollments($user_id) as $enrollment) {
      $pid = (int) $enrollment->programme_id;
      $tree = nba()->curriculum->tree($pid);
      $items = [];
      foreach (array_merge($tree['lessons'], $tree['bonus']) as $lesson_id) {
        $files = nba()->curriculum->resources($lesson_id);
        if (!$files) {
          continue;
        }
        $open = nba()->gate->check_lesson($user_id, $lesson_id)->allowed;
        foreach ($files as $f) {
          $f['lesson'] = $lesson_id;
          $f['url'] = $open ? nba()->stream->resource_url($lesson_id, $f['id']) : '';
          $items[] = $f;
        }
      }
      if ($items) {
        $sections[] = ['programme_id' => $pid, 'items' => $items];
      }
    }
    View::page('resources', ['sections' => $sections], ['title' => __('Downloads', 'nirog-academy')]);
  }

  private static function live(int $user_id): never {
    $upcoming = [];
    $past = [];
    foreach (self::visible_enrollments($user_id) as $enrollment) {
      $pid = (int) $enrollment->programme_id;
      foreach (nba()->curriculum->live_sessions($pid, (int) $enrollment->cohort_id, true, 20) as $s) {
        $upcoming[$s->ID] = $s;
      }
      foreach (nba()->curriculum->live_sessions($pid, (int) $enrollment->cohort_id, false, 20) as $s) {
        $past[$s->ID] = $s;
      }
    }
    $sort = static fn($a, $b) => strcmp((string) get_post_meta($a->ID, '_nba_starts_at', true), (string) get_post_meta($b->ID, '_nba_starts_at', true));
    uasort($upcoming, $sort);
    uasort($past, static fn($a, $b) => -$sort($a, $b));
    View::page('live', ['upcoming' => array_values($upcoming), 'past' => array_values($past)], ['title' => __('Live sessions', 'nirog-academy')]);
  }

  private static function profile(int $user_id): never {
    $devices = nba()->devices->active($user_id);
    View::page('profile', [
      'user' => wp_get_current_user(),
      'phone' => (string) get_user_meta($user_id, 'billing_phone', true),
      'prefs' => Notifications::prefs($user_id),
      'devices' => $devices,
      'this_device' => nba()->devices->current_device($user_id),
      'enrollments' => self::visible_enrollments($user_id),
      'orders_url' => function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : '',
      'locale' => I18n::current(),
    ], ['title' => __('Profile', 'nirog-academy')]);
  }

  private static function notifications(int $user_id): never {
    $items = nba()->notifications->list($user_id, 50);
    nba()->notifications->mark_read($user_id);
    View::page('notifications', ['items' => $items], ['title' => __('Notifications', 'nirog-academy')]);
  }

  private static function device(): never {
    $user_id = get_current_user_id();
    $status = nba()->devices->verify($user_id);
    View::page('device', [
      'blocked' => !$status['ok'],
      'devices' => nba()->devices->active($user_id),
      'can_self_reset' => nba()->devices->can_self_reset($user_id),
      'next_reset' => nba()->devices->next_self_reset($user_id),
      'pending' => nba()->devices->pending_request($user_id),
      'code_sent' => is_array(get_user_meta($user_id, 'nba_device_code', true)),
    ], ['title' => __('Your device', 'nirog-academy'), 'bare' => !$status['ok']]);
  }

  private static function help(): never {
    $slug = sanitize_title((string) get_query_var('nba_slug'));
    $docs = Docs::participant();
    if ($slug && isset($docs[$slug])) {
      View::page('help-article', ['doc' => $docs[$slug], 'html' => Docs::render($docs[$slug]['path'])], ['title' => $docs[$slug]['title'], 'bare' => !is_user_logged_in()]);
    }
    View::page('help', ['docs' => $docs], ['title' => __('Help', 'nirog-academy'), 'bare' => !is_user_logged_in()]);
  }
}
