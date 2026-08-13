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
  <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/img-logo.png'); ?>" alt="<?php bloginfo('name'); ?>" width="300" height="69" loading="eager" decoding="async"></a>
  <button class="menu" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="primary-navigation" data-menu-toggle><span></span><span></span></button>
  <nav id="primary-navigation" data-menu><div class="nav-dropdown" data-nav-dropdown><button class="nav-dropdown-toggle" type="button" aria-expanded="false" aria-haspopup="true" data-nav-dropdown-toggle>About</button><div class="nav-dropdown-menu"><a href="<?php echo esc_url(home_url('/about/')); ?>">About Us</a><a href="<?php echo esc_url(home_url('/founder-story/')); ?>">Founder's Story</a></div></div><a href="<?php echo esc_url(home_url('/programmes/')); ?>">Our Programs</a><a href="<?php echo esc_url(home_url('/faqs/')); ?>">FAQs</a><a href="<?php echo esc_url(home_url('/store/')); ?>">Store</a><a href="<?php echo esc_url(home_url('/education/')); ?>">Education</a><a href="https://expedition.nirogbhumi.com" target="_blank" rel="noopener">Expedition</a></nav>
</header>
<div class="nb-strip" data-nb-strip><p>&#127956; We’ve launched the <strong>World Diabetes Day Himalayan Expedition 2026</strong> &mdash; 45 days of preparation, then 6 days on the Himalayas at Dayara Bugyal.</p><a class="pill" href="https://expedition.nirogbhumi.com" target="_blank" rel="noopener">Apply Now</a><button type="button" class="nb-strip-close" data-nb-strip-close aria-label="Dismiss">&times;</button></div>