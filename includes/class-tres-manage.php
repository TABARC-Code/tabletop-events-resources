<?php
/**
 * Self-service listing management via a magic link — same pattern as
 * the LFG board and Carpool plugins. An organiser can edit a lending
 * item's details or take it down, with no account needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TRES_Manage {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function add_rewrite_rules() {
		add_rewrite_rule( '^manage-resource/?$', 'index.php?tres_manage_page=1', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'tres_manage_page';
		return $vars;
	}

	public function template_include( $template ) {
		if ( ! get_query_var( 'tres_manage_page' ) ) {
			return $template;
		}
		return TRES_PLUGIN_DIR . 'templates/manage-resource.php';
	}

	public function get_manage_url( $post_id ) {
		$token = get_post_meta( $post_id, '_tres_manage_token', true );
		return add_query_arg(
			array( 'post' => $post_id, 'token' => $token ),
			home_url( '/manage-resource/' )
		);
	}

	public function send_confirmation_email( $post_id ) {
		$to = get_post_meta( $post_id, '_tres_organiser_email', true );
		if ( ! is_email( $to ) ) {
			return;
		}
		wp_mail(
			$to,
			sprintf( '[%s] Your resource listing is up', get_bloginfo( 'name' ) ),
			sprintf(
				"Thanks — your listing is now awaiting review.\n\nNeed to change something, or lent everything out for good? Use this link any time to edit it or take it down:\n%s\n\nNo account or password needed — keep this link safe, it's what lets you manage this listing.\n",
				$this->get_manage_url( $post_id )
			)
		);
	}

	public function register_routes() {
		register_rest_route(
			'tres/v1',
			'/manage/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_managed_resource' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tres/v1',
			'/manage/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_managed_resource' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tres/v1',
			'/manage/(?P<id>\d+)/remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'remove_resource' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private function verify_token( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$token   = (string) $request->get_param( 'token' );
		$post    = get_post( $post_id );

		if ( ! $post || TRES_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'tres_not_found', __( 'Listing not found.', 'tabletop-events-resources' ), array( 'status' => 404 ) );
		}
		$real_token = get_post_meta( $post_id, '_tres_manage_token', true );
		if ( ! $token || ! $real_token || ! hash_equals( $real_token, $token ) ) {
			return new WP_Error( 'tres_invalid_token', __( 'Invalid or expired management link.', 'tabletop-events-resources' ), array( 'status' => 403 ) );
		}
		return $post;
	}

	public function get_managed_resource( WP_REST_Request $request ) {
		$post = $this->verify_token( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return rest_ensure_response(
			array(
				'id'       => $post->ID,
				'status'   => $post->post_status,
				'title'    => get_the_title( $post ),
				'category' => get_post_meta( $post->ID, '_tres_category', true ),
				'quantity' => (int) get_post_meta( $post->ID, '_tres_quantity', true ),
				'notes'    => get_post_meta( $post->ID, '_tres_notes', true ),
			)
		);
	}

	public function update_managed_resource( WP_REST_Request $request ) {
		$post = $this->verify_token( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$params = $request->get_json_params() ?: $request->get_body_params();

		if ( isset( $params['title'] ) && trim( $params['title'] ) ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_title' => sanitize_text_field( $params['title'] ) ) );
		}
		if ( isset( $params['quantity'] ) ) {
			update_post_meta( $post->ID, '_tres_quantity', max( 1, (int) $params['quantity'] ) );
		}
		if ( isset( $params['notes'] ) ) {
			update_post_meta( $post->ID, '_tres_notes', sanitize_textarea_field( $params['notes'] ) );
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Saved.', 'tabletop-events-resources' ) ) );
	}

	public function remove_resource( WP_REST_Request $request ) {
		$post = $this->verify_token( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		wp_trash_post( $post->ID );
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Taken down — thanks for keeping the list current!', 'tabletop-events-resources' ) ) );
	}
}
