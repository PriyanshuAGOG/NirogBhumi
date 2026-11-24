<?php
namespace NirogBhumi\Academy\Admin;

use NirogBhumi\Academy\Content\PostTypes;

defined('ABSPATH') || exit;

/**
 * Creates a sample programme so the team can see how everything fits
 * together. All content is clearly marked as a placeholder, saved as a draft,
 * and has no video attached.
 */
final class Demo {
  public static function create(): int|\WP_Error {
    $programme_id = wp_insert_post([
      'post_type' => PostTypes::PROGRAMME,
      'post_status' => 'draft',
      'post_title' => 'Yoga for Diabetes (sample)',
      'post_content' => 'SAMPLE PROGRAMME — replace this text. Describe what participants will learn and how the programme is organised.',
    ]);
    if (is_wp_error($programme_id)) {
      return $programme_id;
    }
    $meta = [
      '_nba_summary' => 'SAMPLE: a self-paced daily yoga practice, released week by week.',
      '_nba_title_hi' => 'डायबिटीज़ के लिए योग (सैंपल)',
      '_nba_access_days' => 365,
      '_nba_drip_mode' => 'enrollment',
      '_nba_cohort_assignment' => 'auto',
      '_nba_sequential' => '0',
      '_nba_element' => 'earth',
      '_nba_ack_text' => "PLACEHOLDER — to be written and approved by the Nirog Bhumi clinical team before launch.\n\nThis text should explain, in plain words, what participants must do to practise safely (for example about their current medicines, low blood sugar, and when to stop and seek help).",
    ];
    foreach ($meta as $key => $value) {
      update_post_meta($programme_id, $key, $value);
    }
    update_post_meta($programme_id, '_nba_ack_version', gmdate('Ymd-His'));

    $weeks = [
      ['Week 1: Foundations', ['Welcome and how to use the Academy', 'Breath awareness', 'Gentle morning sequence']],
      ['Week 2: Building the practice', ['Standing postures', 'Twists', 'Evening wind-down']],
      ['Week 3: Rest and recovery', ['Yoga Nidra', 'Pranayama practice']],
    ];
    $order = 0;
    foreach ($weeks as $w => [$module_title, $lessons]) {
      $module_id = wp_insert_post(['post_type' => PostTypes::MODULE, 'post_status' => 'publish', 'post_title' => $module_title, 'menu_order' => $w]);
      update_post_meta($module_id, '_nba_programme_id', $programme_id);
      foreach ($lessons as $l => $title) {
        $lesson_id = wp_insert_post([
          'post_type' => PostTypes::LESSON,
          'post_status' => 'publish',
          'post_title' => $title . ' (sample)',
          'post_content' => 'SAMPLE LESSON — attach a video from the Video Library and replace this text.',
          'menu_order' => $w * 1000 + $l,
        ]);
        update_post_meta($lesson_id, '_nba_programme_id', $programme_id);
        update_post_meta($lesson_id, '_nba_module_id', $module_id);
        update_post_meta($lesson_id, '_nba_placement', 'curriculum');
        update_post_meta($lesson_id, '_nba_type', $l === 0 && $w === 0 ? 'reading' : 'video');
        update_post_meta($lesson_id, '_nba_release_day', $w * 7 + $l);
        update_post_meta($lesson_id, '_nba_required', '1');
        update_post_meta($lesson_id, '_nba_duration_min', 15 + 5 * $l);
        update_post_meta($lesson_id, '_nba_summary', 'Sample summary. One or two sentences on what this lesson covers.');
        $order++;
      }
    }
    $bonus_id = wp_insert_post(['post_type' => PostTypes::LESSON, 'post_status' => 'publish', 'post_title' => 'Q&A recording (sample)', 'post_content' => 'SAMPLE BONUS ITEM.']);
    update_post_meta($bonus_id, '_nba_programme_id', $programme_id);
    update_post_meta($bonus_id, '_nba_placement', 'bonus');
    update_post_meta($bonus_id, '_nba_category', 'Q&A');
    update_post_meta($bonus_id, '_nba_type', 'reading');
    update_post_meta($bonus_id, '_nba_release_day', 0);
    nba()->curriculum->flush($programme_id);
    return (int) $programme_id;
  }
}
