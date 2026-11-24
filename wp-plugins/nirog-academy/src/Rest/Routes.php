<?php
namespace NirogBhumi\Academy\Rest;

use NirogBhumi\Academy\Support\RateLimiter;

defined('ABSPATH') || exit;

/**
 * REST API: /wp-json/nirog-academy/v1/
 *
 * All endpoints need a logged-in user and the standard wp_rest nonce
 * (X-WP-Nonce header). Lesson endpoints also run the full access check.
 */
final class Routes {
  public const NS = 'nirog-academy/v1';

  public static function register(): void {
    add_action('rest_api_init', [self::class, 'routes']);
  }

  public static function routes(): void {
    $logged_in = static fn() => is_user_logged_in();
    $lesson_arg = ['id' => ['type' => 'integer', 'required' => true, 'minimum' => 1]];

    register_rest_route(self::NS, '/lessons/(?P<id>\d+)/progress', [
      'methods' => 'POST',
      'permission_callback' => $logged_in,
      'callback' => [self::class, 'progress'],
      'args' => $lesson_arg + [
        'position' => ['type' => 'number', 'default' => 0],
        'duration' => ['type' => 'number', 'default' => 0],
        'ranges' => ['type' => 'array', 'default' => [], 'items' => ['type' => 'array', 'items' => ['type' => 'number']]],
        'stream' => ['type' => 'string', 'default' => ''],
      ],
    ]);

    register_rest_route(self::NS, '/lessons/(?P<id>\d+)/complete', [
      'methods' => 'POST',
      'permission_callback' => $logged_in,
      'callback' => [self::class, 'complete'],
      'args' => $lesson_arg,
    ]);

    register_rest_route(self::NS, '/lessons/(?P<id>\d+)/note', [
      'methods' => 'POST',
      'permission_callback' => $logged_in,
      'callback' => [self::class, 'note'],
      'args' => $lesson_arg + ['body' => ['type' => 'string', 'default' => '']],
    ]);

    register_rest_route(self::NS, '/stream/claim', [
      'methods' => 'POST',
      'permission_callback' => $logged_in,
      'callback' => [self::class, 'claim'],
      'args' => ['stream' => ['type' => 'string', 'required' => true]],
    ]);

    register_rest_route(self::NS, '/notifications', [
      [
        'methods' => 'GET',
        'permission_callback' => $logged_in,
        'callback' => [self::class, 'notifications'],
      ],
    ]);
    register_rest_route(self::NS, '/notifications/read', [
      'methods' => 'POST',
      'permission_callback' => $logged_in,
      'callback' => [self::class, 'read'],
      'args' => ['id' => ['type' => 'integer', 'default' => 0]],
    ]);
  }

  private static function guard(int $lesson_id): \WP_Error|int {
    $access = nba()->gate->check_lesson(get_current_user_id(), $lesson_id, false);
    if (!$access->allowed) {
      return new \WP_Error('nba_forbidden', $access->message, ['status' => 403, 'code' => $access->code]);
    }
    return $access->programme_id;
  }

  public static function progress(\WP_REST_Request $r): \WP_REST_Response|\WP_Error {
    $user_id = get_current_user_id();
    if (!RateLimiter::hit('progress', (string) $user_id, 40, MINUTE_IN_SECONDS)) {
      return new \WP_Error('nba_rate', 'Slow down', ['status' => 429]);
    }
    $lesson_id = (int) $r['id'];
    $programme_id = self::guard($lesson_id);
    if (is_wp_error($programme_id)) {
      return $programme_id;
    }
    $stream = substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $r['stream']), 0, 40);
    if ($stream !== '' && !nba()->progress->stream_allowed($user_id, $stream)) {
      return new \WP_REST_Response(['stop' => true], 409);
    }
    $ranges = [];
    foreach ((array) $r['ranges'] as $range) {
      if (is_array($range) && count($range) >= 2) {
        $ranges[] = [(float) $range[0], (float) $range[1]];
      }
    }
    $result = nba()->progress->heartbeat($user_id, $lesson_id, $programme_id, (float) $r['position'], (float) $r['duration'], $ranges);
    return new \WP_REST_Response($result);
  }

  public static function complete(\WP_REST_Request $r): \WP_REST_Response|\WP_Error {
    $lesson_id = (int) $r['id'];
    $programme_id = self::guard($lesson_id);
    if (is_wp_error($programme_id)) {
      return $programme_id;
    }
    $result = nba()->progress->complete(get_current_user_id(), $lesson_id, $programme_id);
    if (is_wp_error($result)) {
      $result->add_data(['status' => 400]);
      return $result;
    }
    return new \WP_REST_Response(['completed' => true, 'summary' => nba()->progress->summary(get_current_user_id(), $programme_id)]);
  }

  public static function note(\WP_REST_Request $r): \WP_REST_Response|\WP_Error {
    $lesson_id = (int) $r['id'];
    $programme_id = self::guard($lesson_id);
    if (is_wp_error($programme_id)) {
      return $programme_id;
    }
    nba()->progress->save_note(get_current_user_id(), $lesson_id, (string) $r['body']);
    return new \WP_REST_Response(['saved' => true]);
  }

  public static function claim(\WP_REST_Request $r): \WP_REST_Response {
    nba()->progress->claim_stream(get_current_user_id(), substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $r['stream']), 0, 40));
    return new \WP_REST_Response(['ok' => true]);
  }

  public static function notifications(): \WP_REST_Response {
    $user_id = get_current_user_id();
    $items = array_map(static fn($n) => [
      'id' => (int) $n->id,
      'type' => $n->type,
      'title' => $n->title,
      'body' => $n->body,
      'url' => $n->url,
      'read' => (bool) $n->read_at,
      'created_at' => mysql_to_rfc3339($n->created_at),
    ], nba()->notifications->list($user_id, 20));
    return new \WP_REST_Response(['unread' => nba()->notifications->unread_count($user_id), 'items' => $items]);
  }

  public static function read(\WP_REST_Request $r): \WP_REST_Response {
    nba()->notifications->mark_read(get_current_user_id(), (int) $r['id'] ?: null);
    return new \WP_REST_Response(['ok' => true]);
  }
}
