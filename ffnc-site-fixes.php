<?php
/**
 * Plugin Name: FFNC Site Fixes
 * Description: Keeps the Fast Food Nutrition Calculators theme in sync with GitHub. Hostinger deploys this repo into this plugin folder; the plugin copies the theme files from theme/ into the active theme.
 * Version:     2.0.0
 * Author:      Fast Food Nutrition Calculators Team
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'FFNC_SITE_FIXES_VERSION', '2.0.0' );

// Only this theme is ever written to (matched by its style.css Text Domain).
define( 'FFNC_SITE_FIXES_THEME_DOMAIN', 'ffnc' );

/**
 * Every file under theme/, as relative path => absolute source path.
 *
 * @return array
 */
function ffnc_site_fixes_theme_files() {
	$base  = __DIR__ . '/theme/';
	$files = array();
	if ( ! is_dir( $base ) ) {
		return $files;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( $file->isFile() ) {
			$files[ substr( $file->getPathname(), strlen( $base ) ) ] = $file->getPathname();
		}
	}
	ksort( $files );
	return $files;
}

/**
 * Cheap fingerprint of theme/: changes whenever a deploy rewrites a file.
 *
 * @param array $files From ffnc_site_fixes_theme_files().
 * @return string
 */
function ffnc_site_fixes_fingerprint( $files ) {
	$parts = array();
	foreach ( $files as $rel => $path ) {
		$parts[] = $rel . ':' . filesize( $path ) . ':' . filemtime( $path );
	}
	return md5( implode( '|', $parts ) );
}

/**
 * Copy changed files from theme/ into the active theme. Never deletes.
 *
 * @return array{copied:int,failed:array}|null Null when nothing to do.
 */
function ffnc_site_fixes_sync() {
	$files = ffnc_site_fixes_theme_files();
	if ( ! $files ) {
		return null;
	}
	$fingerprint = ffnc_site_fixes_fingerprint( $files );
	if ( get_option( 'ffnc_site_fixes_synced' ) === $fingerprint ) {
		return null;
	}

	// One sync at a time.
	if ( get_transient( 'ffnc_site_fixes_sync_lock' ) ) {
		return null;
	}
	set_transient( 'ffnc_site_fixes_sync_lock', 1, 60 );

	$theme = wp_get_theme( get_template() );
	if ( FFNC_SITE_FIXES_THEME_DOMAIN !== $theme->get( 'TextDomain' ) ) {
		update_option( 'ffnc_site_fixes_sync_error', sprintf( 'Active theme "%s" is not the Fast Food Nutrition Calculators theme; nothing was copied.', $theme->get( 'Name' ) ), false );
		delete_transient( 'ffnc_site_fixes_sync_lock' );
		return null;
	}
	$target = trailingslashit( get_template_directory() );

	$copied = 0;
	$failed = array();
	foreach ( $files as $rel => $src ) {
		$dest = $target . $rel;
		if ( is_file( $dest ) && md5_file( $dest ) === md5_file( $src ) ) {
			continue;
		}
		// Write to a temp file and rename it into place, so a visitor's
		// request never loads a half-written PHP file.
		$tmp = $dest . '.ffnc-tmp';
		if ( ! wp_mkdir_p( dirname( $dest ) ) || ! @copy( $src, $tmp ) || ! @rename( $tmp, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$failed[] = $rel;
			continue;
		}
		$copied++;
	}

	if ( $failed ) {
		update_option( 'ffnc_site_fixes_sync_error', 'Could not copy: ' . implode( ', ', $failed ), false );
	} else {
		update_option( 'ffnc_site_fixes_synced', $fingerprint, false );
		delete_option( 'ffnc_site_fixes_sync_error' );
		if ( $copied ) {
			do_action( 'litespeed_purge_all' ); // LiteSpeed Cache, if active.
		}
	}
	update_option( 'ffnc_site_fixes_last_sync', array( 'time' => time(), 'copied' => $copied, 'failed' => count( $failed ) ), false );
	if ( $failed ) {
		set_transient( 'ffnc_site_fixes_sync_lock', 1, 10 * MINUTE_IN_SECONDS ); // Retry later, not on every request.
	} else {
		delete_transient( 'ffnc_site_fixes_sync_lock' );
	}
	return array( 'copied' => $copied, 'failed' => $failed );
}
add_action( 'init', 'ffnc_site_fixes_sync', 1 );

/** Tell admins when a sync could not finish. */
function ffnc_site_fixes_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$error = get_option( 'ffnc_site_fixes_sync_error' );
	if ( $error ) {
		echo '<div class="notice notice-error"><p><strong>FFNC Site Fixes:</strong> ' . esc_html( $error ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'ffnc_site_fixes_admin_notice' );
