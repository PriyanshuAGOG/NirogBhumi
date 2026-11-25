<?php
/**
 * Plugin Name:       Nirog Bhumi Academy
 * Plugin URI:        https://nirogbhumi.com/academy/
 * Description:       Programmes, protected lessons, progress tracking, one-device access and notifications for Nirog Bhumi participants. Sells programmes through WooCommerce.
 * Version:           1.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Nirog Bhumi
 * License:           GPL-2.0-or-later
 * Text Domain:       nirog-academy
 * Domain Path:       /languages
 *
 * @package NirogBhumi\Academy
 */

defined('ABSPATH') || exit;

define('NBA_VERSION', '1.2.0');
define('NBA_DB_VERSION', 2);
define('NBA_FILE', __FILE__);
define('NBA_DIR', plugin_dir_path(__FILE__));
define('NBA_URL', plugin_dir_url(__FILE__));

/*
 * PSR-4 autoloader for the NirogBhumi\Academy namespace. The plugin has no
 * runtime Composer dependencies, so nothing needs to be built on the server.
 */
spl_autoload_register(static function (string $class): void {
  $prefix = 'NirogBhumi\\Academy\\';
  if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
    return;
  }
  $relative = substr($class, strlen($prefix));
  $file = NBA_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';
  if (is_readable($file)) {
    require $file;
  }
});

if (version_compare(PHP_VERSION, '8.1', '<')) {
  add_action('admin_notices', static function (): void {
    echo '<div class="notice notice-error"><p>' . esc_html__('Nirog Bhumi Academy needs PHP 8.1 or newer. Ask your host to upgrade PHP.', 'nirog-academy') . '</p></div>';
  });
  return;
}

register_activation_hook(__FILE__, [NirogBhumi\Academy\Install\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [NirogBhumi\Academy\Install\Installer::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
  NirogBhumi\Academy\Plugin::instance()->boot();
}, 5);

/**
 * Global accessor, mainly for templates.
 */
function nba(): NirogBhumi\Academy\Plugin {
  return NirogBhumi\Academy\Plugin::instance();
}
