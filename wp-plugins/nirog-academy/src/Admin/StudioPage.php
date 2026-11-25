<?php
namespace NirogBhumi\Academy\Admin;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Support\Storage;

defined('ABSPATH') || exit;

/**
 * Course Studio: a dedicated app inside WordPress admin for designing
 * programmes — weeks, recorded lessons, audio practices, booklets, readings,
 * bonus content and live sessions — without WordPress's form screens.
 *
 * Built with WordPress's own bundled React (wp-element) and htm, so there is
 * no build step and nothing loads from outside the site.
 */
final class StudioPage {
  private static function is_studio(): bool {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    return $screen && $screen->id === 'toplevel_page_' . Admin::SLUG && current_user_can('edit_nba_items');
  }

  /** The Studio is a focused workspace: no admin notices from other plugins. */
  public static function quiet_screen(\WP_Screen $screen): void {
    if ($screen->id === 'toplevel_page_' . Admin::SLUG && current_user_can('edit_nba_items')) {
      remove_all_actions('admin_notices');
      remove_all_actions('all_admin_notices');
      add_filter('admin_body_class', static fn(string $c) => $c . ' nba-studio-screen');
      add_filter('admin_footer_text', '__return_empty_string', 99);
      add_filter('update_footer', '__return_empty_string', 99);
    }
  }

  /** Old list screens open the Studio instead. */
  public static function redirect_lists(): void {
    $type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    if (in_array($type, [PostTypes::PROGRAMME, PostTypes::LESSON, PostTypes::LIVE], true) && current_user_can('edit_nba_items')) {
      wp_safe_redirect(admin_url('admin.php?page=' . Admin::SLUG));
      exit;
    }
  }

  public static function assets(string $hook): void {
    if ($hook !== 'toplevel_page_' . Admin::SLUG || !current_user_can('edit_nba_items')) {
      return;
    }
    wp_enqueue_media();
    wp_register_script('nba-htm', NBA_URL . 'assets/vendor/htm.umd.js', [], '3.1.1', true);
    wp_register_script('nba-sortable', NBA_URL . 'assets/vendor/sortable.min.js', [], '1.15.6', true);
    wp_enqueue_script('nba-studio', NBA_URL . 'assets/js/studio.js', ['wp-element', 'nba-htm', 'nba-sortable'], NBA_VERSION, true);
    wp_enqueue_style('nba-studio', NBA_URL . 'assets/css/studio.css', [], NBA_VERSION);

    $user = wp_get_current_user();
    wp_localize_script('nba-studio', 'NBAStudio', [
      'rest' => esc_url_raw(rest_url('nirog-academy/v1/studio/')),
      'nonce' => wp_create_nonce('wp_rest'),
      'maxUpload' => (int) wp_max_upload_size(),
      'maxUploadLabel' => size_format(wp_max_upload_size()),
      'firstName' => $user->first_name ?: $user->display_name,
      'academyUrl' => Router::url(),
      'mediaFolder' => str_replace(wp_normalize_path(ABSPATH), '/', wp_normalize_path(Storage::dir('media'))),
      'canImport' => current_user_can('nba_manage_academy'),
      'pdfjs' => NBA_URL . 'assets/vendor/pdfjs/pdf.min.js?ver=4.10.38',
      'pdfWorker' => NBA_URL . 'assets/vendor/pdfjs/pdf.worker.min.js?ver=4.10.38',
      'links' => [
        'enrollments' => admin_url('admin.php?page=nba-enrollments'),
        'batches' => admin_url('admin.php?page=nba-batches'),
        'overview' => admin_url('admin.php?page=nba-overview'),
        'liveDesk' => admin_url('admin.php?page=nba-live-desk'),
        'reports' => admin_url('admin.php?page=nba-reports'),
        'settings' => admin_url('admin.php?page=nba-settings'),
        'videoHelp' => admin_url('admin.php?page=nba-help&doc=prepare-and-upload-videos'),
        'help' => admin_url('admin.php?page=nba-help'),
        'products' => admin_url('edit.php?post_type=product'),
      ],
    ]);
  }

  public static function render(): void {
    echo '<div id="nba-studio" class="nba-studio"><div class="st-loading">' . esc_html__('Opening the Studio…', 'nirog-academy') . '</div></div>';
    echo '<noscript>' . esc_html__('The Course Studio needs JavaScript.', 'nirog-academy') . '</noscript>';
  }
}
