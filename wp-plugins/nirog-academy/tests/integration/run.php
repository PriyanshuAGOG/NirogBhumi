<?php
/**
 * Integration tests. Run on a STAGING site only:
 *
 *   wp eval-file wp-content/plugins/nirog-academy/tests/integration/run.php
 *
 * Creates its own programme, lessons and users, checks the core rules, and
 * deletes everything it created at the end.
 */

use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Enrollment\WooBridge;
use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

$results = [];
$created_posts = [];
$created_users = [];

$check = static function (string $name, bool $ok, string $detail = '') use (&$results): void {
  $results[] = $ok;
  echo ($ok ? 'PASS  ' : 'FAIL  ') . $name . ($detail !== '' ? " — $detail" : '') . "\n";
};

$post = static function (array $args, array $meta = []) use (&$created_posts): int {
  $id = wp_insert_post($args + ['post_status' => 'publish']);
  foreach ($meta as $k => $v) {
    update_post_meta($id, $k, $v);
  }
  $created_posts[] = $id;
  return $id;
};

$user = static function (string $email) use (&$created_users): int {
  $existing = get_user_by('email', $email);
  if ($existing) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($existing->ID);
  }
  $id = wp_insert_user(['user_login' => sanitize_user(strstr($email, '@', true)), 'user_email' => $email, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
  $created_users[] = $id;
  return $id;
};

// Run from WP-CLI, output has started, so allow the device cookie to be "set".
add_filter('nba_can_set_device_cookie', '__return_true');

$old_settings = nba()->settings->all();
nba()->settings->update(['launch_mode' => 'open', 'max_devices' => 1, 'completion_threshold' => 90]);

// ---------------------------------------------------------------- fixtures
$prog = $post(['post_type' => PostTypes::PROGRAMME, 'post_title' => 'NBA Test Programme', 'post_name' => 'nba-test-programme'], [
  '_nba_access_days' => 365, '_nba_drip_mode' => 'enrollment', '_nba_sequential' => '0',
  '_nba_ack_text' => 'Test acknowledgement', '_nba_ack_version' => 'v1',
]);
$mod = $post(['post_type' => PostTypes::MODULE, 'post_title' => 'Week 1'], ['_nba_programme_id' => $prog]);
$l0 = $post(['post_type' => PostTypes::LESSON, 'post_title' => 'Day 0 lesson', 'menu_order' => 0], ['_nba_programme_id' => $prog, '_nba_module_id' => $mod, '_nba_release_day' => 0, '_nba_type' => 'reading']);
$l7 = $post(['post_type' => PostTypes::LESSON, 'post_title' => 'Day 7 lesson', 'menu_order' => 1], ['_nba_programme_id' => $prog, '_nba_module_id' => $mod, '_nba_release_day' => 7, '_nba_type' => 'reading']);
$media = $post(['post_type' => PostTypes::MEDIA, 'post_title' => 'Test media'], ['_nba_provider' => 'hls', '_nba_path' => 'does-not-exist']);
$lv = $post(['post_type' => PostTypes::LESSON, 'post_title' => 'Video lesson', 'menu_order' => 2], ['_nba_programme_id' => $prog, '_nba_module_id' => $mod, '_nba_release_day' => 0, '_nba_type' => 'video', '_nba_media_id' => $media]);
nba()->curriculum->flush($prog);

$u = $user('nba-test-participant@example.com');
$_COOKIE = [];
nba()->devices->forget();

// ---------------------------------------------------------------- tree
$tree = nba()->curriculum->tree($prog);
$check('curriculum tree lists lessons in order', $tree['lessons'] === [$l0, $l7, $lv], implode(',', $tree['lessons']));

// ---------------------------------------------------------------- not enrolled
$r = nba()->gate->check_lesson($u, $l0, true);
$check('not enrolled → denied', !$r->allowed && $r->code === 'enrollment', $r->code);

// ---------------------------------------------------------------- enrolled
$eid = nba()->enrollments->create(['user_id' => $u, 'programme_id' => $prog, 'source' => 'manual']);
$e = nba()->enrollments->get((int) $eid);
$days = (int) round((Time::ts($e->ends_at) - Time::ts($e->starts_at)) / DAY_IN_SECONDS);
$check('enrollment lasts the programme access length', $days === 365, "$days days");
$check('enrolled user gets the participant role', in_array('nba_participant', get_userdata($u)->roles, true));

nba()->devices->forget();
$r = (new NirogBhumi\Academy\Access\Gate())->check_lesson($u, $l0, true);
$check('day-0 lesson needs the safety acknowledgement first', !$r->allowed && $r->code === 'ack', $r->code);
nba()->gate->acknowledge($u, $prog);
$gate = new NirogBhumi\Academy\Access\Gate();
$r = $gate->check_lesson($u, $l0, true);
$check('day-0 lesson open after acknowledgement', $r->allowed, $r->code);
$r = $gate->check_lesson($u, $l7, true);
$check('day-7 lesson locked with a date', !$r->allowed && $r->code === 'release' && !empty($r->data['opens_at']), $r->code);

// Move the start back 8 days: day-7 lesson should open.
global $wpdb;
$wpdb->update(Installer::table('enrollments'), ['starts_at' => Time::add_days(Time::now(), -8)], ['id' => $eid]);
nba()->enrollments->forget();
$gate = new NirogBhumi\Academy\Access\Gate();
$check('day-7 lesson opens after 7 days', $gate->check_lesson($u, $l7, true)->allowed);

// ---------------------------------------------------------------- sequential
update_post_meta($prog, '_nba_sequential', '1');
$gate = new NirogBhumi\Academy\Access\Gate();
$r = $gate->check_lesson($u, $l7, true);
$check('sequential programme locks the next lesson', !$r->allowed && $r->code === 'sequence', $r->code);
nba()->progress->complete($u, $l0, $prog);
$gate = new NirogBhumi\Academy\Access\Gate();
$check('completing the previous lesson unlocks it', $gate->check_lesson($u, $l7, true)->allowed);
update_post_meta($prog, '_nba_sequential', '0');

// ---------------------------------------------------------------- progress anti-cheat
$res = nba()->progress->complete($u, $lv, $prog);
$check('video lesson cannot be marked complete unwatched', is_wp_error($res));
$res = nba()->progress->heartbeat($u, $lv, $prog, 600, 600, [[0, 600]]);
$check('first report cannot credit a whole 10-minute video', !$res['completed'] && $res['watched_pct'] < 30, $res['watched_pct'] . '%');
// Simulate honest viewing: ten reports, each after enough real time.
$table = Installer::table('progress');
for ($i = 0; $i < 10; $i++) {
  $wpdb->update($table, ['updated_at' => gmdate('Y-m-d H:i:s', time() - 70)], ['user_id' => $u, 'lesson_id' => $lv]);
  $res = nba()->progress->heartbeat($u, $lv, $prog, ($i + 1) * 60, 600, [[$i * 60, ($i + 1) * 60]]);
}
$check('honest viewing completes the video lesson', $res['completed'], $res['watched_pct'] . '%');
$summary = nba()->progress->summary($u, $prog);
$check('progress summary counts completions', $summary['completed'] === 2 && $summary['total'] === 3, $summary['completed'] . '/' . $summary['total']);

// ---------------------------------------------------------------- pause / resume / extend / expire
nba()->enrollments->set_status((int) $eid, 'paused', 'test');
$gate = new NirogBhumi\Academy\Access\Gate();
$check('paused enrollment is denied', $gate->check_lesson($u, $l0, true)->code === 'paused');
$wpdb->update(Installer::table('enrollments'), ['paused_at' => Time::add_days(Time::now(), -5)], ['id' => $eid]);
$before = nba()->enrollments->get((int) $eid)->ends_at;
nba()->enrollments->set_status((int) $eid, 'active', 'test');
$after = nba()->enrollments->get((int) $eid)->ends_at;
$check('resume adds the paused days back', Time::days_between($before, $after) === 5, Time::days_between($before, $after) . ' days');
nba()->enrollments->extend((int) $eid, 30);
$check('extend adds days', Time::days_between($after, nba()->enrollments->get((int) $eid)->ends_at) === 30);
$wpdb->update(Installer::table('enrollments'), ['ends_at' => Time::add_days(Time::now(), -1)], ['id' => $eid]);
nba()->enrollments->expire_due();
$check('daily job expires ended access', nba()->enrollments->get((int) $eid)->status === 'expired');
$gate = new NirogBhumi\Academy\Access\Gate();
$check('expired enrollment is denied', $gate->check_lesson($u, $l0, true)->code === 'window');

// ---------------------------------------------------------------- devices
$u2 = $user('nba-test-device@example.com');
nba()->enrollments->create(['user_id' => $u2, 'programme_id' => $prog]);
$_COOKIE = [];
nba()->devices->forget();
$first = nba()->devices->verify($u2);
$check('first browser is registered', $first['ok'] && $first['registered_now']);
$_COOKIE = []; // A different browser: no device cookie.
nba()->devices->forget();
$second = nba()->devices->verify($u2);
$check('second browser is blocked', !$second['ok']);
nba()->devices->admin_reset($u2, 'test');
$_COOKIE = [];
$third = nba()->devices->verify($u2);
$check('after a staff reset the next browser is registered', $third['ok'] && $third['registered_now']);
$check('self-service change blocked within the interval', !nba()->devices->can_self_reset($u2));

// ---------------------------------------------------------------- batches
$prog_b = $post(['post_type' => PostTypes::PROGRAMME, 'post_title' => 'NBA Batch Programme'], ['_nba_access_days' => 99, '_nba_drip_mode' => 'cohort', '_nba_cohort_assignment' => 'auto']);
$u3 = $user('nba-test-batch@example.com');
$pending = nba()->enrollments->create(['user_id' => $u3, 'programme_id' => $prog_b]);
$check('batch programme without an open batch → pending', nba()->enrollments->get((int) $pending)->status === 'pending');
$start_local = wp_date('Y-m-d\TH:i', time() + 10 * DAY_IN_SECONDS);
$cohort = nba()->cohorts->save(['programme_id' => $prog_b, 'name' => 'Test batch', 'starts_at' => $start_local]);
nba()->enrollments->move_cohort((int) $pending, (int) $cohort);
$row = nba()->enrollments->get((int) $pending);
$check('moving into a batch activates and starts at the batch date', $row->status === 'active' && $row->starts_at === nba()->cohorts->get((int) $cohort)->starts_at);
$u4 = $user('nba-test-batch2@example.com');
$auto = nba()->enrollments->create(['user_id' => $u4, 'programme_id' => $prog_b]);
$check('new buyer joins the next open batch automatically', (int) nba()->enrollments->get((int) $auto)->cohort_id === (int) $cohort);

// ---------------------------------------------------------------- WooCommerce
if (function_exists('wc_create_order')) {
  $product = new WC_Product_Simple();
  $product->set_name('NBA Test Product');
  $product->set_regular_price('100');
  $product->set_virtual(true);
  $product->update_meta_data('_nba_programme_ids', [$prog]);
  $pid = $product->save();
  $created_posts[] = $pid;
  $email = 'nba-test-buyer@example.com';
  if ($existing = get_user_by('email', $email)) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($existing->ID);
  }
  $order = wc_create_order();
  $order->add_product(wc_get_product($pid), 1);
  $order->set_billing_email($email);
  $order->calculate_totals();
  $order->save();
  $order->payment_complete('NBA-TEST');
  $buyer = get_user_by('email', $email);
  if ($buyer) {
    $created_users[] = $buyer->ID;
  }
  $rows = nba()->enrollments->for_order($order->get_id());
  $check('paid guest order creates an account and an enrollment', $buyer && count($rows) === 1 && $rows[0]->status === 'active');
  do_action('woocommerce_order_status_completed', $order->get_id());
  $check('repeated order events do not duplicate', count(nba()->enrollments->for_order($order->get_id())) === 1);
  $order = wc_get_order($order->get_id());
  $order->update_status('refunded');
  $rows = nba()->enrollments->for_order($order->get_id());
  $check('refund revokes the access', $rows && $rows[0]->status === 'revoked');
  $order->delete(true);
}

// ---------------------------------------------------------------- notifications
$n1 = nba()->notifications->create($u, 'test', 'Hello', '', '', 'dedupe-test');
$n2 = nba()->notifications->create($u, 'test', 'Hello again', '', '', 'dedupe-test');
$check('notification dedupe keys prevent repeats', $n1 > 0 && $n2 === 0);

// ---------------------------------------------------------------- booklets
$bk = $post(['post_type' => PostTypes::LESSON, 'post_title' => 'Week 1 booklet', 'menu_order' => 3], ['_nba_programme_id' => $prog, '_nba_module_id' => $mod, '_nba_release_day' => 0, '_nba_type' => 'booklet', '_nba_file' => 'booklets/does-not-matter.pdf']);
nba()->curriculum->flush($prog);
$r1 = nba()->progress->page_view($u2, $bk, $prog, 1, 10);
$check('booklet: one page of ten is not complete', !$r1['completed'] && $r1['watched_pct'] === 10);
for ($pg = 2; $pg <= 9; $pg++) {
  $r1 = nba()->progress->page_view($u2, $bk, $prog, $pg, 10);
}
$check('booklet: nine of ten pages completes it (90%)', $r1['completed']);
$check('booklet: page count remembered for the library', (int) get_post_meta($bk, '_nba_pages', true) === 10);

// ---------------------------------------------------------------- studio
$studio_item = NirogBhumi\Academy\Studio\Studio::create_item($prog, ['type' => 'booklet', 'week' => $mod]);
$created_posts[] = (int) $studio_item;
$check('studio: new item starts as a hidden draft in its week', get_post_status((int) $studio_item) === 'draft' && (int) get_post_meta((int) $studio_item, '_nba_module_id', true) === $mod);
NirogBhumi\Academy\Studio\Studio::update_item((int) $studio_item, ['title' => 'Renamed', 'day' => 5, 'status' => 'publish', 'type' => 'not-a-type']);
$check('studio: edits save and bad values are cleaned', get_the_title((int) $studio_item) === 'Renamed' && (int) get_post_meta((int) $studio_item, '_nba_release_day', true) === 5 && get_post_meta((int) $studio_item, '_nba_type', true) === 'video');
NirogBhumi\Academy\Studio\Studio::reorder($prog, [['id' => $mod, 'items' => [(int) $studio_item, $l0]]], [$l7]);
$tree = nba()->curriculum->tree($prog);
$check('studio: drag and drop order is saved', ($tree['lessons'][0] ?? 0) === (int) $studio_item && in_array($l7, $tree['bonus'], true));
wp_set_current_user($u2);
$denied = rest_do_request(new WP_REST_Request('GET', '/nirog-academy/v1/studio/programmes'));
$check('studio: participants cannot use the Studio API', in_array($denied->get_status(), [401, 403], true), (string) $denied->get_status());
wp_set_current_user(0);

// ---------------------------------------------------------------- cleanup
foreach ($created_users as $id) {
  $wpdb->delete(Installer::table('enrollments'), ['user_id' => $id]);
  $wpdb->delete(Installer::table('progress'), ['user_id' => $id]);
  $wpdb->delete(Installer::table('devices'), ['user_id' => $id]);
  $wpdb->delete(Installer::table('notifications'), ['user_id' => $id]);
  $wpdb->delete(Installer::table('acknowledgements'), ['user_id' => $id]);
  $wpdb->delete(Installer::table('audit_log'), ['subject_user_id' => $id]);
  require_once ABSPATH . 'wp-admin/includes/user.php';
  wp_delete_user($id);
}
$wpdb->delete(Installer::table('cohorts'), ['programme_id' => $prog_b]);
foreach ($created_posts as $id) {
  wp_delete_post($id, true);
}
update_option(NirogBhumi\Academy\Support\Settings::OPTION, $old_settings);

$failed = count(array_filter($results, static fn($ok) => !$ok));
echo "\n" . (count($results) - $failed) . '/' . count($results) . " checks passed\n";
if ($failed && defined('WP_CLI')) {
  WP_CLI::halt(1);
}
