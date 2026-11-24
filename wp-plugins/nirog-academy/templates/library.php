<?php
/**
 * Library: booklets, bonus content and downloads.
 *
 * @var string $tab
 * @var array  $booklets
 * @var array  $bonus      category => items
 * @var array  $downloads
 */
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;

defined('ABSPATH') || exit;

$bonus_count = array_sum(array_map('count', $bonus));
$tabs = [
  'booklets' => [__('Booklets', 'nirog-academy'), count($booklets)],
  'bonus' => [__('Bonus', 'nirog-academy'), $bonus_count],
  'downloads' => [__('Downloads', 'nirog-academy'), count($downloads)],
];
?>
<div class="nba-wrap nba-page">
  <header class="nba-pagehead" style="border-bottom:0;margin-bottom:0">
    <span class="nba-eyebrow"><?php esc_html_e('Library', 'nirog-academy'); ?></span>
    <h1 class="nba-title"><?php echo wp_kses(__('Everything you can <em>come back to</em>', 'nirog-academy'), ['em' => []]); ?></h1>
  </header>

  <nav class="nba-tabs" aria-label="<?php esc_attr_e('Library', 'nirog-academy'); ?>">
    <?php foreach ($tabs as $key => [$label, $count]) : ?>
      <a href="<?php echo esc_url(add_query_arg('tab', $key, Router::url('library'))); ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?><sup><?php echo (int) $count; ?></sup></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($tab === 'booklets') : ?>
    <?php if (!$booklets) : ?>
      <p class="nba-lead"><?php esc_html_e('Your weekly booklets will appear here as they open.', 'nirog-academy'); ?></p>
    <?php else : ?>
      <div class="nba-covers">
        <?php foreach ($booklets as $b) :
          View::include('parts/cover', ['lesson' => $b['id'], 'state' => $b['state'], 'week' => $b['week'], 'element' => (string) get_post_meta((int) get_post_meta($b['id'], '_nba_programme_id', true), '_nba_element', true) ?: 'earth']);
        endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($tab === 'bonus') : ?>
    <?php if (!$bonus) : ?>
      <p class="nba-lead"><?php esc_html_e('Extra talks, recipes and recordings will appear here as they are added.', 'nirog-academy'); ?></p>
    <?php endif; ?>
    <?php foreach ($bonus as $category => $items) : ?>
      <section class="nba-week">
        <div class="nba-week-side"><h2 class="nba-h2"><?php echo esc_html($category); ?></h2><span class="nba-week-count"><?php echo esc_html(sprintf(
          /* translators: %d: count */
          _n('%d item', '%d items', count($items), 'nirog-academy'),
          count($items)
        )); ?></span></div>
        <div><?php View::include('parts/items', ['items' => $items, 'live' => []]); ?></div>
      </section>
    <?php endforeach; ?>

  <?php else : ?>
    <?php if (!$downloads) : ?>
      <p class="nba-lead"><?php esc_html_e('Charts and worksheets from your lessons will appear here.', 'nirog-academy'); ?></p>
    <?php else : ?>
      <ul class="nba-list">
        <?php foreach ($downloads as $f) : ?>
          <li>
            <div class="nba-list-row">
              <span class="nba-meta"><?php echo $f['week'] ? esc_html(sprintf(
                /* translators: %d: week number */
                __('Week %d', 'nirog-academy'),
                $f['week']
              )) : esc_html__('Bonus', 'nirog-academy'); ?></span>
              <span>
                <span class="nba-h3" style="display:block"><?php echo esc_html($f['name']); ?></span>
                <span class="nba-meta"><?php echo esc_html(\NirogBhumi\Academy\Support\I18n::field($f['lesson'], 'title')); ?><?php echo $f['size'] ? ' · ' . esc_html(size_format($f['size'])) : ''; ?></span>
              </span>
              <?php if ($f['url']) : ?>
                <a class="nba-link" href="<?php echo esc_url($f['url']); ?>" rel="nofollow"><?php esc_html_e('Download', 'nirog-academy'); ?></a>
              <?php else : ?>
                <span class="nba-meta"><?php esc_html_e('Opens with its lesson', 'nirog-academy'); ?></span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>
</div>
