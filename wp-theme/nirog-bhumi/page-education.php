<?php
/**
 * Static Nirog Bhumi template generated from education/index.html.
 *
 * Published "Education Articles" (the nb_education_post type) are shown inside
 * the four education categories below, separated from the curated external
 * articles so it is always clear which writing is Nirog Bhumi's own and which
 * is third-party reference material. Assign each article one of the four
 * Education Topics in WordPress admin and it appears under the matching
 * category, in the "From Nirog Bhumi" group, automatically.
 */
$nb_education_posts = get_posts([
  'post_type' => 'nb_education_post',
  'post_status' => 'publish',
  'posts_per_page' => -1,
  'orderby' => 'date',
  'order' => 'DESC',
]);

// Group published articles by their education topic so each vertical can render
// the ones that belong to it. Matching uses a normalised key so it is robust to
// casing and HTML-encoded ampersands. Articles without a recognised topic fall
// back to the first category so they are never lost.
$nb_topics = function_exists('nirog_bhumi_education_topics') ? nirog_bhumi_education_topics() : [];
$nb_fallback_key = $nb_topics ? nirog_bhumi_education_topic_key($nb_topics[0]) : nirog_bhumi_education_topic_key('Miscellaneous');
$nb_education_groups = [];
foreach ($nb_education_posts as $nb_post) {
  $names = wp_get_post_terms($nb_post->ID, 'nb_education_topic', ['fields' => 'names']);
  if (is_wp_error($names) || !$names) {
    $names = [];
  }
  $keys = [];
  foreach ($names as $name) {
    $keys[] = nirog_bhumi_education_topic_key($name);
  }
  $keys = array_filter(array_unique($keys));
  if (!$keys) {
    $keys = [$nb_fallback_key];
  }
  foreach ($keys as $key) {
    $nb_education_groups[$key][] = $nb_post;
  }
}

// 16 curated external references + however many Nirog Bhumi articles are live.
$nb_reference_count = 16 + count($nb_education_posts);

$nb_education_schema = [];
foreach ($nb_education_posts as $nb_post) {
  $nb_education_schema[] = [
    '@type' => 'BlogPosting',
    'headline' => get_the_title($nb_post),
    'url' => get_permalink($nb_post),
    'datePublished' => get_the_date('c', $nb_post),
    'dateModified' => get_the_modified_date('c', $nb_post),
    'description' => wp_strip_all_tags(get_the_excerpt($nb_post)),
    'author' => [
      '@type' => 'Person',
      'name' => get_the_author_meta('display_name', (int) $nb_post->post_author),
    ],
    'publisher' => [
      '@type' => 'Organization',
      'name' => 'Nirog Bhumi',
    ],
  ];
}

get_header(); ?>
<main>
<?php if (!empty($nb_education_schema)) : ?>
<script type="application/ld+json"><?php echo wp_json_encode(['@context' => 'https://schema.org', '@type' => 'Blog', 'name' => 'Nirog Bhumi Education Articles', 'blogPost' => $nb_education_schema], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<?php endif; ?>
<section class="education-guide first" data-read-more-root><p class="eyebrow">How to make the best use of this library</p><h1>Use these articles as learning resources, not personalised medical advice.</h1><p>The articles linked on this page have been carefully selected and curated by Nirog Bhumi to help readers understand how lifestyle, nutrition, movement, sleep, stress reduction, weight management, and other health changes may support better metabolic health and, in turn, lead to Type 2 diabetes reversal.</p><p>Each category below separates our own written articles from third-party articles we have curated. External articles are shared for educational and reference purposes only. Nirog Bhumi does not claim ownership over any third-party articles, images, videos, graphics, or media content. All rights belong to the respective publishers, authors, and media organisations. Nirog Bhumi is not responsible for their content, accuracy, policies, advertisements, future changes, or availability. Some articles may be behind a paywall.</p><p>While Nirog Bhumi generally agrees with the broad viewpoint expressed in these curated articles, especially where they support lifestyle-based improvement in Type 2 diabetes, we may not agree with every recommendation, statement, interpretation, or dietary suggestion made in them. For example, an article may mention eggs or other animal-based foods as part of a healthy diet, whereas Nirog Bhumi promotes a whole-food, plant-based dietary approach for diabetes reversal.</p><div class="read-more-content" data-read-more-content><p>Readers are encouraged to use these articles as learning resources, not as personalised medical advice. Please consult your physician, diabetologist, or qualified healthcare practitioner before acting upon anything mentioned in these articles, especially before making changes to your diet, medicines, supplements, herbs, fasting routine, exercise, or diabetes management plan.</p><p>Nirog Bhumi shall not be held responsible for any action taken by readers based on the content contained in these articles. Any reliance on such content is entirely at the reader discretion and risk.</p></div><button class="read-more-toggle" type="button" data-read-more-toggle>More...</button></section>
<section class="education-search">
  <label>Search education<input data-education-search type="search" placeholder="Search fibre, yoga, sleep, chewing, food order..."></label>
  <p data-education-count><?php echo esc_html($nb_reference_count); ?> references available</p>
</section>
<section class="vertical-map"><a href="#vertical-1"><span>01</span><strong>Movement, Exercise &amp; Yoga</strong><small>Walking, yoga, daily movement</small></a><a href="#vertical-2"><span>02</span><strong>Lifestyle</strong><small>Sleep, stress, digestion</small></a><a href="#vertical-3"><span>03</span><strong>Food</strong><small>What, how much &amp; when to eat</small></a><a href="#vertical-4"><span>04</span><strong>Miscellaneous</strong><small>General health context</small></a></section>

<section class="education-vertical" id="vertical-1" data-edu-section>
  <div class="vertical-intro"><span>01</span><h2>Movement, Exercise &amp; Yoga</h2><p>Walking, yoga, simple seated movements and post-meal activity that can be easily practised in daily life.</p><small>Why frequent, less intensive movement often matters more than occasional intense exercise.</small></div>
  <div class="edu-group">
  <?php $nb_own_1 = $nb_education_groups[nirog_bhumi_education_topic_key('Movement, Exercise & Yoga')] ?? []; if ($nb_own_1) : ?>
    <p class="edu-source-label nb">From Nirog Bhumi</p>
    <div class="edu-grid"><?php echo nirog_bhumi_education_cards_html($nb_own_1, 0); ?></div>
  <?php endif; ?>
    <p class="edu-source-label other">From other sources</p>
    <div class="edu-grid"><article class="edu-card" data-edu-card data-search="movement, exercise & yoga walking, yoga, daily movement researcher discovers a muscle that can promote glucose and fat burning while sitting medical xpress introduces the idea of soleus push-ups and why light muscular activity can be metabolically meaningful.">
    <div><span>01</span><small>Medical Xpress</small></div>
    <h3>Researcher discovers a muscle that can promote glucose and fat burning while sitting</h3>
    <p>Introduces the idea of soleus push-ups and why light muscular activity can be metabolically meaningful.</p>
    <a href="https://medicalxpress.com/news/2022-09-muscle-glucose-fat-fuel-metabolism.html" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="movement, exercise & yoga walking, yoga, daily movement just move your heels: a 3-min trick may reduce post-meal sugar spike times of india a simple entry point for readers who need a low-friction post-meal movement habit.">
    <div><span>02</span><small>Times of India</small></div>
    <h3>Just move your heels: a 3-min trick may reduce post-meal sugar spike</h3>
    <p>A simple entry point for readers who need a low-friction post-meal movement habit.</p>
    <a href="https://timesofindia.indiatimes.com/india/just-move-your-heels-a-3-min-trick-may-reduce-post-meal-sugar-spike/articleshow/128284378.cms" target="_blank" rel="noopener">Read article</a>
  </article></div>
  </div>
</section>

<section class="education-vertical" id="vertical-2" data-edu-section>
  <div class="vertical-intro"><span>02</span><h2>Lifestyle</h2><p>Blood sugar is also shaped by sleep, stress, digestion, emotional eating and whether progress is being tracked. This vertical connects meditation, sleep rhythm and daily tracking.</p><small>Learn how sleep rhythm, stress management, digestion, and calm tracking can improve metabolic progress.</small></div>
  <div class="edu-group">
  <?php $nb_own_2 = $nb_education_groups[nirog_bhumi_education_topic_key('Lifestyle')] ?? []; if ($nb_own_2) : ?>
    <p class="edu-source-label nb">From Nirog Bhumi</p>
    <div class="edu-grid"><?php echo nirog_bhumi_education_cards_html($nb_own_2, 0); ?></div>
  <?php endif; ?>
    <p class="edu-source-label other">From other sources</p>
    <div class="edu-grid"><article class="edu-card" data-edu-card data-search="lifestyle sleep, stress, digestion sleep is more important for longevity than diet, exercise, social ties, study says medical news today a readable reference for why sleep rhythm deserves serious attention in metabolic recovery.">
    <div><span>01</span><small>Medical News Today</small></div>
    <h3>Sleep is more important for longevity than diet, exercise, social ties, study says</h3>
    <p>A readable reference for why sleep rhythm deserves serious attention in metabolic recovery.</p>
    <a href="https://www.medicalnewstoday.com/articles/sleep-is-more-important-for-longevity-than-diet-exercise-social-ties" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="lifestyle sleep, stress, digestion mindfulness may be as effective as antidepressant for anxiety symptoms medical news today a useful article for understanding mindfulness as a serious stress-regulation practice.">
    <div><span>02</span><small>Medical News Today</small></div>
    <h3>Mindfulness may be as effective as antidepressant for anxiety symptoms</h3>
    <p>A useful article for understanding mindfulness as a serious stress-regulation practice.</p>
    <a href="https://www.medicalnewstoday.com/articles/mindfulness-may-be-as-effective-as-antidepressant-relieving-anxiety-symptoms" target="_blank" rel="noopener">Read article</a>
  </article></div>
  </div>
</section>

<section class="education-vertical" id="vertical-3" data-edu-section>
  <div class="vertical-intro"><span>03</span><h2>Food</h2><p>What and how you eat shapes inflammation, satiety, gut rhythm and glucose stability. This vertical covers fibre, whole foods, meal sequencing, timing, and what to avoid.</p><small>Use this when building grocery lists, meal plans and daily eating habits.</small></div>
  <div class="edu-group">
  <?php $nb_own_3 = $nb_education_groups[nirog_bhumi_education_topic_key('Food')] ?? []; if ($nb_own_3) : ?>
    <p class="edu-source-label nb">From Nirog Bhumi</p>
    <div class="edu-grid"><?php echo nirog_bhumi_education_cards_html($nb_own_3, 0); ?></div>
  <?php endif; ?>
    <p class="edu-source-label other">From other sources</p>
    <div class="edu-grid"><article class="edu-card" data-edu-card data-search="food what, how much & when to eat how slow chewing can help prevent diabetes and other health issues times of india eating slowly helps the body feel full sooner, digest better, and reduce unnecessary glucose spikes.">
    <div><span>01</span><small>Times of India</small></div>
    <h3>How slow chewing can help prevent diabetes and other health issues</h3>
    <p>Eating slowly helps the body feel full sooner, digest better, and reduce unnecessary glucose spikes.</p>
    <a href="https://timesofindia.indiatimes.com/life-style/health-fitness/health-news/how-slow-chewing-can-help-prevent-diabetes-and-other-health-issues/articleshow/112176800.cms" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat obesity and the fastness of food the new york times data on the link between eating speed and weight gain risk.">
    <div><span>02</span><small>The New York Times</small></div>
    <h3>Obesity and the fastness of food</h3>
    <p>Data on the link between eating speed and weight gain risk.</p>
    <a href="https://archive.nytimes.com/economix.blogs.nytimes.com/2009/05/05/obesity-and-the-fastness-of-food/" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat vegetables before carbs and food sequence the new york times how eating vegetables first can slow glucose spikes and make everyday meals more blood-sugar friendly.">
    <div><span>03</span><small>The New York Times</small></div>
    <h3>Vegetables before carbs and food sequence</h3>
    <p>How eating vegetables first can slow glucose spikes and make everyday meals more blood-sugar friendly.</p>
    <a href="https://www.nytimes.com/2024/04/02/well/eat/vegetables-carbs-food-sequence.html" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat food order has a significant impact on postprandial glucose and insulin levels american diabetes association clinical reference for sequencing vegetables/protein before carbohydrate.">
    <div><span>04</span><small>American Diabetes Association</small></div>
    <h3>Food order has a significant impact on postprandial glucose and insulin levels</h3>
    <p>Clinical reference for sequencing vegetables/protein before carbohydrate.</p>
    <a href="https://diabetesjournals.org/care/article/38/7/e98/30914/Food-Order-Has-a-Significant-Impact-on" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat is it bad to eat late at night? the new york times useful for explaining late eating, circadian rhythm and metabolic strain.">
    <div><span>05</span><small>The New York Times</small></div>
    <h3>Is it bad to eat late at night?</h3>
    <p>Useful for explaining late eating, circadian rhythm and metabolic strain.</p>
    <a href="https://www.nytimes.com/2023/12/19/well/eat/eating-late-night-health.html" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat time-restricted eating and early eating windows medical news today why earlier eating windows may better support metabolism.">
    <div><span>06</span><small>Medical News Today</small></div>
    <h3>Time-restricted eating and early eating windows</h3>
    <p>Why earlier eating windows may better support metabolism.</p>
    <a href="https://www.medicalnewstoday.com/articles/time-restricted-eating-early-window-best-metabolism" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat the foods that fight inflammation the new york times a list of anti-inflammatory foods.">
    <div><span>07</span><small>The New York Times</small></div>
    <h3>The foods that fight inflammation</h3>
    <p>A list of anti-inflammatory foods.</p>
    <a href="https://www.nytimes.com/2024/09/18/well/eat/inflammation-food-diet.html" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat low-carb diet may reduce need for drugs in type 2 diabetes medical news today enhance beta-cell function by adopting a low-carb diet.">
    <div><span>08</span><small>Medical News Today</small></div>
    <h3>Low-carb diet may reduce need for drugs in type 2 diabetes</h3>
    <p>Enhance beta-cell function by adopting a low-carb diet.</p>
    <a href="https://www.medicalnewstoday.com/articles/low-carb-diet-may-eliminate-need-for-drugs-in-type-2-diabetes" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat food preservatives linked to type 2 diabetes and cancer, studies warn medical news today a cautionary overview of additives and packaged-foods.">
    <div><span>09</span><small>Medical News Today</small></div>
    <h3>Food preservatives linked to type 2 diabetes and cancer, studies warn</h3>
    <p>A cautionary overview of additives and packaged-foods.</p>
    <a href="https://www.medicalnewstoday.com/articles/food-preservatives-linked-to-type-2-diabetes-and-cancer-studies-warn" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat ultra-processed foods: a global threat to public health global food research program a detailed study for understanding multiple aspects related to ultra-processed foods.">
    <div><span>10</span><small>Global Food Research Program</small></div>
    <h3>Ultra-processed foods: a global threat to public health</h3>
    <p>A detailed study for understanding multiple aspects related to ultra-processed foods.</p>
    <a href="https://www.globalfoodresearchprogram.org/wp-content/uploads/2023/11/GFRP_FactSheet_UltraProcessedFoods_2023_11.pdf" target="_blank" rel="noopener">Read article</a>
  </article><article class="edu-card" data-edu-card data-search="food what, how much & when to eat are sugar substitutes healthier than the real thing? the economist the bitter truth of sugar substitutes.">
    <div><span>11</span><small>The Economist</small></div>
    <h3>Are sugar substitutes healthier than the real thing?</h3>
    <p>The bitter truth of sugar substitutes.</p>
    <a href="https://www.economist.com/science-and-technology/2026/04/10/are-sugar-substitutes-healthier-than-the-real-thing" target="_blank" rel="noopener">Read article</a>
  </article></div>
  </div>
</section>

<section class="education-vertical" id="vertical-4" data-edu-section>
  <div class="vertical-intro"><span>04</span><h2>Miscellaneous</h2><p>General health and fitness context that does not sit neatly inside movement, lifestyle or food, but is still useful background.</p><small>A useful starting point for reframing fitness and health more broadly.</small></div>
  <div class="edu-group">
  <?php $nb_own_4 = $nb_education_groups[nirog_bhumi_education_topic_key('Miscellaneous')] ?? []; if ($nb_own_4) : ?>
    <p class="edu-source-label nb">From Nirog Bhumi</p>
    <div class="edu-grid"><?php echo nirog_bhumi_education_cards_html($nb_own_4, 0); ?></div>
  <?php endif; ?>
    <p class="edu-source-label other">From other sources</p>
    <div class="edu-grid"><article class="edu-card" data-edu-card data-search="miscellaneous general health context bollywood template of big biceps and 6-pack is not fitness times of india a useful article for reframing fitness around functional health and sustainable living.">
    <div><span>01</span><small>Times of India</small></div>
    <h3>Bollywood template of big biceps and 6-pack is not fitness</h3>
    <p>A useful article for reframing fitness around functional health and sustainable living.</p>
    <a href="https://timesofindia.indiatimes.com/life-style/health-plus/bollywood-template-of-big-biceps-6-pack-isnt-fitness-living-healthy-is/articleshow/129633763.cms" target="_blank" rel="noopener">Read article</a>
  </article></div>
  </div>
</section>

<?php get_template_part('template-parts/store-promo-strip'); ?>
</main>
<?php get_footer(); ?>
