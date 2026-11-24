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
  'src' => $player['src'],
  'type' => $player['type'],
  'trackable' => $player['trackable'],
  'resume' => $progress ? (int) $progress->position_sec : 0,
  'completed' => $completed,
] : null;
?>
<article class="nba-wrap nba-page nba-lesson-page" data-lesson="<?php echo (int) $lesson->ID; ?>">
  <a class="nba-crumb" href="<?php echo esc_url($crumb_url); ?>">← <?php echo esc_html($crumb); ?></a>

  <header class="nba-lesson-head">
    <span class="nba-eyebrow"><?php echo esc_html($labels[$type] ?? ''); ?><?php echo $minutes && $type !== 'booklet' ? ' · ' . esc_html(View::minutes($minutes)) : ''; ?></span>
    <h1 class="nba-title"><?php echo esc_html(I18n::field($lesson->ID, 'title')); ?></h1>
    <?php if ($summary) : ?><p class="nba-lead"><?php echo esc_html($summary); ?></p><?php endif; ?>
    <span class="nba-done-tag nba-complete-badge"<?php echo $completed ? '' : ' hidden'; ?>><?php echo View::icon('check', 15); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Completed', 'nirog-academy'); ?></span>
  </header>

  <?php if ($safety) : ?>
    <aside class="nba-safety" role="note">
      <span class="nba-eyebrow"><?php esc_html_e('Before you start', 'nirog-academy'); ?></span>
      <p><?php echo nl2br(esc_html($safety)); ?></p>
    </aside>
  <?php endif; ?>

  <?php if ($type === 'booklet') : ?>
    <?php if ($booklet) : ?>
      <div class="nba-reader" data-config="<?php echo esc_attr((string) wp_json_encode([
        'lesson' => $lesson->ID,
        'url' => $booklet['url'],
        'resume' => $progress ? max(1, (int) $progress->position_sec) : 1,
        'completed' => $completed,
      ])); ?>">
        <div class="nba-reader-bar">
          <div class="nba-reader-group">
            <button type="button" data-act="prev" aria-label="<?php esc_attr_e('Previous page', 'nirog-academy'); ?>">←</button>
            <span class="nba-reader-page" aria-live="polite">…</span>
            <button type="button" data-act="next" aria-label="<?php esc_attr_e('Next page', 'nirog-academy'); ?>">→</button>
          </div>
          <div class="nba-reader-group">
            <button type="button" data-act="out" aria-label="<?php esc_attr_e('Smaller', 'nirog-academy'); ?>">−</button>
            <button type="button" data-act="fit"><?php esc_html_e('Fit', 'nirog-academy'); ?></button>
            <button type="button" data-act="in" aria-label="<?php esc_attr_e('Bigger', 'nirog-academy'); ?>">+</button>
            <?php if ($booklet['download']) : ?><a href="<?php echo esc_url($booklet['url']); ?>" download><?php esc_html_e('Download', 'nirog-academy'); ?></a><?php endif; ?>
          </div>
        </div>
        <div class="nba-reader-pages"><p class="nba-reader-msg"><?php esc_html_e('Opening the booklet…', 'nirog-academy'); ?></p></div>
      </div>
      <p class="nba-player-status nba-reader-status" role="status" aria-live="polite"></p>
    <?php else : ?>
      <p class="nba-lead"><?php esc_html_e('This booklet is being prepared. Please check back soon.', 'nirog-academy'); ?></p>
    <?php endif; ?>
  <?php elseif ($player) : ?>
    <div class="nba-player nba-player-<?php echo esc_attr($player['type']); ?>" data-config="<?php echo esc_attr((string) wp_json_encode($config)); ?>">
      <?php if ($player['kind'] === 'youtube') : ?>
        <div class="nba-player-frame"><iframe src="<?php echo esc_url($player['src']); ?>" title="<?php echo esc_attr(I18n::field($lesson->ID, 'title')); ?>" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
      <?php elseif ($player['type'] === 'audio') : ?>
        <div class="nba-audio">
          <span class="nba-arch-sub"><?php esc_html_e('Find a comfortable position and close your eyes.', 'nirog-academy'); ?></span>
          <audio class="nba-media" controls preload="metadata" controlsList="nodownload"></audio>
          <p class="nba-meta"><?php esc_html_e('You can lock your phone. The audio keeps playing.', 'nirog-academy'); ?></p>
        </div>
      <?php else : ?>
        <div class="nba-player-frame">
          <video class="nba-media" controls playsinline preload="metadata" controlsList="nodownload" disablePictureInPicture></video>
          <?php if ($watermark) : ?><div class="nba-watermark" aria-hidden="true"><?php echo esc_html($watermark); ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
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
      <?php elseif (!$resources && $type !== 'reading') : ?>
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
