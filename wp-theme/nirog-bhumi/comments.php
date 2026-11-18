<?php
/**
 * The standard WordPress comments template, loaded automatically by
 * comments_template() wherever a singular template calls it (currently
 * single-nb_education_post.php, for the education article comment box).
 */

if (post_password_required()) {
  return;
}
?>
<div id="comments" class="nb-comments">

  <?php if (have_comments()) : ?>
    <h2 class="nb-comments-title">
      <?php
      $nb_comment_count = get_comments_number();
      printf(
        /* translators: %s: number of comments */
        esc_html(_n('%s Comment', '%s Comments', $nb_comment_count, 'nirog-bhumi')),
        esc_html(number_format_i18n($nb_comment_count))
      );
      ?>
    </h2>

    <ol class="nb-comment-list">
      <?php
      wp_list_comments([
        'style' => 'ol',
        'short_ping' => true,
        'avatar_size' => 44,
        'callback' => 'nirog_bhumi_education_comment_callback',
      ]);
      ?>
    </ol>

    <?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : ?>
      <nav class="nb-comment-nav">
        <?php previous_comments_link(esc_html__('&larr; Older comments', 'nirog-bhumi')); ?>
        <?php next_comments_link(esc_html__('Newer comments &rarr;', 'nirog-bhumi')); ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!comments_open() && get_comments_number()) : ?>
    <p class="nb-comments-closed"><?php esc_html_e('Comments are closed.', 'nirog-bhumi'); ?></p>
  <?php endif; ?>

  <?php
  // Custom fields: no Website field, and the field order/required-ness is
  // explicit rather than left to WordPress's own "require_name_email"
  // default field markup (which is what was stacking the label text and
  // the "*" onto separate lines under this theme's global label styling).
  $nb_commenter = wp_get_current_commenter();
  $nb_consent_checked = !empty($nb_commenter['comment_author_email']) ? ' checked' : '';
  comment_form([
    'title_reply' => __('Leave a comment', 'nirog-bhumi'),
    'title_reply_to' => __('Leave a reply to %s', 'nirog-bhumi'),
    'label_submit' => __('Post Comment', 'nirog-bhumi'),
    'class_form' => 'nb-comment-form',
    'class_submit' => 'pill primary',
    'comment_field' => '<p class="comment-form-comment"><label for="comment">' . esc_html__('Comment', 'nirog-bhumi') . ' <span class="required">*</span></label><textarea id="comment" name="comment" rows="6" required></textarea></p>',
    'fields' => [
      'author' => '<p class="comment-form-author"><label for="author">' . esc_html__('Name', 'nirog-bhumi') . ' <span class="required">*</span></label><input id="author" name="author" type="text" value="' . esc_attr($nb_commenter['comment_author']) . '" required></p>',
      'email' => '<p class="comment-form-email"><label for="email">' . esc_html__('Email', 'nirog-bhumi') . ' <span class="required">*</span></label><input id="email" name="email" type="email" value="' . esc_attr($nb_commenter['comment_author_email']) . '" required></p>',
      // Replaces WordPress's generic "save my name, email and website in
      // this browser" opt-in with an explicit Digital Personal Data
      // Protection Act (DPDP Act) consent statement covering what this
      // site actually does with the submitted data: publishing the
      // comment and name, keeping the email private, and optionally
      // remembering the details in this browser for next time (the same
      // underlying "wp-comment-cookies-consent" field WordPress already
      // uses for that, so ticking it keeps that convenience working).
      'cookies' => '<p class="comment-form-cookies-consent"><label class="check"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $nb_consent_checked . ' required><span>' . sprintf(
        /* translators: %s: link to the Privacy Policy page */
        esc_html__('I consent to Nirog Bhumi collecting and storing my name, email and this comment, in accordance with the %s, so that it can be published and moderated. My email will not be published, and these details may be remembered in this browser for next time.', 'nirog-bhumi'),
        '<a href="' . esc_url(home_url('/privacy-policy/')) . '" target="_blank" rel="noopener">' . esc_html__('Privacy Policy', 'nirog-bhumi') . '</a>'
      ) . '</span></label></p>',
    ],
  ]);
  ?>
</div>
