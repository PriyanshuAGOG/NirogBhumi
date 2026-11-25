<?php
/**
 * Runs when the plugin is deleted from Plugins → Installed Plugins.
 *
 * Data is kept unless "Delete all course data when the plugin is deleted"
 * is ticked in Courses → Settings. Enrollments and progress are business
 * records, so the safe default is to keep them.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

$settings = get_option('nba_settings', []);
if (empty($settings['delete_data_on_uninstall'])) {
  return;
}

global $wpdb;
foreach (['enrollments', 'cohorts', 'progress', 'devices', 'device_requests', 'notifications', 'notes', 'acknowledgements', 'audit_log', 'attendance'] as $table) {
  $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nba_' . $table); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

foreach (['nba_programme', 'nba_module', 'nba_lesson', 'nba_media', 'nba_live', 'nba_announcement'] as $type) {
  $ids = get_posts(['post_type' => $type, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']);
  foreach ($ids as $id) {
    wp_delete_post($id, true);
  }
}

foreach (['nba_settings', 'nba_secrets', 'nba_db_version', 'nba_flush_rewrites', 'nba_announcement_queue', 'nba_google', 'nba_demo'] as $option) {
  delete_option($option);
}
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'nba\\_%'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

require_once __DIR__ . '/src/Install/Roles.php';
NirogBhumi\Academy\Install\Roles::uninstall();

wp_clear_scheduled_hook('nba_daily');
wp_clear_scheduled_hook('nba_quarter_hourly');
// Private files in wp-content/uploads/nirog-academy/ are left in place on purpose:
// delete that folder by hand if you no longer need the videos.
