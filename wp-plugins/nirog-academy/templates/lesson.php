<?php
/**
 * Lesson page: player, notes, downloads, completion.
 *
 * @var WP_Post     $lesson
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
$type = (string) get_post_meta($lesson->ID, '_nba_type', true) ?: 'video';
$safety = I18n::field($lesson->ID, 'safety_note');
$summary = I18n::field($lesson->ID, 'summary');
$minutes = (int) get_post_meta($lesson->ID, '_nba_duration_min', true);
$manual_complete = !$player || !$player['trackable'] || $type === 'reading';
$config = $player ? [
  'lesson' => $lesson->ID,
  'kind' => $player['kind'],
  'src' => $player['src'],
  'type' => $player['type'],
  'trackable' => $player['trackable'],
  'resume' => $progress ? (int) $progress->position_sec : 0,
  'completed' => $completed,
] : ['lesson' => $lesson->ID];
$content = trim((string) $lesson->post_content);
?>
<article class="nba-lesson-page" data-lesson="<?php echo (int) $lesson->ID; ?>">
  <a class="nba-back" href="<?php echo esc_url($is_bonus ? Router::url('bonus') : Router::programme_url($programme_id)); ?>">
    <?php echo View::icon('arrow-left', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php echo esc_html($is_bonus ? __('Bonus library', 'nirog-academy') : ($module_id ? I18n::field($module_id, 'title') : I18n::field($programme_id, 'title'))); ?>
  </a>

  <header class="nba-lesson-head">
    <h1><?php echo esc_html(I18n::field($lesson->ID, 'title')); ?></h1>
    <p class="nba-muted">
      <?php echo esc_html(implode(' · ', array_filter([
        ['video' => __('Video', 'nirog-academy'), 'audio' => __('Audio', 'nirog-academy'), 'reading' => __('Reading', 'nirog-academy')][$type] ?? '',
        View::minutes($minutes),
      ]))); ?>
      <span class="nba-complete-badge"<?php echo $completed ? '' : ' hidden'; ?>><?php echo View::icon('check', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Completed', 'nirog-academy'); ?></span>
    </p>
  </header>

  <?php if ($safety) : ?>
    <aside class="nba-safety" role="note">
      <?php echo View::icon('alert', 20); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <div><strong><?php esc_html_e('Before you start', 'nirog-academy'); ?></strong><p><?php echo nl2br(esc_html($safety)); ?></p></div>
    </aside>
  <?php endif; ?>

  <?php if ($player) : ?>
    <div class="nba-player nba-player-<?php echo esc_attr($player['type']); ?>" data-config="<?php echo esc_attr((string) wp_json_encode($config)); ?>">
      <?php if ($player['kind'] === 'youtube') : ?>
        <div class="nba-player-frame"><iframe src="<?php echo esc_url($player['src']); ?>" title="<?php echo esc_attr(I18n::field($lesson->ID, 'title')); ?>" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
      <?php elseif ($player['type'] === 'audio') : ?>
        <div class="nba-audio-card">
          <?php echo View::icon('audio', 40); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <audio class="nba-media" controls preload="metadata" controlsList="nodownload"></audio>
          <p class="nba-muted"><?php esc_html_e('Find a comfortable position. You can lock your phone screen; the audio keeps playing.', 'nirog-academy'); ?></p>
        </div>
      <?php else : ?>
        <div class="nba-player-frame">
          <video class="nba-media" controls playsinline preload="metadata" controlsList="nodownload" disablePictureInPicture></video>
          <?php if ($watermark) : ?><div class="nba-watermark" aria-hidden="true"><?php echo esc_html($watermark); ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="nba-player-status" role="status" aria-live="polite"></div>
    </div>
  <?php endif; ?>

  <div class="nba-lesson-actions">
    <?php if (!$completed) : ?>
      <button type="button" class="nba-btn nba-complete<?php echo $manual_complete ? '' : ' nba-btn-quiet'; ?>" data-manual="<?php echo $manual_complete ? '1' : '0'; ?>">
        <?php echo View::icon('check', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Mark as complete', 'nirog-academy'); ?>
      </button>
    <?php endif; ?>
    <?php if ($next) : ?>
      <?php if ($next_open) : ?>
        <a class="nba-btn nba-btn-quiet nba-next" href="<?php echo esc_url(Router::lesson_url($next)); ?>"><?php esc_html_e('Next lesson', 'nirog-academy'); ?> <?php echo View::icon('arrow-right', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
      <?php else : ?>
        <span class="nba-muted"><?php echo View::icon('lock', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('The next lesson opens later', 'nirog-academy'); ?></span>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="nba-lesson-grid">
    <div class="nba-lesson-main">
      <?php if ($summary) : ?><p class="nba-lead"><?php echo esc_html($summary); ?></p><?php endif; ?>
      <?php if ($content !== '') : ?>
        <div class="nba-prose"><?php echo wp_kses_post(wpautop($content)); ?></div>
      <?php endif; ?>
    </div>
    <div class="nba-lesson-side">
      <?php if ($resources) : ?>
        <section class="nba-card">
          <h2 class="nba-card-title"><?php echo View::icon('download', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Downloads', 'nirog-academy'); ?></h2>
          <ul class="nba-files">
            <?php foreach ($resources as $r) : ?>
              <li><a href="<?php echo esc_url($r['url']); ?>" rel="nofollow"><?php echo esc_html($r['name']); ?></a>
                <?php if ($r['size']) : ?><span class="nba-muted"><?php echo esc_html(size_format($r['size'])); ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
      <section class="nba-card">
        <h2 class="nba-card-title"><label for="nba-note"><?php esc_html_e('My notes', 'nirog-academy'); ?></label></h2>
        <textarea id="nba-note" class="nba-note" rows="5" placeholder="<?php esc_attr_e('Only you can see these notes. How did the practice feel?', 'nirog-academy'); ?>"><?php echo esc_textarea($note); ?></textarea>
        <p class="nba-note-status nba-muted" role="status" aria-live="polite"></p>
      </section>
    </div>
  </div>

  <?php if ($prev) : ?>
    <p><a class="nba-link" href="<?php echo esc_url(Router::lesson_url($prev)); ?>">← <?php esc_html_e('Previous lesson', 'nirog-academy'); ?></a></p>
  <?php endif; ?>
</article>
