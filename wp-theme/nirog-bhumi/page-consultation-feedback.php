<?php
/**
 * Consultation feedback form. Reached only from the takeaway email link.
 * Noindex, not in navigation. Submissions stored under Form Entries.
 */
get_header();
$submitted = isset($_GET['form_saved']) && $_GET['form_saved'] === '1';
$order_ref = isset($_GET['o']) ? absint($_GET['o']) : '';
?>
<main>
<section class="nb-feedback-page">

  <div class="nb-feedback-header">
    <p class="eyebrow">Nirog Bhumi</p>
    <h1>Feedback Form</h1>
    <p>Your honest feedback helps us improve every consultation. It only takes a minute.</p>
  </div>

  <?php if ($submitted) : ?>
  <div class="nb-feedback-card nb-feedback-thanks">
    <div class="nb-thanks-icon">&#10003;</div>
    <h2>Thank you for your feedback.</h2>
    <p>We have received your response. We are grateful for your time and trust.</p>
    <a class="pill primary" href="<?php echo esc_url(home_url('/')); ?>">Back to home</a>
  </div>
  <?php else : ?>
  <form class="nb-feedback-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="nirog_form_entry_submit">
    <input type="hidden" name="form_type" value="Consultation Feedback">
    <input type="hidden" name="order_reference" value="<?php echo esc_attr($order_ref); ?>">
    <?php wp_nonce_field('nirog_form_entry_submit', 'nirog_form_entry_nonce'); ?>

    <div class="nb-feedback-section">
      <div class="nb-feedback-section-label"><span>01</span> Your details</div>
      <div class="nb-feedback-fields">
        <label>Full name<input required name="name" autocomplete="name" placeholder="Your name"></label>
        <label>Email<input required type="email" name="email" autocomplete="email" placeholder="your@email.com"></label>
      </div>
    </div>

    <div class="nb-feedback-section">
      <div class="nb-feedback-section-label"><span>02</span> Your experience</div>
      <div class="nb-feedback-fields">
        <label>Overall rating
          <select name="rating">
            <option value="5">5 — Excellent</option>
            <option value="4">4 — Good</option>
            <option value="3">3 — Okay</option>
            <option value="2">2 — Below expectations</option>
            <option value="1">1 — Poor</option>
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
    </div>

    <div class="nb-feedback-section">
      <div class="nb-feedback-section-label"><span>03</span> Tell us more</div>
      <div class="nb-feedback-fields nb-feedback-fields-full">
        <label>What helped you the most?<textarea rows="4" name="what_helped" placeholder="Anything from the consultation that was useful for you"></textarea></label>
        <label>What can we improve?<textarea rows="4" name="improvements" placeholder="Honest suggestions help us serve you better"></textarea></label>
      </div>
    </div>

    <div class="nb-feedback-submit">
      <button class="pill primary" type="submit">Submit feedback</button>
      <p data-form-status></p>
    </div>
  </form>
  <?php endif; ?>

</section>
</main>
<style>
.nb-feedback-page {
  min-height: calc(100vh - 86px);
  background: var(--paper);
  padding: clamp(40px, 6vw, 80px) clamp(16px, 5vw, 40px) clamp(60px, 8vw, 100px);
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Header */
.nb-feedback-header {
  text-align: center;
  max-width: 580px;
  margin: 0 auto clamp(32px, 4vw, 52px);
}
.nb-feedback-header h1 {
  font-family: Georgia, "Times New Roman", serif;
  font-size: clamp(38px, 6vw, 62px);
  font-weight: 400;
  color: var(--ink);
  line-height: 1;
  letter-spacing: -.02em;
  margin: 10px 0 16px;
}
.nb-feedback-header p:not(.eyebrow) {
  color: #314936;
  line-height: 1.65;
  font-size: 15px;
  margin: 0;
}

/* Card */
.nb-feedback-card {
  width: 100%;
  max-width: 620px;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: 0 16px 48px rgba(49, 73, 54, .07);
  padding: clamp(24px, 4vw, 40px);
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* Section rows */
.nb-feedback-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.nb-feedback-section-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .14em;
  color: #665a41;
}
.nb-feedback-section-label span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 50%;
  font-size: 10px;
  color: #665a41;
  flex-shrink: 0;
}

/* Field grid */
.nb-feedback-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.nb-feedback-fields-full {
  grid-template-columns: 1fr;
}
.nb-feedback-card label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #3a4a3c;
  text-transform: uppercase;
  letter-spacing: .06em;
}
.nb-feedback-card input,
.nb-feedback-card select,
.nb-feedback-card textarea {
  width: 100%;
  padding: 10px 13px;
  border: 1px solid var(--line);
  border-radius: 7px;
  background: var(--paper);
  color: #263126;
  font-size: 14px;
  font-weight: 400;
  text-transform: none;
  letter-spacing: 0;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.nb-feedback-card input:focus,
.nb-feedback-card select:focus,
.nb-feedback-card textarea:focus {
  border-color: var(--dark);
  box-shadow: 0 0 0 3px rgba(49, 73, 54, .1);
  background: #fff;
}
.nb-feedback-card textarea {
  resize: vertical;
  min-height: 96px;
}
.nb-feedback-card select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23304535' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 34px;
  cursor: pointer;
}

/* Divider between sections */
.nb-feedback-section + .nb-feedback-section {
  border-top: 1px solid var(--line);
  padding-top: 28px;
}

/* Submit row */
.nb-feedback-submit {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  border-top: 1px solid var(--line);
  padding-top: 24px;
}
.nb-feedback-submit .pill.primary {
  min-width: 200px;
  font-size: 14px;
  letter-spacing: .04em;
}
.nb-feedback-submit [data-form-status] {
  font-size: 13px;
  color: #c0392b;
  min-height: 18px;
  text-align: center;
}

/* Thank you card */
.nb-feedback-thanks {
  align-items: center;
  text-align: center;
  gap: 16px;
}
.nb-thanks-icon {
  width: 54px;
  height: 54px;
  background: #e8f5e9;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #2e7d32;
}
.nb-feedback-thanks h2 {
  font-family: Georgia, serif;
  font-size: clamp(22px, 3vw, 30px);
  font-weight: 400;
  color: var(--ink);
  margin: 0;
}
.nb-feedback-thanks p {
  color: #314936;
  line-height: 1.65;
  max-width: 440px;
  margin: 0;
}

/* Mobile */
@media (max-width: 540px) {
  .nb-feedback-fields {
    grid-template-columns: 1fr;
  }
}
</style>
<?php get_footer(); ?>
