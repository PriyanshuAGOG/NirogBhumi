<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation.html.
 */
get_header(); ?>
<main>
<section class="hero consultation-simple-hero">
  <div><figure class="hero-visual consultation-hero-visual"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/yoga-backbend-hero.jpg'); ?>" srcset="../assets/img/yoga-backbend-hero-thumb.jpg 360w, ../assets/img/yoga-backbend-hero.jpg 520w" sizes="(max-width: 900px) 340px, 430px" alt="Nirog Bhumi consultation" width="520" height="624"></figure></div>
  <div class="hero-copy"><h1>Book Consultation</h1><div class="hero-actions"><a class="pill primary" href="#consultation-form">Book Consultation</a></div></div>
</section>
<section class="consultation-founder-strip">
  <div class="consultation-founder-copy"><p class="eyebrow">Founder-led session</p><h2>Book a 30-minute session with Gautam Khandelwal.</h2><p>Gautam brings 25+ years of experience in naturopathy and natural healing. The session is designed to understand your diabetes history, lifestyle rhythm, current medication context and the next responsible step before any program or product recommendation.</p></div>
  <figure><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/consultation-opt.jpg'); ?>" alt="Nirog Bhumi consultation room" width="900" height="600"></figure>
</section>
<section id="consultation-form" class="consultation-form-wrap">
  <div class="consultation-form-intro"><p class="eyebrow">Consultation form</p><h2>Share the details that matter.</h2><p>Complete the form in parts, choose a time slot, then continue to the Rs. 500 checkout for booking confirmation.</p></div>
  <form class="consultation-booking-form step-consultation-form" data-step-form data-redirect="consultation-calendar.html">
    <div class="step-progress"><span data-step-count>Step 1 of 4</span><b></b></div>
    <fieldset data-step-panel><legend>Personal details</legend><div><label>Full name<input required name="name" autocomplete="name"></label><label>Email<input required type="email" name="email" autocomplete="email"></label><label>Phone / WhatsApp<input required name="phone" autocomplete="tel"></label></div></fieldset>
    <fieldset data-step-panel hidden><legend>Health details</legend><div><label>Primary concern<select required name="concern"><option>Type 2 diabetes</option><option>Pre-diabetes</option><option>Insulin resistance</option><option>Weight and sugar control</option><option>General consultation</option></select></label><label>Fasting blood sugar<input name="fasting" placeholder="Example: 145 mg/dL"></label><label>Post-meal blood sugar<input name="postmeal" placeholder="Example: 210 mg/dL"></label><label>Latest HbA1c<input name="hba1c" placeholder="Example: 8.2"></label><label>Blood pressure<input name="bp" placeholder="Example: 130/85"></label><label>Weight / waist<input name="body" placeholder="Example: 82 kg, 38 inch waist"></label></div></fieldset>
    <fieldset data-step-panel hidden><legend>Medication and lifestyle</legend><div><label>Current medication or insulin<textarea rows="4" name="medicines" placeholder="Medicine names, insulin, dose if comfortable sharing"></textarea></label><label>Other health conditions<textarea rows="4" name="conditions" placeholder="BP, thyroid, kidney, heart, digestion, pain, surgery, etc."></textarea></label><label>Food timing<textarea rows="4" name="food" placeholder="Wake time, meals, snacks, tea, late-night eating"></textarea></label><label>Sleep, stress and activity<textarea rows="4" name="lifestyle" placeholder="Sleep time, stress level, walking, yoga, work hours"></textarea></label><label>Main goal<textarea rows="4" name="goal" placeholder="What do you want help with first?"></textarea></label></div></fieldset>
    <fieldset data-step-panel hidden><legend>Consent</legend><label class="check"><input required type="checkbox" name="consent"> I understand this is not emergency care and medication changes must be discussed with my doctor.</label></fieldset>
    <div class="step-actions"><button class="pill ghost" type="button" data-step-prev hidden>Back</button><button class="pill primary" type="button" data-step-next>Next</button><button class="pill primary" type="submit" hidden>Choose Time Slot</button></div>
    <p data-form-status></p>
  </form>
</section>
</main>
<?php get_footer(); ?>