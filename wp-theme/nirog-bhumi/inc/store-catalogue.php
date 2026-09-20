<?php
/**
 * Catalogue definition and one-click seeder for the Nirog Bhumi store.
 *
 * The launch catalogue is the five wellness accessories published in the
 * "Nirog Bhumi Wellness Products - Website Information Pack" (Sep 2026):
 * Jal Neti Lota, Vijaysar Wooden Tumbler, Hot & Cold Waist Pack, Acupressure
 * Jimmy and Acupressure Mat. Prices are the GST-inclusive customer price
 * from that pack; HSN and GST rate are set per product from the same
 * source so the tax invoice is correct without guessing.
 *
 * Only Jal Neti Lota and Vijaysar Wooden Tumbler have their own photography
 * (assets/img/jal-neti-pot-opt.jpg, assets/img/vijaysar-tumbler-opt.jpg).
 * The other three use the general store photo as a placeholder until real
 * product photos are supplied - nothing here invents or fetches an image.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Product categories, in shelf order.
 */
function nirog_bhumi_store_categories() {
  return [
    // Slug kept as diabetes-friendly-foods so a site that already seeded
    // this term keeps its existing product links; only the display name
    // and description change. No product currently sits in this category -
    // it stays defined and simply stays hidden until a food product exists.
    'diabetes-friendly-foods' => [
      'name' => __('Nutrition', 'nirog-bhumi'),
      'description' => __('Fibre-forward staples and daily rituals built around steady blood sugar.', 'nirog-bhumi'),
    ],
    // Slug kept as cure-kit-essentials for the same reason.
    'cure-kit-essentials' => [
      'name' => __('Lifestyle', 'nirog-bhumi'),
      'description' => __('Naturopathy, cleansing and hydrotherapy tools for a daily home routine.', 'nirog-bhumi'),
    ],
    'acupressure' => [
      'name' => __('Acupressure', 'nirog-bhumi'),
      'description' => __('Pressure-point tools for daily self-practice.', 'nirog-bhumi'),
    ],
  ];
}

/**
 * Build the multi-paragraph description plus the "what's included" list
 * shown on the product page.
 */
function nirog_bhumi_catalogue_description($entry) {
  $html = '';
  $paragraphs = is_array($entry['description']) ? $entry['description'] : [$entry['description']];
  foreach ($paragraphs as $paragraph) {
    $html .= '<p>' . esc_html($paragraph) . '</p>';
  }
  if (!empty($entry['includes'])) {
    $html .= '<ul>';
    foreach ($entry['includes'] as $item) {
      $html .= '<li>' . esc_html($item) . '</li>';
    }
    $html .= '</ul>';
  }
  return $html;
}

/**
 * The launch catalogue.
 *
 * status:
 *   sale        - price known, purchasable once the store is open
 *   coming_soon - visible, not purchasable, waitlist button
 *   enquiry     - visible, not purchasable, links to a consultation or programme page
 *
 * ritual       - "How to use", one numbered step per array entry
 * caution      - "Precautions", one bullet per array entry
 * benefits     - "Benefits", one bullet per array entry
 * diabetes_context - paragraph shown under "Diabetes wellness context"
 * disclosure   - the product-specific "Wellness disclosure" paragraph,
 *                separate from the generic disclaimer shown on every page
 * hsn / gst_rate - printed on the tax invoice for this line item
 */
function nirog_bhumi_store_catalogue() {
  return [
    [
      'sku' => 'NB-TOOL-01',
      'slug' => 'jal-neti-lota',
      'name' => __('Jal Neti Lota', 'nirog-bhumi'),
      'category' => 'cure-kit-essentials',
      'status' => 'sale',
      'price' => '283.50',
      'featured' => true,
      'image' => 'jal-neti-pot-opt.jpg',
      'eyebrow' => __('Cleansing / Nasal hygiene', 'nirog-bhumi'),
      'short' => __('Stainless-steel nasal cleansing pot for the traditional Jala Neti kriya, part of a clean morning breathing routine.', 'nirog-bhumi'),
      'description' => [
        __('A stainless-steel nasal cleansing pot designed for the traditional yogic practice of Jala Neti, a saline nasal-rinsing technique used to cleanse the nasal passages. Used correctly with a properly prepared saline solution and safe water, nasal irrigation can help flush away excess mucus, dust, pollen and other debris, supporting nasal hygiene, clearer breathing and everyday respiratory comfort.', 'nirog-bhumi'),
        __('Jala Neti is one of the traditional Shatkarma cleansing practices of Hatha Yoga. It is a hygiene and wellness practice, not a cure for respiratory disease or diabetes.', 'nirog-bhumi'),
      ],
      'includes' => [
        __('1 stainless-steel Jal Neti Lota', 'nirog-bhumi'),
      ],
      'ritual' => [
        __('Prepare safe water - distilled, sterile, or tap water boiled and cooled to lukewarm. Never use untreated tap water directly.', 'nirog-bhumi'),
        __('Prepare the saline - roughly 1 teaspoon of clean salt fully dissolved in half a litre of water.', 'nirog-bhumi'),
        __('Stand over a sink, lean slightly forward, tilt the head to one side, and breathe through the mouth.', 'nirog-bhumi'),
        __('Fill the Lota and rest the spout gently in the upper nostril to form a comfortable seal.', 'nirog-bhumi'),
        __('Tilt the Lota so saline flows in through the upper nostril and out through the lower one, for about 10 to 20 seconds.', 'nirog-bhumi'),
        __('Remove the Lota, let residual solution drain, and repeat on the other side.', 'nirog-bhumi'),
        __('Dry the nasal passages with gentle exhalations - avoid forceful nose-blowing.', 'nirog-bhumi'),
        __('Wash and fully dry the Lota after use, and prepare fresh saline for every session.', 'nirog-bhumi'),
      ],
      'benefits' => [
        __('Supports nasal cleansing by helping remove mucus, dust, pollen and other debris.', 'nirog-bhumi'),
        __('May help loosen mucus and ease congestion linked to colds, allergies or sinus irritation.', 'nirog-bhumi'),
        __('Can support more comfortable nasal breathing when congestion is present.', 'nirog-bhumi'),
        __('Saline may help moisturise nasal passages exposed to dry air.', 'nirog-bhumi'),
        __('A structured nasal-hygiene practice within a broader yoga or wellness routine.', 'nirog-bhumi'),
      ],
      'diabetes_context' => __('Jala Neti is not a treatment for diabetes and has not been shown to lower blood glucose. Comfortable breathing, sleep, stress management and consistent self-care all matter to overall wellbeing - if nasal congestion is disturbing sleep or daily comfort, saline irrigation can be a supportive hygiene practice alongside your diabetes care, not a substitute for it.', 'nirog-bhumi'),
      'caution' => [
        __('Use only distilled, sterile, or properly boiled and cooled water - this is the most important safety step.', 'nirog-bhumi'),
        __('Use lukewarm water, never hot, and make sure the salt is fully dissolved.', 'nirog-bhumi'),
        __('Do not force water through a blocked nostril, and avoid blowing the nose too hard afterward.', 'nirog-bhumi'),
        __('Do not use over actively bleeding nasal tissue; people with recurrent nosebleeds should check with a professional first.', 'nirog-bhumi'),
        __('Seek medical advice rather than increasing pressure if water repeatedly will not pass through, or a blockage is suspected.', 'nirog-bhumi'),
        __('Stop and seek advice if rinsing causes persistent pain, bleeding, worsening symptoms or severe burning.', 'nirog-bhumi'),
        __('People with recent nasal or sinus surgery, or significant immune suppression, should get medical guidance before use.', 'nirog-bhumi'),
        __('Clean and dry the device between uses and never share a personal nasal-rinsing device.', 'nirog-bhumi'),
      ],
      'disclosure' => __('Jala Neti is a traditional yogic nasal-cleansing practice intended to support nasal hygiene and general wellness. It is not intended to diagnose, treat, cure or prevent diabetes or any other disease. Individual experiences vary, and nasal irrigation should never replace medical evaluation or treatment.', 'nirog-bhumi'),
      'hsn' => '7323',
      'gst_rate' => '5',
      'cross_sell' => ['NB-TOOL-02'],
    ],
    [
      'sku' => 'NB-TOOL-02',
      'slug' => 'vijaysar-wooden-tumbler',
      'name' => __('Vijaysar Wooden Tumbler', 'nirog-bhumi'),
      'category' => 'cure-kit-essentials',
      'status' => 'sale',
      'price' => '210.00',
      'featured' => true,
      'image' => 'vijaysar-tumbler-opt.jpg',
      'eyebrow' => __('Hydration / Traditional wood', 'nirog-bhumi'),
      'short' => __('Traditional tumbler turned from Vijaysar heartwood for the overnight water ritual used in metabolic-wellness practice.', 'nirog-bhumi'),
      'description' => [
        __('A traditional wooden tumbler crafted from Vijaysar heartwood (Pterocarpus marsupium), a tree with a long history of Ayurvedic use and traditional association with Madhumeha and metabolic wellness. Water is stored in the tumbler overnight, allowing it to remain in contact with the wood for several hours and gradually take on a light brown or amber tint.', 'nirog-bhumi'),
        __('Vijaysar has been studied for glucose-related effects, including an older Indian multicentre clinical study of Vijaysar preparations in people with newly diagnosed type 2 diabetes. That evidence does not prove that water from a wooden tumbler alone will control diabetes, so this is a supportive traditional wellness practice, not a glucose-lowering treatment.', 'nirog-bhumi'),
      ],
      'includes' => [
        __('1 Vijaysar wooden tumbler made from Pterocarpus marsupium heartwood', 'nirog-bhumi'),
      ],
      'ritual' => [
        __('Rinse the tumbler with clean drinking water before use - avoid harsh detergents, bleach or prolonged soaking.', 'nirog-bhumi'),
        __('In the evening, fill the tumbler with clean drinking water.', 'nirog-bhumi'),
        __('Leave the water in contact with the wood overnight, typically 8 to 10 hours.', 'nirog-bhumi'),
        __('By morning the water may turn light brown or amber - natural variation in shade is normal.', 'nirog-bhumi'),
        __('Decant into a clean glass and drink, traditionally on an empty stomach in the morning.', 'nirog-bhumi'),
        __('Rinse the tumbler and let it air-dry fully in a clean, ventilated spot before refilling.', 'nirog-bhumi'),
      ],
      'benefits' => [
        __('Traditional Ayurvedic use: long associated with Madhumeha and metabolic-wellness practices.', 'nirog-bhumi'),
        __('Scientific interest: Pterocarpus marsupium preparations have been studied for glucose-metabolism effects.', 'nirog-bhumi'),
        __('A simple, repeatable morning hydration ritual using plain water instead of sugar-sweetened drinks.', 'nirog-bhumi'),
        __('Vijaysar heartwood naturally contains polyphenolic and other plant compounds studied experimentally.', 'nirog-bhumi'),
        __('Best positioned as traditional metabolic-wellness support, not as something that controls diabetes or lowers blood sugar on its own.', 'nirog-bhumi'),
      ],
      'diabetes_context' => __('The diabetes connection here is more direct than for the other wellness accessories, since Vijaysar has both traditional use and human research around glucose metabolism. The evidence is not strong enough, though, to claim this tumbler will reduce HbA1c, reverse diabetes or replace standard care - it is an adjunct to your existing routine, not a substitute for it.', 'nirog-bhumi'),
      'caution' => [
        __('Do not stop, reduce or alter insulin, metformin or any prescribed medicine because you start using this tumbler.', 'nirog-bhumi'),
        __('If you take glucose-lowering medication, keep monitoring blood glucose as advised by your healthcare professional.', 'nirog-bhumi'),
        __('Talk to your doctor before regular use if you have recurrent hypoglycaemia, take multiple glucose-lowering medicines, or have significant kidney or liver disease.', 'nirog-bhumi'),
        __('Watch for signs of low blood glucose - sweating, shakiness, dizziness, unusual hunger, weakness, confusion or palpitations - and treat suspected hypoglycaemia per your diabetes plan.', 'nirog-bhumi'),
        __('Pregnant or breastfeeding individuals should get professional guidance before using herbal or Ayurvedic preparations with therapeutic intent.', 'nirog-bhumi'),
        __('A darker infused water colour is not a sign of a stronger effect.', 'nirog-bhumi'),
        __('Natural changes in colour, grain or surface are expected in wood - stop use if the tumbler develops mould, an unusual odour, deep cracking or damage that prevents hygienic cleaning.', 'nirog-bhumi'),
      ],
      'disclosure' => __('The Vijaysar Wooden Tumbler is a traditional Ayurvedic wellness product with a history of use in metabolic and diabetes-related wellness practice, and it has been studied scientifically for glucose-related effects. Evidence for water prepared specifically in a wooden tumbler remains limited. It is not intended to diagnose, cure, reverse or independently treat diabetes or any other medical condition.', 'nirog-bhumi'),
      'hsn' => '4419',
      'gst_rate' => '5',
      'cross_sell' => ['NB-TOOL-01'],
    ],
    [
      'sku' => 'NB-TOOL-05',
      'slug' => 'hot-cold-waist-pack',
      'name' => __('Hot & Cold Waist Pack', 'nirog-bhumi'),
      'category' => 'cure-kit-essentials',
      'status' => 'sale',
      'price' => '315.00',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Naturopathy / Abdominal comfort', 'nirog-bhumi'),
      'short' => __('A two-layer wet-cotton and woollen waist wrap used in naturopathy traditions for local thermal comfort.', 'nirog-bhumi'),
      'description' => [
        __('A two-layer waist pack: a damp cotton inner cloth and a dry woollen outer cloth. Used in some naturopathy and hydrotherapy traditions to create a mild cooling-to-warming sensation around the abdomen - the cool, damp inner layer gives a local thermal stimulus, while the woollen outer layer slows heat loss and lets the pack gradually warm toward body temperature.', 'nirog-bhumi'),
        __('Traditional explanations describe this as supporting abdominal circulation and digestive comfort. That rationale is retained as a traditional wellness concept, though current evidence does not establish that a waist pack increases blood flow specifically to the pancreas, liver, intestines or stomach, or improves how those organs function.', 'nirog-bhumi'),
      ],
      'includes' => [
        __('1 cotton cloth for the damp inner layer', 'nirog-bhumi'),
        __('1 woollen outer cloth with attached lace for securing the pack', 'nirog-bhumi'),
      ],
      'ritual' => [
        __('Wet the cotton cloth with normal or comfortably cool water and wring it thoroughly so it is damp, not dripping.', 'nirog-bhumi'),
        __('Wrap the damp cotton cloth around the waist and abdomen, lying flat against the skin without folds.', 'nirog-bhumi'),
        __('Wrap the dry woollen cloth over it and secure with the attached lace - snug enough to stay in place, never tight enough to restrict breathing.', 'nirog-bhumi'),
        __('Use at least 2 hours after a meal - avoid applying it right after eating.', 'nirog-bhumi'),
        __('For routine self-use, begin with 20 to 45 minutes and check skin comfort - avoid prolonged or overnight unsupervised use.', 'nirog-bhumi'),
        __('After removing the pack, dry the skin thoroughly and check for persistent redness, irritation, numbness or skin breakdown.', 'nirog-bhumi'),
        __('Traditional practice may suggest waiting about an hour before the next meal - a wellness convention, not a medical requirement.', 'nirog-bhumi'),
      ],
      'benefits' => [
        __('A clear local cooling-to-warming skin sensation that many find relaxing and soothing.', 'nirog-bhumi'),
        __('Cold exposure can cause local vasoconstriction, with rewarming changing superficial blood flow - the exact response varies by temperature, duration and individual physiology.', 'nirog-bhumi'),
        __('May support a sense of abdominal comfort and relaxation as part of a broader wellness routine.', 'nirog-bhumi'),
        __('Traditional naturopathy links abdominal packs with digestive comfort, though evidence for improving pancreatic, hepatic or intestinal function specifically is limited.', 'nirog-bhumi'),
      ],
      'diabetes_context' => __('There is not enough clinical evidence to say an abdominal wet pack regulates blood glucose, improves pancreatic function or treats diabetes. Its most defensible role in a diabetes-wellness routine is as a comfort and relaxation practice - any benefit is indirect, through a structured self-care routine rather than a proven glucose-lowering mechanism.', 'nirog-bhumi'),
      'caution' => [
        __('Diabetes can reduce the ability to feel temperature, pressure or skin injury - people with neuropathy, impaired sensation, poor circulation or a history of skin ulcers should not use prolonged thermal wraps unsupervised.', 'nirog-bhumi'),
        __('Check the skin before and after use, and stop immediately for pain, numbness, excessive redness, pallor, blistering or discomfort.', 'nirog-bhumi'),
        __('Keep a gap of at least 2 hours after a meal.', 'nirog-bhumi'),
        __('Do not use over open wounds, rashes, infections, recent surgical sites or inflamed skin.', 'nirog-bhumi'),
        __('Do not secure the pack tightly enough to restrict breathing, abdominal movement or circulation.', 'nirog-bhumi'),
        __('Avoid water that is uncomfortably cold or hot.', 'nirog-bhumi'),
        __('If pregnant, or you have significant abdominal disease, recent surgery or unexplained abdominal pain, get professional advice before use.', 'nirog-bhumi'),
        __('Stop if you feel dizziness, shivering, persistent numbness, worsening abdominal pain or skin irritation.', 'nirog-bhumi'),
        __('Wash and fully dry both cloths after use to reduce odour, mould and microbial growth.', 'nirog-bhumi'),
      ],
      'disclosure' => __('The Hot & Cold Waist Pack is a traditional naturopathy-style wellness accessory intended for local thermal stimulation, relaxation and comfort. It is not proven to increase blood flow to specific internal organs, improve pancreatic function, regulate blood sugar, detoxify the body or treat digestive disease, and it should not replace diabetes treatment or medical evaluation of persistent abdominal symptoms.', 'nirog-bhumi'),
      'hsn' => '6307',
      'gst_rate' => '5',
      'cross_sell' => ['NB-TOOL-02'],
    ],
    [
      'sku' => 'NB-ACU-01',
      'slug' => 'acupressure-jimmy',
      'name' => __('Acupressure Jimmy', 'nirog-bhumi'),
      'category' => 'acupressure',
      'status' => 'sale',
      'price' => '78.75',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Acupressure / Hand & foot', 'nirog-bhumi'),
      'short' => __('A handheld wooden tool for controlled pressure and rolling over traditional acupressure points on the palms and soles.', 'nirog-bhumi'),
      'description' => [
        __('A handheld wooden acupressure tool designed to apply controlled, localised pressure or a gentle rolling stimulus to selected points on the palms and soles, and to reduce strain on your thumb when practising self-acupressure.', 'nirog-bhumi'),
        __('The point locations used are drawn from traditional acupressure and reflexology mapping - they are traditional practice points, not anatomically proven switches for individual organs.', 'nirog-bhumi'),
      ],
      'includes' => [
        __('1 wooden Acupressure Jimmy', 'nirog-bhumi'),
      ],
      'ritual' => [
        __('Sit comfortably and support the hand or foot so you can control pressure precisely.', 'nirog-bhumi'),
        __('Place the rounded end on the selected soft-tissue point in the palm or sole - never press directly onto a bone.', 'nirog-bhumi'),
        __('Apply gentle to moderate, comfortable pressure while breathing normally, and release slowly.', 'nirog-bhumi'),
        __('For a simple cycle, press each point about 10 times if the area stays comfortable.', 'nirog-bhumi'),
        __('The Jimmy can also be rolled gently over the selected area instead of static pressure.', 'nirog-bhumi'),
        __('Use your thumb instead of the tool whenever that gives you better control.', 'nirog-bhumi'),
        __('For general digestive-organ mapping, the central region of the palm and sole is commonly worked with gentle rolling pressure.', 'nirog-bhumi'),
      ],
      'benefits' => [
        __('Focused tactile stimulation and massage-like pressure to the hands and feet.', 'nirog-bhumi'),
        __('May promote relaxation and reduce perceived stress for some users.', 'nirog-bhumi'),
        __('Acupressure has been studied as a complementary therapy in type 2 diabetes - small trials report changes in fasting glucose or stress, though HbA1c effects have been inconsistent.', 'nirog-bhumi'),
        __('Can reduce thumb strain for people already using acupressure as part of a self-care routine.', 'nirog-bhumi'),
      ],
      'diabetes_context' => __('A small randomised trial of self-acupressure in people with type 2 diabetes reported a reduction in fasting blood glucose and stress after one month, though HbA1c did not change significantly. The evidence is encouraging but not strong enough to claim the tool reliably lowers blood glucose, improves insulin sensitivity or replaces standard diabetes care.', 'nirog-bhumi'),
      'caution' => [
        __('Never apply pressure directly over a bone.', 'nirog-bhumi'),
        __('Use gentle to moderate pressure - pain is not a sign the technique is working.', 'nirog-bhumi'),
        __('Avoid acupressure for about an hour after a meal if that is part of your programme protocol.', 'nirog-bhumi'),
        __('Do not press over bruised, inflamed, infected, blistered, ulcerated or broken skin.', 'nirog-bhumi'),
        __('If you have diabetic peripheral neuropathy, reduced sensation or a history of foot ulcers, use extreme caution - pressure injury can occur without being felt, so do not use it unsupervised on numb areas.', 'nirog-bhumi'),
        __('Stop if pressure causes persistent pain, numbness, skin colour change or bruising.', 'nirog-bhumi'),
        __('If pregnant, get guidance from a qualified professional before using acupressure points therapeutically.', 'nirog-bhumi'),
        __('Acupressure should never delay medical assessment of persistent pain, numbness, digestive symptoms or glucose problems.', 'nirog-bhumi'),
      ],
      'disclosure' => __('The Acupressure Jimmy is a non-invasive wellness and massage accessory. Acupressure and reflexology point maps are traditional frameworks and do not establish a direct anatomical link between a point on the hand or foot and a specific internal organ. It is not intended to diagnose, treat, cure or prevent diabetes, neuropathy, kidney disease or digestive disease.', 'nirog-bhumi'),
      'hsn' => '9019',
      'gst_rate' => '5',
      'cross_sell' => ['NB-ACU-02'],
    ],
    [
      'sku' => 'NB-ACU-02',
      'slug' => 'acupressure-mat',
      'name' => __('Acupressure Mat', 'nirog-bhumi'),
      'category' => 'acupressure',
      'status' => 'sale',
      'price' => '262.50',
      'image' => 'store-products-opt.jpg',
      'eyebrow' => __('Acupressure / Foot', 'nirog-bhumi'),
      'short' => __('A foot acupressure mat with raised pressure elements and magnets for broad sole stimulation.', 'nirog-bhumi'),
      'description' => [
        __('A foot acupressure mat designed to apply pressure across multiple areas of the soles at once - a raised central section gives a stronger tactile stimulus to the middle of the foot, while the surrounding pressure elements stimulate a wider surface area. Magnets are built into the design.', 'nirog-bhumi'),
        __('The mat is best positioned as a massage and reflexology accessory for foot stimulation and relaxation. There is no established diabetes-specific benefit from the magnets, and they should not be the basis for a therapeutic claim.', 'nirog-bhumi'),
      ],
      'includes' => [
        __('1 Acupressure Mat with raised pressure elements and magnets', 'nirog-bhumi'),
      ],
      'ritual' => [
        __('Place the mat on a flat, dry, non-slip surface near a stable chair, wall or support.', 'nirog-bhumi'),
        __('Position your feet so the toes rest over the magnet area and the centre of each foot sits on the raised section.', 'nirog-bhumi'),
        __('Begin by standing still or shifting weight gently - progress to a slow marching motion only if comfortable and well-balanced.', 'nirog-bhumi'),
        __('Start with about 1 to 2 minutes daily and increase gradually only if your skin and feet stay comfortable.', 'nirog-bhumi'),
        __('A typical upper range for routine use is about 10 to 15 minutes daily - longer sessions are not automatically more beneficial.', 'nirog-bhumi'),
        __('If your balance is uncertain, hold a chair or stable support - seated use with controlled foot pressure may be safer for some.', 'nirog-bhumi'),
        __('Inspect the soles after every session, especially if you live with diabetes.', 'nirog-bhumi'),
      ],
      'benefits' => [
        __('Broad pressure and massage-like stimulation across the soles of the feet.', 'nirog-bhumi'),
        __('May promote relaxation, body awareness and a temporary sense of warmth in the feet.', 'nirog-bhumi'),
        __('Traditional reflexology links areas in the centre of the sole with digestive and metabolic organs - a traditional framework, not proof the mat stimulates the pancreas or liver directly.', 'nirog-bhumi'),
        __('A pleasant part of a daily movement or relaxation routine for people without sensory loss.', 'nirog-bhumi'),
      ],
      'diabetes_context' => __('Acupressure is being studied as a complementary modality, and foot stimulation may contribute to relaxation and a structured self-care routine, but the mat should not be marketed as a diabetes treatment. People with diabetic neuropathy need extra caution, since reduced sensation can make it hard to detect excessive pressure, skin damage or balance problems.', 'nirog-bhumi'),
      'caution' => [
        __('If neuropathy causes numbness, loss of protective sensation, ulcers, significant calluses, poor wound healing or balance impairment, do not use the mat unsupervised - foot protection comes before acupressure.', 'nirog-bhumi'),
        __('Avoid use for about an hour after a meal if that is part of your programme protocol.', 'nirog-bhumi'),
        __('Do not use on bruised, inflamed, blistered, infected, cracked or ulcerated skin.', 'nirog-bhumi'),
        __('Stop immediately for sharp pain, persistent numbness, skin discolouration, bleeding or a new blister.', 'nirog-bhumi'),
        __('Use a chair or stable support if balance is uncertain - never march rapidly or use the mat on a slippery surface.', 'nirog-bhumi'),
        __('People with diabetic peripheral neuropathy or reduced protective sensation should get professional guidance before using a pressure mat on the feet.', 'nirog-bhumi'),
        __('If pregnant, get guidance from a qualified professional before using acupressure therapeutically.', 'nirog-bhumi'),
        __('Keep the mat clean and dry, and do not share it if there is a risk of skin infection.', 'nirog-bhumi'),
      ],
      'disclosure' => __('The Acupressure Mat is a wellness and massage accessory for controlled pressure stimulation of the feet. Reflexology organ maps and magnetic-health claims should not be presented as established medical mechanisms. It is not intended to diagnose, treat, cure or prevent diabetes, peripheral neuropathy or disease of the pancreas, liver, kidneys or digestive system.', 'nirog-bhumi'),
      'hsn' => '9019',
      'gst_rate' => '5',
      'cross_sell' => ['NB-ACU-01'],
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
