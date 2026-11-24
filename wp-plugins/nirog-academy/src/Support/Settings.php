<?php
namespace NirogBhumi\Academy\Support;

defined('ABSPATH') || exit;

/**
 * Plugin settings stored in a single option. Defaults live here so every part
 * of the plugin reads the same values.
 */
final class Settings {
  public const OPTION = 'nba_settings';

  private ?array $cache = null;

  public static function defaults(): array {
    return [
      // Launch switch: closed (staff only), preview (staff + preview lessons), open.
      'launch_mode' => 'closed',
      'area_name' => 'Nirog Bhumi Academy',
      'base_slug' => 'academy',

      // Access.
      'default_access_days' => 365,
      'grace_days' => 0,
      'completion_threshold' => 90,

      // Devices.
      'max_devices' => 1,
      'self_reset_interval_days' => 30,
      'device_code_ttl_minutes' => 15,
      'staff_device_exempt' => 1,

      // Video.
      'watermark' => 1,
      'signed_url_ttl' => 21600,

      // Notifications.
      'email_from_name' => 'Nirog Bhumi',
      'email_lesson_unlocked' => 1,
      'nudge_after_days' => 3,
      'expiry_warning_days' => 7,

      // Support.
      'support_email' => get_option('admin_email'),
      'support_whatsapp' => '',

      // Data.
      'delete_data_on_uninstall' => 0,
      'audit_retention_days' => 730,
    ];
  }

  public function all(): array {
    if ($this->cache === null) {
      $stored = get_option(self::OPTION, []);
      $this->cache = array_merge(self::defaults(), is_array($stored) ? $stored : []);
    }
    return $this->cache;
  }

  public function get(string $key, mixed $fallback = null): mixed {
    $all = $this->all();
    return $all[$key] ?? $fallback;
  }

  public function int(string $key): int {
    return (int) $this->get($key, 0);
  }

  public function update(array $values): void {
    $merged = array_merge($this->all(), $values);
    update_option(self::OPTION, $merged, true);
    $this->cache = null;
  }

  public function flush(): void {
    $this->cache = null;
  }

  public static function sanitize(array $input): array {
    $d = self::defaults();
    $out = [];
    $out['launch_mode'] = in_array($input['launch_mode'] ?? '', ['closed', 'preview', 'open'], true) ? $input['launch_mode'] : $d['launch_mode'];
    $out['area_name'] = sanitize_text_field($input['area_name'] ?? $d['area_name']) ?: $d['area_name'];
    $slug = sanitize_title($input['base_slug'] ?? $d['base_slug']);
    $out['base_slug'] = $slug ?: $d['base_slug'];
    $out['default_access_days'] = max(1, min(3650, (int) ($input['default_access_days'] ?? $d['default_access_days'])));
    $out['grace_days'] = max(0, min(365, (int) ($input['grace_days'] ?? 0)));
    $out['completion_threshold'] = max(50, min(100, (int) ($input['completion_threshold'] ?? $d['completion_threshold'])));
    $out['max_devices'] = max(1, min(5, (int) ($input['max_devices'] ?? 1)));
    $out['self_reset_interval_days'] = max(0, min(365, (int) ($input['self_reset_interval_days'] ?? 30)));
    $out['device_code_ttl_minutes'] = max(5, min(60, (int) ($input['device_code_ttl_minutes'] ?? 15)));
    $out['staff_device_exempt'] = empty($input['staff_device_exempt']) ? 0 : 1;
    $out['watermark'] = empty($input['watermark']) ? 0 : 1;
    $out['signed_url_ttl'] = max(300, min(86400, (int) ($input['signed_url_ttl'] ?? 21600)));
    $out['email_from_name'] = sanitize_text_field($input['email_from_name'] ?? $d['email_from_name']);
    $out['email_lesson_unlocked'] = empty($input['email_lesson_unlocked']) ? 0 : 1;
    $out['nudge_after_days'] = max(0, min(30, (int) ($input['nudge_after_days'] ?? 3)));
    $out['expiry_warning_days'] = max(0, min(60, (int) ($input['expiry_warning_days'] ?? 7)));
    $out['support_email'] = sanitize_email($input['support_email'] ?? '') ?: $d['support_email'];
    $out['support_whatsapp'] = preg_replace('/\D+/', '', (string) ($input['support_whatsapp'] ?? ''));
    $out['delete_data_on_uninstall'] = empty($input['delete_data_on_uninstall']) ? 0 : 1;
    $out['audit_retention_days'] = max(30, min(3650, (int) ($input['audit_retention_days'] ?? 730)));
    if ($out['base_slug'] !== (get_option(self::OPTION)['base_slug'] ?? $d['base_slug'])) {
      update_option('nba_flush_rewrites', 1, false);
    }
    return $out;
  }
}
