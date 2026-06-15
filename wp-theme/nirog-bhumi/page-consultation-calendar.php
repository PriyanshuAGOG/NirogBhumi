<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-calendar.html.
 */
get_header(); ?>
<main>
<section class="page-hero slim"><p class="eyebrow">Step 02 / calendar</p><h1>Choose your preferred consultation slot.</h1><p>Select a 30-minute slot with founder Gautam Khandelwal. Slots should keep a 10-minute buffer between consultations. Once the slot is selected, continue to pay the Rs. 500 consultation amount.</p></section>
<section class="calendar-shell"><div class="calendar-card"><a href="<?php echo esc_url(home_url('/consultation-payment/')); ?>"><span>Mon</span><b>10</b><small>11:00 AM</small></a><a href="<?php echo esc_url(home_url('/consultation-payment/')); ?>"><span>Tue</span><b>11</b><small>4:30 PM</small></a><a href="<?php echo esc_url(home_url('/consultation-payment/')); ?>"><span>Thu</span><b>13</b><small>12:00 PM</small></a><a href="<?php echo esc_url(home_url('/consultation-payment/')); ?>"><span>Sat</span><b>15</b><small>9:30 AM</small></a></div><aside><h2>Select a slot to continue.</h2><p>Choose the time that gives you space to speak openly about reports, medicines, lifestyle rhythm and goals. After the slot is selected, you can confirm the booking amount.</p><a class="pill ghost" href="<?php echo esc_url(home_url('/consultation/')); ?>">Edit Form</a></aside></section>
</main>
<?php get_footer(); ?>