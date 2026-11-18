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
  comment_form([
    'title_reply' => __('Leave a comment', 'nirog-bhumi'),
    'title_reply_to' => __('Leave a reply to %s', 'nirog-bhumi'),
    'label_submit' => __('Post Comment', 'nirog-bhumi'),
    'class_form' => 'nb-comment-form',
    'class_submit' => 'pill primary',
    'comment_field' => '<p class="comment-form-comment"><label for="comment">' . esc_html__('Comment', 'nirog-bhumi') . ' <span class="required">*</span></label><textarea id="comment" name="comment" rows="6" required></textarea></p>',
  ]);
  ?>
</div>
