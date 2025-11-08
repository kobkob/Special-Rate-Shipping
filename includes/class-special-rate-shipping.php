<?php
/**
 * Special Rate Shipping Main Class
 *
 * Main plugin class that handles initialization and core functionality.
 *
 * @package KobKob\SpecialRateShipping
 * @author Monsenhor Ricardo Filipo <filipo@kobkob.org>
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Special_Rate_Shipping {

	/**
	 * The single instance of Special_Rate_Shipping.
	 * @var 	object
	 * @access  private
	 * @since 	2.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 * @var     object
	 * @access  public
	 * @since   2.0.0
	 */
	public $settings = null;

	/**
	 * Admin API class object
	 * @var     object
	 * @access  public
	 * @since   2.0.0
	 */
	public $admin = null;

	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   2.0.0
	 */
	public $_version;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   2.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  public
	 * @since   2.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  public
	 * @since   2.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  public
	 * @since   2.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 * @var     string
	 * @access  public
	 * @since   2.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  public
	 * @since   2.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function __construct ( $file = '', $version = '2.0.0' ) {
		$this->_version = $version;
		$this->_token = 'special_rate_shipping';

		// Load plugin environment variables
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );


		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		// Load API for generic admin functions
		if ( is_admin() ) {
			$this->admin = new Special_Rate_Shipping_Admin_API();
		}

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// Create custom post type 
		add_action( 'init', array( $this, 'create_post_types' ),0 );

		// Add save hook for pouch meta data
		add_action( 'save_post', array( $this, 'save_pouch_meta_data' ) );

		// Add admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

	} // End __construct ()


	/**
	 * Add meta boxes for pouch edit screen
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function add_pouch_meta_boxes() {
		add_meta_box(
			'pouch-details',
			__( 'Pouch Details', 'special-rate-shipping' ),
			array( $this, 'pouch_details_meta_box' ),
			'pouch',
			'normal',
			'high'
		);

		add_meta_box(
			'pouch-products',
			__( 'Products in Pouch', 'special-rate-shipping' ),
			array( $this, 'pouch_products_meta_box' ),
			'pouch',
			'normal',
			'default'
		);

		add_meta_box(
			'pouch-shipping',
			__( 'Shipping Information', 'special-rate-shipping' ),
			array( $this, 'pouch_shipping_meta_box' ),
			'pouch',
			'side',
			'default'
		);

		add_meta_box(
			'pouch-barcode',
			__( 'Barcode & Label', 'special-rate-shipping' ),
			array( $this, 'pouch_barcode_meta_box' ),
			'pouch',
			'side',
			'default'
		);
	} // End add_pouch_meta_boxes ()

	/**
	 * Pouch details meta box content
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function pouch_details_meta_box( $post ) {
		wp_nonce_field( 'pouch_meta_box', 'pouch_meta_nonce' );

		$pouch_status = get_post_meta( $post->ID, '_pouch_status', true ) ?: 'new';
		$created_date = get_post_meta( $post->ID, '_created_date', true );
		$recipient_info = get_post_meta( $post->ID, '_recipient_info', true );
		$notes = get_post_meta( $post->ID, '_pouch_notes', true );

		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="pouch_status"><?php esc_html_e( 'Status', 'special-rate-shipping' ); ?></label></th>
				<td>
					<select name="pouch_status" id="pouch_status" class="regular-text">
						<option value="new" <?php selected( $pouch_status, 'new' ); ?>><?php esc_html_e( 'New', 'special-rate-shipping' ); ?></option>
						<option value="packed" <?php selected( $pouch_status, 'packed' ); ?>><?php esc_html_e( 'Packed', 'special-rate-shipping' ); ?></option>
						<option value="shipped" <?php selected( $pouch_status, 'shipped' ); ?>><?php esc_html_e( 'Shipped', 'special-rate-shipping' ); ?></option>
						<option value="delivered" <?php selected( $pouch_status, 'delivered' ); ?>><?php esc_html_e( 'Delivered', 'special-rate-shipping' ); ?></option>
					</select>
				</td>
			</tr>
			<?php if ( $created_date ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Created', 'special-rate-shipping' ); ?></th>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created_date ) ) ); ?></td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="recipient_info"><?php esc_html_e( 'Recipient Information', 'special-rate-shipping' ); ?></label></th>
				<td>
					<textarea name="recipient_info" id="recipient_info" class="large-text" rows="4"><?php echo esc_textarea( $recipient_info ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pouch_notes"><?php esc_html_e( 'Notes', 'special-rate-shipping' ); ?></label></th>
				<td>
					<textarea name="pouch_notes" id="pouch_notes" class="large-text" rows="3"><?php echo esc_textarea( $notes ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php
	} // End pouch_details_meta_box ()

	/**
	 * Products meta box content
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function pouch_products_meta_box( $post ) {
		$product_ids = get_post_meta( $post->ID, '_pouch_products', true ) ?: array();

		if ( ! empty( $product_ids ) && is_array( $product_ids ) ) :
			?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'special-rate-shipping' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'special-rate-shipping' ); ?></th>
						<th><?php esc_html_e( 'Price', 'special-rate-shipping' ); ?></th>
						<th><?php esc_html_e( 'Stock', 'special-rate-shipping' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $product_ids as $product_id ) :
						$product = wc_get_product( $product_id );
						if ( $product ) :
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $product->get_name() ); ?></strong>
								<?php if ( $product->get_description() ) : ?>
									<br><small><?php echo wp_kses_post( wp_trim_words( $product->get_description(), 15 ) ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $product->get_sku() ?: '—' ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></td>
							<td>
								<?php 
								if ( $product->is_in_stock() ) {
									if ( $product->managing_stock() ) {
										printf( esc_html__( '%d in stock', 'special-rate-shipping' ), $product->get_stock_quantity() );
									} else {
										esc_html_e( 'In stock', 'special-rate-shipping' );
									}
								} else {
									echo '<span style="color: #d63638;">' . esc_html__( 'Out of stock', 'special-rate-shipping' ) . '</span>';
								}
								?>
							</td>
						</tr>
						<?php 
						else :
						?>
						<tr>
							<td colspan="4">
								<em><?php printf( esc_html__( 'Product ID %d not found or deleted', 'special-rate-shipping' ), $product_id ); ?></em>
							</td>
						</tr>
						<?php 
						endif;
					endforeach; ?>
				</tbody>
			</table>
			<?php
		else :
			?>
			<p><?php esc_html_e( 'No products assigned to this pouch yet.', 'special-rate-shipping' ); ?></p>
			<?php
		endif;
	} // End pouch_products_meta_box ()

	/**
	 * Shipping information meta box content
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function pouch_shipping_meta_box( $post ) {
		$package_type = get_post_meta( $post->ID, '_package_type', true );

		$package_types = array(
			'small_box' => __( 'Small Box', 'special-rate-shipping' ),
			'medium_box' => __( 'Medium Box', 'special-rate-shipping' ),
			'big_box' => __( 'Big Box', 'special-rate-shipping' ),
			'envelope' => __( 'Envelope', 'special-rate-shipping' ),
			'flat_rate' => __( 'Flat Rate Box', 'special-rate-shipping' )
		);
		?>
		<p>
			<label for="package_type"><strong><?php esc_html_e( 'Package Type', 'special-rate-shipping' ); ?></strong></label><br>
			<select name="package_type" id="package_type" class="widefat">
				<option value=""><?php esc_html_e( 'Select Package Type', 'special-rate-shipping' ); ?></option>
				<?php foreach ( $package_types as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $package_type, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	} // End pouch_shipping_meta_box ()

	/**
	 * Barcode meta box content
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function pouch_barcode_meta_box( $post ) {
		$barcode = get_post_meta( $post->ID, '_barcode', true );
		
		if ( $barcode ) :
			?>
			<div class="pouch-barcode-display">
				<p><strong><?php esc_html_e( 'Barcode:', 'special-rate-shipping' ); ?></strong></p>
				<div class="barcode-text"><?php echo esc_html( $barcode ); ?></div>
				<p>
					<button type="button" class="button" onclick="window.open('<?php echo esc_url( admin_url( 'admin-post.php?action=print_pouch_label&pouch_id=' . $post->ID ) ); ?>', '_blank')">
						<?php esc_html_e( 'Print Label', 'special-rate-shipping' ); ?>
					</button>
				</p>
			</div>
			<style>
			.pouch-barcode-display {
				text-align: center;
				padding: 15px;
				border: 1px solid #ddd;
				background: #f9f9f9;
				border-radius: 4px;
			}
			.barcode-text {
				font-family: 'Courier New', monospace;
				font-size: 16px;
				font-weight: bold;
				padding: 10px;
				border: 2px solid #333;
				background: #fff;
				margin: 10px 0;
				letter-spacing: 2px;
			}
			</style>
			<?php
		else :
			?>
			<p><?php esc_html_e( 'Barcode will be generated when the pouch is saved.', 'special-rate-shipping' ); ?></p>
			<?php
		endif;
	} // End pouch_barcode_meta_box ()

	/**
	 * Save pouch meta box data
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function save_pouch_meta_data( $post_id ) {
		// Check if it's a pouch post type
		if ( get_post_type( $post_id ) !== 'pouch' ) {
			return;
		}

		// Check nonce
		if ( ! isset( $_POST['pouch_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pouch_meta_nonce'], 'pouch_meta_box' ) ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save meta data
		if ( isset( $_POST['pouch_status'] ) ) {
			update_post_meta( $post_id, '_pouch_status', sanitize_text_field( $_POST['pouch_status'] ) );
		}

		if ( isset( $_POST['package_type'] ) ) {
			update_post_meta( $post_id, '_package_type', sanitize_text_field( $_POST['package_type'] ) );
		}

		if ( isset( $_POST['recipient_info'] ) ) {
			update_post_meta( $post_id, '_recipient_info', sanitize_textarea_field( $_POST['recipient_info'] ) );
		}

		if ( isset( $_POST['pouch_notes'] ) ) {
			update_post_meta( $post_id, '_pouch_notes', sanitize_textarea_field( $_POST['pouch_notes'] ) );
		}

		// Generate barcode if it doesn't exist
		if ( ! get_post_meta( $post_id, '_barcode', true ) ) {
			update_post_meta( $post_id, '_barcode', $this->generate_barcode() );
		}
	} // End save_pouch_meta_data ()

	/**
	 * Create the custom post type.
	 * @access  public
	 * @since   2.0.0
	 * @return void
	 */
	public function create_post_types () {
		register_post_type( 'pouch',
				array(
					'labels' => array(
						'name' => __( 'Pouches', 'special-rate-shipping' ),
						'singular_name' => __( 'Pouch', 'special-rate-shipping' ),
						'edit_item' => __( 'Edit Pouch', 'special-rate-shipping' ),
						'add_new_item' => __( 'Add New Pouch', 'special-rate-shipping' ),
						'new_item' => __( 'New Pouch', 'special-rate-shipping' ),
						'add_new' => __( 'Add New Pouch', 'special-rate-shipping' ),
						'view_item' => __( 'View Pouch', 'special-rate-shipping' ),
						'view_items' => __( 'View Pouches', 'special-rate-shipping' ),
						'search_items' => __( 'Search Pouches', 'special-rate-shipping' ),
						'not_found' => __( 'No pouches found', 'special-rate-shipping' ),
						'not_found_in_trash' => __( 'No pouches found in trash', 'special-rate-shipping' ),
						'all_items' => __( 'All Pouches', 'special-rate-shipping' ),
						'archives' => __( 'Pouch Archives', 'special-rate-shipping' ),
						'attributes' => __( 'Pouch Attributes', 'special-rate-shipping' ),
						),
					'description' => __( 'Shipping pouches containing WooCommerce products with USPS package information and tracking barcodes.', 'special-rate-shipping' ),
					'public' => false,
					'publicly_queryable' => false,
					'show_ui' => true,
					'show_in_menu' => 'special-rate-system',
					'show_in_admin_bar' => true,
					'show_in_nav_menus' => false,
					'can_export' => true,
					'has_archive' => false,
					'exclude_from_search' => true,
					'capability_type' => 'post',
					'capabilities' => array(
						'create_posts' => 'manage_woocommerce',
						'edit_posts' => 'manage_woocommerce',
						'edit_others_posts' => 'manage_woocommerce',
						'delete_posts' => 'manage_woocommerce',
						'publish_posts' => 'manage_woocommerce',
						'read_private_posts' => 'manage_woocommerce'
					),
					'map_meta_cap' => true,
					'menu_icon'=> 'dashicons-archive',
					'supports' => array('title', 'custom-fields'),
					'register_meta_box_cb' => array( $this, 'add_pouch_meta_boxes' ),
					)
				);
	} // End create_post_types ()

	/**
	 * Add admin menu for Special Rate System
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function add_admin_menu() {
		// Add main menu page
		add_menu_page(
			__( 'Special Rate System', 'special-rate-shipping' ),
			__( 'Special Rate System', 'special-rate-shipping' ),
			'manage_woocommerce',
			'special-rate-system',
			array( $this, 'admin_dashboard_page' ),
			'dashicons-shipping',
			56
		);

		// Add submenu pages
		add_submenu_page(
			'special-rate-system',
			__( 'Dashboard', 'special-rate-shipping' ),
			__( 'Dashboard', 'special-rate-shipping' ),
			'manage_woocommerce',
			'special-rate-system',
			array( $this, 'admin_dashboard_page' )
		);

		add_submenu_page(
			'special-rate-system',
			__( 'Create Pouch', 'special-rate-shipping' ),
			__( 'Create Pouch', 'special-rate-shipping' ),
			'manage_woocommerce',
			'special-rate-create-pouch',
			array( $this, 'admin_create_pouch_page' )
		);

		add_submenu_page(
			'special-rate-system',
			__( 'Settings', 'special-rate-shipping' ),
			__( 'Settings', 'special-rate-shipping' ),
			'manage_options',
			'special-rate-settings',
			array( $this, 'admin_settings_redirect' )
		);

		add_submenu_page(
			'special-rate-system',
			__( 'WooCommerce Shipping', 'special-rate-shipping' ),
			__( 'WooCommerce Shipping', 'special-rate-shipping' ),
			'manage_woocommerce',
			'special-rate-wc-shipping',
			array( $this, 'admin_woocommerce_redirect' )
		);

		add_submenu_page(
			'special-rate-system',
			__( 'Documentation', 'special-rate-shipping' ),
			__( 'Documentation', 'special-rate-shipping' ),
			'read',
			'special-rate-docs',
			array( $this, 'admin_documentation_page' )
		);
	} // End add_admin_menu ()

	/**
	 * Dashboard page content
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function admin_dashboard_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Special Rate System Dashboard', 'special-rate-shipping' ); ?></h1>
			
			<div class="srs-dashboard-widgets">
				<div class="srs-widget">
					<h2><?php esc_html_e( 'Quick Actions', 'special-rate-shipping' ); ?></h2>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=special-rate-create-pouch' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Create New Pouch', 'special-rate-shipping' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pouch' ) ); ?>" class="button">
							<?php esc_html_e( 'View All Pouches', 'special-rate-shipping' ); ?>
						</a>
					</p>
				</div>
				
				<div class="srs-widget">
					<h2><?php esc_html_e( 'System Status', 'special-rate-shipping' ); ?></h2>
					<?php
					$pouch_count = wp_count_posts( 'pouch' );
					$total_pouches = $pouch_count->publish + $pouch_count->draft + $pouch_count->pending;
					?>
					<p><?php printf( esc_html__( 'Total Pouches: %d', 'special-rate-shipping' ), $total_pouches ); ?></p>
					<p><?php printf( esc_html__( 'Published: %d', 'special-rate-shipping' ), $pouch_count->publish ); ?></p>
					<p><?php printf( esc_html__( 'Draft: %d', 'special-rate-shipping' ), $pouch_count->draft ); ?></p>
				</div>
			</div>
			
			<style>
			.srs-dashboard-widgets {
				display: flex;
				flex-wrap: wrap;
				gap: 20px;
				margin-top: 20px;
			}
			.srs-widget {
				flex: 1;
				min-width: 300px;
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 20px;
				box-shadow: 0 1px 1px rgba(0,0,0,0.04);
			}
			.srs-widget h2 {
				margin-top: 0;
				border-bottom: 1px solid #eee;
				padding-bottom: 10px;
			}
			</style>
		</div>
		<?php
	} // End admin_dashboard_page ()

	/**
	 * Create Pouch form page
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function admin_create_pouch_page() {
		// Handle form submission
		if ( isset( $_POST['create_pouch'] ) && wp_verify_nonce( $_POST['pouch_nonce'], 'create_pouch_action' ) ) {
			$this->handle_create_pouch_form();
		}

		// Get WooCommerce products for the dropdown
		$products = wc_get_products( array(
			'status' => 'publish',
			'limit' => -1,
			'orderby' => 'title',
			'order' => 'ASC'
		) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Create New Pouch', 'special-rate-shipping' ); ?></h1>
			
			<form method="post" action="" class="srs-create-pouch-form">
				<?php wp_nonce_field( 'create_pouch_action', 'pouch_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="pouch_title"><?php esc_html_e( 'Pouch Title', 'special-rate-shipping' ); ?></label>
						</th>
						<td>
							<input type="text" id="pouch_title" name="pouch_title" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Enter a descriptive title for this pouch', 'special-rate-shipping' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="pouch_products"><?php esc_html_e( 'Products', 'special-rate-shipping' ); ?></label>
						</th>
						<td>
							<select id="pouch_products" name="pouch_products[]" multiple class="srs-products-select" style="width: 100%; min-height: 150px;">
								<?php foreach ( $products as $product ) : ?>
									<option value="<?php echo esc_attr( $product->get_id() ); ?>">
										<?php echo esc_html( $product->get_name() . ' - $' . $product->get_price() ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select products to include in this pouch. Hold Ctrl/Cmd to select multiple.', 'special-rate-shipping' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="package_type"><?php esc_html_e( 'USPS Package Type', 'special-rate-shipping' ); ?></label>
						</th>
						<td>
							<select id="package_type" name="package_type" class="regular-text" required>
								<option value=""><?php esc_html_e( 'Select Package Type', 'special-rate-shipping' ); ?></option>
								<option value="small_box"><?php esc_html_e( 'Small Box', 'special-rate-shipping' ); ?></option>
								<option value="medium_box"><?php esc_html_e( 'Medium Box', 'special-rate-shipping' ); ?></option>
								<option value="big_box"><?php esc_html_e( 'Big Box', 'special-rate-shipping' ); ?></option>
								<option value="envelope"><?php esc_html_e( 'Envelope', 'special-rate-shipping' ); ?></option>
								<option value="flat_rate"><?php esc_html_e( 'Flat Rate Box', 'special-rate-shipping' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Select the USPS package type for shipping', 'special-rate-shipping' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="recipient_info"><?php esc_html_e( 'Recipient Information', 'special-rate-shipping' ); ?></label>
						</th>
						<td>
							<textarea id="recipient_info" name="recipient_info" class="large-text" rows="4" placeholder="Name
Address Line 1
Address Line 2
City, State ZIP"></textarea>
							<p class="description"><?php esc_html_e( 'Enter recipient shipping address (optional for draft pouches)', 'special-rate-shipping' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="pouch_notes"><?php esc_html_e( 'Notes', 'special-rate-shipping' ); ?></label>
						</th>
						<td>
							<textarea id="pouch_notes" name="pouch_notes" class="large-text" rows="3"></textarea>
							<p class="description"><?php esc_html_e( 'Optional notes about this pouch', 'special-rate-shipping' ); ?></p>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" name="create_pouch" class="button button-primary" value="<?php esc_attr_e( 'Create Pouch', 'special-rate-shipping' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=special-rate-system' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'special-rate-shipping' ); ?></a>
				</p>
			</form>
			
			<style>
			.srs-create-pouch-form .form-table th {
				width: 200px;
				vertical-align: top;
				padding-top: 15px;
			}
			.srs-products-select {
				max-width: 500px;
			}
			</style>
		</div>
		<?php
	} // End admin_create_pouch_page ()

	/**
	 * Handle create pouch form submission
	 * @access  private
	 * @since   2.0.0
	 * @return  void
	 */
	private function handle_create_pouch_form() {
		// Sanitize and validate input
		$title = sanitize_text_field( $_POST['pouch_title'] );
		$products = isset( $_POST['pouch_products'] ) ? array_map( 'intval', $_POST['pouch_products'] ) : array();
		$package_type = sanitize_text_field( $_POST['package_type'] );
		$recipient_info = sanitize_textarea_field( $_POST['recipient_info'] );
		$notes = sanitize_textarea_field( $_POST['pouch_notes'] );

		if ( empty( $title ) || empty( $package_type ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Title and Package Type are required fields.', 'special-rate-shipping' ) . '</p></div>';
			} );
			return;
		}

		// Create the pouch post
		$pouch_data = array(
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'draft',
			'post_type' => 'pouch',
			'meta_input' => array(
				'_pouch_products' => $products,
				'_package_type' => $package_type,
				'_recipient_info' => $recipient_info,
				'_pouch_notes' => $notes,
				'_pouch_status' => 'new',
				'_created_date' => current_time( 'mysql' ),
				'_barcode' => $this->generate_barcode()
			)
		);

		$pouch_id = wp_insert_post( $pouch_data );

		if ( $pouch_id && ! is_wp_error( $pouch_id ) ) {
			add_action( 'admin_notices', function() use ( $pouch_id ) {
				echo '<div class="notice notice-success"><p>' . 
					sprintf( 
						esc_html__( 'Pouch created successfully! <a href="%s">View Pouch</a>', 'special-rate-shipping' ),
						esc_url( get_edit_post_link( $pouch_id ) )
					) . 
					'</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Error creating pouch. Please try again.', 'special-rate-shipping' ) . '</p></div>';
			} );
		}
	} // End handle_create_pouch_form ()

	/**
	 * Generate a unique barcode for the pouch
	 * @access  private
	 * @since   2.0.0
	 * @return  string
	 */
	private function generate_barcode() {
		return 'SRS' . date( 'Ymd' ) . strtoupper( substr( uniqid(), -6 ) );
	} // End generate_barcode ()

	/**
	 * Redirect to plugin settings
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function admin_settings_redirect() {
		wp_redirect( admin_url( 'options-general.php?page=special_rate_shipping_settings' ) );
		exit;
	} // End admin_settings_redirect ()

	/**
	 * Redirect to WooCommerce shipping settings
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function admin_woocommerce_redirect() {
		wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) );
		exit;
	} // End admin_woocommerce_redirect ()

	/**
	 * Documentation page content
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function admin_documentation_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Special Rate System Documentation', 'special-rate-shipping' ); ?></h1>
			
			<div class="srs-docs-content">
				<h2><?php esc_html_e( 'Getting Started', 'special-rate-shipping' ); ?></h2>
				<p><?php esc_html_e( 'The Special Rate System allows you to create custom shipping pouches with optimized package selection and automatic label generation.', 'special-rate-shipping' ); ?></p>
				
				<h3><?php esc_html_e( 'Creating Pouches', 'special-rate-shipping' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Go to Special Rate System > Create Pouch', 'special-rate-shipping' ); ?></li>
					<li><?php esc_html_e( 'Enter a descriptive title for your pouch', 'special-rate-shipping' ); ?></li>
					<li><?php esc_html_e( 'Select one or more WooCommerce products', 'special-rate-shipping' ); ?></li>
					<li><?php esc_html_e( 'Choose the appropriate USPS package type', 'special-rate-shipping' ); ?></li>
					<li><?php esc_html_e( 'Add recipient information and notes', 'special-rate-shipping' ); ?></li>
					<li><?php esc_html_e( 'Click "Create Pouch" to generate the pouch with barcode', 'special-rate-shipping' ); ?></li>
				</ol>
				
				<h3><?php esc_html_e( 'Package Types', 'special-rate-shipping' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'Small Box', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'For lightweight, small items', 'special-rate-shipping' ); ?></li>
					<li><strong><?php esc_html_e( 'Medium Box', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'For standard-sized products', 'special-rate-shipping' ); ?></li>
					<li><strong><?php esc_html_e( 'Big Box', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'For large or multiple items', 'special-rate-shipping' ); ?></li>
					<li><strong><?php esc_html_e( 'Envelope', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'For documents or flat items', 'special-rate-shipping' ); ?></li>
					<li><strong><?php esc_html_e( 'Flat Rate Box', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'USPS flat rate shipping', 'special-rate-shipping' ); ?></li>
				</ul>
				
				<h3><?php esc_html_e( 'Pouch Status', 'special-rate-shipping' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'New', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'Recently created pouch', 'special-rate-shipping' ); ?></li>
					<li><strong><?php esc_html_e( 'Packed', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'Items have been packed', 'special-rate-shipping' ); ?></li>
					<li><strong><?php esc_html_e( 'Shipped', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'Package has been shipped', 'special-rate-shipping' ); ?></li>
					<li><strong><?php esc_html_e( 'Delivered', 'special-rate-shipping' ); ?>:</strong> <?php esc_html_e( 'Package has been delivered', 'special-rate-shipping' ); ?></li>
				</ul>
				
				<h2><?php esc_html_e( 'Configuration', 'special-rate-shipping' ); ?></h2>
				<p><?php esc_html_e( 'Configure the plugin settings in the Settings page to set up API credentials, sender information, and default package rates.', 'special-rate-shipping' ); ?></p>
				
				<h2><?php esc_html_e( 'Support', 'special-rate-shipping' ); ?></h2>
				<p><?php printf( 
					esc_html__( 'For support and updates, visit %s or contact %s', 'special-rate-shipping' ),
					'<a href="https://www.kobkob.org/" target="_blank">kobkob.org</a>',
					'<a href="mailto:filipo@kobkob.org">filipo@kobkob.org</a>'
				); ?></p>
			</div>
			
			<style>
			.srs-docs-content {
				max-width: 800px;
				line-height: 1.6;
			}
			.srs-docs-content h2 {
				border-bottom: 1px solid #ccd0d4;
				padding-bottom: 10px;
				margin-top: 30px;
			}
			.srs-docs-content h3 {
				color: #0073aa;
				margin-top: 25px;
			}
			.srs-docs-content ul, .srs-docs-content ol {
				margin-left: 20px;
			}
			.srs-docs-content li {
				margin-bottom: 8px;
			}
			</style>
		</div>
		<?php
	} // End admin_documentation_page ()

	/**
	 * Wrapper function to register a new post type
	 * @param  string $post_type   Post type name
	 * @param  string $plural      Post type item plural name
	 * @param  string $single      Post type item single name
	 * @param  string $description Description of post type
	 * @return object              Post type class object
	 */
	public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) return;

		$post_type = new Special_Rate_Shipping_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy
	 * @param  string $taxonomy   Taxonomy name
	 * @param  string $plural     Taxonomy single name
	 * @param  string $single     Taxonomy plural name
	 * @param  array  $post_types Post types to which this taxonomy applies
	 * @return object             Taxonomy class object
	 */
	public function register_taxonomy ( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) return;

		$taxonomy = new Special_Rate_Shipping_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   2.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-frontend' );
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'special-rate-shipping', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
		$domain = 'special-rate-shipping';

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main Special_Rate_Shipping Instance
	 *
	 * Ensures only one instance of Special_Rate_Shipping is loaded or can be loaded.
	 *
	 * @since 2.0.0
	 * @static
	 * @see Special_Rate_Shipping()
	 * @return Main Special_Rate_Shipping instance
	 */
	public static function instance ( $file = '', $version = '2.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 2.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 2.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
