<?php
namespace NirogBhumi\Academy\Support;

defined('ABSPATH') || exit;

/**
 * Small, explicit crypto helpers. Tokens are random, stored only as keyed
 * hashes, and compared in constant time.
 */
final class Crypto {
  private static function key(): string {
    $secrets = get_option('nba_secrets');
    if (!is_array($secrets) || empty($secrets['sign_key'])) {
      \NirogBhumi\Academy\Install\Installer::ensure_secrets();
      $secrets = get_option('nba_secrets');
    }
    return (string) $secrets['sign_key'];
  }

  public static function token(int $bytes = 32): string {
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
  }

  public static function hash(string $value, string $purpose = 'generic'): string {
    return hash_hmac('sha256', $purpose . '|' . $value, self::key());
  }

  public static function sign(array $parts): string {
    return hash_hmac('sha256', implode('|', array_map('strval', $parts)), self::key());
  }

  public static function verify(array $parts, string $signature): bool {
    return $signature !== '' && hash_equals(self::sign($parts), $signature);
  }

  public static function code(int $digits = 6): string {
    return str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
  }

  public static function ip(): string {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
  }

  public static function ip_hash(): string {
    return self::hash(self::ip(), 'ip');
  }
}
