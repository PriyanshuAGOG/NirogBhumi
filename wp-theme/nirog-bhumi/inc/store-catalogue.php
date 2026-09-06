<?php
/**
 * Catalogue definition and one-click seeder for the Nirog Bhumi store.
 *
 * The catalogue mirrors the shelves that were previously hard-coded in
 * page-store.php so the launch inventory can be created as real WooCommerce
 * products instead of static markup.
 *
 * Prices carried here are the ones already published on the site. Everything
 * that has never had a public price is seeded as a "coming soon" product:
 * visible, not purchasable, waitlist only. Nothing in this file invents a
 * price, an HSN code or a GST rate.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Product categories, in shelf order.
 */
function nirog_bhumi_store_categories() {
  return [
    'cure-kit' => [
      'name' => __('Cure Kit', 'nirog-bhumi'),
      'description' => __('The complete Nirog Bhumi diabetes reversal kit.', 'nirog-bhumi'),
    ],
    'diabetes-friendly-foods' => [
      'name' => __('Diabetes Friendly Foods', 'nirog-bhumi'),
      'description' => __('Fibre-forward staples and daily rituals built around steady blood sugar.', 'nirog-bhumi'),
    ],
    'cure-kit-essentials' => [
      'name' => __('Cure Kit Essentials', 'nirog-bhumi'),
      'description' => __('Individual naturopathy, cleansing and tracking tools.', 'nirog-bhumi'),
    ],
    'combos' => [
      'name' => __('Combos', 'nirog-bhumi'),
      'description' => __('Bundles that pair tools, food and guidance for fuller support.', 'nirog-bhumi'),
    ],
    'consultations' => [
      'name' => __('Consultations', 'nirog-bhumi'),
      'description' => __('Booking amounts for consultations with the Nirog Bhumi team.', 'nirog-bhumi'),
    ],
  ];
}

/**
 * The launch catalogue.
 *
 * status:
 *   sale        - price known, purchasable once the store is open
 *   coming_soon - visible, not purchasable, waitlist button
 *   enquiry     - visible, not purchasable, links to a consultation or programme page
 */
function nirog_bhumi_store_catalogue() {
  return [
    [
      'sku' => 'NB-KIT-01',
      'slug' => 'diabetes-reversal-kit',
      'name' => __('Diabetes Reversal Kit', 'nirog-bhumi'),
      'category' => 'cure-kit',
      'status' => 'coming_soon',
      'price' => '',
      'virtual' => false,
      'featured' => true,
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Complete bundle', 'nirog-bhumi'),
      'short' => __('The full daily rhythm bundle: cleansing, hydration, food structure, tracking and practice essentials in one box.', 'nirog-bhumi'),
      'description' => __('The Nirog Bhumi Cure Kit gathers the everyday tools our programme participants use most: a steel Jal Neti pot for morning cleansing, a Vijaysar wood tumbler for the overnight water ritual, a printed food rhythm guide, tracking sheets for fasting and post-meal readings, and the yoga and acupressure essentials used in daily practice.', 'nirog-bhumi'),
      'includes' => [
        __('Steel Jal Neti pot', 'nirog-bhumi'),
        __('Vijaysar wood tumbler', 'nirog-bhumi'),
        __('Food rhythm guide', 'nirog-bhumi'),
        __('Tracking sheets for fasting and post-meal readings', 'nirog-bhumi'),
        __('Yoga and acupressure essentials', 'nirog-bhumi'),
      ],
    ],
    [
      'sku' => 'NB-FOOD-01',
      'slug' => 'low-gi-millet-mix',
      'name' => __('Low-GI Millet Mix', 'nirog-bhumi'),
      'category' => 'diabetes-friendly-foods',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Food', 'nirog-bhumi'),
      'short' => __('A fibre-forward base mix for simple breakfasts and early dinners.', 'nirog-bhumi'),
      'description' => __('A blend of whole millets chosen for a slower glucose response than refined wheat or polished rice. Intended as the base of a simple breakfast or an early dinner, alongside vegetables and a protein.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-FOOD-02',
      'slug' => 'roasted-seed-and-nut-mix',
      'name' => __('Roasted Seed & Nut Mix', 'nirog-bhumi'),
      'category' => 'diabetes-friendly-foods',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Food', 'nirog-bhumi'),
      'short' => __('A portion-aware snack option for people replacing refined packaged snacks.', 'nirog-bhumi'),
      'description' => __('Lightly roasted seeds and nuts, portioned so a snack stays a snack. Useful when replacing biscuits, namkeen and other refined packaged snacks in the late afternoon gap.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-FOOD-03',
      'slug' => 'herbal-metabolic-tea',
      'name' => __('Herbal Metabolic Tea', 'nirog-bhumi'),
      'category' => 'diabetes-friendly-foods',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Food', 'nirog-bhumi'),
      'short' => __('A caffeine-light daily ritual designed around digestion and calm cravings.', 'nirog-bhumi'),
      'description' => __('A caffeine-light herbal infusion meant to sit in the place of a third or fourth cup of tea. Used in our programmes as a pause in the day rather than as a treatment.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-FOOD-04',
      'slug' => 'diabetic-plate-starter-pack',
      'name' => __('Diabetic Plate Starter Pack', 'nirog-bhumi'),
      'category' => 'diabetes-friendly-foods',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Food', 'nirog-bhumi'),
      'short' => __('Simple staples and guides to make fibre, protein and meal sequencing easier.', 'nirog-bhumi'),
      'description' => __('A starter set of staples with a printed plate guide, so the first weeks of changing meal structure do not depend on memory. Covers fibre first, protein next, and how to sequence a plate.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-TOOL-01',
      'slug' => 'steel-jal-neti-pot',
      'name' => __('Steel Jal Neti Pot', 'nirog-bhumi'),
      'category' => 'cure-kit-essentials',
      'status' => 'sale',
      'price' => '699',
      'image' => 'jal-neti-pot-opt.jpg',
      'eyebrow' => __('Cleansing / Water', 'nirog-bhumi'),
      'short' => __('Stainless-steel nasal cleansing pot for a clean morning breathwork routine.', 'nirog-bhumi'),
      'description' => __('A stainless-steel nasal cleansing pot for the traditional Jal Neti kriya, designed to support a clean breathing routine before pranayama, yoga or morning practice.', 'nirog-bhumi'),
      'includes' => [
        __('Stainless-steel body with tapered spout', 'nirog-bhumi'),
        __('Easy to rinse, dry and keep hygienic', 'nirog-bhumi'),
        __('Useful for saline nasal irrigation practice', 'nirog-bhumi'),
        __('Pairs with morning breathwork and yoga', 'nirog-bhumi'),
      ],
      'ritual' => __('Use only with sterile, distilled or previously boiled and cooled water mixed with the right saline level. Clean and dry the pot after every use.', 'nirog-bhumi'),
      'caution' => __('Do not use plain tap water. Avoid use during severe nasal infection, ear pain, bleeding or after nasal surgery unless cleared by a clinician.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-TOOL-02',
      'slug' => 'vijaysar-wood-tumbler',
      'name' => __('Vijaysar Wood Tumbler', 'nirog-bhumi'),
      'category' => 'cure-kit-essentials',
      'status' => 'sale',
      'price' => '899',
      'image' => 'vijaysar-tumbler-opt.jpg',
      'eyebrow' => __('Hydration / Wood', 'nirog-bhumi'),
      'short' => __('Traditional Vijaysar tumbler for mindful overnight water rituals.', 'nirog-bhumi'),
      'description' => __('A tumbler turned from Vijaysar (Pterocarpus marsupium) heartwood, used in the traditional overnight water ritual. Water is left to stand in the tumbler through the night and taken in the morning.', 'nirog-bhumi'),
      'includes' => [
        __('Turned from Vijaysar heartwood', 'nirog-bhumi'),
        __('Sized for the overnight water ritual', 'nirog-bhumi'),
        __('Rinse and air-dry between uses', 'nirog-bhumi'),
        __('Replace when the wood stops colouring the water', 'nirog-bhumi'),
      ],
      'ritual' => __('Fill the tumbler at night, let the water stand until morning, and drink it on an empty stomach. Rinse and air-dry the tumbler through the day.', 'nirog-bhumi'),
      'caution' => __('This is a traditional practice, not a substitute for prescribed medication. Do not change or stop any diabetes medication without your doctor. Discontinue and speak to a clinician if you notice any reaction.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-TOOL-03',
      'slug' => 'food-rhythm-tracker',
      'name' => __('Food Rhythm Tracker', 'nirog-bhumi'),
      'category' => 'cure-kit-essentials',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Tracking', 'nirog-bhumi'),
      'short' => __('A practical tracker for meals, fasting sugar, post-meal response and habits.', 'nirog-bhumi'),
      'description' => __('A printed tracker for the four things our programme reviews each week: what was eaten and when, fasting readings, post-meal readings, and the habits around sleep and movement.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-TOOL-04',
      'slug' => 'yoga-starter-mat-guide',
      'name' => __('Yoga Starter Mat Guide', 'nirog-bhumi'),
      'category' => 'cure-kit-essentials',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'yoga-balance-opt.jpg',
      'eyebrow' => __('Practice', 'nirog-bhumi'),
      'short' => __('A small practice companion for daily movement, breath and posture discipline.', 'nirog-bhumi'),
      'description' => __('A compact guide to keep beside the mat, covering the daily sequence, breath counts and the postures we ask participants to hold through the first months.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-COMBO-01',
      'slug' => 'cure-kit-and-food-starter',
      'name' => __('Cure Kit + Food Starter', 'nirog-bhumi'),
      'category' => 'combos',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Kit + Food', 'nirog-bhumi'),
      'short' => __('The cure kit paired with diabetic food staples for the first routine reset.', 'nirog-bhumi'),
      'description' => __('The full cure kit together with the food staples most people need in the first four to six weeks, so tools and meals change at the same time rather than one after the other.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-COMBO-02',
      'slug' => 'food-rhythm-combo',
      'name' => __('Food Rhythm Combo', 'nirog-bhumi'),
      'category' => 'combos',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'articles-food-opt.jpg',
      'eyebrow' => __('Food combo', 'nirog-bhumi'),
      'short' => __('Millet mix, seed mix, herbal tea and plate guide for simple daily structure.', 'nirog-bhumi'),
      'description' => __('The four food items together: millet mix, roasted seed and nut mix, herbal metabolic tea and the plate starter guide.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-COMBO-03',
      'slug' => 'practice-and-pressure-combo',
      'name' => __('Practice & Pressure Combo', 'nirog-bhumi'),
      'category' => 'combos',
      'status' => 'coming_soon',
      'price' => '',
      'image' => 'yoga-twist-opt.jpg',
      'eyebrow' => __('Yoga + Acupressure', 'nirog-bhumi'),
      'short' => __('Yoga practice support with acupressure tools for home discipline.', 'nirog-bhumi'),
      'description' => __('The practice side of the programme in one bundle: the yoga starter guide with the acupressure tools used in daily home practice.', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-PROG-06M',
      'slug' => 'six-month-diabetes-reversal-programme',
      'name' => __('6 Months Diabetes Reversal Program', 'nirog-bhumi'),
      'category' => 'combos',
      'status' => 'enquiry',
      'price' => '',
      'virtual' => true,
      'image' => 'program-diabetes-card.jpg',
      'eyebrow' => __('Flagship programme', 'nirog-bhumi'),
      'short' => __('Our flagship six-month guided programme. Joins after a consultation.', 'nirog-bhumi'),
      'description' => __('The flagship Nirog Bhumi programme runs for six months with structured food rhythm, yoga therapy, naturopathy practice and acupressure, reviewed through the programme. Enrolment begins with a consultation so the plan can be matched to your reports and medication.', 'nirog-bhumi'),
      'enquiry_url' => '/6-month-diabetes-reversal/',
      'enquiry_label' => __('View programme', 'nirog-bhumi'),
    ],
    [
      'sku' => 'NB-PROG-99D',
      'slug' => '99-day-diabetes-programme',
      'name' => __('99 Day Diabetes Program', 'nirog-bhumi'),
      'category' => 'combos',
      'status' => 'enquiry',
      'price' => '',
      'virtual' => true,
      'image' => 'yoga-meditation-opt.jpg',
      'eyebrow' => __('Short programme', 'nirog-bhumi'),
      'short' => __('A shorter guided reset for people who want to begin before committing to six months.', 'nirog-bhumi'),
      'description' => __('A ninety-nine day guided reset covering the same five elements, food rhythm and daily practice as the flagship programme, over a shorter horizon. Enrolment begins with a consultation.', 'nirog-bhumi'),
      'enquiry_url' => '/programmes/',
      'enquiry_label' => __('Enquire', 'nirog-bhumi'),
    ],
    [
      'sku' => 'CONSULT-500',
      'slug' => 'founder-consultation',
      'name' => __('Consultation Booking Amount', 'nirog-bhumi'),
      'category' => 'consultations',
      'status' => 'sale',
      'price' => '500',
      'virtual' => true,
      'sold_individually' => true,
      'catalogue_visibility' => 'hidden',
      'image' => 'consultation-opt.jpg',
      'eyebrow' => __('Consultation', 'nirog-bhumi'),
      'short' => __('Booking amount for an online consultation with the Nirog Bhumi team.', 'nirog-bhumi'),
      'description' => __('The booking amount for a scheduled online consultation. Book through the consultation page so your intake form is linked to the appointment.', 'nirog-bhumi'),
    ],
  ];
}

/**
 * Look up a single catalogue definition by SKU.
 */
function nirog_bhumi_catalogue_entry($sku) {
  foreach (nirog_bhumi_store_catalogue() as $entry) {
    if ($entry['sku'] === $sku) {
      return $entry;
    }
  }
  return null;
}
