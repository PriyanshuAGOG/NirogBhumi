<?php
namespace NirogBhumi\Academy\Content;

defined('ABSPATH') || exit;

/**
 * Content types. None of them are public in the WordPress sense: participants
 * only reach content through the Academy routes, which run the access check.
 */
final class PostTypes {
  public const PROGRAMME = 'nba_programme';
  public const MODULE = 'nba_module';
  public const LESSON = 'nba_lesson';
  public const MEDIA = 'nba_media';
  public const LIVE = 'nba_live';
  public const ANNOUNCEMENT = 'nba_announcement';

  public static function register(): void {
    add_action('init', [self::class, 'register_types'], 5);
  }

  public static function register_types(): void {
    $base = [
      'public' => false,
      'publicly_queryable' => false,
      'exclude_from_search' => true,
      'show_ui' => true,
      'show_in_menu' => 'nirog-academy',
      'show_in_nav_menus' => false,
      'show_in_admin_bar' => false,
      'show_in_rest' => false,
      'has_archive' => false,
      'rewrite' => false,
      'query_var' => false,
      'capability_type' => ['nba_item', 'nba_items'],
      'map_meta_cap' => true,
      'hierarchical' => false,
    ];

    register_post_type(self::PROGRAMME, $base + [
      'labels' => self::labels(__('Programme', 'nirog-academy'), __('Programmes', 'nirog-academy')),
      'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
      'menu_icon' => 'dashicons-welcome-learn-more',
    ]);

    register_post_type(self::MODULE, array_merge($base, [
      'labels' => self::labels(__('Module', 'nirog-academy'), __('Modules', 'nirog-academy')),
      'supports' => ['title', 'page-attributes'],
      'show_in_menu' => false,
    ]));

    register_post_type(self::LESSON, $base + [
      'labels' => self::labels(__('Lesson', 'nirog-academy'), __('Lessons', 'nirog-academy')),
      'supports' => ['title', 'editor', 'page-attributes', 'revisions'],
    ]);

    register_post_type(self::MEDIA, $base + [
      'labels' => self::labels(__('Video / Audio', 'nirog-academy'), __('Video Library', 'nirog-academy')),
      'supports' => ['title'],
    ]);

    register_post_type(self::LIVE, $base + [
      'labels' => self::labels(__('Live Session', 'nirog-academy'), __('Live Sessions', 'nirog-academy')),
      'supports' => ['title', 'editor'],
    ]);

    register_post_type(self::ANNOUNCEMENT, array_merge($base, [
      'labels' => self::labels(__('Announcement', 'nirog-academy'), __('Announcements', 'nirog-academy')),
      'supports' => ['title', 'editor'],
      'show_ui' => false,
    ]));
  }

  private static function labels(string $singular, string $plural): array {
    return [
      'name' => $plural,
      'singular_name' => $singular,
      'menu_name' => $plural,
      'all_items' => $plural,
      /* translators: %s: content type name */
      'add_new_item' => sprintf(__('Add %s', 'nirog-academy'), $singular),
      /* translators: %s: content type name */
      'edit_item' => sprintf(__('Edit %s', 'nirog-academy'), $singular),
      /* translators: %s: content type name */
      'new_item' => sprintf(__('New %s', 'nirog-academy'), $singular),
      /* translators: %s: content type name */
      'search_items' => sprintf(__('Search %s', 'nirog-academy'), $plural),
      /* translators: %s: content type name */
      'not_found' => sprintf(__('No %s found.', 'nirog-academy'), strtolower($plural)),
      'add_new' => __('Add new', 'nirog-academy'),
    ];
  }
}
