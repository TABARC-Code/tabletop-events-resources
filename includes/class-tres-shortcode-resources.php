<?php
/**
 * [tabletop_organiser_resources organiser="123"] — an organiser's
 * lending list plus a "list an item" form, anchored on any one of
 * their own event IDs (same pattern as [tabletop_organiser_events]
 * and the Reviews plugin's organiser widget). Reuses the core
 * plugin's submission-form.css wholesale.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TRES_Shortcode_Resources {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_shortcode( 'tabletop_organiser_resources', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'organiser' => 0 ), $atts, 'tabletop_organiser_resources' );
		$anchor_id = (int) $atts['organiser'];
		if ( ! $anchor_id ) {
			return '';
		}

		wp_enqueue_style( 'tec-submit', TEC_PLUGIN_URL . 'assets/css/submission-form.css', array(), TEC_VERSION );
		wp_enqueue_style( 'tres-resources', TRES_PLUGIN_URL . 'assets/css/resources.css', array(), TRES_VERSION );
		wp_enqueue_script( 'tres-resources', TRES_PLUGIN_URL . 'assets/js/organiser-resources.js', array(), TRES_VERSION, true );

		// wp_head()'s print pass has usually already run by the time a
		// shortcode renders inside the_content() — print explicitly or
		// these never make it onto the page.
		wp_print_styles( 'tec-submit' );
		wp_print_styles( 'tres-resources' );

		wp_localize_script(
			'tres-resources',
			'TRES_RESOURCES',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'tres/v1' ) ),
				'anchorId'  => $anchor_id,
			)
		);

		return '<div class="tres-resources-root" data-tres-resources></div>';
	}
}
