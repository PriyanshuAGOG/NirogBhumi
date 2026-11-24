<?php
namespace NirogBhumi\Academy\Support;

defined('ABSPATH') || exit;

/**
 * Fixed-window rate limiter backed by transients (or the object cache when the
 * host provides one).
 */
final class RateLimiter {
  /**
   * Record one attempt and say whether it is still within the limit.
   */
  public static function hit(string $bucket, string $subject, int $max, int $window_seconds): bool {
    $key = 'nba_rl_' . substr(Crypto::hash($bucket . '|' . $subject, 'rl'), 0, 32);
    $data = get_transient($key);
    $now = time();
    if (!is_array($data) || ($data['reset'] ?? 0) <= $now) {
      $data = ['count' => 0, 'reset' => $now + $window_seconds];
    }
    $data['count']++;
    set_transient($key, $data, max(1, $data['reset'] - $now));
    return $data['count'] <= $max;
  }

  public static function too_many(string $bucket, string $subject, int $max): bool {
    $key = 'nba_rl_' . substr(Crypto::hash($bucket . '|' . $subject, 'rl'), 0, 32);
    $data = get_transient($key);
    return is_array($data) && ($data['count'] ?? 0) >= $max && ($data['reset'] ?? 0) > time();
  }

  public static function clear(string $bucket, string $subject): void {
    delete_transient('nba_rl_' . substr(Crypto::hash($bucket . '|' . $subject, 'rl'), 0, 32));
  }
}
