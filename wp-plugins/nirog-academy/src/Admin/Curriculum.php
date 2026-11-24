<?php
namespace NirogBhumi\Academy\Admin;

use NirogBhumi\Academy\Content\PostTypes;

defined('ABSPATH') || exit;

/**
 * AJAX endpoints for the drag-and-drop curriculum builder on the programme
 * edit screen.
 */
final class Curriculum {
  private static function guard(): int {
    check_ajax_referer('nba_curriculum', 'nonce');
    $programme_id = isset($_POST['programme']) ? absint($_POST['programme']) : 0;
    if (!$programme_id || get_post_type($programme_id) !== PostTypes::PROGRAMME || !current_user_can('edit_post', $programme_id)) {
      wp_send_json_error(['message' => 'forbidden'], 403);
    }
    return $programme_id;
  }

  /**
   * Save module order and lesson order/placement in one go.
   * Expects modules = [{id, lessons: [id, …]}, …].
   */
  public static function cb_order(): void {
    $programme_id = self::guard();
    $modules = isset($_POST['modules']) ? json_decode((string) wp_unslash($_POST['modules']), true) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and cast below.
    if (!is_array($modules)) {
      wp_send_json_error(['message' => 'bad input'], 400);
    }
    foreach (array_values($modules) as $m_index => $module) {
      $module_id = absint($module['id'] ?? 0);
      if ($module_id && get_post_type($module_id) === PostTypes::MODULE && (int) get_post_meta($module_id, '_nba_programme_id', true) === $programme_id) {
        wp_update_post(['ID' => $module_id, 'menu_order' => $m_index]);
      } else {
        $module_id = 0;
      }
      foreach (array_values((array) ($module['lessons'] ?? [])) as $l_index => $lesson_id) {
        $lesson_id = absint($lesson_id);
        if (get_post_type($lesson_id) !== PostTypes::LESSON || (int) get_post_meta($lesson_id, '_nba_programme_id', true) !== $programme_id || !current_user_can('edit_post', $lesson_id)) {
          continue;
        }
        wp_update_post(['ID' => $lesson_id, 'menu_order' => $m_index * 1000 + $l_index]);
        update_post_meta($lesson_id, '_nba_module_id', $module_id);
      }
    }
    nba()->curriculum->flush($programme_id);
    wp_send_json_success();
  }

  public static function cb_add_module(): void {
    $programme_id = self::guard();
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    if ($title === '') {
      wp_send_json_error(['message' => 'empty'], 400);
    }
    $count = count(get_posts(['post_type' => PostTypes::MODULE, 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_nba_programme_id', 'meta_value' => $programme_id]));
    $id = wp_insert_post(['post_type' => PostTypes::MODULE, 'post_status' => 'publish', 'post_title' => $title, 'menu_order' => $count]);
    if (is_wp_error($id)) {
      wp_send_json_error(['message' => $id->get_error_message()], 500);
    }
    update_post_meta($id, '_nba_programme_id', $programme_id);
    nba()->curriculum->flush($programme_id);
    wp_send_json_success([
      'id' => $id,
      'title' => $title,
      'add_lesson' => admin_url('post-new.php?post_type=' . PostTypes::LESSON . '&nba_programme=' . $programme_id . '&nba_module=' . $id),
    ]);
  }

  public static function cb_rename_module(): void {
    $programme_id = self::guard();
    $module_id = isset($_POST['module']) ? absint($_POST['module']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    if (!$module_id || $title === '' || (int) get_post_meta($module_id, '_nba_programme_id', true) !== $programme_id) {
      wp_send_json_error(['message' => 'bad input'], 400);
    }
    wp_update_post(['ID' => $module_id, 'post_title' => $title]);
    nba()->curriculum->flush($programme_id);
    wp_send_json_success();
  }

  public static function cb_delete_module(): void {
    $programme_id = self::guard();
    $module_id = isset($_POST['module']) ? absint($_POST['module']) : 0;
    if (!$module_id || (int) get_post_meta($module_id, '_nba_programme_id', true) !== $programme_id) {
      wp_send_json_error(['message' => 'bad input'], 400);
    }
    wp_trash_post($module_id);
    nba()->curriculum->flush($programme_id);
    wp_send_json_success();
  }
}
