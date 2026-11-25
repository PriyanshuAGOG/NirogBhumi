<?php
namespace NirogBhumi\Academy\Admin;

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Live\LiveSessions;
use NirogBhumi\Academy\Media\Importer;
use NirogBhumi\Academy\Support\Storage;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * The demo programme: a complete, published programme built from the files in
 * the plugin's demo/ folder (two short encrypted videos/audio, three booklets
 * and a worksheet), with four weeks, bonus content, live sessions of every
 * kind and a demo participant whose access started nine days ago — so weeks 1
 * and 2 are open and weeks 3 and 4 show as "opens on …".
 *
 * Everything it creates is recorded in one option and removed again by
 * remove(). It never creates WooCommerce products or touches the theme.
 */
final class Demo {
  public const OPTION = 'nba_demo';
  public const USER_META = 'nba_demo_user';
  private const DAYS_IN = 9;

  /** @return array<string,mixed> */
  public static function state(): array {
    $state = get_option(self::OPTION, []);
    return is_array($state) ? $state : [];
  }

  public static function exists(): bool {
    $state = self::state();
    return !empty($state['programme']) && get_post((int) $state['programme']);
  }

  /** Where the demo participant logs in (works while the Academy is Closed). */
  public static function login_url(): string {
    $academy = \NirogBhumi\Academy\Frontend\Router::url();
    return nba()->settings->get('launch_mode') === 'closed' ? wp_login_url($academy) : \NirogBhumi\Academy\Frontend\Router::url('login');
  }

  public static function is_demo_user(int $user_id): bool {
    return $user_id > 0 && (bool) get_user_meta($user_id, self::USER_META, true);
  }

  public static function create(): int|\WP_Error {
    if (self::exists()) {
      return (int) self::state()['programme'];
    }
    $source = NBA_DIR . 'demo';
    if (!is_dir($source)) {
      return new \WP_Error('nba_demo', __('The demo files are missing from the plugin folder. Upload the full plugin ZIP again.', 'nirog-academy'));
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    Storage::ensure_directories();

    $t = [
      'posts' => [], 'attachments' => [], 'users' => [], 'enrollments' => [], 'media_dirs' => [], 'files' => [],
    ];
    $admin_id = get_current_user_id();

    // ---------------------------------------------------------- media
    $media = [];
    foreach (['video' => 'demo-lesson-video', 'audio' => 'demo-practice-audio'] as $kind => $folder) {
      $target = Storage::dir('media') . '/' . $folder;
      if (!is_dir($target)) {
        self::copy_dir($source . '/media/' . $folder, $target);
        $t['media_dirs'][] = $folder;
      }
    }
    Importer::run();
    foreach (['video' => 'demo-lesson-video', 'audio' => 'demo-practice-audio'] as $kind => $folder) {
      $ids = get_posts(['post_type' => PostTypes::MEDIA, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_nba_path', 'meta_value' => $folder]);
      $media[$kind] = (int) ($ids[0] ?? 0);
      if ($media[$kind]) {
        wp_update_post(['ID' => $media[$kind], 'post_title' => $kind === 'video' ? 'Demo video (16 seconds)' : 'Demo practice audio (20 seconds)']);
        $t['posts'][] = $media[$kind];
      }
    }

    // ---------------------------------------------------------- programme
    $programme_id = wp_insert_post([
      'post_type' => PostTypes::PROGRAMME,
      'post_status' => 'publish',
      'post_title' => 'Yoga for Diabetes — demo programme',
      'post_name' => 'demo-yoga-for-diabetes',
      'post_content' => "<p>This is a demo programme. It shows how a real programme looks to participants: four weeks of recorded lessons, audio practices, weekly booklets, readings, bonus content and live sessions.</p><p>Replace it with your own programme, or remove it from Course Studio → Demo programme.</p>",
    ], true);
    if (is_wp_error($programme_id)) {
      return $programme_id;
    }
    $t['posts'][] = $programme_id;
    $t['programme'] = $programme_id;
    update_option(self::OPTION, $t, false); // Save early so a failure half-way can still be removed.

    foreach ([
      '_nba_summary' => 'A gentle daily yoga and lifestyle routine, released week by week. (Demo content.)',
      '_nba_summary_hi' => 'रोज़ का आसान योग और दिनचर्या, हर हफ़्ते नए lesson के साथ। (डेमो)',
      '_nba_title_hi' => 'डायबिटीज़ के लिए योग — डेमो programme',
      '_nba_access_days' => 365,
      '_nba_drip_mode' => 'enrollment',
      '_nba_cohort_assignment' => 'auto',
      '_nba_sequential' => '0',
      '_nba_element' => 'earth',
      '_nba_ack_text' => "<p><strong>Demo text — your clinical team writes the real one.</strong></p><p>Before you begin: keep taking your medicines as your doctor advised, and do not change them on your own. Check your blood sugar as you have been told. If you feel dizzy, shaky, sweaty or unwell during a practice, stop, rest and follow your doctor's advice.</p>",
      '_nba_ack_text_hi' => "<p><strong>डेमो text — असली text आपकी टीम लिखेगी।</strong></p><p>शुरू करने से पहले: अपनी दवाइयाँ डॉक्टर की सलाह के अनुसार लेते रहें, खुद से न बदलें। अगर practice के दौरान चक्कर, कंपकंपी, पसीना या कमज़ोरी लगे, तो रुकें, आराम करें और डॉक्टर की सलाह मानें।</p>",
    ] as $key => $value) {
      update_post_meta($programme_id, $key, $value);
    }
    update_post_meta($programme_id, '_nba_ack_version', gmdate('Ymd-His'));

    $safety = 'Demo note: if you have knee, back or eye problems, or feel unwell, do the gentler version or rest. Ask your doctor if unsure.';
    $safety_hi = 'डेमो: घुटने, कमर या आँख की तकलीफ़ हो या तबीयत ठीक न लगे, तो आसान तरीका करें या आराम करें। शक हो तो डॉक्टर से पूछें।';

    $weeks = [
      ['Getting started', 'शुरुआत', [
        ['reading', 'Welcome — how this programme works', 'स्वागत — यह programme कैसे चलता है', 0, 8, self::welcome_text()],
        ['video', 'Breath awareness', 'साँस पर ध्यान', 0, 16, '', ['resource' => 'daily-practice-tracker.pdf']],
        ['booklet', 'Week 1 booklet: Getting started', 'हफ़्ता 1 booklet: शुरुआत', 0, 0, '', ['pdf' => 'week-1', 'pages' => 5, 'download' => true]],
        ['audio', 'Guided relaxation', 'गाइडेड आराम', 1, 20, ''],
      ]],
      ['Moving gently', 'आराम से चलना-फिरना', [
        ['video', 'Standing postures', 'खड़े होकर आसन', 7, 16, ''],
        ['booklet', 'Week 2 booklet: Moving gently', 'हफ़्ता 2 booklet: आराम से चलना-फिरना', 7, 0, '', ['pdf' => 'week-2', 'pages' => 4]],
        ['reading', 'A simple rhythm for your day', 'दिन की आसान दिनचर्या', 8, 6, self::rhythm_text()],
        ['audio', 'Evening wind-down', 'शाम का आराम', 9, 20, ''],
      ]],
      ['Rest and recovery', 'आराम और रिकवरी', [
        ['video', 'Twists and gentle stretches', 'हल्के ट्विस्ट और स्ट्रेच', 14, 16, ''],
        ['audio', 'Yoga Nidra', 'योग निद्रा', 15, 20, ''],
        ['reading', 'Sleep and your routine', 'नींद और आपकी दिनचर्या', 16, 5, self::sleep_text()],
      ]],
      ['Keeping it going', 'आगे भी जारी रखें', [
        ['video', 'Putting it all together', 'सब एक साथ', 21, 16, ''],
        ['reading', 'Your plan after the programme', 'programme के बाद आपका प्लान', 22, 5, self::plan_text()],
      ]],
    ];

    $week_ids = [];
    $lessons = [];
    foreach ($weeks as $w => [$week_title, $week_hi, $items]) {
      $week_id = (int) wp_insert_post(['post_type' => PostTypes::MODULE, 'post_status' => 'publish', 'post_title' => $week_title, 'menu_order' => $w]);
      update_post_meta($week_id, '_nba_programme_id', $programme_id);
      update_post_meta($week_id, '_nba_title_hi', $week_hi);
      $t['posts'][] = $week_id;
      $week_ids[$w] = $week_id;
      foreach ($items as $i => $item) {
        $id = self::lesson($programme_id, $week_id, $w * 1000 + $i, $item, $media, $t);
        if ($item[0] === 'video' || $item[0] === 'audio') {
          update_post_meta($id, '_nba_safety_note', $safety);
          update_post_meta($id, '_nba_safety_note_hi', $safety_hi);
        }
        $lessons[] = $id;
      }
      update_option(self::OPTION, $t, false);
    }

    // ---------------------------------------------------------- bonus
    $bonus = [
      ['booklet', 'Recipes for steady energy', 'स्थिर ऊर्जा के लिए रेसिपी', 0, 0, '', ['pdf' => 'recipes', 'pages' => 3, 'download' => true, 'category' => 'Recipes']],
      ['video', 'Q&A recording: your first-week questions', 'Q&A रिकॉर्डिंग: पहले हफ़्ते के सवाल', 0, 16, '', ['category' => 'Q&A recordings']],
      ['reading', 'Kitchen spices: a short guide', 'रसोई के मसाले: छोटी गाइड', 0, 4, self::spices_text(), ['category' => 'Reading']],
    ];
    $recording = 0;
    foreach ($bonus as $i => $item) {
      $id = self::lesson($programme_id, 0, 900000 + $i, $item, $media, $t);
      if ($item[0] === 'video') {
        $recording = $id;
      }
    }

    // ---------------------------------------------------------- live
    $tz_today = static function (int $days, string $time): int {
      $d = new \DateTimeImmutable('today ' . $time, wp_timezone());
      return $d->modify(($days >= 0 ? '+' : '') . $days . ' days')->getTimestamp();
    };
    $sessions = [
      [
        'title' => 'Morning practice together (demo)',
        'description' => 'A short live practice. The Join button opens 15 minutes before the start. (Demo: the link opens Zoom\'s test page.)',
        'start' => (int) (ceil((time() + 10 * MINUTE_IN_SECONDS) / 300) * 300),
        'kind' => 'meeting', 'url' => 'https://zoom.us/test', 'week' => $week_ids[1],
        'notes' => 'Demo team note: host joins 10 minutes early, co-host admits people and watches chat.',
      ],
      [
        'title' => 'Week 2 questions and answers (demo)',
        'description' => 'Bring your questions about the first two weeks.',
        'start' => $tz_today(3, '19:00'),
        'kind' => 'meeting', 'url' => 'https://zoom.us/test', 'week' => $week_ids[1],
        'notes' => 'Demo team note: collect questions from WhatsApp group beforehand.',
      ],
      [
        'title' => 'Sunday live class on YouTube (demo)',
        'description' => 'Watch inside the Academy — no app needed. (Demo: replace the link with your own YouTube Live link.)',
        'start' => (new \DateTimeImmutable('next sunday 07:00', wp_timezone()))->getTimestamp(),
        'kind' => 'stream', 'url' => 'https://www.youtube.com/watch?v=jfKfPfyJRdk', 'week' => $week_ids[1],
        'notes' => 'Demo team note: set the YouTube stream to Unlisted and allow embedding.',
      ],
      [
        'title' => 'Welcome call (demo)',
        'description' => 'Meet the team and ask your first questions.',
        'start' => $tz_today(-5, '19:00'),
        'kind' => 'meeting', 'url' => 'https://zoom.us/test', 'week' => $week_ids[0],
        'notes' => 'Demo: the recording is linked, so participants see "Recording".',
        'recording' => $recording,
      ],
    ];
    $live_ids = [];
    foreach ($sessions as $s) {
      $id = \NirogBhumi\Academy\Studio\Studio::save_live($programme_id, [
        'title' => $s['title'], 'description' => $s['description'], 'duration' => 45, 'join_url' => $s['url'],
        'week' => $s['week'], 'recording' => $s['recording'] ?? 0, 'kind' => $s['kind'], 'host' => $admin_id, 'notes' => $s['notes'],
      ]);
      if (is_wp_error($id)) {
        continue;
      }
      update_post_meta($id, '_nba_starts_at', gmdate('Y-m-d H:i:s', $s['start']));
      if ($s['start'] < time()) {
        // Reminders for a past session must not go out.
        update_post_meta($id, '_nba_reminded_24h', 1);
        update_post_meta($id, '_nba_reminded_15m', 1);
      }
      $t['posts'][] = $id;
      $live_ids[] = $id;
    }
    update_option(self::OPTION, $t, false);

    // ---------------------------------------------------------- people
    $login = 'demo-participant';
    $password = 'Demo-' . wp_generate_password(8, false);
    $user_id = username_exists($login);
    if ($user_id) {
      wp_set_password($password, (int) $user_id);
    } else {
      $email = 'demo-participant@example.com';
      if (email_exists($email)) {
        $email = 'demo-participant-' . wp_generate_password(6, false, false) . '@example.com';
      }
      $user_id = wp_insert_user([
        'user_login' => $login, 'user_pass' => $password, 'user_email' => $email,
        'first_name' => 'Asha', 'last_name' => '(demo)', 'display_name' => 'Asha (demo)', 'role' => 'nba_participant',
      ]);
      if (is_wp_error($user_id)) {
        return $user_id;
      }
    }
    $user_id = (int) $user_id;
    update_user_meta($user_id, self::USER_META, 1);
    update_user_meta($user_id, 'show_admin_bar_front', 'false');
    $t['users'][] = $user_id;
    $t['login'] = $login;
    $t['password'] = $password;

    $starts = gmdate('Y-m-d H:i:s', time() - self::DAYS_IN * DAY_IN_SECONDS - HOUR_IN_SECONDS);
    $people = array_unique(array_filter([$user_id, $admin_id]));
    foreach ($people as $person) {
      $enrollment = nba()->enrollments->create(['user_id' => $person, 'programme_id' => $programme_id, 'source' => 'demo', 'starts_at' => $starts, 'note' => 'Demo programme']);
      if (!is_wp_error($enrollment)) {
        $t['enrollments'][] = (int) $enrollment;
      }
    }

    // Some progress, so Today and the programme page look lived-in.
    global $wpdb;
    $now = Time::now();
    foreach (array_slice($lessons, 0, 2) as $lesson_id) {
      $wpdb->replace(Installer::table('progress'), [
        'user_id' => $user_id, 'lesson_id' => $lesson_id, 'programme_id' => $programme_id, 'status' => 'completed',
        'position_sec' => 0, 'duration_sec' => 16, 'watched' => null, 'watched_pct' => 100, 'completed_at' => $now, 'updated_at' => $now,
      ]);
    }
    $wpdb->replace(Installer::table('progress'), [
      'user_id' => $user_id, 'lesson_id' => $lessons[2], 'programme_id' => $programme_id, 'status' => 'in_progress',
      'position_sec' => 0, 'duration_sec' => 0, 'watched' => '11000', 'watched_pct' => 40, 'completed_at' => null, 'updated_at' => $now,
    ]);
    if (!empty($live_ids[3])) {
      LiveSessions::record_join($live_ids[3], $user_id);
    }
    foreach ($people as $person) {
      nba()->notifications->create(
        $person,
        'announcement',
        'Welcome to the demo programme',
        'This is how a message from your team looks. Try a lesson, read a booklet, open a live session — everything here is demo content you can remove later.',
        \NirogBhumi\Academy\Frontend\Router::url('notifications'),
        'demo-welcome-' . $programme_id
      );
    }

    nba()->curriculum->flush($programme_id);
    nba()->enrollments->forget();
    update_option(self::OPTION, $t, false);
    nba()->audit->log('demo.created', $admin_id, 'programme', $programme_id, ['participant' => $user_id]);
    return (int) $programme_id;
  }

  /**
   * @param array{0:string,1:string,2:string,3:int,4:int,5:string,6?:array<string,mixed>} $item
   * @param array<string,int> $media
   * @param array<string,mixed> $t
   */
  private static function lesson(int $programme_id, int $week_id, int $order, array $item, array $media, array &$t): int {
    [$type, $title, $title_hi, $day, $minutes, $body] = $item;
    $opts = $item[6] ?? [];
    $id = (int) wp_insert_post([
      'post_type' => PostTypes::LESSON,
      'post_status' => 'publish',
      'post_title' => $title,
      'post_content' => $body,
      'menu_order' => $order,
    ]);
    $t['posts'][] = $id;
    $meta = [
      '_nba_programme_id' => $programme_id,
      '_nba_module_id' => $week_id,
      '_nba_placement' => $week_id ? 'curriculum' : 'bonus',
      '_nba_type' => $type,
      '_nba_release_day' => $day,
      '_nba_required' => $week_id ? '1' : '0',
      '_nba_duration_min' => $minutes ? max(1, (int) round($minutes / ($type === 'reading' ? 1 : 60))) : 0,
      '_nba_title_hi' => $title_hi,
      '_nba_summary' => self::summary($type),
    ];
    if ($type === 'video' || $type === 'audio') {
      $meta['_nba_media_id'] = $media[$type] ?? 0;
    }
    if (!empty($opts['category'])) {
      $meta['_nba_category'] = $opts['category'];
    }
    if (!empty($opts['download'])) {
      $meta['_nba_allow_download'] = '1';
    }
    foreach ($meta as $k => $v) {
      update_post_meta($id, $k, $v);
    }
    if (!empty($opts['pdf'])) {
      self::attach_booklet($id, $opts['pdf'], (int) $opts['pages'], $t);
    }
    if (!empty($opts['resource'])) {
      self::attach_resource($id, $opts['resource'], $t);
    }
    return $id;
  }

  private static function summary(string $type): string {
    return match ($type) {
      'video' => 'Demo lesson: a 16-second sample video stands in for your recorded lesson.',
      'audio' => 'Demo practice: a 20-second sample audio stands in for your guided practice.',
      'booklet' => 'Demo booklet: read it as a flipbook, page by page.',
      default => 'Demo reading: short text you can replace with your own.',
    };
  }

  private static function attach_booklet(int $lesson_id, string $name, int $pages, array &$t): void {
    $src = NBA_DIR . 'demo/booklets/' . $name . '.pdf';
    $dir = Storage::dir('files') . '/booklets';
    wp_mkdir_p($dir);
    $relative = 'booklets/' . $lesson_id . '-demo-' . $name . '.pdf';
    if (is_file($src) && copy($src, Storage::dir('files') . '/' . $relative)) {
      update_post_meta($lesson_id, '_nba_file', $relative);
      update_post_meta($lesson_id, '_nba_file_name', 'nirog-bhumi-' . $name . '.pdf');
      update_post_meta($lesson_id, '_nba_pages', $pages);
      $t['files'][] = 'files/' . $relative;
    }
    $cover = NBA_DIR . 'demo/booklets/' . $name . '-cover.jpg';
    if (is_file($cover)) {
      $tmp = wp_tempnam('nba-cover');
      copy($cover, $tmp);
      $attachment = media_handle_sideload(['name' => 'booklet-cover-demo-' . $name . '.jpg', 'tmp_name' => $tmp], $lesson_id, 'Demo booklet cover');
      if (!is_wp_error($attachment)) {
        update_post_meta($lesson_id, '_nba_cover_id', (int) $attachment);
        $t['attachments'][] = (int) $attachment;
      } elseif (is_file($tmp)) {
        wp_delete_file($tmp);
      }
    }
  }

  private static function attach_resource(int $lesson_id, string $file, array &$t): void {
    $src = NBA_DIR . 'demo/files/' . $file;
    $dir = Storage::dir('files') . '/lessons';
    wp_mkdir_p($dir);
    $target = $dir . '/demo-' . $file;
    if (!is_file($src) || !copy($src, $target)) {
      return;
    }
    $attachment = wp_insert_attachment([
      'post_mime_type' => 'application/pdf',
      'post_title' => 'Daily practice tracker',
      'post_status' => 'inherit',
      'post_parent' => $lesson_id,
    ], $target, $lesson_id);
    if (!is_wp_error($attachment) && $attachment) {
      update_post_meta($lesson_id, '_nba_resources', [(int) $attachment]);
      $t['attachments'][] = (int) $attachment;
    }
  }

  /**
   * Remove everything the demo created. Real programmes, participants and
   * videos are never touched: only the IDs recorded at creation are removed.
   */
  public static function remove(): void {
    global $wpdb;
    $t = self::state();
    if (!$t) {
      return;
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $programme_id = (int) ($t['programme'] ?? 0);
    $posts = array_map('intval', (array) ($t['posts'] ?? []));
    $users = array_map('intval', (array) ($t['users'] ?? []));
    $lessons = array_values(array_filter($posts, static fn ($id) => get_post_type($id) === PostTypes::LESSON));
    $sessions = array_values(array_filter($posts, static fn ($id) => get_post_type($id) === PostTypes::LIVE));

    foreach (array_map('intval', (array) ($t['enrollments'] ?? [])) as $id) {
      $wpdb->delete(Installer::table('enrollments'), ['id' => $id]);
      $wpdb->delete(Installer::table('notifications'), ['dedupe_key' => 'welcome-' . $id]);
    }
    if ($programme_id) {
      $wpdb->delete(Installer::table('progress'), ['programme_id' => $programme_id]);
      $wpdb->delete(Installer::table('acknowledgements'), ['programme_id' => $programme_id]);
      $wpdb->delete(Installer::table('notifications'), ['type' => 'announcement', 'dedupe_key' => 'demo-welcome-' . $programme_id]);
    }
    foreach ($lessons as $id) {
      $wpdb->delete(Installer::table('notes'), ['lesson_id' => $id]);
    }
    foreach ($sessions as $id) {
      $wpdb->delete(Installer::table('attendance'), ['session_id' => $id]);
    }
    foreach ($users as $user_id) {
      if (!self::is_demo_user($user_id)) {
        continue; // Never delete a real account.
      }
      foreach (['progress', 'devices', 'device_requests', 'notifications', 'notes', 'acknowledgements', 'attendance', 'enrollments'] as $table) {
        $wpdb->delete(Installer::table($table), ['user_id' => $user_id]);
      }
      wp_delete_user($user_id);
    }
    foreach (array_map('intval', (array) ($t['attachments'] ?? [])) as $id) {
      wp_delete_attachment($id, true);
    }
    foreach ((array) ($t['files'] ?? []) as $relative) {
      $path = Storage::base_dir() . '/' . ltrim((string) $relative, '/');
      if (!str_contains((string) $relative, '..') && is_file($path)) {
        wp_delete_file($path);
      }
    }
    foreach ($posts as $id) {
      if (get_post_type($id) === PostTypes::MEDIA) {
        $key = (string) get_post_meta($id, '_nba_key_file', true);
        if ($key && ($key_path = Storage::resolve('keys', $key))) {
          wp_delete_file($key_path);
        }
      }
      if (get_post($id)) {
        wp_delete_post($id, true);
      }
    }
    foreach ((array) ($t['media_dirs'] ?? []) as $folder) {
      if (preg_match('~^demo-[a-z-]+$~', (string) $folder)) {
        self::delete_dir(Storage::dir('media') . '/' . $folder);
      }
    }
    if ($programme_id) {
      nba()->curriculum->flush($programme_id);
    }
    nba()->enrollments->forget();
    delete_option(self::OPTION);
    nba()->audit->log('demo.removed', get_current_user_id(), 'programme', $programme_id);
  }

  // ------------------------------------------------------------------ files

  private static function copy_dir(string $from, string $to): void {
    wp_mkdir_p($to);
    foreach (scandir($from) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..' || $entry === '.htaccess') {
        continue;
      }
      $src = $from . '/' . $entry;
      is_dir($src) ? self::copy_dir($src, $to . '/' . $entry) : copy($src, $to . '/' . $entry);
    }
  }

  private static function delete_dir(string $dir): void {
    if (!is_dir($dir) || !str_starts_with(wp_normalize_path($dir), wp_normalize_path(Storage::dir('media')) . '/')) {
      return;
    }
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) {
      /** @var \SplFileInfo $file */
      $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($dir);
  }

  // ------------------------------------------------------------------ text

  private static function welcome_text(): string {
    return <<<HTML
<p><em>Demo reading — replace with your own words.</em></p>
<p>Welcome. This programme is a gentle daily routine of yoga, breathing and simple habits, released week by week. You do not need any experience. Fifteen to thirty minutes a day is enough.</p>
<h2>How each week works</h2>
<ul>
<li><strong>Recorded lessons</strong> open day by day. Watch them whenever it suits you.</li>
<li><strong>The weekly booklet</strong> sums up the week. Read it like a book, page by page.</li>
<li><strong>Audio practices</strong> keep playing when your phone is locked.</li>
<li><strong>Live sessions</strong> are listed with their week. The Join button appears 15 minutes before the start.</li>
</ul>
<h2>A few simple rules</h2>
<ol>
<li>Keep taking your medicines as your doctor advised.</li>
<li>Practise on an empty or light stomach, on a mat or a folded blanket.</li>
<li>Go slowly. Stop if anything hurts.</li>
</ol>
<p>When you are ready, press <strong>Mark as done</strong> below and go to the next lesson.</p>
HTML;
  }

  private static function rhythm_text(): string {
    return <<<HTML
<p><em>Demo reading — replace with your own words.</em></p>
<p>A steady rhythm makes everything easier. Here is a simple example of a day; your practitioner will adjust it for you.</p>
<table>
<thead><tr><th>Time</th><th>What to do</th></tr></thead>
<tbody>
<tr><td>Morning</td><td>Glass of water, 15 minutes of practice, then breakfast.</td></tr>
<tr><td>Midday</td><td>Main meal at a regular time. A short walk afterwards.</td></tr>
<tr><td>Evening</td><td>Light dinner, early. Evening wind-down audio.</td></tr>
<tr><td>Night</td><td>Screens off half an hour before sleep.</td></tr>
</tbody>
</table>
<blockquote><p>Small things done every day matter more than big things done once.</p></blockquote>
HTML;
  }

  private static function sleep_text(): string {
    return <<<HTML
<p><em>Demo reading — replace with your own words.</em></p>
<p>Good sleep is part of the practice. Try to go to bed and wake up at about the same time every day, keep the room dark and cool, and use the Yoga Nidra audio if your mind is busy.</p>
<p>If you often wake up at night feeling unwell, tell your doctor.</p>
HTML;
  }

  private static function plan_text(): string {
    return <<<HTML
<p><em>Demo reading — replace with your own words.</em></p>
<p>You now have a full routine. Keep the lessons you liked most as your daily practice — they stay open in the Academy while your access lasts.</p>
<ul>
<li>Pick one morning and one evening practice.</li>
<li>Keep using the daily practice tracker.</li>
<li>Join the live sessions when you can.</li>
</ul>
HTML;
  }

  private static function spices_text(): string {
    return <<<HTML
<p><em>Demo reading — replace with content approved by your team.</em></p>
<p>Many Indian kitchens use spices such as turmeric, cumin, fenugreek and cinnamon in everyday cooking. In this section your team can explain how they are used in your programme's recipes.</p>
<p>Do not take any herb or supplement in large amounts, or in place of your medicines, without speaking to your doctor or practitioner.</p>
HTML;
  }
}
