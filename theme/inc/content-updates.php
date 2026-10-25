<?php
/**
 * One-click page-text corrections (Tools → FFNC Content Updates).
 *
 * The calculator pages' prose and FAQs live in the database, not in the theme,
 * so when the nutrition datasets were re-verified (v1.3.0) the numbers quoted
 * in that prose went out of date. This screen applies the reviewed text
 * changes listed in inc/content-updates.json:
 *
 *  - Nothing runs automatically. An editor opens the screen, sees a preview of
 *    every change (found / already applied / not found) and clicks Apply.
 *  - Only an exact match (ignoring curly-vs-straight quotes, dashes, entities,
 *    inline tags and whitespace) is changed; anything else is left untouched
 *    and reported so it can be edited by hand.
 *  - Each page is saved with wp_update_post(), so WordPress keeps a revision
 *    you can restore from the page editor.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize text for comparison: strip tags, decode entities, straighten
 * quotes, unify dashes and collapse whitespace.
 *
 * @param string $s Text or HTML.
 * @return string
 */
function ffnc_cu_norm( $s ) {
	$s = preg_replace( '/<[^>]*>/', '', (string) $s );
	$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$s = str_replace(
		array( "\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{2014}", "\u{2013}", '--', "\u{00A0}", "\u{2032}", "\u{2033}" ),
		array( "'", "'", '"', '"', '-', '-', '-', ' ', "'", '"' ),
		$s
	);
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/**
 * Regex matching $text while tolerating quote style, dash style and spacing.
 *
 * @param string $text Plain text.
 * @return string
 */
function ffnc_cu_loose_pattern( $text ) {
	$text  = ffnc_cu_norm( $text );
	$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
	$out   = '';
	foreach ( $chars as $ch ) {
		// Entity alternatives let the same pattern match raw post content.
		if ( "'" === $ch ) {
			$out .= "(?:['\u{2018}\u{2019}\u{2032}]|&(?:rsquo|lsquo|#8217|#8216|#039|#39);)";
		} elseif ( '"' === $ch ) {
			$out .= "(?:[\"\u{201C}\u{201D}\u{2033}]|&(?:ldquo|rdquo|quot|#8220|#8221);)";
		} elseif ( '-' === $ch ) {
			$out .= "(?:--|[-\u{2013}\u{2014}]|&(?:ndash|mdash|#8211|#8212);)";
		} elseif ( ' ' === $ch ) {
			$out .= '\s+';
		} else {
			$out .= preg_quote( $ch, '/' );
		}
	}
	return '/' . $out . '/u';
}

/**
 * Candidate "units" of a post's raw content where a text change may apply.
 * Each unit is [ start offset, length, type ] into $content (byte offsets).
 * Types: 'attr' (a shortcode question="..." value), 'inner' (text inside a
 * <p>/<h1-6>/<li> element or an [ff_faq_item] answer), 'line' (a raw line —
 * classic-editor paragraphs are stored as plain lines).
 *
 * @param string $content Raw post content.
 * @return array
 */
function ffnc_cu_units( $content ) {
	$units = array();
	if ( preg_match_all( '/\squestion="([^"]*)"/', $content, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[1] as $g ) {
			$units[] = array( $g[1], strlen( $g[0] ), 'attr' );
		}
	}
	if ( preg_match_all( '/\[ff_faq_item[^\]]*\](.*?)\[\/ff_faq_item\]/s', $content, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[1] as $g ) {
			$units[] = array( $g[1], strlen( $g[0] ), 'inner' );
		}
	}
	if ( preg_match_all( '/<(p|h[1-6]|li)(?:\s[^>]*)?>(.*?)<\/\1>/s', $content, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[2] as $g ) {
			$units[] = array( $g[1], strlen( $g[0] ), 'inner' );
		}
	}
	$offset = 0;
	foreach ( explode( "\n", $content ) as $line ) {
		$units[] = array( $offset, strlen( $line ), 'line' );
		$offset += strlen( $line ) + 1;
	}
	return $units;
}

/**
 * Apply one change to raw content.
 *
 * @param string $content Raw content (by reference).
 * @param array  $change  { find, replace, insert_after? }.
 * @return string 'applied' | 'already' | 'not_found'
 */
function ffnc_cu_apply_change( &$content, $change ) {
	$find    = (string) $change['find'];
	$replace = (string) $change['replace'];
	$after   = isset( $change['insert_after'] ) ? (string) $change['insert_after'] : '';

	// Raw-markup changes (e.g. a broken href) are plain string replacements.
	if ( 0 === strpos( $find, '<' ) ) {
		if ( false !== strpos( $content, $find ) ) {
			$content = str_replace( $find, $replace, $content );
			return 'applied';
		}
		return false !== strpos( $content, $replace ) ? 'already' : 'not_found';
	}

	$nfind = ffnc_cu_norm( $find );
	$nrep  = ffnc_cu_norm( $replace );

	if ( $after ) {
		if ( false !== strpos( ffnc_cu_norm( $content ), ffnc_cu_norm( $after ) ) ) {
			return 'already';
		}
	} elseif ( $nrep !== $nfind && false !== strpos( ffnc_cu_norm( $content ), $nrep ) && false === strpos( ffnc_cu_norm( $content ), $nfind ) ) {
		return 'already';
	}

	foreach ( ffnc_cu_units( $content ) as $u ) {
		list( $start, $len, $type ) = $u;
		$raw  = substr( $content, $start, $len );
		$norm = ffnc_cu_norm( $raw );
		if ( '' === $norm || false === strpos( $norm, $nfind ) ) {
			continue;
		}
		// Skip a 'line' unit that holds several block elements or shortcodes;
		// a narrower unit (paragraph, FAQ answer, attribute) handles those.
		if ( 'line' === $type && ( preg_match( '/<(p|h[1-6]|li)[\s>]/', $raw ) || false !== strpos( $raw, '[ff_faq' ) ) ) {
			continue;
		}
		$in_raw = 0;
		if ( 'attr' !== $type ) {
			// The sentence sits as plain text inside the unit's markup (no tags
			// in between): replace it there and keep the unit's links and tags.
			$try = preg_replace( ffnc_cu_loose_pattern( $find ), str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $replace ) ), $raw, 1, $in_raw );
		}
		if ( $in_raw && ! $after ) {
			$content = substr( $content, 0, $start ) . $try . substr( $content, $start + $len );
			return 'applied';
		}
		if ( $norm === $nfind ) {
			$new = $replace;
		} else {
			// The sentence is part of a longer unit: rewrite that unit as plain
			// text with the sentence replaced (inline formatting in this one
			// unit is dropped; the text is otherwise unchanged).
			$plain = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( preg_replace( '/<[^>]*>/', '', $raw ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			$new   = preg_replace( ffnc_cu_loose_pattern( $find ), str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $replace ), $plain, 1, $n );
			if ( ! $n ) {
				$new = str_replace( $nfind, $replace, $norm );
			}
		}
		if ( 'attr' === $type ) {
			$new = str_replace( array( '"', '[', ']' ), array( "'", '(', ')' ), $new );
		} else {
			$new = str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $new );
		}
		if ( $after && $norm === $nfind ) {
			$new = $raw; // Keep the matched unit as is; only insert after it.
		}
		$content = substr( $content, 0, $start ) . $new . substr( $content, $start + $len );
		if ( $after ) {
			$pos = $start + strlen( $new );
			// Step past the element's closing tag and a block-editor end comment.
			if ( preg_match( '/\G\s*<\/(p|h[1-6]|li)>/', $content, $cm, 0, $pos ) ) {
				$pos += strlen( $cm[0] );
			}
			$block = false;
			if ( preg_match( '/\G\s*<!-- \/wp:[a-z\/-]+ -->/', $content, $cm, 0, $pos ) ) {
				$pos  += strlen( $cm[0] );
				$block = true;
			}
			$insert  = $block ? "\n\n<!-- wp:html -->\n" . $after . "\n<!-- /wp:html -->" : "\n\n" . $after;
			$content = substr( $content, 0, $pos ) . $insert . substr( $content, $pos );
		}
		return 'applied';
	}
	return 'not_found';
}

/**
 * The list of reviewed changes.
 *
 * @return array
 */
function ffnc_cu_get_updates() {
	$path = FFNC_DIR . '/inc/content-updates.json';
	$data = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
	return is_array( $data ) ? $data : array();
}

/**
 * Run all changes against the current content (dry run unless $save).
 *
 * @param bool $save Save changed pages.
 * @return array Report rows.
 */
function ffnc_cu_run( $save = false ) {
	$report = array();
	foreach ( ffnc_cu_get_updates() as $page_cfg ) {
		// Blog posts are listed with "post_type": "post"; everything else is a page.
		$page = get_page_by_path( $page_cfg['page'], OBJECT, isset( $page_cfg['post_type'] ) ? $page_cfg['post_type'] : 'page' );
		if ( ! $page ) {
			$report[] = array( 'page' => $page_cfg['page'], 'status' => 'page_missing', 'find' => '' );
			continue;
		}
		$content  = $page->post_content;
		$original = $content;
		foreach ( isset( $page_cfg['changes'] ) ? $page_cfg['changes'] : array() as $change ) {
			$status   = ffnc_cu_apply_change( $content, $change );
			$report[] = array( 'page' => $page_cfg['page'], 'status' => $status, 'find' => $change['find'], 'replace' => $change['replace'] );
		}
		// Optional new page title: { "find": old title, "replace": new title }.
		$title = $page->post_title;
		if ( ! empty( $page_cfg['title'] ) ) {
			$t = $page_cfg['title'];
			if ( ffnc_cu_norm( $title ) === ffnc_cu_norm( $t['find'] ) ) {
				$title  = $t['replace'];
				$status = 'applied';
			} else {
				$status = ffnc_cu_norm( $title ) === ffnc_cu_norm( $t['replace'] ) ? 'already' : 'not_found';
			}
			$report[] = array( 'page' => $page_cfg['page'] . ' (title)', 'status' => $status, 'find' => $t['find'], 'replace' => $t['replace'] );
		}
		// Optional SEO fields from the Page Options box, set to the given text.
		$meta = array();
		foreach ( isset( $page_cfg['meta'] ) ? (array) $page_cfg['meta'] : array() as $key => $value ) {
			if ( ! in_array( $key, array( '_ffnc_seo_title', '_ffnc_meta_description' ), true ) ) {
				continue;
			}
			$old    = (string) get_post_meta( $page->ID, $key, true );
			$status = $old === $value ? 'already' : 'applied';
			if ( 'applied' === $status ) {
				$meta[ $key ] = $value;
			}
			$report[] = array( 'page' => $page_cfg['page'] . ' (' . $key . ')', 'status' => $status, 'find' => $old, 'replace' => $value );
		}
		if ( $save && ( $content !== $original || $title !== $page->post_title ) ) {
			wp_update_post( array(
				'ID'           => $page->ID,
				'post_title'   => wp_slash( $title ),
				'post_content' => wp_slash( $content ),
			) );
		}
		if ( $save ) {
			foreach ( $meta as $key => $value ) {
				update_post_meta( $page->ID, $key, $value );
			}
		}
	}
	if ( $save ) {
		do_action( 'litespeed_purge_all' ); // LiteSpeed Cache, if active.
		if ( function_exists( 'rank_math' ) ) {
			do_action( 'rank_math/sitemap/invalidate_cache' );
		}
	}
	return $report;
}

function ffnc_cu_admin_menu() {
	add_management_page(
		__( 'FFNC Content Updates', 'ffnc' ),
		__( 'FFNC Content Updates', 'ffnc' ),
		'edit_pages',
		'ffnc-content-updates',
		'ffnc_cu_admin_page'
	);
}
add_action( 'admin_menu', 'ffnc_cu_admin_menu' );

function ffnc_cu_admin_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ffnc' ) );
	}
	$applied = false;
	if ( isset( $_POST['ffnc_cu_apply'] ) ) {
		check_admin_referer( 'ffnc_cu_apply' );
		$report  = ffnc_cu_run( true );
		$applied = true;
	} else {
		$report = ffnc_cu_run( false );
	}
	$counts = array_count_values( wp_list_pluck( $report, 'status' ) );
	$labels = array(
		'applied'      => $applied ? __( 'Changed', 'ffnc' ) : __( 'Ready to change', 'ffnc' ),
		'already'      => __( 'Already up to date', 'ffnc' ),
		'not_found'    => __( 'Not found — edit by hand', 'ffnc' ),
		'page_missing' => __( 'Page not found', 'ffnc' ),
	);
	echo '<div class="wrap"><h1>' . esc_html__( 'FFNC Content Updates', 'ffnc' ) . '</h1>';
	echo '<p>' . esc_html__( 'Reviewed text corrections for the calculator pages (numbers now match the verified datasets; subjective "healthiest / best / clear winner" wording replaced with measurable comparisons) and the Contact page. Each page is saved as a new revision, so any change can be restored from the page editor.', 'ffnc' ) . '</p>';
	echo '<p><strong>' . esc_html( sprintf( '%s: %d · %s: %d · %s: %d', $labels['applied'], isset( $counts['applied'] ) ? $counts['applied'] : 0, $labels['already'], isset( $counts['already'] ) ? $counts['already'] : 0, $labels['not_found'], isset( $counts['not_found'] ) ? $counts['not_found'] : 0 ) ) . '</strong></p>';
	if ( ! $applied && ! empty( $counts['applied'] ) ) {
		echo '<form method="post">';
		wp_nonce_field( 'ffnc_cu_apply' );
		echo '<p><button type="submit" name="ffnc_cu_apply" value="1" class="button button-primary">' . esc_html__( 'Apply the changes marked “Ready to change”', 'ffnc' ) . '</button></p></form>';
	}
	if ( $applied ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Done. Pages were saved as new revisions and the LiteSpeed cache purge was requested. Open the pages to check them.', 'ffnc' ) . '</p></div>';
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'ffnc' ) . '</th><th>' . esc_html__( 'Status', 'ffnc' ) . '</th><th>' . esc_html__( 'Current text', 'ffnc' ) . '</th><th>' . esc_html__( 'New text', 'ffnc' ) . '</th></tr></thead><tbody>';
	foreach ( $report as $row ) {
		echo '<tr><td>' . esc_html( $row['page'] ) . '</td><td>' . esc_html( isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : $row['status'] ) . '</td><td>' . esc_html( $row['find'] ) . '</td><td>' . esc_html( isset( $row['replace'] ) ? $row['replace'] : '' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
