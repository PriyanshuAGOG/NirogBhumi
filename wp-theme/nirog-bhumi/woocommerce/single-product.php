<?php
/**
 * Single product wrapper.
 */

defined('ABSPATH') || exit;

get_header();
?>
<main class="nb-single-product">
  <?php while (have_posts()) : the_post(); ?>
    <?php wc_get_template_part('content', 'single-product'); ?>
  <?php endwhile; ?>
</main>
<?php
get_footer();
