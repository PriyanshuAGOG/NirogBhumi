<?php
/**
 * One lesson: recorded video, audio practice, booklet or reading.
 *
 * @var WP_Post     $lesson
 * @var string      $type
 * @var array|null  $booklet
 * @var int         $week_index
 * @var int         $programme_id
 * @var int         $module_id
 * @var array|null  $player
 * @var object|null $progress
 * @var string      $note
 * @var array       $resources
 * @var int         $prev
 * @var int         $next
 * @var bool        $next_open
 * @var string      $watermark
 * @var bool        $is_bonus
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;

$completed = $progress && $progress->status === 'completed';
$labels = View::type_labels();
$safety = I18n::field($lesson->ID, 'safety_note');
$summary = I18n::field($lesson->ID, 'summary');
$minutes = (int) get_post_meta($lesson->ID, '_nba_duration_min', true);
$content = trim((string) $lesson->post_content);
$manual_complete = $type === 'reading' || ($type !== 'booklet' && (!$player || !$player['trackable']));
$crumb_url = $is_bonus ? add_query_arg('tab', 'bonus', Router::url('library')) : Router::programme_url($programme_id) . ($week_index ? '#week-' . $week_index : '');
$crumb = $is_bonus ? __('Bonus', 'nirog-academy') : ($week_index ? sprintf(
  /* translators: 1: week number, 2: week title */
  __('Week %1$d · %2$s', 'nirog-academy'),
  $week_index,
  View::week_title($module_id, $week_index)
) : I18n::field($programme_id, 'title'));
$config = $player ? [
  'lesson' => $lesson->ID,
  'kind' => $player['kind'],
  'yt' => $player['yt'] ?? '',
  'src' => $player['src'],
  'type' => $player['type'],
  'trackable' => $player['trackable'],
  'resume' => $progress ? (int) $progress->position_sec : 0,
  'completed' => $completed,
] : null;
?>
<article class="nba-wrap nba-page nba-lesson-page nba-lesson-<?php echo esc_attr($player ? $player['type'] : $type); ?>" data-lesson="<?php echo (int) $lesson->ID; ?>">
  <a class="nba-crumb" href="<?php echo esc_url($crumb_url); ?>">← <?php echo esc_html($crumb); ?></a>

  <?php if ($type === 'booklet' && $booklet) :
    $pages = (int) get_post_meta($lesson->ID, '_nba_pages', true);
    $resume_page = $progress && $progress->status !== 'completed' ? (int) $progress->position_sec : 0; ?>
    <section class="nba-bookhero">
      <a class="nba-bookhero-obj" href="#read" data-open-reader aria-label="<?php echo esc_attr($resume_page > 1 ? __('Continue reading', 'nirog-academy') : __('Start reading', 'nirog-academy')); ?>"><?php View::include('parts/cover', ['lesson' => $lesson->ID, 'hero' => true]); ?></a>
      <div>
        <span class="nba-eyebrow"><?php echo esc_html($labels['booklet'] . ($week_index ? ' · ' . sprintf(
          /* translators: %d: week number */
          __('Week %d', 'nirog-academy'),
          $week_index
        ) : '')); ?></span>
        <h1 class="nba-title"><?php echo esc_html(I18n::field($lesson->ID, 'title')); ?></h1>
        <?php if ($summary) : ?><p class="nba-lead"><?php echo esc_html($summary); ?></p><?php endif; ?>
        <div class="nba-bookhero-facts">
          <?php if ($pages) : ?><span><?php echo esc_html(sprintf(
            /* translators: %d: pages */
            _n('%d page', '%d pages', $pages, 'nirog-academy'),
            $pages
          )); ?></span><?php endif; ?>
          <?php if ($resume_page > 1) : ?><span><?php echo esc_html(sprintf(
            /* translators: %d: page number */
            __('You stopped on page %d.', 'nirog-academy'),
            $resume_page
          )); ?></span><?php endif; ?>
          <span class="nba-done-tag nba-complete-badge"<?php echo $completed ? '' : ' hidden'; ?>><?php echo View::icon('check', 15); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Completed', 'nirog-academy'); ?></span>
        </div>
        <div class="nba-bookhero-actions">
          <a class="nba-btn" href="#read" data-open-reader><?php echo esc_html($resume_page > 1 ? __('Continue reading', 'nirog-academy') : __('Start reading', 'nirog-academy')); ?></a>
          <?php if ($booklet['download']) : ?><a class="nba-link" href="<?php echo esc_url($booklet['url']); ?>" download><?php esc_html_e('Download PDF', 'nirog-academy'); ?></a><?php endif; ?>
        </div>
      </div>
    </section>
  <?php else : ?>
  <header class="nba-lesson-head">
    <span class="nba-eyebrow"><?php echo esc_html($labels[$type] ?? ''); ?><?php echo $minutes && $type !== 'booklet' ? ' · ' . esc_html(View::minutes($minutes)) : ''; ?></span>
    <h1 class="nba-title"><?php echo esc_html(I18n::field($lesson->ID, 'title')); ?></h1>
    <?php if ($summary) : ?><p class="nba-lead"><?php echo esc_html($summary); ?></p><?php endif; ?>
    <span class="nba-done-tag nba-complete-badge"<?php echo $completed ? '' : ' hidden'; ?>><?php echo View::icon('check', 15); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Completed', 'nirog-academy'); ?></span>
  </header>
  <?php endif; ?>

  <?php if ($safety) : ?>
    <aside class="nba-safety" role="note">
      <span class="nba-eyebrow"><?php esc_html_e('Before you start', 'nirog-academy'); ?></span>
      <p><?php echo nl2br(esc_html($safety)); ?></p>
    </aside>
  <?php endif; ?>

  <?php if ($type === 'booklet') : ?>
    <?php if ($booklet) : ?>
      <div class="nba-reader" id="nba-reader" hidden role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(I18n::field($lesson->ID, 'title')); ?>" data-config="<?php echo esc_attr((string) wp_json_encode([
        'lesson' => $lesson->ID,
        'url' => $booklet['url'],
        'resume' => $progress ? max(1, (int) $progress->position_sec) : 1,
        'read' => $progress && $progress->watched ? (string) $progress->watched : '',
        'completed' => $completed,
        'next' => $next && $next_open ? Router::lesson_url($next) : '',
        'nextTitle' => $next && $next_open ? I18n::field($next, 'title') : '',
      ])); ?>">
        <div class="nba-rd-top">
          <button type="button" class="nba-rd-btn nba-rd-close" data-act="close"><span aria-hidden="true">←</span> <span class="nba-rd-label"><?php esc_html_e('Close', 'nirog-academy'); ?></span></button>
          <div class="nba-rd-title">
            <strong><?php echo esc_html(I18n::field($lesson->ID, 'title')); ?></strong>
            <span class="nba-rd-count" aria-live="polite"></span>
          </div>
          <div class="nba-rd-tools">
            <div class="nba-rd-mode" role="group" aria-label="<?php esc_attr_e('Reading view', 'nirog-academy'); ?>">
              <button type="button" class="nba-rd-btn" data-mode="scroll" aria-pressed="false"><?php esc_html_e('Scroll', 'nirog-academy'); ?></button>
              <button type="button" class="nba-rd-btn" data-mode="book" aria-pressed="false"><?php esc_html_e('Book', 'nirog-academy'); ?></button>
            </div>
            <button type="button" class="nba-rd-btn" data-act="index" aria-expanded="false"><?php esc_html_e('Pages', 'nirog-academy'); ?></button>
            <?php if ($booklet['download']) : ?><a class="nba-rd-btn nba-rd-hide-sm" href="<?php echo esc_url($booklet['url']); ?>" download><?php esc_html_e('Download', 'nirog-academy'); ?></a><?php endif; ?>
          </div>
        </div>
        <div class="nba-rd-progress" aria-hidden="true"><span></span></div>
        <div class="nba-rd-body">
          <div class="nba-rd-book"></div>
          <div class="nba-rd-scroll" tabindex="-1"></div>
          <p class="nba-rd-msg"><?php esc_html_e('Opening the booklet…', 'nirog-academy'); ?></p>
          <div class="nba-rd-index" hidden></div>
        </div>
        <div class="nba-rd-bottom">
          <button type="button" class="nba-rd-btn" data-act="prev" aria-label="<?php esc_attr_e('Previous page', 'nirog-academy'); ?>">←</button>
          <span class="nba-rd-hint"><?php esc_html_e('Tap the page edge or swipe to turn', 'nirog-academy'); ?></span>
          <span class="nba-rd-zoom" role="group" aria-label="<?php esc_attr_e('Zoom', 'nirog-academy'); ?>">
            <button type="button" class="nba-rd-btn" data-zoom="out" aria-label="<?php esc_attr_e('Smaller', 'nirog-academy'); ?>">−</button>
            <button type="button" class="nba-rd-btn" data-zoom="in" aria-label="<?php esc_attr_e('Larger', 'nirog-academy'); ?>">+</button>
          </span>
          <button type="button" class="nba-rd-btn" data-act="next" aria-label="<?php esc_attr_e('Next page', 'nirog-academy'); ?>">→</button>
        </div>
        <template class="nba-rd-end-tpl">
          <div class="nba-rd-end">
            <span class="nba-eyebrow"><?php esc_html_e('End of booklet', 'nirog-academy'); ?></span>
            <p class="nba-rd-end-title"><?php esc_html_e('You have read every page.', 'nirog-academy'); ?></p>
            <div class="nba-rd-end-actions">
              <?php if ($next && $next_open) : ?><a class="nba-btn" href="<?php echo esc_url(Router::lesson_url($next)); ?>"><?php esc_html_e('Next lesson', 'nirog-academy'); ?> →</a><?php endif; ?>
              <button type="button" class="nba-btn nba-btn-line" data-act="close"><?php esc_html_e('Close the booklet', 'nirog-academy'); ?></button>
            </div>
          </div>
        </template>
      </div>
      <p class="nba-player-status nba-reader-status" role="status" aria-live="polite"></p>
    <?php else : ?>
      <p class="nba-lead"><?php esc_html_e('This booklet is being prepared. Please check back soon.', 'nirog-academy'); ?></p>
    <?php endif; ?>
  <?php elseif ($player) : ?>
    <div class="nba-player nba-player-<?php echo esc_attr($player['type']); ?>" data-config="<?php echo esc_attr((string) wp_json_encode($config)); ?>">
      <?php if ($player['kind'] === 'youtube') : ?>
        <div class="nba-player-frame nba-yt" data-yt="<?php echo esc_attr($player['yt']); ?>">
          <div class="nba-yt-mount"><iframe src="<?php echo esc_url($player['src']); ?>" title="<?php echo esc_attr(I18n::field($lesson->ID, 'title')); ?>" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
        </div>
      <?php elseif ($player['type'] === 'audio') : ?>
        <div class="nba-audio">
          <p class="nba-lead" style="margin:0"><?php esc_html_e('Find a comfortable position and close your eyes.', 'nirog-academy'); ?></p>
          <audio class="nba-media" controls preload="metadata" controlsList="nodownload"></audio>
          <p class="nba-meta"><?php esc_html_e('You can lock your phone. The audio keeps playing.', 'nirog-academy'); ?></p>
        </div>
      <?php else : ?>
        <div class="nba-player-frame">
          <video class="nba-media" controls playsinline preload="metadata" controlsList="nodownload" disablePictureInPicture></video>
          <?php if ($watermark) : ?><div class="nba-watermark" aria-hidden="true"><?php echo esc_html($watermark); ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="nba-pctl" role="group" aria-label="<?php esc_attr_e('Playback', 'nirog-academy'); ?>">
        <button type="button" data-seek="-10" aria-label="<?php esc_attr_e('Back 10 seconds', 'nirog-academy'); ?>">↺ 10<?php esc_html_e('s', 'nirog-academy'); ?></button>
        <button type="button" data-speed aria-label="<?php esc_attr_e('Playback speed', 'nirog-academy'); ?>">1×</button>
        <button type="button" data-seek="10" aria-label="<?php esc_attr_e('Forward 10 seconds', 'nirog-academy'); ?>">10<?php esc_html_e('s', 'nirog-academy'); ?> ↻</button>
      </div>
      <p class="nba-player-status" role="status" aria-live="polite"></p>
    </div>
  <?php elseif ($type !== 'reading') : ?>
    <p class="nba-lead"><?php esc_html_e('This lesson is being prepared. Please check back soon.', 'nirog-academy'); ?></p>
  <?php endif; ?>

  <div class="nba-actions">
    <?php if (!$completed) : ?>
      <button type="button" class="nba-btn<?php echo $manual_complete ? '' : ' nba-btn-line'; ?> nba-complete" data-manual="<?php echo $manual_complete ? '1' : '0'; ?>"><?php esc_html_e('Mark as done', 'nirog-academy'); ?></button>
    <?php endif; ?>
    <?php if ($next && $next_open) : ?>
      <a class="nba-link" href="<?php echo esc_url(Router::lesson_url($next)); ?>"><?php esc_html_e('Next lesson', 'nirog-academy'); ?> →</a>
    <?php elseif ($next) : ?>
      <span class="nba-meta"><?php esc_html_e('The next lesson opens later.', 'nirog-academy'); ?></span>
    <?php endif; ?>
  </div>

  <div class="nba-lesson-body">
    <div>
      <?php if ($content !== '') : ?>
        <div class="nba-prose"><?php echo wp_kses_post(wpautop($content)); ?></div>
      <?php elseif (!$resources && !in_array($type, ['reading', 'booklet'], true)) : ?>
        <p class="nba-muted"><?php esc_html_e('Take your time with this practice. Come back to it as often as you like.', 'nirog-academy'); ?></p>
      <?php endif; ?>
    </div>
    <aside>
      <?php if ($resources) : ?>
        <section class="nba-side-block">
          <span class="nba-eyebrow"><?php esc_html_e('Downloads', 'nirog-academy'); ?></span>
          <ul class="nba-files">
            <?php foreach ($resources as $r) : ?>
              <li><a href="<?php echo esc_url($r['url']); ?>" rel="nofollow"><?php echo esc_html($r['name']); ?></a><?php if ($r['size']) : ?><span class="nba-meta"><?php echo esc_html(size_format($r['size'])); ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
      <section class="nba-side-block">
        <label class="nba-eyebrow" for="nba-note"><?php esc_html_e('My notes', 'nirog-academy'); ?></label>
        <textarea id="nba-note" class="nba-note" rows="5" placeholder="<?php esc_attr_e('Only you can see these. How did the practice feel?', 'nirog-academy'); ?>"><?php echo esc_textarea($note); ?></textarea>
        <p class="nba-note-status" role="status" aria-live="polite"></p>
      </section>
    </aside>
  </div>

  <?php if ($prev || ($next && $next_open)) : ?>
    <nav class="nba-pager" aria-label="<?php esc_attr_e('Lessons', 'nirog-academy'); ?>">
      <?php if ($prev) : ?>
        <a href="<?php echo esc_url(Router::lesson_url($prev)); ?>"><span>← <?php esc_html_e('Previous', 'nirog-academy'); ?></span><strong><?php echo esc_html(I18n::field($prev, 'title')); ?></strong></a>
      <?php else : ?><span></span><?php endif; ?>
      <?php if ($next && $next_open) : ?>
        <a href="<?php echo esc_url(Router::lesson_url($next)); ?>"><span><?php esc_html_e('Next', 'nirog-academy'); ?> →</span><strong><?php echo esc_html(I18n::field($next, 'title')); ?></strong></a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</article>
