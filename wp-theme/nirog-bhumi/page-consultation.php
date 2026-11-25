<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation.html.
 */
get_header(); ?>
<main>
<section class="hero consultation-simple-hero">
  <div class="hero-copy consultation-hero-copy"><h1>Book Consultation</h1><div class="hero-actions"><a class="pill primary" href="#consultation-form">Book Consultation</a></div></div>
  <div class="consultation-hero-media"><figure class="hero-visual consultation-hero-visual consultation-photo-visual consultation-room-visual"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/consultation-hero-generated.jpg'); ?>" alt="Nirog Bhumi consultation session" width="1536" height="1024"></figure></div>
</section>
<section class="consultation-founder-strip">
  <div class="consultation-founder-copy"><p class="eyebrow">Founder-led session</p><h2>Book a 30-minute session with Gautam Khandelwal.</h2><p>Gautam brings over 26 years of personal practice, study, and lived experience in naturopathy and natural healing. This session is designed to understand your diabetes history, lifestyle rhythm, current medication context, and the most responsible next step before any program or product recommendation.</p></div>
  <figure><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/yoga-meditation-opt.jpg'); ?>" alt="Nirog Bhumi yoga and meditation practice" width="1100" height="733"></figure>
</section>
<section id="consultation-form" class="consultation-form-wrap">
  <div class="consultation-form-intro"><p class="eyebrow">Consultation form</p><h2>Let's get you booked in.</h2><p>Share your name, email and phone number so we can confirm your session and get you to payment. Once that's done, we'll ask a few more questions to help Gautam prepare for your 30 minutes.</p><p class="consultation-price-inline"><strong>Consultation fee: &#8377;590</strong> <span>(&#8377;500 + 18% GST) for a 30-minute session</span></p></div>
  <form class="consultation-booking-form step-consultation-form consultation-contact-form" data-step-form data-wp-form="true" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nirog_consultation_submit"><input type="hidden" name="nb_form_stage" value="contact"><input type="hidden" name="consultation_entry_id" value=""><?php wp_nonce_field('nirog_consultation_submit', 'nirog_consultation_nonce'); ?>
    <fieldset data-step-panel><legend>Your details</legend><div><label>Full name<input required name="name" autocomplete="name"></label><label>Email<input required type="email" name="email" autocomplete="email"></label><div class="international-phone"><label>Country code<input required name="country_code" value="+91" inputmode="tel" autocomplete="tel-country-code" pattern="\+?[0-9]{1,4}" aria-label="Country calling code"></label><label>Phone / WhatsApp<input required name="phone" inputmode="tel" autocomplete="tel-national" pattern="[0-9 ()-]{6,20}"></label></div></div><div class="consent-copy"><p><strong>Consultation disclaimer:</strong> I understand that this 30-minute consultation is for educational and lifestyle guidance only. It is not emergency care, diagnosis, prescription, or a substitute for consultation with my treating physician. I will not start, stop, or change any medication based on this session without discussing it with my doctor.</p><p>I consent to Nirog Bhumi collecting and processing my name, email and phone number to schedule this consultation, process payment, and contact me about it, as per Nirog Bhumi's Privacy Policy.</p></div><label class="check"><input required type="checkbox" name="consultation_disclaimer"> I agree to the consultation disclaimer.</label><label class="check"><input required type="checkbox" name="contact_data_consent"> I consent to my contact details being used as described above.</label></fieldset>
    <div class="step-actions"><button class="pill primary" type="submit">Continue to Payment</button></div>
    <p data-form-status></p>
  </form>
</section><?php if (!empty($_GET['edit_consultation']) && !empty($_GET['entry'])) : ?><script>
document.addEventListener('DOMContentLoaded',function(){
  var form=document.querySelector('[data-step-form]');
  if(!form)return;
  var url=<?php echo wp_json_encode(add_query_arg(['action'=>'nirog_consultation_edit_data','entry'=>absint(wp_unslash($_GET['entry']))],admin_url('admin-ajax.php'))); ?>;
  fetch(url,{credentials:'same-origin',cache:'no-store'}).then(function(response){return response.json();}).then(function(result){
    if(!result.success)throw new Error(result.data&&result.data.message?result.data.message:'Unable to load the saved response.');
    Object.keys(result.data).forEach(function(name){
      var field=form.elements.namedItem(name);if(!field)return;
      if(field.type==='checkbox')field.checked=result.data[name]==='yes';else field.value=result.data[name]||'';
    });
    var status=form.querySelector('[data-form-status]');if(status)status.textContent='Your saved response is ready to edit. Submit again to update it.';
  }).catch(function(error){var status=form.querySelector('[data-form-status]');if(status)status.textContent=error.message;});
});
</script><?php endif; ?>
</main>
<?php get_footer(); ?>