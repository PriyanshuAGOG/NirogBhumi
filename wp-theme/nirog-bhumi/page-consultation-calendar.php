<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-calendar.html.
 */
get_header(); ?>
<main>
<section class="page-hero slim"><p class="eyebrow">Choose your time</p><h1>Choose your consultation slot.</h1><p>Select a convenient 30-minute time with founder Gautam Khandelwal.</p></section>
<section class="calendar-shell real-calendar-shell"><div class="calendar-embed-slot" data-calendar-content><?php
$nb_calendar_entry_id = 0;
$nb_paid_order = function_exists('nirog_bhumi_paid_consultation_order_from_request') ? nirog_bhumi_paid_consultation_order_from_request() : false;
if ($nb_paid_order) {
  $nb_calendar_entry_id = absint($nb_paid_order->get_meta('_nb_consultation_entry_id'));
}
if (!$nb_calendar_entry_id && function_exists('nirog_bhumi_consultation_cookie_entry')) {
  $nb_calendar_entry_id = nirog_bhumi_consultation_cookie_entry();
}
$nb_calcom_embed = function_exists('nirog_bhumi_render_calcom_embed') ? nirog_bhumi_render_calcom_embed($nb_calendar_entry_id) : '';
if ($nb_calcom_embed) {
  echo $nb_calcom_embed;
} else {
  while (have_posts()) : the_post(); $calendar_content = trim(get_the_content()); if ($calendar_content) { the_content(); } else { ?><div class="calendar-placeholder"><span>Scheduling</span><h2>Available times will appear here.</h2><p>If no times are visible, the Nirog Bhumi team will contact you to arrange your session.</p></div><?php } endwhile;
}
?></div><aside><h2>Plan a quiet 30 minutes.</h2><p>Keep your recent reports, medicine details and questions nearby so the session can remain focused and useful.</p><a class="pill ghost" href="<?php echo esc_url(home_url('/consultation/')); ?>">Back to Consultation</a></aside></section>
</main>
<?php get_footer(); ?>