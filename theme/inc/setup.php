<?php
/**
 * One-click creation of the recommended trust/tool pages, as DRAFTS.
 *
 * Nothing is published automatically and nothing existing is touched: the
 * button only creates pages whose slug does not exist yet, as drafts, so the
 * site owner reviews them before they go live.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ffnc_recommended_pages() {
	return array(
		'methodology'     => array(
			'title'    => __( 'How We Source and Check Nutrition Data', 'ffnc' ),
			'template' => 'page-templates/template-methodology.php',
			'content'  => '',
			'seo_title' => __( 'Nutrition Data Methodology & Sources', 'ffnc' ),
			'desc'     => __( 'Where our fast food nutrition data comes from, how serving sizes and add-ons are handled, how each restaurant dataset is reviewed, and how to report an error.', 'ffnc' ),
			'noindex'  => false,
		),
		'compare'         => array(
			'title'    => __( 'Compare Fast Food Menu Items', 'ffnc' ),
			'template' => 'page-templates/template-compare.php',
			'content'  => '',
			'seo_title' => __( 'Compare Fast Food Nutrition Side by Side', 'ffnc' ),
			'desc'     => __( 'Compare calories, protein, carbs, fat and sodium for up to three menu items from McDonald’s, KFC, Subway, Taco Bell and 11 more chains, side by side.', 'ffnc' ),
			'noindex'  => false,
		),
		'report-an-error' => array(
			'title'    => __( 'Report a Nutrition Data Error', 'ffnc' ),
			'template' => '',
			'content'  => "<p>" . __( 'Spotted a number that doesn’t match the restaurant’s official nutrition information? Tell us below. Every report is checked against the official source before we change anything.', 'ffnc' ) . "</p>\n\n[ff_report_error]",
			'seo_title' => '',
			'desc'     => __( 'Tell us about a fast food nutrition value that looks wrong. No account needed.', 'ffnc' ),
			'noindex'  => true,
		),
	);
}

function ffnc_missing_recommended_pages() {
	$missing = array();
	foreach ( ffnc_recommended_pages() as $slug => $cfg ) {
		if ( ! get_page_by_path( $slug, OBJECT, 'page' ) ) {
			$missing[ $slug ] = $cfg;
		}
	}
	return $missing;
}

function ffnc_setup_admin_notice() {
	if ( ! current_user_can( 'publish_pages' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'themes', 'edit-page' ), true ) ) {
		return;
	}
	if ( isset( $_GET['ffnc_created'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Recommended pages were created as drafts. Review them under Pages, then publish.', 'ffnc' ) . '</p></div>';
	}
	$missing = ffnc_missing_recommended_pages();
	if ( ! $missing ) {
		return;
	}
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=ffnc_create_pages' ), 'ffnc_create_pages' );
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Fast Food Nutrition theme:', 'ffnc' ) . '</strong> ';
	echo esc_html( sprintf(
		/* translators: %s list of page titles */
		__( 'These recommended pages don’t exist yet: %s.', 'ffnc' ),
		implode( ', ', wp_list_pluck( $missing, 'title' ) )
	) );
	echo ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Create them as drafts', 'ffnc' ) . '</a></p></div>';
}
add_action( 'admin_notices', 'ffnc_setup_admin_notice' );

function ffnc_handle_create_pages() {
	if ( ! current_user_can( 'publish_pages' ) || ! check_admin_referer( 'ffnc_create_pages' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ffnc' ) );
	}
	foreach ( ffnc_missing_recommended_pages() as $slug => $cfg ) {
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => $cfg['title'],
			'post_name'    => $slug,
			'post_content' => $cfg['content'],
		) );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		if ( $cfg['template'] ) {
			update_post_meta( $id, '_wp_page_template', $cfg['template'] );
		}
		if ( $cfg['seo_title'] ) {
			update_post_meta( $id, '_ffnc_seo_title', $cfg['seo_title'] );
		}
		update_post_meta( $id, '_ffnc_meta_description', $cfg['desc'] );
		if ( $cfg['noindex'] ) {
			update_post_meta( $id, '_ffnc_noindex', '1' );
		}
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=page&post_status=draft&ffnc_created=1' ) );
	exit;
}
add_action( 'admin_post_ffnc_create_pages', 'ffnc_handle_create_pages' );
