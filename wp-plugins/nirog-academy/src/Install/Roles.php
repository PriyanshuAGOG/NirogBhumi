<?php
namespace NirogBhumi\Academy\Install;

defined('ABSPATH') || exit;

/**
 * Roles and capabilities.
 *
 * Content (programmes, lessons, media, live sessions) uses the capability
 * type "nba_item" so it can be edited by Academy Managers without giving them
 * the right to edit normal posts and pages.
 */
final class Roles {
  /** Capabilities for Academy content post types. */
  public const CONTENT_CAPS = [
    'edit_nba_items', 'edit_others_nba_items', 'edit_published_nba_items', 'edit_private_nba_items',
    'publish_nba_items', 'read_private_nba_items',
    'delete_nba_items', 'delete_others_nba_items', 'delete_published_nba_items', 'delete_private_nba_items',
  ];

  /** Academy-specific capabilities. */
  public const ACADEMY_CAPS = [
    'nba_manage_academy',      // settings, health, media import
    'nba_manage_enrollments',  // grant, extend, pause, revoke, payment offers
    'nba_manage_devices',      // device resets and device requests
    'nba_view_participants',   // participant list and progress
    'nba_view_reports',
    'nba_send_announcements',
    'nba_view_audit',
  ];

  public static function install(): void {
    add_role('nba_participant', __('Academy Participant', 'nirog-academy'), ['read' => true]);

    add_role('nba_coach', __('Academy Coach', 'nirog-academy'), [
      'read' => true,
      'nba_view_participants' => true,
      'nba_send_announcements' => true,
    ]);

    $manager_caps = ['read' => true, 'upload_files' => true];
    foreach (self::CONTENT_CAPS as $cap) {
      $manager_caps[$cap] = true;
    }
    foreach (self::ACADEMY_CAPS as $cap) {
      $manager_caps[$cap] = $cap !== 'nba_manage_academy';
    }
    add_role('nba_manager', __('Academy Manager', 'nirog-academy'), $manager_caps);

    // Keep existing roles in sync after updates.
    $manager = get_role('nba_manager');
    if ($manager) {
      foreach ($manager_caps as $cap => $grant) {
        $manager->add_cap($cap, $grant);
      }
    }

    $admin = get_role('administrator');
    if ($admin) {
      foreach (array_merge(self::CONTENT_CAPS, self::ACADEMY_CAPS) as $cap) {
        $admin->add_cap($cap);
      }
    }
  }

  public static function uninstall(): void {
    remove_role('nba_participant');
    remove_role('nba_coach');
    remove_role('nba_manager');
    $admin = get_role('administrator');
    if ($admin) {
      foreach (array_merge(self::CONTENT_CAPS, self::ACADEMY_CAPS) as $cap) {
        $admin->remove_cap($cap);
      }
    }
  }

  /**
   * Staff are people who manage the Academy. They are exempt from the
   * one-device rule and can preview every lesson.
   */
  public static function is_staff(int $user_id): bool {
    return $user_id > 0 && (user_can($user_id, 'edit_nba_items') || user_can($user_id, 'nba_view_participants'));
  }

  public static function make_participant(int $user_id): void {
    $user = get_userdata($user_id);
    if ($user && !in_array('nba_participant', (array) $user->roles, true)) {
      $user->add_role('nba_participant');
    }
  }
}
