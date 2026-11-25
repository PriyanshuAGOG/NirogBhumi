<?php
namespace NirogBhumi\Academy;

use NirogBhumi\Academy\Access\Gate;
use NirogBhumi\Academy\Admin\Admin;
use NirogBhumi\Academy\Audit\AuditLog;
use NirogBhumi\Academy\Cli\Commands;
use NirogBhumi\Academy\Content\Curriculum;
use NirogBhumi\Academy\Content\MetaBoxes;
use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Device\Devices;
use NirogBhumi\Academy\Enrollment\Cohorts;
use NirogBhumi\Academy\Enrollment\Enrollments;
use NirogBhumi\Academy\Enrollment\WooBridge;
use NirogBhumi\Academy\Frontend\Auth;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Media\Stream;
use NirogBhumi\Academy\Notify\Notifications;
use NirogBhumi\Academy\Notify\Scheduler;
use NirogBhumi\Academy\Progress\Progress;
use NirogBhumi\Academy\Rest\Routes;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Service container and boot order. Each service is created once and can be
 * reached through nba()->service_name.
 *
 * @property-read Settings      $settings
 * @property-read AuditLog      $audit
 * @property-read Curriculum    $curriculum
 * @property-read Enrollments   $enrollments
 * @property-read Cohorts       $cohorts
 * @property-read Devices       $devices
 * @property-read Gate          $gate
 * @property-read Progress      $progress
 * @property-read Notifications $notifications
 * @property-read Stream        $stream
 */
final class Plugin {
  private static ?Plugin $instance = null;

  /** @var array<string, object> */
  private array $services = [];

  public static function instance(): Plugin {
    return self::$instance ??= new self();
  }

  public function __get(string $name): object {
    if (!isset($this->services[$name])) {
      throw new \LogicException(sprintf('Unknown Courses service "%s".', $name));
    }
    return $this->services[$name];
  }

  public function boot(): void {
    $this->services = [
      'settings' => new Settings(),
      'audit' => new AuditLog(),
      'curriculum' => new Curriculum(),
      'enrollments' => new Enrollments(),
      'cohorts' => new Cohorts(),
      'devices' => new Devices(),
      'progress' => new Progress(),
      'notifications' => new Notifications(),
      'stream' => new Stream(),
    ];
    $this->services['gate'] = new Gate();

    Installer::maybe_upgrade();
    I18n::register();
    PostTypes::register();
    MetaBoxes::register();
    $this->curriculum->register();
    $this->enrollments->register();
    $this->devices->register();
    $this->notifications->register();
    $this->stream->register();
    Scheduler::register();
    Routes::register();
    \NirogBhumi\Academy\Rest\StudioRoutes::register();
    \NirogBhumi\Academy\Live\GoogleMeet::register();
    Router::register();
    Auth::register();

    if (class_exists('WooCommerce') || defined('WC_VERSION')) {
      WooBridge::register();
    } else {
      add_action('woocommerce_loaded', [WooBridge::class, 'register']);
    }

    if (is_admin()) {
      Admin::register();
    }

    if (defined('WP_CLI') && WP_CLI) {
      Commands::register();
    }

    do_action('nba_loaded', $this);
  }
}
