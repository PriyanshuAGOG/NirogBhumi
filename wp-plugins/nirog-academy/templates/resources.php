<?php
/**
 * All downloads across the participant's programmes.
 *
 * @var array $sections
 */
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;
?>
<header class="nba-page-head nba-el-water">
  <h1><?php esc_html_e('Downloads', 'nirog-academy'); ?></h1>
  <p class="nba-lead"><?php esc_html_e('Charts, worksheets and guides from your lessons. Files open when their lesson opens.', 'nirog-academy'); ?></p>
</header>
<?php if (!$sections) : ?>
  <p class="nba-card"><?php esc_html_e('No downloads yet.', 'nirog-academy'); ?></p>
<?php endif; ?>
<?php foreach ($sections as $section) : ?>
  <section class="nba-card">
    <h2 class="nba-card-title"><?php echo esc_html(I18n::field($section['programme_id'], 'title')); ?></h2>
    <ul class="nba-files">
      <?php foreach ($section['items'] as $f) : ?>
        <li>
          <?php if ($f['url']) : ?>
            <a href="<?php echo esc_url($f['url']); ?>" rel="nofollow"><?php echo View::icon('download', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html($f['name']); ?></a>
          <?php else : ?>
            <span class="nba-muted"><?php echo View::icon('lock', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html($f['name']); ?></span>
          <?php endif; ?>
          <span class="nba-muted"><?php echo esc_html(I18n::field($f['lesson'], 'title')); ?><?php echo $f['size'] ? ' · ' . esc_html(size_format($f['size'])) : ''; ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
