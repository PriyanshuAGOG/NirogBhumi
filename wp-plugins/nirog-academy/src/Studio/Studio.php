<?php
namespace NirogBhumi\Academy\Studio;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Support\Storage;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Data layer for the Course Studio: turns programmes, weeks, items and live
 * sessions into plain arrays for the Studio app, and applies its changes.
 *
 * Vocabulary used in the Studio:
 *   week    = module (nba_module)
 *   item    = lesson (nba_lesson): recorded lesson, audio practice, booklet or reading
 *   bonus   = item with placement "bonus"
 *   live    = live session (nba_live)
 */
final class Studio {
  public const ITEM_TYPES = ['video', 'audio', 'booklet', 'reading'];

  /** Item fields the Studio may change, mapped to post meta keys. */
  private const ITEM_META = [
    'type' => '_nba_type',
    'day' => '_nba_release_day',
    'duration' => '_nba_duration_min',
    'required' => '_nba_required',
    'preview' => '_nba_preview',
    'summary' => '_nba_summary',
    'safety' => '_nba_safety_note',
    'media' => '_nba_media_id',
    'media_hi' => '_nba_media_id_hi',
    'category' => '_nba_category',
    'allow_download' => '_nba_allow_download',
    'title_hi' => '_nba_title_hi',
    'summary_hi' => '_nba_summary_hi',
    'safety_hi' => '_nba_safety_note_hi',
  ];

  /** Programme settings the Studio may change. */
  private const PROGRAMME_META = [
    'summary' => '_nba_summary',
    'summary_hi' => '_nba_summary_hi',
    'title_hi' => '_nba_title_hi',
    'access_days' => '_nba_access_days',
    'drip' => '_nba_drip_mode',
    'sequential' => '_nba_sequential',
    'cohort_assignment' => '_nba_cohort_assignment',
    'element' => '_nba_element',
    'ack' => '_nba_ack_text',
    'ack_hi' => '_nba_ack_text_hi',
  ];

  // ------------------------------------------------------------------ read

  public static function programmes(): array {
    $out = [];
    foreach (get_posts(['post_type' => PostTypes::PROGRAMME, 'post_status' => ['publish', 'draft', 'private'], 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC']) as $p) {
      $out[] = self::programme_summary($p);
    }
    return $out;
  }

  private static function programme_summary(\WP_Post $p): array {
    $items = get_posts(['post_type' => PostTypes::LESSON, 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_nba_programme_id', 'meta_value' => $p->ID]);
    $counts = ['weeks' => 0, 'video' => 0, 'audio' => 0, 'booklet' => 0, 'reading' => 0, 'bonus' => 0, 'live' => 0];
    foreach ($items as $id) {
      if (get_post_meta($id, '_nba_placement', true) === 'bonus') {
        $counts['bonus']++;
        continue;
      }
      $type = (string) get_post_meta($id, '_nba_type', true) ?: 'video';
      $counts[$type] = ($counts[$type] ?? 0) + 1;
    }
    $counts['weeks'] = count(get_posts(['post_type' => PostTypes::MODULE, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_nba_programme_id', 'meta_value' => $p->ID]));
    $counts['live'] = count(get_posts(['post_type' => PostTypes::LIVE, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_nba_programme_id', 'meta_value' => $p->ID]));
    return [
      'id' => $p->ID,
      'title' => $p->post_title,
      'status' => $p->post_status,
      'element' => (string) get_post_meta($p->ID, '_nba_element', true) ?: 'earth',
      'summary' => (string) get_post_meta($p->ID, '_nba_summary', true),
      'cover' => (string) get_the_post_thumbnail_url($p->ID, 'medium_large'),
      'counts' => $counts,
      'participants' => count(nba()->enrollments->all_active($p->ID)),
      'updated' => Time::human(get_gmt_from_date($p->post_modified), 'j M Y'),
    ];
  }

  public static function programme(int $id): ?array {
    $p = get_post($id);
    if (!$p || $p->post_type !== PostTypes::PROGRAMME) {
      return null;
    }
    $data = self::programme_summary($p);
    foreach (self::PROGRAMME_META as $key => $meta) {
      $data[$key] = (string) get_post_meta($id, $meta, true);
    }
    $data['access_days'] = (int) ($data['access_days'] ?: nba()->settings->int('default_access_days'));
    $data['drip'] = $data['drip'] ?: 'enrollment';
    $data['cohort_assignment'] = $data['cohort_assignment'] ?: 'auto';
    $data['element'] = $data['element'] ?: 'earth';
    $data['sequential'] = $data['sequential'] === '1';
    $data['description'] = $p->post_content;
    $data['slug'] = $p->post_name;
    $data['view_url'] = $p->post_status === 'publish' && $p->post_name ? Router::programme_url($id) : '';
    $data['cover_id'] = (int) get_post_thumbnail_id($id);
    $data['products'] = [];
    foreach (array_filter(array_map('intval', (array) get_post_meta($id, '_nba_product_ids', true))) as $pid) {
      if (get_post($pid)) {
        $data['products'][] = ['id' => $pid, 'name' => get_the_title($pid), 'edit' => (string) get_edit_post_link($pid, 'raw')];
      }
    }

    // Weeks and their items.
    $modules = get_posts([
      'post_type' => PostTypes::MODULE, 'post_status' => 'publish', 'numberposts' => -1,
      'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'], 'meta_key' => '_nba_programme_id', 'meta_value' => $id,
    ]);
    $lessons = get_posts([
      'post_type' => PostTypes::LESSON, 'post_status' => ['publish', 'draft'], 'numberposts' => -1,
      'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'], 'meta_key' => '_nba_programme_id', 'meta_value' => $id,
    ]);
    $by_module = [];
    $bonus = [];
    foreach ($lessons as $l) {
      $item = self::item($l);
      if ($item['placement'] === 'bonus') {
        $bonus[] = $item;
      } else {
        $by_module[$item['week']][] = $item;
      }
    }
    $weeks = [];
    foreach ($modules as $m) {
      $weeks[] = [
        'id' => $m->ID,
        'title' => $m->post_title,
        'title_hi' => (string) get_post_meta($m->ID, '_nba_title_hi', true),
        'items' => $by_module[$m->ID] ?? [],
      ];
      unset($by_module[$m->ID]);
    }
    $loose = array_merge(...array_values($by_module ?: [[]]));
    $data['weeks'] = $weeks;
    $data['unsorted'] = $loose;
    $data['bonus'] = $bonus;
    $data['live'] = array_map([self::class, 'live'], get_posts([
      'post_type' => PostTypes::LIVE, 'post_status' => ['publish', 'draft'], 'numberposts' => -1,
      'meta_key' => '_nba_starts_at', 'orderby' => 'meta_value', 'order' => 'ASC',
      'meta_query' => [['key' => '_nba_programme_id', 'value' => $id]],
    ]));
    $data['team'] = \NirogBhumi\Academy\Live\LiveSessions::team();
    $data['cohorts'] = array_map(static fn($c) => ['id' => (int) $c->id, 'name' => $c->name, 'starts' => Time::human($c->starts_at, 'j M Y'), 'members' => (int) $c->members], nba()->cohorts->all($id));
    return $data;
  }

  public static function item(\WP_Post $l): array {
    $file = nba()->stream->booklet_file($l->ID);
    $resources = [];
    foreach (nba()->curriculum->resources($l->ID) as $r) {
      $resources[] = ['id' => $r['id'], 'name' => $r['name'], 'size' => size_format($r['size'])];
    }
    return [
      'id' => $l->ID,
      'title' => $l->post_title,
      'status' => $l->post_status,
      'type' => (string) get_post_meta($l->ID, '_nba_type', true) ?: 'video',
      'placement' => get_post_meta($l->ID, '_nba_placement', true) === 'bonus' ? 'bonus' : 'curriculum',
      'week' => (int) get_post_meta($l->ID, '_nba_module_id', true),
      'category' => (string) get_post_meta($l->ID, '_nba_category', true),
      'day' => (int) get_post_meta($l->ID, '_nba_release_day', true),
      'duration' => (int) get_post_meta($l->ID, '_nba_duration_min', true),
      'required' => get_post_meta($l->ID, '_nba_required', true) !== '0',
      'preview' => get_post_meta($l->ID, '_nba_preview', true) === '1',
      'allow_download' => get_post_meta($l->ID, '_nba_allow_download', true) === '1',
      'summary' => (string) get_post_meta($l->ID, '_nba_summary', true),
      'safety' => (string) get_post_meta($l->ID, '_nba_safety_note', true),
      'body' => $l->post_content,
      'media' => (int) get_post_meta($l->ID, '_nba_media_id', true),
      'media_hi' => (int) get_post_meta($l->ID, '_nba_media_id_hi', true),
      'title_hi' => (string) get_post_meta($l->ID, '_nba_title_hi', true),
      'summary_hi' => (string) get_post_meta($l->ID, '_nba_summary_hi', true),
      'safety_hi' => (string) get_post_meta($l->ID, '_nba_safety_note_hi', true),
      'file' => $file ? ['name' => $file['name'], 'size' => size_format($file['size']), 'pages' => (int) get_post_meta($l->ID, '_nba_pages', true)] : null,
      'file_url' => $file ? nba()->stream->booklet_admin_url($l->ID) : '',
      'cover' => (string) wp_get_attachment_image_url((int) get_post_meta($l->ID, '_nba_cover_id', true), 'medium_large'),
      'resources' => $resources,
      'view_url' => $l->post_status === 'publish' ? Router::lesson_url($l->ID) : '',
    ];
  }

  public static function live(\WP_Post $s): array {
    $info = \NirogBhumi\Academy\Live\LiveSessions::info($s);
    return [
      'id' => $s->ID,
      'title' => $s->post_title,
      'status' => $s->post_status,
      'description' => $s->post_content,
      'starts_at' => Time::utc_to_local_input((string) get_post_meta($s->ID, '_nba_starts_at', true)),
      'starts_label' => Time::human_time((string) get_post_meta($s->ID, '_nba_starts_at', true)),
      'past' => $info['phase'] === 'ended',
      'phase' => $info['phase'],
      'duration' => (int) get_post_meta($s->ID, '_nba_duration_min', true) ?: 60,
      'kind' => $info['kind'],
      'join_url' => $info['link'],
      'host' => $info['host'],
      'host_name' => $info['host_name'],
      'notes' => $info['notes'],
      'cancelled' => $info['cancelled'],
      'week' => $info['week'],
      'cohort' => $info['cohort'],
      'recording' => $info['recording'],
      'attended' => \NirogBhumi\Academy\Live\LiveSessions::attendance_count($s->ID),
      'meet_auto' => (string) get_post_meta($s->ID, '_nba_gcal_event_id', true) !== '',
      'view_url' => $s->post_status === 'publish' ? \NirogBhumi\Academy\Live\LiveSessions::url($s->ID) : '',
    ];
  }

  public static function media_library(): array {
    $usage = [];
    foreach (get_posts(['post_type' => PostTypes::LESSON, 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'fields' => 'ids']) as $lid) {
      foreach (['_nba_media_id', '_nba_media_id_hi'] as $k) {
        $m = (int) get_post_meta($lid, $k, true);
        if ($m) {
          $usage[$m][] = get_the_title($lid);
        }
      }
    }
    $out = [];
    foreach (get_posts(['post_type' => PostTypes::MEDIA, 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC']) as $m) {
      $provider = (string) get_post_meta($m->ID, '_nba_provider', true) ?: 'hls';
      $status = $provider === 'hls' ? nba()->stream->hls_status($m->ID) : ['ok' => true, 'message' => __('Ready', 'nirog-academy')];
      $out[] = [
        'id' => $m->ID,
        'title' => $m->post_title,
        'provider' => $provider,
        'duration' => (int) get_post_meta($m->ID, '_nba_duration_sec', true),
        'ok' => $status['ok'],
        'status' => $status['message'],
        'used_in' => array_values(array_unique($usage[$m->ID] ?? [])),
        'url' => (string) get_post_meta($m->ID, $provider === 'youtube' ? '_nba_youtube_id' : '_nba_external_url', true),
        'thumb' => $provider === 'youtube' ? 'https://i.ytimg.com/vi/' . rawurlencode((string) get_post_meta($m->ID, '_nba_youtube_id', true)) . '/mqdefault.jpg' : '',
      ];
    }
    return $out;
  }

  // ------------------------------------------------------------------ write

  public static function create_programme(string $title): int|\WP_Error {
    $id = wp_insert_post([
      'post_type' => PostTypes::PROGRAMME,
      'post_status' => 'draft',
      'post_title' => $title ?: __('Untitled programme', 'nirog-academy'),
    ], true);
    if (is_wp_error($id)) {
      return $id;
    }
    update_post_meta($id, '_nba_access_days', nba()->settings->int('default_access_days'));
    update_post_meta($id, '_nba_drip_mode', 'enrollment');
    update_post_meta($id, '_nba_cohort_assignment', 'auto');
    update_post_meta($id, '_nba_element', 'earth');
    self::add_week($id, __('Week 1', 'nirog-academy'));
    return $id;
  }

  public static function update_programme(int $id, array $data): void {
    $post = ['ID' => $id];
    if (isset($data['title'])) {
      $post['post_title'] = sanitize_text_field($data['title']);
    }
    if (isset($data['description'])) {
      $post['post_content'] = wp_kses_post($data['description']);
    }
    if (isset($data['status']) && in_array($data['status'], ['publish', 'draft'], true)) {
      $post['post_status'] = $data['status'];
    }
    if (count($post) > 1) {
      wp_update_post($post);
    }
    if (array_key_exists('cover_id', $data)) {
      (int) $data['cover_id'] ? set_post_thumbnail($id, (int) $data['cover_id']) : delete_post_thumbnail($id);
    }
    foreach (self::PROGRAMME_META as $key => $meta) {
      if (!array_key_exists($key, $data)) {
        continue;
      }
      $v = $data[$key];
      $v = match ($key) {
        'access_days' => max(1, min(3650, (int) $v)),
        'drip' => in_array($v, ['none', 'enrollment', 'cohort'], true) ? $v : 'enrollment',
        'cohort_assignment' => $v === 'manual' ? 'manual' : 'auto',
        'element' => in_array($v, ['earth', 'water', 'fire', 'air', 'space'], true) ? $v : 'earth',
        'sequential' => $v ? '1' : '0',
        'ack', 'ack_hi' => wp_kses_post((string) $v),
        'title_hi' => sanitize_text_field((string) $v),
        default => sanitize_textarea_field((string) $v),
      };
      if ($key === 'ack' && $v !== (string) get_post_meta($id, '_nba_ack_text', true)) {
        update_post_meta($id, '_nba_ack_version', $v === '' ? '' : gmdate('Ymd-His'));
      }
      update_post_meta($id, $meta, $v);
    }
    nba()->curriculum->flush($id);
  }

  public static function add_week(int $programme_id, string $title): int {
    $count = count(get_posts(['post_type' => PostTypes::MODULE, 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_nba_programme_id', 'meta_value' => $programme_id]));
    $id = (int) wp_insert_post(['post_type' => PostTypes::MODULE, 'post_status' => 'publish', 'post_title' => $title, 'menu_order' => $count]);
    update_post_meta($id, '_nba_programme_id', $programme_id);
    nba()->curriculum->flush($programme_id);
    return $id;
  }

  public static function update_week(int $id, array $data): void {
    if (isset($data['title'])) {
      wp_update_post(['ID' => $id, 'post_title' => sanitize_text_field($data['title'])]);
    }
    if (isset($data['title_hi'])) {
      update_post_meta($id, '_nba_title_hi', sanitize_text_field($data['title_hi']));
    }
    nba()->curriculum->flush((int) get_post_meta($id, '_nba_programme_id', true));
  }

  public static function create_item(int $programme_id, array $data): int|\WP_Error {
    $type = in_array($data['type'] ?? '', self::ITEM_TYPES, true) ? $data['type'] : 'video';
    $placement = ($data['placement'] ?? '') === 'bonus' ? 'bonus' : 'curriculum';
    $week = $placement === 'curriculum' ? (int) ($data['week'] ?? 0) : 0;
    $default_titles = [
      'video' => __('New recorded lesson', 'nirog-academy'),
      'audio' => __('New audio practice', 'nirog-academy'),
      'booklet' => __('New booklet', 'nirog-academy'),
      'reading' => __('New reading', 'nirog-academy'),
    ];
    // Sensible default opening day: same as the last item in the week,
    // otherwise the first day of that week.
    $day = 0;
    $order = 0;
    if ($week) {
      $siblings = get_posts(['post_type' => PostTypes::LESSON, 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC', 'meta_key' => '_nba_module_id', 'meta_value' => $week]);
      $last = end($siblings);
      $week_index = (int) get_post_field('menu_order', $week);
      $day = $last ? (int) get_post_meta($last->ID, '_nba_release_day', true) : $week_index * 7;
      $order = $week_index * 1000 + count($siblings);
    }
    $id = wp_insert_post([
      'post_type' => PostTypes::LESSON,
      'post_status' => 'draft',
      'post_title' => sanitize_text_field($data['title'] ?? '') ?: $default_titles[$type],
      'menu_order' => $order,
    ], true);
    if (is_wp_error($id)) {
      return $id;
    }
    update_post_meta($id, '_nba_programme_id', $programme_id);
    update_post_meta($id, '_nba_module_id', $week);
    update_post_meta($id, '_nba_placement', $placement);
    update_post_meta($id, '_nba_type', $type);
    update_post_meta($id, '_nba_release_day', $day);
    update_post_meta($id, '_nba_required', $placement === 'bonus' ? '0' : '1');
    if ($placement === 'bonus') {
      update_post_meta($id, '_nba_category', sanitize_text_field($data['category'] ?? '') ?: __('Bonus', 'nirog-academy'));
    }
    nba()->curriculum->flush($programme_id);
    return (int) $id;
  }

  public static function update_item(int $id, array $data): void {
    $post = ['ID' => $id];
    if (isset($data['title'])) {
      $post['post_title'] = sanitize_text_field($data['title']);
    }
    if (isset($data['body'])) {
      $post['post_content'] = wp_kses_post($data['body']);
    }
    if (isset($data['status']) && in_array($data['status'], ['publish', 'draft'], true)) {
      $post['post_status'] = $data['status'];
    }
    if (count($post) > 1) {
      wp_update_post($post);
    }
    foreach (self::ITEM_META as $key => $meta) {
      if (!array_key_exists($key, $data)) {
        continue;
      }
      $v = $data[$key];
      $v = match ($key) {
        'type' => in_array($v, self::ITEM_TYPES, true) ? $v : 'video',
        'day' => max(0, min(3650, (int) $v)),
        'duration' => max(0, min(1000, (int) $v)),
        'media', 'media_hi' => get_post_type((int) $v) === PostTypes::MEDIA ? (int) $v : 0,
        'required', 'preview', 'allow_download' => $v ? '1' : '0',
        'title_hi', 'category' => sanitize_text_field((string) $v),
        default => sanitize_textarea_field((string) $v),
      };
      update_post_meta($id, $meta, $v);
    }
    nba()->curriculum->flush((int) get_post_meta($id, '_nba_programme_id', true));
  }

  /**
   * Save the order of weeks and items after drag and drop.
   *
   * @param array<int, array{id:int, items:int[]}> $weeks
   * @param int[] $bonus
   */
  public static function reorder(int $programme_id, array $weeks, array $bonus): void {
    foreach (array_values($weeks) as $w_index => $week) {
      $week_id = (int) ($week['id'] ?? 0);
      if ($week_id && (int) get_post_meta($week_id, '_nba_programme_id', true) === $programme_id) {
        wp_update_post(['ID' => $week_id, 'menu_order' => $w_index]);
      } else {
        $week_id = 0;
      }
      foreach (array_values((array) ($week['items'] ?? [])) as $i => $item_id) {
        $item_id = (int) $item_id;
        if ((int) get_post_meta($item_id, '_nba_programme_id', true) !== $programme_id) {
          continue;
        }
        wp_update_post(['ID' => $item_id, 'menu_order' => $w_index * 1000 + $i]);
        update_post_meta($item_id, '_nba_module_id', $week_id);
        update_post_meta($item_id, '_nba_placement', 'curriculum');
      }
    }
    foreach (array_values($bonus) as $i => $item_id) {
      $item_id = (int) $item_id;
      if ((int) get_post_meta($item_id, '_nba_programme_id', true) !== $programme_id) {
        continue;
      }
      wp_update_post(['ID' => $item_id, 'menu_order' => 900000 + $i]);
      update_post_meta($item_id, '_nba_module_id', 0);
      update_post_meta($item_id, '_nba_placement', 'bonus');
    }
    nba()->curriculum->flush($programme_id);
  }

  public static function save_live(int $programme_id, array $data, int $id = 0): int|\WP_Error {
    $post = [
      'post_type' => PostTypes::LIVE,
      'post_status' => in_array($data['status'] ?? 'publish', ['publish', 'draft'], true) ? ($data['status'] ?? 'publish') : 'publish',
      'post_title' => sanitize_text_field($data['title'] ?? '') ?: __('Live session', 'nirog-academy'),
      'post_content' => sanitize_textarea_field($data['description'] ?? ''),
    ];
    if ($id) {
      $post['ID'] = $id;
    }
    $id = $id ? wp_update_post($post, true) : wp_insert_post($post, true);
    if (is_wp_error($id)) {
      return $id;
    }
    $old_start = (string) get_post_meta($id, '_nba_starts_at', true);
    $start = Time::local_to_utc((string) ($data['starts_at'] ?? '')) ?: ($old_start ?: Time::add_days(Time::now(), 7));
    update_post_meta($id, '_nba_programme_id', $programme_id);
    update_post_meta($id, '_nba_starts_at', $start);
    update_post_meta($id, '_nba_duration_min', max(5, (int) ($data['duration'] ?? 60)));
    update_post_meta($id, '_nba_join_url', esc_url_raw((string) ($data['join_url'] ?? '')));
    update_post_meta($id, '_nba_module_id', (int) ($data['week'] ?? 0));
    update_post_meta($id, '_nba_cohort_id', (int) ($data['cohort'] ?? 0));
    update_post_meta($id, '_nba_recording_lesson_id', (int) ($data['recording'] ?? 0));
    update_post_meta($id, '_nba_live_kind', ($data['kind'] ?? '') === 'stream' ? 'stream' : 'meeting');
    $host = (int) ($data['host'] ?? 0);
    update_post_meta($id, '_nba_host_id', $host && user_can($host, 'nba_view_participants') ? $host : 0);
    update_post_meta($id, '_nba_staff_notes', sanitize_textarea_field((string) ($data['notes'] ?? '')));
    if ($start !== $old_start) {
      delete_post_meta($id, '_nba_reminded_24h');
      delete_post_meta($id, '_nba_reminded_15m');
    }
    nba()->curriculum->flush($programme_id);
    return (int) $id;
  }

  // ------------------------------------------------------------------ uploads

  /**
   * Store an uploaded booklet PDF in the private files folder.
   */
  public static function upload_booklet(int $item_id, array $file): array|\WP_Error {
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], ['pdf' => 'application/pdf']);
    if (($check['ext'] ?? '') !== 'pdf') {
      return new \WP_Error('nba_type', __('Booklets must be PDF files.', 'nirog-academy'), ['status' => 400]);
    }
    Storage::ensure_directories();
    $dir = Storage::dir('files') . '/booklets';
    wp_mkdir_p($dir);
    $name = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'booklet';
    $relative = 'booklets/' . $item_id . '-' . substr(wp_generate_password(8, false), 0, 8) . '-' . $name . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], Storage::dir('files') . '/' . $relative) && !rename($file['tmp_name'], Storage::dir('files') . '/' . $relative)) {
      return new \WP_Error('nba_move', __('Could not save the file on the server.', 'nirog-academy'), ['status' => 500]);
    }
    $old = (string) get_post_meta($item_id, '_nba_file', true);
    if ($old && ($old_path = Storage::resolve('files', $old))) {
      wp_delete_file($old_path);
    }
    update_post_meta($item_id, '_nba_file', $relative);
    update_post_meta($item_id, '_nba_file_name', sanitize_file_name($file['name']));
    delete_post_meta($item_id, '_nba_pages');
    return self::item(get_post($item_id));
  }

  /**
   * Booklet cover: an image of the first page (made by the Studio in the
   * browser right after upload) or one the team chooses. Covers are ordinary
   * images, so they can be shown in lists without the access check.
   */
  public static function upload_cover(int $item_id, array $file, int $pages = 0): array|\WP_Error {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']);
    if (empty($check['ext'])) {
      return new \WP_Error('nba_type', __('Covers must be JPG, PNG or WebP images.', 'nirog-academy'), ['status' => 400]);
    }
    $file['name'] = 'booklet-cover-' . $item_id . '.' . $check['ext'];
    $attachment = media_handle_sideload($file, $item_id, sprintf('Booklet cover: %s', get_the_title($item_id)));
    if (is_wp_error($attachment)) {
      return $attachment;
    }
    $old = (int) get_post_meta($item_id, '_nba_cover_id', true);
    if ($old && (int) wp_get_post_parent_id($old) === $item_id) {
      wp_delete_attachment($old, true);
    }
    update_post_meta($item_id, '_nba_cover_id', (int) $attachment);
    if ($pages > 0) {
      update_post_meta($item_id, '_nba_pages', min(2000, $pages));
    }
    return self::item(get_post($item_id));
  }

  /**
   * Add a private download (worksheet, chart, audio) to an item.
   */
  public static function upload_resource(int $item_id, array $file): array|\WP_Error {
    $allowed = ['pdf' => 'application/pdf', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    Storage::ensure_directories();
    $private = static function (array $u): array {
      $u['path'] = Storage::dir('files') . '/lessons';
      $u['url'] = Storage::base_url() . '/files/lessons';
      $u['subdir'] = '/nirog-academy/files/lessons';
      wp_mkdir_p($u['path']);
      return $u;
    };
    add_filter('upload_dir', $private, 99);
    $result = wp_handle_upload($file, ['test_form' => false, 'mimes' => $allowed]);
    remove_filter('upload_dir', $private, 99);
    if (!empty($result['error'])) {
      return new \WP_Error('nba_upload', $result['error'], ['status' => 400]);
    }
    $attachment = wp_insert_attachment([
      'post_mime_type' => $result['type'],
      'post_title' => sanitize_text_field(pathinfo($file['name'], PATHINFO_FILENAME)),
      'post_status' => 'inherit',
      'post_parent' => $item_id,
    ], $result['file'], $item_id);
    if (is_wp_error($attachment)) {
      return $attachment;
    }
    $ids = array_values(array_filter(array_map('intval', (array) get_post_meta($item_id, '_nba_resources', true))));
    $ids[] = (int) $attachment;
    update_post_meta($item_id, '_nba_resources', $ids);
    return self::item(get_post($item_id));
  }

  public static function remove_resource(int $item_id, int $attachment_id): array {
    $ids = array_values(array_diff(array_map('intval', (array) get_post_meta($item_id, '_nba_resources', true)), [$attachment_id]));
    update_post_meta($item_id, '_nba_resources', $ids);
    if ((int) wp_get_post_parent_id($attachment_id) === $item_id) {
      wp_delete_attachment($attachment_id, true);
    }
    return self::item(get_post($item_id));
  }

  public static function participants_count(int $programme_id): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT user_id) FROM ' . Installer::table('enrollments') . " WHERE programme_id = %d AND status IN ('active','paused','pending')", $programme_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
  }
}
