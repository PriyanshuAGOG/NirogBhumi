<?php
if (!defined('ABSPATH')) {
  exit;
}

function nirog_bhumi_is_anonymised_record($post) {
  $post = get_post($post);
  if (!$post) {
    return false;
  }
  if (get_post_meta($post->ID, '_nb_anonymised_at', true)) {
    return true;
  }
  return (bool) preg_match('/^(Anonymised request|Restricted invoice record)/i', (string) $post->post_title);
}

function nirog_bhumi_mark_anonymised_record($post_id, $post) {
  if (wp_is_post_revision($post_id) || !in_array($post->post_type, ['nb_consultation', 'nb_form_entry'], true)) {
    return;
  }
  if (preg_match('/^(Anonymised request|Restricted invoice record)/i', (string) $post->post_title) && !get_post_meta($post_id, '_nb_anonymised_at', true)) {
    update_post_meta($post_id, '_nb_anonymised_at', current_time('mysql'));
  }
}
add_action('save_post', 'nirog_bhumi_mark_anonymised_record', 20, 2);

function nirog_bhumi_register_anonymised_data_page() {
  add_management_page(
    __('Anonymised Data', 'nirog-bhumi'),
    __('Anonymised Data', 'nirog-bhumi'),
    'manage_options',
    'nirog-anonymised-data',
    'nirog_bhumi_render_anonymised_data_page'
  );
}
add_action('admin_menu', 'nirog_bhumi_register_anonymised_data_page');

function nirog_bhumi_export_url($dataset) {
  return wp_nonce_url(
    admin_url('admin-post.php?action=nirog_export_records&dataset=' . rawurlencode($dataset)),
    'nirog_export_records_' . $dataset
  );
}

function nirog_bhumi_record_posts($dataset) {
  $post_types = $dataset === 'form_entries' ? ['nb_form_entry'] : ($dataset === 'consultations' ? ['nb_consultation'] : ['nb_health_metric']);
  $posts = get_posts([
    'post_type' => $post_types,
    'post_status' => ['publish', 'private', 'draft'],
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
  ]);
  return $posts;
}

function nirog_bhumi_render_anonymised_data_page() {
  if (!current_user_can('manage_options')) {
    return;
  }
  $posts = nirog_bhumi_record_posts('anonymised');
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Anonymised Data', 'nirog-bhumi'); ?></h1>
    <p><?php esc_html_e('These records retain non-identifying health and operational data after personal identifiers have been erased. Issued invoices retain only the legally required restricted identity and tax fields.', 'nirog-bhumi'); ?></p>
    <p><a class="button button-primary" href="<?php echo esc_url(nirog_bhumi_export_url('anonymised')); ?>"><?php esc_html_e('Export anonymised CSV', 'nirog-bhumi'); ?></a></p>
    <table class="widefat striped">
      <thead><tr><th><?php esc_html_e('Record', 'nirog-bhumi'); ?></th><th><?php esc_html_e('Type', 'nirog-bhumi'); ?></th><th><?php esc_html_e('Anonymised', 'nirog-bhumi'); ?></th><th><?php esc_html_e('Retained data', 'nirog-bhumi'); ?></th></tr></thead>
      <tbody>
      <?php if (!$posts) : ?>
        <tr><td colspan="4"><?php esc_html_e('No anonymised records yet.', 'nirog-bhumi'); ?></td></tr>
      <?php else : foreach ($posts as $post) : ?>
        <?php
        $meta = get_post_meta($post->ID);
        $retained = array_filter(array_keys($meta), function ($key) {
          return !in_array($key, ['name', 'email', 'phone', 'country_code', 'billing_address', 'billing_city', 'billing_postcode', 'customer_gstin'], true) && strpos($key, '_') !== 0;
        });
        ?>
        <tr>
          <td><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a></td>
          <td><?php esc_html_e('Anonymous health metrics', 'nirog-bhumi'); ?></td>
          <td><?php echo esc_html(get_post_meta($post->ID, 'anonymised_at', true) ?: $post->post_modified); ?></td>
          <td><?php echo esc_html($retained ? implode(', ', array_slice($retained, 0, 12)) : __('Restricted record', 'nirog-bhumi')); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php
}

function nirog_bhumi_add_export_buttons($post_type) {
  if (!current_user_can('manage_options')) {
    return;
  }
  if ($post_type === 'nb_consultation') {
    echo '<a class="button" style="margin-left:8px" href="' . esc_url(nirog_bhumi_export_url('consultations')) . '">' . esc_html__('Export consultations CSV', 'nirog-bhumi') . '</a>';
  }
  if ($post_type === 'nb_form_entry') {
    echo '<a class="button" style="margin-left:8px" href="' . esc_url(nirog_bhumi_export_url('form_entries')) . '">' . esc_html__('Export form entries CSV', 'nirog-bhumi') . '</a>';
  }
  if ($post_type === 'nb_health_metric') {
    echo '<a class="button" style="margin-left:8px" href="' . esc_url(nirog_bhumi_export_url('anonymised')) . '">' . esc_html__('Export anonymous metrics CSV', 'nirog-bhumi') . '</a>';
  }
}
add_action('restrict_manage_posts', 'nirog_bhumi_add_export_buttons');

function nirog_bhumi_csv_value($value) {
  if (is_array($value) || is_object($value)) {
    return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  if (is_serialized($value)) {
    return wp_json_encode(maybe_unserialize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  return (string) $value;
}

function nirog_bhumi_handle_records_export() {
  if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You are not allowed to export these records.', 'nirog-bhumi'));
  }
  $dataset = isset($_GET['dataset']) ? sanitize_key(wp_unslash($_GET['dataset'])) : '';
  if (!in_array($dataset, ['consultations', 'form_entries', 'anonymised'], true)) {
    wp_die(esc_html__('Invalid export type.', 'nirog-bhumi'));
  }
  check_admin_referer('nirog_export_records_' . $dataset);
  $posts = nirog_bhumi_record_posts($dataset);
  nocache_headers();
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename=nirog-bhumi-' . $dataset . '-' . gmdate('Y-m-d-His') . '.csv');
  $output = fopen('php://output', 'w');
  fwrite($output, "\xEF\xBB\xBF");
  fputcsv($output, ['record_id', 'record_type', 'created_at', 'title', 'anonymised_at', 'payment_status', 'invoice_number', 'name', 'email', 'country_code', 'phone', 'data_json']);
  foreach ($posts as $post) {
    $meta = get_post_meta($post->ID);
    $flat = [];
    foreach ($meta as $key => $values) {
      if (strpos($key, '_edit_') === 0) {
        continue;
      }
      $flat[$key] = count($values) === 1 ? nirog_bhumi_csv_value($values[0]) : array_map('nirog_bhumi_csv_value', $values);
    }
    $anonymised = $dataset === 'anonymised' || nirog_bhumi_is_anonymised_record($post);
    fputcsv($output, [
      $post->ID,
      $post->post_type,
      $post->post_date,
      $post->post_title,
      $anonymised ? (get_post_meta($post->ID, 'anonymised_at', true) ?: get_post_meta($post->ID, '_nb_anonymised_at', true) ?: $post->post_modified) : '',
      get_post_meta($post->ID, 'payment_status', true) ?: get_post_meta($post->ID, 'status', true),
      get_post_meta($post->ID, 'invoice_number', true),
      $anonymised ? '' : get_post_meta($post->ID, 'name', true),
      $anonymised ? '' : get_post_meta($post->ID, 'email', true),
      $anonymised ? '' : get_post_meta($post->ID, 'country_code', true),
      $anonymised ? '' : get_post_meta($post->ID, 'phone', true),
      wp_json_encode($flat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
  }
  fclose($output);
  exit;
}
add_action('admin_post_nirog_export_records', 'nirog_bhumi_handle_records_export');

function nirog_bhumi_consultation_admin_columns($columns) {
  $columns['nb_privacy'] = __('Privacy', 'nirog-bhumi');
  $columns['nb_invoice_delivery'] = __('Invoice delivery', 'nirog-bhumi');
  return $columns;
}
add_filter('manage_nb_consultation_posts_columns', 'nirog_bhumi_consultation_admin_columns', 20);

function nirog_bhumi_consultation_admin_column($column, $post_id) {
  if ($column === 'nb_privacy') {
    echo nirog_bhumi_is_anonymised_record($post_id) ? esc_html__('Anonymised', 'nirog-bhumi') : esc_html__('Identifiable', 'nirog-bhumi');
  }
  if ($column === 'nb_invoice_delivery') {
    $sent = get_post_meta($post_id, 'invoice_sent_at', true);
    $error = get_post_meta($post_id, 'invoice_error', true);
    if ($sent) {
      echo '<strong style="color:#22712f">' . esc_html__('Sent', 'nirog-bhumi') . '</strong><br><small>' . esc_html($sent) . '</small>';
    } elseif ($error) {
      echo '<strong style="color:#b32d2e">' . esc_html__('Failed', 'nirog-bhumi') . '</strong><br><small>' . esc_html($error) . '</small>';
    } else {
      echo esc_html__('Not sent', 'nirog-bhumi');
    }
    $status = get_post_meta($post_id, 'payment_status', true) ?: get_post_meta($post_id, 'status', true);
    $url = wp_nonce_url(admin_url('admin-post.php?action=nirog_retry_invoice&post_id=' . $post_id), 'nirog_retry_invoice_' . $post_id);
    $label = $status === 'verified' ? __('Generate and send', 'nirog-bhumi') : __('Verify payment + invoice', 'nirog-bhumi');
    echo '<br><a class="button button-small" style="margin-top:6px" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
  }
}
add_action('manage_nb_consultation_posts_custom_column', 'nirog_bhumi_consultation_admin_column', 10, 2);

function nirog_bhumi_consultation_invoice_row_action($actions, $post) {
  if (!$post || $post->post_type !== 'nb_consultation' || !current_user_can('edit_post', $post->ID)) return $actions;
  $status = get_post_meta($post->ID, 'payment_status', true);
  $url = wp_nonce_url(admin_url('admin-post.php?action=nirog_retry_invoice&post_id=' . $post->ID), 'nirog_retry_invoice_' . $post->ID);
  $actions['nb_invoice'] = '<a href="' . esc_url($url) . '">' . esc_html($status === 'verified' ? __('Generate/send invoice', 'nirog-bhumi') : __('Verify payment and invoice', 'nirog-bhumi')) . '</a>';
  return $actions;
}
add_filter('post_row_actions', 'nirog_bhumi_consultation_invoice_row_action', 20, 2);

function nirog_bhumi_retry_invoice_delivery() {
  $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
  if (!$post_id || get_post_type($post_id) !== 'nb_consultation' || !current_user_can('edit_post', $post_id)) {
    wp_die(esc_html__('You are not allowed to send this invoice.', 'nirog-bhumi'));
  }
  check_admin_referer('nirog_retry_invoice_' . $post_id);
  $redirect = admin_url('post.php?post=' . $post_id . '&action=edit');
  if (get_post_meta($post_id, 'payment_status', true) !== 'verified') {
    update_post_meta($post_id, 'payment_status', 'verified');
    update_post_meta($post_id, 'status', 'verified');
    update_post_meta($post_id, 'payment_verified_at', current_time('mysql'));
  }
  delete_post_meta($post_id, 'invoice_error');
  $success = false;
  if (function_exists('nirog_bhumi_send_consultation_invoice')) {
    $success = (bool) nirog_bhumi_send_consultation_invoice($post_id);
  }
  if (!$success && !get_post_meta($post_id, 'invoice_error', true)) {
    $last_error = get_option('nirog_bhumi_last_mail_error');
    update_post_meta($post_id, 'invoice_error', $last_error ? $last_error : __('WordPress could not send the email. Configure authenticated SMTP and retry.', 'nirog-bhumi'));
  }
  wp_safe_redirect(add_query_arg('nb_invoice_retry', $success ? 'sent' : 'failed', $redirect));
  exit;
}
add_action('admin_post_nirog_retry_invoice', 'nirog_bhumi_retry_invoice_delivery');

function nirog_bhumi_capture_mail_failure($error) {
  $message = is_wp_error($error) ? $error->get_error_message() : __('Unknown WordPress mail error.', 'nirog-bhumi');
  update_option('nirog_bhumi_last_mail_error', sanitize_text_field($message), false);
  update_option('nirog_bhumi_last_mail_error_at', current_time('mysql'), false);
}
add_action('wp_mail_failed', 'nirog_bhumi_capture_mail_failure');

function nirog_bhumi_invoice_admin_notices() {
  if (!current_user_can('manage_options')) {
    return;
  }
  if (isset($_GET['nb_invoice_retry'])) {
    $sent = sanitize_key(wp_unslash($_GET['nb_invoice_retry'])) === 'sent';
    echo '<div class="notice ' . ($sent ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>' . esc_html($sent ? __('Invoice PDF generated and email sent.', 'nirog-bhumi') : __('Invoice email was not sent. Review the error in the Invoice delivery column and verify SMTP settings.', 'nirog-bhumi')) . '</p></div>';
  }
}
add_action('admin_notices', 'nirog_bhumi_invoice_admin_notices');

function nirog_bhumi_mail_from_name() {
  return 'Nirog Bhumi';
}
add_filter('wp_mail_from_name', 'nirog_bhumi_mail_from_name');

/**
 * Force the single, current contact/reply-to address everywhere an outgoing
 * email needs one - the site-wide "from" address, and WooCommerce's own
 * email "from" setting - regardless of whatever address is saved in
 * WordPress General Settings, WooCommerce > Settings > Emails, or an old
 * placeholder used when the site was first built. This is the one place to
 * change it in the future: edit the email string below.
 *
 * Note: if an SMTP plugin (e.g. FluentSMTP) is configured with its own
 * "From email" on the outgoing connection, that plugin's setting is what
 * actually reaches the mail server and will override this filter - check
 * Settings -> FluentSMTP -> your connection there too if emails still show
 * an old address after this update.
 */
function nirog_bhumi_contact_email() {
  return 'priyanshu@nirogbhumi.com';
}
function nirog_bhumi_mail_from_address() {
  return nirog_bhumi_contact_email();
}
add_filter('wp_mail_from', 'nirog_bhumi_mail_from_address');
add_filter('woocommerce_email_from_address', 'nirog_bhumi_mail_from_address');

// ─── Invoice ZIP bulk download ─────────────────────────────────────────────

/**
 * Return all consultation post IDs that have a stored invoice PDF.
 * Optionally filter by invoice date range (uses payment_verified_at meta).
 */
function nirog_bhumi_invoice_posts_in_range($date_from = '', $date_to = '') {
  global $wpdb;
  $sql = "SELECT p.ID FROM {$wpdb->posts} p
          JOIN {$wpdb->postmeta} pf ON pf.post_id = p.ID AND pf.meta_key = '_nb_invoice_pdf_file' AND pf.meta_value != ''
          WHERE p.post_type = 'nb_consultation'
            AND p.post_status IN ('publish','private','draft')";
  $args = [];
  if ($date_from) {
    $sql .= " AND p.post_date >= %s";
    $args[] = $date_from . ' 00:00:00';
  }
  if ($date_to) {
    $sql .= " AND p.post_date <= %s";
    $args[] = $date_to . ' 23:59:59';
  }
  $sql .= " ORDER BY p.post_date ASC";
  if ($args) {
    $ids = $wpdb->get_col($wpdb->prepare($sql, $args)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
  } else {
    $ids = $wpdb->get_col($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
  }
  return array_map('intval', (array) $ids);
}

/** Stream a ZIP of all invoice PDFs for a date range to the browser. */
function nirog_bhumi_handle_invoice_zip_download() {
  if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You are not allowed to export invoices.', 'nirog-bhumi'));
  }
  check_admin_referer('nirog_download_invoices_zip');

  if (!class_exists('ZipArchive')) {
    wp_die(esc_html__('ZipArchive is not available on this server. Ask your host to enable the PHP zip extension.', 'nirog-bhumi'));
  }

  $date_from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
  $date_to   = isset($_GET['to'])   ? sanitize_text_field(wp_unslash($_GET['to']))   : '';
  $from_clean = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ? $date_from : '';
  $to_clean   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)   ? $date_to   : '';

  $uploads   = wp_upload_dir();
  $inv_dir   = trailingslashit($uploads['basedir']) . 'nirog-private-invoices';

  $post_ids = nirog_bhumi_invoice_posts_in_range($from_clean, $to_clean);
  if (!$post_ids) {
    wp_die(esc_html__('No invoices found for the selected period.', 'nirog-bhumi'));
  }

  $tmp = tempnam(sys_get_temp_dir(), 'nb_inv_');
  $zip = new ZipArchive();
  if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    wp_die(esc_html__('Could not create ZIP file.', 'nirog-bhumi'));
  }

  $added = 0;
  foreach ($post_ids as $post_id) {
    $pdf_file = (string) get_post_meta($post_id, '_nb_invoice_pdf_file', true);
    $pdf_path = trailingslashit($inv_dir) . $pdf_file;
    if (!$pdf_file || !is_readable($pdf_path)) {
      // Attempt to regenerate if the file is missing.
      if (function_exists('nirog_bhumi_create_consultation_invoice_pdf')) {
        $pdf_path = nirog_bhumi_create_consultation_invoice_pdf($post_id);
      }
    }
    if (!$pdf_path || !is_readable($pdf_path)) {
      continue;
    }
    $inv_num = (string) get_post_meta($post_id, 'invoice_number', true);
    $zip_name = $inv_num ? 'NB-Invoice-' . sanitize_file_name($inv_num) . '.pdf'
                         : 'NB-Invoice-entry-' . $post_id . '.pdf';
    $zip->addFile($pdf_path, $zip_name);
    $added++;
  }
  $zip->close();

  if (!$added) {
    @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    wp_die(esc_html__('No invoice PDF files could be found or generated for the selected period.', 'nirog-bhumi'));
  }

  $label = ($from_clean || $to_clean)
    ? 'NirogBhumi-Invoices-' . ($from_clean ?: 'start') . '-to-' . ($to_clean ?: 'today') . '.zip'
    : 'NirogBhumi-Invoices-All.zip';

  nocache_headers();
  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="' . $label . '"');
  header('Content-Length: ' . filesize($tmp));
  readfile($tmp);
  @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
  exit;
}
add_action('admin_post_nirog_download_invoices_zip', 'nirog_bhumi_handle_invoice_zip_download');
