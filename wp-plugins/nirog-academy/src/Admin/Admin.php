<?php
namespace NirogBhumi\Academy\Admin;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Support\Settings;

defined('ABSPATH') || exit;

/**
 * The Academy admin menu, assets, curriculum builder AJAX and the
 * admin-post form handlers.
 */
final class Admin {
  public const SLUG = 'nirog-academy';

  public static function register(): void {
    add_action('admin_menu', [self::class, 'menu']);
    add_action('admin_enqueue_scripts', [self::class, 'assets']);
    add_action('admin_init', [self::class, 'register_settings']);
    add_action('admin_notices', [self::class, 'notices']);
    add_filter('parent_file', [self::class, 'highlight_menu']);

    foreach (['cb_order', 'cb_add_module', 'cb_rename_module', 'cb_delete_module'] as $ajax) {
      add_action('wp_ajax_nba_' . $ajax, [Curriculum::class, $ajax]);
    }
    foreach (Handlers::ACTIONS as $action => $cap) {
      add_action('admin_post_nba_' . $action, static function () use ($action, $cap): void {
        Handlers::dispatch($action, $cap);
      });
    }
    add_filter('manage_' . PostTypes::LESSON . '_posts_columns', [self::class, 'lesson_columns']);
    add_action('manage_' . PostTypes::LESSON . '_posts_custom_column', [self::class, 'lesson_column'], 10, 2);
    add_filter('manage_' . PostTypes::MEDIA . '_posts_columns', [self::class, 'media_columns']);
    add_action('manage_' . PostTypes::MEDIA . '_posts_custom_column', [self::class, 'media_column'], 10, 2);
  }

  public static function menu(): void {
    add_menu_page(
      __('Academy', 'nirog-academy'),
      __('Academy', 'nirog-academy'),
      'nba_view_participants',
      self::SLUG,
      [Pages::class, 'overview'],
      'dashicons-welcome-learn-more',
      26
    );
    $pages = [
      [self::SLUG, __('Overview', 'nirog-academy'), 'nba_view_participants', 'overview'],
      ['nba-enrollments', __('Enrollments', 'nirog-academy'), 'nba_manage_enrollments', 'enrollments'],
      ['nba-participant', __('Participant', 'nirog-academy'), 'nba_view_participants', 'participant'],
      ['nba-batches', __('Batches', 'nirog-academy'), 'edit_nba_items', 'batches'],
      ['nba-devices', __('Device Requests', 'nirog-academy'), 'nba_manage_devices', 'devices'],
      ['nba-announcements', __('Announcements', 'nirog-academy'), 'nba_send_announcements', 'announcements'],
      ['nba-reports', __('Reports', 'nirog-academy'), 'nba_view_reports', 'reports'],
      ['nba-import', __('Import Videos', 'nirog-academy'), 'nba_manage_academy', 'import'],
      ['nba-audit', __('Audit Log', 'nirog-academy'), 'nba_view_audit', 'audit'],
      ['nba-settings', __('Settings', 'nirog-academy'), 'nba_manage_academy', 'settings'],
      ['nba-help', __('Help', 'nirog-academy'), 'nba_view_participants', 'help'],
    ];
    foreach ($pages as [$slug, $title, $cap, $method]) {
      $title_with_count = $title;
      if ($slug === 'nba-devices') {
        $pending = count(nba()->devices->requests('pending'));
        if ($pending) {
          $title_with_count .= ' <span class="awaiting-mod">' . (int) $pending . '</span>';
        }
      }
      add_submenu_page($slug === 'nba-participant' ? '' : self::SLUG, $title, $title_with_count, $cap, $slug, [Pages::class, $method]);
    }
  }

  public static function highlight_menu(string $parent): string {
    global $plugin_page;
    if ($plugin_page === 'nba-participant') {
      return self::SLUG;
    }
    return $parent;
  }

  public static function assets(string $hook): void {
    $screen = get_current_screen();
    $ours = str_contains($hook, 'nba-') || str_contains($hook, self::SLUG)
      || ($screen && in_array($screen->post_type, [PostTypes::PROGRAMME, PostTypes::LESSON, PostTypes::MEDIA, PostTypes::LIVE], true));
    if (!$ours) {
      return;
    }
    wp_enqueue_style('nba-admin', NBA_URL . 'assets/css/admin.css', [], NBA_VERSION);
    if ($screen && $screen->post_type === PostTypes::LESSON) {
      wp_enqueue_media();
    }
    wp_enqueue_script('nba-admin', NBA_URL . 'assets/js/admin.js', ['jquery', 'jquery-ui-sortable'], NBA_VERSION, true);
    wp_localize_script('nba-admin', 'NBAAdmin', [
      'ajax' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('nba_curriculum'),
      'i18n' => [
        'moduleName' => __('Module name (for example "Week 1: Foundations")', 'nirog-academy'),
        'saved' => __('Order saved', 'nirog-academy'),
        'saving' => __('Saving…', 'nirog-academy'),
        'error' => __('Could not save. Reload the page and try again.', 'nirog-academy'),
        'confirmDelete' => __('Delete this module? Its lessons are kept and listed under "Lessons not in a module".', 'nirog-academy'),
        'chooseFiles' => __('Choose files for this lesson', 'nirog-academy'),
        'useFiles' => __('Use these files', 'nirog-academy'),
      ],
    ]);
  }

  public static function register_settings(): void {
    register_setting('nba_settings', Settings::OPTION, [
      'type' => 'array',
      'sanitize_callback' => static function ($input) {
        $clean = Settings::sanitize(is_array($input) ? $input : []);
        nba()->audit->log('settings.updated', 0, 'settings', 0, ['launch_mode' => $clean['launch_mode']]);
        return $clean;
      },
    ]);
  }

  public static function notices(): void {
    if (!current_user_can('nba_manage_academy')) {
      return;
    }
    $screen = get_current_screen();
    if (!$screen || (!str_contains((string) $screen->id, 'nba') && !str_contains((string) $screen->id, self::SLUG))) {
      return;
    }
    if (nba()->settings->get('launch_mode') === 'closed') {
      echo '<div class="notice notice-info"><p>' . wp_kses_post(sprintf(
        /* translators: %s: settings URL */
        __('The Academy is <strong>closed</strong>: only staff can see lessons. Open it in <a href="%s">Academy → Settings</a> when you are ready.', 'nirog-academy'),
        esc_url(admin_url('admin.php?page=nba-settings'))
      )) . '</p></div>';
    }
    if (!class_exists('WooCommerce')) {
      echo '<div class="notice notice-warning"><p>' . esc_html__('WooCommerce is not active, so programmes cannot be sold. Manual enrollment still works.', 'nirog-academy') . '</p></div>';
    }
    // phpcs:ignore WordPress.Security.NonceVerification -- display only.
    if (!empty($_GET['nba_msg'])) {
      $type = !empty($_GET['nba_err']) ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification
      echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['nba_msg']))) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
    }
  }

  public static function lesson_columns(array $cols): array {
    $new = [];
    foreach ($cols as $key => $label) {
      $new[$key] = $label;
      if ($key === 'title') {
        $new['nba_programme'] = __('Programme', 'nirog-academy');
        $new['nba_day'] = __('Opens on day', 'nirog-academy');
        $new['nba_type'] = __('Type', 'nirog-academy');
      }
    }
    return $new;
  }

  public static function lesson_column(string $col, int $post_id): void {
    match ($col) {
      'nba_programme' => print(esc_html(get_the_title((int) get_post_meta($post_id, '_nba_programme_id', true)) . (get_post_meta($post_id, '_nba_placement', true) === 'bonus' ? ' · ' . __('Bonus', 'nirog-academy') : ''))),
      'nba_day' => print((int) get_post_meta($post_id, '_nba_release_day', true)),
      'nba_type' => print(esc_html(ucfirst((string) get_post_meta($post_id, '_nba_type', true) ?: 'video'))),
      default => null,
    };
  }

  public static function media_columns(array $cols): array {
    return array_slice($cols, 0, 2, true) + ['nba_source' => __('Source', 'nirog-academy'), 'nba_length' => __('Length', 'nirog-academy'), 'nba_status' => __('Status', 'nirog-academy')] + array_slice($cols, 2, null, true);
  }

  public static function media_column(string $col, int $post_id): void {
    $provider = (string) get_post_meta($post_id, '_nba_provider', true) ?: 'hls';
    if ($col === 'nba_source') {
      echo esc_html(['hls' => __('Protected HLS', 'nirog-academy'), 'file' => __('Private file', 'nirog-academy'), 'youtube' => 'YouTube', 'external' => __('External', 'nirog-academy')][$provider] ?? $provider);
    } elseif ($col === 'nba_length') {
      $sec = (int) get_post_meta($post_id, '_nba_duration_sec', true);
      echo $sec ? esc_html(gmdate($sec >= 3600 ? 'G:i:s' : 'i:s', $sec)) : '—';
    } elseif ($col === 'nba_status' && $provider === 'hls') {
      $status = nba()->stream->hls_status($post_id);
      echo '<span style="color:' . ($status['ok'] ? '#2e7d32' : '#b32d2e') . '">' . esc_html($status['message']) . '</span>';
    }
  }

  public static function redirect(string $page, string $message, bool $error = false, array $args = []): never {
    $url = add_query_arg(array_merge(['page' => $page, 'nba_msg' => rawurlencode($message)], $error ? ['nba_err' => 1] : [], $args), admin_url('admin.php'));
    wp_safe_redirect($url);
    exit;
  }
}
