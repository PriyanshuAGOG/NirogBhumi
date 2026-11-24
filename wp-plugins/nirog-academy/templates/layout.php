<?php
/**
 * Academy page shell.
 *
 * @var string $title
 * @var string $view
 * @var string $content
 * @var bool   $bare
 */
use NirogBhumi\Academy\Frontend\Actions;
use NirogBhumi\Academy\Frontend\Router;
use NirogBhumi\Academy\Frontend\View;
use NirogBhumi\Academy\Support\I18n;

defined('ABSPATH') || exit;

$area = (string) nba()->settings->get('area_name');
$logo = View::logo_url();
// The logo is a wordmark, so next to it we only add "Academy".
$brand_label = $logo ? __('Academy', 'nirog-academy') : $area;
$user_id = get_current_user_id();
$unread = $user_id ? nba()->notifications->unread_count($user_id) : 0;
$nav = [
  'dashboard' => [__('Home', 'nirog-academy'), 'home', Router::url()],
  'programme' => [__('Programme', 'nirog-academy'), 'book', ''],
  'bonus' => [__('Bonus', 'nirog-academy'), 'gift', Router::url('bonus')],
  'resources' => [__('Downloads', 'nirog-academy'), 'file', Router::url('resources')],
  'live' => [__('Live', 'nirog-academy'), 'live', Router::url('live')],
  'profile' => [__('Profile', 'nirog-academy'), 'user', Router::url('profile')],
];
// "Programme" points to the participant's first programme.
if ($user_id && !$bare) {
  $first = Router::visible_enrollments($user_id)[0] ?? null;
  $nav['programme'][2] = $first ? Router::programme_url((int) $first->programme_id) : Router::url();
}
$active = $view === 'lesson' || $view === 'orientation' ? 'programme' : $view;
$flash_notice = Actions::flash('notice');
$flash_error = Actions::flash('error');
$lang = I18n::current();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#314936">
<title><?php echo esc_html(($title ? $title . ' · ' : '') . $area); ?></title>
<?php wp_head(); ?>
</head>
<body class="nba-body nba-view-<?php echo esc_attr($view); ?><?php echo $bare ? ' nba-bare' : ''; ?> nba-lang-<?php echo esc_attr($lang); ?>">
<a class="nba-skip" href="#nba-main"><?php esc_html_e('Skip to content', 'nirog-academy'); ?></a>
<?php if ($bare) : ?>
  <header class="nba-bare-head">
    <a class="nba-brand" href="<?php echo esc_url(home_url('/')); ?>">
      <?php if ($logo) : ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($area); ?>"><?php endif; ?>
      <span><?php echo esc_html($brand_label); ?></span>
    </a>
    <nav class="nba-lang" aria-label="<?php esc_attr_e('Language', 'nirog-academy'); ?>">
      <?php foreach (I18n::LOCALES as $code => $label) : ?>
        <a href="<?php echo esc_url(View::lang_switch_url($code)); ?>"<?php echo $code === $lang ? ' aria-current="true"' : ''; ?> lang="<?php echo esc_attr(substr($code, 0, 2)); ?>"><?php echo esc_html($label); ?></a>
      <?php endforeach; ?>
    </nav>
  </header>
  <main id="nba-main" class="nba-main nba-main-bare">
    <?php if ($flash_notice) : ?><div class="nba-flash nba-flash-ok" role="status"><?php echo esc_html($flash_notice); ?></div><?php endif; ?>
    <?php if ($flash_error) : ?><div class="nba-flash nba-flash-error" role="alert"><?php echo esc_html($flash_error); ?></div><?php endif; ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- view templates escape their own output. ?>
  </main>
<?php else : ?>
<div class="nba-app">
  <aside class="nba-sidebar" aria-label="<?php esc_attr_e('Academy', 'nirog-academy'); ?>">
    <a class="nba-brand" href="<?php echo esc_url(Router::url()); ?>">
      <?php if ($logo) : ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($area); ?>"><?php endif; ?>
      <span><?php echo esc_html($brand_label); ?></span>
    </a>
    <nav class="nba-nav">
      <?php foreach ($nav as $key => [$label, $icon, $url]) : ?>
        <a href="<?php echo esc_url($url); ?>" class="<?php echo $key === $active ? 'is-active' : ''; ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>>
          <?php echo View::icon($icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <span><?php echo esc_html($label); ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="nba-sidebar-foot">
      <a href="<?php echo esc_url(Router::url('help')); ?>"><?php echo View::icon('help', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Help', 'nirog-academy'); ?></a>
      <a href="<?php echo esc_url(wp_nonce_url(Router::url('logout'), 'nba_logout')); ?>"><?php echo View::icon('logout', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e('Log out', 'nirog-academy'); ?></a>
    </div>
  </aside>

  <div class="nba-column">
    <header class="nba-topbar">
      <a class="nba-brand nba-brand-mobile" href="<?php echo esc_url(Router::url()); ?>">
        <?php if ($logo) : ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($area); ?>"><?php endif; ?>
        <span><?php echo esc_html($brand_label); ?></span>
      </a>
      <div class="nba-topbar-actions">
        <nav class="nba-lang" aria-label="<?php esc_attr_e('Language', 'nirog-academy'); ?>">
          <?php foreach (I18n::LOCALES as $code => $label) : ?>
            <a href="<?php echo esc_url(View::lang_switch_url($code)); ?>"<?php echo $code === $lang ? ' aria-current="true"' : ''; ?> lang="<?php echo esc_attr(substr($code, 0, 2)); ?>"><?php echo esc_html($code === 'hi_IN' ? 'हिं' : 'EN'); ?></a>
          <?php endforeach; ?>
        </nav>
        <a class="nba-bell" href="<?php echo esc_url(Router::url('notifications')); ?>" aria-label="<?php echo esc_attr(sprintf(
          /* translators: %d: unread count */
          _n('Notifications, %d unread', 'Notifications, %d unread', $unread, 'nirog-academy'),
          $unread
        )); ?>">
          <?php echo View::icon('bell'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <?php if ($unread) : ?><span class="nba-bell-count"><?php echo (int) min(99, $unread); ?></span><?php endif; ?>
        </a>
      </div>
    </header>

    <main id="nba-main" class="nba-main">
      <?php if ($flash_notice) : ?><div class="nba-flash nba-flash-ok" role="status"><?php echo esc_html($flash_notice); ?></div><?php endif; ?>
      <?php if ($flash_error) : ?><div class="nba-flash nba-flash-error" role="alert"><?php echo esc_html($flash_error); ?></div><?php endif; ?>
      <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- view templates escape their own output. ?>
    </main>
  </div>

  <nav class="nba-tabbar" aria-label="<?php esc_attr_e('Main', 'nirog-academy'); ?>">
    <?php foreach (['dashboard', 'programme', 'bonus', 'profile'] as $key) : [$label, $icon, $url] = $nav[$key]; ?>
      <a href="<?php echo esc_url($url); ?>" class="<?php echo $key === $active ? 'is-active' : ''; ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>>
        <?php echo View::icon($icon, 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <span><?php echo esc_html($label); ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
</div>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
