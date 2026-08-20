<?php
/**
 * Nirog Bhumi — Visual Admin Dashboard
 * Registered as a top-level WP admin menu page.
 */

if (!defined('ABSPATH')) {
  exit;
}

// ─── Registration ──────────────────────────────────────────────────────────

function nirog_bhumi_register_dashboard_page() {
  add_menu_page(
    __('Nirog Bhumi Dashboard', 'nirog-bhumi'),
    __('NB Dashboard', 'nirog-bhumi'),
    'manage_options',
    'nirog-bhumi-dashboard',
    'nirog_bhumi_render_dashboard',
    'dashicons-heart',
    3
  );
}
add_action('admin_menu', 'nirog_bhumi_register_dashboard_page');

// ─── Data helpers ──────────────────────────────────────────────────────────

function nirog_bhumi_dash_date_range($period) {
  $tz = wp_timezone();
  $now = new DateTime('now', $tz);
  if ($period === 'week') {
    $start = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
  } elseif ($period === 'month') {
    $start = (clone $now)->setDate((int)$now->format('Y'), (int)$now->format('m'), 1)->setTime(0, 0, 0);
  } elseif ($period === 'last30') {
    $start = (clone $now)->modify('-30 days')->setTime(0, 0, 0);
  } else {
    $start = (clone $now)->modify('-365 days')->setTime(0, 0, 0);
  }
  return [$start->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')];
}

function nirog_bhumi_dash_count_posts($post_type, $date_from = null, $date_to = null, $meta_key = null, $meta_value = null) {
  global $wpdb;
  $sql = "SELECT COUNT(*) FROM {$wpdb->posts} p";
  $where = ["p.post_type = %s", "p.post_status IN ('publish','private','draft')"];
  $args = [$post_type];

  if ($date_from && $date_to) {
    $where[] = "p.post_date >= %s AND p.post_date <= %s";
    $args[] = $date_from;
    $args[] = $date_to;
  }
  if ($meta_key && $meta_value !== null) {
    $sql .= " JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s";
    $where[] = "pm.meta_value = %s";
    $args = array_merge([$args[0]], [$meta_key], array_slice($args, 1), [$meta_value]);
  }
  $sql .= ' WHERE ' . implode(' AND ', $where);
  return (int) $wpdb->get_var($wpdb->prepare($sql, $args)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

function nirog_bhumi_dash_count_meta($post_type, $meta_key, $meta_value) {
  global $wpdb;
  return (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts} p
     JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
     WHERE p.post_type = %s AND p.post_status IN ('publish','private','draft')
       AND pm.meta_key = %s AND pm.meta_value = %s",
    $post_type, $meta_key, $meta_value
  ));
}

function nirog_bhumi_dash_posts_per_day($post_type, $days = 30) {
  global $wpdb;
  $since = gmdate('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
  $rows = $wpdb->get_results($wpdb->prepare(
    "SELECT DATE(post_date) as d, COUNT(*) as n
     FROM {$wpdb->posts}
     WHERE post_type = %s AND post_status IN ('publish','private','draft')
       AND post_date >= %s
     GROUP BY DATE(post_date)
     ORDER BY d ASC",
    $post_type, $since
  ), ARRAY_A);
  $map = [];
  foreach ($rows as $r) {
    $map[$r['d']] = (int) $r['n'];
  }
  // Fill missing days with 0
  $result = [];
  for ($i = $days - 1; $i >= 0; $i--) {
    $date = gmdate('Y-m-d', strtotime('-' . $i . ' days'));
    $result[$date] = $map[$date] ?? 0;
  }
  return $result;
}

function nirog_bhumi_dash_recent_consultations($limit = 10) {
  return get_posts([
    'post_type'      => 'nb_consultation',
    'post_status'    => ['publish', 'private', 'draft'],
    'posts_per_page' => $limit,
    'orderby'        => 'date',
    'order'          => 'DESC',
  ]);
}

function nirog_bhumi_dash_recent_form_entries($limit = 10) {
  return get_posts([
    'post_type'      => 'nb_form_entry',
    'post_status'    => ['publish', 'private', 'draft'],
    'posts_per_page' => $limit,
    'orderby'        => 'date',
    'order'          => 'DESC',
  ]);
}

function nirog_bhumi_dash_mail_error_status() {
  $error = get_option('nirog_bhumi_last_mail_error');
  $at    = get_option('nirog_bhumi_last_mail_error_at');
  return ['error' => $error, 'at' => $at];
}

function nirog_bhumi_dash_cron_next() {
  $ts = wp_next_scheduled('nirog_bhumi_takeaway_sweep');
  if (!$ts) return __('Not scheduled', 'nirog-bhumi');
  $diff = $ts - time();
  if ($diff < 0) return __('Overdue — runs on next page load', 'nirog-bhumi');
  if ($diff < 60) return $diff . 's';
  return round($diff / 60) . 'm';
}

// ─── Render ─────────────────────────────────────────────────────────────────

function nirog_bhumi_render_dashboard() {
  if (!current_user_can('manage_options')) return;

  // ── Gather all stats ──
  [$week_start, $now_str] = nirog_bhumi_dash_date_range('week');
  [$month_start]          = nirog_bhumi_dash_date_range('month');
  [$l30_start]            = nirog_bhumi_dash_date_range('last30');

  $cons_total   = nirog_bhumi_dash_count_posts('nb_consultation');
  $cons_week    = nirog_bhumi_dash_count_posts('nb_consultation', $week_start, $now_str);
  $cons_month   = nirog_bhumi_dash_count_posts('nb_consultation', $month_start, $now_str);
  $cons_l30     = nirog_bhumi_dash_count_posts('nb_consultation', $l30_start, $now_str);

  $form_total   = nirog_bhumi_dash_count_posts('nb_form_entry');
  $form_week    = nirog_bhumi_dash_count_posts('nb_form_entry', $week_start, $now_str);
  $form_month   = nirog_bhumi_dash_count_posts('nb_form_entry', $month_start, $now_str);

  // Payment / invoice stats (consultation entries)
  $pay_verified  = nirog_bhumi_dash_count_meta('nb_consultation', 'payment_status', 'verified');
  $pay_pending   = nirog_bhumi_dash_count_meta('nb_consultation', 'payment_status', 'pending');
  $inv_sent      = nirog_bhumi_dash_count_meta('nb_consultation', 'invoice_sent_at', '');  // non-empty sent
  // Count where invoice_sent_at exists and is not empty
  global $wpdb;
  $inv_sent = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} p
     JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
     WHERE p.post_type = 'nb_consultation' AND p.post_status IN ('publish','private','draft')
       AND pm.meta_key = 'invoice_sent_at' AND pm.meta_value != ''"
  );
  $inv_failed = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} p
     JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
     WHERE p.post_type = 'nb_consultation' AND p.post_status IN ('publish','private','draft')
       AND pm.meta_key = 'invoice_error' AND pm.meta_value != ''"
  );

  // Takeaway email stats
  $tw_sent    = nirog_bhumi_dash_count_meta('nb_consultation', 'takeaway_email_sent_status', 'sent');
  $tw_pending = nirog_bhumi_dash_count_meta('nb_consultation', 'takeaway_email_sent_status', 'pending');
  $tw_skipped = nirog_bhumi_dash_count_meta('nb_consultation', 'takeaway_email_sent_status', 'skipped');

  // Anonymised
  $anon_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} p
     JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
     WHERE p.post_type IN ('nb_consultation','nb_form_entry')
       AND p.post_status IN ('publish','private','draft')
       AND pm.meta_key = '_nb_anonymised_at'"
  );

  // Chart data: consultations + form entries per day (last 30 days)
  $cons_daily = nirog_bhumi_dash_posts_per_day('nb_consultation', 30);
  $form_daily = nirog_bhumi_dash_posts_per_day('nb_form_entry', 30);
  $chart_labels = array_keys($cons_daily);
  $chart_cons   = array_values($cons_daily);
  $chart_forms  = [];
  foreach ($chart_labels as $d) {
    $chart_forms[] = $form_daily[$d] ?? 0;
  }

  // Recent activity
  $recent_cons  = nirog_bhumi_dash_recent_consultations(8);
  $recent_forms = nirog_bhumi_dash_recent_form_entries(6);

  // Mail health
  $mail_err = nirog_bhumi_dash_mail_error_status();
  $cron_next = nirog_bhumi_dash_cron_next();

  // Invoice sequence
  $inv_seq = (int) $wpdb->get_var("SELECT MAX(sequence_number) FROM {$wpdb->prefix}nb_invoice_sequences WHERE context = 'consultation'");
  ?>
  <div class="wrap nirog-dash-wrap">
  <style>
  .nirog-dash-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1d2327;max-width:1440px}
  .nirog-dash-wrap h1{display:flex;align-items:center;gap:10px;font-size:22px;font-weight:700;margin:16px 0 20px;color:#1d2327}
  .nirog-dash-wrap h1 .nb-logo-dot{width:10px;height:10px;background:#2e7d32;border-radius:50%;display:inline-block}

  /* Stat card grid */
  .nb-stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:24px}
  .nb-stat-card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:18px 20px;position:relative;overflow:hidden}
  .nb-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--nb-accent,#2e7d32)}
  .nb-stat-card.accent-blue::before{background:#1565c0}
  .nb-stat-card.accent-orange::before{background:#e65100}
  .nb-stat-card.accent-teal::before{background:#00695c}
  .nb-stat-card.accent-red::before{background:#c62828}
  .nb-stat-card.accent-purple::before{background:#6a1b9a}
  .nb-stat-card.accent-grey::before{background:#546e7a}
  .nb-stat-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:#5f6368;margin-bottom:6px}
  .nb-stat-value{font-size:30px;font-weight:800;line-height:1;color:#1d2327;margin-bottom:4px}
  .nb-stat-sub{font-size:11px;color:#5f6368;margin-top:6px}
  .nb-stat-sub strong{color:#1d2327}

  /* Section headers */
  .nb-section-head{display:flex;align-items:center;justify-content:space-between;margin:28px 0 12px}
  .nb-section-head h2{font-size:15px;font-weight:700;color:#1d2327;margin:0;padding:0;border:none}
  .nb-section-head a{font-size:12px;color:#2e7d32;text-decoration:none}

  /* Two-column layout */
  .nb-two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
  @media(max-width:900px){.nb-two-col{grid-template-columns:1fr}}

  /* Chart card */
  .nb-chart-card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px}
  .nb-chart-card h3{font-size:13px;font-weight:700;color:#1d2327;margin:0 0 14px;text-transform:uppercase;letter-spacing:.4px}
  .nb-chart-card canvas{max-height:220px}

  /* Donut grid */
  .nb-donut-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-bottom:24px}
  .nb-donut-card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:18px;display:flex;flex-direction:column;align-items:center}
  .nb-donut-card h3{font-size:12px;font-weight:700;color:#1d2327;margin:0 0 10px;text-transform:uppercase;letter-spacing:.4px;text-align:center}
  .nb-donut-card canvas{max-width:160px;max-height:160px}
  .nb-donut-legend{margin-top:10px;width:100%}
  .nb-donut-legend li{display:flex;align-items:center;gap:6px;font-size:11px;color:#3c4043;padding:2px 0}
  .nb-donut-legend li span{width:10px;height:10px;border-radius:2px;flex-shrink:0}

  /* Activity table */
  .nb-activity-card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;margin-bottom:24px}
  .nb-activity-card table{width:100%;border-collapse:collapse;font-size:13px}
  .nb-activity-card thead th{background:#f8f9fa;color:#5f6368;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:1px solid #e0e0e0}
  .nb-activity-card tbody td{padding:10px 14px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
  .nb-activity-card tbody tr:last-child td{border-bottom:none}
  .nb-activity-card tbody tr:hover{background:#fafafa}

  /* Status badges */
  .nb-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
  .nb-badge-green{background:#e8f5e9;color:#1b5e20}
  .nb-badge-orange{background:#fff3e0;color:#bf360c}
  .nb-badge-blue{background:#e3f2fd;color:#0d47a1}
  .nb-badge-red{background:#ffebee;color:#b71c1c}
  .nb-badge-grey{background:#f5f5f5;color:#546e7a}

  /* Health panel */
  .nb-health-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-bottom:24px}
  .nb-health-card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:18px}
  .nb-health-card h3{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#5f6368;margin:0 0 12px}
  .nb-health-item{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:12px}
  .nb-health-item:last-child{border-bottom:none}
  .nb-health-item-label{color:#3c4043}
  .nb-health-item-value{font-weight:600;color:#1d2327}

  /* Quick links */
  .nb-quick-links{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:28px}
  .nb-quick-links a{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;background:#f0f4f0;border:1px solid #c8e6c9;border-radius:20px;font-size:12px;font-weight:600;color:#2e7d32;text-decoration:none;transition:background .15s}
  .nb-quick-links a:hover{background:#c8e6c9}
  .nb-quick-links a .dashicons{font-size:14px;width:14px;height:14px;line-height:1}

  /* Empty state */
  .nb-empty{padding:24px;text-align:center;color:#80868b;font-size:13px}
  </style>

  <h1><span class="nb-logo-dot"></span> Nirog Bhumi Dashboard</h1>

  <!-- Quick links -->
  <div class="nb-quick-links">
    <a href="<?php echo esc_url(admin_url('edit.php?post_type=nb_consultation')); ?>"><span class="dashicons dashicons-calendar-alt"></span> Consultations</a>
    <a href="<?php echo esc_url(admin_url('edit.php?post_type=nb_form_entry')); ?>"><span class="dashicons dashicons-feedback"></span> Form Entries</a>
    <a href="<?php echo esc_url(admin_url('options-general.php?page=nirog-bhumi-setup')); ?>"><span class="dashicons dashicons-admin-settings"></span> Settings</a>
    <a href="<?php echo esc_url(admin_url('tools.php?page=nirog-anonymised-data')); ?>"><span class="dashicons dashicons-lock"></span> Anonymised Data</a>
    <a href="<?php echo esc_url(home_url('/consultation-feedback/')); ?>" target="_blank"><span class="dashicons dashicons-star-filled"></span> Feedback Form</a>
    <a href="<?php echo esc_url(home_url('/book-consultation/')); ?>" target="_blank"><span class="dashicons dashicons-external"></span> Consultation Page</a>
  </div>

  <!-- ── Invoice ZIP download ── -->
  <div class="nb-section-head"><h2>Download Invoices</h2></div>
  <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap">
    <div>
      <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#5f6368;margin-bottom:5px">From date</label>
      <input type="date" id="nb-inv-from" name="from" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px;color:#1d2327">
    </div>
    <div>
      <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#5f6368;margin-bottom:5px">To date</label>
      <input type="date" id="nb-inv-to" name="to" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px;color:#1d2327">
    </div>
    <div>
      <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#5f6368;margin-bottom:5px">Quick select</label>
      <select id="nb-inv-quick" style="padding:7px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px;color:#1d2327">
        <option value="">Custom range</option>
        <option value="this_month">This month</option>
        <option value="last_month">Last month</option>
        <option value="this_year">This year</option>
        <option value="all">All time</option>
      </select>
    </div>
    <div>
      <a id="nb-inv-download-btn" href="#" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#2e7d32;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none">
        <span class="dashicons dashicons-download" style="font-size:15px;width:15px;height:15px;line-height:1"></span> Download ZIP
      </a>
      <p style="font-size:11px;color:#80868b;margin:5px 0 0">Leave dates blank to download all invoices.</p>
    </div>
  </div>
  <script>
  (function(){
    var base = <?php echo wp_json_encode(wp_nonce_url(admin_url('admin-post.php?action=nirog_download_invoices_zip'), 'nirog_download_invoices_zip')); ?>;
    var fromEl = document.getElementById('nb-inv-from');
    var toEl   = document.getElementById('nb-inv-to');
    var quick  = document.getElementById('nb-inv-quick');
    var btn    = document.getElementById('nb-inv-download-btn');

    function updateUrl() {
      var url = base;
      if (fromEl.value) url += '&from=' + encodeURIComponent(fromEl.value);
      if (toEl.value)   url += '&to='   + encodeURIComponent(toEl.value);
      btn.href = url;
    }

    function applyQuick(v) {
      var now = new Date();
      var y = now.getFullYear(), m = now.getMonth();
      if (v === 'this_month') {
        fromEl.value = y + '-' + String(m+1).padStart(2,'0') + '-01';
        var last = new Date(y, m+1, 0);
        toEl.value = y + '-' + String(m+1).padStart(2,'0') + '-' + String(last.getDate()).padStart(2,'0');
      } else if (v === 'last_month') {
        var lm = m === 0 ? 11 : m-1;
        var ly = m === 0 ? y-1 : y;
        var lastDay = new Date(ly, lm+1, 0);
        fromEl.value = ly + '-' + String(lm+1).padStart(2,'0') + '-01';
        toEl.value   = ly + '-' + String(lm+1).padStart(2,'0') + '-' + String(lastDay.getDate()).padStart(2,'0');
      } else if (v === 'this_year') {
        fromEl.value = y + '-01-01';
        toEl.value   = y + '-12-31';
      } else if (v === 'all') {
        fromEl.value = '';
        toEl.value   = '';
      }
      updateUrl();
    }

    quick.addEventListener('change', function(){ applyQuick(this.value); });
    fromEl.addEventListener('change', function(){ quick.value = ''; updateUrl(); });
    toEl.addEventListener('change',   function(){ quick.value = ''; updateUrl(); });
    updateUrl();
  })();
  </script>

  <!-- ── Stat cards row 1: Consultations ── -->
  <div class="nb-section-head">
    <h2>Consultations</h2>
    <a href="<?php echo esc_url(admin_url('edit.php?post_type=nb_consultation')); ?>">View all &rarr;</a>
  </div>
  <div class="nb-stat-grid">
    <div class="nb-stat-card">
      <div class="nb-stat-label">Total</div>
      <div class="nb-stat-value"><?php echo esc_html($cons_total); ?></div>
      <div class="nb-stat-sub">All time</div>
    </div>
    <div class="nb-stat-card accent-blue">
      <div class="nb-stat-label">This week</div>
      <div class="nb-stat-value"><?php echo esc_html($cons_week); ?></div>
      <div class="nb-stat-sub">Mon &ndash; today</div>
    </div>
    <div class="nb-stat-card accent-teal">
      <div class="nb-stat-label">This month</div>
      <div class="nb-stat-value"><?php echo esc_html($cons_month); ?></div>
      <div class="nb-stat-sub"><?php echo esc_html(gmdate('F Y')); ?></div>
    </div>
    <div class="nb-stat-card accent-orange">
      <div class="nb-stat-label">Payment verified</div>
      <div class="nb-stat-value"><?php echo esc_html($pay_verified); ?></div>
      <div class="nb-stat-sub"><strong><?php echo esc_html($pay_pending); ?></strong> pending</div>
    </div>
    <div class="nb-stat-card">
      <div class="nb-stat-label">Invoices sent</div>
      <div class="nb-stat-value"><?php echo esc_html($inv_sent); ?></div>
      <div class="nb-stat-sub"><?php if ($inv_failed): ?><strong style="color:#c62828"><?php echo esc_html($inv_failed); ?> failed</strong><?php else: ?>No failures<?php endif; ?></div>
    </div>
    <div class="nb-stat-card accent-purple">
      <div class="nb-stat-label">Invoice series</div>
      <div class="nb-stat-value"><?php echo $inv_seq ? esc_html(str_pad($inv_seq, 3, '0', STR_PAD_LEFT)) : '—'; ?></div>
      <div class="nb-stat-sub">Last issued number</div>
    </div>
    <div class="nb-stat-card accent-grey">
      <div class="nb-stat-label">Anonymised</div>
      <div class="nb-stat-value"><?php echo esc_html($anon_count); ?></div>
      <div class="nb-stat-sub">Records erased</div>
    </div>
  </div>

  <!-- ── Stat cards row 2: Form entries ── -->
  <div class="nb-section-head">
    <h2>Form Entries</h2>
    <a href="<?php echo esc_url(admin_url('edit.php?post_type=nb_form_entry')); ?>">View all &rarr;</a>
  </div>
  <div class="nb-stat-grid">
    <div class="nb-stat-card">
      <div class="nb-stat-label">Total</div>
      <div class="nb-stat-value"><?php echo esc_html($form_total); ?></div>
      <div class="nb-stat-sub">All forms combined</div>
    </div>
    <div class="nb-stat-card accent-blue">
      <div class="nb-stat-label">This week</div>
      <div class="nb-stat-value"><?php echo esc_html($form_week); ?></div>
      <div class="nb-stat-sub">Mon &ndash; today</div>
    </div>
    <div class="nb-stat-card accent-teal">
      <div class="nb-stat-label">This month</div>
      <div class="nb-stat-value"><?php echo esc_html($form_month); ?></div>
      <div class="nb-stat-sub"><?php echo esc_html(gmdate('F Y')); ?></div>
    </div>
  </div>

  <!-- ── Activity chart + Donut row ── -->
  <div class="nb-section-head"><h2>Activity — Last 30 Days</h2></div>
  <div class="nb-two-col" style="margin-bottom:24px">
    <div class="nb-chart-card">
      <h3>Consultations &amp; Form Entries</h3>
      <canvas id="nb-activity-chart"></canvas>
    </div>
    <div class="nb-chart-card">
      <h3>Consultations per Week (last 12 weeks)</h3>
      <canvas id="nb-weekly-chart"></canvas>
    </div>
  </div>

  <!-- ── Donut charts ── -->
  <div class="nb-donut-row">
    <div class="nb-donut-card">
      <h3>Payment Status</h3>
      <canvas id="nb-payment-donut"></canvas>
      <ul class="nb-donut-legend" style="list-style:none;padding:0;margin:0">
        <li><span style="background:#2e7d32"></span> Verified (<?php echo esc_html($pay_verified); ?>)</li>
        <li><span style="background:#e65100"></span> Pending (<?php echo esc_html($pay_pending); ?>)</li>
        <li><span style="background:#546e7a"></span> Other (<?php echo esc_html(max(0, $cons_total - $pay_verified - $pay_pending)); ?>)</li>
      </ul>
    </div>
    <div class="nb-donut-card">
      <h3>Invoice Status</h3>
      <canvas id="nb-invoice-donut"></canvas>
      <ul class="nb-donut-legend" style="list-style:none;padding:0;margin:0">
        <li><span style="background:#1565c0"></span> Sent (<?php echo esc_html($inv_sent); ?>)</li>
        <li><span style="background:#c62828"></span> Failed (<?php echo esc_html($inv_failed); ?>)</li>
        <li><span style="background:#bdbdbd"></span> Not yet (<?php echo esc_html(max(0, $cons_total - $inv_sent - $inv_failed)); ?>)</li>
      </ul>
    </div>
    <div class="nb-donut-card">
      <h3>Takeaway Email</h3>
      <canvas id="nb-takeaway-donut"></canvas>
      <ul class="nb-donut-legend" style="list-style:none;padding:0;margin:0">
        <li><span style="background:#00695c"></span> Sent (<?php echo esc_html($tw_sent); ?>)</li>
        <li><span style="background:#f57f17"></span> Pending (<?php echo esc_html($tw_pending); ?>)</li>
        <li><span style="background:#546e7a"></span> Skipped (<?php echo esc_html($tw_skipped); ?>)</li>
        <li><span style="background:#bdbdbd"></span> Not scheduled (<?php echo esc_html(max(0, $cons_total - $tw_sent - $tw_pending - $tw_skipped)); ?>)</li>
      </ul>
    </div>
  </div>

  <!-- ── System health ── -->
  <div class="nb-section-head"><h2>System Health</h2></div>
  <div class="nb-health-row">
    <div class="nb-health-card">
      <h3>Automation</h3>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Takeaway sweep (cron)</span>
        <span class="nb-health-item-value"><?php echo esc_html(wp_next_scheduled('nirog_bhumi_takeaway_sweep') ? 'Active' : 'NOT SCHEDULED'); ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Next sweep in</span>
        <span class="nb-health-item-value"><?php echo esc_html($cron_next); ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Cal.com autosend</span>
        <span class="nb-health-item-value"><?php
          $s = nirog_bhumi_get_settings();
          echo esc_html(($s['cal_autosend'] ?? 'yes') === 'yes' ? 'Enabled' : 'Disabled');
        ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Takeaway delay</span>
        <span class="nb-health-item-value"><?php echo esc_html(($s['takeaway_email_delay_minutes'] ?? 10)); ?> min</span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Booklet URL</span>
        <span class="nb-health-item-value"><?php echo ($s['takeaway_booklet_url'] ?? '') ? '<span class="nb-badge nb-badge-green">Set</span>' : '<span class="nb-badge nb-badge-orange">Not set</span>'; ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Cal.com webhook secret</span>
        <span class="nb-health-item-value"><?php echo ($s['cal_webhook_secret'] ?? '') ? '<span class="nb-badge nb-badge-green">Set</span>' : '<span class="nb-badge nb-badge-orange">Not set</span>'; ?></span>
      </div>
    </div>

    <div class="nb-health-card">
      <h3>Email / SMTP</h3>
      <?php
      $mail_ok = !$mail_err['error'];
      ?>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Last mail error</span>
        <span class="nb-health-item-value"><?php echo $mail_ok ? '<span class="nb-badge nb-badge-green">None</span>' : '<span class="nb-badge nb-badge-red">Error</span>'; ?></span>
      </div>
      <?php if (!$mail_ok) : ?>
      <div style="background:#fff8f8;border:1px solid #ffcdd2;border-radius:6px;padding:10px;margin-top:8px;font-size:11px;color:#c62828;word-break:break-word">
        <?php echo esc_html($mail_err['error']); ?>
        <?php if ($mail_err['at']) echo ' <em style="color:#80868b">(' . esc_html($mail_err['at']) . ')</em>'; ?>
      </div>
      <?php endif; ?>
      <div class="nb-health-item" style="margin-top:8px">
        <span class="nb-health-item-label">Lead alert recipients</span>
        <span class="nb-health-item-value" style="font-size:11px"><?php echo esc_html(implode(', ', nirog_bhumi_lead_notification_recipients())); ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Invoice sender email</span>
        <span class="nb-health-item-value" style="font-size:11px"><?php echo esc_html($s['invoice_email'] ?? 'priyanshu@nirogbhumi.com'); ?></span>
      </div>
    </div>

    <div class="nb-health-card">
      <h3>Invoice Config</h3>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Legal name</span>
        <span class="nb-health-item-value" style="font-size:11px"><?php echo esc_html($s['invoice_legal_name'] ?? 'Nirog Bhumi Pvt. Ltd.'); ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">GSTIN</span>
        <span class="nb-health-item-value" style="font-size:11px"><?php echo esc_html($s['invoice_gstin'] ?? '—'); ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">GST rate</span>
        <span class="nb-health-item-value"><?php echo esc_html($s['invoice_gst_rate'] ?? '18'); ?>%</span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Last invoice #</span>
        <span class="nb-health-item-value"><?php echo $inv_seq ? esc_html(str_pad($inv_seq, 3, '0', STR_PAD_LEFT)) : '—'; ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Invoices sent</span>
        <span class="nb-health-item-value"><?php echo esc_html($inv_sent); ?></span>
      </div>
      <div class="nb-health-item">
        <span class="nb-health-item-label">Invoice failures</span>
        <span class="nb-health-item-value"><?php echo $inv_failed ? '<span class="nb-badge nb-badge-red">' . esc_html($inv_failed) . '</span>' : '<span class="nb-badge nb-badge-green">0</span>'; ?></span>
      </div>
    </div>
  </div>

  <!-- ── Recent consultations ── -->
  <div class="nb-section-head">
    <h2>Recent Consultations</h2>
    <a href="<?php echo esc_url(admin_url('edit.php?post_type=nb_consultation')); ?>">View all &rarr;</a>
  </div>
  <div class="nb-activity-card" style="margin-bottom:24px">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Date</th>
          <th>Payment</th>
          <th>Invoice</th>
          <th>Takeaway</th>
          <th>Slot</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$recent_cons) : ?>
        <tr><td colspan="7" class="nb-empty">No consultations yet.</td></tr>
      <?php else : ?>
        <?php foreach ($recent_cons as $c) :
          $pay   = get_post_meta($c->ID, 'payment_status', true) ?: 'pending';
          $inv   = get_post_meta($c->ID, 'invoice_sent_at', true);
          $inv_e = get_post_meta($c->ID, 'invoice_error', true);
          $tw    = get_post_meta($c->ID, 'takeaway_email_sent_status', true);
          $slot_d = get_post_meta($c->ID, 'slot_date', true);
          $slot_t = get_post_meta($c->ID, 'slot_time', true);
          $name  = get_post_meta($c->ID, 'name', true) ?: $c->post_title;
          $anon  = nirog_bhumi_is_anonymised_record($c->ID);
        ?>
        <tr>
          <td>
            <?php if ($anon) : ?>
              <em style="color:#80868b">Anonymised</em>
            <?php else : ?>
              <a href="<?php echo esc_url(get_edit_post_link($c->ID)); ?>" style="font-weight:600;color:#1d2327;text-decoration:none"><?php echo esc_html($name ?: '—'); ?></a>
            <?php endif; ?>
          </td>
          <td style="color:#5f6368;font-size:11px"><?php echo esc_html(nirog_bhumi_local_date('d M Y', $c->post_date)); ?></td>
          <td>
            <?php if ($pay === 'verified') : ?>
              <span class="nb-badge nb-badge-green">Verified</span>
            <?php elseif ($pay === 'pending') : ?>
              <span class="nb-badge nb-badge-orange">Pending</span>
            <?php else : ?>
              <span class="nb-badge nb-badge-grey"><?php echo esc_html(ucfirst($pay)); ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($inv) : ?>
              <span class="nb-badge nb-badge-blue">Sent</span>
            <?php elseif ($inv_e) : ?>
              <span class="nb-badge nb-badge-red" title="<?php echo esc_attr($inv_e); ?>">Failed</span>
            <?php else : ?>
              <span class="nb-badge nb-badge-grey">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($tw === 'sent') : ?>
              <span class="nb-badge nb-badge-green">Sent</span>
            <?php elseif ($tw === 'pending') : ?>
              <span class="nb-badge nb-badge-orange">Pending</span>
            <?php elseif ($tw === 'skipped') : ?>
              <span class="nb-badge nb-badge-grey">Skipped</span>
            <?php else : ?>
              <span class="nb-badge nb-badge-grey">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:11px;color:#5f6368">
            <?php echo $slot_d ? esc_html($slot_d . ($slot_t ? ' ' . $slot_t : '')) : '—'; ?>
          </td>
          <td>
            <a href="<?php echo esc_url(get_edit_post_link($c->ID)); ?>" style="font-size:11px;color:#2e7d32">Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Recent form entries ── -->
  <div class="nb-section-head">
    <h2>Recent Form Entries</h2>
    <a href="<?php echo esc_url(admin_url('edit.php?post_type=nb_form_entry')); ?>">View all &rarr;</a>
  </div>
  <div class="nb-activity-card" style="margin-bottom:40px">
    <table>
      <thead>
        <tr><th>Name</th><th>Form / Source</th><th>Date</th><th>Email</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$recent_forms) : ?>
        <tr><td colspan="5" class="nb-empty">No form entries yet.</td></tr>
      <?php else : ?>
        <?php foreach ($recent_forms as $f) :
          $fname = get_post_meta($f->ID, 'name', true) ?: $f->post_title;
          $femail = get_post_meta($f->ID, 'email', true);
          $fsource = get_post_meta($f->ID, 'form_source', true) ?: get_post_meta($f->ID, 'source', true) ?: '—';
          $anon = nirog_bhumi_is_anonymised_record($f->ID);
        ?>
        <tr>
          <td>
            <?php if ($anon) : ?>
              <em style="color:#80868b">Anonymised</em>
            <?php else : ?>
              <a href="<?php echo esc_url(get_edit_post_link($f->ID)); ?>" style="font-weight:600;color:#1d2327;text-decoration:none"><?php echo esc_html($fname ?: '—'); ?></a>
            <?php endif; ?>
          </td>
          <td style="font-size:11px;color:#5f6368"><?php echo esc_html(ucwords(str_replace(['-','_'], ' ', $fsource))); ?></td>
          <td style="font-size:11px;color:#5f6368"><?php echo esc_html(nirog_bhumi_local_date('d M Y, g:i a', $f->post_date)); ?></td>
          <td style="font-size:11px;color:#5f6368"><?php echo $anon ? '—' : esc_html($femail ?: '—'); ?></td>
          <td><a href="<?php echo esc_url(get_edit_post_link($f->ID)); ?>" style="font-size:11px;color:#2e7d32">View</a></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Chart.js ── -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = "-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#5f6368';

    var labels = <?php echo wp_json_encode(array_map(function($d){ return gmdate('d M', strtotime($d)); }, $chart_labels)); ?>;
    var consData = <?php echo wp_json_encode($chart_cons); ?>;
    var formData = <?php echo wp_json_encode($chart_forms); ?>;

    // Activity line chart
    new Chart(document.getElementById('nb-activity-chart'), {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Consultations',
            data: consData,
            borderColor: '#2e7d32',
            backgroundColor: 'rgba(46,125,50,0.08)',
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.35,
            fill: true,
          },
          {
            label: 'Form Entries',
            data: formData,
            borderColor: '#1565c0',
            backgroundColor: 'rgba(21,101,192,0.06)',
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.35,
            fill: true,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 14 } } },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f0f0f0' } }
        }
      }
    });

    // Weekly bar chart (aggregate labels and data from daily)
    (function(){
      var weekLabels = [], weekCons = [], acc = 0, dayCount = 0;
      var allDates = <?php echo wp_json_encode($chart_labels); ?>;
      var allCons  = <?php echo wp_json_encode($chart_cons); ?>;
      for (var i = 0; i < allDates.length; i++) {
        var d = new Date(allDates[i]);
        acc += allCons[i]; dayCount++;
        if (d.getDay() === 0 || i === allDates.length - 1) {
          weekLabels.push('W' + (weekLabels.length + 1));
          weekCons.push(acc);
          acc = 0; dayCount = 0;
        }
      }
      new Chart(document.getElementById('nb-weekly-chart'), {
        type: 'bar',
        data: {
          labels: weekLabels,
          datasets: [{
            label: 'Consultations',
            data: weekCons,
            backgroundColor: 'rgba(46,125,50,0.75)',
            borderRadius: 4,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f0f0f0' } }
          }
        }
      });
    })();

    // Payment donut
    new Chart(document.getElementById('nb-payment-donut'), {
      type: 'doughnut',
      data: {
        labels: ['Verified', 'Pending', 'Other'],
        datasets: [{ data: [<?php echo esc_js($pay_verified); ?>, <?php echo esc_js($pay_pending); ?>, <?php echo esc_js(max(0, $cons_total - $pay_verified - $pay_pending)); ?>], backgroundColor: ['#2e7d32','#e65100','#90a4ae'], borderWidth: 0, hoverOffset: 4 }]
      },
      options: { responsive: true, cutout: '68%', plugins: { legend: { display: false } } }
    });

    // Invoice donut
    new Chart(document.getElementById('nb-invoice-donut'), {
      type: 'doughnut',
      data: {
        labels: ['Sent', 'Failed', 'Not yet'],
        datasets: [{ data: [<?php echo esc_js($inv_sent); ?>, <?php echo esc_js($inv_failed); ?>, <?php echo esc_js(max(0, $cons_total - $inv_sent - $inv_failed)); ?>], backgroundColor: ['#1565c0','#c62828','#bdbdbd'], borderWidth: 0, hoverOffset: 4 }]
      },
      options: { responsive: true, cutout: '68%', plugins: { legend: { display: false } } }
    });

    // Takeaway donut
    new Chart(document.getElementById('nb-takeaway-donut'), {
      type: 'doughnut',
      data: {
        labels: ['Sent', 'Pending', 'Skipped', 'Not scheduled'],
        datasets: [{ data: [<?php echo esc_js($tw_sent); ?>, <?php echo esc_js($tw_pending); ?>, <?php echo esc_js($tw_skipped); ?>, <?php echo esc_js(max(0, $cons_total - $tw_sent - $tw_pending - $tw_skipped)); ?>], backgroundColor: ['#00695c','#f57f17','#546e7a','#bdbdbd'], borderWidth: 0, hoverOffset: 4 }]
      },
      options: { responsive: true, cutout: '68%', plugins: { legend: { display: false } } }
    });
  });
  </script>
  </div>
  <?php
}
