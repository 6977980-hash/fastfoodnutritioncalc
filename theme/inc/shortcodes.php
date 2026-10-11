<?php
/**
 * Shortcodes used across calculator + article pages:
 *
 *   [ff_calculator brand="mcdonalds"]                      Interactive calculator widget.
 *   [ff_nutrition_table brand="mcdonalds" category="burgers"]   Static nutrition data table (same JSON source as the calculator).
 *   [ff_toc]                                                Auto-built table of contents from this page's headings.
 *   [ff_faq] ... [ff_faq_item question="..."]answer[/ff_faq_item] ... [/ff_faq]   FAQ accordion + FAQPage schema.
 *   [ff_disclaimer_note brand="McDonald's"]                 Short inline "not affiliated" note.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Collected per-page during rendering, then flushed into JSON-LD. */
global $ffnc_faq_collector;
$ffnc_faq_collector = array();

/* -------------------------------------------------------------------------
 * [ff_calculator]
 * ---------------------------------------------------------------------- */
function ffnc_shortcode_calculator( $atts ) {
	$atts = shortcode_atts( array( 'brand' => '' ), $atts, 'ff_calculator' );
	$slug = sanitize_title( $atts['brand'] );

	if ( empty( $slug ) ) {
		return '';
	}

	$brand      = ffnc_get_brand( $slug );
	$name       = $brand ? $brand['name'] : ucwords( str_replace( '-', ' ', $slug ) );
	$color      = ffnc_get_brand_color( $brand );
	$color_dark = ffnc_darken_hex( $color, 0.32 );
	$abbr       = ffnc_get_brand_abbr( $brand );
	$data       = ffnc_get_brand_data( $slug );
	$hub_url    = ffnc_page_url( 'calculators' ) ? ffnc_page_url( 'calculators' ) : home_url( '/' );

	ob_start();
	?>
	<div class="ffnc-calculator" data-brand="<?php echo esc_attr( $slug ); ?>" data-brand-name="<?php echo esc_attr( $name ); ?>" data-brand-abbr="<?php echo esc_attr( $abbr ); ?>" data-brand-color="<?php echo esc_attr( $color ); ?>" data-brand-color-dark="<?php echo esc_attr( $color_dark ); ?>" data-hub-url="<?php echo esc_url( $hub_url ); ?>" data-site-name="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" role="region" aria-label="<?php echo esc_attr( $name . ' ' . __( 'calorie calculator', 'ffnc' ) ); ?>">
		<?php if ( $data ) : ?>
			<?php // Same JSON file the table below is built from, inlined to skip a network request. ?>
			<script type="application/json" class="ffnc-calc-data"><?php echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<?php endif; ?>
		<div class="ffnc-calc-loading"><?php esc_html_e( 'Loading calculator…', 'ffnc' ); ?></div>
		<noscript><p class="ffnc-calc-error"><?php esc_html_e( 'The interactive calculator needs JavaScript. The complete nutrition table for every menu item is further down this page.', 'ffnc' ); ?></p></noscript>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_calculator', 'ffnc_shortcode_calculator' );

/**
 * On a calculator page, pull the [ff_calculator] shortcode's rendered
 * output out of the normal content flow so page.php can place it directly
 * under the page title — guaranteeing the calculator is always the first
 * thing visitors see, no matter where the shortcode happens to sit inside
 * the page's own content. This is deliberately template-level (not just a
 * content-authoring convention): a page whose content was imported or
 * hand-edited with the calculator lower down in the text still gets the
 * calculator-first layout, without needing that content re-imported or
 * re-ordered.
 *
 * Runs at priority 4 — before wpautop/do_shortcode (10/11) — so the raw
 * shortcode tag is still easy to find with a simple regex, and before
 * ffnc_add_heading_anchors (5) / ffnc_append_page_extras (20) so neither of
 * those needs to know this happened.
 */
function ffnc_pull_calculator_shortcode( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( '1' !== get_post_meta( get_the_ID(), '_ffnc_is_calculator_page', true ) ) {
		return $content;
	}
	if ( ! preg_match( '/\[ff_calculator[^\]]*\]/', $content, $m ) ) {
		return $content;
	}
	$GLOBALS['ffnc_top_calculator_html'] = do_shortcode( $m[0] );
	return str_replace( $m[0], '', $content );
}
add_filter( 'the_content', 'ffnc_pull_calculator_shortcode', 4 );

/* -------------------------------------------------------------------------
 * [ff_nutrition_table]
 * ---------------------------------------------------------------------- */
function ffnc_shortcode_nutrition_table( $atts ) {
	$atts = shortcode_atts( array(
		'brand'    => '',
		'category' => '', // optional category id filter
		'heading'  => 'h3', // heading level for each category (h3 inside an H2 section by default)
	), $atts, 'ff_nutrition_table' );

	$slug = sanitize_title( $atts['brand'] );
	$data = ffnc_get_brand_data( $slug );
	if ( ! $data ) {
		return '';
	}
	$meta   = ffnc_get_dataset_meta( $slug );
	$checks = ffnc_get_spot_checks_by_item( $slug );
	$hl     = in_array( $atts['heading'], array( 'h2', 'h3', 'h4' ), true ) ? $atts['heading'] : 'h3';
	$brand  = ffnc_get_brand( $slug );
	$bname  = $brand ? $brand['name'] : $data['brand'];

	$GLOBALS['ffnc_rendered_table'][ $slug ] = true;

	ob_start();
	foreach ( $data['categories'] as $cat ) {
		if ( ! empty( $atts['category'] ) && $atts['category'] !== $cat['id'] ) {
			continue;
		}
		?>
		<div class="ffnc-nutrition-table-wrap">
			<<?php echo $hl; // phpcs:ignore ?> class="ffnc-table-heading"><?php echo esc_html( $bname . ' ' . $cat['name'] ); ?></<?php echo $hl; // phpcs:ignore ?>>
			<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="<?php echo esc_attr( sprintf( __( '%s nutrition table (scrollable)', 'ffnc' ), $cat['name'] ) ); ?>">
			<table class="ffnc-nutrition-table">
				<caption class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: 1 brand 2 category */ __( '%1$s %2$s nutrition per item', 'ffnc' ), $bname, $cat['name'] ) ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Item', 'ffnc' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Calories', 'ffnc' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Protein', 'ffnc' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Carbs', 'ffnc' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Fat', 'ffnc' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Sodium', 'ffnc' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $cat['items'] as $item ) : ?>
					<tr id="<?php echo esc_attr( 'item-' . $slug . '-' . $item['id'] ); ?>">
						<th scope="row"><?php echo esc_html( $item['name'] ); ?>
							<?php
							if ( ! empty( $checks[ $item['id'] ] ) ) {
								foreach ( $checks[ $item['id'] ] as $c ) {
									if ( (float) $c['official_value'] !== (float) $c['dataset_value'] ) {
										echo '<span class="ffnc-flag">' . esc_html( sprintf(
											/* translators: 1 field 2 value 3 date */
											__( 'Official %1$s listed as %2$s on %3$s — update pending', 'ffnc' ),
											$c['field'],
											ffnc_fmt_num( $c['official_value'] ),
											ffnc_format_review_date( $c['checked'], 'M j, Y' )
										) ) . '</span>';
									}
								}
							}
							?>
						</th>
						<td><?php echo esc_html( ffnc_fmt_num( $item['calories'] ) ); ?> cal</td>
						<td><?php echo esc_html( ffnc_fmt_num( $item['protein'] ) ); ?> g</td>
						<td><?php echo esc_html( ffnc_fmt_num( $item['carbs'] ) ); ?> g</td>
						<td><?php echo esc_html( ffnc_fmt_num( $item['fat'] ) ); ?> g</td>
						<td><?php echo esc_html( ffnc_fmt_num( $item['sodium'] ) ); ?> mg</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>
		<?php
	}
	echo '<p class="ffnc-table-note">';
	if ( ! empty( $meta['market_label'] ) ) {
		/* translators: %s market */
		echo esc_html( sprintf( __( 'Market: %s. ', 'ffnc' ), $meta['market_label'] ) );
	}
	echo esc_html__( 'Values are per standard serving as named, before customizations.', 'ffnc' ) . ' ';
	if ( ! empty( $data['note'] ) ) {
		echo esc_html( $data['note'] );
	}
	echo ' <a href="#ffnc-data-source">' . esc_html__( 'Data source and review status', 'ffnc' ) . '</a>.</p>';
	return ob_get_clean();
}
add_shortcode( 'ff_nutrition_table', 'ffnc_shortcode_nutrition_table' );

/* -------------------------------------------------------------------------
 * [ff_toc] — builds a table of contents from H2/H3 headings already present
 * in the same post content, and slugifies them so the headings become
 * jump-link anchors.
 * ---------------------------------------------------------------------- */
function ffnc_shortcode_toc( $atts ) {
	$post = get_post();
	if ( ! $post ) {
		return '';
	}

	if ( ! preg_match_all( '/<h([23])[^>]*>(.*?)<\/h\1>/i', $post->post_content, $matches, PREG_SET_ORDER ) ) {
		return '';
	}

	$items = array();
	foreach ( $matches as $m ) {
		// Decode entities (e.g. "&amp;" in a heading like "Q &amp; A") before
		// stripping tags, so esc_html() below doesn't double-escape them into
		// a literal "&amp;" on the page — see also ffnc_add_heading_anchors(),
		// which decodes the same way so the two stay in sync.
		$text = wp_strip_all_tags( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
		$slug = sanitize_title( $text );
		$items[] = array( 'level' => (int) $m[1], 'text' => $text, 'slug' => $slug );
	}

	if ( empty( $items ) ) {
		return '';
	}

	ob_start();
	?>
	<nav class="ffnc-toc" aria-label="<?php esc_attr_e( 'Table of contents', 'ffnc' ); ?>">
		<p class="ffnc-toc-title"><?php esc_html_e( 'On this page', 'ffnc' ); ?></p>
		<ul>
			<?php foreach ( $items as $item ) : ?>
				<li class="ffnc-toc-level-<?php echo esc_attr( $item['level'] ); ?>">
					<a href="#<?php echo esc_attr( $item['slug'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_toc', 'ffnc_shortcode_toc' );

/**
 * Add matching id="" anchors to H2/H3 headings so the TOC links resolve,
 * but only on pages that actually use [ff_toc].
 */
function ffnc_add_heading_anchors( $content ) {
	if ( ! is_singular() || ! has_shortcode( $content, 'ff_toc' ) ) {
		return $content;
	}
	return preg_replace_callback( '/<h([23])([^>]*)>(.*?)<\/h\1>/i', function( $m ) {
		if ( stripos( $m[2], 'id=' ) !== false ) {
			return $m[0];
		}
		// Same entity-decode as ffnc_shortcode_toc() above, so a heading's
		// generated id="" always matches the slug the TOC links to.
		$slug = sanitize_title( wp_strip_all_tags( html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' ) ) );
		return '<h' . $m[1] . $m[2] . ' id="' . esc_attr( $slug ) . '">' . $m[3] . '</h' . $m[1] . '>';
	}, $content );
}
add_filter( 'the_content', 'ffnc_add_heading_anchors', 5 );

/* -------------------------------------------------------------------------
 * [ff_faq] ... [ff_faq_item question="..."]answer[/ff_faq_item] ... [/ff_faq]
 * ---------------------------------------------------------------------- */
function ffnc_shortcode_faq( $atts, $content = null ) {
	if ( is_null( $content ) ) {
		return '';
	}
	$inner = do_shortcode( $content );
	return '<div class="ffnc-faq">' . $inner . '</div>';
}
add_shortcode( 'ff_faq', 'ffnc_shortcode_faq' );

function ffnc_shortcode_faq_item( $atts, $content = null ) {
	global $ffnc_faq_collector;

	$atts = shortcode_atts( array( 'question' => '' ), $atts, 'ff_faq_item' );
	$question = trim( wp_strip_all_tags( $atts['question'] ) );
	$answer_html = trim( wpautop( do_shortcode( (string) $content ) ) );
	$answer_text = trim( wp_strip_all_tags( $answer_html ) );

	if ( '' === $question || '' === $answer_text ) {
		return '';
	}

	// get_the_ID() needs an active Loop (the_post()) to resolve. front-page.php
	// renders its FAQ shortcode directly (no Loop), so fall back to the
	// queried object id — reliably set for any singular/front-page request —
	// otherwise the homepage's FAQ entries were silently dropped from the
	// collector and no FAQPage schema was ever built for them.
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		$post_id = get_queried_object_id();
	}
	if ( $post_id ) {
		if ( ! isset( $ffnc_faq_collector[ $post_id ] ) ) {
			$ffnc_faq_collector[ $post_id ] = array();
		}
		$ffnc_faq_collector[ $post_id ][] = array( 'q' => $question, 'a' => $answer_text );
	}

	$uid = 'ffnc-faq-' . sanitize_title( $question ) . '-' . substr( md5( $question ), 0, 6 );

	ob_start();
	?>
	<div class="ffnc-faq-item">
		<h3 class="ffnc-faq-question">
			<button type="button" class="ffnc-faq-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>">
				<?php echo esc_html( $question ); ?>
				<span class="ffnc-faq-icon" aria-hidden="true"></span>
			</button>
		</h3>
		<div class="ffnc-faq-answer" id="<?php echo esc_attr( $uid ); ?>">
			<?php echo wp_kses_post( $answer_html ); ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_faq_item', 'ffnc_shortcode_faq_item' );

/* -------------------------------------------------------------------------
 * [ff_disclaimer_note brand="McDonald's"]
 * ---------------------------------------------------------------------- */
function ffnc_shortcode_disclaimer_note( $atts ) {
	$atts = shortcode_atts( array( 'brand' => 'this restaurant' ), $atts, 'ff_disclaimer_note' );
	return '<p class="ffnc-inline-disclaimer">' . sprintf(
		/* translators: %s: brand name */
		esc_html__( 'This independent calculator is not affiliated with, endorsed by, or sponsored by %s. Brand and product names are used for identification and nominative reference only. See our full %s.', 'ffnc' ),
		esc_html( $atts['brand'] ),
		'<a href="' . esc_url( ffnc_page_url( 'disclaimer' ) ? ffnc_page_url( 'disclaimer' ) : home_url( '/disclaimer/' ) ) . '">' . esc_html__( 'disclaimer', 'ffnc' ) . '</a>'
	) . '</p>';
}
add_shortcode( 'ff_disclaimer_note', 'ffnc_shortcode_disclaimer_note' );

/* -------------------------------------------------------------------------
 * [ff_related_calculators] — manual placement option (auto-append also
 * happens via inc/template-tags.php ffnc_maybe_append_related()).
 * ---------------------------------------------------------------------- */
function ffnc_shortcode_related( $atts ) {
	$atts = shortcode_atts( array( 'brands' => '' ), $atts, 'ff_related_calculators' );
	$slugs = array_filter( array_map( 'trim', explode( ',', $atts['brands'] ) ) );
	return ffnc_render_related_calculators( $slugs );
}
add_shortcode( 'ff_related_calculators', 'ffnc_shortcode_related' );
