<?php
namespace NirogBhumi\Academy\Cli;

use NirogBhumi\Academy\Admin\Demo;
use NirogBhumi\Academy\Media\Importer;
use NirogBhumi\Academy\Notify\Scheduler;

defined('ABSPATH') || exit;

/**
 * WP-CLI commands (Hostinger plans include WP-CLI over SSH).
 *
 *   wp academy import-videos
 *   wp academy enroll <email> <programme-id> [--days=<n>]
 *   wp academy device-reset <email>
 *   wp academy run-daily
 *   wp academy sample
 */
final class Commands {
  public static function register(): void {
    \WP_CLI::add_command('academy', self::class);
  }

  /**
   * Register newly uploaded HLS folders in the Video Library.
   *
   * @subcommand import-videos
   */
  public function import_videos(): void {
    foreach (Importer::run() as $r) {
      \WP_CLI::log(sprintf('%-40s %-9s %s', $r['folder'], $r['status'], $r['message'] ?? ($r['media_id'] ?? '')));
    }
    \WP_CLI::success('Done.');
  }

  /**
   * Enroll a person by email (creates the account if needed).
   *
   * ## OPTIONS
   * <email>
   * : Participant email.
   * <programme>
   * : Programme ID.
   */
  public function enroll(array $args): void {
    [$email, $programme] = $args;
    $user = get_user_by('email', $email);
    $user_id = $user ? $user->ID : \NirogBhumi\Academy\Enrollment\WooBridge::create_user($email);
    if (!$user_id) {
      \WP_CLI::error('Could not create the user.');
    }
    $result = nba()->enrollments->create(['user_id' => $user_id, 'programme_id' => (int) $programme, 'source' => 'manual']);
    if (is_wp_error($result)) {
      \WP_CLI::error($result->get_error_message());
    }
    \WP_CLI::success("Enrollment #$result");
  }

  /**
   * Free a participant's device slot.
   *
   * @subcommand device-reset
   */
  public function device_reset(array $args): void {
    $user = get_user_by('email', $args[0]);
    if (!$user) {
      \WP_CLI::error('No such user.');
    }
    nba()->devices->admin_reset($user->ID, 'wp-cli');
    \WP_CLI::success('Device reset.');
  }

  /**
   * Run the daily jobs now (lesson emails, reminders, expiry).
   *
   * @subcommand run-daily
   */
  public function run_daily(): void {
    do_action('nba_daily');
    Scheduler::quarter_hourly();
    \WP_CLI::success('Daily jobs ran.');
  }

  /**
   * Create the sample programme.
   */
  public function sample(): void {
    $id = Demo::create();
    if (is_wp_error($id)) {
      \WP_CLI::error($id->get_error_message());
    }
    \WP_CLI::success("Sample programme #$id created as a draft.");
  }
}
