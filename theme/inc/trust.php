<?php
/**
 * Trust, provenance and calculator-page enrichment.
 *
 *  - Data source / review-status box (visible on every calculator page).
 *  - Crawlable nutrition table auto-added to calculator pages whose content
 *    doesn't already contain [ff_nutrition_table].
 *  - Default "related calculators" + next-step links when a page has none.
 *  - [ff_data_source], [ff_item_facts], [ff_dataset_status] shortcodes.
 *
 * Everything reads from inc/data.php, so tables, calculator and facts boxes
 * can never disagree.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The person responsible for the site (About page, bylines, schema,
 * privacy policy). Real details given by the owner; no credentials are
 * claimed beyond what the owner stated.
 *
 * @return array{name: string, role: string, bio: string, same_as: string[]}
 */
function ffnc_site_owner() {
	return apply_filters( 'ffnc_site_owner', array(
		'name'    => 'Ali Ahmad',
		'role'    => __( 'Founder and editor', 'ffnc' ),
		'bio'     => __( 'Ali Ahmad is a web developer with a strong interest in health and nutrition. The site was built for people who work out and still want fast food now and then, so they can see exactly what they are eating, how much, and in what portion.', 'ffnc' ),
		'same_as' => array( 'https://www.linkedin.com/in/ali-ahmad-chaudhry-12777486/' ),
	) );
}

/** schema.org Person for the owner, linked from posts and the About page. */
function ffnc_owner_schema() {
	$o     = ffnc_site_owner();
	$about = ffnc_page_url( 'about' );
	return array_filter( array(
		'@type'       => 'Person',
		'@id'         => home_url( '/#owner' ),
		'name'        => $o['name'],
		'jobTitle'    => $o['role'],
		'description' => $o['bio'],
		'url'         => $about ? $about : home_url( '/' ),
		'sameAs'      => $o['same_as'],
		'worksFor'    => array( '@id' => home_url( '/#organization' ) ),
	) );
}

/**
 * Rank Math: blog posts are written by the owner (not the generic "Team"
 * WordPress user), and the About page describes the owner.
 *
 * @param array $data JSON-LD entities keyed by id.
 * @return array
 */
function ffnc_rank_math_owner_schema( $data ) {
	if ( is_singular( 'post' ) ) {
		foreach ( $data as $key => $entity ) {
			$type = isset( $entity['@type'] ) ? (array) $entity['@type'] : array();
			if ( array( 'Person' ) === $type && isset( $entity['@id'] ) && false !== strpos( $entity['@id'], '/author/' ) ) {
				unset( $data[ $key ] );
			}
			if ( array_intersect( $type, array( 'Article', 'BlogPosting', 'NewsArticle' ) ) ) {
				$data[ $key ]['author'] = array( '@id' => home_url( '/#owner' ) );
			}
		}
		$data['ffncOwner'] = ffnc_owner_schema();
	} elseif ( is_page( 'about' ) ) {
		$data['ffncOwner'] = ffnc_owner_schema();
	}
	return $data;
}
add_filter( 'rank_math/json_ld', 'ffnc_rank_math_owner_schema', 100 );

/** "Who runs this site" box for the About page ([ff_site_owner]). */
add_shortcode( 'ff_site_owner', function () {
	$o    = ffnc_site_owner();
	$link = $o['same_as'] ? ' <a href="' . esc_url( $o['same_as'][0] ) . '" rel="noopener me" target="_blank">' . esc_html__( 'LinkedIn profile', 'ffnc' ) . '</a>' : '';
	return '<section class="ffnc-owner" aria-labelledby="ffnc-owner-title"><h2 id="ffnc-owner-title">' . esc_html__( 'Who runs this site', 'ffnc' ) . '</h2>' .
		'<p><strong>' . esc_html( $o['name'] ) . '</strong> · ' . esc_html( $o['role'] ) . '</p>' .
		'<p>' . esc_html( $o['bio'] ) . $link . '</p>' .
		'<p>' . esc_html( sprintf( /* translators: %s name */ __( '%s writes the guides, builds the calculators and checks every dataset against the restaurant’s official nutrition information. No dietitian or medical professional reviews the site at the moment.', 'ffnc' ), $o['name'] ) ) . '</p></section>';
} );

/**
 * Menu-similarity clusters used for default related-calculator links.
 */
function ffnc_brand_clusters() {
	return array(
		'burgers' => array( 'mcdonalds', 'burger-king', 'wendys', 'five-guys', 'dairy-queen' ),
		'chicken' => array( 'kfc', 'chick-fil-a', 'popeyes', 'mcdonalds', 'wendys' ),
		'mexican' => array( 'chipotle', 'qdoba', 'taco-bell' ),
		'pizza'   => array( 'dominos', 'pizza-hut' ),
		'fastcasual' => array( 'panera-bread', 'subway', 'chipotle', 'panda-express' ),
		'breakfast'  => array( 'dunkin', 'mcdonalds', 'panera-bread' ),
	);
}

/**
 * Up to $limit related brand slugs for a brand, from shared clusters.
 *
 * @param string $slug  Brand.
 * @param int    $limit Max.
 * @return array
 */
function ffnc_default_related_brands( $slug, $limit = 4 ) {
	$scores = array();
	foreach ( ffnc_brand_clusters() as $members ) {
		if ( ! in_array( $slug, $members, true ) ) {
			continue;
		}
		foreach ( $members as $m ) {
			if ( $m !== $slug ) {
				$scores[ $m ] = ( isset( $scores[ $m ] ) ? $scores[ $m ] : 0 ) + 1;
			}
		}
	}
	arsort( $scores );
	$live = ffnc_get_live_brands();
	$out  = array();
	foreach ( array_keys( $scores ) as $m ) {
		if ( isset( $live[ $m ] ) ) {
			$out[] = $m;
		}
	}
	return array_slice( $out, 0, $limit );
}

/**
 * The data-source / review-status box.
 *
 * @param string $slug Brand slug.
 * @return string
 */
function ffnc_render_data_source_box( $slug ) {
	$data = ffnc_get_brand_data( $slug );
	if ( ! $data ) {
		return '';
	}
	$meta     = ffnc_get_dataset_meta( $slug );
	$brand    = ffnc_get_brand( $slug );
	$name     = $brand ? $brand['name'] : $data['brand'];
	$verified = ffnc_dataset_is_verified( $slug );
	$items    = ffnc_get_brand_items( $slug );
	$method   = ffnc_methodology_url();
	$report   = ffnc_report_error_url();
	$contact  = ffnc_page_url( 'contact' );

	ob_start();
	?>
	<section class="ffnc-data-source" id="ffnc-data-source" aria-labelledby="ffnc-data-source-title">
		<h2 id="ffnc-data-source-title"><?php echo esc_html( sprintf( /* translators: %s brand */ __( '%s nutrition data source', 'ffnc' ), $name ) ); ?></h2>
		<dl class="ffnc-source-list">
			<div><dt><?php esc_html_e( 'Restaurant', 'ffnc' ); ?></dt><dd><?php echo esc_html( $name ); ?></dd></div>
			<?php if ( $meta['market_label'] ) : ?>
				<div><dt><?php esc_html_e( 'Market', 'ffnc' ); ?></dt><dd><?php echo esc_html( sprintf( /* translators: %s market */ __( '%s menu only — values differ in other countries', 'ffnc' ), $meta['market_label'] ) ); ?></dd></div>
			<?php endif; ?>
			<div><dt><?php esc_html_e( 'Items covered', 'ffnc' ); ?></dt><dd><?php echo esc_html( sprintf( /* translators: %d count */ _n( '%d menu item or size', '%d menu items and sizes', count( $items ), 'ffnc' ), count( $items ) ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Data source', 'ffnc' ); ?></dt><dd>
				<?php if ( $verified ) : ?>
					<?php esc_html_e( 'Official restaurant nutrition information', 'ffnc' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Compiled reference values (not yet checked against the official source)', 'ffnc' ); ?>
				<?php endif; ?>
			</dd></div>
			<?php if ( $meta['source_url'] ) : ?>
				<div><dt><?php esc_html_e( 'Official reference', 'ffnc' ); ?></dt><dd><a href="<?php echo esc_url( $meta['source_url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $meta['source_name'] ? $meta['source_name'] : $meta['source_url'] ); ?></a></dd></div>
			<?php endif; ?>
			<div><dt><?php esc_html_e( 'Verification status', 'ffnc' ); ?></dt><dd>
				<?php if ( $verified ) : ?>
					<span class="ffnc-status ffnc-status-ok"><?php esc_html_e( 'Verified', 'ffnc' ); ?></span>
					<?php esc_html_e( 'Every value was checked against the official source.', 'ffnc' ); ?>
				<?php else : ?>
					<span class="ffnc-status ffnc-status-pending"><?php esc_html_e( 'Pending', 'ffnc' ); ?></span>
					<?php esc_html_e( 'These are compiled reference values that have not yet been checked line-by-line against the official source. Treat them as estimates.', 'ffnc' ); ?>
				<?php endif; ?>
			</dd></div>
			<div><dt><?php echo $verified ? esc_html__( 'Last verified', 'ffnc' ) : esc_html__( 'Last reviewed', 'ffnc' ); ?></dt><dd>
				<?php
				echo $meta['last_reviewed']
					? '<time datetime="' . esc_attr( $meta['last_reviewed'] ) . '">' . esc_html( ffnc_format_review_date( $meta['last_reviewed'], 'F j, Y' ) ) . '</time>'
					: esc_html__( 'Not yet reviewed', 'ffnc' );
				if ( ! empty( $meta['review_detail'] ) ) {
					echo ' <span class="ffnc-review-detail">' . esc_html( $meta['review_detail'] ) . '</span>';
				}
				?>
			</dd></div>
			<div><dt><?php esc_html_e( 'Serving basis', 'ffnc' ); ?></dt><dd><?php echo esc_html( ! empty( $meta['serving_basis'] ) ? $meta['serving_basis'] : __( 'One standard menu serving as named.', 'ffnc' ) ); ?></dd></div>
			<?php if ( ! empty( $meta['addon_macros'] ) ) : ?>
				<div><dt><?php esc_html_e( 'Add-ons', 'ffnc' ); ?></dt><dd><?php echo esc_html( $meta['addon_macros'] ); ?></dd></div>
			<?php endif; ?>
		</dl>
		<?php
		$diffs = array_filter( (array) $meta['spot_checks'], function ( $c ) {
			return (float) $c['official_value'] !== (float) $c['dataset_value'];
		} );
		if ( $meta['spot_checks'] ) :
			?>
			<p class="ffnc-source-spot"><?php
				echo esc_html( sprintf(
					/* translators: 1 checked count 2 diff count */
					__( 'Spot check: %1$d values were compared with the official source; %2$d differed and are flagged in the table above until the full review is done.', 'ffnc' ),
					count( $meta['spot_checks'] ),
					count( $diffs )
				) );
			?></p>
		<?php endif; ?>
		<p class="ffnc-source-links">
			<?php if ( $method ) : ?><a href="<?php echo esc_url( $method ); ?>"><?php esc_html_e( 'How we source and check nutrition data', 'ffnc' ); ?></a><?php endif; ?>
			<?php if ( $report ) : ?><a href="<?php echo esc_url( add_query_arg( 'brand', $slug, $report ) ); ?>" rel="nofollow"><?php esc_html_e( 'Report a nutrition data error', 'ffnc' ); ?></a>
			<?php elseif ( $contact ) : ?><a href="<?php echo esc_url( $contact ); ?>"><?php esc_html_e( 'Report a nutrition data error', 'ffnc' ); ?></a><?php endif; ?>
		</p>
		<?php
		// The page's own [ff_disclaimer_note] already states the no-affiliation
		// point; don't repeat it in the same page's source box.
		$ffnc_has_note = get_post() && has_shortcode( get_post()->post_content, 'ff_disclaimer_note' );
		?>
		<p class="ffnc-source-independent"><?php
		echo esc_html( $ffnc_has_note
			? __( 'Always confirm current values with the restaurant, especially for allergies or medical diets.', 'ffnc' )
			: sprintf( /* translators: %s brand */ __( 'This site is independent and is not affiliated with, endorsed by, or sponsored by %s. Always confirm current values with the restaurant, especially for allergies or medical diets.', 'ffnc' ), $name ) );
		?></p>
	</section>
	<?php
	return ob_get_clean();
}

function ffnc_shortcode_data_source( $atts ) {
	$atts = shortcode_atts( array( 'brand' => '' ), $atts, 'ff_data_source' );
	$slug = sanitize_title( $atts['brand'] ? $atts['brand'] : ffnc_get_page_brand_slug() );
	$GLOBALS['ffnc_rendered_source'][ $slug ] = true;
	return ffnc_render_data_source_box( $slug );
}
add_shortcode( 'ff_data_source', 'ffnc_shortcode_data_source' );

/**
 * [ff_item_facts brand="mcdonalds" item="big-mac"]
 * Answer-first fact box for a single item, straight from the dataset.
 * Intended for hand-written item pages; it refuses to show an item that
 * doesn't exist rather than inventing anything.
 */
function ffnc_shortcode_item_facts( $atts ) {
	$atts = shortcode_atts( array( 'brand' => '', 'item' => '' ), $atts, 'ff_item_facts' );
	$slug = sanitize_title( $atts['brand'] );
	$item = ffnc_get_brand_item( $slug, sanitize_title( $atts['item'] ) );
	if ( ! $item ) {
		return current_user_can( 'edit_posts' ) ? '<p class="ffnc-flag">' . esc_html__( '[ff_item_facts]: item not found in dataset.', 'ffnc' ) . '</p>' : '';
	}
	$meta   = ffnc_get_dataset_meta( $slug );
	$brand  = ffnc_get_brand( $slug );
	$checks = ffnc_get_spot_checks_by_item( $slug );
	ob_start();
	?>
	<div class="ffnc-item-facts">
		<p class="ffnc-item-facts-answer"><?php
			echo esc_html( sprintf(
				/* translators: 1 brand 2 item 3 cal 4 protein 5 carbs 6 fat 7 sodium */
				__( 'In our %1$s dataset, one %2$s has %3$s calories, %4$sg protein, %5$sg carbohydrates, %6$sg fat and %7$smg sodium.', 'ffnc' ),
				$brand ? $brand['name'] : '',
				$item['name'],
				ffnc_fmt_num( $item['calories'] ),
				ffnc_fmt_num( $item['protein'] ),
				ffnc_fmt_num( $item['carbs'] ),
				ffnc_fmt_num( $item['fat'] ),
				ffnc_fmt_num( $item['sodium'] )
			) );
		?></p>
		<?php if ( ! empty( $checks[ $item['id'] ] ) ) : foreach ( $checks[ $item['id'] ] as $c ) : if ( (float) $c['official_value'] !== (float) $c['dataset_value'] ) : ?>
			<p class="ffnc-flag"><?php echo esc_html( sprintf( __( 'Note: the official source listed %1$s %2$s on %3$s. Our full update is pending.', 'ffnc' ), ffnc_fmt_num( $c['official_value'] ), $c['field'], ffnc_format_review_date( $c['checked'], 'M j, Y' ) ) ); ?></p>
		<?php endif; endforeach; endif; ?>
		<p class="ffnc-item-facts-meta"><?php
			echo esc_html( trim( ( $meta['market_label'] ? $meta['market_label'] . ' menu · ' : '' ) . ( ffnc_dataset_is_verified( $slug )
				? sprintf( __( 'Checked %s', 'ffnc' ), ffnc_format_review_date( $meta['last_reviewed'] ) )
				: __( 'Not yet verified against the official source', 'ffnc' ) ) ) );
		?></p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_item_facts', 'ffnc_shortcode_item_facts' );

/**
 * [ff_dataset_status] — table of every dataset's market / source / status.
 */
function ffnc_shortcode_dataset_status() {
	ob_start();
	?>
	<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Dataset review status (scrollable)', 'ffnc' ); ?>">
	<table class="ffnc-nutrition-table ffnc-status-table">
		<caption class="screen-reader-text"><?php esc_html_e( 'Review status of each restaurant dataset', 'ffnc' ); ?></caption>
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Restaurant', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Market', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Items', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Official reference', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Last reviewed', 'ffnc' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( ffnc_get_live_brands() as $slug => $brand ) : $meta = ffnc_get_dataset_meta( $slug ); ?>
			<tr>
				<th scope="row"><a href="<?php echo esc_url( home_url( $brand['url'] ) ); ?>"><?php echo esc_html( $brand['name'] ); ?></a></th>
				<td><?php echo esc_html( $meta['market'] ? $meta['market'] : '—' ); ?></td>
				<td><?php echo esc_html( count( ffnc_get_brand_items( $slug ) ) ); ?></td>
				<td><?php if ( $meta['source_url'] ) : ?><a href="<?php echo esc_url( $meta['source_url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( wp_parse_url( $meta['source_url'], PHP_URL_HOST ) ); ?></a><?php else : ?>—<?php endif; ?></td>
				<td><?php echo ffnc_dataset_is_verified( $slug ) ? esc_html__( 'Verified', 'ffnc' ) : esc_html__( 'Pending', 'ffnc' ); ?></td>
				<td><?php echo $meta['last_reviewed'] ? esc_html( ffnc_format_review_date( $meta['last_reviewed'], 'M j, Y' ) ) : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_dataset_status', 'ffnc_shortcode_dataset_status' );

/**
 * "Keep exploring" links under a calculator: methodology, compare, guides.
 *
 * @param string $slug Brand.
 * @return string
 */
function ffnc_render_calc_explore_links( $slug ) {
	$links   = array();
	$compare = ffnc_compare_url();
	$blog_id = (int) get_option( 'page_for_posts' );
	$brand   = ffnc_get_brand( $slug );
	if ( $compare ) {
		$links[] = '<a href="' . esc_url( add_query_arg( 'a', $slug, $compare ) ) . '" rel="nofollow">' . esc_html( sprintf( /* translators: %s brand */ __( 'Compare %s items with other restaurants', 'ffnc' ), $brand ? $brand['name'] : '' ) ) . '</a>';
	}
	if ( $blog_id ) {
		$links[] = '<a href="' . esc_url( get_permalink( $blog_id ) ) . '">' . esc_html__( 'Fast food nutrition guides', 'ffnc' ) . '</a>';
	}
	if ( ffnc_page_url( 'calculators' ) ) {
		$links[] = '<a href="' . esc_url( ffnc_page_url( 'calculators' ) ) . '">' . esc_html__( 'All restaurant calculators', 'ffnc' ) . '</a>';
	}
	if ( ! $links ) {
		return '';
	}
	return '<nav class="ffnc-explore-links" aria-label="' . esc_attr__( 'Keep exploring', 'ffnc' ) . '"><ul><li>' . implode( '</li><li>', $links ) . '</li></ul></nav>';
}

/**
 * Enrich calculator pages after shortcodes run (priority 19, just before
 * ffnc_append_page_extras at 20): add the crawlable table if the page
 * doesn't already have one, then the data-source box.
 */
function ffnc_enrich_calculator_page( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( '1' !== get_post_meta( get_the_ID(), '_ffnc_is_calculator_page', true ) ) {
		return $content;
	}
	$slug = ffnc_get_page_brand_slug();
	if ( ! $slug || ! ffnc_get_brand_data( $slug ) ) {
		return $content;
	}
	$brand = ffnc_get_brand( $slug );
	$extra = '';
	if ( empty( $GLOBALS['ffnc_rendered_table'][ $slug ] ) ) {
		$extra .= '<h2 id="nutrition-table">' . esc_html( sprintf( /* translators: %s brand */ __( '%s nutrition facts table', 'ffnc' ), $brand ? $brand['name'] : '' ) ) . '</h2>';
		$extra .= ffnc_shortcode_nutrition_table( array( 'brand' => $slug ) );
	}
	if ( empty( $GLOBALS['ffnc_rendered_source'][ $slug ] ) ) {
		$extra .= ffnc_render_data_source_box( $slug );
	}
	$extra .= ffnc_render_calc_explore_links( $slug );
	return $content . $extra;
}
add_filter( 'the_content', 'ffnc_enrich_calculator_page', 19 );
