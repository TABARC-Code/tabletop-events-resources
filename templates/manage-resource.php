<?php
/**
 * Self-service "manage your resource listing" page, reached via the
 * magic link emailed on submission. Renders at
 * /manage-resource/?post=ID&token=...
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

wp_enqueue_style( 'tec-submit', TEC_PLUGIN_URL . 'assets/css/submission-form.css', array(), TEC_VERSION );
wp_enqueue_script( 'tres-manage', TRES_PLUGIN_URL . 'assets/js/resources-manage.js', array(), TRES_VERSION, true );

// get_header() above has already fired wp_head()'s print pass, so this
// style would otherwise never reach the page — print it explicitly.
wp_print_styles( 'tec-submit' );
wp_localize_script(
	'tres-manage',
	'TRES_MANAGE',
	array(
		'restUrl' => esc_url_raw( rest_url( 'tres/v1' ) ),
		'postId'  => $post_id,
		'token'   => $token,
	)
);
?>
<div style="max-width:640px;margin:0 auto;padding:24px 16px;">
	<h1><?php esc_html_e( 'Manage Your Resource Listing', 'tabletop-events-resources' ); ?></h1>
	<div class="tec-submit-root" data-tres-manage></div>
</div>
<?php
get_footer();
