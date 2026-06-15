<?php
/**
 * Static Nirog Bhumi template generated from programmes/diabetes-interest-form.html.
 */
get_header(); ?>
<main>
<section class="interest-page refined-interest">
  <div class="interest-intro"><p class="eyebrow">Diabetes reversal interest</p><h1>Tell us where your sugar journey stands.</h1><p>Share your reports, medicines and current rhythm so the team can understand whether consultation, the 6-month program or a simpler first step is right for you.</p><div class="interest-points"><span>Report-aware</span><span>Founder-guided</span><span>Natural rhythm focused</span></div></div>
  <form class="interest-form-card" data-demo-form data-success="Thank you. Your interest has been received. The Nirog Bhumi team will review your details and guide you toward the right next step.">
    <div class="form-cluster"><span>01</span><h2>Contact</h2><label>Full name<input required name="name" autocomplete="name"></label><label>Email<input required type="email" name="email" autocomplete="email"></label><label>Phone / WhatsApp<input required name="phone" autocomplete="tel"></label></div>
    <div class="form-cluster"><span>02</span><h2>Health Snapshot</h2><label>Diabetes stage<select name="stage"><option>Type 2 diabetes</option><option>Pre-diabetes</option><option>Insulin resistance</option><option>Not sure yet</option></select></label><label>Latest HbA1c if known<input name="hba1c" placeholder="Example: 7.8"></label><label>Current medicines / insulin<textarea rows="4" name="medicines" placeholder="Medicine names, insulin or other relevant details"></textarea></label></div>
    <div class="form-cluster wide"><span>03</span><h2>Your Goal</h2><label>What do you want help with?<textarea rows="5" name="message" placeholder="Sugar control, weight, cravings, fatigue, sleep, stress, food discipline, medicine reduction guidance, etc."></textarea></label></div>
    <button class="pill primary" type="submit">Submit Interest</button><p data-form-status></p>
  </form>
  <aside class="interest-aside"><h2>What happens next?</h2><p>Your details are reviewed with care. If your case needs medical caution, we may recommend consultation first. If you are ready for structured work, the team can guide you toward the diabetes reversal program.</p><ul><li>Keep recent HbA1c or sugar readings nearby.</li><li>Do not stop or change medication without medical supervision.</li><li>Be honest about food, sleep, stress and activity patterns.</li></ul></aside>
</section>
</main>
<?php get_footer(); ?>