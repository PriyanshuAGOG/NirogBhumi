<?php
get_header();
if (have_posts()) :
  while (have_posts()) : the_post();
    $topics = get_the_terms(get_the_ID(), 'nb_education_topic');
    $topic_names = [];
    if ($topics && !is_wp_error($topics)) {
      foreach ($topics as $topic) {
        $topic_names[] = $topic->name;
      }
    }
    $schema = [
      '@context' => 'https://schema.org',
      '@type' => 'BlogPosting',
      'headline' => get_the_title(),
      'datePublished' => get_the_date('c'),
      'dateModified' => get_the_modified_date('c'),
      'author' => [
        '@type' => 'Person',
        'name' => get_the_author(),
      ],
      'publisher' => [
        '@type' => 'Organization',
        'name' => 'Nirog Bhumi',
      ],
      'url' => get_permalink(),
      'articleSection' => $topic_names,
      'description' => wp_strip_all_tags(get_the_excerpt() ?: wp_trim_words(wp_strip_all_tags(get_the_content()), 28)),
    ];
?>
<main>
  <script type="application/ld+json"><?php echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
  <section class="legal-split legal-intro-only">
    <div>
      <p class="eyebrow">Nirog Bhumi Article</p>
      <h1><?php the_title(); ?></h1>
      <p>Published on <?php echo esc_html(get_the_date('d M Y')); ?><?php if ($topic_names) : ?> in <?php echo esc_html(implode(', ', $topic_names)); ?><?php endif; ?>.</p>
    </div>
  </section>

  <section class="nb-article-section">
    <div class="nb-article-body">
      <?php the_content(); ?>
    </div>
  </section>

  <section class="consult-cards">
    <article>
      <h2>Continue learning</h2>
      <p>Read more practical guidance in the Education hub and apply one actionable step each week.</p>
      <a class="pill ghost" href="<?php echo esc_url(home_url('/education/')); ?>">Back to Education</a>
    </article>
  </section>
</main>
<?php
  endwhile;
endif;
get_footer();
