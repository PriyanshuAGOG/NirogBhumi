<?php
namespace NirogBhumi\Academy\Install;

use NirogBhumi\Academy\Support\Storage;

defined('ABSPATH') || exit;

/**
 * Activation, database tables, roles, secrets and versioned upgrades.
 *
 * Every schema change bumps NBA_DB_VERSION. dbDelta() adds new columns and
 * indexes safely, and data migrations go in run_migrations() keyed by version.
 */
final class Installer {
  public static function activate(): void {
    self::install_tables();
    Roles::install();
    self::ensure_secrets();
    Storage::ensure_directories();
    update_option('nba_db_version', NBA_DB_VERSION, false);
    update_option('nba_flush_rewrites', 1, false);
  }

  public static function deactivate(): void {
    wp_clear_scheduled_hook('nba_daily');
    wp_clear_scheduled_hook('nba_quarter_hourly');
    if (function_exists('as_unschedule_all_actions')) {
      as_unschedule_all_actions('nba_daily');
      as_unschedule_all_actions('nba_quarter_hourly');
    }
    flush_rewrite_rules();
  }

  /**
   * Runs on every load but does real work only after a plugin update.
   */
  public static function maybe_upgrade(): void {
    $installed = (int) get_option('nba_db_version', 0);
    if ($installed < NBA_DB_VERSION) {
      self::install_tables();
      self::run_migrations($installed);
      Roles::install();
      self::ensure_secrets();
      Storage::ensure_directories();
      update_option('nba_db_version', NBA_DB_VERSION, false);
      update_option('nba_flush_rewrites', 1, false);
    }
    if (get_option('nba_flush_rewrites')) {
      add_action('init', static function (): void {
        flush_rewrite_rules(false);
        delete_option('nba_flush_rewrites');
      }, 999);
    }
  }

  private static function run_migrations(int $from): void {
    // Version-keyed data migrations go here, for example:
    // if ($from < 2) { ... }
    do_action('nba_migrated', $from, NBA_DB_VERSION);
  }

  public static function ensure_secrets(): void {
    $secrets = get_option('nba_secrets');
    if (!is_array($secrets) || empty($secrets['sign_key'])) {
      $secrets = [
        'sign_key' => bin2hex(random_bytes(32)),
        'created_at' => gmdate('c'),
      ];
      update_option('nba_secrets', $secrets, false);
    }
  }

  public static function table(string $name): string {
    global $wpdb;
    return $wpdb->prefix . 'nba_' . $name;
  }

  public static function install_tables(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $c = $wpdb->get_charset_collate();

    $sql = [];
    $sql[] = 'CREATE TABLE ' . self::table('enrollments') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  programme_id bigint(20) unsigned NOT NULL,
  cohort_id bigint(20) unsigned DEFAULT NULL,
  order_id bigint(20) unsigned DEFAULT NULL,
  source varchar(20) NOT NULL DEFAULT 'manual',
  status varchar(20) NOT NULL DEFAULT 'active',
  starts_at datetime NOT NULL,
  ends_at datetime DEFAULT NULL,
  paused_at datetime DEFAULT NULL,
  paused_days int(11) NOT NULL DEFAULT 0,
  needs_review tinyint(1) NOT NULL DEFAULT 0,
  note text NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY order_programme (order_id,programme_id),
  KEY user_programme (user_id,programme_id),
  KEY programme_status (programme_id,status),
  KEY ends_at (ends_at)
) $c;";

    $sql[] = 'CREATE TABLE ' . self::table('cohorts') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  programme_id bigint(20) unsigned NOT NULL,
  name varchar(190) NOT NULL,
  starts_at datetime NOT NULL,
  enroll_opens_at datetime DEFAULT NULL,
  enroll_closes_at datetime DEFAULT NULL,
  capacity int(11) DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'open',
  coach_id bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY programme_status (programme_id,status)
) $c;";

    $sql[] = 'CREATE TABLE ' . self::table('progress') . " (
  user_id bigint(20) unsigned NOT NULL,
  lesson_id bigint(20) unsigned NOT NULL,
  programme_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'in_progress',
  position_sec int(11) NOT NULL DEFAULT 0,
  duration_sec int(11) NOT NULL DEFAULT 0,
  watched text NULL,
  watched_pct tinyint(3) unsigned NOT NULL DEFAULT 0,
  completed_at datetime DEFAULT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (user_id,lesson_id),
  KEY programme_user (programme_id,user_id),
  KEY lesson_status (lesson_id,status)
) $c;";

    $sql[] = 'CREATE TABLE ' . self::table('devices') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  token_hash char(64) NOT NULL,
  label varchar(190) NOT NULL DEFAULT '',
  registered_at datetime NOT NULL,
  last_seen_at datetime NOT NULL,
  revoked_at datetime DEFAULT NULL,
  revoked_by bigint(20) unsigned DEFAULT NULL,
  revoke_reason varchar(40) DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token_hash (token_hash),
  KEY user_revoked (user_id,revoked_at)
) $c;";

    $sql[] = 'CREATE TABLE ' . self::table('device_requests') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  reason text NULL,
  device_label varchar(190) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  decided_at datetime DEFAULT NULL,
  decided_by bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY user_id (user_id)
) $c;";

    $sql[] = 'CREATE TABLE ' . self::table('notifications') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  type varchar(40) NOT NULL,
  title varchar(255) NOT NULL,
  body text NULL,
  url varchar(255) NOT NULL DEFAULT '',
  dedupe_key varchar(120) DEFAULT NULL,
  read_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_dedupe (user_id,dedupe_key),
  KEY user_read (user_id,read_at)
) $c;";

    $sql[] = 'CREATE TABLE ' . self::table('notes') . " (
  user_id bigint(20) unsigned NOT NULL,
  lesson_id bigint(20) unsigned NOT NULL,
  body longtext NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (user_id,lesson_id)
) $c;";

    $sql[] = 'CREATE TABLE ' . self::table('acknowledgements') . " (
  user_id bigint(20) unsigned NOT NULL,
  programme_id bigint(20) unsigned NOT NULL,
  version varchar(20) NOT NULL,
  accepted_at datetime NOT NULL,
  ip_hash char(64) NOT NULL DEFAULT '',
  PRIMARY KEY  (user_id,programme_id,version)
) $c;";

    $sql[] = 'CREATE TABLE ' . self::table('audit_log') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(60) NOT NULL,
  subject_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  target_type varchar(30) NOT NULL DEFAULT '',
  target_id bigint(20) unsigned NOT NULL DEFAULT 0,
  context longtext NULL,
  ip_hash char(64) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY action (action),
  KEY subject_user_id (subject_user_id),
  KEY created_at (created_at)
) $c;";

    foreach ($sql as $statement) {
      dbDelta($statement);
    }
  }
}
