<?php
/**
 * Plugin Name: CityHub Data Manager
 * Description: Manage CityHub events, communities, services, real estate, market, city hall, marketplace, promotions, and sponsors. Provides public read-only JSON APIs for custom frontends.
 * Version: 2.1.1
 * Author: CityHub
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: cityhub-data-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CityHub_Data_Manager {
	const VERSION      = '2.1.1';
	const API_NAMESPACE = 'cityhub/v1';
	const NONCE_ACTION = 'chm_save_listing';
	const NONCE_NAME   = 'chm_listing_nonce';

	/** @var CityHub_Data_Manager|null */
	private static $instance = null;

	/** @var array<string,array<string,mixed>> */
	private $types = array();

	/**
	 * Start the plugin.
	 *
	 * @return CityHub_Data_Manager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->types = $this->get_type_config();

		add_action( 'init', array( $this, 'register_content_types' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_listing' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'edit_form_after_title', array( $this, 'render_description_label' ) );

		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'render_single_listing_content' ), 20 );

		foreach ( array_keys( $this->types ) as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'admin_columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_admin_column' ), 10, 2 );
		}
	}

	/**
	 * Content types and their backward-compatible identifiers.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_type_config() {
		return array(
			'chm_event'       => array(
				'singular'    => 'Event',
				'plural'      => 'Events',
				'api'         => 'events',
				'rewrite'     => 'cityhub-events',
				'icon'        => 'dashicons-calendar-alt',
				'cta_default' => 'Book an Event',
				'taxonomy'    => '',
			),
			'chm_community'   => array(
				'singular'    => 'Community',
				'plural'      => 'Community',
				'api'         => 'community',
				'rewrite'     => 'cityhub-community',
				'icon'        => 'dashicons-groups',
				'cta_default' => 'Read more',
				'taxonomy'    => '',
			),
			'chm_service'     => array(
				'singular'      => 'Service',
				'plural'        => 'Services',
				'api'           => 'services',
				'rewrite'       => 'cityhub-services',
				'icon'          => 'dashicons-admin-tools',
				'cta_default'   => 'Read more',
				'taxonomy'      => 'chm_service_cat',
				'taxonomy_name' => 'Service Categories',
			),
			'chm_realestate'  => array(
				'singular'      => 'Real Estate Listing',
				'plural'        => 'Real Estate',
				'api'           => 'real-estate',
				'rewrite'       => 'cityhub-real-estate',
				'icon'          => 'dashicons-building',
				'cta_default'   => 'Read more',
				'taxonomy'      => 'chm_property_cat',
				'taxonomy_name' => 'Property Categories',
			),
			'chm_market'      => array(
				'singular'      => 'Market Listing',
				'plural'        => 'Market',
				'api'           => 'market',
				'rewrite'       => 'cityhub-market',
				'icon'          => 'dashicons-store',
				'cta_default'   => 'Read more',
				'taxonomy'      => 'chm_market_cat',
				'taxonomy_name' => 'Market Categories',
			),
			'chm_cityhall'    => array(
				'singular'      => 'City Hall Listing',
				'plural'        => 'City Hall',
				'api'           => 'city-hall',
				'rewrite'       => 'cityhub-city-hall',
				'icon'          => 'dashicons-bank',
				'cta_default'   => 'Read more',
				'taxonomy'      => 'chm_cityhall_cat',
				'taxonomy_name' => 'City Hall Categories',
			),
			'chm_marketplace' => array(
				'singular'      => 'Marketplace Listing',
				'plural'        => 'Marketplace',
				'api'           => 'marketplace',
				'rewrite'       => 'cityhub-marketplace',
				'icon'          => 'dashicons-cart',
				'cta_default'   => 'Read more',
				'taxonomy'      => 'chm_marketplace_cat',
				'taxonomy_name' => 'Marketplace Categories',
			),
			'chm_promotion'   => array(
				'singular'      => 'Promotion / Deal',
				'plural'        => 'Promotions / Deals',
				'api'           => 'promotions',
				'rewrite'       => 'cityhub-promotions',
				'icon'          => 'dashicons-tickets-alt',
				'cta_default'   => 'Read more',
				'taxonomy'      => 'chm_promotion_cat',
				'taxonomy_name' => 'Promotion Categories',
			),
			'chm_sponsor'     => array(
				'singular'      => 'Sponsor',
				'plural'        => 'Sponsors',
				'api'           => 'sponsors',
				'rewrite'       => 'cityhub-sponsors',
				'icon'          => 'dashicons-awards',
				'cta_default'   => 'Read more',
				'taxonomy'      => 'chm_sponsor_cat',
				'taxonomy_name' => 'Sponsor Categories',
			),
		);
	}

	/** Register custom post types and taxonomies. */
	public function register_content_types() {
		foreach ( $this->types as $post_type => $config ) {
			$singular = $config['singular'];
			$plural   = $config['plural'];

			register_post_type(
				$post_type,
				array(
					'labels'              => array(
						'name'                  => $plural,
						'singular_name'         => $singular,
						'menu_name'             => $plural,
						'add_new'               => 'Add New',
						'add_new_item'          => "Add {$singular}",
						'edit_item'             => "Edit {$singular}",
						'new_item'              => "New {$singular}",
						'view_item'             => "View {$singular}",
						'search_items'          => "Search {$plural}",
						'not_found'             => "No {$plural} found",
						'not_found_in_trash'    => "No {$plural} found in Trash",
						'featured_image'        => 'Thumbnail',
						'set_featured_image'    => 'Set thumbnail',
						'remove_featured_image' => 'Remove thumbnail',
					),
					'public'              => true,
					'publicly_queryable'  => true,
					'show_ui'             => true,
					// Menus are added explicitly below so every type gets a clear Add link.
					'show_in_menu'        => false,
					'show_in_rest'        => true,
					'rest_base'           => $post_type,
					'has_archive'         => false,
					'rewrite'             => array( 'slug' => $config['rewrite'], 'with_front' => false ),
					'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions', 'author' ),
					'menu_icon'           => $config['icon'],
					'capability_type'     => 'post',
					'map_meta_cap'        => true,
					'delete_with_user'    => false,
					'exclude_from_search' => false,
				)
			);

			if ( ! empty( $config['taxonomy'] ) ) {
				register_taxonomy(
					$config['taxonomy'],
					array( $post_type ),
					array(
						'labels'            => array(
							'name'          => $config['taxonomy_name'],
							'singular_name' => 'Category',
							'search_items'  => 'Search Categories',
							'all_items'     => 'All Categories',
							'edit_item'     => 'Edit Category',
							'update_item'   => 'Update Category',
							'add_new_item'  => 'Add New Category',
							'new_item_name' => 'New Category Name',
						),
						'public'            => true,
						'hierarchical'      => true,
						'show_ui'           => true,
						'show_admin_column' => true,
						'show_in_rest'      => true,
						'rewrite'           => false,
					)
				);
			}
		}
	}

	/** Build the CityHub admin navigation. */
	public function register_admin_menu() {
		add_menu_page(
			'CityHub Data Manager',
			'CityHub',
			'edit_posts',
			'cityhub-manager',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-multisite',
			25
		);

		foreach ( $this->types as $post_type => $config ) {
			add_submenu_page(
				'cityhub-manager',
				$config['plural'],
				$config['plural'],
				'edit_posts',
				"edit.php?post_type={$post_type}"
			);

			add_submenu_page(
				'cityhub-manager',
				"Add {$config['singular']}",
				"↳ Add {$config['singular']}",
				'edit_posts',
				"post-new.php?post_type={$post_type}"
			);
		}
	}

	/** Plugin dashboard with API links. */
	public function render_dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		?>
		<div class="wrap chm-dashboard">
			<h1>CityHub Data Manager</h1>
			<p>Add and manage structured content here. Your frontend can request the public JSON endpoints below and render any design you want.</p>
			<div class="chm-api-grid">
				<?php foreach ( $this->types as $post_type => $config ) : ?>
					<section class="chm-api-card">
						<h2><?php echo esc_html( $config['plural'] ); ?></h2>
						<p>
							<a class="button button-primary" href="<?php echo esc_url( admin_url( "post-new.php?post_type={$post_type}" ) ); ?>">
								Add <?php echo esc_html( $config['singular'] ); ?>
							</a>
							<a class="button" href="<?php echo esc_url( admin_url( "edit.php?post_type={$post_type}" ) ); ?>">Manage</a>
						</p>
						<code><?php echo esc_html( rest_url( self::API_NAMESPACE . '/' . $config['api'] ) ); ?></code>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/** Show classic forms rather than a blank-looking block editor page. */
	public function disable_block_editor( $use_block_editor, $post_type ) {
		return isset( $this->types[ $post_type ] ) ? false : $use_block_editor;
	}

	/** Customize the title field prompt. */
	public function title_placeholder( $placeholder, $post ) {
		if ( $post && isset( $this->types[ $post->post_type ] ) ) {
			return 'Enter ' . strtolower( $this->types[ $post->post_type ]['singular'] ) . ' title';
		}

		return $placeholder;
	}

	/** Label the WordPress editor as the listing description. */
	public function render_description_label( $post ) {
		if ( isset( $this->types[ $post->post_type ] ) ) {
			echo '<h2 class="chm-description-heading">Description</h2>';
		}
	}

	/** Register event, CTA, and gallery forms. */
	public function register_meta_boxes() {
		foreach ( $this->types as $post_type => $config ) {
			add_meta_box(
				'chm_listing_details',
				'chm_event' === $post_type ? 'Event Details & Call to Action' : 'Call to Action',
				array( $this, 'render_details_meta_box' ),
				$post_type,
				'normal',
				'high'
			);

			add_meta_box(
				'chm_listing_gallery',
				'Other Images',
				array( $this, 'render_gallery_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/** Render structured fields. */
	public function render_details_meta_box( $post ) {
		$config = $this->types[ $post->post_type ];
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		if ( 'chm_event' === $post->post_type ) {
			$event_date = $this->get_compatible_meta( $post->ID, 'event_date' );
			$start_time = $this->get_compatible_meta( $post->ID, 'start_time' );
			$end_time   = $this->get_compatible_meta( $post->ID, 'end_time' );
			$location   = $this->get_compatible_meta( $post->ID, 'location' );
			?>
			<div class="chm-form-grid">
				<p class="chm-field">
					<label for="chm_event_date">Event date</label>
					<input type="date" id="chm_event_date" name="chm_event_date" value="<?php echo esc_attr( $event_date ); ?>">
					<small>The API also returns this as “Sat, Aug 3”.</small>
				</p>
				<p class="chm-field">
					<label for="chm_start_time">Start time</label>
					<input type="time" id="chm_start_time" name="chm_start_time" value="<?php echo esc_attr( $start_time ); ?>">
				</p>
				<p class="chm-field">
					<label for="chm_end_time">End time</label>
					<input type="time" id="chm_end_time" name="chm_end_time" value="<?php echo esc_attr( $end_time ); ?>">
				</p>
				<p class="chm-field chm-field-wide">
					<label for="chm_location">Location</label>
					<input type="text" id="chm_location" name="chm_location" value="<?php echo esc_attr( $location ); ?>" placeholder="Village Square">
				</p>
			</div>
			<?php
		}

		$cta_label = $this->get_compatible_meta( $post->ID, 'cta_label' );
		$cta_url   = $this->get_compatible_meta( $post->ID, 'cta_url' );

		if ( '' === $cta_label ) {
			$cta_label = $config['cta_default'];
		}
		?>
		<hr>
		<div class="chm-form-grid">
			<p class="chm-field">
				<label for="chm_cta_label">Button label</label>
				<input type="text" id="chm_cta_label" name="chm_cta_label" value="<?php echo esc_attr( $cta_label ); ?>">
			</p>
			<p class="chm-field chm-field-wide">
				<label for="chm_cta_url">Button link</label>
				<input type="url" id="chm_cta_url" name="chm_cta_url" value="<?php echo esc_attr( $cta_url ); ?>" placeholder="https://example.com/page">
			</p>
		</div>
		<?php
	}

	/** Render media gallery selector. */
	public function render_gallery_meta_box( $post ) {
		$gallery_value = $this->get_compatible_meta( $post->ID, 'gallery' );
		$gallery_ids   = $this->sanitize_gallery_ids( $gallery_value );
		?>
		<p>Select additional images. Use the WordPress <strong>Thumbnail</strong> panel for the main card image.</p>
		<input type="hidden" id="chm_gallery_ids" name="chm_gallery_ids" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>">
		<div id="chm-gallery-preview" class="chm-gallery-preview">
			<?php foreach ( $gallery_ids as $attachment_id ) : ?>
				<?php $thumbnail = wp_get_attachment_image_url( $attachment_id, 'thumbnail' ); ?>
				<?php if ( $thumbnail ) : ?>
					<div class="chm-gallery-item" data-id="<?php echo esc_attr( $attachment_id ); ?>">
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="">
						<button type="button" class="chm-remove-image" aria-label="Remove image">×</button>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<p>
			<button type="button" class="button" id="chm-select-gallery">Select images</button>
			<button type="button" class="button-link-delete" id="chm-clear-gallery">Clear gallery</button>
		</p>
		<?php
	}

	/** Save all structured listing data. */
	public function save_listing( $post_id, $post ) {
		if ( ! isset( $this->types[ $post->post_type ] ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'chm_event' === $post->post_type ) {
			$event_date = isset( $_POST['chm_event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['chm_event_date'] ) ) : '';
			$start_time = isset( $_POST['chm_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['chm_start_time'] ) ) : '';
			$end_time   = isset( $_POST['chm_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['chm_end_time'] ) ) : '';
			$location   = isset( $_POST['chm_location'] ) ? sanitize_text_field( wp_unslash( $_POST['chm_location'] ) ) : '';

			$this->update_meta( $post_id, 'event_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) ? $event_date : '' );
			$this->update_meta( $post_id, 'start_time', preg_match( '/^\d{2}:\d{2}$/', $start_time ) ? $start_time : '' );
			$this->update_meta( $post_id, 'end_time', preg_match( '/^\d{2}:\d{2}$/', $end_time ) ? $end_time : '' );
			$this->update_meta( $post_id, 'location', $location );
		}

		$config    = $this->types[ $post->post_type ];
		$cta_label = isset( $_POST['chm_cta_label'] ) ? sanitize_text_field( wp_unslash( $_POST['chm_cta_label'] ) ) : '';
		$cta_url   = isset( $_POST['chm_cta_url'] ) ? esc_url_raw( wp_unslash( $_POST['chm_cta_url'] ) ) : '';
		$gallery   = isset( $_POST['chm_gallery_ids'] ) ? $this->sanitize_gallery_ids( wp_unslash( $_POST['chm_gallery_ids'] ) ) : array();

		$this->update_meta( $post_id, 'cta_label', '' !== $cta_label ? $cta_label : $config['cta_default'] );
		$this->update_meta( $post_id, 'cta_url', $cta_url );
		$this->update_meta( $post_id, 'gallery', implode( ',', $gallery ) );
	}

	/** Register public, read-only JSON endpoints. */
	public function register_rest_routes() {
		foreach ( $this->types as $post_type => $config ) {
			$resource = $config['api'];

			register_rest_route(
				self::API_NAMESPACE,
				'/' . $resource,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $post_type ) {
						return $this->api_collection( $request, $post_type );
					},
					'permission_callback' => '__return_true',
					'args'                => $this->collection_args(),
				)
			);

			register_rest_route(
				self::API_NAMESPACE,
				'/' . $resource . '/(?P<id>\d+)',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $post_type ) {
						return $this->api_single_by_id( $request, $post_type );
					},
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				)
			);

			register_rest_route(
				self::API_NAMESPACE,
				'/' . $resource . '/slug/(?P<slug>[a-z0-9-]+)',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $post_type ) {
						return $this->api_single_by_slug( $request, $post_type );
					},
					'permission_callback' => '__return_true',
					'args'                => array(
						'slug' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_title',
						),
					),
				)
			);
		}
	}

	/** REST collection arguments. */
	private function collection_args() {
		return array(
			'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
			'per_page' => array(
				'default'           => 12,
				'sanitize_callback' => function ( $value ) {
					return max( 1, min( 100, absint( $value ) ) );
				},
			),
			'search'   => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'category' => array(
				'default'           => 'all',
				'sanitize_callback' => function ( $value ) {
					$value = is_scalar( $value ) ? sanitize_title( (string) $value ) : '';
					return '' !== $value ? $value : 'all';
				},
			),
			'order'    => array(
				'default'           => 'DESC',
				'sanitize_callback' => function ( $value ) {
					return 'ASC' === strtoupper( $value ) ? 'ASC' : 'DESC';
				},
			),
			'orderby'  => array(
				'default'           => 'date',
				'sanitize_callback' => function ( $value ) {
					$allowed = array( 'date', 'modified', 'title', 'menu_order' );
					return in_array( $value, $allowed, true ) ? $value : 'date';
				},
			),
		);
	}

	/** Return a paginated collection. */
	public function api_collection( $request, $post_type ) {
		$config = $this->types[ $post_type ];
		$args   = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) ),
			'paged'               => max( 1, (int) $request->get_param( 'page' ) ),
			's'                   => (string) $request->get_param( 'search' ),
			'order'               => (string) $request->get_param( 'order' ),
			'orderby'             => (string) $request->get_param( 'orderby' ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		$category = (string) $request->get_param( 'category' );
		if ( $category && 'all' !== $category && ! empty( $config['taxonomy'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => $config['taxonomy'],
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$query = new WP_Query( $args );
		$data  = array_map( array( $this, 'prepare_listing' ), $query->posts );

		return new WP_REST_Response(
			array(
				'success'    => true,
				'data'       => $data,
				'pagination' => array(
					'current_page' => max( 1, (int) $request->get_param( 'page' ) ),
					'per_page'    => max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) ),
					'total_items' => (int) $query->found_posts,
					'total_pages' => (int) $query->max_num_pages,
				),
			),
			200
		);
	}

	/** Return a single listing by ID. */
	public function api_single_by_id( $request, $post_type ) {
		$post = get_post( absint( $request['id'] ) );

		if ( ! $post || $post_type !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'chm_not_found', 'Listing not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'success' => true, 'data' => $this->prepare_listing( $post ) ) );
	}

	/** Return a single listing by slug. */
	public function api_single_by_slug( $request, $post_type ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'name'           => sanitize_title( $request['slug'] ),
				'posts_per_page' => 1,
			)
		);

		if ( empty( $posts ) ) {
			return new WP_Error( 'chm_not_found', 'Listing not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'success' => true, 'data' => $this->prepare_listing( $posts[0] ) ) );
	}

	/** Convert a WordPress post into frontend-friendly JSON. */
	public function prepare_listing( $post ) {
		$post   = get_post( $post );
		$config = $this->types[ $post->post_type ];
		$data   = array(
			'id'          => (int) $post->ID,
			'title'       => get_the_title( $post ),
			'slug'        => $post->post_name,
			'url'         => get_permalink( $post ),
			'description' => array(
				'raw'      => $post->post_content,
				'rendered' => apply_filters( 'the_content', $post->post_content ),
			),
			'thumbnail'   => $this->image_data( get_post_thumbnail_id( $post ) ),
			'gallery'     => array(),
			'cta'         => array(
				'label' => $this->get_compatible_meta( $post->ID, 'cta_label' ) ?: $config['cta_default'],
				'url'   => $this->get_compatible_meta( $post->ID, 'cta_url' ),
			),
			'categories'  => array(),
			'status'      => $post->post_status,
			'created_at'  => get_post_time( 'c', true, $post ),
			'updated_at'  => get_post_modified_time( 'c', true, $post ),
		);

		foreach ( $this->sanitize_gallery_ids( $this->get_compatible_meta( $post->ID, 'gallery' ) ) as $attachment_id ) {
			$image = $this->image_data( $attachment_id );
			if ( $image ) {
				$data['gallery'][] = $image;
			}
		}

		if ( ! empty( $config['taxonomy'] ) ) {
			$terms = get_the_terms( $post, $config['taxonomy'] );
			if ( is_array( $terms ) ) {
				$data['categories'] = array_map(
					function ( $term ) {
						return array(
							'id'   => (int) $term->term_id,
							'name' => $term->name,
							'slug' => $term->slug,
						);
					},
					$terms
				);
			}
		}

		if ( 'chm_event' === $post->post_type ) {
			$event_date = $this->get_compatible_meta( $post->ID, 'event_date' );
			$data['event_date']           = $event_date;
			$data['event_date_formatted'] = $event_date ? wp_date( 'D, M j', strtotime( $event_date ) ) : '';
			$data['start_time']            = $this->get_compatible_meta( $post->ID, 'start_time' );
			$data['end_time']              = $this->get_compatible_meta( $post->ID, 'end_time' );
			$data['location']              = $this->get_compatible_meta( $post->ID, 'location' );
		}

		return $data;
	}

	/**
	 * Add all structured CityHub data to the theme's normal single-post content.
	 * This keeps Astra/Elementor headers and footers while making View Post useful.
	 */
	public function render_single_listing_content( $content ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		if ( ! is_singular( array_keys( $this->types ) ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post || ! isset( $this->types[ $post->post_type ] ) ) {
			return $content;
		}

		$config       = $this->types[ $post->post_type ];
		$thumbnail_id = get_post_thumbnail_id( $post );
		$output       = '<div class="chm-single-listing chm-single-' . esc_attr( $post->post_type ) . '">';

		// Do not duplicate an image that the author already inserted into Description.
		if ( $thumbnail_id && false === strpos( $content, 'wp-image-' . $thumbnail_id ) ) {
			$output .= '<figure class="chm-single-hero">';
			$output .= get_the_post_thumbnail(
				$post,
				'large',
				array(
					'class'   => 'chm-single-hero-image',
					'loading' => 'eager',
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$output .= '</figure>';
		}

		if ( 'chm_event' === $post->post_type ) {
			$output .= $this->render_event_facts( $post->ID );
		}

		if ( ! empty( $config['taxonomy'] ) ) {
			$terms = get_the_terms( $post, $config['taxonomy'] );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				$output .= '<div class="chm-single-categories" aria-label="Categories">';
				foreach ( $terms as $term ) {
					$output .= '<span>' . esc_html( $term->name ) . '</span>';
				}
				$output .= '</div>';
			}
		}

		if ( '' !== trim( wp_strip_all_tags( $content ) ) || false !== strpos( $content, '<img' ) ) {
			$output .= '<section class="chm-single-description">';
			$output .= '<h2>Description</h2>';
			$output .= $content; // Already filtered and sanitized by WordPress core/theme.
			$output .= '</section>';
		}

		$output .= $this->render_single_gallery( $post->ID );

		$cta_label = $this->get_compatible_meta( $post->ID, 'cta_label' );
		$cta_url   = $this->get_compatible_meta( $post->ID, 'cta_url' );
		if ( '' === $cta_label ) {
			$cta_label = $config['cta_default'];
		}

		if ( $cta_url ) {
			$output .= '<p class="chm-single-cta"><a href="' . esc_url( $cta_url ) . '">' . esc_html( $cta_label ) . '</a></p>';
		}

		$output .= '</div>';

		return $output;
	}

	/** Render event date, time, and location on single pages. */
	private function render_event_facts( $post_id ) {
		$event_date = $this->get_compatible_meta( $post_id, 'event_date' );
		$start_time = $this->get_compatible_meta( $post_id, 'start_time' );
		$end_time   = $this->get_compatible_meta( $post_id, 'end_time' );
		$location   = $this->get_compatible_meta( $post_id, 'location' );
		$facts      = array();

		if ( $event_date ) {
			$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $event_date, wp_timezone() );
			if ( $date_object ) {
				$facts['Date'] = $date_object->format( 'l, F j, Y' );
			}
		}

		if ( $start_time || $end_time ) {
			$formatted_start = $this->format_admin_time( $start_time );
			$formatted_end   = $this->format_admin_time( $end_time );
			$facts['Time']   = $formatted_start && $formatted_end
				? $formatted_start . ' – ' . $formatted_end
				: ( $formatted_start ?: $formatted_end );
		}

		if ( $location ) {
			$facts['Location'] = $location;
		}

		if ( empty( $facts ) ) {
			return '';
		}

		$output = '<dl class="chm-single-facts">';
		foreach ( $facts as $label => $value ) {
			$output .= '<div class="chm-single-fact">';
			$output .= '<dt>' . esc_html( $label ) . '</dt>';
			$output .= '<dd>' . esc_html( $value ) . '</dd>';
			$output .= '</div>';
		}
		$output .= '</dl>';

		return $output;
	}

	/** Format an HTML time input value for visitors. */
	private function format_admin_time( $time ) {
		if ( ! $time || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return '';
		}

		$time_object = DateTimeImmutable::createFromFormat( '!H:i', $time, wp_timezone() );
		return $time_object ? $time_object->format( get_option( 'time_format', 'g:i a' ) ) : '';
	}

	/** Render all additional Media Library images on a single page. */
	private function render_single_gallery( $post_id ) {
		$gallery_ids = $this->sanitize_gallery_ids( $this->get_compatible_meta( $post_id, 'gallery' ) );
		if ( empty( $gallery_ids ) ) {
			return '';
		}

		$output = '<section class="chm-single-gallery-section">';
		$output .= '<h2>Gallery</h2><div class="chm-single-gallery">';

		foreach ( $gallery_ids as $attachment_id ) {
			$full_url = wp_get_attachment_image_url( $attachment_id, 'full' );
			$image    = wp_get_attachment_image(
				$attachment_id,
				'large',
				false,
				array(
					'class'   => 'chm-single-gallery-image',
					'loading' => 'lazy',
				)
			);

			if ( $full_url && $image ) {
				$output .= '<a href="' . esc_url( $full_url ) . '" class="chm-single-gallery-link">';
				$output .= $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$output .= '</a>';
			}
		}

		$output .= '</div></section>';

		return $output;
	}

	/** Load detail-page styling without changing the site's theme or header/footer. */
	public function enqueue_frontend_assets() {
		if ( ! is_singular( array_keys( $this->types ) ) ) {
			return;
		}

		wp_register_style( 'chm-frontend', false, array(), self::VERSION );
		wp_enqueue_style( 'chm-frontend' );
		wp_add_inline_style( 'chm-frontend', $this->frontend_css() );
	}

	/** Single-listing presentation. */
	private function frontend_css() {
		return '
			.chm-single-listing{--chm-ink:#172434;--chm-link:#274b72;--chm-line:#dce4eb;--chm-soft:#f4f7f9;color:var(--chm-ink)}
			.chm-single-hero{margin:0 0 28px}.chm-single-hero-image{display:block;width:100%;max-height:620px;object-fit:cover;border-radius:14px}
			.chm-single-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:0 0 30px;padding:0}
			.chm-single-fact{margin:0;padding:18px;border:1px solid var(--chm-line);border-radius:10px;background:var(--chm-soft)}
			.chm-single-fact dt{margin:0 0 5px;color:#657487;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
			.chm-single-fact dd{margin:0;color:var(--chm-ink);font-size:17px;font-weight:650}
			.chm-single-categories{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 24px}.chm-single-categories span{padding:6px 11px;border-radius:999px;background:var(--chm-soft);font-size:13px;font-weight:650}
			.chm-single-description{margin:0 0 32px}.chm-single-description>h2,.chm-single-gallery-section>h2{margin:0 0 16px;font-size:26px}
			.chm-single-gallery-section{margin:32px 0}.chm-single-gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
			.chm-single-gallery-link{display:block;aspect-ratio:4/3;overflow:hidden;border-radius:10px;background:var(--chm-soft)}
			.chm-single-gallery-image{display:block;width:100%;height:100%;object-fit:cover;transition:transform .2s ease}.chm-single-gallery-link:hover .chm-single-gallery-image{transform:scale(1.025)}
			.chm-single-cta{margin:32px 0 8px}.chm-single-cta a{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:10px 22px;border-radius:7px;color:#fff!important;background:var(--chm-link);font-weight:700;text-decoration:none!important}.chm-single-cta a:hover{filter:brightness(.9)}
			@media(max-width:700px){.chm-single-facts,.chm-single-gallery{grid-template-columns:1fr}.chm-single-hero-image{max-height:420px}}
			@media(prefers-reduced-motion:reduce){.chm-single-gallery-image{transition:none}}
		';
	}

	/** Featured/gallery image information. */
	private function image_data( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return null;
		}

		$full = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! $full ) {
			return null;
		}

		$sizes = array();
		foreach ( array( 'thumbnail', 'medium', 'large', 'full' ) as $size ) {
			$source = wp_get_attachment_image_url( $attachment_id, $size );
			if ( $source ) {
				$sizes[ $size ] = $source;
			}
		}

		return array(
			'id'     => $attachment_id,
			'url'    => $full[0],
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'width'  => (int) $full[1],
			'height' => (int) $full[2],
			'sizes'  => $sizes,
		);
	}

	/** Backward-compatible metadata keys used by the original plugin and this replacement. */
	private function meta_keys() {
		return array(
			'event_date' => array( '_chm_event_date', 'chm_event_date', 'event_date' ),
			'start_time' => array( '_chm_start_time', 'chm_start_time', 'start_time' ),
			'end_time'   => array( '_chm_end_time', 'chm_end_time', 'end_time' ),
			'location'   => array( '_chm_location', 'chm_location', 'location' ),
			'cta_label'  => array( '_chm_cta_label', 'chm_cta_label', 'cta_label' ),
			'cta_url'    => array( '_chm_cta_url', 'chm_cta_url', 'cta_url' ),
			'gallery'    => array( '_chm_gallery_ids', '_chm_gallery', 'chm_gallery_ids', 'gallery_ids' ),
		);
	}

	/** Read the first stored compatible value. */
	private function get_compatible_meta( $post_id, $field ) {
		$keys = $this->meta_keys();
		if ( empty( $keys[ $field ] ) ) {
			return '';
		}

		foreach ( $keys[ $field ] as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' !== $value && null !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/** Write to the canonical key while leaving old data untouched. */
	private function update_meta( $post_id, $field, $value ) {
		$keys = $this->meta_keys();
		if ( empty( $keys[ $field ][0] ) ) {
			return;
		}

		update_post_meta( $post_id, $keys[ $field ][0], $value );
	}

	/** Sanitize a comma-separated or array gallery value. */
	private function sanitize_gallery_ids( $value ) {
		$values = is_array( $value ) ? $value : explode( ',', (string) $value );
		$ids    = array();

		foreach ( $values as $value_item ) {
			$attachment_id = absint( $value_item );
			if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) {
				$ids[] = $attachment_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/** Add useful columns to every CityHub list screen. */
	public function admin_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'cb' === $key ) {
				$new_columns['chm_thumbnail'] = 'Thumbnail';
			}
			if ( 'title' === $key ) {
				$new_columns['chm_details'] = 'Details';
			}
		}

		return $new_columns;
	}

	/** Render custom list-table columns. */
	public function render_admin_column( $column, $post_id ) {
		if ( 'chm_thumbnail' === $column ) {
			echo get_the_post_thumbnail( $post_id, array( 64, 64 ), array( 'style' => 'width:64px;height:64px;object-fit:cover;border-radius:6px;' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		if ( 'chm_details' === $column ) {
			$post_type = get_post_type( $post_id );
			if ( 'chm_event' === $post_type ) {
				$date     = $this->get_compatible_meta( $post_id, 'event_date' );
				$start    = $this->get_compatible_meta( $post_id, 'start_time' );
				$end      = $this->get_compatible_meta( $post_id, 'end_time' );
				$location = $this->get_compatible_meta( $post_id, 'location' );
				echo esc_html( trim( "{$date} {$start}" ) );
				if ( $end ) {
					echo esc_html( " – {$end}" );
				}
				if ( $location ) {
					echo '<br><span class="description">' . esc_html( $location ) . '</span>';
				}
			} else {
				echo esc_html( $this->get_compatible_meta( $post_id, 'cta_label' ) );
			}
		}
	}

	/** Load the media picker and admin form styling only on CityHub screens. */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ( ! isset( $this->types[ $screen->post_type ] ) && 'toplevel_page_cityhub-manager' !== $screen->id ) ) {
			return;
		}

		wp_register_style( 'chm-admin', false, array(), self::VERSION );
		wp_enqueue_style( 'chm-admin' );
		wp_add_inline_style( 'chm-admin', $this->admin_css() );

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'jquery' );
			wp_add_inline_script( 'jquery', $this->admin_javascript() );
		}
	}

	/** Admin CSS. */
	private function admin_css() {
		return '
			.chm-description-heading{margin:20px 0 8px;font-size:14px}
			.chm-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 20px}
			.chm-field{margin:8px 0}.chm-field-wide{grid-column:1/-1}
			.chm-field label{display:block;margin-bottom:6px;font-weight:600}
			.chm-field input{width:100%;max-width:100%;min-height:38px}
			.chm-field small{display:block;margin-top:5px;color:#646970}
			.chm-gallery-preview{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0}
			.chm-gallery-item{position:relative;width:100px;height:100px}
			.chm-gallery-item img{width:100%;height:100%;object-fit:cover;border:1px solid #ccd0d4;border-radius:5px}
			.chm-remove-image{position:absolute;top:-7px;right:-7px;width:24px;height:24px;border:0;border-radius:50%;color:#fff;background:#b32d2e;font-size:18px;line-height:20px;cursor:pointer}
			.chm-api-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;max-width:1200px;margin-top:24px}
			.chm-api-card{padding:20px;border:1px solid #dcdcde;border-radius:8px;background:#fff}.chm-api-card h2{margin-top:0}.chm-api-card code{display:block;overflow:auto;padding:10px;background:#f6f7f7}
			.column-chm_thumbnail{width:76px}.column-chm_details{width:22%}
			@media(max-width:782px){.chm-form-grid{grid-template-columns:1fr}.chm-field-wide{grid-column:auto}}
		';
	}

	/** Media picker behavior. */
	private function admin_javascript() {
		return <<<'JS'
jQuery(function ($) {
	var frame;
	var input = $('#chm_gallery_ids');
	var preview = $('#chm-gallery-preview');

	function currentIds() {
		return input.val().split(',').map(Number).filter(Boolean);
	}

	function syncIds() {
		var ids = preview.find('.chm-gallery-item').map(function () {
			return Number($(this).data('id'));
		}).get().filter(Boolean);
		input.val(ids.join(','));
	}

	$('#chm-select-gallery').on('click', function (event) {
		event.preventDefault();
		frame = wp.media({
			title: 'Select gallery images',
			button: { text: 'Use selected images' },
			library: { type: 'image' },
			multiple: true
		});

		frame.on('open', function () {
			var selection = frame.state().get('selection');
			currentIds().forEach(function (id) {
				var attachment = wp.media.attachment(id);
				attachment.fetch();
				selection.add(attachment);
			});
		});

		frame.on('select', function () {
			preview.empty();
			frame.state().get('selection').each(function (attachment) {
				var data = attachment.toJSON();
				var imageUrl = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
				var item = $('<div class="chm-gallery-item">').attr('data-id', data.id);
				item.append($('<img alt="">').attr('src', imageUrl));
				item.append('<button type="button" class="chm-remove-image" aria-label="Remove image">×</button>');
				preview.append(item);
			});
			syncIds();
		});

		frame.open();
	});

	preview.on('click', '.chm-remove-image', function () {
		$(this).closest('.chm-gallery-item').remove();
		syncIds();
	});

	$('#chm-clear-gallery').on('click', function (event) {
		event.preventDefault();
		preview.empty();
		syncIds();
	});
});
JS;
	}

	/** Activation: register types before flushing rewrites. */
	public static function activate() {
		$plugin = self::instance();
		$plugin->register_content_types();
		flush_rewrite_rules();
	}

	/** Deactivation cleanup. */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

register_activation_hook( __FILE__, array( 'CityHub_Data_Manager', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CityHub_Data_Manager', 'deactivate' ) );

CityHub_Data_Manager::instance();
