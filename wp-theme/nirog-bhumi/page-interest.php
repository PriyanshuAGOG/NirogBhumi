<?php
/**
 * Quick yoga interest form - name and phone number only.
 *
 * Deliberately unlinked from navigation, footer and any menu - shared
 * directly by link. nirog_bhumi_private_consultation_robots() (functions.php)
 * marks it noindex/nofollow so it never surfaces via search either.
 */
get_header();
$submitted = isset($_GET['form_saved']) && $_GET['form_saved'] === '1';
?>
<main>
<section class="interest-page yoga-interest-form-only">

  <?php if ($submitted) : ?>
    <div class="interest-form-card">
      <h2><?php esc_html_e('Thank you.', 'nirog-bhumi'); ?></h2>
      <p><?php esc_html_e('We have your details and will reach out shortly.', 'nirog-bhumi'); ?></p>
    </div>
  <?php else : ?>
    <div class="yoga-interest-heading">
      <p class="eyebrow"><?php esc_html_e('Register interest', 'nirog-bhumi'); ?></p>
      <h1><?php esc_html_e('Yoga for Diabetes Reversal', 'nirog-bhumi'); ?></h1>
    </div>
    <form class="interest-form-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="nirog_form_entry_submit">
      <input type="hidden" name="form_type" value="Yoga Interest">
      <?php wp_nonce_field('nirog_form_entry_submit', 'nirog_form_entry_nonce'); ?>
      <div class="form-cluster wide">
        <span>01</span>
        <h2><?php esc_html_e('Your details', 'nirog-bhumi'); ?></h2>
        <label><?php esc_html_e('Full name', 'nirog-bhumi'); ?>
          <input required name="name" autocomplete="name">
        </label>
        <label><?php esc_html_e('Phone / WhatsApp', 'nirog-bhumi'); ?>
          <input required name="phone" inputmode="tel" autocomplete="tel" pattern="[0-9 ()+-]{6,20}">
        </label>
      </div>
      <button class="pill primary" type="submit"><?php esc_html_e('Register Interest', 'nirog-bhumi'); ?></button>
    </form>
  <?php endif; ?>

</section>
</main>
<?php get_footer(); ?>
