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

		// Hook into WooCommerce order processing
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_create_pouch_from_order' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
		// Alternative hooks for pouch creation
		add_action( 'woocommerce_new_order', array( $this, 'maybe_create_pouch_from_new_order' ), 20, 1 );
		add_action( 'woocommerce_thankyou', array( $this, 'maybe_create_pouch_from_thankyou' ), 10, 1 );
		
		// Additional debugging hooks (can be disabled in production)
		// add_action( 'woocommerce_checkout_order_processed', array( $this, 'debug_order_processed' ), 5, 3 );
		// add_action( 'woocommerce_new_order', array( $this, 'debug_new_order' ), 10, 1 );
		// add_action( 'woocommerce_thankyou', array( $this, 'debug_thankyou' ), 10, 1 );
		// add_action( 'woocommerce_order_status_changed', array( $this, 'debug_status_change' ), 5, 4 );

		// Add custom columns to pouch list
		add_filter( 'manage_pouch_posts_columns', array( $this, 'add_pouch_columns' ) );
		add_action( 'manage_pouch_posts_custom_column', array( $this, 'populate_pouch_columns' ), 10, 2 );

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

		add_meta_box(
			'pouch-order',
			__( 'Order Information', 'special-rate-shipping' ),
			array( $this, 'pouch_order_meta_box' ),
			'pouch',
			'side',
			'high'
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
		$package_type = get_post_meta( $post->ID, '_package_type', true );

		// Display summary information
		if ( ! empty( $product_ids ) && is_array( $product_ids ) ) :
			$product_count = count( $product_ids );
			$package_types = array(
				'small_box' => __( 'Small Box', 'special-rate-shipping' ),
				'medium_box' => __( 'Medium Box', 'special-rate-shipping' ),
				'big_box' => __( 'Big Box', 'special-rate-shipping' ),
				'envelope' => __( 'Envelope', 'special-rate-shipping' ),
				'flat_rate' => __( 'Flat Rate Box', 'special-rate-shipping' )
			);
			$package_label = isset( $package_types[ $package_type ] ) ? $package_types[ $package_type ] : __( 'Not specified', 'special-rate-shipping' );
			?>
			<div class="pouch-summary" style="background: #f0f6fc; padding: 15px; margin-bottom: 20px; border: 1px solid #c3d4e5; border-radius: 5px;">
				<h4 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e( 'Packaging Summary', 'special-rate-shipping' ); ?></h4>
				<div style="display: flex; gap: 20px; align-items: center;">
					<div>
						<strong><?php printf( esc_html__( '%d Products', 'special-rate-shipping' ), $product_count ); ?></strong>
					</div>
					<div>
						<span class="dashicons dashicons-archive" style="color: #2271b1;"></span>
						<strong><?php echo esc_html( $package_label ); ?></strong>
					</div>
				</div>
			</div>
			<?php
		endif;

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
	 * Order information meta box content
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function pouch_order_meta_box( $post ) {
		$order_id = get_post_meta( $post->ID, '_order_id', true );
		$customer_id = get_post_meta( $post->ID, '_customer_id', true );
		$order_total = get_post_meta( $post->ID, '_order_total', true );

		if ( $order_id ) :
			$order = wc_get_order( $order_id );
			if ( $order ) :
				?>
				<div class="pouch-order-info">
					<p><strong><?php esc_html_e( 'Order:', 'special-rate-shipping' ); ?></strong><br>
						<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" target="_blank">
							#<?php echo esc_html( $order->get_order_number() ); ?>
						</a>
					</p>

					<p><strong><?php esc_html_e( 'Status:', 'special-rate-shipping' ); ?></strong><br>
						<span class="order-status status-<?php echo esc_attr( $order->get_status() ); ?>">
							<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
						</span>
					</p>

					<p><strong><?php esc_html_e( 'Total:', 'special-rate-shipping' ); ?></strong><br>
						<?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?>
					</p>

					<p><strong><?php esc_html_e( 'Date:', 'special-rate-shipping' ); ?></strong><br>
						<?php echo esc_html( $order->get_date_created()->date_i18n( wc_date_format() ) ); ?>
					</p>

					<?php 
					$customer = $order->get_user();
					if ( $customer ) :
					?>
					<p><strong><?php esc_html_e( 'Customer:', 'special-rate-shipping' ); ?></strong><br>
						<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $customer->ID ) ); ?>" target="_blank">
							<?php echo esc_html( $customer->display_name ); ?>
						</a>
					</p>
					<?php endif; ?>

					<p>
						<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" class="button" target="_blank">
							<?php esc_html_e( 'View Order', 'special-rate-shipping' ); ?>
						</a>
					</p>
				</div>
				<style>
				.pouch-order-info {
					padding: 10px;
				}
				.order-status {
					padding: 4px 8px;
					border-radius: 3px;
					font-size: 11px;
					font-weight: bold;
					text-transform: uppercase;
					background: #f0f0f0;
					color: #444;
				}
				.status-processing { background: #c6e1c6; color: #5b841b; }
				.status-completed { background: #c8d7e1; color: #2e4453; }
				.status-on-hold { background: #f8dda7; color: #94660c; }
				.status-cancelled { background: #eba3a3; color: #761919; }
				</style>
				<?php
			else :
				?>
				<p><?php printf( esc_html__( 'Order #%s not found or deleted', 'special-rate-shipping' ), $order_id ); ?></p>
				<?php
			endif;
		else :
			?>
			<p><?php esc_html_e( 'This pouch was created manually and is not linked to an order.', 'special-rate-shipping' ); ?></p>
			<?php
		endif;
	} // End pouch_order_meta_box ()

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
	 * Check if order uses Special Rate Shipping and create pouch if needed
	 * @access  public
	 * @since   2.0.0
	 * @param   int $order_id
	 * @param   array $posted_data
	 * @param   WC_Order $order
	 * @return  void
	 */
	public function maybe_create_pouch_from_order( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if order uses Special Rate Shipping
		$shipping_methods = $order->get_shipping_methods();
		$uses_special_rate = false;
		$selected_package_type = 'medium_box'; // default
		$shipping_method_debug = array();

		foreach ( $shipping_methods as $shipping_method ) {
			$method_id = $shipping_method->get_method_id();
			$shipping_method_debug[] = $method_id;
			
			// Check for both shipping method variations
			if ( strpos( $method_id, 'special_rate_shipping' ) !== false || 
				 $method_id === 'special_rate_shipping_method' ||
				 $method_id === 'special_rate_shipping_enhanced_method' ) {
				$uses_special_rate = true;
				// Try to determine the best package type based on items
				$selected_package_type = $this->determine_optimal_package_type( $order );
				break;
			}
		}

		// Optional debug information (uncomment for troubleshooting)
		// error_log( sprintf( 
		//	'Special Rate Shipping Debug - Order %d: Shipping methods found: %s, Uses special rate: %s', 
		//	$order_id, 
		//	implode( ', ', $shipping_method_debug ),
		//	$uses_special_rate ? 'YES' : 'NO'
		// ) );

		if ( ! $uses_special_rate ) {
			return;
		}

		// Check if pouch already exists for this order
		$existing_pouch = get_posts( array(
			'post_type' => 'pouch',
			'meta_query' => array(
				array(
					'key' => '_order_id',
					'value' => $order_id,
					'compare' => '='
				)
			),
			'posts_per_page' => 1,
			'post_status' => 'any'
		) );

		if ( ! empty( $existing_pouch ) ) {
			return; // Pouch already exists
		}

		// Get order items (products)
		$product_ids = array();
		foreach ( $order->get_items() as $item ) {
			$product_ids[] = $item->get_product_id();
		}

		// Get shipping address
		$shipping_address = $this->format_shipping_address( $order );

		// Create the pouch
		$pouch_title = sprintf(
			__( 'Order #%s Pouch', 'special-rate-shipping' ),
			$order->get_order_number()
		);

		$pouch_data = array(
			'post_title' => $pouch_title,
			'post_content' => '',
			'post_status' => 'draft',
			'post_type' => 'pouch',
			'meta_input' => array(
				'_pouch_products' => $product_ids,
				'_package_type' => $selected_package_type,
				'_recipient_info' => $shipping_address,
				'_pouch_notes' => sprintf( __( 'Auto-generated from WooCommerce Order #%s', 'special-rate-shipping' ), $order->get_order_number() ),
				'_pouch_status' => 'new',
				'_created_date' => current_time( 'mysql' ),
				'_barcode' => $this->generate_barcode(),
				'_order_id' => $order_id,
				'_order_total' => $order->get_total(),
				'_customer_id' => $order->get_customer_id()
			)
		);

		$pouch_id = wp_insert_post( $pouch_data );

		if ( $pouch_id && ! is_wp_error( $pouch_id ) ) {
			// Add order note
			$order->add_order_note(
				sprintf(
					__( 'Shipping pouch created: %s (ID: %d)', 'special-rate-shipping' ),
					$pouch_title,
					$pouch_id
				)
			);

			// Store pouch ID in order meta
			$order->update_meta_data( '_pouch_id', $pouch_id );
			$order->save();

			// Log successful creation (can be disabled in production)
			// error_log( sprintf( 'Special Rate Shipping: Created pouch %d for order %d', $pouch_id, $order_id ) );
		}
	} // End maybe_create_pouch_from_order ()

	/**
	 * Handle order status changes to update pouch status
	 * @access  public
	 * @since   2.0.0
	 * @param   int $order_id
	 * @param   string $old_status
	 * @param   string $new_status
	 * @param   WC_Order $order
	 * @return  void
	 */
	public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
		// Get associated pouch
		$pouch_id = $order->get_meta( '_pouch_id' );

		if ( ! $pouch_id ) {
			return;
		}

		// Map order statuses to pouch statuses
		$status_mapping = array(
			'processing' => 'new',
			'shipped' => 'shipped',
			'completed' => 'delivered',
			'cancelled' => 'cancelled',
			'refunded' => 'cancelled'
		);

		if ( isset( $status_mapping[ $new_status ] ) ) {
			$new_pouch_status = $status_mapping[ $new_status ];
			update_post_meta( $pouch_id, '_pouch_status', $new_pouch_status );

			// Add order note about pouch status update
			$order->add_order_note(
				sprintf(
					__( 'Pouch status updated to: %s', 'special-rate-shipping' ),
					ucwords( str_replace( '_', ' ', $new_pouch_status ) )
				)
			);
		}
	} // End handle_order_status_change ()

	/**
	 * Determine optimal package type based on order items
	 * @access  private
	 * @since   2.0.0
	 * @param   WC_Order $order
	 * @return  string
	 */
	private function determine_optimal_package_type( $order ) {
		$item_count = 0;
		$total_weight = 0;

		// Count items and calculate total weight
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$item_count += $item->get_quantity();
				$weight = $product->get_weight();
				if ( $weight ) {
					$total_weight += ( $weight * $item->get_quantity() );
				}
			}
		}

		// Simple logic for package type determination
		// This can be enhanced with more sophisticated rules
		if ( $item_count <= 2 && $total_weight <= 1 ) {
			return 'small_box';
		} elseif ( $item_count <= 5 && $total_weight <= 5 ) {
			return 'medium_box';
		} else {
			return 'big_box';
		}
	} // End determine_optimal_package_type ()

	/**
	 * Format shipping address for pouch
	 * @access  private
	 * @since   2.0.0
	 * @param   WC_Order $order
	 * @return  string
	 */
	private function format_shipping_address( $order ) {
		$address_parts = array();

		// Get shipping address
		$first_name = $order->get_shipping_first_name();
		$last_name = $order->get_shipping_last_name();
		$company = $order->get_shipping_company();
		$address_1 = $order->get_shipping_address_1();
		$address_2 = $order->get_shipping_address_2();
		$city = $order->get_shipping_city();
		$state = $order->get_shipping_state();
		$postcode = $order->get_shipping_postcode();
		$country = $order->get_shipping_country();

		// If no shipping address, use billing address
		if ( empty( $first_name ) && empty( $address_1 ) ) {
			$first_name = $order->get_billing_first_name();
			$last_name = $order->get_billing_last_name();
			$company = $order->get_billing_company();
			$address_1 = $order->get_billing_address_1();
			$address_2 = $order->get_billing_address_2();
			$city = $order->get_billing_city();
			$state = $order->get_billing_state();
			$postcode = $order->get_billing_postcode();
			$country = $order->get_billing_country();
		}

		// Build address string
		if ( $first_name || $last_name ) {
			$address_parts[] = trim( $first_name . ' ' . $last_name );
		}

		if ( $company ) {
			$address_parts[] = $company;
		}

		if ( $address_1 ) {
			$address_parts[] = $address_1;
		}

		if ( $address_2 ) {
			$address_parts[] = $address_2;
		}

		if ( $city || $state || $postcode ) {
			$city_state_zip = trim( $city . ', ' . $state . ' ' . $postcode );
			$address_parts[] = $city_state_zip;
		}

		if ( $country ) {
			$countries = WC()->countries->get_countries();
			if ( isset( $countries[ $country ] ) ) {
				$address_parts[] = $countries[ $country ];
			}
		}

		return implode( "\n", $address_parts );
	} // End format_shipping_address ()

	/**
	 * Add custom columns to pouch list
	 * @access  public
	 * @since   2.0.0
	 * @param   array $columns
	 * @return  array
	 */
	public function add_pouch_columns( $columns ) {
		$new_columns = array();
		
		// Keep title and date, add custom columns
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];
		$new_columns['pouch_status'] = __( 'Status', 'special-rate-shipping' );
		$new_columns['pouch_order'] = __( 'Order', 'special-rate-shipping' );
		$new_columns['pouch_package'] = __( 'Package Type', 'special-rate-shipping' );
		$new_columns['pouch_barcode'] = __( 'Barcode', 'special-rate-shipping' );
		$new_columns['pouch_products'] = __( 'Products', 'special-rate-shipping' );
		$new_columns['date'] = $columns['date'];
		
		return $new_columns;
	} // End add_pouch_columns ()

	/**
	 * Populate custom columns in pouch list
	 * @access  public
	 * @since   2.0.0
	 * @param   string $column
	 * @param   int $post_id
	 * @return  void
	 */
	public function populate_pouch_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'pouch_status':
				$status = get_post_meta( $post_id, '_pouch_status', true ) ?: 'new';
				$status_labels = array(
					'new' => __( 'New', 'special-rate-shipping' ),
					'packed' => __( 'Packed', 'special-rate-shipping' ),
					'shipped' => __( 'Shipped', 'special-rate-shipping' ),
					'delivered' => __( 'Delivered', 'special-rate-shipping' ),
					'cancelled' => __( 'Cancelled', 'special-rate-shipping' )
				);
				$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
				printf( '<span class="pouch-status status-%s">%s</span>', esc_attr( $status ), esc_html( $status_label ) );
				break;

			case 'pouch_order':
				$order_id = get_post_meta( $post_id, '_order_id', true );
				if ( $order_id ) {
					$order = wc_get_order( $order_id );
					if ( $order ) {
						printf(
							'<a href="%s" target="_blank">#%s</a><br><small>%s</small>',
							esc_url( $order->get_edit_order_url() ),
							esc_html( $order->get_order_number() ),
							esc_html( wc_price( $order->get_total() ) )
						);
					} else {
						printf( '<em>%s</em>', esc_html__( 'Order not found', 'special-rate-shipping' ) );
					}
				} else {
					printf( '<em>%s</em>', esc_html__( 'Manual', 'special-rate-shipping' ) );
				}
				break;

			case 'pouch_package':
				$package_type = get_post_meta( $post_id, '_package_type', true );
				$package_types = array(
					'small_box' => __( 'Small Box', 'special-rate-shipping' ),
					'medium_box' => __( 'Medium Box', 'special-rate-shipping' ),
					'big_box' => __( 'Big Box', 'special-rate-shipping' ),
					'envelope' => __( 'Envelope', 'special-rate-shipping' ),
					'flat_rate' => __( 'Flat Rate Box', 'special-rate-shipping' )
				);
				$package_label = isset( $package_types[ $package_type ] ) ? $package_types[ $package_type ] : ucfirst( str_replace( '_', ' ', $package_type ) );
				echo esc_html( $package_label );
				break;

			case 'pouch_barcode':
				$barcode = get_post_meta( $post_id, '_barcode', true );
				if ( $barcode ) {
					printf(
						'<code style="font-size: 11px; background: #f0f0f0; padding: 2px 4px;">%s</code>',
						esc_html( $barcode )
					);
				} else {
					printf( '<em>%s</em>', esc_html__( 'Not generated', 'special-rate-shipping' ) );
				}
				break;

			case 'pouch_products':
				$product_ids = get_post_meta( $post_id, '_pouch_products', true );
				$package_type = get_post_meta( $post_id, '_package_type', true );
				
				if ( ! empty( $product_ids ) && is_array( $product_ids ) ) {
					$product_count = count( $product_ids );
					$package_types = array(
						'small_box' => __( 'S', 'special-rate-shipping' ),
						'medium_box' => __( 'M', 'special-rate-shipping' ),
						'big_box' => __( 'L', 'special-rate-shipping' ),
						'envelope' => __( 'E', 'special-rate-shipping' ),
						'flat_rate' => __( 'FR', 'special-rate-shipping' )
					);
					$package_short = isset( $package_types[ $package_type ] ) ? $package_types[ $package_type ] : '?';
					
					printf(
						'<div style="display: flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-products" style="color: #2271b1; font-size: 16px;"></span><strong>%d</strong> <span style="background: #f0f6fc; color: #2271b1; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">%s</span></div>',
						$product_count,
						esc_html( $package_short )
					);
					
					// Show first few product names as tooltip or small text
					$product_names = array();
					foreach ( array_slice( $product_ids, 0, 2 ) as $product_id ) {
						$product = wc_get_product( $product_id );
						if ( $product ) {
							$product_names[] = $product->get_name();
						}
					}
					if ( ! empty( $product_names ) ) {
						printf( '<small style="color: #646970; display: block; margin-top: 2px;">%s%s</small>', 
							esc_html( implode( ', ', $product_names ) ),
							$product_count > 2 ? '...' : ''
						);
					}
				} else {
					printf( '<em>%s</em>', esc_html__( 'No products', 'special-rate-shipping' ) );
				}
				break;
		}
	} // End populate_pouch_columns ()

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
			'dashicons-shopping-cart',
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

					// Get automatic vs manual pouches
					$automatic_pouches = get_posts( array(
						'post_type' => 'pouch',
						'posts_per_page' => -1,
						'post_status' => 'any',
						'meta_query' => array(
							array(
								'key' => '_order_id',
								'compare' => 'EXISTS'
							)
						),
						'fields' => 'ids'
					) );
					$automatic_count = count( $automatic_pouches );
					$manual_count = $total_pouches - $automatic_count;

					// Get status counts
					$status_counts = array(
						'new' => 0,
						'packed' => 0,
						'shipped' => 0,
						'delivered' => 0
					);

					if ( $total_pouches > 0 ) {
						global $wpdb;
						$results = $wpdb->get_results(
							"SELECT meta_value, COUNT(*) as count FROM {$wpdb->postmeta} pm 
							 JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
							 WHERE pm.meta_key = '_pouch_status' AND p.post_type = 'pouch' AND p.post_status != 'trash'
							 GROUP BY meta_value"
						);
						foreach ( $results as $result ) {
							if ( isset( $status_counts[ $result->meta_value ] ) ) {
								$status_counts[ $result->meta_value ] = $result->count;
							}
						}
					}
					?>
					<p><strong><?php printf( esc_html__( 'Total Pouches: %d', 'special-rate-shipping' ), $total_pouches ); ?></strong></p>
					<p><?php printf( esc_html__( 'Automatic: %d', 'special-rate-shipping' ), $automatic_count ); ?></p>
					<p><?php printf( esc_html__( 'Manual: %d', 'special-rate-shipping' ), $manual_count ); ?></p>
					<hr>
					<p><strong><?php esc_html_e( 'By Status:', 'special-rate-shipping' ); ?></strong></p>
					<p><?php printf( esc_html__( 'New: %d', 'special-rate-shipping' ), $status_counts['new'] ); ?></p>
					<p><?php printf( esc_html__( 'Packed: %d', 'special-rate-shipping' ), $status_counts['packed'] ); ?></p>
					<p><?php printf( esc_html__( 'Shipped: %d', 'special-rate-shipping' ), $status_counts['shipped'] ); ?></p>
					<p><?php printf( esc_html__( 'Delivered: %d', 'special-rate-shipping' ), $status_counts['delivered'] ); ?></p>
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

	/**
	 * Debug function to track order processing
	 * @access  public
	 * @since   2.0.0
	 * @param   int $order_id
	 * @param   array $posted_data
	 * @param   WC_Order $order
	 * @return  void
	 */
	public function debug_order_processed( $order_id, $posted_data, $order ) {
		error_log( sprintf( 
			'Special Rate Shipping Debug: Order processed hook fired for order %d', 
			$order_id 
		) );
	} // End debug_order_processed ()

	/**
	 * Debug function to track new orders
	 * @access  public
	 * @since   2.0.0
	 * @param   int $order_id
	 * @return  void
	 */
	public function debug_new_order( $order_id ) {
		error_log( sprintf( 
			'Special Rate Shipping Debug: New order hook fired for order %d', 
			$order_id 
		) );
	} // End debug_new_order ()

	/**
	 * Debug function to track thank you page
	 * @access  public
	 * @since   2.0.0
	 * @param   int $order_id
	 * @return  void
	 */
	public function debug_thankyou( $order_id ) {
		error_log( sprintf( 
			'Special Rate Shipping Debug: Thank you page for order %d', 
			$order_id 
		) );
	} // End debug_thankyou ()

	/**
	 * Debug function to track status changes
	 * @access  public
	 * @since   2.0.0
	 * @param   int $order_id
	 * @param   string $old_status
	 * @param   string $new_status
	 * @param   WC_Order $order
	 * @return  void
	 */
	public function debug_status_change( $order_id, $old_status, $new_status, $order ) {
		error_log( sprintf( 
			'Special Rate Shipping Debug: Order %d status changed from %s to %s', 
			$order_id, $old_status, $new_status
		) );
	} // End debug_status_change ()

	/**
	 * Alternative pouch creation from new order hook
	 * @access  public
	 * @since   2.0.0
	 * @param   int $order_id
	 * @return  void
	 */
	public function maybe_create_pouch_from_new_order( $order_id ) {
		$this->maybe_create_pouch_from_order( $order_id );
	} // End maybe_create_pouch_from_new_order ()

	/**
	 * Alternative pouch creation from thank you page
	 * @access  public
	 * @since   2.0.0
	 * @param   int $order_id
	 * @return  void
	 */
	public function maybe_create_pouch_from_thankyou( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$this->maybe_create_pouch_from_order( $order_id );
	} // End maybe_create_pouch_from_thankyou ()

}
