<?php
/**
 * Store archive: shop root, product categories and product tags.
 */

defined('ABSPATH') || exit;

get_header();

$is_shop_root = is_shop() && !is_search();
$dispatch = nirog_bhumi_store_dispatch_note();
?>
<main>
  <section class="page-hero slim store-archive-hero">
    <p class="eyebrow"><?php esc_html_e('Nirog Bhumi Store', 'nirog-bhumi'); ?></p>
    <h1><?php echo $is_shop_root ? esc_html__('Tools for a steadier day.', 'nirog-bhumi') : esc_html(woocommerce_page_title(false)); ?></h1>
    <?php if ($is_shop_root) : ?>
      <p class="store-archive-intro"><?php esc_html_e('The naturopathy, food and practice items we ask programme participants to keep at hand. Nothing here replaces medical care or prescribed medication.', 'nirog-bhumi'); ?></p>
    <?php else : ?>
      <?php do_action('woocommerce_archive_description'); ?>
    <?php endif; ?>
    <?php if ($dispatch) : ?><p class="store-dispatch-note"><?php echo esc_html($dispatch); ?></p><?php endif; ?>
  </section>

  <?php if (!nirog_bhumi_store_selling_is_open()) : ?>
    <p class="store-status-banner">
      <?php echo 'preview' === nirog_bhumi_store_status()
        ? esc_html__('The store is in preview. You can browse everything here, but ordering is not open yet.', 'nirog-bhumi')
        : esc_html__('Store preview, visible to shop managers only. Ordering is not open to visitors yet.', 'nirog-bhumi'); ?>
    </p>
  <?php endif; ?>

  <?php get_template_part('template-parts/store-shelf-nav'); ?>

  <section class="store-shelf">
    <?php do_action('woocommerce_before_shop_loop'); ?>
    <?php if (woocommerce_product_loop()) : ?>
      <?php woocommerce_product_loop_start(); ?>
      <?php while (have_posts()) : the_post(); ?>
        <?php wc_get_template_part('content', 'product'); ?>
      <?php endwhile; ?>
      <?php woocommerce_product_loop_end(); ?>
      <?php do_action('woocommerce_after_shop_loop'); ?>
    <?php else : ?>
      <?php do_action('woocommerce_no_products_found'); ?>
    <?php endif; ?>
  </section>
</main>
<?php
get_footer();
