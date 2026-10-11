<?php
/**
 * The header for our theme.
 *
 * @package FastFoodNutritionCalculators
 */

$ffnc_brands_for_search = array();
$ffnc_items_for_search  = array();
foreach ( ffnc_get_live_brands() as $ffnc_slug => $ffnc_b ) {
	$ffnc_brands_for_search[] = array(
		'name'      => $ffnc_b['name'],
		'url'       => home_url( $ffnc_b['url'] ),
		'abbr'      => ffnc_get_brand_abbr( $ffnc_b ),
		'color'     => ffnc_get_brand_color( $ffnc_b ),
		'colorDark' => ffnc_darken_hex( ffnc_get_brand_color( $ffnc_b ), 0.32 ),
	);
	// Menu items point at their row in the brand page's nutrition table.
	foreach ( ffnc_get_brand_items( $ffnc_slug ) as $ffnc_it ) {
		$ffnc_items_for_search[] = array( $ffnc_it['name'], count( $ffnc_brands_for_search ) - 1, 'item-' . $ffnc_slug . '-' . $ffnc_it['id'], $ffnc_it['calories'] );
	}
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
/* Set the theme (light/dark) before first paint to avoid a flash of the wrong theme. */
(function(){
	try {
		var saved = localStorage.getItem('ffncTheme');
		var theme = saved ? saved : ( window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light' );
		document.documentElement.setAttribute('data-ffnc-theme', theme);
	} catch (e) {}
})();
</script>
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text ffnc-skip-link" href="#primary"><?php esc_html_e( 'Skip to content', 'ffnc' ); ?></a>

<header id="masthead" class="ffnc-site-header">
	<div class="ffnc-container ffnc-header-inner">

		<div class="ffnc-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ffnc-site-title-link" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) . ' — ' . __( 'Home', 'ffnc' ) ); ?>">
					<span class="ffnc-logo-mark" aria-hidden="true">
						<svg viewBox="0 0 64 64" role="img" focusable="false">
							<rect x="0" y="0" width="64" height="64" rx="16" fill="url(#ffncLogoGradient)"/>
							<defs>
								<linearGradient id="ffncLogoGradient" x1="0" y1="0" x2="1" y2="1">
									<stop offset="0%" stop-color="#2f6fed"/>
									<stop offset="100%" stop-color="#14459e"/>
								</linearGradient>
							</defs>
							<g fill="#ffffff">
								<rect x="11" y="14" width="4" height="16" rx="2"/>
								<rect x="18" y="14" width="4" height="16" rx="2"/>
								<rect x="25" y="14" width="4" height="16" rx="2"/>
								<path d="M11 28 h18 v5 a9 9 0 0 1 -9 9 a9 9 0 0 1 -9 -9 z"/>
								<rect x="18" y="40" width="4" height="14" rx="2"/>
							</g>
							<g fill="#ffd166">
								<rect x="36" y="38" width="6" height="16" rx="1.5"/>
								<rect x="45" y="29" width="6" height="25" rx="1.5"/>
								<rect x="54" y="19" width="6" height="35" rx="1.5"/>
							</g>
						</svg>
					</span>
					<span class="ffnc-site-title"><?php bloginfo( 'name' ); ?></span>
				</a>
			<?php endif; ?>
		</div>

		<div class="ffnc-header-search">
			<label for="ffnc-brand-search" class="screen-reader-text"><?php esc_html_e( 'Find a restaurant or menu item', 'ffnc' ); ?></label>
			<span class="ffnc-search-icon" aria-hidden="true">
				<svg viewBox="0 0 20 20" width="16" height="16"><path fill="currentColor" d="M13.6 12.2h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5L18.6 17l-5-4.8zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/></svg>
			</span>
			<input
				type="search"
				id="ffnc-brand-search"
				class="ffnc-brand-search-input"
				placeholder="<?php esc_attr_e( 'Search brand or item…', 'ffnc' ); ?>"
				autocomplete="off"
				role="combobox"
				aria-autocomplete="list"
				aria-expanded="false"
				aria-controls="ffnc-brand-search-results"
				data-ffnc-brand-search
			>
			<script type="application/json" id="ffnc-search-index"><?php echo wp_json_encode( array( 'b' => $ffnc_brands_for_search, 'i' => $ffnc_items_for_search ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
			<div class="ffnc-brand-search-results" id="ffnc-brand-search-results" role="listbox" data-ffnc-search-results hidden></div>
		</div>

		<div class="ffnc-header-actions">
			<button type="button" class="ffnc-theme-toggle" data-ffnc-theme-toggle aria-label="<?php esc_attr_e( 'Toggle dark mode', 'ffnc' ); ?>">
				<svg class="ffnc-icon-sun" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 4.5a1 1 0 0 1-1-1v-1a1 1 0 1 1 2 0v1a1 1 0 0 1-1 1zm0 15a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1zm9-8.5a1 1 0 0 1-1 1h-1a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1zM5 12a1 1 0 0 1-1 1H3a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1zm12.36-6.36a1 1 0 0 1 0 1.41l-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 0 1 1.41 0zM8.76 17.66a1 1 0 0 1 0 1.41l-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 0 1 1.41 0zm9.19 1.41a1 1 0 0 1-1.41 0l-.71-.71a1 1 0 1 1 1.41-1.41l.71.71a1 1 0 0 1 0 1.41zM7.34 6.76a1 1 0 0 1-1.41 0l-.71-.71a1 1 0 0 1 1.41-1.41l.71.71a1 1 0 0 1 0 1.41zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10z"/></svg>
				<svg class="ffnc-icon-moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20.7 14.9A8.8 8.8 0 0 1 9.1 3.3a.9.9 0 0 0-1.1-1.2A10.5 10.5 0 1 0 21.9 16a.9.9 0 0 0-1.2-1.1z"/></svg>
			</button>

			<button type="button" class="ffnc-menu-toggle" aria-controls="ffnc-primary-menu" aria-expanded="false">
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'ffnc' ); ?></span>
				<span class="ffnc-hamburger"></span>
			</button>
		</div>

		<nav id="ffnc-primary-menu" class="ffnc-primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'ffnc' ); ?>">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'ffnc-menu',
				'fallback_cb'    => 'ffnc_default_menu',
			) );
			?>
		</nav>
	</div>
</header>

<?php if ( ! is_front_page() ) : ?>
	<div class="ffnc-container ffnc-breadcrumb-wrap">
		<?php ffnc_breadcrumbs(); ?>
	</div>
<?php endif; ?>

<main id="primary" class="ffnc-site-main">
