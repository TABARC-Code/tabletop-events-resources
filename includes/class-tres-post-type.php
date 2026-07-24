<?php
/**
 * The tres_resource CPT: a single lendable item (a terrain set, a
 * folding table, a box of spare dice/minis) belonging to one
 * organiser. Not public — same treatment as tevr_review/tcar_listing —
 * only ever seen through this plugin's own REST endpoint and
 * shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TRES_Post_Type {

	private static $instance = null;

	const CATEGORIES = array( 'terrain', 'tables', 'dice_minis', 'other' );

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . TRES_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
	}

	public function register_post_type() {
		register_post_type(
			TRES_POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Resource Listings', 'tabletop-events-resources' ),
					'singular_name' => __( 'Resource Listing', 'tabletop-events-resources' ),
					'add_new_item'  => __( 'Add New Listing', 'tabletop-events-resources' ),
					'edit_item'     => __( 'Edit Listing', 'tabletop-events-resources' ),
					'all_items'     => __( 'Resources', 'tabletop-events-resources' ),
					'search_items'  => __( 'Search Listings', 'tabletop-events-resources' ),
					'not_found'     => __( 'No resource listings found.', 'tabletop-events-resources' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . TEC_POST_TYPE,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
				'show_in_rest'        => false, // Vetted through /tres/v1/, not the default REST controller.
			)
		);
	}

	public function add_meta_box() {
		add_meta_box(
			'tres_resource_meta',
			__( 'Listing Details', 'tabletop-events-resources' ),
			array( $this, 'render_meta_box' ),
			TRES_POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'tres_save_resource_meta', 'tres_resource_meta_nonce' );

		$anchor_id = (int) get_post_meta( $post->ID, '_tres_anchor_event_id', true );
		$org_email = get_post_meta( $post->ID, '_tres_organiser_email', true );
		$category  = get_post_meta( $post->ID, '_tres_category', true ) ?: 'other';
		$quantity  = get_post_meta( $post->ID, '_tres_quantity', true ) ?: 1;
		$notes     = get_post_meta( $post->ID, '_tres_notes', true );
		?>
		<style>
			.tres-meta-row { display: flex; gap: 20px; margin-bottom: 14px; flex-wrap: wrap; }
			.tres-meta-field { flex: 1; min-width: 220px; }
			.tres-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; }
			.tres-meta-field input, .tres-meta-field select, .tres-meta-field textarea { width: 100%; }
			.tres-meta-box h3 { margin: 0 0 10px; padding-top: 14px; border-top: 1px solid #dcdcde; }
			.tres-meta-box h3:first-child { padding-top: 0; border-top: none; }
		</style>
		<div class="tres-meta-box">
			<h3><?php esc_html_e( 'Listing', 'tabletop-events-resources' ); ?></h3>
			<div class="tres-meta-row">
				<div class="tres-meta-field">
					<label for="tres_category"><?php esc_html_e( 'Category', 'tabletop-events-resources' ); ?></label>
					<select name="tres_category" id="tres_category">
						<option value="terrain" <?php selected( $category, 'terrain' ); ?>><?php esc_html_e( 'Terrain', 'tabletop-events-resources' ); ?></option>
						<option value="tables" <?php selected( $category, 'tables' ); ?>><?php esc_html_e( 'Tables', 'tabletop-events-resources' ); ?></option>
						<option value="dice_minis" <?php selected( $category, 'dice_minis' ); ?>><?php esc_html_e( 'Dice / Minis', 'tabletop-events-resources' ); ?></option>
						<option value="other" <?php selected( $category, 'other' ); ?>><?php esc_html_e( 'Other', 'tabletop-events-resources' ); ?></option>
					</select>
				</div>
				<div class="tres-meta-field">
					<label for="tres_quantity"><?php esc_html_e( 'Quantity available', 'tabletop-events-resources' ); ?></label>
					<input type="number" min="1" name="tres_quantity" id="tres_quantity" value="<?php echo esc_attr( $quantity ); ?>">
				</div>
			</div>
			<div class="tres-meta-row">
				<div class="tres-meta-field" style="flex-basis:100%;">
					<label for="tres_notes"><?php esc_html_e( 'Notes (condition, collection arrangements, etc.)', 'tabletop-events-resources' ); ?></label>
					<textarea name="tres_notes" id="tres_notes" rows="3"><?php echo esc_textarea( $notes ); ?></textarea>
				</div>
			</div>

			<h3><?php esc_html_e( 'Owner (private)', 'tabletop-events-resources' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: 1: organiser email, 2: link to the anchor event */
					esc_html__( 'Linked organiser email: %1$s — anchored on event: %2$s', 'tabletop-events-resources' ),
					'<strong>' . esc_html( $org_email ?: __( '(not set)', 'tabletop-events-resources' ) ) . '</strong>',
					$anchor_id ? '<a href="' . esc_url( get_edit_post_link( $anchor_id ) ) . '">' . esc_html( get_the_title( $anchor_id ) ) . '</a>' : __( '(none)', 'tabletop-events-resources' )
				);
				?>
			</p>
		</div>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['tres_resource_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tres_resource_meta_nonce'] ) ), 'tres_save_resource_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['tres_category'] ) ) {
			$category = sanitize_key( wp_unslash( $_POST['tres_category'] ) );
			update_post_meta( $post_id, '_tres_category', in_array( $category, self::CATEGORIES, true ) ? $category : 'other' );
		}
		if ( isset( $_POST['tres_quantity'] ) ) {
			update_post_meta( $post_id, '_tres_quantity', max( 1, (int) $_POST['tres_quantity'] ) );
		}
		if ( isset( $_POST['tres_notes'] ) ) {
			update_post_meta( $post_id, '_tres_notes', sanitize_textarea_field( wp_unslash( $_POST['tres_notes'] ) ) );
		}
	}
}
