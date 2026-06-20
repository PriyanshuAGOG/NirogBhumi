<?php
/**
 * Static Nirog Bhumi template generated from programmes/yoga-interest-form.html.
 */
get_header(); ?>
<main>
<section class="interest-page refined-interest yoga-interest-layout">
  <div class="interest-intro"><p class="eyebrow">Yoga program interest</p><h1>Help us shape your practice path.</h1><p>Tell us your current capacity, body concerns and goals so the right yoga track can be recommended with safety, progression and consistency.</p><div class="interest-points"><span>Beginner friendly</span><span>Personalised progression</span><span>Diabetes-aware practice</span></div></div>
  <form class="interest-form-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nirog_form_entry_submit"><input type="hidden" name="form_type" value="Yoga for Diabetes Interest"><?php wp_nonce_field('nirog_form_entry_submit', 'nirog_form_entry_nonce'); ?>
    <div class="form-cluster"><span>01</span><h2>Contact</h2><label>Full name<input required name="name" autocomplete="name"></label><label>Email<input required type="email" name="email" autocomplete="email"></label><div class="international-phone"><label>Country code<input required name="country_code" value="+91" inputmode="tel" autocomplete="tel-country-code" pattern="\+[0-9]{1,4}" aria-label="Country calling code"></label><label>Phone / WhatsApp<input required name="phone" inputmode="tel" autocomplete="tel-national" pattern="[0-9 ()-]{6,20}"></label></div></div>
    <div class="form-cluster"><span>02</span><h2>Practice Fit</h2><label>Preferred track<select name="track"><option>1 month reset</option><option>3 month foundation</option><option>6 month transformation</option><option>Need recommendation</option></select></label><label>Current yoga level<select name="level"><option>Complete beginner</option><option>Occasional practice</option><option>Regular beginner</option><option>Intermediate</option></select></label><label>Body concerns or limitations<textarea rows="4" name="limitations" placeholder="Back pain, stiffness, knee pain, weight, stress, diabetes, vertigo, surgery, etc."></textarea></label></div>
    <div class="form-cluster wide"><span>03</span><h2>What should yoga help you build?</h2><label>Your goal<textarea rows="5" name="message" placeholder="Flexibility, strength, glucose control, stress calm, sleep, digestion, discipline, pain support, or daily routine."></textarea></label></div>
    <button class="pill primary" type="submit">Submit Interest</button><p data-form-status></p>
  </form>
  <aside class="interest-aside"><h2>Before joining</h2><p>The practice is recommended around your current body capacity. If pain, pregnancy, surgery, vertigo or medical concerns are involved, consultation may be suggested before starting.</p><ul><li>Choose consistency over intensity.</li><li>Share limitations clearly.</li><li>Practice guidance can be adjusted as your body adapts.</li></ul></aside>
</section>
</main>
<?php get_footer(); ?>