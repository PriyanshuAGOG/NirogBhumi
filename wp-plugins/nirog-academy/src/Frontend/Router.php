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
 * The participant area at /courses/ (the slug is a setting).
 *
 * Each view is prepared here (data and access) and drawn by a template in
 * templates/. All views are private, uncached and not indexed.
 */
final class Router {
  public const VIEWS = ['dashboard', 'login', 'logout', 'set-password', 'forgot', 'device', 'help', 'library', 'bonus', 'resources', 'live', 'profile', 'notifications', 'programme', 'lesson', 'orientation'];
  private const PUBLIC_VIEWS = ['login', 'logout', 'set-password', 'forgot', 'help'];

  public static function register(): void {
    add_action('init', [self::class, 'rewrites']);
    add_filter('query_vars', static function (array $vars): array {
      $vars[] = 'nba_view';
      $vars[] = 'nba_slug';
      $vars[] = 'nba_legacy';
      return $vars;
    });
    add_action('template_redirect', [self::class, 'dispatch'], 1);
    // Courses pages are a self-contained space: they do not print the theme's
    // head/footer, so WordPress must not add its admin toolbar either.
    add_filter('show_admin_bar', static fn ($show) => self::current_view() ? false : $show, 100);
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
    add_rewrite_rule("^$s/(programme|lesson|help|orientation|live)/([^/]+)/?$", 'index.php?nba_view=$matches[1]&nba_slug=$matches[2]', 'top');
    add_rewrite_rule("^$s/([a-z-]+)/?$", 'index.php?nba_view=$matches[1]', 'top');
    // Links from before the rename (/academy/...) go to the new address.
    if (self::slug() !== 'academy') {
      add_rewrite_rule('^academy(/.*)?$', 'index.php?nba_legacy=1', 'top');
    }
  }

  public static function current_view(): string {
    $view = (string) get_query_var('nba_view');
    return in_array($view, self::VIEWS, true) ? $view : '';
  }

  /**
   * Printed on the course pages <head>. Nirog Bhumi Courses does not call wp_head(): the
   * theme, other plugins and the admin toolbar never print on these pages,
   * so nothing outside the plugin can change how they look (and the plugin
   * never changes how the rest of the site looks).
   */
  public static function head(): void {
    echo '<link rel="stylesheet" href="' . esc_url(NBA_URL . 'assets/css/academy.css?ver=' . NBA_VERSION) . '">' . "\n";
    if (function_exists('wp_site_icon')) {
      wp_site_icon();
    }
  }

  /** Printed before </body>. */
  public static function foot(): void {
    echo '<script>var NBA = ' . wp_json_encode(self::script_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
    echo '<script src="' . esc_url(NBA_URL . 'assets/js/academy.js?ver=' . NBA_VERSION) . '"></script>' . "\n";
  }

  /** @return array<string,mixed> */
  public static function script_config(): array {
    return [
      'rest' => esc_url_raw(rest_url('nirog-academy/v1/')),
      'nonce' => wp_create_nonce('wp_rest'),
      'hls' => NBA_URL . 'assets/vendor/hls.light.min.js?ver=1.5.20',
      'pdfjs' => NBA_URL . 'assets/vendor/pdfjs/pdf.min.js?ver=4.10.38',
      'pdfWorker' => NBA_URL . 'assets/vendor/pdfjs/pdf.worker.min.js?ver=4.10.38',
      'pageflip' => NBA_URL . 'assets/vendor/page-flip.browser.js?ver=2.0.7',
      'watermark' => self::watermark_text(),
      'i18n' => [
        'saved' => __('Saved', 'nirog-academy'),
        'saving' => __('Saving…', 'nirog-academy'),
        'completed' => __('Completed', 'nirog-academy'),
        'stopped' => __('Playback stopped because this account started playing somewhere else.', 'nirog-academy'),
        'expired' => __('This page has been open a long time. Reload to continue watching.', 'nirog-academy'),
        'error' => __('Something went wrong. Check your connection and try again.', 'nirog-academy'),
        'notWatched' => __('Watch the lesson to the end to complete it.', 'nirog-academy'),
        'notRead' => __('Read the booklet to the last page to complete it.', 'nirog-academy'),
        'page' => __('Page %1$d of %2$d', 'nirog-academy'),
        'pages' => __('Pages %1$d–%2$d of %3$d', 'nirog-academy'),
        'loadingBooklet' => __('Opening the booklet…', 'nirog-academy'),
        'bookletError' => __('The booklet could not be opened. Reload the page to try again.', 'nirog-academy'),
      ],
    ];
  }

  public static function dispatch(): void {
    if (get_query_var('nba_legacy')) {
      $path = (string) wp_parse_url(self::request_url(), PHP_URL_PATH);
      $rest = (string) preg_replace('~^.*?/academy(/|$)~', '', $path);
      $query = (string) wp_parse_url(self::request_url(), PHP_URL_QUERY);
      wp_safe_redirect(self::url($rest) . ($query !== '' ? '?' . $query : ''), 301);
      exit;
    }
    $view = self::current_view();
    if (!$view) {
      return;
    }
    $user_id = get_current_user_id();

    // Launch lock: until the Courses are opened in Settings, every Courses
    // address looks like an ordinary missing page to anyone who is not staff.
    // Staff log in through wp-login.php and can use everything.
    if (self::is_locked_for($user_id)) {
      global $wp_query;
      $wp_query->set_404();
      status_header(404);
      nocache_headers();
      header('X-Robots-Tag: noindex, nofollow');
      return; // WordPress renders the theme's normal "not found" page.
    }
    View::send_headers();

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

    match ($view) {
      'dashboard' => self::dashboard($user_id),
      'programme' => self::programme($user_id),
      'lesson' => self::lesson($user_id),
      'orientation' => self::orientation($user_id),
      'library', 'bonus', 'resources' => self::library($user_id),
      'live' => self::live($user_id),
      'profile' => self::profile($user_id),
      'notifications' => self::notifications($user_id),
      default => self::not_found(),
    };
  }

  /**
   * True while Nirog Bhumi Courses is Closed and this visitor is not staff.
   */
  public static function is_locked_for(int $user_id): bool {
    // The demo participant made by the team can look around before launch.
    return nba()->settings->get('launch_mode') === 'closed' && !Roles::is_staff($user_id) && !\NirogBhumi\Academy\Admin\Demo::is_demo_user($user_id);
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

  /**
   * Week sections for a programme: items with their state, plus live
   * sessions linked to that week.
   *
   * @return array<int, array{id:int, index:int, items:array, live:\WP_Post[], done:int, total:int}>
   */
  public static function weeks(int $user_id, int $programme_id, ?object $enrollment): array {
    $tree = nba()->curriculum->tree($programme_id);
    $rows = nba()->progress->for_programme($user_id, $programme_id);
    $live_by_week = [];
    foreach (array_merge(
      nba()->curriculum->live_sessions($programme_id, $enrollment ? (int) $enrollment->cohort_id : 0, true, 50),
      nba()->curriculum->live_sessions($programme_id, $enrollment ? (int) $enrollment->cohort_id : 0, false, 50)
    ) as $session) {
      $live_by_week[(int) get_post_meta($session->ID, '_nba_module_id', true)][] = $session;
    }
    $weeks = [];
    foreach ($tree['modules'] as $i => $module) {
      $items = [];
      foreach ($module['lessons'] as $lesson_id) {
        $items[] = ['id' => $lesson_id, 'state' => self::lesson_state($user_id, $lesson_id, $rows)];
      }
      $live = $live_by_week[$module['id']] ?? [];
      usort($live, static fn($a, $b) => strcmp((string) get_post_meta($a->ID, '_nba_starts_at', true), (string) get_post_meta($b->ID, '_nba_starts_at', true)));
      $weeks[] = [
        'id' => $module['id'],
        'index' => $i + 1,
        'items' => $items,
        'live' => $live,
        'done' => count(array_filter($items, static fn($x) => $x['state']['state'] === 'completed')),
        'total' => count($items),
      ];
    }
    return $weeks;
  }

  private static function dashboard(int $user_id): never {
    $enrollments = self::visible_enrollments($user_id);
    $primary = $enrollments[0] ?? null;
    $data = [
      'user' => wp_get_current_user(),
      'enrollment' => $primary,
      'others' => array_slice($enrollments, 1),
      'programme_id' => 0,
      'summary' => null,
      'next' => 0,
      'next_state' => null,
      'next_progress' => null,
      'day' => 0,
      'week' => null,
      'weeks_total' => 0,
      'booklet' => 0,
      'next_live' => null,
      'announcement' => null,
    ];

    if ($primary) {
      $pid = (int) $primary->programme_id;
      $data['programme_id'] = $pid;
      $summary = nba()->progress->summary($user_id, $pid);
      $data['summary'] = $summary;
      $next = $summary['resume_lesson'] ?: $summary['next_lesson'];
      $rows = nba()->progress->for_programme($user_id, $pid);
      if ($next) {
        $data['next'] = $next;
        $data['next_state'] = self::lesson_state($user_id, $next, $rows);
        $data['next_progress'] = $rows[$next] ?? null;
      }
      $base = empty($primary->staff_view) ? nba()->enrollments->drip_base($primary) : null;
      if ($base !== null && Time::ts($base) <= time()) {
        $data['day'] = Time::days_between($base, Time::now()) + 1;
      }

      // The week to show: the one holding the next lesson, else the last week.
      $weeks = self::weeks($user_id, $pid, empty($primary->staff_view) ? $primary : null);
      $data['weeks_total'] = count($weeks);
      foreach ($weeks as $w) {
        foreach ($w['items'] as $item) {
          if ($item['id'] === $next) {
            $data['week'] = $w;
          }
        }
      }
      $data['week'] = $data['week'] ?? ($weeks ? end($weeks) : null);

      // This week's booklet, else the most recent open booklet.
      $candidates = $data['week'] ? array_column($data['week']['items'], 'id') : [];
      $all = array_merge($candidates, array_reverse(nba()->curriculum->tree($pid)['lessons']));
      foreach ($all as $lid) {
        if (get_post_meta($lid, '_nba_type', true) === 'booklet' && nba()->gate->check_lesson($user_id, $lid)->allowed) {
          $data['booklet'] = $lid;
          break;
        }
      }

      foreach ($enrollments as $e) {
        foreach (nba()->curriculum->live_sessions((int) $e->programme_id, (int) ($e->cohort_id ?? 0), true, 1) as $session) {
          if (!$data['next_live'] || Time::ts((string) get_post_meta($session->ID, '_nba_starts_at', true)) < Time::ts((string) get_post_meta($data['next_live']->ID, '_nba_starts_at', true))) {
            $data['next_live'] = $session;
          }
        }
      }
    }

    foreach (nba()->notifications->list($user_id, 20) as $n) {
      if ($n->type === 'announcement') {
        $data['announcement'] = $n;
        break;
      }
    }

    View::page('dashboard', $data, ['title' => __('Today', 'nirog-academy')]);
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
    View::page('programme', [
      'programme' => $programme,
      'enrollment' => $access->enrollment,
      'weeks' => self::weeks($user_id, $programme->ID, $access->enrollment),
      'summary' => nba()->progress->summary($user_id, $programme->ID),
      'bonus_count' => count(nba()->curriculum->tree($programme->ID)['bonus']),
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
    $watermark = self::watermark_text();

    $module_id = nba()->curriculum->module_of($lesson->ID);
    $week_index = 0;
    foreach (nba()->curriculum->tree($programme_id)['modules'] as $i => $m) {
      if ($m['id'] === $module_id) {
        $week_index = $i + 1;
      }
    }
    $type = (string) get_post_meta($lesson->ID, '_nba_type', true) ?: 'video';
    $booklet = null;
    if ($type === 'booklet' && ($file = nba()->stream->booklet_file($lesson->ID))) {
      $booklet = [
        'url' => nba()->stream->booklet_url($lesson->ID),
        'name' => $file['name'],
        'download' => get_post_meta($lesson->ID, '_nba_allow_download', true) === '1',
      ];
    }
    View::page('lesson', [
      'lesson' => $lesson,
      'type' => $type,
      'booklet' => $booklet,
      'week_index' => $week_index,
      'programme_id' => $programme_id,
      'module_id' => $module_id,
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

  /** Name and phone ending shown faintly over videos and booklet pages. */
  public static function watermark_text(): string {
    $user = wp_get_current_user();
    if (!$user->ID || !nba()->settings->int('watermark')) {
      return '';
    }
    $phone = (string) get_user_meta($user->ID, 'billing_phone', true);
    return trim($user->display_name . ' · ' . ($phone ? '••' . substr(preg_replace('/\D/', '', $phone), -4) : self::mask_email($user->user_email)));
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

  private static function library(int $user_id): never {
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $view = (string) get_query_var('nba_view');
    if ($view === 'bonus') {
      $tab = 'bonus';
    } elseif ($view === 'resources') {
      $tab = 'downloads';
    }
    $tab = in_array($tab, ['booklets', 'bonus', 'downloads'], true) ? $tab : 'booklets';

    $booklets = [];
    $bonus = [];
    $downloads = [];
    foreach (self::visible_enrollments($user_id) as $enrollment) {
      $pid = (int) $enrollment->programme_id;
      $tree = nba()->curriculum->tree($pid);
      $rows = nba()->progress->for_programme($user_id, $pid);
      $week_of = [];
      foreach ($tree['modules'] as $i => $m) {
        foreach ($m['lessons'] as $lid) {
          $week_of[$lid] = $i + 1;
        }
      }
      foreach (array_merge($tree['lessons'], $tree['bonus']) as $lid) {
        $state = null;
        if (get_post_meta($lid, '_nba_type', true) === 'booklet') {
          $state = self::lesson_state($user_id, $lid, $rows);
          $booklets[] = ['id' => $lid, 'state' => $state, 'week' => $week_of[$lid] ?? 0, 'pages' => (int) get_post_meta($lid, '_nba_pages', true)];
        }
        foreach (nba()->curriculum->resources($lid) as $f) {
          $open = nba()->gate->check_lesson($user_id, $lid)->allowed;
          $f['lesson'] = $lid;
          $f['week'] = $week_of[$lid] ?? 0;
          $f['url'] = $open ? nba()->stream->resource_url($lid, $f['id']) : '';
          $downloads[] = $f;
        }
      }
      foreach ($tree['bonus'] as $lid) {
        $category = (string) get_post_meta($lid, '_nba_category', true) ?: __('More', 'nirog-academy');
        $bonus[$category][] = ['id' => $lid, 'state' => self::lesson_state($user_id, $lid, $rows)];
      }
    }
    ksort($bonus);
    View::page('library', [
      'tab' => $tab,
      'booklets' => $booklets,
      'bonus' => $bonus,
      'downloads' => $downloads,
    ], ['title' => __('Library', 'nirog-academy')]);
  }

  private static function live(int $user_id): never {
    $slug = (string) get_query_var('nba_slug');
    if ($slug !== '') {
      self::live_session($user_id, absint($slug));
    }
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

  /**
   * One live session: details, join button or embedded stream, recording.
   */
  private static function live_session(int $user_id, int $session_id): never {
    if (!$session_id || !\NirogBhumi\Academy\Live\LiveSessions::can_view($user_id, $session_id)) {
      self::not_found();
    }
    $info = \NirogBhumi\Academy\Live\LiveSessions::info($session_id);
    // Join: record attendance, then go to the meeting.
    if (!empty($_GET['join'])) { // phpcs:ignore WordPress.Security.NonceVerification
      $nonce = isset($_GET['_nbajoin']) ? sanitize_text_field(wp_unslash($_GET['_nbajoin'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
      if (wp_verify_nonce($nonce, 'nba_join_' . $session_id) && in_array($info['phase'], ['open', 'live'], true) && $info['kind'] === 'meeting' && $info['link']) {
        \NirogBhumi\Academy\Live\LiveSessions::record_join($session_id, $user_id);
        wp_redirect(esc_url_raw($info['link'])); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external meeting host chosen by staff.
        exit;
      }
      wp_safe_redirect(\NirogBhumi\Academy\Live\LiveSessions::url($session_id));
      exit;
    }
    // Watching a stream on the course pages counts as attending.
    if ($info['kind'] === 'stream' && in_array($info['phase'], ['open', 'live'], true) && !Roles::is_staff($user_id)) {
      \NirogBhumi\Academy\Live\LiveSessions::record_join($session_id, $user_id);
    }
    View::page('live-session', ['info' => $info], ['title' => $info['title']]);
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
