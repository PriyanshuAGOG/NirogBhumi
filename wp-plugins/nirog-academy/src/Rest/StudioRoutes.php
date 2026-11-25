<?php
namespace NirogBhumi\Academy\Rest;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Media\Importer;
use NirogBhumi\Academy\Studio\Studio;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * REST API for the Course Studio: /wp-json/nirog-academy/v1/studio/…
 *
 * Every route needs the edit_nba_items capability (Administrator, Courses
 * Manager) and the wp_rest nonce. Object-level checks make sure an item,
 * week or session belongs to the programme being edited.
 */
final class StudioRoutes {
  private const NS = Routes::NS;

  public static function register(): void {
    add_action('rest_api_init', [self::class, 'routes']);
  }

  /**
   * Title and thumbnail of a YouTube video, from YouTube's public oEmbed
   * endpoint. Returns null when YouTube cannot be reached (the video is
   * still added), or an error when YouTube says it cannot be embedded.
   *
   * @return array{title:string,thumb:string}|\WP_Error|null
   */
  public static function youtube_info(string $yt): array|\WP_Error|null {
    $res = wp_remote_get('https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=' . $yt), ['timeout' => 8]);
    if (is_wp_error($res)) {
      return null;
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code === 401 || $code === 403) {
      return new \WP_Error('nba_media', __('This video does not allow embedding. In YouTube Studio open the video, Show more, and tick "Allow embedding", then add it again.', 'nirog-academy'), ['status' => 400]);
    }
    if ($code === 404 || $code === 400) {
      return new \WP_Error('nba_media', __('YouTube cannot find this video. If it is Private, change it to Unlisted and add it again.', 'nirog-academy'), ['status' => 400]);
    }
    $data = json_decode((string) wp_remote_retrieve_body($res), true);
    if ($code !== 200 || !is_array($data)) {
      return null;
    }
    return ['title' => (string) ($data['title'] ?? ''), 'thumb' => (string) ($data['thumbnail_url'] ?? '')];
  }

  /**
   * What needs the team's attention, for the top of the Studio.
   *
   * @return array<string,mixed>
   */
  public static function glance(): array {
    $counts = nba()->enrollments->count_by_status();
    $sessions = get_posts([
      'post_type' => PostTypes::LIVE,
      'post_status' => ['publish', 'draft'],
      'numberposts' => 8,
      'meta_key' => '_nba_starts_at',
      'orderby' => 'meta_value',
      'order' => 'ASC',
      'meta_query' => [['key' => '_nba_starts_at', 'value' => [gmdate('Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS), gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS)], 'compare' => 'BETWEEN', 'type' => 'DATETIME']],
    ]);
    $live = [];
    foreach ($sessions as $s) {
      $info = \NirogBhumi\Academy\Live\LiveSessions::info($s);
      if ($info['phase'] === 'ended' || $info['cancelled']) {
        continue;
      }
      $live[] = [
        'id' => $s->ID,
        'title' => $s->post_title,
        'programme' => (int) get_post_meta($s->ID, '_nba_programme_id', true),
        'when' => Time::human_time((string) get_post_meta($s->ID, '_nba_starts_at', true)),
        'phase' => $info['phase'],
        'has_link' => $info['link'] !== '',
        'draft' => $s->post_status !== 'publish',
      ];
    }
    return [
      'active' => (int) ($counts['active'] ?? 0),
      'waiting' => (int) ($counts['pending'] ?? 0),
      'devices' => count(nba()->devices->requests('pending')),
      'live' => array_slice($live, 0, 5),
      'open' => nba()->settings->get('launch_mode') !== 'closed',
      'links' => [
        'enrollments' => admin_url('admin.php?page=nba-enrollments'),
        'devices' => admin_url('admin.php?page=nba-devices'),
        'liveDesk' => admin_url('admin.php?page=nba-live-desk'),
        'settings' => admin_url('admin.php?page=nba-settings'),
      ],
    ];
  }

  /** @return array<string,mixed> */
  public static function demo_info(): array {
    $demo = \NirogBhumi\Academy\Admin\Demo::class;
    if (!$demo::exists()) {
      return ['exists' => false, 'canManage' => current_user_can('nba_manage_academy')];
    }
    $state = $demo::state();
    $programme_id = (int) $state['programme'];
    return [
      'exists' => true,
      'canManage' => current_user_can('nba_manage_academy'),
      'programme' => $programme_id,
      'login' => (string) ($state['login'] ?? ''),
      'password' => (string) ($state['password'] ?? ''),
      // While Nirog Bhumi Courses is Closed its own login page is hidden too, so the
      // WordPress login is used; it lands on the course pages afterwards.
      'loginUrl' => \NirogBhumi\Academy\Admin\Demo::login_url(),
      'academyUrl' => \NirogBhumi\Academy\Frontend\Router::url(),
      'programmeUrl' => \NirogBhumi\Academy\Frontend\Router::programme_url($programme_id),
      'open' => nba()->settings->get('launch_mode') !== 'closed',
    ];
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
    self::route('/demo', 'GET', static fn() => rest_ensure_response(self::demo_info()));
    self::route('/glance', 'GET', static fn() => rest_ensure_response(self::glance()));
    self::route('/demo', 'POST', static function () {
      if (!current_user_can('nba_manage_academy')) {
        return new \WP_Error('nba_forbidden', __('Only an administrator can load the demo.', 'nirog-academy'), ['status' => 403]);
      }
      $id = \NirogBhumi\Academy\Admin\Demo::create();
      return is_wp_error($id) ? $id : rest_ensure_response(self::demo_info());
    });
    self::route('/demo', 'DELETE', static function () {
      if (!current_user_can('nba_manage_academy')) {
        return new \WP_Error('nba_forbidden', __('Only an administrator can remove the demo.', 'nirog-academy'), ['status' => 403]);
      }
      \NirogBhumi\Academy\Admin\Demo::remove();
      return rest_ensure_response(self::demo_info());
    });
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
    self::route('/items/(?P<id>\d+)/cover', 'POST', static function (\WP_REST_Request $r) {
      $item = self::owned((int) $r['id'], PostTypes::LESSON);
      if (is_wp_error($item)) {
        return $item;
      }
      $files = $r->get_file_params();
      if (empty($files['file']['tmp_name'])) {
        return new \WP_Error('nba_no_file', __('No image received.', 'nirog-academy'), ['status' => 400]);
      }
      return rest_ensure_response(Studio::upload_cover($item, $files['file'], (int) $r['pages']));
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
      if (!is_wp_error($saved)) {
        \NirogBhumi\Academy\Live\GoogleMeet::sync_session($saved);
      }
      return is_wp_error($saved) ? $saved : rest_ensure_response(Studio::live(get_post($saved)));
    });
    self::route('/live/(?P<id>\d+)/meet', 'POST', static function (\WP_REST_Request $r) {
      $live = self::owned((int) $r['id'], PostTypes::LIVE);
      if (is_wp_error($live)) {
        return $live;
      }
      $link = \NirogBhumi\Academy\Live\GoogleMeet::create_for_session($live);
      return is_wp_error($link) ? $link : rest_ensure_response(Studio::live(get_post($live)));
    });
    self::route('/live/(?P<id>\d+)', 'DELETE', static function (\WP_REST_Request $r) {
      $live = self::owned((int) $r['id'], PostTypes::LIVE);
      if (is_wp_error($live)) {
        return $live;
      }
      wp_trash_post($live);
      return rest_ensure_response(['ok' => true]);
    });

    self::route('/live/(?P<id>\d+)/attendance', 'GET', static function (\WP_REST_Request $r) {
      $live = self::owned((int) $r['id'], PostTypes::LIVE);
      return is_wp_error($live) ? $live : rest_ensure_response(\NirogBhumi\Academy\Live\LiveSessions::attendance($live));
    });
    self::route('/live/(?P<id>\d+)/remind', 'POST', static function (\WP_REST_Request $r) {
      $live = self::owned((int) $r['id'], PostTypes::LIVE);
      if (is_wp_error($live)) {
        return $live;
      }
      if (get_post_status($live) !== 'publish') {
        return new \WP_Error('nba_draft', __('Make the session visible to participants first.', 'nirog-academy'), ['status' => 400]);
      }
      $sent = \NirogBhumi\Academy\Live\LiveSessions::remind($live, 'now');
      nba()->audit->log('live.reminded', 0, 'live', $live, ['people' => $sent]);
      return rest_ensure_response(['sent' => $sent]);
    });
    self::route('/live/(?P<id>\d+)/cancel', 'POST', static function (\WP_REST_Request $r) {
      $live = self::owned((int) $r['id'], PostTypes::LIVE);
      if (is_wp_error($live)) {
        return $live;
      }
      $notified = \NirogBhumi\Academy\Live\LiveSessions::set_cancelled($live, (bool) $r['cancelled']);
      \NirogBhumi\Academy\Live\GoogleMeet::sync_session($live);
      return rest_ensure_response(['live' => Studio::live(get_post($live)), 'notified' => $notified]);
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
        // YouTube tells us the title and whether the video may be shown
        // inside another site (needs "Allow embedding" in YouTube Studio).
        $info = self::youtube_info($yt);
        if (is_wp_error($info)) {
          wp_delete_post($id, true);
          return $info;
        }
        if ($info && trim((string) $r['title']) === '') {
          wp_update_post(['ID' => $id, 'post_title' => sanitize_text_field($info['title'])]);
        }
        if ($info) {
          update_post_meta($id, '_nba_thumb_url', esc_url_raw($info['thumb']));
        }
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
