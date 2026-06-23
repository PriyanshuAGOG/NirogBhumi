<?php
get_header();
?>
<main>
  <section class="legal-split legal-intro-only">
    <div>
      <p class="eyebrow">Education Articles</p>
      <h1>All Nirog Bhumi education articles.</h1>
      <p>Evidence-informed writing focused on diabetes reversal, metabolic health, routines and long-term behaviour change.</p>
    </div>
  </section>

  <section class="consult-cards" data-nb-article-grid>
    <?php if (have_posts()) :
      while (have_posts()) : the_post();
        $topics = get_the_terms(get_the_ID(), 'nb_education_topic');
        $topic_names = [];
        if ($topics && !is_wp_error($topics)) {
          foreach ($topics as $topic) {
            $topic_names[] = $topic->name;
          }
        }
    ?>
    <article>
      <small><?php echo esc_html(get_the_date('d M Y')); ?><?php if ($topic_names) : ?> | <?php echo esc_html(implode(', ', $topic_names)); ?><?php endif; ?></small>
      <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
      <p><?php echo esc_html(get_the_excerpt() ?: wp_trim_words(wp_strip_all_tags(get_the_content()), 26)); ?></p>
      <a class="pill ghost" href="<?php the_permalink(); ?>">Read article</a>
    </article>
    <?php
      endwhile;
    else : ?>
    <article>
      <h2>No articles yet.</h2>
      <p>Publish your first post from WordPress Admin under Education Articles.</p>
    </article>
    <?php endif; ?>
  </section>
</main>
<?php get_footer();
