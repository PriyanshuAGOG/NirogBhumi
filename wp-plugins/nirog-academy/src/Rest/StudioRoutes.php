<?php
namespace NirogBhumi\Academy\Rest;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Media\Importer;
use NirogBhumi\Academy\Studio\Studio;

defined('ABSPATH') || exit;

/**
 * REST API for the Course Studio: /wp-json/nirog-academy/v1/studio/…
 *
 * Every route needs the edit_nba_items capability (Administrator, Academy
 * Manager) and the wp_rest nonce. Object-level checks make sure an item,
 * week or session belongs to the programme being edited.
 */
final class StudioRoutes {
  private const NS = Routes::NS;

  public static function register(): void {
    add_action('rest_api_init', [self::class, 'routes']);
  }

  public static function can_edit(): bool {
    return current_user_can('edit_nba_items');
  }

  private static function route(string $path, string $methods, callable $cb): void {
    register_rest_route(self::NS, '/studio' . $path, [
      'methods' => $methods,
      'permission_callback' => [self::class, 'can_edit'],
      'callback' => $cb,
    ]);
  }

  public static function routes(): void {
    self::route('/programmes', 'GET', static fn() => rest_ensure_response(Studio::programmes()));
    self::route('/programmes', 'POST', static function (\WP_REST_Request $r) {
      $id = Studio::create_programme(sanitize_text_field((string) $r['title']));
      return is_wp_error($id) ? $id : rest_ensure_response(Studio::programme($id));
    });
    self::route('/programmes/(?P<id>\d+)', 'GET', static function (\WP_REST_Request $r) {
      $data = Studio::programme((int) $r['id']);
      return $data ? rest_ensure_response($data) : new \WP_Error('nba_not_found', 'Not found', ['status' => 404]);
    });
    self::route('/programmes/(?P<id>\d+)', 'POST', static function (\WP_REST_Request $r) {
      $id = self::programme_id($r);
      if (is_wp_error($id)) {
        return $id;
      }
      Studio::update_programme($id, (array) $r->get_json_params());
      return rest_ensure_response(Studio::programme($id));
    });
    self::route('/programmes/(?P<id>\d+)', 'DELETE', static function (\WP_REST_Request $r) {
      $id = self::programme_id($r);
      if (is_wp_error($id)) {
        return $id;
      }
      if (Studio::participants_count($id) > 0) {
        return new \WP_Error('nba_in_use', __('This programme has participants. Unpublish it instead of deleting it.', 'nirog-academy'), ['status' => 409]);
      }
      wp_trash_post($id);
      return rest_ensure_response(['ok' => true]);
    });
    self::route('/programmes/(?P<id>\d+)/order', 'POST', static function (\WP_REST_Request $r) {
      $id = self::programme_id($r);
      if (is_wp_error($id)) {
        return $id;
      }
      Studio::reorder($id, (array) ($r['weeks'] ?? []), array_map('intval', (array) ($r['bonus'] ?? [])));
      return rest_ensure_response(Studio::programme($id));
    });

    // Weeks.
    self::route('/programmes/(?P<id>\d+)/weeks', 'POST', static function (\WP_REST_Request $r) {
      $id = self::programme_id($r);
      if (is_wp_error($id)) {
        return $id;
      }
      Studio::add_week($id, sanitize_text_field((string) $r['title']) ?: __('New week', 'nirog-academy'));
      return rest_ensure_response(Studio::programme($id));
    });
    self::route('/weeks/(?P<id>\d+)', 'POST', static function (\WP_REST_Request $r) {
      $week = self::owned((int) $r['id'], PostTypes::MODULE);
      if (is_wp_error($week)) {
        return $week;
      }
      Studio::update_week($week, (array) $r->get_json_params());
      return rest_ensure_response(['ok' => true]);
    });
    self::route('/weeks/(?P<id>\d+)', 'DELETE', static function (\WP_REST_Request $r) {
      $week = self::owned((int) $r['id'], PostTypes::MODULE);
      if (is_wp_error($week)) {
        return $week;
      }
      $programme = (int) get_post_meta($week, '_nba_programme_id', true);
      wp_trash_post($week);
      nba()->curriculum->flush($programme);
      return rest_ensure_response(Studio::programme($programme));
    });

    // Items (lessons, booklets, bonus).
    self::route('/programmes/(?P<id>\d+)/items', 'POST', static function (\WP_REST_Request $r) {
      $id = self::programme_id($r);
      if (is_wp_error($id)) {
        return $id;
      }
      $item = Studio::create_item($id, (array) $r->get_json_params());
      return is_wp_error($item) ? $item : rest_ensure_response(Studio::item(get_post($item)));
    });
    self::route('/items/(?P<id>\d+)', 'POST', static function (\WP_REST_Request $r) {
      $item = self::owned((int) $r['id'], PostTypes::LESSON);
      if (is_wp_error($item)) {
        return $item;
      }
      Studio::update_item($item, (array) $r->get_json_params());
      return rest_ensure_response(Studio::item(get_post($item)));
    });
    self::route('/items/(?P<id>\d+)', 'DELETE', static function (\WP_REST_Request $r) {
      $item = self::owned((int) $r['id'], PostTypes::LESSON);
      if (is_wp_error($item)) {
        return $item;
      }
      wp_trash_post($item);
      nba()->curriculum->flush((int) get_post_meta($item, '_nba_programme_id', true));
      return rest_ensure_response(['ok' => true]);
    });
    self::route('/items/(?P<id>\d+)/booklet', 'POST', static function (\WP_REST_Request $r) {
      $item = self::owned((int) $r['id'], PostTypes::LESSON);
      if (is_wp_error($item)) {
        return $item;
      }
      $files = $r->get_file_params();
      if (empty($files['file']['tmp_name'])) {
        return new \WP_Error('nba_no_file', __('No file received. It may be larger than the server allows.', 'nirog-academy'), ['status' => 400]);
      }
      return rest_ensure_response(Studio::upload_booklet($item, $files['file']));
    });
    self::route('/items/(?P<id>\d+)/files', 'POST', static function (\WP_REST_Request $r) {
      $item = self::owned((int) $r['id'], PostTypes::LESSON);
      if (is_wp_error($item)) {
        return $item;
      }
      $files = $r->get_file_params();
      if (empty($files['file']['tmp_name'])) {
        return new \WP_Error('nba_no_file', __('No file received. It may be larger than the server allows.', 'nirog-academy'), ['status' => 400]);
      }
      return rest_ensure_response(Studio::upload_resource($item, $files['file']));
    });
    self::route('/items/(?P<id>\d+)/files/(?P<file>\d+)', 'DELETE', static function (\WP_REST_Request $r) {
      $item = self::owned((int) $r['id'], PostTypes::LESSON);
      if (is_wp_error($item)) {
        return $item;
      }
      return rest_ensure_response(Studio::remove_resource($item, (int) $r['file']));
    });

    // Live sessions.
    self::route('/programmes/(?P<id>\d+)/live', 'POST', static function (\WP_REST_Request $r) {
      $id = self::programme_id($r);
      if (is_wp_error($id)) {
        return $id;
      }
      $live = Studio::save_live($id, (array) $r->get_json_params());
      return is_wp_error($live) ? $live : rest_ensure_response(Studio::live(get_post($live)));
    });
    self::route('/live/(?P<id>\d+)', 'POST', static function (\WP_REST_Request $r) {
      $live = self::owned((int) $r['id'], PostTypes::LIVE);
      if (is_wp_error($live)) {
        return $live;
      }
      $saved = Studio::save_live((int) get_post_meta($live, '_nba_programme_id', true), (array) $r->get_json_params(), $live);
      return is_wp_error($saved) ? $saved : rest_ensure_response(Studio::live(get_post($saved)));
    });
    self::route('/live/(?P<id>\d+)', 'DELETE', static function (\WP_REST_Request $r) {
      $live = self::owned((int) $r['id'], PostTypes::LIVE);
      if (is_wp_error($live)) {
        return $live;
      }
      wp_trash_post($live);
      return rest_ensure_response(['ok' => true]);
    });

    // Video library.
    self::route('/media', 'GET', static fn() => rest_ensure_response(Studio::media_library()));
    self::route('/media', 'POST', static function (\WP_REST_Request $r) {
      $provider = in_array($r['provider'], ['youtube', 'external'], true) ? $r['provider'] : '';
      $url = trim((string) $r['url']);
      if (!$provider || $url === '') {
        return new \WP_Error('nba_media', __('Choose a source and paste a link.', 'nirog-academy'), ['status' => 400]);
      }
      $id = wp_insert_post(['post_type' => PostTypes::MEDIA, 'post_status' => 'publish', 'post_title' => sanitize_text_field((string) $r['title']) ?: __('Untitled video', 'nirog-academy')], true);
      if (is_wp_error($id)) {
        return $id;
      }
      update_post_meta($id, '_nba_provider', $provider);
      if ($provider === 'youtube') {
        $yt = \NirogBhumi\Academy\Content\MetaBoxes::youtube_id($url);
        if ($yt === '') {
          wp_delete_post($id, true);
          return new \WP_Error('nba_media', __('That does not look like a YouTube link.', 'nirog-academy'), ['status' => 400]);
        }
        update_post_meta($id, '_nba_youtube_id', $yt);
      } else {
        update_post_meta($id, '_nba_external_url', esc_url_raw($url));
      }
      update_post_meta($id, '_nba_duration_sec', max(0, (int) $r['duration']) * 60);
      return rest_ensure_response(Studio::media_library());
    });
    self::route('/media/import', 'POST', static function () {
      if (!current_user_can('nba_manage_academy') && !current_user_can('edit_nba_items')) {
        return new \WP_Error('nba_forbidden', 'Forbidden', ['status' => 403]);
      }
      $results = Importer::run();
      return rest_ensure_response(['results' => $results, 'media' => Studio::media_library()]);
    });
    self::route('/media/(?P<id>\d+)', 'POST', static function (\WP_REST_Request $r) {
      $id = (int) $r['id'];
      if (get_post_type($id) !== PostTypes::MEDIA) {
        return new \WP_Error('nba_not_found', 'Not found', ['status' => 404]);
      }
      if (isset($r['title'])) {
        wp_update_post(['ID' => $id, 'post_title' => sanitize_text_field((string) $r['title'])]);
      }
      return rest_ensure_response(Studio::media_library());
    });
  }

  private static function programme_id(\WP_REST_Request $r): int|\WP_Error {
    $id = (int) $r['id'];
    if (get_post_type($id) !== PostTypes::PROGRAMME || !current_user_can('edit_post', $id)) {
      return new \WP_Error('nba_not_found', 'Not found', ['status' => 404]);
    }
    return $id;
  }

  private static function owned(int $id, string $type): int|\WP_Error {
    if (get_post_type($id) !== $type || !current_user_can('edit_post', $id)) {
      return new \WP_Error('nba_not_found', 'Not found', ['status' => 404]);
    }
    return $id;
  }
}
