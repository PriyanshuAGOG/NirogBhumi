<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-calendar.html.
 */
get_header(); ?>
<main>
<section class="page-hero slim"><p class="eyebrow">Step 03 / calendar</p><h1>Choose your consultation slot.</h1><p>After the booking amount is paid, use this page to select a 30-minute slot with founder Gautam Khandelwal. Add your Cal.com or Google Calendar booking embed from WordPress whenever you are ready.</p></section>
<section class="calendar-shell real-calendar-shell"><div class="calendar-embed-slot" data-calendar-content><?php while (have_posts()) : the_post(); $calendar_content = trim(get_the_content()); if ($calendar_content) { the_content(); } else { ?><div class="calendar-placeholder"><span>Calendar embed</span><h2>Connect Cal.com or Google Calendar here.</h2><p>Edit this WordPress page and paste your Cal.com shortcode, Cal.com embed, or Google Appointment Schedule embed. The live booking calendar will appear in this space.</p></div><?php } endwhile; ?></div><aside><h2>Booking after payment.</h2><p>This page is intentionally free of dummy slots. Use Cal.com if you want payments, buffers, reminders and Google Calendar sync in one workflow. Use Google Appointment Schedule if you want the lightest Google-native setup.</p><a class="pill ghost" href="<?php echo esc_url(home_url('/consultation/')); ?>">Back to Consultation</a></aside></section>
</main>
<?php get_footer(); ?>