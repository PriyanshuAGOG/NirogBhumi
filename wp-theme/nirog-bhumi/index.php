<?php get_header(); ?>
<main class="text-card">
  <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <h1><?php the_title(); ?></h1>
    <?php the_content(); ?>
  <?php endwhile; else : ?>
    <h1><?php esc_html_e('Page not found', 'nirog-bhumi'); ?></h1>
  <?php endif; ?>
</main>
<?php get_footer(); ?>