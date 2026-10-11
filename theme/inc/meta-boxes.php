<?php
/**
 * Lightweight native meta boxes (no third-party plugin dependency) for:
 *  - SEO title / meta description overrides
 *  - Table of contents toggle
 *  - Related calculators (comma separated brand slugs) for internal linking
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ffnc_add_meta_boxes() {
	add_meta_box( 'ffnc_seo', __( 'SEO & Snippet', 'ffnc' ), 'ffnc_render_seo_box', array( 'page', 'post' ), 'normal', 'high' );
	add_meta_box( 'ffnc_page_options', __( 'Page Options', 'ffnc' ), 'ffnc_render_page_options_box', array( 'page', 'post' ), 'side', 'default' );
}
add_action( 'add_meta_boxes', 'ffnc_add_meta_boxes' );

function ffnc_render_seo_box( $post ) {
	wp_nonce_field( 'ffnc_save_meta', 'ffnc_meta_nonce' );
	$seo_title = get_post_meta( $post->ID, '_ffnc_seo_title', true );
	$meta_desc = get_post_meta( $post->ID, '_ffnc_meta_description', true );
	?>
	<p>
		<label for="ffnc_seo_title"><strong><?php esc_html_e( 'SEO Title (leave blank to use the page title)', 'ffnc' ); ?></strong></label><br>
		<input type="text" id="ffnc_seo_title" name="ffnc_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" style="width:100%;" maxlength="70">
	</p>
	<p>
		<label for="ffnc_meta_description"><strong><?php esc_html_e( 'Meta Description (aim for 140-160 characters)', 'ffnc' ); ?></strong></label><br>
		<textarea id="ffnc_meta_description" name="ffnc_meta_description" style="width:100%;" rows="3" maxlength="320"><?php echo esc_textarea( $meta_desc ); ?></textarea>
	</p>
	<?php
}

function ffnc_render_page_options_box( $post ) {
	$show_toc  = get_post_meta( $post->ID, '_ffnc_show_toc', true );
	$related   = get_post_meta( $post->ID, '_ffnc_related_brands', true );
	$is_calc   = get_post_meta( $post->ID, '_ffnc_is_calculator_page', true );
	$noindex   = get_post_meta( $post->ID, '_ffnc_noindex', true );
	?>
	<p>
		<label>
			<input type="checkbox" name="ffnc_show_toc" value="1" <?php checked( $show_toc, '1' ); ?>>
			<?php esc_html_e( 'Show auto-generated table of contents', 'ffnc' ); ?>
		</label>
	</p>
	<p>
		<label>
			<input type="checkbox" name="ffnc_is_calculator_page" value="1" <?php checked( $is_calc, '1' ); ?>>
			<?php esc_html_e( 'This is a calculator page (adds WebApplication schema, data-source box and nutrition table)', 'ffnc' ); ?>
		</label>
	</p>
	<p>
		<label>
			<input type="checkbox" name="ffnc_noindex" value="1" <?php checked( $noindex, '1' ); ?>>
			<?php esc_html_e( 'Hide from search engines (noindex + remove from sitemap)', 'ffnc' ); ?>
		</label>
	</p>
	<p>
		<label for="ffnc_related_brands"><strong><?php esc_html_e( 'Related brand slugs (comma separated, e.g. kfc,subway)', 'ffnc' ); ?></strong></label><br>
		<input type="text" id="ffnc_related_brands" name="ffnc_related_brands" value="<?php echo esc_attr( $related ); ?>" style="width:100%;">
		<span class="description"><?php esc_html_e( 'Rendered as an internal-linking "Related Calculators" block near the bottom of the page. Leave empty on calculator pages to use menu-similar brands automatically.', 'ffnc' ); ?></span>
	</p>
	<?php
}

function ffnc_save_meta_boxes( $post_id ) {
	if ( ! isset( $_POST['ffnc_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ffnc_meta_nonce'], 'ffnc_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['ffnc_seo_title'] ) ) {
		update_post_meta( $post_id, '_ffnc_seo_title', sanitize_text_field( wp_unslash( $_POST['ffnc_seo_title'] ) ) );
	}
	if ( isset( $_POST['ffnc_meta_description'] ) ) {
		update_post_meta( $post_id, '_ffnc_meta_description', sanitize_textarea_field( wp_unslash( $_POST['ffnc_meta_description'] ) ) );
	}
	update_post_meta( $post_id, '_ffnc_show_toc', isset( $_POST['ffnc_show_toc'] ) ? '1' : '' );
	update_post_meta( $post_id, '_ffnc_is_calculator_page', isset( $_POST['ffnc_is_calculator_page'] ) ? '1' : '' );
	update_post_meta( $post_id, '_ffnc_noindex', isset( $_POST['ffnc_noindex'] ) ? '1' : '' );
	if ( isset( $_POST['ffnc_related_brands'] ) ) {
		update_post_meta( $post_id, '_ffnc_related_brands', sanitize_text_field( wp_unslash( $_POST['ffnc_related_brands'] ) ) );
	}
}
add_action( 'save_post', 'ffnc_save_meta_boxes' );
