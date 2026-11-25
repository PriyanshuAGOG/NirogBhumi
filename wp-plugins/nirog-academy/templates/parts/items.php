<?php
/**
 * Numbered list of a week's items, with that week's live sessions.
 *
 * @var array     $items     [['id' => int, 'state' => array], …]
 * @var WP_Post[] $live
 * @var int       $highlight Lesson to highlight as "next".
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$labels = View::type_labels();
$highlight = $highlight ?? 0;
$live = $live ?? [];
?>
<ol class="nba-items">
  <?php foreach ($items as $i => $item) :
    $lid = $item['id'];
    $state = $item['state'];
    $type = (string) get_post_meta($lid, '_nba_type', true) ?: 'video';
    $min = (int) get_post_meta($lid, '_nba_duration_min', true);
    $pages = (int) get_post_meta($lid, '_nba_pages', true);
    $locked = $state['state'] === 'locked';
    $right = match ($state['state']) {
      'completed' => __('Done', 'nirog-academy'),
      'progress' => $type === 'booklet' ? __('Reading', 'nirog-academy') : __('Continue', 'nirog-academy'),
      'available' => $type === 'booklet' ? __('Read', 'nirog-academy') : __('Start', 'nirog-academy'),
      default => $state['label'],
    };
    $meta = array_filter([
      $labels[$type] ?? '',
      $type === 'booklet' ? ($pages ? sprintf(
        /* translators: %d: pages */
        _n('%d page', '%d pages', $pages, 'nirog-academy'),
        $pages
      ) : '') : View::minutes($min),
    ]);
    $tag = $locked ? 'div' : 'a';
    ?>
    <li>
      <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="nba-item is-<?php echo esc_attr($state['state']); ?><?php echo $lid === $highlight && !$locked ? ' is-next' : ''; ?>"<?php echo $locked ? '' : ' href="' . esc_url(Router::lesson_url($lid)) . '"'; ?>>
        <span class="nba-item-no"><?php echo esc_html(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)); ?></span>
        <span>
          <span class="nba-item-title"><?php echo esc_html(I18n::field($lid, 'title')); ?></span>
          <span class="nba-item-meta"><?php echo esc_html(implode(' · ', $meta)); ?></span>
          <?php if ($state['state'] === 'progress' && $state['pct'] > 0) : ?><span class="nba-item-bar"><span style="width:<?php echo (int) $state['pct']; ?>%"></span></span><?php endif; ?>
        </span>
        <span class="nba-item-state"><?php echo esc_html($right); ?></span>
      </<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    </li>
  <?php endforeach; ?>
  <?php foreach ($live as $session) :
    $ts = Time::ts((string) get_post_meta($session->ID, '_nba_starts_at', true));
    $past = $ts + 60 * max(5, (int) get_post_meta($session->ID, '_nba_duration_min', true)) < time();
    $recording = (int) get_post_meta($session->ID, '_nba_recording_lesson_id', true);
    $cancelled = get_post_meta($session->ID, '_nba_cancelled', true) === '1';
    $url = $past && $recording ? Router::lesson_url($recording) : \NirogBhumi\Academy\Live\LiveSessions::url($session->ID);
    ?>
    <li>
      <a class="nba-item nba-item-live<?php echo $past ? ' is-completed' : ''; ?>" href="<?php echo esc_url($url); ?>">
        <span class="nba-item-no"><?php esc_html_e('Live', 'nirog-academy'); ?></span>
        <span>
          <span class="nba-item-title"><?php echo esc_html($session->post_title); ?></span>
          <span class="nba-item-meta"><?php echo esc_html(wp_date('l, j M · g:i a', $ts)); ?></span>
        </span>
        <span class="nba-item-state"><?php echo esc_html($cancelled ? __('Cancelled', 'nirog-academy') : ($past ? ($recording ? __('Recording', 'nirog-academy') : __('Done', 'nirog-academy')) : __('Details', 'nirog-academy'))); ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ol>
