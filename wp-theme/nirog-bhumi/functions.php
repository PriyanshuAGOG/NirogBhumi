<?php
function nirog_bhumi_setup() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('woocommerce');
  add_theme_support('wc-product-gallery-zoom');
  add_theme_support('wc-product-gallery-lightbox');
  register_nav_menus(['primary' => __('Primary Menu', 'nirog-bhumi')]);
}
add_action('after_setup_theme', 'nirog_bhumi_setup');

function nirog_bhumi_assets() {
  wp_enqueue_style('nirog-bhumi-style', get_template_directory_uri() . '/assets/css/styles.css', [], '0.1.0');
  wp_enqueue_script('nirog-bhumi-main', get_template_directory_uri() . '/assets/js/main.js', [], '0.1.0', true);
}
add_action('wp_enqueue_scripts', 'nirog_bhumi_assets');
