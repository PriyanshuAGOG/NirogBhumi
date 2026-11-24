<?php
namespace NirogBhumi\Academy\Content;

use NirogBhumi\Academy\Support\Storage;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Edit screens for programmes, lessons, media and live sessions.
 */
final class MetaBoxes {
  private const NONCE = 'nba_meta_nonce';

  public static function register(): void {
    add_action('add_meta_boxes', [self::class, 'add']);
    add_action('save_post', [self::class, 'save'], 10, 2);
    add_filter('upload_dir', [self::class, 'protect_lesson_uploads']);
  }

  public static function add(): void {
    add_meta_box('nba_programme_settings', __('Programme settings', 'nirog-academy'), [self::class, 'programme_box'], PostTypes::PROGRAMME, 'normal', 'high');
    add_meta_box('nba_programme_curriculum', __('Curriculum', 'nirog-academy'), [self::class, 'curriculum_box'], PostTypes::PROGRAMME, 'normal', 'high');
    add_meta_box('nba_programme_safety', __('Health and safety acknowledgement', 'nirog-academy'), [self::class, 'safety_box'], PostTypes::PROGRAMME, 'normal', 'default');
    add_meta_box('nba_lesson_settings', __('Lesson settings', 'nirog-academy'), [self::class, 'lesson_box'], PostTypes::LESSON, 'normal', 'high');
    add_meta_box('nba_lesson_resources', __('Downloads for this lesson', 'nirog-academy'), [self::class, 'resources_box'], PostTypes::LESSON, 'side', 'default');
    add_meta_box('nba_media_settings', __('Source', 'nirog-academy'), [self::class, 'media_box'], PostTypes::MEDIA, 'normal', 'high');
    add_meta_box('nba_live_settings', __('Session details', 'nirog-academy'), [self::class, 'live_box'], PostTypes::LIVE, 'normal', 'high');
  }

  // ---------------------------------------------------------------- helpers

  private static function meta(int $post_id, string $key, mixed $default = ''): mixed {
    $value = get_post_meta($post_id, $key, true);
    return $value === '' ? $default : $value;
  }

  private static function row(string $label, string $control, string $help = ''): void {
    echo '<div class="nba-field"><label>' . esc_html($label) . '</label><div>' . $control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controls are escaped when built.
    if ($help) {
      echo '<p class="description">' . esc_html($help) . '</p>';
    }
    echo '</div></div>';
  }

  private static function select(string $name, array $options, string|int $current): string {
    $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
    foreach ($options as $value => $label) {
      $html .= '<option value="' . esc_attr((string) $value) . '"' . selected((string) $current, (string) $value, false) . '>' . esc_html($label) . '</option>';
    }
    return $html . '</select>';
  }

  private static function text(string $name, string $value, string $type = 'text', string $extra = ''): string {
    return '<input type="' . esc_attr($type) . '" class="regular-text" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . $extra . '>';
  }

  private static function textarea(string $name, string $value, int $rows = 3, string $extra = ''): string {
    return '<textarea class="large-text" rows="' . (int) $rows . '" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" ' . $extra . '>' . esc_textarea($value) . '</textarea>';
  }

  private static function checkbox(string $name, bool $checked, string $label): string {
    return '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($checked, true, false) . '> ' . esc_html($label) . '</label>';
  }

  /** @return array<int, string> */
  public static function programme_options(bool $with_empty = true): array {
    $options = $with_empty ? [0 => __('— Choose a programme —', 'nirog-academy')] : [];
    foreach (get_posts(['post_type' => PostTypes::PROGRAMME, 'post_status' => ['publish', 'draft', 'private'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $p) {
      $options[$p->ID] = $p->post_title . ($p->post_status !== 'publish' ? ' (' . $p->post_status . ')' : '');
    }
    return $options;
  }

  /** @return array<int, string> */
  private static function media_options(): array {
    $options = [0 => __('— None —', 'nirog-academy')];
    foreach (get_posts(['post_type' => PostTypes::MEDIA, 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $m) {
      $options[$m->ID] = $m->post_title;
    }
    return $options;
  }

  // ---------------------------------------------------------------- programme

  public static function programme_box(\WP_Post $post): void {
    wp_nonce_field('nba_save_' . $post->ID, self::NONCE);
    $id = $post->ID;
    echo '<div class="nba-fields">';
    self::row(__('Short description', 'nirog-academy'), self::textarea('nba_summary', (string) self::meta($id, '_nba_summary'), 2), __('One or two sentences shown on the participant dashboard.', 'nirog-academy'));
    self::row(__('Title in Hindi', 'nirog-academy'), self::text('nba_title_hi', (string) self::meta($id, '_nba_title_hi')), __('Optional. Shown when the participant chooses Hindi.', 'nirog-academy'));
    self::row(__('Short description in Hindi', 'nirog-academy'), self::textarea('nba_summary_hi', (string) self::meta($id, '_nba_summary_hi'), 2));
    self::row(
      __('Access length (days)', 'nirog-academy'),
      self::text('nba_access_days', (string) self::meta($id, '_nba_access_days', (string) nba()->settings->int('default_access_days')), 'number', 'min="1" max="3650" style="width:100px"'),
      __('How long a participant keeps access from their start date. 365 = one year.', 'nirog-academy')
    );
    self::row(__('Lesson release', 'nirog-academy'), self::select('nba_drip_mode', [
      'none' => __('All lessons available immediately', 'nirog-academy'),
      'enrollment' => __('Self-paced: lessons open day by day from each person\'s start date', 'nirog-academy'),
      'cohort' => __('Batch: lessons open day by day from the batch start date', 'nirog-academy'),
    ], (string) self::meta($id, '_nba_drip_mode', 'enrollment')), __('Each lesson has an "Opens on day" number. Day 0 opens immediately.', 'nirog-academy'));
    self::row(__('Batch assignment', 'nirog-academy'), self::select('nba_cohort_assignment', [
      'auto' => __('Automatic: put new buyers in the next open batch', 'nirog-academy'),
      'manual' => __('Manual: staff choose the batch', 'nirog-academy'),
    ], (string) self::meta($id, '_nba_cohort_assignment', 'auto')), __('Only used when lesson release is set to Batch. Batches are managed under Academy → Batches.', 'nirog-academy'));
    self::row(__('Order', 'nirog-academy'), self::checkbox('nba_sequential', (bool) self::meta($id, '_nba_sequential', 0), __('Participants must complete each lesson before the next one opens', 'nirog-academy')));
    self::row(__('Colour', 'nirog-academy'), self::select('nba_element', [
      'earth' => __('Earth (green)', 'nirog-academy'),
      'water' => __('Water (aqua)', 'nirog-academy'),
      'fire' => __('Fire (gold)', 'nirog-academy'),
      'air' => __('Air (blue)', 'nirog-academy'),
      'space' => __('Space (violet)', 'nirog-academy'),
    ], (string) self::meta($id, '_nba_element', 'earth')));

    $products = (array) get_post_meta($id, '_nba_product_ids', true);
    $links = [];
    foreach (array_filter(array_map('intval', $products)) as $pid) {
      $links[] = '<a href="' . esc_url((string) get_edit_post_link($pid)) . '">' . esc_html(get_the_title($pid)) . '</a>';
    }
    self::row(
      __('Sold through', 'nirog-academy'),
      $links ? implode(', ', $links) : '<em>' . esc_html__('No WooCommerce product linked yet.', 'nirog-academy') . '</em>',
      __('To sell this programme, open the WooCommerce product and choose this programme on its "Academy" tab.', 'nirog-academy')
    );
    echo '</div>';
  }

  public static function safety_box(\WP_Post $post): void {
    $id = $post->ID;
    echo '<p class="description" style="margin-top:0">' . esc_html__('Participants must accept this once before their first lesson. Write it with your clinical team. If you change the English text, everyone is asked to accept the new version.', 'nirog-academy') . '</p>';
    echo '<div class="nba-fields">';
    self::row(__('Acknowledgement (English)', 'nirog-academy'), self::textarea('nba_ack_text', (string) self::meta($id, '_nba_ack_text'), 8));
    self::row(__('Acknowledgement (Hindi)', 'nirog-academy'), self::textarea('nba_ack_text_hi', (string) self::meta($id, '_nba_ack_text_hi'), 8));
    $version = (string) self::meta($id, '_nba_ack_version', '');
    if ($version) {
      self::row(__('Current version', 'nirog-academy'), '<code>' . esc_html($version) . '</code>');
    }
    echo '</div>';
  }

  public static function curriculum_box(\WP_Post $post): void {
    if ($post->post_status === 'auto-draft') {
      echo '<p>' . esc_html__('Save the programme first, then add modules and lessons here.', 'nirog-academy') . '</p>';
      return;
    }
    $modules = get_posts([
      'post_type' => PostTypes::MODULE, 'post_status' => ['publish', 'draft'], 'numberposts' => -1,
      'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'], 'meta_key' => '_nba_programme_id', 'meta_value' => $post->ID,
    ]);
    $lessons = get_posts([
      'post_type' => PostTypes::LESSON, 'post_status' => ['publish', 'draft', 'pending', 'private'], 'numberposts' => -1,
      'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'], 'meta_key' => '_nba_programme_id', 'meta_value' => $post->ID,
    ]);
    $by_module = [];
    $bonus = [];
    foreach ($lessons as $lesson) {
      if (get_post_meta($lesson->ID, '_nba_placement', true) === 'bonus') {
        $bonus[] = $lesson;
      } else {
        $by_module[(int) get_post_meta($lesson->ID, '_nba_module_id', true)][] = $lesson;
      }
    }

    $render_lesson = static function (\WP_Post $lesson): void {
      $type = (string) get_post_meta($lesson->ID, '_nba_type', true) ?: 'video';
      $day = (int) get_post_meta($lesson->ID, '_nba_release_day', true);
      echo '<li class="nba-cb-lesson" data-id="' . (int) $lesson->ID . '">';
      echo '<span class="nba-cb-handle" aria-hidden="true">⋮⋮</span>';
      echo '<a href="' . esc_url((string) get_edit_post_link($lesson->ID)) . '">' . esc_html($lesson->post_title ?: __('(untitled)', 'nirog-academy')) . '</a>';
      echo ' <span class="nba-cb-meta">' . esc_html(ucfirst($type));
      /* translators: %d: day number */
      echo ' · ' . esc_html(sprintf(__('day %d', 'nirog-academy'), $day));
      if ($lesson->post_status !== 'publish') {
        echo ' · <strong>' . esc_html($lesson->post_status) . '</strong>';
      }
      echo '</span></li>';
    };

    echo '<div class="nba-cb" data-programme="' . (int) $post->ID . '">';
    echo '<p class="description">' . esc_html__('Drag modules and lessons to reorder them, then press Save order. Lessons can be dragged between modules.', 'nirog-academy') . '</p>';
    echo '<ol class="nba-cb-modules">';
    foreach ($modules as $module) {
      echo '<li class="nba-cb-module" data-id="' . (int) $module->ID . '">';
      echo '<div class="nba-cb-module-head"><span class="nba-cb-handle" aria-hidden="true">⋮⋮</span><strong class="nba-cb-module-title">' . esc_html($module->post_title) . '</strong>';
      echo ' <button type="button" class="button-link nba-cb-rename">' . esc_html__('Rename', 'nirog-academy') . '</button>';
      echo ' <a class="button button-small" href="' . esc_url(admin_url('post-new.php?post_type=' . PostTypes::LESSON . '&nba_programme=' . $post->ID . '&nba_module=' . $module->ID)) . '">' . esc_html__('+ Lesson', 'nirog-academy') . '</a>';
      echo ' <button type="button" class="button-link nba-cb-delete-module" style="color:#b32d2e">' . esc_html__('Delete module', 'nirog-academy') . '</button></div>';
      echo '<ol class="nba-cb-lessons">';
      foreach ($by_module[$module->ID] ?? [] as $lesson) {
        $render_lesson($lesson);
      }
      echo '</ol></li>';
      unset($by_module[$module->ID]);
    }
    echo '</ol>';

    $orphans = array_merge(...array_values($by_module ?: [[]]));
    if ($orphans) {
      echo '<h4>' . esc_html__('Lessons not in a module', 'nirog-academy') . '</h4><ol class="nba-cb-lessons nba-cb-orphans">';
      foreach ($orphans as $lesson) {
        $render_lesson($lesson);
      }
      echo '</ol>';
    }

    echo '<p class="nba-cb-actions">';
    echo '<button type="button" class="button nba-cb-add-module">' . esc_html__('+ Add module', 'nirog-academy') . '</button> ';
    echo '<button type="button" class="button button-primary nba-cb-save">' . esc_html__('Save order', 'nirog-academy') . '</button> ';
    echo '<span class="nba-cb-status" role="status"></span></p>';

    echo '<h4>' . esc_html__('Bonus library', 'nirog-academy') . '</h4>';
    if ($bonus) {
      echo '<ul class="nba-cb-bonus">';
      foreach ($bonus as $lesson) {
        echo '<li><a href="' . esc_url((string) get_edit_post_link($lesson->ID)) . '">' . esc_html($lesson->post_title) . '</a> <span class="nba-cb-meta">' . esc_html((string) get_post_meta($lesson->ID, '_nba_category', true)) . '</span></li>';
      }
      echo '</ul>';
    }
    echo '<p><a class="button" href="' . esc_url(admin_url('post-new.php?post_type=' . PostTypes::LESSON . '&nba_programme=' . $post->ID . '&nba_placement=bonus')) . '">' . esc_html__('+ Bonus item', 'nirog-academy') . '</a></p>';
    echo '</div>';
  }

  // ---------------------------------------------------------------- lesson

  public static function lesson_box(\WP_Post $post): void {
    wp_nonce_field('nba_save_' . $post->ID, self::NONCE);
    $id = $post->ID;
    // Prefill from "+ Lesson" links in the curriculum builder.
    $programme_id = (int) self::meta($id, '_nba_programme_id', isset($_GET['nba_programme']) ? absint($_GET['nba_programme']) : 0); // phpcs:ignore WordPress.Security.NonceVerification
    $module_id = (int) self::meta($id, '_nba_module_id', isset($_GET['nba_module']) ? absint($_GET['nba_module']) : 0); // phpcs:ignore WordPress.Security.NonceVerification
    $placement = (string) self::meta($id, '_nba_placement', isset($_GET['nba_placement']) && $_GET['nba_placement'] === 'bonus' ? 'bonus' : 'curriculum'); // phpcs:ignore WordPress.Security.NonceVerification

    $module_options = [0 => __('— Choose a module —', 'nirog-academy')];
    $modules = get_posts(['post_type' => PostTypes::MODULE, 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'orderby' => ['menu_order' => 'ASC']]);
    $module_programme = [];
    foreach ($modules as $m) {
      $pid = (int) get_post_meta($m->ID, '_nba_programme_id', true);
      $module_options[$m->ID] = $m->post_title;
      $module_programme[$m->ID] = $pid;
    }

    echo '<div class="nba-fields">';
    self::row(__('Programme', 'nirog-academy'), self::select('nba_programme_id', self::programme_options(), $programme_id));
    self::row(__('Where it appears', 'nirog-academy'), self::select('nba_placement', [
      'curriculum' => __('In the programme curriculum', 'nirog-academy'),
      'bonus' => __('In the Bonus library', 'nirog-academy'),
    ], $placement));

    // Module select carries data-programme on each option so JS can filter it.
    $html = '<select name="nba_module_id" id="nba_module_id">';
    foreach ($module_options as $value => $label) {
      $html .= '<option value="' . (int) $value . '" data-programme="' . (int) ($module_programme[$value] ?? 0) . '"' . selected($module_id, $value, false) . '>' . esc_html($label) . '</option>';
    }
    $html .= '</select>';
    self::row(__('Module', 'nirog-academy'), $html);
    self::row(__('Bonus category', 'nirog-academy'), self::text('nba_category', (string) self::meta($id, '_nba_category')), __('Bonus items only, for example Recipes, Pranayama, Q&A.', 'nirog-academy'));

    self::row(__('Lesson type', 'nirog-academy'), self::select('nba_type', [
      'video' => __('Video', 'nirog-academy'),
      'audio' => __('Audio', 'nirog-academy'),
      'reading' => __('Reading (uses the main text above)', 'nirog-academy'),
      'booklet' => __('Booklet (PDF)', 'nirog-academy'),
    ], (string) self::meta($id, '_nba_type', 'video')));
    $media = self::media_options();
    self::row(__('Video or audio', 'nirog-academy'), self::select('nba_media_id', $media, (int) self::meta($id, '_nba_media_id', 0)), __('Choose from Academy → Video Library.', 'nirog-academy'));
    self::row(__('Hindi version (optional)', 'nirog-academy'), self::select('nba_media_id_hi', $media, (int) self::meta($id, '_nba_media_id_hi', 0)), __('If set, participants who choose Hindi see this one instead.', 'nirog-academy'));
    self::row(__('Length (minutes)', 'nirog-academy'), self::text('nba_duration_min', (string) self::meta($id, '_nba_duration_min'), 'number', 'min="0" style="width:100px"'));
    self::row(
      __('Opens on day', 'nirog-academy'),
      self::text('nba_release_day', (string) self::meta($id, '_nba_release_day', '0'), 'number', 'min="0" max="3650" style="width:100px"'),
      __('0 = available from the start. 7 = opens one week after the participant\'s start date (or the batch start date). Ignored if the programme releases everything at once.', 'nirog-academy')
    );
    self::row(__('Counts towards progress', 'nirog-academy'), self::checkbox('nba_required', self::meta($id, '_nba_required', '1') !== '0', __('Required lesson', 'nirog-academy')));
    self::row(__('Free preview', 'nirog-academy'), self::checkbox('nba_preview', (bool) self::meta($id, '_nba_preview', 0), __('Any logged-in visitor can watch this lesson without buying', 'nirog-academy')));
    self::row(__('Summary', 'nirog-academy'), self::textarea('nba_summary', (string) self::meta($id, '_nba_summary'), 2));
    self::row(__('Safety note', 'nirog-academy'), self::textarea('nba_safety_note', (string) self::meta($id, '_nba_safety_note'), 2), __('Shown above the lesson, for example who should skip a posture. Must be approved by your clinical team.', 'nirog-academy'));
    self::row(__('Title in Hindi', 'nirog-academy'), self::text('nba_title_hi', (string) self::meta($id, '_nba_title_hi')));
    self::row(__('Summary in Hindi', 'nirog-academy'), self::textarea('nba_summary_hi', (string) self::meta($id, '_nba_summary_hi'), 2));
    self::row(__('Safety note in Hindi', 'nirog-academy'), self::textarea('nba_safety_note_hi', (string) self::meta($id, '_nba_safety_note_hi'), 2));
    echo '</div>';
  }

  public static function resources_box(\WP_Post $post): void {
    $ids = array_filter(array_map('intval', (array) get_post_meta($post->ID, '_nba_resources', true)));
    echo '<div class="nba-resources">';
    echo '<ul class="nba-resources-list">';
    foreach ($ids as $aid) {
      $file = get_attached_file($aid);
      $protected = $file && str_starts_with(wp_normalize_path($file), wp_normalize_path(Storage::dir('files')));
      echo '<li data-id="' . (int) $aid . '">' . esc_html(get_the_title($aid) ?: wp_basename((string) $file));
      if (!$protected) {
        echo ' <em title="' . esc_attr__('This file was uploaded outside the lesson, so it is stored in the public uploads folder. Re-upload it from here to protect it.', 'nirog-academy') . '">(' . esc_html__('public file', 'nirog-academy') . ')</em>';
      }
      echo ' <button type="button" class="button-link nba-resource-remove" aria-label="' . esc_attr__('Remove', 'nirog-academy') . '">×</button></li>';
    }
    echo '</ul>';
    echo '<input type="hidden" name="nba_resources" class="nba-resources-input" value="' . esc_attr(implode(',', $ids)) . '">';
    echo '<button type="button" class="button nba-resources-add">' . esc_html__('Add files', 'nirog-academy') . '</button>';
    echo '<p class="description">' . esc_html__('PDFs and audio uploaded here are stored in a private folder and can only be downloaded by enrolled participants.', 'nirog-academy') . '</p>';
    echo '</div>';
  }

  // ---------------------------------------------------------------- media

  public static function media_box(\WP_Post $post): void {
    wp_nonce_field('nba_save_' . $post->ID, self::NONCE);
    $id = $post->ID;
    $provider = (string) self::meta($id, '_nba_provider', 'hls');
    echo '<div class="nba-fields">';
    self::row(__('Source', 'nirog-academy'), self::select('nba_provider', [
      'hls' => __('Protected video on this website (encrypted HLS)', 'nirog-academy'),
      'file' => __('Protected audio or MP4 file on this website', 'nirog-academy'),
      'youtube' => __('YouTube (unlisted) — previews only, not protected', 'nirog-academy'),
      'external' => __('External video service (HLS or MP4 link)', 'nirog-academy'),
    ], $provider));
    self::row(__('HLS folder', 'nirog-academy'), self::text('nba_path', (string) self::meta($id, '_nba_path')), __('Folder name inside wp-content/uploads/nirog-academy/media/. Filled in automatically by Academy → Import Videos.', 'nirog-academy'));

    $files = self::list_private_files();
    $file_options = ['' => __('— Choose a file —', 'nirog-academy')];
    foreach ($files as $f) {
      $file_options[$f] = $f;
    }
    self::row(__('Private file', 'nirog-academy'), self::select('nba_file', $file_options, (string) self::meta($id, '_nba_file')), __('Files in wp-content/uploads/nirog-academy/files/ (upload by SFTP or Hostinger File Manager).', 'nirog-academy'));
    self::row(__('YouTube link or ID', 'nirog-academy'), self::text('nba_youtube', (string) self::meta($id, '_nba_youtube_id')));
    self::row(__('External link', 'nirog-academy'), self::text('nba_external_url', (string) self::meta($id, '_nba_external_url'), 'url'), __('An HLS (.m3u8) or MP4 link from a video service. Protection depends on that service.', 'nirog-academy'));
    self::row(__('Length (seconds)', 'nirog-academy'), self::text('nba_duration_sec', (string) self::meta($id, '_nba_duration_sec'), 'number', 'min="0" style="width:120px"'));

    if ($provider === 'hls') {
      $status = nba()->stream->hls_status($id);
      $colour = $status['ok'] ? '#2e7d32' : '#b32d2e';
      self::row(__('Status', 'nirog-academy'), '<strong style="color:' . esc_attr($colour) . '">' . esc_html($status['message']) . '</strong>');
    }
    echo '</div>';
  }

  /** @return string[] */
  private static function list_private_files(): array {
    $dir = Storage::dir('files');
    if (!is_dir($dir)) {
      return [];
    }
    $out = [];
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
      /** @var \SplFileInfo $file */
      $name = $file->getFilename();
      if ($file->isFile() && !in_array($name, ['.htaccess', 'index.php'], true)) {
        $out[] = ltrim(str_replace(wp_normalize_path($dir), '', wp_normalize_path($file->getPathname())), '/');
      }
      if (count($out) > 500) {
        break;
      }
    }
    sort($out);
    return $out;
  }

  // ---------------------------------------------------------------- live

  public static function live_box(\WP_Post $post): void {
    wp_nonce_field('nba_save_' . $post->ID, self::NONCE);
    $id = $post->ID;
    $programme_id = (int) self::meta($id, '_nba_programme_id', 0);
    $cohort_options = [0 => __('Everyone in the programme', 'nirog-academy')];
    foreach (nba()->cohorts->all() as $c) {
      $cohort_options[$c->id] = get_the_title((int) $c->programme_id) . ' — ' . $c->name;
    }
    echo '<div class="nba-fields">';
    self::row(__('Programme', 'nirog-academy'), self::select('nba_programme_id', self::programme_options(), $programme_id));
    self::row(__('Batch', 'nirog-academy'), self::select('nba_cohort_id', $cohort_options, (int) self::meta($id, '_nba_cohort_id', 0)));
    self::row(__('Starts at', 'nirog-academy'), self::text('nba_starts_at', Time::utc_to_local_input((string) self::meta($id, '_nba_starts_at')), 'datetime-local'), sprintf(
      /* translators: %s: timezone name */
      __('Time zone: %s', 'nirog-academy'),
      wp_timezone_string()
    ));
    self::row(__('Length (minutes)', 'nirog-academy'), self::text('nba_duration_min', (string) self::meta($id, '_nba_duration_min', '60'), 'number', 'min="5" style="width:100px"'));
    self::row(__('Join link', 'nirog-academy'), self::text('nba_join_url', (string) self::meta($id, '_nba_join_url'), 'url'), __('Zoom, Google Meet or similar. Shown to participants 15 minutes before the start.', 'nirog-academy'));
    $lesson_options = [0 => __('— None yet —', 'nirog-academy')];
    if ($programme_id) {
      foreach (get_posts(['post_type' => PostTypes::LESSON, 'numberposts' => -1, 'meta_key' => '_nba_programme_id', 'meta_value' => $programme_id]) as $l) {
        $lesson_options[$l->ID] = $l->post_title;
      }
    }
    self::row(__('Recording', 'nirog-academy'), self::select('nba_recording_lesson_id', $lesson_options, (int) self::meta($id, '_nba_recording_lesson_id', 0)), __('After the session, link the lesson that holds the recording.', 'nirog-academy'));
    echo '</div>';
  }

  // ---------------------------------------------------------------- save

  public static function save(int $post_id, \WP_Post $post): void {
    if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), 'nba_save_' . $post_id)) {
      return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
      return;
    }
    $p = wp_unslash($_POST);
    $text = static fn(string $k): string => sanitize_text_field((string) ($p[$k] ?? ''));
    $area = static fn(string $k): string => sanitize_textarea_field((string) ($p[$k] ?? ''));
    $int = static fn(string $k): int => (int) ($p[$k] ?? 0);
    $flag = static fn(string $k): string => empty($p[$k]) ? '0' : '1';

    switch ($post->post_type) {
      case PostTypes::PROGRAMME:
        update_post_meta($post_id, '_nba_summary', $area('nba_summary'));
        update_post_meta($post_id, '_nba_summary_hi', $area('nba_summary_hi'));
        update_post_meta($post_id, '_nba_title_hi', $text('nba_title_hi'));
        update_post_meta($post_id, '_nba_access_days', max(1, min(3650, $int('nba_access_days'))));
        update_post_meta($post_id, '_nba_drip_mode', in_array($p['nba_drip_mode'] ?? '', ['none', 'enrollment', 'cohort'], true) ? $p['nba_drip_mode'] : 'enrollment');
        update_post_meta($post_id, '_nba_cohort_assignment', ($p['nba_cohort_assignment'] ?? '') === 'manual' ? 'manual' : 'auto');
        update_post_meta($post_id, '_nba_sequential', $flag('nba_sequential'));
        update_post_meta($post_id, '_nba_element', in_array($p['nba_element'] ?? '', ['earth', 'water', 'fire', 'air', 'space'], true) ? $p['nba_element'] : 'earth');
        if (array_key_exists('nba_ack_text', $p)) {
          $ack = wp_kses_post((string) $p['nba_ack_text']);
          if ($ack !== (string) get_post_meta($post_id, '_nba_ack_text', true)) {
            update_post_meta($post_id, '_nba_ack_version', $ack === '' ? '' : gmdate('Ymd-His'));
          }
          update_post_meta($post_id, '_nba_ack_text', $ack);
          update_post_meta($post_id, '_nba_ack_text_hi', wp_kses_post((string) ($p['nba_ack_text_hi'] ?? '')));
        }
        break;

      case PostTypes::LESSON:
        update_post_meta($post_id, '_nba_programme_id', $int('nba_programme_id'));
        update_post_meta($post_id, '_nba_placement', ($p['nba_placement'] ?? '') === 'bonus' ? 'bonus' : 'curriculum');
        update_post_meta($post_id, '_nba_module_id', $int('nba_module_id'));
        update_post_meta($post_id, '_nba_category', $text('nba_category'));
        update_post_meta($post_id, '_nba_type', in_array($p['nba_type'] ?? '', ['video', 'audio', 'reading', 'booklet'], true) ? $p['nba_type'] : 'video');
        update_post_meta($post_id, '_nba_media_id', $int('nba_media_id'));
        update_post_meta($post_id, '_nba_media_id_hi', $int('nba_media_id_hi'));
        update_post_meta($post_id, '_nba_duration_min', max(0, $int('nba_duration_min')));
        update_post_meta($post_id, '_nba_release_day', max(0, min(3650, $int('nba_release_day'))));
        update_post_meta($post_id, '_nba_required', $flag('nba_required'));
        update_post_meta($post_id, '_nba_preview', $flag('nba_preview'));
        update_post_meta($post_id, '_nba_summary', $area('nba_summary'));
        update_post_meta($post_id, '_nba_safety_note', $area('nba_safety_note'));
        update_post_meta($post_id, '_nba_title_hi', $text('nba_title_hi'));
        update_post_meta($post_id, '_nba_summary_hi', $area('nba_summary_hi'));
        update_post_meta($post_id, '_nba_safety_note_hi', $area('nba_safety_note_hi'));
        if (isset($p['nba_resources'])) {
          $ids = array_values(array_filter(array_map('absint', explode(',', (string) $p['nba_resources']))));
          update_post_meta($post_id, '_nba_resources', $ids);
        }
        break;

      case PostTypes::MEDIA:
        $provider = in_array($p['nba_provider'] ?? '', ['hls', 'file', 'youtube', 'external'], true) ? $p['nba_provider'] : 'hls';
        update_post_meta($post_id, '_nba_provider', $provider);
        update_post_meta($post_id, '_nba_path', trim(str_replace(['..', '\\'], '', $text('nba_path')), '/'));
        update_post_meta($post_id, '_nba_file', trim(str_replace(['..', '\\'], '', $text('nba_file')), '/'));
        update_post_meta($post_id, '_nba_youtube_id', self::youtube_id($text('nba_youtube')));
        update_post_meta($post_id, '_nba_external_url', esc_url_raw((string) ($p['nba_external_url'] ?? '')));
        update_post_meta($post_id, '_nba_duration_sec', max(0, $int('nba_duration_sec')));
        break;

      case PostTypes::LIVE:
        update_post_meta($post_id, '_nba_programme_id', $int('nba_programme_id'));
        update_post_meta($post_id, '_nba_cohort_id', $int('nba_cohort_id'));
        update_post_meta($post_id, '_nba_starts_at', (string) Time::local_to_utc($text('nba_starts_at')));
        update_post_meta($post_id, '_nba_duration_min', max(5, $int('nba_duration_min')));
        update_post_meta($post_id, '_nba_join_url', esc_url_raw((string) ($p['nba_join_url'] ?? '')));
        update_post_meta($post_id, '_nba_recording_lesson_id', $int('nba_recording_lesson_id'));
        delete_post_meta($post_id, '_nba_reminded_24h');
        delete_post_meta($post_id, '_nba_reminded_15m');
        break;
    }
  }

  public static function youtube_id(string $input): string {
    $input = trim($input);
    if (preg_match('~(?:youtu\.be/|v=|embed/|shorts/|live/)([A-Za-z0-9_-]{11})~', $input, $m)) {
      return $m[1];
    }
    return preg_match('~^[A-Za-z0-9_-]{11}$~', $input) ? $input : '';
  }

  /**
   * Files uploaded while editing a lesson (except images) are stored in the
   * private files folder. They can then only be downloaded through the access
   * check.
   */
  public static function protect_lesson_uploads(array $uploads): array {
    // phpcs:disable WordPress.Security.NonceVerification -- read-only context check; WordPress verifies the upload nonce.
    $post_id = isset($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0;
    if (!$post_id || get_post_type($post_id) !== PostTypes::LESSON) {
      return $uploads;
    }
    $name = isset($_FILES['async-upload']['name']) ? (string) $_FILES['async-upload']['name'] : '';
    // phpcs:enable
    $type = wp_check_filetype($name);
    if ($name === '' || str_starts_with((string) $type['type'], 'image/')) {
      return $uploads;
    }
    Storage::ensure_directories();
    $uploads['path'] = Storage::dir('files') . '/lessons';
    $uploads['url'] = Storage::base_url() . '/files/lessons';
    $uploads['subdir'] = '/nirog-academy/files/lessons';
    wp_mkdir_p($uploads['path']);
    return $uploads;
  }
}
