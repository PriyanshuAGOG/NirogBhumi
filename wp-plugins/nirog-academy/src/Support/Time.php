<?php
namespace NirogBhumi\Academy\Support;

defined('ABSPATH') || exit;

/**
 * All Academy datetimes are stored in UTC ('Y-m-d H:i:s') and shown in the
 * site's timezone (Settings > General, Asia/Kolkata for Nirog Bhumi).
 */
final class Time {
  public static function now(): string {
    return gmdate('Y-m-d H:i:s');
  }

  public static function ts(?string $utc): int {
    if (!$utc) {
      return 0;
    }
    $t = strtotime($utc . ' UTC');
    return $t === false ? 0 : $t;
  }

  public static function add_days(string $utc, int $days): string {
    return gmdate('Y-m-d H:i:s', self::ts($utc) + $days * DAY_IN_SECONDS);
  }

  /**
   * Format a UTC datetime for people, in the site timezone.
   */
  public static function human(?string $utc, string $format = ''): string {
    if (!$utc) {
      return '';
    }
    return wp_date($format ?: (string) get_option('date_format', 'j M Y'), self::ts($utc));
  }

  public static function human_time(?string $utc): string {
    return self::human($utc, get_option('date_format', 'j M Y') . ', ' . get_option('time_format', 'g:i a'));
  }

  /**
   * Convert a site-local "Y-m-d" or "Y-m-d\TH:i" form value to UTC.
   */
  public static function local_to_utc(string $local): ?string {
    $local = trim($local);
    if ($local === '') {
      return null;
    }
    try {
      $dt = new \DateTimeImmutable(str_replace('T', ' ', $local), wp_timezone());
      return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (\Exception) {
      return null;
    }
  }

  /**
   * UTC to a value for <input type="datetime-local">.
   */
  public static function utc_to_local_input(?string $utc, bool $with_time = true): string {
    if (!$utc) {
      return '';
    }
    return wp_date($with_time ? 'Y-m-d\TH:i' : 'Y-m-d', self::ts($utc));
  }

  /**
   * Whole days between two UTC datetimes, counted on the site's calendar.
   */
  public static function days_between(string $from_utc, string $to_utc): int {
    $tz = wp_timezone();
    $a = (new \DateTimeImmutable('@' . self::ts($from_utc)))->setTimezone($tz)->setTime(0, 0);
    $b = (new \DateTimeImmutable('@' . self::ts($to_utc)))->setTimezone($tz)->setTime(0, 0);
    return (int) $a->diff($b)->format('%r%a');
  }

  /**
   * Start of the site-local day $days after $utc, returned in UTC. Lessons
   * unlock at local midnight, not at the exact minute of enrollment.
   */
  public static function local_day_start_after(string $utc, int $days): string {
    $tz = wp_timezone();
    $local = (new \DateTimeImmutable('@' . self::ts($utc)))->setTimezone($tz)->setTime(0, 0)->modify('+' . $days . ' days');
    return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }
}
