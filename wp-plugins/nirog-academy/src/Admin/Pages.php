<?php
namespace NirogBhumi\Academy\Admin;

use NirogBhumi\Academy\Audit\AuditLog;
use NirogBhumi\Academy\Content\MetaBoxes;
use NirogBhumi\Academy\Content\PostTypes;
use NirogBhumi\Academy\Enrollment\Enrollments;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Install\Installer;
use NirogBhumi\Academy\Support\Docs;
use NirogBhumi\Academy\Support\Settings;
use NirogBhumi\Academy\Support\Storage;
use NirogBhumi\Academy\Support\Time;

defined('ABSPATH') || exit;

/**
 * Admin screens. Plain WordPress admin markup so they feel familiar.
 */
final class Pages {
  // phpcs:disable WordPress.Security.NonceVerification.Recommended -- these screens only read query args for display and filtering.

  private static function form(string $action, string $back = ''): void {
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="nba_' . esc_attr($action) . '">';
    if ($back) {
      echo '<input type="hidden" name="_back" value="' . esc_attr($back) . '">';
    }
    wp_nonce_field('nba_' . $action);
  }

  private static function header(string $title, string $intro = ''): void {
    echo '<div class="wrap nba-admin"><h1>' . esc_html($title) . '</h1>';
    if ($intro) {
      echo '<p class="nba-intro">' . esc_html($intro) . '</p>';
    }
  }

  private static function footer(): void {
    echo '</div>';
  }

  private static function status_badge(string $status): string {
    return '<span class="nba-badge nba-badge-' . esc_attr($status) . '">' . esc_html(Enrollments::status_label($status)) . '</span>';
  }

  private static function participant_link(int $user_id, string $label = ''): string {
    $user = get_userdata($user_id);
    $label = $label ?: ($user ? $user->display_name : '#' . $user_id);
    return '<a href="' . esc_url(admin_url('admin.php?page=nba-participant&user=' . $user_id)) . '">' . esc_html($label) . '</a>';
  }

  private static function cohort_options(int $programme_id = 0, bool $empty = true): array {
    $out = $empty ? [0 => __('— No batch —', 'nirog-academy')] : [];
    foreach (nba()->cohorts->all($programme_id) as $c) {
      $out[(int) $c->id] = ($programme_id ? '' : get_the_title((int) $c->programme_id) . ' — ') . $c->name . ' (' . Time::human($c->starts_at, 'j M Y') . ')';
    }
    return $out;
  }

  private static function select(string $name, array $options, int|string $current = '', string $extra = ''): string {
    $html = '<select name="' . esc_attr($name) . '" ' . $extra . '>';
    foreach ($options as $value => $label) {
      $html .= '<option value="' . esc_attr((string) $value) . '"' . selected((string) $current, (string) $value, false) . '>' . esc_html($label) . '</option>';
    }
    return $html . '</select>';
  }

  private static function pagination(int $total, int $per_page, int $page): void {
    $pages = (int) ceil($total / $per_page);
    if ($pages <= 1) {
      return;
    }
    echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links([
      'base' => add_query_arg('paged', '%#%'),
      'format' => '',
      'current' => $page,
      'total' => $pages,
    ]) . '</div></div>';
  }

  // ------------------------------------------------------------ overview

  public static function overview(): void {
    global $wpdb;
    self::header(__('Overview', 'nirog-academy'));
    $counts = nba()->enrollments->count_by_status();
    $table = Installer::table('enrollments');
    $month = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE created_at >= %s", gmdate('Y-m-01 00:00:00'))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $review = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE needs_review = 1"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $pending_devices = count(nba()->devices->requests('pending'));

    echo '<div class="nba-cards">';
    $cards = [
      [__('Active participants', 'nirog-academy'), $counts['active'], admin_url('admin.php?page=nba-enrollments&status=active')],
      [__('New this month', 'nirog-academy'), $month, admin_url('admin.php?page=nba-enrollments')],
      [__('Paused', 'nirog-academy'), $counts['paused'], admin_url('admin.php?page=nba-enrollments&status=paused')],
      [__('Need review', 'nirog-academy'), $review + $counts['pending'], admin_url('admin.php?page=nba-enrollments&needs_review=1')],
      [__('Device requests', 'nirog-academy'), $pending_devices, admin_url('admin.php?page=nba-devices')],
    ];
    foreach ($cards as [$label, $value, $url]) {
      echo '<a class="nba-card" href="' . esc_url($url) . '"><span class="nba-card-value">' . (int) $value . '</span><span class="nba-card-label">' . esc_html($label) . '</span></a>';
    }
    echo '</div>';

    // Needs attention: active participants who have gone quiet, or whose access ends soon.
    $quiet = [];
    $ending = [];
    foreach (nba()->enrollments->all_active() as $e) {
      $last = Time::ts((string) get_user_meta((int) $e->user_id, 'nba_last_active', true));
      if (Time::ts($e->starts_at) < time() - 5 * DAY_IN_SECONDS && $last < time() - 5 * DAY_IN_SECONDS) {
        $quiet[] = [$e, $last];
      }
      if ($e->ends_at && Time::ts($e->ends_at) < time() + 14 * DAY_IN_SECONDS) {
        $ending[] = $e;
      }
    }
    usort($quiet, static fn($a, $b) => $a[1] <=> $b[1]);

    echo '<div class="nba-columns"><div class="nba-panel"><h2>' . esc_html__('Need a check-in', 'nirog-academy') . '</h2>';
    echo '<p class="description">' . esc_html__('Active for 5+ days but not seen in the last 5 days. A short message often helps.', 'nirog-academy') . '</p>';
    if (!$quiet) {
      echo '<p>' . esc_html__('Everyone has been active recently.', 'nirog-academy') . '</p>';
    } else {
      echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Participant', 'nirog-academy') . '</th><th>' . esc_html__('Programme', 'nirog-academy') . '</th><th>' . esc_html__('Last seen', 'nirog-academy') . '</th><th>' . esc_html__('Progress', 'nirog-academy') . '</th></tr></thead><tbody>';
      foreach (array_slice($quiet, 0, 15) as [$e, $last]) {
        $summary = nba()->progress->summary((int) $e->user_id, (int) $e->programme_id);
        echo '<tr><td>' . self::participant_link((int) $e->user_id) . '</td><td>' . esc_html(get_the_title((int) $e->programme_id)) . '</td><td>' . ($last ? esc_html(human_time_diff($last) . ' ' . __('ago', 'nirog-academy')) : esc_html__('Never', 'nirog-academy')) . '</td><td>' . (int) $summary['pct'] . '%</td></tr>';
      }
      echo '</tbody></table>';
    }
    echo '</div><div class="nba-panel"><h2>' . esc_html__('Access ending in 14 days', 'nirog-academy') . '</h2>';
    if (!$ending) {
      echo '<p>' . esc_html__('None.', 'nirog-academy') . '</p>';
    } else {
      echo '<ul>';
      foreach (array_slice($ending, 0, 15) as $e) {
        echo '<li>' . self::participant_link((int) $e->user_id) . ' — ' . esc_html(get_the_title((int) $e->programme_id)) . ', ' . esc_html(Time::human($e->ends_at)) . '</li>';
      }
      echo '</ul>';
    }
    echo '<h2>' . esc_html__('Next live sessions', 'nirog-academy') . '</h2>';
    $live = get_posts(['post_type' => PostTypes::LIVE, 'numberposts' => 5, 'meta_key' => '_nba_starts_at', 'orderby' => 'meta_value', 'order' => 'ASC', 'meta_query' => [['key' => '_nba_starts_at', 'value' => Time::now(), 'compare' => '>=', 'type' => 'DATETIME']]]);
    if (!$live) {
      echo '<p>' . esc_html__('None scheduled.', 'nirog-academy') . ' <a href="' . esc_url(admin_url('post-new.php?post_type=' . PostTypes::LIVE)) . '">' . esc_html__('Schedule one', 'nirog-academy') . '</a></p>';
    } else {
      echo '<ul>';
      foreach ($live as $s) {
        echo '<li><a href="' . esc_url((string) get_edit_post_link($s->ID)) . '">' . esc_html($s->post_title) . '</a> — ' . esc_html(Time::human_time((string) get_post_meta($s->ID, '_nba_starts_at', true))) . '</li>';
      }
      echo '</ul>';
    }
    echo '</div></div>';

    echo '<p class="nba-quicklinks"><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=nba-enrollments&tab=enroll')) . '">' . esc_html__('Enroll someone', 'nirog-academy') . '</a> ';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=nba-enrollments&tab=offer')) . '">' . esc_html__('Send a payment link', 'nirog-academy') . '</a> ';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=nirog-academy')) . '">' . esc_html__('Open Course Studio', 'nirog-academy') . '</a> ';
    echo '<a class="button" href="' . esc_url(Router::url()) . '" target="_blank" rel="noopener">' . esc_html__('View as a participant', 'nirog-academy') . '</a></p>';
    self::footer();
  }

  // ------------------------------------------------------------ live desk

  /**
   * For hosts and the technical team: every session in the coming days with
   * its link, private notes, host and attendance, in one place.
   */
  public static function live_desk(): void {
    $mine = isset($_GET['mine']) ? (bool) absint($_GET['mine']) : !current_user_can('edit_nba_items');
    $me = get_current_user_id();
    $sessions = get_posts([
      'post_type' => PostTypes::LIVE,
      'post_status' => ['publish', 'draft'],
      'numberposts' => 100,
      'meta_key' => '_nba_starts_at',
      'orderby' => 'meta_value',
      'order' => 'ASC',
      'meta_query' => [['key' => '_nba_starts_at', 'value' => [Time::add_days(Time::now(), -7), Time::add_days(Time::now(), 21)], 'compare' => 'BETWEEN', 'type' => 'DATETIME']],
    ]);
    $groups = ['now' => [], 'today' => [], 'soon' => [], 'past' => []];
    $today_end = (new \DateTimeImmutable('tomorrow', wp_timezone()))->getTimestamp();
    foreach ($sessions as $s) {
      $info = \NirogBhumi\Academy\Live\LiveSessions::info($s);
      if ($mine && $info['host'] !== $me) {
        continue;
      }
      $info['status'] = $s->post_status;
      $key = match (true) {
        in_array($info['phase'], ['open', 'live'], true) => 'now',
        $info['phase'] === 'ended' => 'past',
        $info['start'] < $today_end => 'today',
        default => 'soon',
      };
      $groups[$key][] = $info;
    }
    $groups['past'] = array_reverse($groups['past']);

    self::header(__('Live desk', 'nirog-academy'), __('Everything the host and the technical team need for live sessions: when, the meeting link, private notes and who has joined. Sessions are created and edited in the Course Studio.', 'nirog-academy'));
    echo '<p><a href="' . esc_url(add_query_arg('mine', $mine ? 0 : 1)) . '">' . esc_html($mine ? __('Show all sessions', 'nirog-academy') : __('Show only sessions I host', 'nirog-academy')) . '</a>';
    if (current_user_can('edit_nba_items')) {
      echo ' · <a href="' . esc_url(admin_url('admin.php?page=nirog-academy')) . '">' . esc_html__('Course Studio', 'nirog-academy') . '</a>';
    }
    echo '</p>';
    $titles = [
      'now' => __('Happening now', 'nirog-academy'),
      'today' => __('Later today', 'nirog-academy'),
      'soon' => __('Next three weeks', 'nirog-academy'),
      'past' => __('Last seven days', 'nirog-academy'),
    ];
    $any = false;
    foreach ($groups as $key => $list) {
      if (!$list) {
        continue;
      }
      $any = true;
      echo '<h2>' . esc_html($titles[$key]) . '</h2><div class="nba-desk">';
      foreach ($list as $i) {
        $attended = \NirogBhumi\Academy\Live\LiveSessions::attendance_count($i['id']);
        echo '<article class="nba-desk-row' . ($key === 'now' ? ' is-now' : '') . ($i['cancelled'] ? ' is-cancelled' : '') . '">';
        echo '<div class="nba-desk-when"><strong>' . esc_html(wp_date('D j M', $i['start'])) . '</strong><br>' . esc_html(wp_date('g:i a', $i['start']) . ' – ' . wp_date('g:i a', $i['end'])) . '</div>';
        echo '<div class="nba-desk-main"><strong class="nba-desk-title">' . esc_html($i['title']) . '</strong>';
        $bits = [get_the_title($i['programme']), $i['kind'] === 'stream' ? __('YouTube Live', 'nirog-academy') : __('Meeting', 'nirog-academy'), $i['host_name'] ? sprintf(
          /* translators: %s: host */
          __('Host: %s', 'nirog-academy'),
          $i['host_name']
        ) : __('No host chosen', 'nirog-academy')];
        if ($i['cancelled']) {
          $bits[] = __('CANCELLED', 'nirog-academy');
        } elseif ($i['status'] !== 'publish') {
          $bits[] = __('Draft — participants cannot see it', 'nirog-academy');
        }
        echo '<div class="description">' . esc_html(implode(' · ', array_filter($bits))) . '</div>';
        if ($i['notes']) {
          echo '<div class="nba-desk-notes">' . nl2br(esc_html($i['notes'])) . '</div>';
        }
        echo '</div><div class="nba-desk-actions">';
        if ($i['link']) {
          echo '<a class="button button-primary" href="' . esc_url($i['link']) . '" target="_blank" rel="noopener">' . esc_html($i['kind'] === 'stream' ? __('Open YouTube', 'nirog-academy') : __('Open meeting', 'nirog-academy')) . '</a> ';
        } else {
          echo '<span class="description">' . esc_html__('No link yet', 'nirog-academy') . '</span> ';
        }
        echo '<a class="button" href="' . esc_url(\NirogBhumi\Academy\Live\LiveSessions::url($i['id'])) . '" target="_blank" rel="noopener">' . esc_html__('Participant page', 'nirog-academy') . '</a>';
        if ($key !== 'past' && !$i['cancelled'] && $i['status'] === 'publish') {
          self::form('live_remind');
          echo '<input type="hidden" name="session_id" value="' . (int) $i['id'] . '"><button class="button" onclick="return confirm(\'' . esc_js(__('Send a reminder to everyone in this session now?', 'nirog-academy')) . '\')">' . esc_html__('Send reminder', 'nirog-academy') . '</button></form>';
        }
        echo '<details><summary>' . esc_html(sprintf(
          /* translators: %d: number of people */
          _n('%d joined', '%d joined', $attended, 'nirog-academy'),
          $attended
        )) . '</summary><ul>';
        foreach (\NirogBhumi\Academy\Live\LiveSessions::attendance($i['id']) as $p) {
          echo '<li>' . self::participant_link($p['user_id'], $p['name']) . ' <span class="description">' . esc_html($p['joined']) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</ul></details></div></article>';
      }
      echo '</div>';
    }
    if (!$any) {
      echo '<p>' . esc_html($mine ? __('You are not hosting any sessions in the next three weeks.', 'nirog-academy') : __('No sessions in the last week or the next three weeks.', 'nirog-academy')) . '</p>';
    }
    echo '<div class="nba-panel nba-narrow" style="margin-top:28px"><h2>' . esc_html__('How a session runs', 'nirog-academy') . '</h2><ol>';
    echo '<li>' . esc_html__('Before: the session is set up in the Course Studio with a time, a host, the meeting or YouTube link, and team notes. Participants get a reminder the day before.', 'nirog-academy') . '</li>';
    echo '<li>' . esc_html__('15–30 minutes before: a second reminder goes out. The Join button (or the stream) appears on the participant page 15 minutes before the start.', 'nirog-academy') . '</li>';
    echo '<li>' . esc_html__('The host starts the meeting (or the YouTube stream) from their own Zoom, Meet or YouTube account using the link above.', 'nirog-academy') . '</li>';
    echo '<li>' . esc_html__('Participants join from their course page, which counts attendance here.', 'nirog-academy') . '</li>';
    echo '<li>' . esc_html__('After: add the recording as a lesson in the Studio and link it to the session.', 'nirog-academy') . '</li></ol></div>';
    self::footer();
  }

  // ------------------------------------------------------------ enrollments

  public static function enrollments(): void {
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'list';
    self::header(__('Enrollments', 'nirog-academy'));
    $tabs = ['list' => __('All enrollments', 'nirog-academy'), 'enroll' => __('Enroll someone', 'nirog-academy'), 'offer' => __('Send a payment link', 'nirog-academy')];
    echo '<nav class="nav-tab-wrapper">';
    foreach ($tabs as $key => $label) {
      echo '<a class="nav-tab' . ($tab === $key ? ' nav-tab-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=nba-enrollments&tab=' . $key)) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';
    match ($tab) {
      'enroll' => self::enroll_form(),
      'offer' => self::offer_form(),
      default => self::enrollment_list(),
    };
    self::footer();
  }

  private static function enrollment_list(): void {
    $args = [
      'programme_id' => isset($_GET['programme_id']) ? absint($_GET['programme_id']) : 0,
      'cohort_id' => isset($_GET['cohort_id']) ? absint($_GET['cohort_id']) : 0,
      'status' => isset($_GET['status']) ? sanitize_key($_GET['status']) : '',
      'needs_review' => !empty($_GET['needs_review']),
      'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
      'page' => isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1,
      'per_page' => 30,
    ];
    $result = nba()->enrollments->query($args);

    echo '<form method="get" class="nba-filters"><input type="hidden" name="page" value="nba-enrollments">';
    echo self::select('programme_id', [0 => __('All programmes', 'nirog-academy')] + MetaBoxes::programme_options(false), $args['programme_id']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo self::select('status', ['' => __('Any status', 'nirog-academy')] + array_combine(Enrollments::STATUSES, array_map([Enrollments::class, 'status_label'], Enrollments::STATUSES)), $args['status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<label><input type="checkbox" name="needs_review" value="1"' . checked($args['needs_review'], true, false) . '> ' . esc_html__('Needs review', 'nirog-academy') . '</label> ';
    echo '<input type="search" name="s" value="' . esc_attr($args['search']) . '" placeholder="' . esc_attr__('Name or email', 'nirog-academy') . '"> ';
    submit_button(__('Filter', 'nirog-academy'), 'secondary', '', false);
    echo '</form>';

    echo '<table class="widefat striped nba-table"><thead><tr>';
    foreach ([__('Participant', 'nirog-academy'), __('Programme', 'nirog-academy'), __('Batch', 'nirog-academy'), __('Status', 'nirog-academy'), __('Access', 'nirog-academy'), __('Progress', 'nirog-academy'), __('Source', 'nirog-academy')] as $h) {
      echo '<th>' . esc_html($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if (!$result['rows']) {
      echo '<tr><td colspan="7">' . esc_html__('No enrollments match.', 'nirog-academy') . '</td></tr>';
    }
    foreach ($result['rows'] as $row) {
      $summary = nba()->progress->summary((int) $row->user_id, (int) $row->programme_id);
      $cohort = $row->cohort_id ? nba()->cohorts->get((int) $row->cohort_id) : null;
      echo '<tr><td>' . self::participant_link((int) $row->user_id, (string) $row->display_name) . '<br><span class="description">' . esc_html((string) $row->user_email) . '</span></td>';
      echo '<td>' . esc_html(get_the_title((int) $row->programme_id)) . '</td>';
      echo '<td>' . esc_html($cohort->name ?? '—') . '</td>';
      echo '<td>' . self::status_badge($row->status) . ($row->needs_review ? ' <span class="nba-badge nba-badge-review">' . esc_html__('Review', 'nirog-academy') . '</span>' : '') . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      echo '<td>' . esc_html(Time::human($row->starts_at, 'j M Y') . ' → ' . Time::human($row->ends_at, 'j M Y')) . '</td>';
      echo '<td><div class="nba-bar"><span style="width:' . (int) $summary['pct'] . '%"></span></div> ' . (int) $summary['completed'] . '/' . (int) $summary['total'] . '</td>';
      $source = esc_html(ucfirst((string) $row->source));
      if ($row->order_id && function_exists('wc_get_order')) {
        $order = wc_get_order((int) $row->order_id);
        $source .= $order ? ' · <a href="' . esc_url($order->get_edit_order_url()) . '">#' . (int) $row->order_id . '</a>' : '';
      }
      echo '<td>' . $source . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    echo '</tbody></table>';
    self::pagination($result['total'], 30, $args['page']);

    self::form('export');
    echo '<input type="hidden" name="programme_id" value="' . (int) $args['programme_id'] . '">';
    submit_button(__('Download as CSV', 'nirog-academy'), 'secondary', '', false);
    echo '</form>';
  }

  private static function enroll_form(): void {
    echo '<div class="nba-panel nba-narrow"><p>' . esc_html__('Give someone access without a WooCommerce order — for example a complimentary place, or a payment received another way. If the email has no account yet, one is created and they get a "choose your password" email.', 'nirog-academy') . '</p>';
    self::form('enroll');
    echo '<table class="form-table"><tbody>';
    echo '<tr><th><label for="nba-email">' . esc_html__('Email', 'nirog-academy') . '</label></th><td><input id="nba-email" type="email" name="email" class="regular-text" required value="' . esc_attr(isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '') . '"></td></tr>';
    echo '<tr><th>' . esc_html__('Name (new accounts)', 'nirog-academy') . '</th><td><input type="text" name="first_name" placeholder="' . esc_attr__('First name', 'nirog-academy') . '"> <input type="text" name="last_name" placeholder="' . esc_attr__('Last name', 'nirog-academy') . '"></td></tr>';
    echo '<tr><th>' . esc_html__('Phone', 'nirog-academy') . '</th><td><input type="tel" name="phone"></td></tr>';
    echo '<tr><th>' . esc_html__('Programme', 'nirog-academy') . '</th><td>' . self::select('programme_id', MetaBoxes::programme_options(), 0, 'required') . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<tr><th>' . esc_html__('Batch', 'nirog-academy') . '</th><td>' . self::select('cohort_id', self::cohort_options()) . '<p class="description">' . esc_html__('Only for batch programmes. Leave empty to use the next open batch.', 'nirog-academy') . '</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<tr><th>' . esc_html__('Start date', 'nirog-academy') . '</th><td><input type="date" name="starts_at"><p class="description">' . esc_html__('Leave empty to start today.', 'nirog-academy') . '</p></td></tr>';
    echo '<tr><th>' . esc_html__('Type', 'nirog-academy') . '</th><td>' . self::select('source', ['manual' => __('Paid another way', 'nirog-academy'), 'complimentary' => __('Complimentary', 'nirog-academy')]) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<tr><th>' . esc_html__('Internal note', 'nirog-academy') . '</th><td><input type="text" name="note" class="large-text"></td></tr>';
    echo '</tbody></table>';
    submit_button(__('Enroll and send welcome email', 'nirog-academy'));
    echo '</form></div>';
  }

  private static function offer_form(): void {
    if (!function_exists('wc_get_products')) {
      echo '<p>' . esc_html__('WooCommerce is needed for payment links.', 'nirog-academy') . '</p>';
      return;
    }
    if (!empty($_GET['pay_url'])) {
      echo '<div class="notice notice-info inline"><p>' . esc_html__('Payment link (also emailed):', 'nirog-academy') . ' <input type="text" readonly class="large-text code" value="' . esc_attr(esc_url_raw(rawurldecode(wp_unslash($_GET['pay_url'])))) . '" onclick="this.select()"></p></div>'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    }
    $products = [];
    foreach (wc_get_products(['limit' => -1, 'status' => ['publish', 'private'], 'meta_key' => '_nba_programme_ids', 'meta_compare' => 'EXISTS']) as $p) {
      if (\NirogBhumi\Academy\Enrollment\WooBridge::programmes_for_product($p->get_id())) {
        $products[$p->get_id()] = $p->get_name() . ' — ' . wp_strip_all_tags(wc_price((float) $p->get_price()));
      }
    }
    echo '<div class="nba-panel nba-narrow"><p>' . esc_html__('For programmes joined after a consultation. This creates an order for the person and emails them a secure Razorpay payment link. Their access opens automatically when the payment succeeds, and your normal invoice is generated.', 'nirog-academy') . '</p>';
    if (!$products) {
      echo '<p><strong>' . esc_html__('No product is linked to a programme yet. Open the programme\'s WooCommerce product and tick the programme on its Course tab.', 'nirog-academy') . '</strong></p></div>';
      return;
    }
    self::form('offer');
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>' . esc_html__('Email', 'nirog-academy') . '</th><td><input type="email" name="email" class="regular-text" required></td></tr>';
    echo '<tr><th>' . esc_html__('Name', 'nirog-academy') . '</th><td><input type="text" name="first_name" placeholder="' . esc_attr__('First name', 'nirog-academy') . '"> <input type="text" name="last_name" placeholder="' . esc_attr__('Last name', 'nirog-academy') . '"></td></tr>';
    echo '<tr><th>' . esc_html__('Phone', 'nirog-academy') . '</th><td><input type="tel" name="phone"></td></tr>';
    echo '<tr><th>' . esc_html__('Programme', 'nirog-academy') . '</th><td>' . self::select('product_id', $products) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<tr><th>' . esc_html__('Price (₹)', 'nirog-academy') . '</th><td><input type="number" name="price" min="0" step="1" style="width:140px"><p class="description">' . esc_html__('Leave empty for the product price. Enter an amount for an agreed price.', 'nirog-academy') . '</p></td></tr>';
    echo '<tr><th>' . esc_html__('Message to include', 'nirog-academy') . '</th><td><input type="text" name="note" class="large-text" placeholder="' . esc_attr__('Optional: shown in the payment email', 'nirog-academy') . '"></td></tr>';
    echo '</tbody></table>';
    submit_button(__('Create order and email payment link', 'nirog-academy'));
    echo '</form></div>';
  }

  // ------------------------------------------------------------ participant

  public static function participant(): void {
    $user_id = isset($_GET['user']) ? absint($_GET['user']) : 0;
    $user = $user_id ? get_userdata($user_id) : null;
    if (!$user) {
      self::header(__('Participant', 'nirog-academy'));
      echo '<p>' . esc_html__('Participant not found.', 'nirog-academy') . '</p>';
      self::footer();
      return;
    }
    $can_manage = current_user_can('nba_manage_enrollments');
    self::header($user->display_name);
    $phone = (string) get_user_meta($user_id, 'billing_phone', true);
    $last = (string) get_user_meta($user_id, 'nba_last_active', true);
    echo '<p class="nba-intro">' . esc_html($user->user_email) . ($phone ? ' · ' . esc_html($phone) : '') . ' · ' . esc_html__('Last active:', 'nirog-academy') . ' ' . esc_html($last ? Time::human_time($last) : __('never', 'nirog-academy'));
    if (current_user_can('edit_user', $user_id)) {
      echo ' · <a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html__('WordPress profile', 'nirog-academy') . '</a>';
    }
    echo '</p>';

    foreach (nba()->enrollments->for_user($user_id) as $e) {
      $pid = (int) $e->programme_id;
      $summary = nba()->progress->summary($user_id, $pid);
      echo '<div class="nba-panel"><h2>' . esc_html(get_the_title($pid)) . ' ' . self::status_badge($e->status) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      $cohort = $e->cohort_id ? nba()->cohorts->get((int) $e->cohort_id) : null;
      echo '<p>' . esc_html(sprintf(
        /* translators: 1: start, 2: end, 3: source */
        __('Access %1$s → %2$s · Source: %3$s', 'nirog-academy'),
        Time::human($e->starts_at),
        Time::human($e->ends_at),
        $e->source
      )) . ($cohort ? ' · ' . esc_html__('Batch:', 'nirog-academy') . ' ' . esc_html($cohort->name) : '') . ($e->paused_days ? ' · ' . esc_html(sprintf(
        /* translators: %d: days */
        __('%d days paused so far', 'nirog-academy'),
        (int) $e->paused_days
      )) : '') . '</p>';
      if ($e->needs_review || $e->note) {
        echo '<p class="nba-note">' . ($e->needs_review ? '<strong>' . esc_html__('Needs review.', 'nirog-academy') . '</strong> ' : '') . nl2br(esc_html((string) $e->note)) . '</p>';
      }
      echo '<p><strong>' . (int) $summary['pct'] . '%</strong> · ' . esc_html(sprintf(
        /* translators: 1: completed, 2: total */
        __('%1$d of %2$d lessons complete', 'nirog-academy'),
        $summary['completed'],
        $summary['total']
      )) . '</p>';

      // Lesson-by-lesson progress.
      $rows = nba()->progress->for_programme($user_id, $pid);
      echo '<details><summary>' . esc_html__('Lesson by lesson', 'nirog-academy') . '</summary><table class="widefat striped"><tbody>';
      foreach (nba()->curriculum->tree($pid)['lessons'] as $lesson_id) {
        $r = $rows[$lesson_id] ?? null;
        $opens = nba()->gate->opens_at($e, $lesson_id);
        echo '<tr><td>' . esc_html(get_the_title($lesson_id)) . '</td><td>';
        if ($r && $r->status === 'completed') {
          echo '✓ ' . esc_html(Time::human($r->completed_at));
        } elseif ($r) {
          echo esc_html((int) $r->watched_pct . '% · ' . Time::human($r->updated_at));
        } elseif ($opens && Time::ts($opens) > time()) {
          echo esc_html__('Opens', 'nirog-academy') . ' ' . esc_html(Time::human($opens));
        } else {
          echo '—';
        }
        echo '</td></tr>';
      }
      echo '</tbody></table></details>';

      if ($can_manage) {
        echo '<div class="nba-actions">';
        $action = static function (string $op, string $label, string $extra = '', string $class = 'button') use ($e): void {
          self::form('enrollment', 'nba-participant');
          echo '<input type="hidden" name="id" value="' . (int) $e->id . '"><input type="hidden" name="op" value="' . esc_attr($op) . '">' . $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          echo '<button class="' . esc_attr($class) . '">' . esc_html($label) . '</button></form>';
        };
        if ($e->status === 'active') {
          $action('pause', __('Pause access', 'nirog-academy'));
        }
        if ($e->status === 'paused') {
          $action('resume', __('Resume access', 'nirog-academy'));
        }
        if (in_array($e->status, ['revoked', 'expired'], true)) {
          $action('reactivate', __('Reactivate', 'nirog-academy'));
        }
        $action('extend', __('Extend', 'nirog-academy'), '<input type="number" name="days" value="30" min="-365" max="3650" style="width:70px"> ' . esc_html__('days', 'nirog-academy') . ' ');
        $action('cohort', __('Move to batch', 'nirog-academy'), self::select('cohort_id', self::cohort_options($pid), (int) $e->cohort_id) . ' ');
        if ($e->needs_review) {
          $action('clear_review', __('Mark reviewed', 'nirog-academy'));
        }
        if (in_array($e->status, ['active', 'paused', 'pending'], true)) {
          $action('revoke', __('Revoke access', 'nirog-academy'), '<input type="text" name="reason" placeholder="' . esc_attr__('Reason', 'nirog-academy') . '"> ', 'button nba-danger');
        }
        echo '</div>';
      }
      echo '</div>';
    }

    // Device.
    echo '<div class="nba-panel"><h2>' . esc_html__('Device', 'nirog-academy') . '</h2>';
    $active = nba()->devices->active($user_id);
    if (!$active) {
      echo '<p>' . esc_html__('No device registered. The next device they log in from will be registered.', 'nirog-academy') . '</p>';
    }
    foreach ($active as $d) {
      echo '<p>' . esc_html($d->label) . ' · ' . esc_html__('registered', 'nirog-academy') . ' ' . esc_html(Time::human($d->registered_at)) . ' · ' . esc_html__('last seen', 'nirog-academy') . ' ' . esc_html(Time::human_time($d->last_seen_at)) . '</p>';
    }
    /* translators: %d: count */
    echo '<p class="description">' . esc_html(sprintf(__('Device changes in the last 12 months: %d', 'nirog-academy'), nba()->devices->reset_count($user_id))) . '</p>';
    if (current_user_can('nba_manage_devices')) {
      self::form('device_reset');
      echo '<input type="hidden" name="user_id" value="' . (int) $user_id . '"><input type="text" name="note" placeholder="' . esc_attr__('Reason (for the log)', 'nirog-academy') . '"> ';
      submit_button(__('Reset device', 'nirog-academy'), 'secondary', '', false);
      echo '</form>';
    }
    echo '</div>';

    if (current_user_can('nba_view_audit')) {
      $log = nba()->audit->query(['subject_user_id' => $user_id, 'per_page' => 30]);
      echo '<div class="nba-panel"><h2>' . esc_html__('Activity log', 'nirog-academy') . '</h2><table class="widefat striped"><tbody>';
      foreach ($log['rows'] as $row) {
        echo '<tr><td>' . esc_html(Time::human_time($row->created_at)) . '</td><td>' . esc_html(AuditLog::label($row->action)) . '</td><td><code>' . esc_html((string) $row->context) . '</code></td></tr>';
      }
      echo '</tbody></table></div>';
    }
    if ($can_manage) {
      echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=nba-enrollments&tab=enroll&email=' . rawurlencode($user->user_email))) . '">' . esc_html__('Enroll in another programme', 'nirog-academy') . '</a></p>';
    }
    self::footer();
  }

  // ------------------------------------------------------------ batches

  public static function batches(): void {
    self::header(__('Batches', 'nirog-academy'), __('A batch is a group that starts together. In programmes set to "Batch", every lesson opens on its day counted from the batch start date. Self-paced programmes do not need batches.', 'nirog-academy'));
    $edit = isset($_GET['edit']) ? nba()->cohorts->get(absint($_GET['edit'])) : null;
    $coaches = [0 => __('— None —', 'nirog-academy')];
    foreach (get_users(['role__in' => ['nba_coach', 'nba_manager', 'administrator'], 'fields' => ['ID', 'display_name']]) as $u) {
      $coaches[(int) $u->ID] = $u->display_name;
    }
    echo '<div class="nba-columns"><div class="nba-panel"><h2>' . esc_html($edit ? __('Edit batch', 'nirog-academy') : __('New batch', 'nirog-academy')) . '</h2>';
    self::form('batch');
    echo '<input type="hidden" name="id" value="' . (int) ($edit->id ?? 0) . '"><table class="form-table"><tbody>';
    echo '<tr><th>' . esc_html__('Programme', 'nirog-academy') . '</th><td>' . self::select('programme_id', MetaBoxes::programme_options(), (int) ($edit->programme_id ?? 0), 'required') . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<tr><th>' . esc_html__('Name', 'nirog-academy') . '</th><td><input type="text" name="name" required class="regular-text" value="' . esc_attr($edit->name ?? '') . '" placeholder="' . esc_attr__('e.g. January 2027', 'nirog-academy') . '"></td></tr>';
    echo '<tr><th>' . esc_html__('Starts', 'nirog-academy') . '</th><td><input type="datetime-local" name="starts_at" required value="' . esc_attr(Time::utc_to_local_input($edit->starts_at ?? null)) . '"></td></tr>';
    echo '<tr><th>' . esc_html__('Enrollment opens', 'nirog-academy') . '</th><td><input type="datetime-local" name="enroll_opens_at" value="' . esc_attr(Time::utc_to_local_input($edit->enroll_opens_at ?? null)) . '"><p class="description">' . esc_html__('Optional. Empty = open now.', 'nirog-academy') . '</p></td></tr>';
    echo '<tr><th>' . esc_html__('Enrollment closes', 'nirog-academy') . '</th><td><input type="datetime-local" name="enroll_closes_at" value="' . esc_attr(Time::utc_to_local_input($edit->enroll_closes_at ?? null)) . '"><p class="description">' . esc_html__('Optional. Empty = closes the day after the batch starts.', 'nirog-academy') . '</p></td></tr>';
    echo '<tr><th>' . esc_html__('Places', 'nirog-academy') . '</th><td><input type="number" name="capacity" min="1" style="width:100px" value="' . esc_attr((string) ($edit->capacity ?? '')) . '"><p class="description">' . esc_html__('Optional. When full, new buyers go to the next open batch.', 'nirog-academy') . '</p></td></tr>';
    echo '<tr><th>' . esc_html__('Coach', 'nirog-academy') . '</th><td>' . self::select('coach_id', $coaches, (int) ($edit->coach_id ?? 0)) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<tr><th>' . esc_html__('Status', 'nirog-academy') . '</th><td>' . self::select('status', ['open' => __('Open', 'nirog-academy'), 'closed' => __('Closed to new joiners', 'nirog-academy'), 'archived' => __('Archived', 'nirog-academy')], $edit->status ?? 'open') . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '</tbody></table>';
    submit_button($edit ? __('Save batch', 'nirog-academy') : __('Create batch', 'nirog-academy'));
    echo '</form></div><div class="nba-panel"><h2>' . esc_html__('Batches', 'nirog-academy') . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__('Batch', 'nirog-academy') . '</th><th>' . esc_html__('Starts', 'nirog-academy') . '</th><th>' . esc_html__('Members', 'nirog-academy') . '</th><th>' . esc_html__('Status', 'nirog-academy') . '</th></tr></thead><tbody>';
    foreach (nba()->cohorts->all(0, true) as $c) {
      echo '<tr><td><a href="' . esc_url(admin_url('admin.php?page=nba-batches&edit=' . (int) $c->id)) . '">' . esc_html($c->name) . '</a><br><span class="description">' . esc_html(get_the_title((int) $c->programme_id)) . '</span></td>';
      echo '<td>' . esc_html(Time::human_time($c->starts_at)) . '</td>';
      echo '<td><a href="' . esc_url(admin_url('admin.php?page=nba-enrollments&cohort_id=' . (int) $c->id)) . '">' . (int) $c->members . ($c->capacity ? ' / ' . (int) $c->capacity : '') . '</a></td>';
      echo '<td>' . esc_html(ucfirst($c->status)) . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
    self::footer();
  }

  // ------------------------------------------------------------ devices

  public static function devices(): void {
    self::header(__('Device requests', 'nirog-academy'), sprintf(
      /* translators: %d: days */
      __('Participants can change device themselves once every %d days with an email code. Requests beyond that come here. Approving frees their device slot: the next device they log in from becomes their device.', 'nirog-academy'),
      nba()->settings->int('self_reset_interval_days')
    ));
    $pending = nba()->devices->requests('pending');
    if (!$pending) {
      echo '<p>' . esc_html__('No pending requests.', 'nirog-academy') . '</p>';
    } else {
      echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Participant', 'nirog-academy') . '</th><th>' . esc_html__('Requested', 'nirog-academy') . '</th><th>' . esc_html__('New device', 'nirog-academy') . '</th><th>' . esc_html__('Reason', 'nirog-academy') . '</th><th>' . esc_html__('Changes in 12 months', 'nirog-academy') . '</th><th></th></tr></thead><tbody>';
      foreach ($pending as $r) {
        echo '<tr><td>' . self::participant_link((int) $r->user_id, (string) $r->display_name) . '<br><span class="description">' . esc_html((string) $r->user_email) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . esc_html(Time::human_time($r->created_at)) . '</td><td>' . esc_html($r->device_label) . '</td><td>' . esc_html((string) $r->reason) . '</td>';
        echo '<td>' . (int) nba()->devices->reset_count((int) $r->user_id) . '</td><td class="nba-actions">';
        foreach (['approve' => __('Approve', 'nirog-academy'), 'deny' => __('Decline', 'nirog-academy')] as $decision => $label) {
          self::form('device_decide');
          echo '<input type="hidden" name="request_id" value="' . (int) $r->id . '"><input type="hidden" name="decision" value="' . esc_attr($decision) . '"><button class="button' . ($decision === 'approve' ? ' button-primary' : '') . '">' . esc_html($label) . '</button></form>';
        }
        echo '</td></tr>';
      }
      echo '</tbody></table>';
    }
    $recent = array_merge(nba()->devices->requests('approved', 10), nba()->devices->requests('denied', 10));
    if ($recent) {
      echo '<h2>' . esc_html__('Recently decided', 'nirog-academy') . '</h2><ul>';
      foreach ($recent as $r) {
        echo '<li>' . self::participant_link((int) $r->user_id, (string) $r->display_name) . ' — ' . esc_html(ucfirst($r->status)) . ', ' . esc_html(Time::human_time($r->decided_at)) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      }
      echo '</ul>';
    }
    self::footer();
  }

  // ------------------------------------------------------------ announcements

  public static function announcements(): void {
    self::header(__('Announcements', 'nirog-academy'), __('Messages appear in participants\' notification bell. Tick "Also email" for important news.', 'nirog-academy'));
    echo '<div class="nba-columns"><div class="nba-panel"><h2>' . esc_html__('New announcement', 'nirog-academy') . '</h2>';
    self::form('announce');
    echo '<p><label>' . esc_html__('Title', 'nirog-academy') . '<br><input type="text" name="title" class="large-text" required maxlength="200"></label></p>';
    echo '<p><label>' . esc_html__('Message', 'nirog-academy') . '<br><textarea name="message" class="large-text" rows="6" required></textarea></label></p>';
    echo '<p><label>' . esc_html__('Programme', 'nirog-academy') . '<br>' . self::select('programme_id', [0 => __('Everyone with active access', 'nirog-academy')] + MetaBoxes::programme_options(false)) . '</label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<p><label>' . esc_html__('Batch', 'nirog-academy') . '<br>' . self::select('cohort_id', [0 => __('All batches', 'nirog-academy')] + self::cohort_options(0, false)) . '</label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<p><label><input type="checkbox" name="email" value="1"> ' . esc_html__('Also email (participants who turned off announcement emails will not get one)', 'nirog-academy') . '</label></p>';
    submit_button(__('Send', 'nirog-academy'));
    echo '</form></div><div class="nba-panel"><h2>' . esc_html__('Sent', 'nirog-academy') . '</h2><ul class="nba-list">';
    foreach (get_posts(['post_type' => PostTypes::ANNOUNCEMENT, 'numberposts' => 20]) as $a) {
      $sent = (int) get_post_meta($a->ID, '_nba_sent_count', true);
      $total = (int) get_post_meta($a->ID, '_nba_audience_count', true);
      echo '<li><strong>' . esc_html($a->post_title) . '</strong><br><span class="description">' . esc_html(get_the_date('', $a)) . ' · ' . esc_html(sprintf(
        /* translators: 1: sent, 2: total */
        __('%1$d of %2$d delivered', 'nirog-academy'),
        $sent,
        $total
      )) . '</span></li>';
    }
    echo '</ul></div></div>';
    self::footer();
  }

  // ------------------------------------------------------------ reports

  public static function reports(): void {
    self::header(__('Reports', 'nirog-academy'), __('Where participants are in each programme, and which lessons people stop at.', 'nirog-academy'));
    $programmes = MetaBoxes::programme_options(false);
    $pid = isset($_GET['programme_id']) ? absint($_GET['programme_id']) : (int) array_key_first($programmes);
    echo '<form method="get" class="nba-filters"><input type="hidden" name="page" value="nba-reports">' . self::select('programme_id', $programmes, $pid); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    submit_button(__('Show', 'nirog-academy'), 'secondary', '', false);
    echo '</form>';
    if (!$pid) {
      echo '<p>' . esc_html__('Create a programme first.', 'nirog-academy') . '</p>';
      self::footer();
      return;
    }
    $active = count(nba()->enrollments->all_active($pid));
    $stats = nba()->progress->lesson_stats($pid);
    $tree = nba()->curriculum->tree($pid);
    /* translators: %d: count */
    echo '<p>' . esc_html(sprintf(__('%d participants with active access.', 'nirog-academy'), $active)) . '</p>';
    echo '<table class="widefat striped nba-table"><thead><tr><th>#</th><th>' . esc_html__('Lesson', 'nirog-academy') . '</th><th>' . esc_html__('Started', 'nirog-academy') . '</th><th>' . esc_html__('Completed', 'nirog-academy') . '</th><th>' . esc_html__('Completion (of active participants)', 'nirog-academy') . '</th></tr></thead><tbody>';
    foreach ($tree['lessons'] as $i => $lesson_id) {
      $s = $stats[$lesson_id] ?? ['started' => 0, 'completed' => 0];
      $pct = $active ? (int) round($s['completed'] * 100 / $active) : 0;
      echo '<tr><td>' . ($i + 1) . '</td><td>' . esc_html(get_the_title($lesson_id)) . ' <span class="description">(' . esc_html__('day', 'nirog-academy') . ' ' . (int) get_post_meta($lesson_id, '_nba_release_day', true) . ')</span></td><td>' . (int) $s['started'] . '</td><td>' . (int) $s['completed'] . '</td><td><div class="nba-bar"><span style="width:' . min(100, $pct) . '%"></span></div> ' . (int) $pct . '%</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p class="description">' . esc_html__('A sharp drop between two lessons usually means the earlier lesson is too long, unclear, or physically hard. Consider a check-in message or a shorter alternative.', 'nirog-academy') . '</p>';
    self::form('export');
    echo '<input type="hidden" name="programme_id" value="' . (int) $pid . '">';
    submit_button(__('Download participant progress (CSV)', 'nirog-academy'), 'secondary', '', false);
    echo '</form>';
    self::footer();
  }

  // ------------------------------------------------------------ import

  public static function import(): void {
    self::header(__('Import videos', 'nirog-academy'));
    $dir = Storage::dir('media');
    echo '<div class="nba-panel nba-narrow"><h2>' . esc_html__('How to add a protected video', 'nirog-academy') . '</h2><ol>';
    echo '<li>' . esc_html__('On your computer, run the encode script from the plugin\'s tools folder on the MP4 file. It creates a folder with the encrypted video.', 'nirog-academy') . '</li>';
    echo '<li>' . esc_html__('Upload that whole folder to the server folder below, using Hostinger File Manager or SFTP.', 'nirog-academy') . '</li>';
    echo '<li>' . esc_html__('Press "Find new videos". Each new folder becomes an entry in the Video Library, and its key is moved somewhere private.', 'nirog-academy') . '</li>';
    echo '<li>' . esc_html__('Open the lesson and choose the video.', 'nirog-academy') . '</li></ol>';
    echo '<p>' . esc_html__('Server folder:', 'nirog-academy') . ' <code>' . esc_html(str_replace(ABSPATH, '/', wp_normalize_path($dir))) . '</code></p>';
    echo '<p>' . esc_html__('Full guide:', 'nirog-academy') . ' <a href="' . esc_url(admin_url('admin.php?page=nba-help&doc=prepare-and-upload-videos')) . '">' . esc_html__('Preparing and uploading videos', 'nirog-academy') . '</a></p>';
    self::form('import');
    submit_button(__('Find new videos', 'nirog-academy'));
    echo '</form></div>';
    $results = get_transient('nba_import_results_' . get_current_user_id());
    if (is_array($results) && $results) {
      echo '<h2>' . esc_html__('Last scan', 'nirog-academy') . '</h2><table class="widefat striped"><tbody>';
      foreach ($results as $r) {
        echo '<tr><td><code>' . esc_html($r['folder']) . '</code></td><td>' . esc_html($r['status']) . '</td><td>';
        if (!empty($r['media_id'])) {
          echo '<a href="' . esc_url((string) get_edit_post_link((int) $r['media_id'])) . '">' . esc_html__('Open', 'nirog-academy') . '</a>';
        }
        echo esc_html($r['message'] ?? '') . '</td></tr>';
      }
      echo '</tbody></table>';
    }
    self::footer();
  }

  // ------------------------------------------------------------ audit

  public static function audit(): void {
    self::header(__('Audit log', 'nirog-academy'), __('A record of sensitive actions. Entries cannot be edited.', 'nirog-academy'));
    $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $action = isset($_GET['log_action']) ? sanitize_text_field(wp_unslash($_GET['log_action'])) : '';
    $result = nba()->audit->query(['page' => $page, 'per_page' => 50, 'action' => $action]);
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('When', 'nirog-academy') . '</th><th>' . esc_html__('Who', 'nirog-academy') . '</th><th>' . esc_html__('What', 'nirog-academy') . '</th><th>' . esc_html__('Participant', 'nirog-academy') . '</th><th>' . esc_html__('Details', 'nirog-academy') . '</th></tr></thead><tbody>';
    foreach ($result['rows'] as $row) {
      $actor = $row->actor_id ? get_userdata((int) $row->actor_id) : null;
      echo '<tr><td>' . esc_html(Time::human_time($row->created_at)) . '</td>';
      echo '<td>' . esc_html($actor ? $actor->display_name : __('System', 'nirog-academy')) . '</td>';
      echo '<td><a href="' . esc_url(add_query_arg('log_action', $row->action)) . '">' . esc_html(AuditLog::label($row->action)) . '</a></td>';
      echo '<td>' . ($row->subject_user_id ? self::participant_link((int) $row->subject_user_id) : '') . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      echo '<td><code>' . esc_html(mb_substr((string) $row->context, 0, 200)) . '</code></td></tr>';
    }
    echo '</tbody></table>';
    self::pagination($result['total'], 50, $page);
    self::footer();
  }

  // ------------------------------------------------------------ settings

  public static function settings(): void {
    $s = nba()->settings->all();
    $name = Settings::OPTION;
    self::header(__('Settings', 'nirog-academy'));
    echo '<form method="post" action="options.php">';
    settings_fields('nba_settings');
    $field = static function (string $label, string $control, string $help = ''): void {
      echo '<tr><th>' . esc_html($label) . '</th><td>' . $control . ($help ? '<p class="description">' . esc_html($help) . '</p>' : '') . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    };
    $num = static fn(string $key, int $min, int $max) => '<input type="number" name="' . esc_attr($name . '[' . $key . ']') . '" value="' . (int) $s[$key] . '" min="' . $min . '" max="' . $max . '" style="width:100px">';
    $text = static fn(string $key, string $type = 'text') => '<input type="' . $type . '" class="regular-text" name="' . esc_attr($name . '[' . $key . ']') . '" value="' . esc_attr((string) $s[$key]) . '">';
    $check = static fn(string $key, string $label) => '<label><input type="checkbox" name="' . esc_attr($name . '[' . $key . ']') . '" value="1"' . checked((int) $s[$key], 1, false) . '> ' . esc_html($label) . '</label>';

    echo '<h2>' . esc_html__('Launch', 'nirog-academy') . '</h2><table class="form-table"><tbody>';
    $field(__('Course status', 'nirog-academy'), self::select($name . '[launch_mode]', [
      'closed' => __('Closed — hidden: everyone except staff gets "page not found"; participant emails are held', 'nirog-academy'),
      'preview' => __('Preview — participants can log in and watch free preview lessons only', 'nirog-academy'),
      'open' => __('Open — enrolled participants have full access', 'nirog-academy'),
    ], $s['launch_mode']));
    $field(__('Name shown to participants', 'nirog-academy'), $text('area_name'));
    $field(__('Log-in page photo', 'nirog-academy'), $text('landing_image', 'url'), __('Optional. Paste the address of an image from Media → Library (Copy URL). Leave empty for the meditation photo.', 'nirog-academy'));
    $field(__('Web address', 'nirog-academy'), home_url('/') . ' ' . $text('base_slug') . ' /', __('Changing this changes every course link. Avoid changing it after launch.', 'nirog-academy'));
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Access', 'nirog-academy') . '</h2><table class="form-table"><tbody>';
    $field(__('Default access length (days)', 'nirog-academy'), $num('default_access_days', 1, 3650), __('Used when a programme does not set its own. 365 = one year.', 'nirog-academy'));
    $field(__('Extra grace days', 'nirog-academy'), $num('grace_days', 0, 365), __('Added to every new enrollment.', 'nirog-academy'));
    $field(__('A lesson counts as watched at', 'nirog-academy'), $num('completion_threshold', 50, 100) . ' %');
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Devices', 'nirog-academy') . '</h2><table class="form-table"><tbody>';
    $field(__('Devices per participant', 'nirog-academy'), $num('max_devices', 1, 5), __('1 = strictly one device. 2 allows, for example, one phone and one computer.', 'nirog-academy'));
    $field(__('Self-service device change', 'nirog-academy'), sprintf(
      /* translators: %s: number input */
      __('Once every %s days', 'nirog-academy'),
      $num('self_reset_interval_days', 0, 365)
    ), __('0 = participants must always ask the team.', 'nirog-academy'));
    $field(__('Staff', 'nirog-academy'), $check('staff_device_exempt', __('Staff are exempt from the device rule', 'nirog-academy')));
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Video', 'nirog-academy') . '</h2><table class="form-table"><tbody>';
    $field(__('Watermark', 'nirog-academy'), $check('watermark', __('Show the participant\'s name and phone ending on the video', 'nirog-academy')), __('A faint, moving watermark. It discourages screen recording because any leak can be traced.', 'nirog-academy'));
    $field(__('Video link lifetime (seconds)', 'nirog-academy'), $num('signed_url_ttl', 300, 86400), __('How long a page of video stays playable before it needs a reload. Links only work for the logged-in participant on their registered device.', 'nirog-academy'));
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Notifications', 'nirog-academy') . '</h2><table class="form-table"><tbody>';
    $field(__('Email sender name', 'nirog-academy'), $text('email_from_name'));
    $field(__('New lesson emails', 'nirog-academy'), $check('email_lesson_unlocked', __('Email participants (at 6 am) when new lessons open', 'nirog-academy')));
    $field(__('Gentle reminder after', 'nirog-academy'), $num('nudge_after_days', 0, 30) . ' ' . esc_html__('days without activity', 'nirog-academy'), __('0 turns reminders off.', 'nirog-academy'));
    $field(__('Warn before access ends', 'nirog-academy'), $num('expiry_warning_days', 0, 60) . ' ' . esc_html__('days', 'nirog-academy'));
    $field(__('Support email', 'nirog-academy'), $text('support_email', 'email'), __('Shown to participants, and receives device change requests.', 'nirog-academy'));
    $field(__('Support WhatsApp number', 'nirog-academy'), $text('support_whatsapp'), __('With country code, digits only, e.g. 919876543210. Shows a "Chat on WhatsApp" link.', 'nirog-academy'));
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Data', 'nirog-academy') . '</h2><table class="form-table"><tbody>';
    $field(__('Keep the audit log for', 'nirog-academy'), $num('audit_retention_days', 30, 3650) . ' ' . esc_html__('days', 'nirog-academy'));
    $field(__('Uninstall', 'nirog-academy'), $check('delete_data_on_uninstall', __('Delete all course data when the plugin is deleted', 'nirog-academy')), __('Leave this off unless you are sure. Enrollments and progress cannot be recovered.', 'nirog-academy'));
    echo '</tbody></table>';
    submit_button();
    echo '</form>';

    self::health();
    self::google_panel();

    echo '<div class="nba-panel nba-narrow"><h2>' . esc_html__('Demo programme', 'nirog-academy') . '</h2>';
    if (Demo::exists()) {
      $state = Demo::state();
      echo '<p>' . esc_html__('The demo programme is loaded. To see it as a participant, open this address in a private window and log in with the demo participant:', 'nirog-academy') . ' <a href="' . esc_url(Demo::login_url()) . '" target="_blank" rel="noopener">' . esc_html(Demo::login_url()) . '</a></p>';
      echo '<p><strong>' . esc_html__('Username', 'nirog-academy') . ':</strong> <code>' . esc_html((string) ($state['login'] ?? '')) . '</code> &nbsp; <strong>' . esc_html__('Password', 'nirog-academy') . ':</strong> <code>' . esc_html((string) ($state['password'] ?? '')) . '</code></p>';
      self::form('demo_remove');
      submit_button(__('Remove demo programme', 'nirog-academy'), 'delete', '', false, ['onclick' => "return confirm('" . esc_js(__('Remove the demo programme, its files and the demo participant?', 'nirog-academy')) . "');"]);
      echo '</form>';
    } else {
      echo '<p>' . esc_html__('Loads a complete, published demo programme: four weeks of videos, audio, booklets and readings, bonus content, live sessions and a demo participant. Everything can be removed again with one button.', 'nirog-academy') . '</p>';
      self::form('demo');
      submit_button(__('Load demo programme', 'nirog-academy'), 'secondary', '', false);
      echo '</form>';
    }
    echo '</div>';
    self::footer();
  }

  /** Settings → Google Meet: one-time connection for creating Meet links. */
  private static function google_panel(): void {
    $g = \NirogBhumi\Academy\Live\GoogleMeet::class;
    $s = $g::settings();
    echo '<div class="nba-panel nba-narrow" id="nba-google"><h2>' . esc_html__('Google Meet', 'nirog-academy') . '</h2>';
    if ($g::connected()) {
      echo '<p><span class="nba-pill nba-pill-ok">' . esc_html__('Connected', 'nirog-academy') . '</span> ' . esc_html(sprintf(
        /* translators: %s: Google account email */
        __('Meet links are created in the calendar of %s.', 'nirog-academy'),
        $s['email'] ?: __('the connected Google account', 'nirog-academy')
      )) . '</p>';
      echo '<p>' . esc_html__('In Course Studio, open a live session and press "Create Google Meet link". The host is invited by Google Calendar, and when you change the session\'s title or time the calendar event changes too.', 'nirog-academy') . '</p>';
      self::form('google_disconnect');
      submit_button(__('Disconnect Google account', 'nirog-academy'), 'secondary', '', false);
      echo '</form></div>';
      return;
    }
    echo '<p>' . esc_html__('Connect the Google account that should own your live sessions (for example the clinic\'s account). Then every live session can get its own Google Meet link with one click. This is free; it needs a one-time set-up of about 10 minutes, described in Help → "Live sessions with Google Meet".', 'nirog-academy') . '</p>';
    echo '<ol class="nba-steps">';
    echo '<li>' . wp_kses_post(__('In <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>, signed in with that account: create a project, enable the <strong>Google Calendar API</strong>, set up the <strong>OAuth consent screen</strong> (External, add your own email as a test user, or publish it), and create an <strong>OAuth client ID</strong> of type <strong>Web application</strong>.', 'nirog-academy')) . '</li>';
    echo '<li>' . esc_html__('Under "Authorised redirect URIs" add exactly this address:', 'nirog-academy') . '<br><code style="user-select:all">' . esc_html($g::redirect_uri()) . '</code></li>';
    echo '<li>' . esc_html__('Copy the Client ID and Client secret here and save.', 'nirog-academy') . '</li>';
    echo '<li>' . esc_html__('Press "Connect Google account" and allow access.', 'nirog-academy') . '</li>';
    echo '</ol>';
    self::form('google_save');
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="nba-gid">' . esc_html__('Client ID', 'nirog-academy') . '</label></th><td><input id="nba-gid" class="regular-text" name="client_id" value="' . esc_attr($s['client_id']) . '" autocomplete="off" placeholder="1234-abc.apps.googleusercontent.com"></td></tr>';
    echo '<tr><th scope="row"><label for="nba-gsecret">' . esc_html__('Client secret', 'nirog-academy') . '</label></th><td><input id="nba-gsecret" class="regular-text" type="password" name="client_secret" value="" autocomplete="new-password" placeholder="' . esc_attr($s['client_secret'] !== '' ? __('Saved — leave empty to keep it', 'nirog-academy') : 'GOCSPX-…') . '"></td></tr>';
    echo '</tbody></table>';
    submit_button(__('Save', 'nirog-academy'), 'secondary', '', false);
    echo '</form>';
    if ($g::configured()) {
      echo '<p style="margin-top:16px">';
      self::form('google_connect');
      submit_button(__('Connect Google account', 'nirog-academy'), 'primary', '', false);
      echo '</form></p>';
    }
    echo '</div>';
  }

  private static function health(): void {
    $checks = [];
    $checks[] = [version_compare(PHP_VERSION, '8.1', '>='), 'PHP ' . PHP_VERSION];
    $checks[] = [is_ssl() || str_starts_with(home_url(), 'https://'), __('Site uses HTTPS', 'nirog-academy')];
    $checks[] = [class_exists('WooCommerce'), __('WooCommerce active', 'nirog-academy')];
    $checks[] = [(bool) get_option('permalink_structure'), __('Pretty permalinks enabled', 'nirog-academy')];
    $checks[] = [is_writable(Storage::base_dir()), __('Private storage folder is writable', 'nirog-academy')];
    $checks[] = [is_file(Storage::dir('keys') . '/.htaccess') && is_file(Storage::dir('files') . '/.htaccess'), __('Private folders have their protection rules', 'nirog-academy')];
    $next = wp_next_scheduled('nba_daily') ?: (function_exists('as_next_scheduled_action') ? as_next_scheduled_action('nba_daily') : false);
    $checks[] = [(bool) $next, __('Daily jobs are scheduled', 'nirog-academy')];
    $checks[] = [!defined('DISABLE_WP_CRON') || DISABLE_WP_CRON, defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? __('WP-Cron runs from a real server cron job (recommended)', 'nirog-academy') : __('WP-Cron runs on page visits — add a Hostinger cron job for reliable timing', 'nirog-academy')];
    $checks[] = [(bool) get_option('nba_secrets'), __('Signing key present', 'nirog-academy')];
    echo '<div class="nba-panel nba-narrow"><h2>' . esc_html__('Health check', 'nirog-academy') . '</h2><ul class="nba-health">';
    foreach ($checks as [$ok, $label]) {
      echo '<li class="' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? '✓' : '✗') . ' ' . esc_html($label) . '</li>';
    }
    echo '</ul></div>';
  }

  // ------------------------------------------------------------ help

  public static function help(): void {
    $docs = Docs::admin();
    $slug = isset($_GET['doc']) ? sanitize_title(wp_unslash($_GET['doc'])) : '';
    $resolver = static fn(string $s) => admin_url('admin.php?page=nba-help&doc=' . $s);
    if ($slug && isset($docs[$slug])) {
      self::header($docs[$slug]['title']);
      echo '<p><a href="' . esc_url(admin_url('admin.php?page=nba-help')) . '">← ' . esc_html__('All guides', 'nirog-academy') . '</a></p>';
      echo '<div class="nba-doc">' . Docs::render($docs[$slug]['path'], $resolver) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered through wp_kses_post.
      self::footer();
      return;
    }
    self::header(__('Guides for the team', 'nirog-academy'), __('Step-by-step guides for the team. Participant guides are in the participant Help Center.', 'nirog-academy'));
    echo '<div class="nba-doc-list">';
    foreach ($docs as $doc) {
      echo '<a class="nba-card" href="' . esc_url($resolver($doc['slug'])) . '"><strong>' . esc_html($doc['title']) . '</strong><span>' . esc_html($doc['summary']) . '</span></a>';
    }
    echo '</div><p><a href="' . esc_url(Router::url('help')) . '" target="_blank" rel="noopener">' . esc_html__('Open the participant Help Center', 'nirog-academy') . ' ↗</a></p>';
    self::footer();
  }
  // phpcs:enable
}
