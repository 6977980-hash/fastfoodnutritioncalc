<?php
/**
 * The footer for our theme.
 *
 * @package FastFoodNutritionCalculators
 */
?>
</main><!-- #primary -->

<footer id="colophon" class="ffnc-site-footer">
	<div class="ffnc-container ffnc-footer-grid">

		<div class="ffnc-footer-about">
			<span class="ffnc-footer-brand">
				<span class="ffnc-logo-mark ffnc-logo-mark-sm" aria-hidden="true">
					<svg viewBox="0 0 64 64" role="img" focusable="false">
						<rect x="0" y="0" width="64" height="64" rx="16" fill="#2f6fed"/>
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
			</span>
			<p><?php esc_html_e( 'Free, independent calorie and nutrition calculators for U.S. fast food menus. For general information only — not medical advice, and not affiliated with any restaurant brand.', 'ffnc' ); ?></p>
		</div>

		<nav class="ffnc-footer-col ffnc-footer-col-calculators" aria-label="<?php esc_attr_e( 'Tools', 'ffnc' ); ?>">
			<h2 class="ffnc-footer-heading"><?php esc_html_e( 'Tools', 'ffnc' ); ?></h2>
			<ul>
				<?php
				// The first six calculators + the hub, not every brand.
				foreach ( array_slice( ffnc_get_live_brands(), 0, 6 ) as $brand ) :
					?>
					<li><a href="<?php echo esc_url( home_url( $brand['url'] ) ); ?>"><?php echo esc_html( $brand['title'] ); ?></a></li>
				<?php endforeach; ?>
				<li><a href="<?php echo esc_url( ffnc_page_url( 'calculators' ) ? ffnc_page_url( 'calculators' ) : home_url( '/calculators/' ) ); ?>"><?php esc_html_e( 'All Calculators', 'ffnc' ); ?></a></li>
				<?php if ( ffnc_compare_url() ) : ?>
					<li><a href="<?php echo esc_url( ffnc_compare_url() ); ?>"><?php esc_html_e( 'Compare Menu Items', 'ffnc' ); ?></a></li>
				<?php endif; ?>
				<?php if ( ffnc_page_url( 'tools' ) ) : ?>
					<li><a href="<?php echo esc_url( ffnc_page_url( 'tools' ) ); ?>"><?php esc_html_e( 'Free Nutrition Tools', 'ffnc' ); ?></a></li>
				<?php endif; ?>
				<?php if ( ffnc_page_url( 'what-to-order' ) ) : ?>
					<li><a href="<?php echo esc_url( ffnc_page_url( 'what-to-order' ) ); ?>"><?php esc_html_e( 'What Should I Order?', 'ffnc' ); ?></a></li>
				<?php endif; ?>
				<?php if ( get_option( 'page_for_posts' ) ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ); ?>"><?php esc_html_e( 'Nutrition Guides', 'ffnc' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</nav>

		<?php $ffnc_explore = ffnc_explore_links(); ?>
		<?php if ( $ffnc_explore ) : ?>
			<nav class="ffnc-footer-col ffnc-footer-col-explore" aria-label="<?php esc_attr_e( 'Explore', 'ffnc' ); ?>">
				<h2 class="ffnc-footer-heading"><?php esc_html_e( 'Explore', 'ffnc' ); ?></h2>
				<ul>
					<?php foreach ( $ffnc_explore as $ffnc_path => $ffnc_l ) : ?>
						<?php
						if ( in_array( $ffnc_path, array( 'tools', 'what-to-order' ), true ) ) {
							continue; // Already in the Tools column.
						}
						?>
						<li><a href="<?php echo esc_url( $ffnc_l[0] ); ?>"><?php echo esc_html( $ffnc_l[1] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

		<nav class="ffnc-footer-col" aria-label="<?php esc_attr_e( 'Company', 'ffnc' ); ?>">
			<h2 class="ffnc-footer-heading"><?php esc_html_e( 'Company', 'ffnc' ); ?></h2>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'menu_class' => '' ) );
			} else {
				// Only link pages that actually exist, so the footer never
				// ships a sitewide broken link.
				$ffnc_company = array(
					array( ffnc_page_url( 'about' ), __( 'About Us', 'ffnc' ) ),
					array( ffnc_methodology_url(), __( 'Data Methodology', 'ffnc' ) ),
					array( ffnc_report_error_url(), __( 'Report a Data Error', 'ffnc' ) ),
					array( ffnc_page_url( 'contact' ), __( 'Contact', 'ffnc' ) ),
				);
				echo '<ul>';
				foreach ( $ffnc_company as $ffnc_l ) {
					if ( $ffnc_l[0] ) {
						echo '<li><a href="' . esc_url( $ffnc_l[0] ) . '">' . esc_html( $ffnc_l[1] ) . '</a></li>';
					}
				}
				echo '</ul>';
			}
			?>
		</nav>

		<nav class="ffnc-footer-col" aria-label="<?php esc_attr_e( 'Legal', 'ffnc' ); ?>">
			<h2 class="ffnc-footer-heading"><?php esc_html_e( 'Legal', 'ffnc' ); ?></h2>
			<?php
			if ( has_nav_menu( 'legal' ) ) {
				wp_nav_menu( array( 'theme_location' => 'legal', 'container' => false, 'menu_class' => '' ) );
			} else {
				$ffnc_privacy = get_privacy_policy_url() ? get_privacy_policy_url() : ffnc_page_url( 'privacy-policy' );
				$ffnc_legal   = array(
					array( ffnc_page_url( 'disclaimer' ), __( 'Disclaimer', 'ffnc' ) ),
					array( $ffnc_privacy, __( 'Privacy Policy', 'ffnc' ) ),
					array( ffnc_page_url( 'terms-of-service' ), __( 'Terms of Service', 'ffnc' ) ),
				);
				echo '<ul>';
				foreach ( $ffnc_legal as $ffnc_l ) {
					if ( $ffnc_l[0] ) {
						echo '<li><a href="' . esc_url( $ffnc_l[0] ) . '">' . esc_html( $ffnc_l[1] ) . '</a></li>';
					}
				}
				echo '</ul>';
			}
			?>
		</nav>

		<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
			<div class="ffnc-footer-col ffnc-footer-widgets">
				<?php dynamic_sidebar( 'footer-1' ); ?>
			</div>
		<?php endif; ?>

	</div>

	<div class="ffnc-container ffnc-footer-bottom">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All trademarks, logos and brand names mentioned are the property of their respective owners and are used for identification purposes only.', 'ffnc' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
