<?php
function nirog_bhumi_setup() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('woocommerce');
  add_theme_support('wc-product-gallery-zoom');
  add_theme_support('wc-product-gallery-lightbox');
  register_nav_menus(['primary' => __('Primary Menu', 'nirog-bhumi')]);
}
add_action('after_setup_theme', 'nirog_bhumi_setup');

function nirog_bhumi_assets() {
  wp_enqueue_style('nirog-bhumi-style', get_template_directory_uri() . '/assets/css/styles.css', [], '0.1.0');
  wp_enqueue_script('nirog-bhumi-main', get_template_directory_uri() . '/assets/js/main.js', [], '0.1.0', true);
}
add_action('wp_enqueue_scripts', 'nirog_bhumi_assets');

function nirog_bhumi_register_consultations() {
  register_post_type('nb_consultation', [
    'labels' => [
      'name' => __('Consultation Entries', 'nirog-bhumi'),
      'singular_name' => __('Consultation Entry', 'nirog-bhumi'),
      'menu_name' => __('Consultations', 'nirog-bhumi'),
      'view_item' => __('View Consultation Entry', 'nirog-bhumi'),
    ],
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_icon' => 'dashicons-clipboard',
    'capability_type' => 'post',
    'supports' => ['title'],
  ]);
}
add_action('init', 'nirog_bhumi_register_consultations');

function nirog_bhumi_clean_field($key) {
  return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
}

function nirog_bhumi_clean_textarea($key) {
  return isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
}

function nirog_bhumi_handle_consultation_form() {
  if (!isset($_POST['nirog_consultation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nirog_consultation_nonce'])), 'nirog_consultation_submit')) {
    wp_die(esc_html__('Security check failed. Please go back and submit the consultation form again.', 'nirog-bhumi'));
  }

  $name = nirog_bhumi_clean_field('name');
  $email = sanitize_email(nirog_bhumi_clean_field('email'));
  $phone = nirog_bhumi_clean_field('phone');

  if (!$name || !$email || !$phone) {
    wp_safe_redirect(add_query_arg('consultation_error', 'missing', wp_get_referer() ?: home_url('/consultation/')));
    exit;
  }

  $post_id = wp_insert_post([
    'post_type' => 'nb_consultation',
    'post_status' => 'private',
    'post_title' => sprintf('%s - %s', $name, current_time('d M Y H:i')),
  ]);

  if (is_wp_error($post_id) || !$post_id) {
    wp_die(esc_html__('Could not save the consultation request. Please try again.', 'nirog-bhumi'));
  }

  $fields = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'age' => nirog_bhumi_clean_field('age'),
    'concern' => nirog_bhumi_clean_field('concern'),
    'fasting' => nirog_bhumi_clean_field('fasting'),
    'postmeal' => nirog_bhumi_clean_field('postmeal'),
    'hba1c' => nirog_bhumi_clean_field('hba1c'),
    'bp' => nirog_bhumi_clean_field('bp'),
    'body' => nirog_bhumi_clean_field('body'),
    'medicines' => nirog_bhumi_clean_textarea('medicines'),
    'conditions' => nirog_bhumi_clean_textarea('conditions'),
    'food' => nirog_bhumi_clean_textarea('food'),
    'lifestyle' => nirog_bhumi_clean_textarea('lifestyle'),
    'goal' => nirog_bhumi_clean_textarea('goal'),
    'consultation_disclaimer' => isset($_POST['consultation_disclaimer']) ? 'yes' : 'no',
    'data_processing_consent' => isset($_POST['data_processing_consent']) ? 'yes' : 'no',
    'followup_consent' => isset($_POST['followup_consent']) ? 'yes' : 'no',
  ];

  foreach ($fields as $key => $value) {
    update_post_meta($post_id, $key, $value);
  }

  if (!empty($_FILES['reports']['name'][0])) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachment_ids = [];
    foreach ($_FILES['reports']['name'] as $index => $filename) {
      if (!$filename) {
        continue;
      }
      $file = [
        'name' => $_FILES['reports']['name'][$index],
        'type' => $_FILES['reports']['type'][$index],
        'tmp_name' => $_FILES['reports']['tmp_name'][$index],
        'error' => $_FILES['reports']['error'][$index],
        'size' => $_FILES['reports']['size'][$index],
      ];
      $_FILES['nb_report_upload'] = $file;
      $attachment_id = media_handle_upload('nb_report_upload', $post_id);
      if (!is_wp_error($attachment_id)) {
        $attachment_ids[] = $attachment_id;
      }
    }
    if ($attachment_ids) {
      update_post_meta($post_id, 'report_attachment_ids', $attachment_ids);
    }
  }

  $admin_email = get_option('admin_email');
  if ($admin_email) {
    wp_mail($admin_email, sprintf(__('New consultation request - %s', 'nirog-bhumi'), $name), sprintf("Name: %s
Email: %s
Phone: %s
Concern: %s

View in WordPress dashboard: %s", $name, $email, $phone, $fields['concern'], admin_url('post.php?post=' . $post_id . '&action=edit')));
  }

  wp_safe_redirect(add_query_arg('consultation_saved', '1', home_url('/consultation-payment/')));
  exit;
}
add_action('admin_post_nopriv_nirog_consultation_submit', 'nirog_bhumi_handle_consultation_form');
add_action('admin_post_nirog_consultation_submit', 'nirog_bhumi_handle_consultation_form');

function nirog_bhumi_consultation_metaboxes() {
  add_meta_box('nb_consultation_details', __('Consultation Details', 'nirog-bhumi'), 'nirog_bhumi_render_consultation_metabox', 'nb_consultation', 'normal', 'high');
}
add_action('add_meta_boxes', 'nirog_bhumi_consultation_metaboxes');

function nirog_bhumi_render_consultation_metabox($post) {
  $fields = [
    'name' => 'Name',
    'email' => 'Email',
    'phone' => 'Phone / WhatsApp',
    'age' => 'Age',
    'concern' => 'Primary concern',
    'fasting' => 'Fasting sugar',
    'postmeal' => 'Post-meal sugar',
    'hba1c' => 'HbA1c',
    'bp' => 'Blood pressure',
    'body' => 'Weight / waist',
    'medicines' => 'Current medication or insulin',
    'conditions' => 'Other health conditions',
    'food' => 'Food routine',
    'lifestyle' => 'Sleep, stress and activity',
    'goal' => 'Main goal',
    'consultation_disclaimer' => 'Consultation disclaimer',
    'data_processing_consent' => 'Data consent',
    'followup_consent' => 'Follow-up consent',
  ];
  echo '<div class="nb-admin-details">';
  foreach ($fields as $key => $label) {
    $value = get_post_meta($post->ID, $key, true);
    echo '<p><strong>' . esc_html($label) . ':</strong><br>' . nl2br(esc_html($value ?: '-')) . '</p>';
  }
  $attachments = (array) get_post_meta($post->ID, 'report_attachment_ids', true);
  if ($attachments) {
    echo '<p><strong>' . esc_html__('Uploaded reports:', 'nirog-bhumi') . '</strong><br>';
    foreach ($attachments as $attachment_id) {
      echo '<a href="' . esc_url(wp_get_attachment_url($attachment_id)) . '" target="_blank" rel="noopener">' . esc_html(get_the_title($attachment_id)) . '</a><br>';
    }
    echo '</p>';
  }
  echo '</div>';
}

function nirog_bhumi_consultation_columns($columns) {
  return [
    'cb' => $columns['cb'],
    'title' => __('Entry', 'nirog-bhumi'),
    'nb_phone' => __('Phone', 'nirog-bhumi'),
    'nb_concern' => __('Concern', 'nirog-bhumi'),
    'date' => $columns['date'],
  ];
}
add_filter('manage_nb_consultation_posts_columns', 'nirog_bhumi_consultation_columns');

function nirog_bhumi_consultation_column_content($column, $post_id) {
  if ($column === 'nb_phone') {
    echo esc_html(get_post_meta($post_id, 'phone', true));
  }
  if ($column === 'nb_concern') {
    echo esc_html(get_post_meta($post_id, 'concern', true));
  }
}
add_action('manage_nb_consultation_posts_custom_column', 'nirog_bhumi_consultation_column_content', 10, 2);
