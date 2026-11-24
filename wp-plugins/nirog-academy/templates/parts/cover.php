<?php
/**
 * A booklet, drawn as a printed cover.
 *
 * @var int    $lesson
 * @var string $element
 * @var bool   $small
 * @var array  $state  Optional lesson state.
 * @var int    $week   Optional week number.
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;

$state = $state ?? ['state' => 'available', 'label' => ''];
$week = $week ?? 0;
$locked = $state['state'] === 'locked';
$pages = (int) get_post_meta($lesson, '_nba_pages', true);
$caption = match ($state['state']) {
  'completed' => __('Read', 'nirog-academy') . ' ✓',
  'progress' => __('Continue reading', 'nirog-academy'),
  'locked' => $state['label'],
  default => __('Read the booklet', 'nirog-academy'),
};
$tag = $locked ? 'div' : 'a';
?>
<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="nba-cover-link is-<?php echo esc_attr($state['state']); ?><?php echo !empty($small) ? ' nba-cover-sm' : ''; ?> nba-el-<?php echo esc_attr($element ?? 'earth'); ?>"<?php echo $locked ? '' : ' href="' . esc_url(Router::lesson_url($lesson)) . '"'; ?>>
  <span class="nba-cover">
    <span class="nba-cover-week"><?php echo $week ? esc_html(sprintf(
      /* translators: %d: week number */
      __('Week %d', 'nirog-academy'),
      $week
    )) : esc_html__('Booklet', 'nirog-academy'); ?></span>
    <span class="nba-cover-title"><?php echo esc_html(I18n::field($lesson, 'title')); ?></span>
    <span class="nba-cover-brand">Nirog Bhumi</span>
  </span>
  <span class="nba-cover-caption"><?php echo esc_html($caption); ?><?php echo $pages && !$locked ? ' · ' . esc_html(sprintf(
    /* translators: %d: pages */
    _n('%d page', '%d pages', $pages, 'nirog-academy'),
    $pages
  )) : ''; ?></span>
</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
