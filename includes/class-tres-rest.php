<?php
/**
 * /wp-json/tres/v1/* — one organiser's lending list, submitting a new
 * item, no caching layer, same reasoning as the rest of this family:
 * low-churn, low-volume traffic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TRES_Rest {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'tres/v1',
			'/organiser/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_organiser_resources' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tres/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'tres/v1',
			'/contact/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'contact_owner' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Anchored on any one of the organiser's own event IDs, exactly the
	 * pattern [tabletop_organiser_events] and the Reviews plugin's
	 * organiser widget already use.
	 */
	public function get_organiser_resources( WP_REST_Request $request ) {
		$anchor_id = (int) $request->get_param( 'id' );
		$anchor    = get_post( $anchor_id );
		if ( ! $anchor || TEC_POST_TYPE !== $anchor->post_type ) {
			return new WP_Error( 'tres_no_anchor', __( 'That event could not be found.', 'tabletop-events-resources' ), array( 'status' => 404 ) );
		}
		$organiser_email = strtolower( trim( get_post_meta( $anchor_id, '_tec_organiser_email', true ) ) );
		if ( ! $organiser_email ) {
			return rest_ensure_response( array() );
		}

		$posts = get_posts(
			array(
				'post_type'      => TRES_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array( 'key' => '_tres_organiser_email', 'value' => $organiser_email, 'compare' => '=' ),
				),
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'id'       => $post->ID,
				'category' => get_post_meta( $post->ID, '_tres_category', true ),
				'title'    => get_the_title( $post ),
				'quantity' => (int) get_post_meta( $post->ID, '_tres_quantity', true ),
				'notes'    => get_post_meta( $post->ID, '_tres_notes', true ),
			);
		}

		return rest_ensure_response( $out );
	}

	public function submit( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: $request->get_body_params();

		if ( ! empty( $params['website'] ) ) {
			return rest_ensure_response( array( 'success' => true ) ); // Honeypot.
		}

		$anchor_id = (int) ( $params['organiser'] ?? 0 );
		$anchor    = get_post( $anchor_id );
		if ( ! $anchor || TEC_POST_TYPE !== $anchor->post_type ) {
			return new WP_Error( 'tres_no_anchor', __( 'That organiser could not be found.', 'tabletop-events-resources' ), array( 'status' => 404 ) );
		}

		$anchor_email = strtolower( trim( get_post_meta( $anchor_id, '_tec_organiser_email', true ) ) );
		$email        = sanitize_email( $params['email'] ?? '' );
		if ( ! $anchor_email || strtolower( trim( $email ) ) !== $anchor_email ) {
			return new WP_Error( 'tres_not_organiser', __( "That email doesn't match the organiser on record for this listing page, so we can't accept a submission from it.", 'tabletop-events-resources' ), array( 'status' => 403 ) );
		}

		$title    = sanitize_text_field( $params['title'] ?? '' );
		$category = sanitize_key( $params['category'] ?? '' );
		$quantity = (int) ( $params['quantity'] ?? 0 );
		$notes    = sanitize_textarea_field( $params['notes'] ?? '' );

		if ( ! $title ) {
			return new WP_Error( 'tres_invalid', __( 'Please give the item a name.', 'tabletop-events-resources' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( $category, TRES_Post_Type::CATEGORIES, true ) ) {
			return new WP_Error( 'tres_invalid', __( 'Please choose a category.', 'tabletop-events-resources' ), array( 'status' => 400 ) );
		}
		if ( $quantity < 1 ) {
			return new WP_Error( 'tres_invalid', __( 'Please say how many are available.', 'tabletop-events-resources' ), array( 'status' => 400 ) );
		}

		$resource_id = wp_insert_post(
			array(
				'post_type'   => TRES_POST_TYPE,
				'post_status' => 'pending',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $resource_id ) ) {
			return $resource_id;
		}

		update_post_meta( $resource_id, '_tres_anchor_event_id', $anchor_id );
		update_post_meta( $resource_id, '_tres_organiser_email', $anchor_email );
		update_post_meta( $resource_id, '_tres_category', $category );
		update_post_meta( $resource_id, '_tres_quantity', $quantity );
		update_post_meta( $resource_id, '_tres_notes', $notes );
		update_post_meta( $resource_id, '_tres_manage_token', wp_generate_password( 40, false, false ) );

		$this->notify_admin( $resource_id, $title );
		if ( class_exists( 'TRES_Manage' ) ) {
			TRES_Manage::instance()->send_confirmation_email( $resource_id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( "Thanks — we'll review your listing shortly. Check your email for a link to edit it or take it down later.", 'tabletop-events-resources' ),
			)
		);
	}

	/**
	 * "Ask about this" relay — same reasoning as the Carpool and LFG
	 * plugins' own contact relays: a visitor never sees the organiser's
	 * real address, only the organiser ever does, and the Reply-To
	 * header means they can just hit reply. Without this, there was no
	 * way at all to actually ask about borrowing something — a real
	 * gap, since a public lending list is only useful if someone can
	 * follow up on it.
	 */
	public function contact_owner( WP_REST_Request $request ) {
		$resource_id = (int) $request->get_param( 'id' );
		$resource    = get_post( $resource_id );
		if ( ! $resource || TRES_POST_TYPE !== $resource->post_type || 'publish' !== $resource->post_status ) {
			return new WP_Error( 'tres_not_found', __( 'Listing not found.', 'tabletop-events-resources' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params() ?: $request->get_body_params();
		if ( ! empty( $params['website'] ) ) {
			return rest_ensure_response( array( 'success' => true ) ); // Honeypot.
		}

		$from_name  = sanitize_text_field( $params['name'] ?? '' );
		$from_email = sanitize_email( $params['email'] ?? '' );
		$message    = sanitize_textarea_field( $params['message'] ?? '' );
		if ( ! $from_name || ! is_email( $from_email ) || ! $message ) {
			return new WP_Error( 'tres_invalid', __( 'Please fill in your name, email, and a message.', 'tabletop-events-resources' ), array( 'status' => 400 ) );
		}

		$to = get_post_meta( $resource_id, '_tres_organiser_email', true );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'tres_no_contact', __( "This listing's owner can't be reached right now.", 'tabletop-events-resources' ), array( 'status' => 404 ) );
		}

		wp_mail(
			$to,
			sprintf( '[%s] Someone asked about your resource listing', get_bloginfo( 'name' ) ),
			sprintf(
				"%s (%s) asked about \"%s\":\n\n%s\n\nJust hit reply to get back to them.\n",
				$from_name,
				$from_email,
				get_the_title( $resource ),
				$message
			),
			array( 'Reply-To: ' . $from_name . ' <' . $from_email . '>' )
		);

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Message sent!', 'tabletop-events-resources' ) ) );
	}

	private function notify_admin( $resource_id, $title ) {
		$to = get_option( 'admin_email' );
		if ( ! $to ) {
			return;
		}
		wp_mail(
			$to,
			sprintf( '[%s] New resource listing: %s', get_bloginfo( 'name' ), $title ),
			sprintf(
				"A new resource listing is awaiting review:\n\n%s\n\nReview it here:\n%s\n",
				$title,
				admin_url( 'post.php?post=' . $resource_id . '&action=edit' )
			)
		);
	}
}
