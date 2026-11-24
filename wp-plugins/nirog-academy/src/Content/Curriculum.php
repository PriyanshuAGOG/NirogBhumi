<?php
namespace NirogBhumi\Academy\Content;

defined('ABSPATH') || exit;

/**
 * Reads programme structure: Programme → Modules → Lessons, plus bonus items
 * and live sessions. Results are cached per programme and cleared whenever
 * any of its content is saved.
 */
final class Curriculum {
  private const CACHE_GROUP = 'nba_curriculum';

  /** @var array<int, array> */
  private array $memo = [];

  public function register(): void {
    $flush = function (int $post_id): void {
      $type = get_post_type($post_id);
      if (!in_array($type, [PostTypes::PROGRAMME, PostTypes::MODULE, PostTypes::LESSON, PostTypes::LIVE], true)) {
        return;
      }
      $programme_id = $type === PostTypes::PROGRAMME ? $post_id : (int) get_post_meta($post_id, '_nba_programme_id', true);
      if ($programme_id) {
        $this->flush($programme_id);
      }
    };
    add_action('save_post', $flush, 20);
    add_action('before_delete_post', $flush);
    add_action('trashed_post', $flush);
    add_action('untrashed_post', $flush);
    add_action('updated_post_meta', function ($meta_id, $post_id, $key) use ($flush): void {
      if (in_array($key, ['_nba_programme_id', '_nba_module_id', '_nba_placement', '_nba_release_day'], true)) {
        $flush((int) $post_id);
      }
    }, 10, 3);
  }

  public function flush(int $programme_id): void {
    unset($this->memo[$programme_id]);
    delete_transient('nba_tree_' . $programme_id);
  }

  /**
   * @return array{
   *   modules: array<int, array{id:int, title:string, lessons:int[]}>,
   *   lessons: int[],
   *   required: int[],
   *   bonus: int[],
   * }
   */
  public function tree(int $programme_id): array {
    if (isset($this->memo[$programme_id])) {
      return $this->memo[$programme_id];
    }
    $cached = get_transient('nba_tree_' . $programme_id);
    if (is_array($cached) && isset($cached['v']) && $cached['v'] === 2) {
      return $this->memo[$programme_id] = $cached['tree'];
    }

    $modules = get_posts([
      'post_type' => PostTypes::MODULE,
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
      'meta_key' => '_nba_programme_id',
      'meta_value' => $programme_id,
      'fields' => 'ids',
    ]);

    $lessons = get_posts([
      'post_type' => PostTypes::LESSON,
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
      'meta_key' => '_nba_programme_id',
      'meta_value' => $programme_id,
      'fields' => 'ids',
    ]);

    $by_module = [];
    $bonus = [];
    foreach ($lessons as $lesson_id) {
      if (get_post_meta($lesson_id, '_nba_placement', true) === 'bonus') {
        $bonus[] = (int) $lesson_id;
        continue;
      }
      $by_module[(int) get_post_meta($lesson_id, '_nba_module_id', true)][] = (int) $lesson_id;
    }

    $tree = ['modules' => [], 'lessons' => [], 'required' => [], 'bonus' => $bonus];
    foreach ($modules as $module_id) {
      $ids = $by_module[(int) $module_id] ?? [];
      $tree['modules'][] = ['id' => (int) $module_id, 'lessons' => $ids];
      foreach ($ids as $id) {
        $tree['lessons'][] = $id;
        if (get_post_meta($id, '_nba_required', true) !== '0') {
          $tree['required'][] = $id;
        }
      }
    }
    // Lessons whose module was deleted still appear, at the end, so nothing silently disappears.
    $orphans = [];
    foreach ($by_module as $module_id => $ids) {
      if (!in_array($module_id, array_map('intval', $modules), true)) {
        $orphans = array_merge($orphans, $ids);
      }
    }
    if ($orphans) {
      $tree['modules'][] = ['id' => 0, 'lessons' => $orphans];
      foreach ($orphans as $id) {
        $tree['lessons'][] = $id;
        if (get_post_meta($id, '_nba_required', true) !== '0') {
          $tree['required'][] = $id;
        }
      }
    }

    set_transient('nba_tree_' . $programme_id, ['v' => 2, 'tree' => $tree], DAY_IN_SECONDS);
    return $this->memo[$programme_id] = $tree;
  }

  public function programme_of(int $post_id): int {
    $type = get_post_type($post_id);
    if ($type === PostTypes::PROGRAMME) {
      return $post_id;
    }
    return (int) get_post_meta($post_id, '_nba_programme_id', true);
  }

  public function lesson_by_slug(string $slug): ?\WP_Post {
    $posts = get_posts([
      'post_type' => PostTypes::LESSON,
      'name' => sanitize_title($slug),
      'post_status' => 'publish',
      'numberposts' => 1,
    ]);
    return $posts[0] ?? null;
  }

  public function programme_by_slug(string $slug): ?\WP_Post {
    $posts = get_posts([
      'post_type' => PostTypes::PROGRAMME,
      'name' => sanitize_title($slug),
      'post_status' => 'publish',
      'numberposts' => 1,
    ]);
    return $posts[0] ?? null;
  }

  /**
   * Previous and next lessons in curriculum order.
   *
   * @return array{0: int, 1: int}
   */
  public function neighbours(int $lesson_id): array {
    $programme_id = $this->programme_of($lesson_id);
    $flat = $this->tree($programme_id)['lessons'];
    $i = array_search($lesson_id, $flat, true);
    if ($i === false) {
      return [0, 0];
    }
    return [$flat[$i - 1] ?? 0, $flat[$i + 1] ?? 0];
  }

  public function module_of(int $lesson_id): int {
    return (int) get_post_meta($lesson_id, '_nba_module_id', true);
  }

  /**
   * Upcoming live sessions for a programme (and, if given, a batch).
   *
   * @return \WP_Post[]
   */
  public function live_sessions(int $programme_id, ?int $cohort_id, bool $upcoming = true, int $limit = 20): array {
    $now = gmdate('Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS);
    $meta = [
      'relation' => 'AND',
      ['key' => '_nba_programme_id', 'value' => $programme_id],
      [
        'relation' => 'OR',
        ['key' => '_nba_cohort_id', 'value' => 0],
        ['key' => '_nba_cohort_id', 'compare' => 'NOT EXISTS'],
        ['key' => '_nba_cohort_id', 'value' => (int) $cohort_id],
      ],
      ['key' => '_nba_starts_at', 'value' => $now, 'compare' => $upcoming ? '>=' : '<', 'type' => 'DATETIME'],
    ];
    return get_posts([
      'post_type' => PostTypes::LIVE,
      'post_status' => 'publish',
      'numberposts' => $limit,
      'meta_query' => $meta,
      'meta_key' => '_nba_starts_at',
      'orderby' => 'meta_value',
      'order' => $upcoming ? 'ASC' : 'DESC',
    ]);
  }

  /**
   * @return array<int, array{id:int, name:string, url:string, size:int}>
   */
  public function resources(int $lesson_id): array {
    $ids = array_filter(array_map('intval', (array) get_post_meta($lesson_id, '_nba_resources', true)));
    $out = [];
    foreach ($ids as $id) {
      $file = get_attached_file($id);
      if (!$file) {
        continue;
      }
      $out[] = [
        'id' => $id,
        'name' => get_the_title($id) ?: wp_basename($file),
        'file' => wp_basename($file),
        'size' => file_exists($file) ? (int) filesize($file) : 0,
      ];
    }
    return $out;
  }
}
