<?php
/**
 * One-time seeder for Nirog Bhumi's own education articles.
 *
 * Mirrors the pattern used for the store catalogue (inc/store-seeder.php):
 * idempotent, create-once-by-slug, safe to run on every request. This keeps
 * publishing a reviewed article a code change (reviewable, revertible, no
 * live wp-admin step needed) rather than a one-off manual post that only
 * exists in the database.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * The 21 research/production references for the fibre article, exactly as
 * given in the source document (editorial notes included verbatim).
 */
function nirog_bhumi_fibre_article_references() {
  return [
    ['Chandalia et al. Beneficial effects of high dietary fiber intake in patients with type 2 diabetes mellitus. <em>New England Journal of Medicine</em>, 2000. Small crossover trial, 13 participants; six weeks on each diet. The 50 g research diet is not a universal intake recommendation.', 'https://pubmed.ncbi.nlm.nih.gov/10805824/'],
    ['Gibb et al. Psyllium meta analysis. <em>American Journal of Clinical Nutrition</em>, 2015. Glucose effects varied with baseline control. Included authors affiliated with Procter and Gamble and company clinical records; this commercial involvement belongs in editorial assessment.', 'https://pubmed.ncbi.nlm.nih.gov/26561625/'],
    ['Li et al. High-fiber diet altered gut microbiota, improved glycemic control, and reduced body weight in newly diagnosed type 2 diabetes and obesity. <em>Frontiers in Microbiology</em>, 2026. Single centre, open label, 48 randomised participants in unequal groups; 46 completed 84 days. Dietary guidance, specialised products and lower calorie intake prevent isolation of fibre&rsquo;s independent effect. GLP-1 was not measured. Not all secondary outcomes favoured diet at day 84.', 'https://www.frontiersin.org/journals/microbiology/articles/10.3389/fmicb.2026.1972983/full'],
    ['Reynolds et al. Dietary fibre and whole grains in diabetes management systematic review and meta analyses. <em>PLOS Medicine</em>, 2020. Broad support for metabolic improvements; substantial heterogeneity, limited long term controlled trials. Mortality evidence was observational.', 'https://journals.plos.org/plosmedicine/article?id=10.1371/journal.pmed.1003053'],
    ['Riddle et al. Consensus report on definition and interpretation of remission in type 2 diabetes, 2021. Remission is not a permanent cure and requires continuing follow up.', 'https://pubmed.ncbi.nlm.nih.gov/34460965/'],
    ['Effect of viscous soluble dietary fiber on glucose and lipid metabolism in patients with type 2 diabetes mellitus. <em>Frontiers in Nutrition</em>, 2023. Systematic review; results varied across trials and fibre interventions.', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC10500602/'],
    ['Unravelling the effects of soluble dietary fibre supplementation on energy intake and perceived satiety in healthy adults, 2019. Responses depended on fibre and intervention; findings should not be generalised into guaranteed appetite suppression.', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC6352252/'],
    ['Peng et al. Butyrate and intestinal barrier tight junction assembly. <em>Journal of Nutrition</em>, 2009. Cultured intestinal cell study, not evidence that fibre seals the gut or reverses diabetes in humans.', 'https://pubmed.ncbi.nlm.nih.gov/19625695/'],
    ['Pingitore et al. Propionate and beta cell function in humans and isolated human islets. <em>Diabetes Obesity and Metabolism</em>, 2017. Specialised colonic delivery intervention; mechanistic results do not establish ordinary food effects or remission.', 'https://pubmed.ncbi.nlm.nih.gov/27761989/'],
    ['Chambers et al. Inulin and inulin propionate supplementation and insulin sensitivity. <em>Gut</em>, 2019. Small crossover trial with 42 day interventions; both improved measures compared with cellulose, with no superiority of the propionate intervention over inulin.', 'https://pubmed.ncbi.nlm.nih.gov/30971437/'],
    ['Tolhurst et al. Short chain fatty acids and GLP-1 secretion through FFAR2. <em>Diabetes</em>, 2012. Mechanistic research. Also Zhao et al., <em>Science</em>, 2018, a targeted high fibre dietary study involving acarbose; not evidence of medication free reversal.', 'https://pubmed.ncbi.nlm.nih.gov/22190648/'],
    ['Brain gut communication and vagovagal reflexes, 2021. Background physiology; not evidence of fibre repairing the vagus nerve.', 'https://pubmed.ncbi.nlm.nih.gov/34643086/'],
    ['NIDDK. Insulin resistance and prediabetes. Background explanation of insulin action.', 'https://www.niddk.nih.gov/health-information/diabetes/overview/what-is-diabetes/prediabetes-insulin-resistance'],
    ['NIDDK. Eating diet and nutrition for constipation. Gradual increases and appropriate fluid intake support tolerance.', 'https://www.niddk.nih.gov/health-information/digestive-diseases/constipation/eating-diet-nutrition'],
    ['Jovanovski et al. Psyllium and LDL cholesterol meta analysis. <em>American Journal of Clinical Nutrition</em>, 2018. Lipid outcomes, not direct proof of fewer cardiovascular events.', 'https://pubmed.ncbi.nlm.nih.gov/30239559/'],
    ['USDA National Nutrient Database for Standard Reference Legacy, total dietary fibre. Calculation basis per 100 g: carrot 2.8 g; tomato 1.2 g; whole wheat flour 10.7 g; cooked lentils 7.9 g; cooked cauliflower 2.3 g; guava 5.4 g; almonds 12.5 g; chia 34.4 g. Plain oats are rounded to 4 g per 40 g dry. Five almonds are estimated at 6 g and one teaspoon chia at 4 g. Breakfast 6.2 g; lunch 16.1 g; snack 5.4 g; dinner 8.7 g. Totals use rounded ingredient estimates.', 'https://www.nal.usda.gov/sites/default/files/page-files/Total_Dietary_Fiber.pdf'],
    ['Shukla et al. Food order has a significant impact on postprandial glucose and insulin levels. <em>Diabetes Care</em>, 2015. Eleven participants, fixed visit sequence, two hour observation. Lower insulin alone does not prove improved insulin sensitivity. The text reports 36.7% lower glucose at 60 minutes; this article rounds that to approximately 37%.', 'https://doi.org/10.2337/dc15-0429'],
    ['Efficacy of a meal sequence in patients with type 2 diabetes systematic review and meta analysis, 2022. Low certainty evidence for little or no HbA1c improvement. Acute and long term outcomes must be distinguished.', 'https://drc.bmj.com/content/10/1/e002534'],
    ['MedlinePlus. Psyllium medication information. Follow product specific fluid instructions and pharmacist advice on interactions; do not use dry powder or assume a universal medicine spacing interval.', 'https://medlineplus.gov/druginfo/meds/a601104.html'],
    ['NIDDK. Eating diet and nutrition for gastroparesis. A lower fibre approach may be necessary.', 'https://www.niddk.nih.gov/health-information/digestive-diseases/gastroparesis/eating-diet-nutrition'],
    ['American Diabetes Association. Facilitating positive health behaviours and well being &ndash; Standards of Care in Diabetes 2026. At least 14 g fibre per 1,000 kcal; individualise nutrition and treatment.', 'https://doi.org/10.2337/dc26-S005'],
  ];
}

/**
 * Build the fibre article's full post_content as formatted HTML, matching
 * the structure (headings, tables, lists, quote, infographic) laid out in
 * the source document rather than dropping in a wall of plain text.
 */
function nirog_bhumi_fibre_article_content($image_html) {
  $references_html = '';
  foreach (nirog_bhumi_fibre_article_references() as $i => $ref) {
    [$text, $url] = $ref;
    $n = $i + 1;
    $references_html .= '<li><strong>[' . $n . ']</strong> ' . $text . ' <a href="' . esc_url($url) . '" target="_blank" rel="noopener">View source</a></li>';
  }

  return '
<p><em>Why, How much, Where &amp; When of Fibre</em></p>

<p>The conversation around type 2 diabetes (T2D) is usually about exercise and foods to cut down &ndash; sugar, sweets, large servings of rice and other starchy foods, etc. But how often has someone helped you look at what you could add to your meals? Fibre certainly is one such food component.</p>
<p>If you are working towards reversing T2D, make fibre rich foods an important part of your eating plan. They can help manage the rise in blood sugar after meals, support fullness and improve digestive health.</p>
<p>The good news is that you do not need an expensive supplement for this purpose. Simple plant-based whole foods &ndash; dal, vegetables, whole fruit, whole grains &ndash; can all contribute. Let us look at why fibre consumption matters and how you can very easily reach the recommended daily intake for fibre.</p>

<h2>Why fibre?</h2>

<h3>A gentler rise in blood sugar</h3>
<p>After you eat rice or roti, your body breaks down much of its starch into glucose. That glucose enters your blood. When it arrives quickly, your blood sugar can rise sharply. This puts excessive burden on your digestive organs, including on the pancreas to deliver insulin to deal with the high amounts of glucose. This not only leads to the clich&eacute;d &ldquo;diabetes crash&rdquo; but could overtime weaken these organs due to overuse.</p>
<p>This is where fibre comes in. Certain fibres absorb water and become thick and gel like during digestion. This slows the digestion and absorption of carbohydrates, helping glucose enter the bloodstream more gradually. This puts the digestive organs at ease. Moreover, the body feels energized for a longer duration instead of experiencing the post meal crash.</p>
<p>This does not mean that one can eat unlimited carbohydrate because you added a salad. Portions still matter. But including fibre can help make the meal work better for your blood sugar.</p>

<h3>Feeling satisfied can make healthy eating easier</h3>
<p>Have you ever finished eating and felt hungry again surprisingly soon? Feeling satisfied matters when you are trying to manage your eating habits. Fibre rich foods can be very helpful for this purpose for the following reasons:</p>
<ul>
<li>Some fibres slow the movement of food out of the stomach, leading to what is called &ldquo;slower stomach emptying&rdquo;.</li>
<li>Fibrous foods not only stay longer in the stomach but they also add volume to the food.</li>
<li>Fibrous foods require more chewing. This can in turn lead to multiple benefits:
<ul>
<li>Chewing food well lowers ghrelin the hunger hormone. This can reduce overeating and obesity&mdash;a major driver of diabetes.</li>
<li>Slow chewing releases more essential vitamins and minerals. This is vital for people with diabetes, who are often prone to nutrient deficiencies.</li>
<li>Fast eaters risk insulin resistance. Gulping food forces a sudden glucose surge, making it harder for your body to manage blood sugar.</li>
<li>Savoring your food increases satisfaction. This reduces the urge to reach for unhealthy snacks later.</li>
</ul>
</li>
</ul>
<p>Together, these effects may help you feel comfortably full and make portion size easier to manage. This matters because managing excess body fat can help improve sugar regulation and, for some people, support remission.</p>

<h3>You are feeding your gut bacteria too</h3>
<p>Some of the fibre you eat reaches the large intestine, where bacteria use it as food. As they break it down, they produce small substances called short chain fatty acids (&ldquo;SCFAs&rdquo;). Three main SCFAs are butyrate, propionate and acetate.</p>
<ul>
<li><strong>Butyrate</strong> &ndash; Butyrate helps fuel the cells lining the colon. Laboratory studies show that it can support the gut barrier, the lining that helps control what passes from the intestine into the body. This can prevent harmful substances from leaking into the blood.</li>
<li><strong>Propionate</strong> &ndash; Propionate is being studied for its role in blood sugar regulation, including how the pancreas releases insulin. Research studies have shown that propionate enhances insulin secretion and helps protect insulin-producing cells from damage.</li>
<li><strong>GLP-1 secretion</strong> &ndash; These SCFAs also encourage the gut to release GLP-1, a hormone involved in appetite regulation and insulin release when glucose is elevated.</li>
<li><strong>Anti-inflammatory effect</strong> &ndash; These SCFAs also help keep inflammation in check, which is now being increasingly linked to many chronic diseases, including diabetes.</li>
<li><strong>Gut brain connection</strong> &ndash; The gut also communicates with the brain through hormones and nerves, helping regulate appetite and metabolism. A healthy community of gut bacteria supports this communication. Eating a variety of fibre-rich foods helps nourish these bacteria and the useful SCFAs that they produce.</li>
</ul>

<h3>Benefits beyond your glucose reading</h3>
<p>Fibre helps add bulk to stool and supports regular bowel movements. This can once again help maintain a healthy gut leading to all the benefits mentioned above.</p>
<p>Certain gel forming fibres can also lower LDL cholesterol (the bad cholesterol) by helping the body lose more bile acids through the stool.</p>
<p>Clearly, the importance of fibre cannot be overstated when it comes to diabetes reversal.</p>

<h2>How much &amp; from where?</h2>
<p>The American Diabetes Association recommends at least 14 grams of fibre per 1,000 calories, or 28 grams on a 2,000 calorie diet. A range of roughly 25 to 35 grams is useful for illustrating everyday intake.</p>
<p>Rather than imagining a mountain of salad, let us see the sources from where fibre can be consumed. We have given below a day with usual food intake, just to show how easy it could be to reach the daily target of fibre. All figures below are approximate.</p>

<h3>Breakfast &ndash; Oatmeal with a few additions</h3>
<table>
<thead><tr><th>Breakfast</th><th>Amount</th><th>Fibre</th></tr></thead>
<tbody>
<tr><td>Plain oats</td><td>40 g dry then cooked</td><td>4 g</td></tr>
<tr><td>Almonds</td><td>5 almonds, approximately 6 g</td><td>0.8 g</td></tr>
<tr><td>Chia seeds</td><td>1 teaspoon, approximately 4 g</td><td>1.4 g</td></tr>
<tr><td><strong>Breakfast total</strong></td><td></td><td><strong>6.2 g</strong></td></tr>
</tbody>
</table>
<p>That is around six grams before you have even thought about lunch.</p>

<h3>Lunch &ndash; A familiar Indian plate</h3>
<table>
<thead><tr><th>Lunch</th><th>Amount</th><th>Fibre</th></tr></thead>
<tbody>
<tr><td>Raw carrot</td><td>100 g</td><td>2.8 g</td></tr>
<tr><td>Raw tomato</td><td>50 g</td><td>0.6 g</td></tr>
<tr><td>Whole wheat rotis</td><td>2, made with 60 g flour in total</td><td>6.4 g</td></tr>
<tr><td>Dal (lentils)</td><td>Small katori containing 50 g cooked lentils plus water</td><td>4 g</td></tr>
<tr><td>Cauliflower sabzi</td><td>Portion containing 100 g cooked cauliflower</td><td>2.3 g</td></tr>
<tr><td><strong>Lunch total</strong></td><td></td><td><strong>16.1 g</strong></td></tr>
</tbody>
</table>
<p>Your running total is now about 22 grams. Notice the dal measurement: it counts the lentils, not the water. A larger katori of very thin dal will not necessarily provide more fibre. Different pulses and recipes will give different amounts of fibre.</p>

<h3>Snack &ndash; whole guava</h3>
<table>
<thead><tr><th>Snack</th><th>Amount</th><th>Fibre</th></tr></thead>
<tbody>
<tr><td>Whole guava</td><td>100 g edible portion</td><td>5.4 g</td></tr>
<tr><td><strong>Snack total</strong></td><td></td><td><strong>5.4 g</strong></td></tr>
</tbody>
</table>
<p>This takes the running total to approximately 28 grams.</p>

<h3>Dinner</h3>
<table>
<thead><tr><th>Dinner</th><th>Amount</th><th>Fibre</th></tr></thead>
<tbody>
<tr><td>Whole wheat rotis</td><td>2, made with 60 g flour in total</td><td>6.4 g</td></tr>
<tr><td>Cauliflower sabzi</td><td>Portion containing 100 g cooked cauliflower</td><td>2.3 g</td></tr>
<tr><td><strong>Dinner total</strong></td><td></td><td><strong>8.7 g</strong></td></tr>
</tbody>
</table>
<p>We have repeated cauliflower to keep the example easy to follow. Rotate vegetables for variety; their fibre counts will differ.</p>

<table>
<thead><tr><th>Meal</th><th>Approximate fibre</th></tr></thead>
<tbody>
<tr><td>Breakfast</td><td>6.2 g</td></tr>
<tr><td>Lunch</td><td>16.1 g</td></tr>
<tr><td>Guava snack</td><td>5.4 g</td></tr>
<tr><td>Dinner</td><td>8.7 g</td></tr>
<tr><td><strong>Daily total</strong></td><td><strong>36.4 g</strong></td></tr>
</tbody>
</table>
<p>The above example illustrates how one can easily reach the daily required quantity of fibre. Roti size, food variety and preparation will change the figures. Still, it shows how ordinary foods can bring a useful fibre intake within reach.</p>
<p><em>Please note that through the above example, we are not prescribing diet for people with T2D.</em></p>

<h2>When?</h2>
<p>Space out consumption of fibre through the day. However, it may be more beneficial to start the main meals with fibrous foods. Research studies have found beneficial effects of meal sequencing &ndash; eating your meal in a certain sequence.</p>
<p>As per a study titled &ldquo;Food Order Has a Significant Impact on Postprandial Glucose and Insulin Levels&rdquo; published in the journal &ldquo;Diabetes Care&rdquo; by the American Diabetes Association, there was a much better glucose and insulin response when proteins and vegetables were consumed before carbohydrates. This is shown below.</p>
' . $image_html . '
<p>The crux of the study results can be quoted in one sentence from the study:</p>
<blockquote><p>&ldquo;The magnitude of the effect of food order on glucose levels is comparable to that observed with pharmacological agents that preferentially target postprandial glucose.&rdquo;</p></blockquote>
<p>In simple words, the above means that consuming food in the order given above has been observed to have the same effect as medication for reducing blood sugar.</p>

<h2>So is fibre indeed a superfood?</h2>
<p>Fibre undoubtedly deserves an important place in your meals. It can help slow glucose absorption, support fullness, keep bowel movements regular and nourish helpful gut bacteria. Certain fibres also help lower LDL cholesterol (bad cholesterol), making fibre-rich foods valuable when managing T2D and working towards remission.</p>
<p>But these benefits do not give fibre a godlike status. &ldquo;Superfood&rdquo; is a marketing term, not a scientific term, and no single food or nutrient can meet all our needs. More is not always better either &ndash; increasing fibre too quickly or excessive consumption of fibre can cause bloating and discomfort. What matters is a balanced, varied diet with enough fibre alongside the other nutrients our body needs.</p>

<h2>When more fibre may not be better</h2>
<p>Fibre is beneficial for most people, but some digestive conditions call for a different approach.</p>
<ul>
<li><strong>Gastroparesis:</strong> When the stomach empties too slowly, large amounts of fibre can worsen symptoms. Smaller meals and a lower-fibre diet may be recommended.</li>
<li><strong>Crohn&rsquo;s disease or ulcerative colitis:</strong> Some people tolerate raw vegetables, skins, seeds and other rough fibres poorly. Temporarily changing the amount of fibre may help.</li>
<li><strong>Stomach or duodenal ulcers:</strong> Follow your treatment plan and adjust foods that worsen your individual symptoms.</li>
</ul>
<p>Even without a digestive condition, increase fibre gradually. A sudden increase can cause gas, bloating, constipation or discomfort. Along with this, drink sufficient fluids. If higher fibre consumption causes persistent pain, bloating or worsening constipation, seek advice from your dietitian / physician.</p>

<h2>Conclusion</h2>
<p>Keep taking prescribed treatment, and arrange a medication review if dietary changes substantially improve your glucose readings.</p>
<p>At your next meal, look for one manageable addition: salads, another vegetable or a whole fruit. You do not need a perfect plate on Day 1. You need a manageable and sustainable pattern you can keep building upon.</p>

<h2>References</h2>
<p>These references support this article. Food figures are rounded estimates; they do not establish a personalised dietary prescription.</p>
<ul class="nb-reference-list">' . $references_html . '</ul>
';
}

/**
 * Create the fibre article once, idempotently, by slug - safe to run on
 * every request. Never overwrites it afterwards, so an editorial change
 * made by hand in wp-admin later is never clobbered by this file.
 */
function nirog_bhumi_seed_fibre_article() {
  if (!post_type_exists('nb_education_post')) {
    return;
  }
  $slug = 'is-fibre-the-superfood-for-reversing-type-2-diabetes';
  if (get_page_by_path($slug, OBJECT, 'nb_education_post')) {
    return;
  }

  $image_html = '';
  $attachment_id = function_exists('nirog_bhumi_import_theme_image') ? nirog_bhumi_import_theme_image('fibre-glucose-insulin-response.png') : 0;
  if ($attachment_id) {
    $image_url = wp_get_attachment_image_url($attachment_id, 'large');
    if ($image_url) {
      $image_html = '<figure><img src="' . esc_url($image_url) . '" alt="' . esc_attr__('Chart showing lower glucose and insulin response when protein and vegetables are eaten before carbohydrates', 'nirog-bhumi') . '" loading="lazy"><figcaption>' . esc_html__('Glucose and insulin response by food order. Source: Shukla et al., Diabetes Care, 2015 [17].', 'nirog-bhumi') . '</figcaption></figure>';
    }
  }

  // This is trusted, code-authored content (tables, figure/figcaption,
  // target="_blank" links), not untrusted user input - insert it without
  // running it through the post-content KSES filters, which would strip
  // attributes on an anonymous front-end request (no unfiltered_html cap).
  if (function_exists('kses_remove_filters')) {
    kses_remove_filters();
  }
  $post_id = wp_insert_post([
    'post_type' => 'nb_education_post',
    'post_status' => 'publish',
    'post_title' => __('Is Fibre the "Superfood" for Reversing Type 2 Diabetes?', 'nirog-bhumi'),
    'post_name' => $slug,
    'post_excerpt' => __('Fibre does not need an expensive supplement. Simple plant-based whole foods can help you reach your daily fibre target and support a gentler blood sugar response. Why fibre matters, how much you need, where to get it, and when to eat it.', 'nirog-bhumi'),
    'post_content' => nirog_bhumi_fibre_article_content($image_html),
  ], true);
  if (function_exists('kses_init_filters')) {
    kses_init_filters();
  }

  if (is_wp_error($post_id) || !$post_id) {
    return;
  }

  wp_set_post_terms($post_id, ['Food'], 'nb_education_topic');

  if ($attachment_id) {
    set_post_thumbnail($post_id, $attachment_id);
  }
}
add_action('init', 'nirog_bhumi_seed_fibre_article', 31);
