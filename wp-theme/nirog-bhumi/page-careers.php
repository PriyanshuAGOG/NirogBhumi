<?php
/**
 * Static Nirog Bhumi template generated from pages/careers.html.
 */
get_header(); ?>
<main>
<section class="career-thankyou">
  <p class="eyebrow">Careers</p>
  <h1>Thank you</h1>
  <p>Thank you for showing interest in working with Nirog Bhumi. We do not have any active openings right now, but you can drop your resume for future opportunities.</p>
  <form class="resume-upload" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nirog_form_entry_submit"><input type="hidden" name="form_type" value="Career Resume"><?php wp_nonce_field('nirog_form_entry_submit', 'nirog_form_entry_nonce'); ?>
    <label><span>Upload resume</span><input required type="file" name="resume" accept=".pdf,.doc,.docx"></label>
    <button class="pill primary" type="submit">Submit Resume</button>
    <p data-form-status></p>
  </form>
</section>
</main>
<?php get_footer(); ?>