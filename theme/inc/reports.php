<?php
/**
 * "Report a nutrition data error" workflow.
 *
 *  - [ff_report_error] renders a short, account-free form.
 *  - Submissions are saved as private "Data reports" in wp-admin (never
 *    public, never indexed) and the site admin gets an email.
 *  - Spam protection without third-party services: honeypot field, minimum
 *    fill time, and a per-visitor hourly limit (hashed, not stored).
 *  - No IP address is stored. The reporter's email is optional.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ffnc_register_report_cpt() {
	register_post_type( 'ffnc_report', array(
		'labels'              => array(
			'name'          => __( 'Data reports', 'ffnc' ),
			'singular_name' => __( 'Data report', 'ffnc' ),
			'menu_name'     => __( 'Data reports', 'ffnc' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => false,
		'menu_icon'           => 'dashicons-flag',
		'supports'            => array( 'title', 'editor' ),
		'capability_type'     => 'post',
		'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'        => true,
	) );
}
add_action( 'init', 'ffnc_register_report_cpt' );

/**
 * Form shortcode.
 */
function ffnc_shortcode_report_error() {
	$sent   = isset( $_GET['report'] ) ? sanitize_key( wp_unslash( $_GET['report'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$preset = isset( $_GET['brand'] ) ? sanitize_title( wp_unslash( $_GET['brand'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	ob_start();
	if ( 'sent' === $sent ) {
		echo '<p class="ffnc-notice ffnc-notice-ok" role="status">' . esc_html__( 'Thank you — your report was received. We review every report against the official source before changing any value.', 'ffnc' ) . '</p>';
	} elseif ( 'error' === $sent ) {
		echo '<p class="ffnc-notice ffnc-notice-err" role="alert">' . esc_html__( 'Sorry, the report could not be sent. Please check the required fields and try again.', 'ffnc' ) . '</p>';
	} elseif ( 'limit' === $sent ) {
		echo '<p class="ffnc-notice ffnc-notice-err" role="alert">' . esc_html__( 'Too many reports from this connection in the last hour. Please try again later.', 'ffnc' ) . '</p>';
	}
	?>
	<form class="ffnc-report-form" id="ffnc-report" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ffnc_report_error">
		<input type="hidden" name="ffnc_ts" value="<?php echo esc_attr( time() ); ?>">
		<input type="hidden" name="ffnc_return" value="<?php echo esc_url( get_permalink() ); ?>">
		<div class="ffnc-hp" aria-hidden="true"><label for="ffnc_website"><?php esc_html_e( 'Leave this empty', 'ffnc' ); ?></label><input type="text" id="ffnc_website" name="ffnc_website" tabindex="-1" autocomplete="off"></div>

		<p><label for="ffnc_r_brand"><?php esc_html_e( 'Restaurant', 'ffnc' ); ?> <span aria-hidden="true">*</span></label>
		<select id="ffnc_r_brand" name="ffnc_r_brand" required>
			<option value=""><?php esc_html_e( 'Choose a restaurant', 'ffnc' ); ?></option>
			<?php foreach ( ffnc_get_live_brands() as $slug => $b ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $preset, $slug ); ?>><?php echo esc_html( $b['name'] ); ?></option>
			<?php endforeach; ?>
		</select></p>

		<p><label for="ffnc_r_item"><?php esc_html_e( 'Menu item', 'ffnc' ); ?> <span aria-hidden="true">*</span></label>
		<input type="text" id="ffnc_r_item" name="ffnc_r_item" required maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Big Mac', 'ffnc' ); ?>"></p>

		<p><label for="ffnc_r_wrong"><?php esc_html_e( 'Which value looks wrong?', 'ffnc' ); ?> <span aria-hidden="true">*</span></label>
		<input type="text" id="ffnc_r_wrong" name="ffnc_r_wrong" required maxlength="160" placeholder="<?php esc_attr_e( 'e.g. Calories shows 540', 'ffnc' ); ?>"></p>

		<p><label for="ffnc_r_correct"><?php esc_html_e( 'Correct value, if you know it', 'ffnc' ); ?></label>
		<input type="text" id="ffnc_r_correct" name="ffnc_r_correct" maxlength="160"></p>

		<p><label for="ffnc_r_source"><?php esc_html_e( 'Source link (ideally the restaurant’s official nutrition page)', 'ffnc' ); ?></label>
		<input type="url" id="ffnc_r_source" name="ffnc_r_source" maxlength="500" placeholder="https://"></p>

		<p><label for="ffnc_r_comment"><?php esc_html_e( 'Anything else? (country, size, date you checked)', 'ffnc' ); ?></label>
		<textarea id="ffnc_r_comment" name="ffnc_r_comment" rows="4" maxlength="2000"></textarea></p>

		<p><label for="ffnc_r_email"><?php esc_html_e( 'Your email (optional — only if you’d like a reply)', 'ffnc' ); ?></label>
		<input type="email" id="ffnc_r_email" name="ffnc_r_email" maxlength="160" autocomplete="email"></p>

		<p class="ffnc-report-privacy"><?php esc_html_e( 'We store only what you type here. We do not store your IP address. Reports are private and used only to correct data.', 'ffnc' ); ?></p>
		<p><button type="submit" class="ffnc-btn ffnc-btn-solid"><?php esc_html_e( 'Send report', 'ffnc' ); ?></button></p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_report_error', 'ffnc_shortcode_report_error' );

/**
 * Handle a submission.
 */
function ffnc_handle_report_error() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public, anonymous, low-risk form; protected by honeypot + timing + rate limit.
	$return = isset( $_POST['ffnc_return'] ) ? esc_url_raw( wp_unslash( $_POST['ffnc_return'] ) ) : home_url( '/' );
	$return = wp_validate_redirect( $return, home_url( '/' ) );
	$go     = function ( $status ) use ( $return ) {
		wp_safe_redirect( add_query_arg( 'report', $status, $return ) . '#ffnc-report' );
		exit;
	};

	// Bots: honeypot filled or form submitted implausibly fast → pretend success.
	$ts = isset( $_POST['ffnc_ts'] ) ? (int) $_POST['ffnc_ts'] : 0;
	if ( ! empty( $_POST['ffnc_website'] ) || ( time() - $ts ) < 3 ) {
		$go( 'sent' );
	}

	// Rate limit: 5 per hour per visitor. Hash only, kept for an hour, never saved to the DB as content.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'ffnc_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		$go( 'limit' );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$brand   = isset( $_POST['ffnc_r_brand'] ) ? sanitize_title( wp_unslash( $_POST['ffnc_r_brand'] ) ) : '';
	$item    = isset( $_POST['ffnc_r_item'] ) ? sanitize_text_field( wp_unslash( $_POST['ffnc_r_item'] ) ) : '';
	$wrong   = isset( $_POST['ffnc_r_wrong'] ) ? sanitize_text_field( wp_unslash( $_POST['ffnc_r_wrong'] ) ) : '';
	$correct = isset( $_POST['ffnc_r_correct'] ) ? sanitize_text_field( wp_unslash( $_POST['ffnc_r_correct'] ) ) : '';
	$source  = isset( $_POST['ffnc_r_source'] ) ? esc_url_raw( wp_unslash( $_POST['ffnc_r_source'] ) ) : '';
	$comment = isset( $_POST['ffnc_r_comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ffnc_r_comment'] ) ) : '';
	$email   = isset( $_POST['ffnc_r_email'] ) ? sanitize_email( wp_unslash( $_POST['ffnc_r_email'] ) ) : '';
	// phpcs:enable

	$b = ffnc_get_brand( $brand );
	if ( ! $b || '' === $item || '' === $wrong ) {
		$go( 'error' );
	}

	$lines = array(
		'Restaurant: ' . $b['name'],
		'Menu item: ' . $item,
		'Value that looks wrong: ' . $wrong,
		'Correct value: ' . ( $correct ? $correct : '—' ),
		'Source: ' . ( $source ? $source : '—' ),
		'Comment: ' . ( $comment ? $comment : '—' ),
		'Reply email: ' . ( $email ? $email : '—' ),
	);
	$post_id = wp_insert_post( array(
		'post_type'    => 'ffnc_report',
		'post_status'  => 'private',
		'post_title'   => wp_trim_words( $b['name'] . ' — ' . $item . ': ' . $wrong, 20, '…' ),
		'post_content' => implode( "\n", array_map( 'esc_html', $lines ) ),
	), true );
	if ( is_wp_error( $post_id ) ) {
		$go( 'error' );
	}
	update_post_meta( $post_id, '_ffnc_report_brand', $brand );

	wp_mail(
		get_option( 'admin_email' ),
		/* translators: %s: site name */
		sprintf( __( '[%s] New nutrition data report', 'ffnc' ), get_bloginfo( 'name' ) ),
		implode( "\n", $lines ) . "\n\n" . admin_url( 'post.php?post=' . $post_id . '&action=edit' )
	);
	$go( 'sent' );
}
add_action( 'admin_post_nopriv_ffnc_report_error', 'ffnc_handle_report_error' );
add_action( 'admin_post_ffnc_report_error', 'ffnc_handle_report_error' );
