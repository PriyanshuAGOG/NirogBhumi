<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="page-frame">
<header class="nb-header">
  <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/img-logo.png'); ?>" alt="<?php bloginfo('name'); ?>"></a>
  <button class="menu" type="button" aria-label="Open menu" data-menu-toggle><span></span><span></span></button>
  <nav data-menu><?php wp_nav_menu(['theme_location'=>'primary','container'=>false,'fallback_cb'=>false,'items_wrap'=>'%3$s']); ?><a class="book" href="<?php echo esc_url(home_url('/consultation/')); ?>">Book now</a></nav>
</header>