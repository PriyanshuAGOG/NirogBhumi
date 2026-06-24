<?php
/**
 * Hidden consultation feedback form. Reached only from the takeaway email link
 * (noindex, not linked in navigation). Submissions are stored under
 * WordPress Admin -> Form Entries as "Consultation Feedback".
 */
get_header();
$submitted = isset($_GET['form_saved']) && $_GET['form_saved'] === '1';
$order_ref = isset($_GET['o']) ? absint($_GET['o']) : '';
?>
<main>
<section class="interest-page refined-interest">
  <?php if ($submitted) : ?>
  <div class="interest-form-card">
    <h2>Thank you for your feedback.</h2>
    <p>We have received your response. We are grateful for your time and trust.</p>
    <p><a class="pill primary" href="<?php echo esc_url(home_url('/')); ?>">Back to home</a></p>
  </div>
  <?php else : ?>
  <form class="interest-form-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="nirog_form_entry_submit">
    <input type="hidden" name="form_type" value="Consultation Feedback">
    <input type="hidden" name="order_reference" value="<?php echo esc_attr($order_ref); ?>">
    <?php wp_nonce_field('nirog_form_entry_submit', 'nirog_form_entry_nonce'); ?>
    <div class="form-cluster"><span>01</span><h2>You</h2>
      <label>Full name<input required name="name" autocomplete="name"></label>
      <label>Email<input required type="email" name="email" autocomplete="email"></label>
    </div>
    <div class="form-cluster"><span>02</span><h2>Your experience</h2>
      <label>Overall rating
        <select name="rating">
          <option value="5">5 - Excellent</option>
          <option value="4">4 - Good</option>
          <option value="3">3 - Okay</option>
          <option value="2">2 - Below expectations</option>
          <option value="1">1 - Poor</option>
        </select>
      </label>
      <label>Would you recommend us?
        <select name="would_recommend">
          <option>Yes</option>
          <option>Maybe</option>
          <option>No</option>
        </select>
      </label>
    </div>
    <div class="form-cluster wide"><span>03</span><h2>Tell us more</h2>
      <label>What helped you the most?<textarea rows="4" name="what_helped" placeholder="Anything from the consultation that was useful for you"></textarea></label>
      <label>What can we improve?<textarea rows="4" name="improvements" placeholder="Honest suggestions help us serve you better"></textarea></label>
    </div>
    <button class="pill primary" type="submit">Submit feedback</button><p data-form-status></p>
  </form>
  <?php endif; ?>
</section>
</main>
<?php get_footer(); ?>
