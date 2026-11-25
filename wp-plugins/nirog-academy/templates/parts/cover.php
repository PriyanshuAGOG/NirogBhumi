<?php
/**
 * A booklet as a real-looking book: front cover (the PDF's first page or an
 * image the team chose), spine and page edges, turned slightly so it reads
 * as an object on a shelf. Title and details sit underneath.
 *
 * @var int    $lesson
 * @var bool   $small
 * @var bool   $hero   Large, draggable book at the top of the booklet page.
 * @var array  $state  Optional lesson state.
 * @var int    $week   Optional week number.
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;

$state = $state ?? ['state' => 'available', 'label' => ''];
$week = $week ?? 0;
$hero = !empty($hero);
$locked = $state['state'] === 'locked';
$pages = (int) get_post_meta($lesson, '_nba_pages', true);
$cover = (string) wp_get_attachment_image_url((int) get_post_meta($lesson, '_nba_cover_id', true), $hero ? 'large' : 'medium_large');
$title = I18n::field($lesson, 'title');
// Thickness follows the page count, like a real book.
$thickness = max(10, min(40, (int) round(8 + $pages * 0.6)));
$status = match ($state['state']) {
  'completed' => __('Read', 'nirog-academy'),
  'progress' => __('Continue reading', 'nirog-academy'),
  'locked' => $state['label'],
  default => __('Open the booklet', 'nirog-academy'),
};
$meta = array_filter([
  $week ? sprintf(
    /* translators: %d: week number */
    __('Week %d', 'nirog-academy'),
    $week
  ) : '',
  $pages ? sprintf(
    /* translators: %d: pages */
    _n('%d page', '%d pages', $pages, 'nirog-academy'),
    $pages
  ) : '',
]);
$book = static function () use ($cover, $title, $thickness, $hero): void {
  ?>
  <span class="nba-b3<?php echo $hero ? ' is-hero' : ''; ?>" style="--b3-depth:<?php echo (int) $thickness; ?>px">
    <span class="nba-b3-obj">
      <span class="nba-b3-front">
        <?php if ($cover) : ?><img src="<?php echo esc_url($cover); ?>" alt="" loading="lazy" crossorigin="anonymous"><?php else : ?><span class="nba-b3-plain"><?php echo esc_html($title); ?></span><?php endif; ?>
      </span>
      <span class="nba-b3-spine"><span><?php echo esc_html($title); ?></span></span>
      <span class="nba-b3-edge"></span>
      <span class="nba-b3-top"></span>
      <span class="nba-b3-back"></span>
    </span>
    <span class="nba-b3-shadow"></span>
  </span>
  <?php
};

if ($hero) {
  $book();
  return;
}
$tag = $locked ? 'div' : 'a';
?>
<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="nba-book-card is-<?php echo esc_attr($state['state']); ?><?php echo !empty($small) ? ' is-small' : ''; ?>"<?php echo $locked ? '' : ' href="' . esc_url(Router::lesson_url($lesson)) . '"'; ?>>
  <?php $book(); ?>
  <span class="nba-book-title"><?php echo esc_html($title); ?></span>
  <span class="nba-book-meta"><?php echo esc_html(implode(' · ', $meta)); ?></span>
  <span class="nba-book-status"><?php echo esc_html($status); ?></span>
</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
