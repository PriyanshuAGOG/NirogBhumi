<?php
/**
 * Courses page shell: the same plain header and footer language as the
 * Nirog Bhumi website.
 *
 * @var string $title
 * @var string $view
 * @var string $content
 * @var bool   $bare   No navigation (login, set password, blocked device).
 */
use NirogBhumi\Academy\Frontend\Actions;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;

$area = (string) nba()->settings->get('area_name');
$logo = View::logo_url();
$user_id = get_current_user_id();
$unread = $user_id && !$bare ? nba()->notifications->unread_count($user_id) : 0;
$programme_url = Router::url();
if ($user_id && !$bare) {
  $first = Router::visible_enrollments($user_id)[0] ?? null;
  $programme_url = $first ? Router::programme_url((int) $first->programme_id) : Router::url();
}
$nav = [
  'dashboard' => [__('Today', 'nirog-academy'), Router::url()],
  'programme' => [__('Programme', 'nirog-academy'), $programme_url],
  'library' => [__('Library', 'nirog-academy'), Router::url('library')],
  'live' => [__('Live', 'nirog-academy'), Router::url('live')],
];
$active = match ($view) {
  'lesson', 'orientation', 'programme' => 'programme',
  'library' => 'library',
  default => $view,
};
$flash_notice = Actions::flash('notice');
$flash_error = Actions::flash('error');
$lang = I18n::current();
$support_email = (string) nba()->settings->get('support_email');
$is_staff = \NirogBhumi\Academy\Install\Roles::is_staff($user_id);
$is_open = nba()->settings->get('launch_mode') !== 'closed';
$whatsapp = (string) nba()->settings->get('support_whatsapp');
$brand = static function () use ($logo, $area): void {
  if ($logo) {
    echo '<img src="' . esc_url($logo) . '" alt="Nirog Bhumi" width="113" height="26">';
  } else {
    echo '<span class="nba-brand-text">Nirog Bhumi</span>';
  }
};
$lang_links = static function () use ($lang): void {
  echo '<span class="nba-lang-switch">';
  foreach (I18n::LOCALES as $code => $label) {
    echo '<a href="' . esc_url(View::lang_switch_url($code)) . '" lang="' . esc_attr(substr($code, 0, 2)) . '"' . ($code === $lang ? ' aria-current="true"' : '') . '>' . esc_html($code === 'hi_IN' ? 'हिंदी' : 'English') . '</a>';
  }
  echo '</span>';
};
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#f8f6ef">
<title><?php echo esc_html(($title ? $title . ' · ' : '') . $area); ?></title>
<?php Router::head(); ?>
</head>
<body class="nba-body nba-view-<?php echo esc_attr($view); ?><?php echo $bare ? ' nba-bare' : ''; ?> nba-lang-<?php echo esc_attr($lang); ?>">
<a class="nba-skip" href="#nba-main"><?php esc_html_e('Skip to content', 'nirog-academy'); ?></a>
<?php if ($is_staff) : ?>
<div class="nba-staffbar" role="region" aria-label="<?php esc_attr_e('Team', 'nirog-academy'); ?>">
  <div class="nba-wrap nba-staffbar-inner">
    <span class="nba-staffbar-state<?php echo $is_open ? ' is-open' : ''; ?>"><?php echo esc_html($is_open ? __('Courses are open', 'nirog-academy') : __('Courses are closed — only the team can see this', 'nirog-academy')); ?></span>
    <span class="nba-staffbar-links">
      <?php if (current_user_can('edit_nba_items')) : ?><a href="<?php echo esc_url(admin_url('admin.php?page=nirog-academy')); ?>"><?php esc_html_e('Course Studio', 'nirog-academy'); ?></a><?php endif; ?>
      <a href="<?php echo esc_url(admin_url('admin.php?page=nba-live-desk')); ?>"><?php esc_html_e('Live desk', 'nirog-academy'); ?></a>
      <a href="<?php echo esc_url(admin_url('admin.php?page=nba-overview')); ?>"><?php esc_html_e('Dashboard', 'nirog-academy'); ?></a>
    </span>
  </div>
</div>
<?php endif; ?>

<header class="nba-head">
  <div class="nba-wrap nba-head-inner">
    <a class="nba-brand" href="<?php echo esc_url($bare ? home_url('/') : Router::url()); ?>"><?php $brand(); ?></a>
    <?php if (!$bare) : ?>
      <nav class="nba-nav" aria-label="<?php esc_attr_e('Main', 'nirog-academy'); ?>">
        <?php foreach ($nav as $key => [$label, $url]) : ?>
          <a href="<?php echo esc_url($url); ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="nba-tools">
        <?php $lang_links(); ?>
        <a href="<?php echo esc_url(Router::url('notifications')); ?>"<?php echo $view === 'notifications' ? ' class="is-current"' : ''; ?>><?php esc_html_e('Inbox', 'nirog-academy'); ?><?php if ($unread) : ?><span class="nba-count"><?php echo (int) min(99, $unread); ?></span><?php endif; ?></a>
        <a href="<?php echo esc_url(Router::url('profile')); ?>"<?php echo $view === 'profile' ? ' class="is-current"' : ''; ?>><?php esc_html_e('Account', 'nirog-academy'); ?></a>
      </div>
      <button type="button" class="nba-menu-btn" aria-expanded="false" aria-controls="nba-sheet"><?php esc_html_e('Menu', 'nirog-academy'); ?><?php if ($unread) : ?><span class="nba-count"><?php echo (int) min(99, $unread); ?></span><?php endif; ?></button>
    <?php else : ?>
      <div class="nba-tools" style="display:flex;margin-left:auto"><?php $lang_links(); ?></div>
    <?php endif; ?>
  </div>
</header>

<?php if (!$bare) : ?>
<div class="nba-sheet" id="nba-sheet" hidden>
  <div class="nba-sheet-head">
    <a class="nba-brand" href="<?php echo esc_url(Router::url()); ?>"><?php $brand(); ?></a>
    <button type="button" class="nba-menu-btn nba-menu-close"><?php esc_html_e('Close', 'nirog-academy'); ?></button>
  </div>
  <nav aria-label="<?php esc_attr_e('Main', 'nirog-academy'); ?>">
    <?php foreach ($nav as $key => [$label, $url]) : ?>
      <a href="<?php echo esc_url($url); ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
    <?php endforeach; ?>
    <a href="<?php echo esc_url(Router::url('notifications')); ?>"><?php esc_html_e('Inbox', 'nirog-academy'); ?><?php if ($unread) : ?><span><?php echo esc_html(sprintf(
      /* translators: %d: count */
      __('%d new', 'nirog-academy'),
      $unread
    )); ?></span><?php endif; ?></a>
    <a href="<?php echo esc_url(Router::url('profile')); ?>"><?php esc_html_e('Account', 'nirog-academy'); ?></a>
  </nav>
  <div class="nba-sheet-tools">
    <?php $lang_links(); ?>
    <a href="<?php echo esc_url(Router::url('help')); ?>"><?php esc_html_e('Help', 'nirog-academy'); ?></a>
    <a href="<?php echo esc_url(wp_nonce_url(Router::url('logout'), 'nba_logout')); ?>"><?php esc_html_e('Log out', 'nirog-academy'); ?></a>
  </div>
</div>
<?php endif; ?>

<?php if (!$bare) :
  $tab_icons = ['dashboard' => 'home', 'programme' => 'play', 'library' => 'book', 'live' => 'live']; ?>
<nav class="nba-tabbar" aria-label="<?php esc_attr_e('Main', 'nirog-academy'); ?>">
  <?php foreach ($nav as $key => [$label, $url]) : ?>
    <a href="<?php echo esc_url($url); ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>><?php echo View::icon($tab_icons[$key] ?? 'home', 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html($label); ?></span></a>
  <?php endforeach; ?>
  <a href="<?php echo esc_url(Router::url('notifications')); ?>"<?php echo $view === 'notifications' ? ' aria-current="page"' : ''; ?>><?php echo View::icon('bell', 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e('Inbox', 'nirog-academy'); ?></span><?php if ($unread) : ?><b class="nba-tab-count"><?php echo (int) min(99, $unread); ?></b><?php endif; ?></a>
</nav>
<?php endif; ?>

<main id="nba-main">
  <?php if ($flash_notice || $flash_error) : ?>
    <div class="nba-wrap nba-flash-wrap">
      <?php if ($flash_notice) : ?><div class="nba-flash nba-flash-ok" role="status"><?php echo esc_html($flash_notice); ?></div><?php endif; ?>
      <?php if ($flash_error) : ?><div class="nba-flash nba-flash-error" role="alert"><?php echo esc_html($flash_error); ?></div><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- view templates escape their own output. ?>
</main>

<footer class="nba-foot">
  <div class="nba-wrap nba-foot-inner">
    <p><?php esc_html_e('Wellness education only. These courses do not replace medical advice. Speak with your doctor before changing medicines, food or activity.', 'nirog-academy'); ?></p>
    <p>
      <a href="<?php echo esc_url(Router::url('help')); ?>"><?php esc_html_e('Help', 'nirog-academy'); ?></a>
      <?php if ($support_email) : ?> · <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a><?php endif; ?>
      <?php if ($whatsapp) : ?> · <a href="<?php echo esc_url('https://wa.me/' . $whatsapp); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if ($user_id && !$bare) : ?> · <a href="<?php echo esc_url(wp_nonce_url(Router::url('logout'), 'nba_logout')); ?>"><?php esc_html_e('Log out', 'nirog-academy'); ?></a><?php endif; ?>
    </p>
  </div>
</footer>
<?php Router::foot(); ?>
</body>
</html>
