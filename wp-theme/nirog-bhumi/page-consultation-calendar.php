<?php
/**
 * Static Nirog Bhumi template generated from pages/consultation-calendar.html.
 *
 * Also carries the fuller health/lifestyle form (age, billing, health
 * details, medication, lifestyle). It only appears here - reachable only
 * once nirog_bhumi_protect_consultation_flow() has confirmed the payment is
 * verified - framed as something that helps Gautam prepare, not flagged as
 * "optional" in the copy, while every field in it stays non-required just
 * as it always has been.
 */

$nb_calendar_entry_id = 0;
$nb_paid_order = function_exists('nirog_bhumi_paid_consultation_order_from_request') ? nirog_bhumi_paid_consultation_order_from_request() : false;
if ($nb_paid_order) {
  $nb_calendar_entry_id = absint($nb_paid_order->get_meta('_nb_consultation_entry_id'));
}
if (!$nb_calendar_entry_id && function_exists('nirog_bhumi_consultation_cookie_entry')) {
  $nb_calendar_entry_id = nirog_bhumi_consultation_cookie_entry();
}

$nb_details_saved = isset($_GET['details_saved']) && $_GET['details_saved'] === '1';
$nb_details = [];
if ($nb_calendar_entry_id) {
  $nb_details_keys = ['name', 'email', 'country_code', 'phone', 'age', 'billing_address', 'billing_city', 'billing_state_code', 'billing_postcode', 'billing_country', 'customer_gstin', 'concern', 'fasting', 'postmeal', 'hba1c', 'bp', 'height', 'body', 'medicines', 'conditions', 'food', 'lifestyle', 'goal', 'followup_consent'];
  foreach ($nb_details_keys as $nb_key) {
    $nb_details[$nb_key] = (string) get_post_meta($nb_calendar_entry_id, $nb_key, true);
  }
}

get_header(); ?>
<main>
<section class="page-hero slim"><p class="eyebrow">Choose your time</p><h1>Choose your consultation slot.</h1><p>Select a convenient 30-minute time with founder Gautam Khandelwal.</p></section>

<?php if ($nb_calendar_entry_id) : ?>
<section id="consultation-details-form" class="consultation-form-wrap">
  <div class="consultation-form-intro"><p class="eyebrow">Before your session</p><h2>Help Gautam prepare for your 30 minutes.</h2><p>Share a bit more about your health history, current medication and daily routine so the session can go straight into what matters for you.</p></div>
  <?php if ($nb_details_saved) : ?><p class="consultation-details-saved-note">Thank you - these details have been saved and shared with Gautam ahead of your session.</p><?php endif; ?>
  <form class="consultation-booking-form consultation-details-form" data-wp-form="true" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="nirog_consultation_submit">
    <input type="hidden" name="nb_form_stage" value="details">
    <input type="hidden" name="consultation_entry_id" value="<?php echo esc_attr($nb_calendar_entry_id); ?>">
    <input type="hidden" name="name" value="<?php echo esc_attr($nb_details['name']); ?>">
    <input type="hidden" name="email" value="<?php echo esc_attr($nb_details['email']); ?>">
    <input type="hidden" name="country_code" value="<?php echo esc_attr($nb_details['country_code']); ?>">
    <input type="hidden" name="phone" value="<?php echo esc_attr($nb_details['phone']); ?>">
    <input type="hidden" name="consultation_disclaimer" value="yes">
    <input type="hidden" name="contact_data_consent" value="yes">
    <?php wp_nonce_field('nirog_consultation_submit', 'nirog_consultation_nonce'); ?>
    <fieldset><legend>A few more details</legend><div>
      <label>Age<input name="age" inputmode="numeric" autocomplete="bday" value="<?php echo esc_attr($nb_details['age']); ?>"></label>
      <label class="wide">Billing address<input name="billing_address" autocomplete="street-address" value="<?php echo esc_attr($nb_details['billing_address']); ?>"></label>
      <label>City<input name="billing_city" autocomplete="address-level2" value="<?php echo esc_attr($nb_details['billing_city']); ?>"></label>
      <label>State<select name="billing_state_code" autocomplete="address-level1"><?php
        $nb_states = ['08' => 'Rajasthan (08)', '01' => 'Jammu and Kashmir (01)', '02' => 'Himachal Pradesh (02)', '03' => 'Punjab (03)', '04' => 'Chandigarh (04)', '05' => 'Uttarakhand (05)', '06' => 'Haryana (06)', '07' => 'Delhi (07)', '09' => 'Uttar Pradesh (09)', '10' => 'Bihar (10)', '11' => 'Sikkim (11)', '12' => 'Arunachal Pradesh (12)', '13' => 'Nagaland (13)', '14' => 'Manipur (14)', '15' => 'Mizoram (15)', '16' => 'Tripura (16)', '17' => 'Meghalaya (17)', '18' => 'Assam (18)', '19' => 'West Bengal (19)', '20' => 'Jharkhand (20)', '21' => 'Odisha (21)', '22' => 'Chhattisgarh (22)', '23' => 'Madhya Pradesh (23)', '24' => 'Gujarat (24)', '26' => 'Dadra and Nagar Haveli and Daman and Diu (26)', '27' => 'Maharashtra (27)', '29' => 'Karnataka (29)', '30' => 'Goa (30)', '31' => 'Lakshadweep (31)', '32' => 'Kerala (32)', '33' => 'Tamil Nadu (33)', '34' => 'Puducherry (34)', '35' => 'Andaman and Nicobar Islands (35)', '36' => 'Telangana (36)', '37' => 'Andhra Pradesh (37)', '38' => 'Ladakh (38)', '97' => 'Other / outside India'];
        $nb_selected_state = $nb_details['billing_state_code'] ?: '08';
        foreach ($nb_states as $nb_code => $nb_label) {
          echo '<option value="' . esc_attr($nb_code) . '"' . selected($nb_selected_state, $nb_code, false) . '>' . esc_html($nb_label) . '</option>';
        }
      ?></select></label>
      <input type="hidden" name="billing_state" value="<?php echo esc_attr(get_post_meta($nb_calendar_entry_id, 'billing_state', true) ?: 'Rajasthan'); ?>">
      <label>Postal code<input name="billing_postcode" autocomplete="postal-code" value="<?php echo esc_attr($nb_details['billing_postcode']); ?>"></label>
      <label>Country<input name="billing_country" autocomplete="country-name" value="<?php echo esc_attr($nb_details['billing_country'] ?: 'India'); ?>"></label>
    </div></fieldset>
    <fieldset><legend>Health details</legend><div>
      <label>Primary concern<select name="concern"><?php
        foreach (['Type 2 diabetes', 'Pre-diabetes', 'Insulin resistance', 'Weight and sugar control', 'General consultation'] as $nb_concern_option) {
          echo '<option' . selected($nb_details['concern'], $nb_concern_option, false) . '>' . esc_html($nb_concern_option) . '</option>';
        }
      ?></select></label>
      <label>Fasting blood sugar<input name="fasting" placeholder="Example: 145 mg/dL" value="<?php echo esc_attr($nb_details['fasting']); ?>"></label>
      <label>Post-meal blood sugar<input name="postmeal" placeholder="Example: 210 mg/dL" value="<?php echo esc_attr($nb_details['postmeal']); ?>"></label>
      <label>Latest HbA1c<input name="hba1c" placeholder="Example: 8.2" value="<?php echo esc_attr($nb_details['hba1c']); ?>"></label>
      <label>Blood pressure<input name="bp" placeholder="Example: 130/85" value="<?php echo esc_attr($nb_details['bp']); ?>"></label>
      <label>Height<input name="height" placeholder="Example: 170 cm or 5 ft 7 in" value="<?php echo esc_attr($nb_details['height']); ?>"></label>
      <label>Weight / waist<input name="body" placeholder="Example: 82 kg, 38 inch waist" value="<?php echo esc_attr($nb_details['body']); ?>"></label>
    </div></fieldset>
    <fieldset><legend>Medication and lifestyle</legend><div>
      <label>Current medication or insulin<textarea rows="4" name="medicines" placeholder="Medicine names, insulin, dose"><?php echo esc_textarea($nb_details['medicines']); ?></textarea></label>
      <label>Other health conditions<textarea rows="4" name="conditions" placeholder="BP, thyroid, kidney, heart, digestion, pain, surgery, etc."><?php echo esc_textarea($nb_details['conditions']); ?></textarea></label>
      <label>Food routine<textarea rows="4" name="food" placeholder="Meals, snacks, tea, late-night eating, eating window"><?php echo esc_textarea($nb_details['food']); ?></textarea></label>
      <label>Sleep, stress and activity<textarea rows="4" name="lifestyle" placeholder="Wake & sleep time, stress level, walking, yoga, work hours"><?php echo esc_textarea($nb_details['lifestyle']); ?></textarea></label>
      <label>Attach reports, if available<input type="file" name="reports[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"></label>
      <label>Main goal<textarea rows="4" name="goal" placeholder="What do you want help with first?"><?php echo esc_textarea($nb_details['goal']); ?></textarea></label>
    </div></fieldset>
    <fieldset><legend>Consent</legend><div class="consent-copy"><p><strong>Data consent:</strong> I consent to Nirog Bhumi collecting and processing my personal and health-related information, including my diabetes history, lifestyle information, medication context, and any reports or information shared by me, for the purpose of conducting this consultation, providing lifestyle guidance, maintaining consultation records, follow-up communication, and recommending suitable Nirog Bhumi programs or products where appropriate.</p><p>I understand that my information will be handled as per Nirog Bhumi's Privacy Policy, that I may request access, correction, or withdrawal of consent by emailing <a href="mailto:priyanshu@nirogbhumi.com">priyanshu@nirogbhumi.com</a>, and that withdrawal of consent may affect Nirog Bhumi's ability to provide follow-up services.</p></div><label class="check"><input required type="checkbox" name="data_processing_consent"> I consent to the processing of my personal and health-related data as described above.</label><label class="check"><input type="checkbox" name="followup_consent" <?php checked($nb_details['followup_consent'], 'yes'); ?>> I agree to receive follow-up messages and program/product recommendations from Nirog Bhumi.</label></fieldset>
    <div class="step-actions"><button class="pill primary" type="submit">Save Details for Gautam</button></div>
    <p data-form-status></p>
  </form>
</section>
<?php endif; ?>

<section class="calendar-shell real-calendar-shell"><div class="calendar-embed-slot" data-calendar-content><?php
$nb_calcom_embed = function_exists('nirog_bhumi_render_calcom_embed') ? nirog_bhumi_render_calcom_embed($nb_calendar_entry_id) : '';
if ($nb_calcom_embed) {
  echo $nb_calcom_embed;
} else {
  while (have_posts()) : the_post(); $calendar_content = trim(get_the_content()); if ($calendar_content) { the_content(); } else { ?><div class="calendar-placeholder"><span>Scheduling</span><h2>Available times will appear here.</h2><p>If no times are visible, the Nirog Bhumi team will contact you to arrange your session.</p></div><?php } endwhile;
}
?></div><aside><h2>Plan a quiet 30 minutes.</h2><p>Keep your recent reports, medicine details and questions nearby so the session can remain focused and useful.</p><a class="pill ghost" href="<?php echo esc_url(home_url('/consultation/')); ?>">Back to Consultation</a></aside></section>
</main>
<?php get_footer(); ?>
