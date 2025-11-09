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

		// Load USPS API integration and Package Optimizer
		require_once( dirname( __FILE__ ) . '/class-usps-api.php' );
		require_once( dirname( __FILE__ ) . '/class-package-optimizer.php' );
		require_once( dirname( __FILE__ ) . '/class-optimized-shipping-method.php' );
		
		// Register optimized shipping method
		add_action( 'woocommerce_shipping_init', array( $this, 'init_optimized_shipping_method' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_optimized_shipping_method' ) );
		
		// Add label generation handlers
		add_action( 'admin_post_print_pouch_label', array( $this, 'handle_print_pouch_label' ) );
		add_action( 'admin_post_generate_usps_label', array( $this, 'handle_generate_usps_label' ) );

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
		$products_data = get_post_meta( $post->ID, '_pouch_products_data', true ) ?: array();
		$package_type = get_post_meta( $post->ID, '_package_type', true );
		$optimization_result = get_post_meta( $post->ID, '_optimization_result', true );
		$calculated_cost = get_post_meta( $post->ID, '_calculated_shipping_cost', true );
		$total_items = get_post_meta( $post->ID, '_total_items', true ) ?: 0;
		$total_weight = get_post_meta( $post->ID, '_total_weight', true ) ?: 0;
		$total_value = get_post_meta( $post->ID, '_total_value', true ) ?: 0;

		// Use detailed products data if available, fallback to product IDs
		$has_detailed_data = ! empty( $products_data ) && is_array( $products_data );
		$display_products = $has_detailed_data ? $products_data : $product_ids;

		// Display enhanced summary information
		if ( ! empty( $display_products ) && is_array( $display_products ) ) :
			$product_count = $has_detailed_data ? count( $products_data ) : count( $product_ids );
			$package_types = array(
				'auto' => __( 'Auto-Optimize', 'special-rate-shipping' ),
				'optimized' => __( 'Optimized Mix', 'special-rate-shipping' ),
				'small_box' => __( 'Small Box', 'special-rate-shipping' ),
				'medium_box' => __( 'Medium Box', 'special-rate-shipping' ),
				'big_box' => __( 'Big Box', 'special-rate-shipping' ),
				'envelope' => __( 'Envelope', 'special-rate-shipping' ),
				'flat_rate' => __( 'Flat Rate Box', 'special-rate-shipping' )
			);
			$package_label = isset( $package_types[ $package_type ] ) ? $package_types[ $package_type ] : __( 'Not specified', 'special-rate-shipping' );
			
			// Calculate totals if not stored
			if ( $has_detailed_data && ( $total_items == 0 || $total_weight == 0 ) ) {
				$total_items = 0;
				$total_weight = 0;
				$total_value = 0;
				foreach ( $products_data as $item ) {
					$product = wc_get_product( $item['product_id'] );
					if ( $product ) {
						$quantity = $item['quantity'] ?: 1;
						$weight = $item['weight'] ?: $product->get_weight() ?: 0;
						$total_items += $quantity;
						$total_weight += $weight * $quantity;
						$total_value += $product->get_price() * $quantity;
					}
				}
			}
			?>
			<!-- Enhanced Bootstrap Summary -->
			<div class="card border-primary mb-3">
				<div class="card-header bg-primary text-white">
					<h5 class="mb-0">
						<i class="dashicons dashicons-chart-bar" style="vertical-align: text-top;"></i>
						<?php esc_html_e( 'Pouch Summary', 'special-rate-shipping' ); ?>
					</h5>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-md-3 text-center">
							<div class="border rounded p-2">
								<i class="dashicons dashicons-products text-primary" style="font-size: 24px;"></i>
								<div class="fw-bold"><?php echo esc_html( $product_count ); ?></div>
								<small class="text-muted"><?php esc_html_e( 'Product Types', 'special-rate-shipping' ); ?></small>
							</div>
						</div>
						<div class="col-md-3 text-center">
							<div class="border rounded p-2">
								<i class="dashicons dashicons-list-view text-success" style="font-size: 24px;"></i>
								<div class="fw-bold"><?php echo esc_html( $total_items ); ?></div>
								<small class="text-muted"><?php esc_html_e( 'Total Items', 'special-rate-shipping' ); ?></small>
							</div>
						</div>
						<div class="col-md-3 text-center">
							<div class="border rounded p-2">
								<i class="dashicons dashicons-scale text-warning" style="font-size: 24px;"></i>
								<div class="fw-bold"><?php printf( '%.2f lbs', $total_weight ); ?></div>
								<small class="text-muted"><?php esc_html_e( 'Total Weight', 'special-rate-shipping' ); ?></small>
							</div>
						</div>
						<div class="col-md-3 text-center">
							<div class="border rounded p-2">
								<i class="dashicons dashicons-money-alt text-info" style="font-size: 24px;"></i>
								<div class="fw-bold"><?php echo wp_kses_post( wc_price( $total_value ) ); ?></div>
								<small class="text-muted"><?php esc_html_e( 'Total Value', 'special-rate-shipping' ); ?></small>
							</div>
						</div>
					</div>
					
					<!-- Package Type & Shipping Cost -->
					<div class="row mt-3">
						<div class="col-md-6">
							<div class="alert alert-info mb-0">
								<strong><?php esc_html_e( 'Package Type:', 'special-rate-shipping' ); ?></strong>
								<br><?php echo esc_html( $package_label ); ?>
							</div>
						</div>
						<?php if ( $calculated_cost > 0 ) : ?>
						<div class="col-md-6">
							<div class="alert alert-success mb-0">
								<strong><?php esc_html_e( 'Shipping Cost:', 'special-rate-shipping' ); ?></strong>
								<br><?php echo wp_kses_post( wc_price( $calculated_cost ) ); ?>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
				
				<?php if ( $optimization_result && is_array( $optimization_result ) && ! empty( $optimization_result['packages'] ) ) : ?>
					<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #c3d4e5;">
						<h5 style="margin: 0 0 8px 0; color: #1d2327; font-size: 13px;"><?php esc_html_e( 'Optimized Package Breakdown:', 'special-rate-shipping' ); ?></h5>
						<div style="display: flex; gap: 15px; flex-wrap: wrap;">
							<?php foreach ( $optimization_result['packages'] as $package ) : ?>
								<div style="background: #fff; padding: 8px 12px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px;">
									<strong><?php echo esc_html( $package['name'] ?? $package['type'] ); ?></strong><br>
									<span style="color: #646970;">
										<?php printf( esc_html__( '%d items, %.1f lbs', 'special-rate-shipping' ), $package['items_count'] ?? 0, $package['weight'] ?? 0 ); ?>
									</span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<?php
		endif;

		if ( ! empty( $display_products ) && is_array( $display_products ) ) :
			?>
			<!-- Product Details Table -->
		<div class="table-responsive">
			<table class="table table-striped table-hover">
				<thead class="table-light">
					<tr>
						<th><?php esc_html_e( 'Product', 'special-rate-shipping' ); ?></th>
						<th width="80"><?php esc_html_e( 'Qty', 'special-rate-shipping' ); ?></th>
						<th width="100"><?php esc_html_e( 'Unit Price', 'special-rate-shipping' ); ?></th>
						<th width="80"><?php esc_html_e( 'Weight', 'special-rate-shipping' ); ?></th>
						<th width="100"><?php esc_html_e( 'Total', 'special-rate-shipping' ); ?></th>
						<th width="80"><?php esc_html_e( 'Stock', 'special-rate-shipping' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php 
					if ( $has_detailed_data ) :
						// Display detailed products with quantities
						foreach ( $products_data as $item ) :
							$product = wc_get_product( $item['product_id'] );
							if ( $product ) :
								$quantity = $item['quantity'] ?: 1;
								$unit_weight = $item['weight'] ?: $product->get_weight() ?: 0;
								$unit_price = $product->get_price();
								$total_price = $unit_price * $quantity;
								?>
								<tr>
									<td>
										<div class="d-flex align-items-center">
											<?php if ( $product->get_image_id() ) : ?>
												<img src="<?php echo esc_url( wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ); ?>" 
													 class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" alt="">
											<?php endif; ?>
											<div>
												<strong><?php echo esc_html( $product->get_name() ); ?></strong>
												<?php if ( $product->get_sku() ) : ?>
													<br><small class="text-muted">SKU: <?php echo esc_html( $product->get_sku() ); ?></small>
												<?php endif; ?>
											</div>
										</div>
									</td>
									<td><span class="badge bg-primary"><?php echo esc_html( $quantity ); ?></span></td>
									<td><?php echo wp_kses_post( wc_price( $unit_price ) ); ?></td>
									<td><?php printf( '%.2f lbs', $unit_weight ); ?></td>
									<td><strong><?php echo wp_kses_post( wc_price( $total_price ) ); ?></strong></td>
									<td>
										<?php 
										if ( $product->is_in_stock() ) {
											if ( $product->managing_stock() ) {
												$stock_qty = $product->get_stock_quantity();
												if ( $stock_qty >= $quantity ) {
													echo '<span class="badge bg-success">' . esc_html( $stock_qty ) . '</span>';
												} else {
													echo '<span class="badge bg-warning">' . esc_html( $stock_qty ) . '</span>';
												}
											} else {
												echo '<span class="badge bg-success">' . esc_html__( '∞', 'special-rate-shipping' ) . '</span>';
											}
										} else {
											echo '<span class="badge bg-danger">' . esc_html__( 'Out', 'special-rate-shipping' ) . '</span>';
										}
										?>
									</td>
								</tr>
								<?php
							else :
								?>
								<tr>
									<td colspan="6" class="text-muted fst-italic">
										<?php printf( esc_html__( 'Product ID %d not found or deleted', 'special-rate-shipping' ), $item['product_id'] ); ?>
									</td>
								</tr>
								<?php
							endif;
						endforeach;
					else :
						// Fallback for legacy data without quantities
						foreach ( $product_ids as $product_id ) :
							$product = wc_get_product( $product_id );
							if ( $product ) :
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $product->get_name() ); ?></strong>
										<?php if ( $product->get_sku() ) : ?>
											<br><small class="text-muted">SKU: <?php echo esc_html( $product->get_sku() ); ?></small>
										<?php endif; ?>
									</td>
									<td><span class="badge bg-secondary">?</span></td>
									<td><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></td>
									<td><?php printf( '%.2f lbs', $product->get_weight() ?: 0 ); ?></td>
									<td>—</td>
									<td>
										<?php 
										if ( $product->is_in_stock() ) {
											echo '<span class="badge bg-success">' . esc_html__( 'In Stock', 'special-rate-shipping' ) . '</span>';
										} else {
											echo '<span class="badge bg-danger">' . esc_html__( 'Out', 'special-rate-shipping' ) . '</span>';
										}
										?>
									</td>
								</tr>
								<?php
							else :
								?>
								<tr>
									<td colspan="6" class="text-muted fst-italic">
										<?php printf( esc_html__( 'Product ID %d not found or deleted', 'special-rate-shipping' ), $product_id ); ?>
									</td>
								</tr>
								<?php
							endif;
						endforeach;
					endif;
					?>
				</tbody>
			</table>
		</div>
		<?php
		else :
			?>
			<div class="alert alert-info">
				<i class="dashicons dashicons-info" style="vertical-align: text-top;"></i>
				<?php esc_html_e( 'No products assigned to this pouch yet.', 'special-rate-shipping' ); ?>
			</div>
			<?php
		endif;
		
		// Add Bootstrap CSS for admin pages
		?>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
		<style>
		/* WordPress admin compatibility */
		.postbox .inside {
			padding: 0;
		}
		.card {
			border-color: #c3d4e5;
		}
		.text-primary { color: #2271b1 !important; }
		.text-success { color: #00a32a !important; }
		.text-warning { color: #dba617 !important; }
		.text-info { color: #72aee6 !important; }
		.bg-primary { background-color: #2271b1 !important; }
		.bg-success { background-color: #00a32a !important; }
		.bg-warning { background-color: #dba617 !important; }
		.bg-info { background-color: #72aee6 !important; }
		.alert {
			margin-bottom: 1rem;
		}
		.badge {
			font-size: 0.75em;
		}
		</style>
		<?php
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
			$usps_label_url = get_post_meta( $post->ID, '_usps_label_url', true );
			$tracking_number = get_post_meta( $post->ID, '_tracking_number', true );
			?>
			<div class="pouch-barcode-display">
				<p><strong><?php esc_html_e( 'Barcode:', 'special-rate-shipping' ); ?></strong></p>
				<div class="barcode-text"><?php echo esc_html( $barcode ); ?></div>
				
				<?php if ( $tracking_number ) : ?>
					<p><strong><?php esc_html_e( 'Tracking:', 'special-rate-shipping' ); ?></strong></p>
					<div class="tracking-text" style="font-family: 'Courier New', monospace; font-size: 14px; padding: 5px; background: #f9f9f9; border: 1px solid #ddd; margin: 5px 0;">
						<?php echo esc_html( $tracking_number ); ?>
					</div>
				<?php endif; ?>
				
				<p>
					<?php if ( $usps_label_url ) : ?>
						<button type="button" class="button button-primary" onclick="window.open('<?php echo esc_url( $usps_label_url ); ?>', '_blank')">
							<span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Download USPS Label', 'special-rate-shipping' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button button-secondary" onclick="generateUSPSLabel(<?php echo esc_js( $post->ID ); ?>)">
							<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Generate USPS Label', 'special-rate-shipping' ); ?>
						</button>
					<?php endif; ?>
					
					<button type="button" class="button" onclick="window.open('<?php echo esc_url( admin_url( 'admin-post.php?action=print_pouch_label&pouch_id=' . $post->ID ) ); ?>', '_blank')" style="margin-left: 10px;">
						<span class="dashicons dashicons-printer" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'Print Simple Label', 'special-rate-shipping' ); ?>
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
			<script>
			function generateUSPSLabel(pouchId) {
				if (!confirm('<?php esc_html_e( 'Generate USPS shipping label for this pouch? This may incur charges.', 'special-rate-shipping' ); ?>')) {
					return;
				}
				
				const button = event.target;
				const originalText = button.innerHTML;
				button.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> <?php esc_html_e( 'Generating...', 'special-rate-shipping' ); ?>';
				button.disabled = true;
				
				fetch('<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: 'action=generate_usps_label&pouch_id=' + pouchId + '&_wpnonce=<?php echo wp_create_nonce( 'generate_usps_label_' . $post->ID ); ?>'
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						location.reload(); // Reload to show new label
					} else {
						alert('<?php esc_html_e( 'Error:', 'special-rate-shipping' ); ?> ' + (data.data || '<?php esc_html_e( 'Unknown error occurred', 'special-rate-shipping' ); ?>'));
						button.innerHTML = originalText;
						button.disabled = false;
					}
				})
				.catch(error => {
					alert('<?php esc_html_e( 'Network error:', 'special-rate-shipping' ); ?> ' + error.message);
					button.innerHTML = originalText;
					button.disabled = false;
				});
			}
			</script>
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

		// Get order items (products) with quantities
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'product_id' => $item->get_product_id(),
				'quantity' => $item->get_quantity()
			);
		}

		// Get shipping address
		$shipping_address = $this->format_shipping_address( $order );
		$recipient_address = $this->parse_recipient_info( $shipping_address );

		// Initialize Package Optimizer
		$use_usps_rates = get_option( 'srs_enable_package_optimization', 'on' ) === 'on';
		$optimizer = new Package_Optimizer( $use_usps_rates );

		// Calculate optimal packaging
		$optimization_result = $optimizer->generate_pouch_configuration( $items, $recipient_address );

		// Create the pouch
		$pouch_title = sprintf(
			__( 'Order #%s Pouch', 'special-rate-shipping' ),
			$order->get_order_number()
		);

		$product_ids = array_column( $items, 'product_id' );
		$optimization_notes = sprintf(
			__( 'Auto-optimized: %d packages, $%.2f total cost. Primary package: %s', 'special-rate-shipping' ),
			$optimization_result['total_packages'],
			$optimization_result['total_cost'],
			$optimization_result['package_type']
		);

		$pouch_data = array(
			'post_title' => $pouch_title,
			'post_content' => '',
			'post_status' => 'draft',
			'post_type' => 'pouch',
			'meta_input' => array(
				'_pouch_products' => $product_ids,
				'_package_type' => $optimization_result['package_type'],
				'_recipient_info' => $shipping_address,
				'_pouch_notes' => $optimization_notes,
				'_pouch_status' => 'new',
				'_created_date' => current_time( 'mysql' ),
				'_barcode' => $this->generate_barcode(),
				'_order_id' => $order_id,
				'_order_total' => $order->get_total(),
				'_customer_id' => $order->get_customer_id(),
				'_optimization_result' => $optimization_result,
				'_calculated_shipping_cost' => $optimization_result['total_cost']
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
			'dashicons-products',
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
		// Enqueue Bootstrap CSS
		wp_enqueue_style( 'bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', array(), '5.3.2' );
		wp_enqueue_script( 'bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', array(), '5.3.2', true );
		
		?>
		<div class="wrap">
			<div class="container-fluid">
				<!-- Header Section -->
				<div class="row mb-4">
					<div class="col-12">
						<div class="d-flex justify-content-between align-items-center">
							<div>
								<h1 class="mb-0">
									<span class="dashicons dashicons-products me-2" style="font-size: 32px; vertical-align: middle;"></span>
									<?php esc_html_e( 'Special Rate System Dashboard', 'special-rate-shipping' ); ?>
								</h1>
								<p class="text-muted mb-0"><?php esc_html_e( 'Intelligent package optimization and shipping management', 'special-rate-shipping' ); ?></p>
							</div>
							<div>
								<span class="badge bg-success fs-6">v2.0</span>
							</div>
						</div>
					</div>
				</div>

				<!-- Quick Actions Section -->
				<div class="row mb-4">
					<div class="col-12">
						<div class="card border-0 shadow-sm">
							<div class="card-header bg-primary text-white">
								<h5 class="mb-0">
									<i class="dashicons dashicons-controls-forward" style="vertical-align: middle;"></i>
									<?php esc_html_e( 'Quick Actions', 'special-rate-shipping' ); ?>
								</h5>
							</div>
							<div class="card-body">
								<div class="row">
									<div class="col-md-6 col-lg-3 mb-3">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=special-rate-create-pouch' ) ); ?>" class="btn btn-success w-100 py-3">
											<i class="dashicons dashicons-plus-alt2" style="font-size: 20px;"></i><br>
											<?php esc_html_e( 'Create New Pouch', 'special-rate-shipping' ); ?>
										</a>
									</div>
									<div class="col-md-6 col-lg-3 mb-3">
										<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pouch' ) ); ?>" class="btn btn-outline-primary w-100 py-3">
											<i class="dashicons dashicons-archive" style="font-size: 20px;"></i><br>
											<?php esc_html_e( 'View All Pouches', 'special-rate-shipping' ); ?>
										</a>
									</div>
									<div class="col-md-6 col-lg-3 mb-3">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=special-rate-settings' ) ); ?>" class="btn btn-outline-secondary w-100 py-3">
											<i class="dashicons dashicons-admin-settings" style="font-size: 20px;"></i><br>
											<?php esc_html_e( 'Settings', 'special-rate-shipping' ); ?>
										</a>
									</div>
									<div class="col-md-6 col-lg-3 mb-3">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=special-rate-docs' ) ); ?>" class="btn btn-outline-info w-100 py-3">
											<i class="dashicons dashicons-book" style="font-size: 20px;"></i><br>
											<?php esc_html_e( 'Documentation', 'special-rate-shipping' ); ?>
										</a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Statistics Section -->
				<div class="row mb-4">
					<div class="col-12">
						<div class="card border-0 shadow-sm">
							<div class="card-header bg-info text-white">
								<h5 class="mb-0">
									<i class="dashicons dashicons-chart-area" style="vertical-align: middle;"></i>
									<?php esc_html_e( 'System Statistics', 'special-rate-shipping' ); ?>
								</h5>
							</div>
							<div class="card-body">
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

							<!-- Statistics Cards -->
							<div class="row g-3">
								<!-- Total Pouches Card -->
								<div class="col-md-6 col-xl-3">
									<div class="card text-white bg-primary h-100">
										<div class="card-body">
											<div class="d-flex justify-content-between">
												<div>
													<h3 class="card-title mb-1"><?php echo esc_html( $total_pouches ); ?></h3>
													<p class="card-text mb-0"><?php esc_html_e( 'Total Pouches', 'special-rate-shipping' ); ?></p>
												</div>
												<div class="align-self-start">
													<i class="dashicons dashicons-archive" style="font-size: 2.5rem;"></i>
												</div>
											</div>
										</div>
									</div>
								</div>

								<!-- Automatic Pouches Card -->
								<div class="col-md-6 col-xl-3">
									<div class="card text-white bg-success h-100">
										<div class="card-body">
											<div class="d-flex justify-content-between">
												<div>
													<h3 class="card-title mb-1"><?php echo esc_html( $automatic_count ); ?></h3>
													<p class="card-text mb-0"><?php esc_html_e( 'Automatic Pouches', 'special-rate-shipping' ); ?></p>
												</div>
												<div class="align-self-start">
													<i class="dashicons dashicons-update" style="font-size: 2.5rem;"></i>
												</div>
											</div>
										</div>
									</div>
								</div>

								<!-- Manual Pouches Card -->
								<div class="col-md-6 col-xl-3">
									<div class="card text-white bg-warning h-100">
										<div class="card-body">
											<div class="d-flex justify-content-between">
												<div>
													<h3 class="card-title mb-1"><?php echo esc_html( $manual_count ); ?></h3>
													<p class="card-text mb-0"><?php esc_html_e( 'Manual Pouches', 'special-rate-shipping' ); ?></p>
												</div>
												<div class="align-self-start">
													<i class="dashicons dashicons-edit" style="font-size: 2.5rem;"></i>
												</div>
											</div>
										</div>
									</div>
								</div>

								<!-- Package Optimization Card -->
								<div class="col-md-6 col-xl-3">
									<div class="card text-white bg-info h-100">
										<div class="card-body">
											<div class="d-flex justify-content-between">
												<div>
													<h3 class="card-title mb-1">v2.0</h3>
													<p class="card-text mb-0"><?php esc_html_e( 'Optimization Engine', 'special-rate-shipping' ); ?></p>
												</div>
												<div class="align-self-start">
													<i class="dashicons dashicons-chart-line" style="font-size: 2.5rem;"></i>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Status Breakdown -->
							<div class="row mt-4">
								<div class="col-12">
									<h6 class="mb-3"><?php esc_html_e( 'Pouch Status Breakdown', 'special-rate-shipping' ); ?></h6>
									<div class="row g-2">
										<div class="col-sm-6 col-lg-3">
											<div class="d-flex align-items-center">
												<span class="badge bg-secondary me-2"><?php echo esc_html( $status_counts['new'] ); ?></span>
												<span><?php esc_html_e( 'New', 'special-rate-shipping' ); ?></span>
											</div>
										</div>
										<div class="col-sm-6 col-lg-3">
											<div class="d-flex align-items-center">
												<span class="badge bg-info me-2"><?php echo esc_html( $status_counts['packed'] ); ?></span>
												<span><?php esc_html_e( 'Packed', 'special-rate-shipping' ); ?></span>
											</div>
										</div>
										<div class="col-sm-6 col-lg-3">
											<div class="d-flex align-items-center">
												<span class="badge bg-warning me-2"><?php echo esc_html( $status_counts['shipped'] ); ?></span>
												<span><?php esc_html_e( 'Shipped', 'special-rate-shipping' ); ?></span>
											</div>
										</div>
										<div class="col-sm-6 col-lg-3">
											<div class="d-flex align-items-center">
												<span class="badge bg-success me-2"><?php echo esc_html( $status_counts['delivered'] ); ?></span>
												<span><?php esc_html_e( 'Delivered', 'special-rate-shipping' ); ?></span>
											</div>
										</div>
									</div>
								</div>
							</div>
							</div>
						</div>
					</div>
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
			<!-- Bootstrap CDN -->
			<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
			<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

			<div class="wrap">
				<div class="container-fluid">
					<div class="row">
						<div class="col-12">
							<h1 class="mb-4">
								<i class="dashicons dashicons-plus-alt" style="vertical-align: text-top;"></i>
								<?php esc_html_e( 'Create New Pouch', 'special-rate-shipping' ); ?>
							</h1>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-8">
							<div class="card">
								<div class="card-header bg-primary text-white">
									<h5 class="mb-0">
										<i class="dashicons dashicons-archive" style="vertical-align: text-top;"></i>
										<?php esc_html_e( 'Pouch Configuration', 'special-rate-shipping' ); ?>
									</h5>
								</div>
								<div class="card-body">
									<form method="post" action="" class="srs-create-pouch-form">
										<?php wp_nonce_field( 'create_pouch_action', 'pouch_nonce' ); ?>
										
										<div class="row mb-4">
											<div class="col-12">
												<div class="mb-3">
													<label for="pouch_title" class="form-label fw-bold">
														<i class="dashicons dashicons-tag" style="vertical-align: text-top;"></i>
														<?php esc_html_e( 'Pouch Title', 'special-rate-shipping' ); ?>
													</label>
													<input type="text" id="pouch_title" name="pouch_title" class="form-control" required>
													<div class="form-text"><?php esc_html_e( 'Enter a descriptive title for this pouch', 'special-rate-shipping' ); ?></div>
												</div>
											</div>
										</div>

										<div class="row mb-4">
											<div class="col-12">
												<div class="mb-3">
													<label class="form-label fw-bold">
														<i class="dashicons dashicons-products" style="vertical-align: text-top;"></i>
														<?php esc_html_e( 'Products & Quantities', 'special-rate-shipping' ); ?>
													</label>
													<div id="product-list-container">
														<!-- Product items will be dynamically added here -->
													</div>
													<button type="button" class="btn btn-outline-success btn-sm mt-2" id="add-product-btn">
														<i class="dashicons dashicons-plus-alt" style="font-size: 14px; vertical-align: text-top;"></i>
														<?php esc_html_e( 'Add Product', 'special-rate-shipping' ); ?>
													</button>
													<div class="form-text"><?php esc_html_e( 'Add products and specify quantities for this pouch.', 'special-rate-shipping' ); ?></div>
												</div>
											</div>
										</div>

										<div class="row mb-4">
											<div class="col-12">
												<div class="mb-3">
													<label for="package_type" class="form-label fw-bold">
														<i class="dashicons dashicons-building" style="vertical-align: text-top;"></i>
														<?php esc_html_e( 'Package Optimization', 'special-rate-shipping' ); ?>
													</label>
													<select id="package_type" name="package_type" class="form-select" required>
														<option value="auto"><?php esc_html_e( 'Auto-Optimize (Recommended)', 'special-rate-shipping' ); ?></option>
														<option value="small_box"><?php esc_html_e( 'Force Small Box', 'special-rate-shipping' ); ?></option>
														<option value="medium_box"><?php esc_html_e( 'Force Medium Box', 'special-rate-shipping' ); ?></option>
														<option value="big_box"><?php esc_html_e( 'Force Big Box', 'special-rate-shipping' ); ?></option>
														<option value="envelope"><?php esc_html_e( 'Force Envelope', 'special-rate-shipping' ); ?></option>
														<option value="flat_rate"><?php esc_html_e( 'Force Flat Rate Box', 'special-rate-shipping' ); ?></option>
													</select>
													<div class="form-text"><?php esc_html_e( 'Choose auto-optimize for best shipping cost, or select a specific package type', 'special-rate-shipping' ); ?></div>
												</div>
											</div>
										</div>

										<div class="row mb-4">
											<div class="col-12">
												<div class="mb-3">
													<label for="recipient_info" class="form-label fw-bold">
														<i class="dashicons dashicons-location-alt" style="vertical-align: text-top;"></i>
														<?php esc_html_e( 'Recipient Information', 'special-rate-shipping' ); ?>
													</label>
													<textarea id="recipient_info" name="recipient_info" class="form-control" rows="4" 
															  placeholder="Name&#10;Address Line 1&#10;Address Line 2&#10;City, State ZIP"></textarea>
													<div class="form-text"><?php esc_html_e( 'Enter recipient shipping address (optional for draft pouches)', 'special-rate-shipping' ); ?></div>
												</div>
											</div>
										</div>

										<div class="row mb-4">
											<div class="col-12">
												<div class="mb-3">
													<label for="pouch_notes" class="form-label fw-bold">
														<i class="dashicons dashicons-sticky" style="vertical-align: text-top;"></i>
														<?php esc_html_e( 'Notes', 'special-rate-shipping' ); ?>
													</label>
													<textarea id="pouch_notes" name="pouch_notes" class="form-control" rows="3"></textarea>
													<div class="form-text"><?php esc_html_e( 'Optional notes about this pouch', 'special-rate-shipping' ); ?></div>
												</div>
											</div>
										</div>
										
										<div class="d-grid gap-2 d-md-flex justify-content-md-end">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=special-rate-system' ) ); ?>" class="btn btn-outline-secondary me-md-2">
												<i class="dashicons dashicons-arrow-left-alt" style="font-size: 14px; vertical-align: text-top;"></i>
												<?php esc_html_e( 'Cancel', 'special-rate-shipping' ); ?>
											</a>
											<button type="submit" name="create_pouch" class="btn btn-primary btn-lg">
												<i class="dashicons dashicons-yes-alt" style="font-size: 18px; vertical-align: text-top;"></i>
												<?php esc_html_e( 'Create Optimized Pouch', 'special-rate-shipping' ); ?>
											</button>
										</div>
									</form>
								</div>
							</div>
						</div>

						<!-- Info Panel -->
						<div class="col-lg-4">
							<div class="card mb-4">
								<div class="card-header bg-success text-white">
									<h6 class="mb-0">
										<i class="dashicons dashicons-chart-line" style="vertical-align: text-top;"></i>
										<?php esc_html_e( 'Package Optimization Engine', 'special-rate-shipping' ); ?>
									</h6>
								</div>
								<div class="card-body">
									<p class="small text-muted mb-3">
										<?php esc_html_e( 'Our optimization engine automatically selects the most cost-effective packaging combination from these USPS options:', 'special-rate-shipping' ); ?>
									</p>
									<div class="list-group list-group-flush">
										<div class="list-group-item px-0 py-2 border-0">
											<div class="d-flex align-items-center">
												<span class="badge bg-primary me-2">S</span>
												<div>
													<small class="fw-bold d-block">Small Box</small>
													<small class="text-muted">8.5×5.5×1.6 in, up to 4 lbs</small>
												</div>
											</div>
										</div>
										<div class="list-group-item px-0 py-2 border-0">
											<div class="d-flex align-items-center">
												<span class="badge bg-info me-2">M</span>
												<div>
													<small class="fw-bold d-block">Medium Box</small>
													<small class="text-muted">11×8.5×5.5 in, up to 20 lbs</small>
												</div>
											</div>
										</div>
										<div class="list-group-item px-0 py-2 border-0">
											<div class="d-flex align-items-center">
												<span class="badge bg-warning me-2">L</span>
												<div>
													<small class="fw-bold d-block">Large Box</small>
													<small class="text-muted">12×12×5.5 in, up to 70 lbs</small>
												</div>
											</div>
										</div>
										<div class="list-group-item px-0 py-2 border-0">
											<div class="d-flex align-items-center">
												<span class="badge bg-secondary me-2">E</span>
												<div>
													<small class="fw-bold d-block">Envelope</small>
													<small class="text-muted">12.5×9.5×0.75 in, up to 4 lbs</small>
												</div>
											</div>
										</div>
										<div class="list-group-item px-0 py-2 border-0">
											<div class="d-flex align-items-center">
												<span class="badge bg-danger me-2">F</span>
												<div>
													<small class="fw-bold d-block">Flat Rate</small>
													<small class="text-muted">12×12×8 in, up to 70 lbs</small>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="card">
								<div class="card-header bg-info text-white">
									<h6 class="mb-0">
										<i class="dashicons dashicons-lightbulb" style="vertical-align: text-top;"></i>
										<?php esc_html_e( 'Smart Features', 'special-rate-shipping' ); ?>
									</h6>
								</div>
								<div class="card-body">
									<div class="mb-2">
										<i class="dashicons dashicons-yes-alt text-success" style="font-size: 14px;"></i>
										<small><?php esc_html_e( 'Multi-package optimization', 'special-rate-shipping' ); ?></small>
									</div>
									<div class="mb-2">
										<i class="dashicons dashicons-yes-alt text-success" style="font-size: 14px;"></i>
										<small><?php esc_html_e( 'Weight & dimension analysis', 'special-rate-shipping' ); ?></small>
									</div>
									<div class="mb-2">
										<i class="dashicons dashicons-yes-alt text-success" style="font-size: 14px;"></i>
										<small><?php esc_html_e( 'Cost minimization', 'special-rate-shipping' ); ?></small>
									</div>
									<div class="mb-0">
										<i class="dashicons dashicons-yes-alt text-success" style="font-size: 14px;"></i>
										<small><?php esc_html_e( 'USPS label generation', 'special-rate-shipping' ); ?></small>
									</div>
								</div>
							</div>
						</div>
						</div>
				</div>
			</div>
			
			<!-- Product Selection Script -->
			<script>
			const products = <?php echo json_encode( array_map( function( $product ) {
				return [
					'id' => $product->get_id(),
					'name' => $product->get_name(),
					'price' => $product->get_price(),
					'sku' => $product->get_sku(),
					'weight' => $product->get_weight() ?: 0,
					'stock_quantity' => $product->get_stock_quantity() ?: 999
				];
			}, $products ) ); ?>;
			
			let productCount = 0;
			
			document.addEventListener('DOMContentLoaded', function() {
				const addProductBtn = document.getElementById('add-product-btn');
				const productContainer = document.getElementById('product-list-container');
				
				// Add first product row by default
				addProductRow();
				
				addProductBtn.addEventListener('click', function() {
					addProductRow();
				});
				
				function addProductRow() {
					const row = document.createElement('div');
					row.className = 'product-row card border mb-3';
					row.innerHTML = `
						<div class="card-body">
							<div class="row align-items-end">
								<div class="col-md-6">
									<label class="form-label small fw-bold"><?php esc_html_e( 'Product', 'special-rate-shipping' ); ?></label>
									<select name="products[${productCount}][product_id]" class="form-select product-select" required>
										<option value=""><?php esc_html_e( 'Select a product...', 'special-rate-shipping' ); ?></option>
										${products.map(p => `<option value="${p.id}" data-price="${p.price}" data-weight="${p.weight}" data-stock="${p.stock_quantity}">${p.name} - $${p.price} ${p.sku ? '(' + p.sku + ')' : ''}</option>`).join('')}
									</select>
								</div>
								<div class="col-md-2">
									<label class="form-label small fw-bold"><?php esc_html_e( 'Quantity', 'special-rate-shipping' ); ?></label>
									<input type="number" name="products[${productCount}][quantity]" class="form-control quantity-input" min="1" value="1" required>
								</div>
								<div class="col-md-2">
									<label class="form-label small fw-bold"><?php esc_html_e( 'Unit Weight', 'special-rate-shipping' ); ?></label>
									<input type="number" name="products[${productCount}][weight]" class="form-control weight-input" step="0.01" min="0" placeholder="0.00" readonly>
								</div>
								<div class="col-md-2">
									<button type="button" class="btn btn-outline-danger btn-sm remove-product-btn w-100" onclick="removeProductRow(this)">
										<i class="dashicons dashicons-trash" style="font-size: 14px; vertical-align: text-top;"></i>
										<?php esc_html_e( 'Remove', 'special-rate-shipping' ); ?>
									</button>
								</div>
							</div>
							<div class="row mt-2">
								<div class="col-12">
									<div class="product-info alert alert-info d-none" style="font-size: 12px; padding: 8px;"></div>
								</div>
							</div>
						</div>
					`;
					
					productContainer.appendChild(row);
					
					// Add event listener for product selection
					const productSelect = row.querySelector('.product-select');
					const weightInput = row.querySelector('.weight-input');
					const quantityInput = row.querySelector('.quantity-input');
					const productInfo = row.querySelector('.product-info');
					
					productSelect.addEventListener('change', function() {
						const selectedOption = this.options[this.selectedIndex];
						if (selectedOption.value) {
							const price = selectedOption.getAttribute('data-price');
							const weight = selectedOption.getAttribute('data-weight');
							const stock = selectedOption.getAttribute('data-stock');
							
							weightInput.value = weight;
							quantityInput.max = stock;
							
							productInfo.innerHTML = `
								<strong><?php esc_html_e( 'Product Info:', 'special-rate-shipping' ); ?></strong> 
								$${price} each, ${weight}lbs unit weight, ${stock} in stock
							`;
							productInfo.classList.remove('d-none');
						} else {
							weightInput.value = '';
							productInfo.classList.add('d-none');
						}
						updateTotalSummary();
					});
					
					quantityInput.addEventListener('change', updateTotalSummary);
					
					productCount++;
					updateRemoveButtons();
				}
				
				window.removeProductRow = function(btn) {
					const row = btn.closest('.product-row');
					row.remove();
					updateRemoveButtons();
					updateTotalSummary();
				};
				
				function updateRemoveButtons() {
					const rows = document.querySelectorAll('.product-row');
					rows.forEach((row, index) => {
						const removeBtn = row.querySelector('.remove-product-btn');
						// Always allow removal if there's more than one row
						removeBtn.style.display = rows.length > 1 ? 'block' : 'none';
					});
				}
				
				function updateTotalSummary() {
					const rows = document.querySelectorAll('.product-row');
					let totalItems = 0;
					let totalWeight = 0;
					let totalValue = 0;
					
					rows.forEach(row => {
						const select = row.querySelector('.product-select');
						const quantity = parseInt(row.querySelector('.quantity-input').value) || 0;
						
						if (select.value && quantity > 0) {
							const option = select.options[select.selectedIndex];
							const price = parseFloat(option.getAttribute('data-price')) || 0;
							const weight = parseFloat(option.getAttribute('data-weight')) || 0;
							
							totalItems += quantity;
							totalWeight += weight * quantity;
							totalValue += price * quantity;
						}
					});
					
					// Update or create summary display
					let summary = document.getElementById('products-summary');
					if (!summary) {
						summary = document.createElement('div');
						summary.id = 'products-summary';
						summary.className = 'alert alert-secondary mt-3';
						document.getElementById('product-list-container').parentNode.appendChild(summary);
					}
					
					summary.innerHTML = `
						<strong><?php esc_html_e( 'Pouch Summary:', 'special-rate-shipping' ); ?></strong> 
						${totalItems} <?php esc_html_e( 'items', 'special-rate-shipping' ); ?>, 
						${totalWeight.toFixed(2)} <?php esc_html_e( 'lbs total weight', 'special-rate-shipping' ); ?>, 
						$${totalValue.toFixed(2)} <?php esc_html_e( 'total value', 'special-rate-shipping' ); ?>
					`;
				}
			});
			</script>
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
		$package_type = sanitize_text_field( $_POST['package_type'] );
		$recipient_info = sanitize_textarea_field( $_POST['recipient_info'] );
		$notes = sanitize_textarea_field( $_POST['pouch_notes'] );
		
		// Process products with quantities
		$products_data = array();
		$product_ids = array(); // For backward compatibility
		
		if ( isset( $_POST['products'] ) && is_array( $_POST['products'] ) ) {
			foreach ( $_POST['products'] as $product_item ) {
				if ( isset( $product_item['product_id'] ) && !empty( $product_item['product_id'] ) ) {
					$product_id = intval( $product_item['product_id'] );
					$quantity = intval( $product_item['quantity'] ) ?: 1;
					$weight = floatval( $product_item['weight'] ) ?: 0;
					
					// Validate product exists
					if ( wc_get_product( $product_id ) ) {
						$products_data[] = array(
							'product_id' => $product_id,
							'quantity' => $quantity,
							'weight' => $weight
						);
						$product_ids[] = $product_id; // For backward compatibility
					}
				}
			}
		}

		if ( empty( $title ) || empty( $package_type ) || empty( $products_data ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Title, Package Type, and at least one product are required.', 'special-rate-shipping' ) . '</p></div>';
			} );
			return;
		}

		// Calculate totals for optimization
		$total_weight = 0;
		$total_value = 0;
		$total_items = 0;
		
		foreach ( $products_data as $product_item ) {
			$product = wc_get_product( $product_item['product_id'] );
			if ( $product ) {
				$quantity = $product_item['quantity'];
				$weight = $product_item['weight'] ?: $product->get_weight();
				
				$total_items += $quantity;
				$total_weight += $weight * $quantity;
				$total_value += $product->get_price() * $quantity;
			}
		}
		
		// Run package optimization if auto-optimize is selected
		$optimization_result = null;
		$calculated_cost = 0;
		
		if ( $package_type === 'auto' && class_exists( 'Package_Optimizer' ) ) {
			try {
				$optimizer = new Package_Optimizer();
				$optimization_result = $optimizer->optimize_packages( $products_data, array(
					'weight' => $total_weight,
					'value' => $total_value,
					'items' => $total_items
				) );
				
				if ( $optimization_result && isset( $optimization_result['total_cost'] ) ) {
					$calculated_cost = $optimization_result['total_cost'];
					$package_type = 'optimized'; // Mark as optimized
				}
			} catch ( Exception $e ) {
				// Log error but continue with manual package type
				error_log( 'Package optimization failed: ' . $e->getMessage() );
			}
		}

		// Create the pouch post
		$pouch_data = array(
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'draft',
			'post_type' => 'pouch',
			'meta_input' => array(
				'_pouch_products' => $product_ids, // Backward compatibility
				'_pouch_products_data' => $products_data, // New detailed structure
				'_package_type' => $package_type,
				'_recipient_info' => $recipient_info,
				'_pouch_notes' => $notes,
				'_pouch_status' => 'new',
				'_created_date' => current_time( 'mysql' ),
				'_barcode' => $this->generate_barcode(),
				'_total_items' => $total_items,
				'_total_weight' => $total_weight,
				'_total_value' => $total_value,
				'_optimization_result' => $optimization_result,
				'_calculated_shipping_cost' => $calculated_cost
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

	/**
	 * Handle USPS label generation request
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function handle_generate_usps_label() {
		// Verify nonce and permissions
		if ( ! isset( $_POST['_wpnonce'] ) || ! isset( $_POST['pouch_id'] ) ) {
			wp_die( 'Invalid request', 'Error', array( 'response' => 400 ) );
		}
		
		$pouch_id = intval( $_POST['pouch_id'] );
		
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'generate_usps_label_' . $pouch_id ) ) {
			wp_die( 'Security check failed', 'Error', array( 'response' => 403 ) );
		}
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Insufficient permissions', 'Error', array( 'response' => 403 ) );
		}
		
		// Get pouch data
		$pouch = get_post( $pouch_id );
		if ( ! $pouch || $pouch->post_type !== 'pouch' ) {
			wp_send_json_error( 'Invalid pouch ID' );
			return;
		}
		
		// Generate USPS label
		$result = $this->generate_usps_label_for_pouch( $pouch_id );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		} else {
			wp_send_json_success( $result );
		}
	} // End handle_generate_usps_label ()

	/**
	 * Generate USPS label for a pouch
	 * @access  public
	 * @since   2.0.0
	 * @param   int $pouch_id
	 * @return  array|WP_Error
	 */
	public function generate_usps_label_for_pouch( $pouch_id ) {
		// Get plugin settings
		$api_key = get_option( 'srs_usps_api_key' );
		$api_secret = get_option( 'srs_usps_api_secret' );
		$environment = get_option( 'srs_usps_environment', 'sandbox' );
		$debug = get_option( 'srs_debug_mode', false );
		
		if ( empty( $api_key ) || empty( $api_secret ) ) {
			return new WP_Error( 'missing_credentials', 'USPS API credentials not configured. Please check plugin settings.' );
		}
		
		// Initialize USPS API
		$usps_api = new USPS_API( $api_key, $api_secret, $environment, $debug );
		
		// Get pouch data
		$order_id = get_post_meta( $pouch_id, '_order_id', true );
		$product_ids = get_post_meta( $pouch_id, '_pouch_products', true );
		$package_type = get_post_meta( $pouch_id, '_package_type', true );
		$recipient_info = get_post_meta( $pouch_id, '_recipient_info', true );
		
		// Get sender information from settings
		$sender_data = array(
			'first_name' => get_option( 'srs_sender_first_name', '' ),
			'last_name' => get_option( 'srs_sender_last_name', '' ),
			'company' => get_option( 'srs_sender_company', '' ),
			'address_1' => get_option( 'srs_sender_address_1', '' ),
			'address_2' => get_option( 'srs_sender_address_2', '' ),
			'city' => get_option( 'srs_sender_city', '' ),
			'state' => get_option( 'srs_sender_state', '' ),
			'postcode' => get_option( 'srs_sender_postcode', '' )
		);
		
		// Validate sender data
		if ( empty( $sender_data['address_1'] ) || empty( $sender_data['city'] ) || empty( $sender_data['state'] ) || empty( $sender_data['postcode'] ) ) {
			return new WP_Error( 'missing_sender_info', 'Sender address information not complete. Please check plugin settings.' );
		}
		
		// Parse recipient information
		$recipient_data = $this->parse_recipient_info( $recipient_info );
		
		if ( is_wp_error( $recipient_data ) ) {
			return $recipient_data;
		}
		
		// Get package dimensions based on package type
		$package_dimensions = $this->get_package_dimensions( $package_type );
		
		// Calculate package weight from products
		$total_weight = $this->calculate_package_weight( $product_ids );
		
		// Prepare label data
		$label_data = array(
			'from_address' => $sender_data,
			'to_address' => $recipient_data,
			'package' => array_merge( $package_dimensions, array( 'weight' => $total_weight ) ),
			'service_type' => 'GROUND_ADVANTAGE', // Default service
			'label_format' => 'PDF',
			'tracking' => true
		);
		
		// Generate label via USPS API
		$label_response = $usps_api->create_label( $label_data );
		
		if ( is_wp_error( $label_response ) ) {
			return $label_response;
		}
		
		// Store label information in pouch meta
		if ( isset( $label_response['labelImage'] ) ) {
			update_post_meta( $pouch_id, '_usps_label_url', $label_response['labelImage'] );
		}
		
		if ( isset( $label_response['trackingNumber'] ) ) {
			update_post_meta( $pouch_id, '_tracking_number', $label_response['trackingNumber'] );
		}
		
		if ( isset( $label_response['postage'] ) ) {
			update_post_meta( $pouch_id, '_postage_cost', $label_response['postage'] );
		}
		
		update_post_meta( $pouch_id, '_label_generated_date', current_time( 'mysql' ) );
		update_post_meta( $pouch_id, '_usps_label_response', $label_response );
		
		// Update pouch status
		update_post_meta( $pouch_id, '_pouch_status', 'packed' );
		
		return array(
			'success' => true,
			'tracking_number' => isset( $label_response['trackingNumber'] ) ? $label_response['trackingNumber'] : '',
			'label_url' => isset( $label_response['labelImage'] ) ? $label_response['labelImage'] : '',
			'postage_cost' => isset( $label_response['postage'] ) ? $label_response['postage'] : ''
		);
	} // End generate_usps_label_for_pouch ()

	/**
	 * Parse recipient information string into address array
	 * @access  private
	 * @since   2.0.0
	 * @param   string $recipient_info
	 * @return  array|WP_Error
	 */
	private function parse_recipient_info( $recipient_info ) {
		if ( empty( $recipient_info ) ) {
			return new WP_Error( 'missing_recipient', 'No recipient information available' );
		}
		
		// Try to parse the formatted address string
		// This is a simplified parser - in production you might want more sophisticated parsing
		$lines = explode( "\n", trim( $recipient_info ) );
		
		if ( count( $lines ) < 3 ) {
			return new WP_Error( 'invalid_recipient', 'Recipient address format is invalid' );
		}
		
		$name_line = trim( $lines[0] );
		$address_line = trim( $lines[1] );
		$city_state_zip = trim( $lines[count($lines) - 1] );
		
		// Parse name (assume first word is first name, rest is last name)
		$name_parts = explode( ' ', $name_line, 2 );
		$first_name = isset( $name_parts[0] ) ? $name_parts[0] : '';
		$last_name = isset( $name_parts[1] ) ? $name_parts[1] : '';
		
		// Parse city, state, zip
		if ( preg_match( '/^(.+),\s*([A-Z]{2})\s+([0-9]{5}(-[0-9]{4})?)$/', $city_state_zip, $matches ) ) {
			$city = trim( $matches[1] );
			$state = trim( $matches[2] );
			$postcode = trim( $matches[3] );
		} else {
			return new WP_Error( 'invalid_address_format', 'Cannot parse city, state, zip from address' );
		}
		
		return array(
			'first_name' => $first_name,
			'last_name' => $last_name,
			'company' => '',
			'address_1' => $address_line,
			'address_2' => '',
			'city' => $city,
			'state' => $state,
			'postcode' => $postcode
		);
	} // End parse_recipient_info ()

	/**
	 * Get package dimensions based on package type
	 * @access  private
	 * @since   2.0.0
	 * @param   string $package_type
	 * @return  array
	 */
	private function get_package_dimensions( $package_type ) {
		$dimensions = array(
			'small_box' => array( 'length' => 8, 'width' => 6, 'height' => 4 ),
			'medium_box' => array( 'length' => 12, 'width' => 8, 'height' => 6 ),
			'big_box' => array( 'length' => 16, 'width' => 12, 'height' => 8 ),
			'envelope' => array( 'length' => 12, 'width' => 9, 'height' => 1 ),
			'flat_rate' => array( 'length' => 12, 'width' => 8, 'height' => 6 )
		);
		
		return isset( $dimensions[ $package_type ] ) ? $dimensions[ $package_type ] : $dimensions['medium_box'];
	} // End get_package_dimensions ()

	/**
	 * Calculate total package weight from product IDs
	 * @access  private
	 * @since   2.0.0
	 * @param   array $product_ids
	 * @return  float
	 */
	private function calculate_package_weight( $product_ids ) {
		$total_weight = 0;
		
		if ( ! empty( $product_ids ) && is_array( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product && $product->has_weight() ) {
					$total_weight += floatval( $product->get_weight() );
				}
			}
		}
		
		// Default minimum weight if no product weights
		return $total_weight > 0 ? $total_weight : 1.0;
	} // End calculate_package_weight ()

	/**
	 * Handle simple label printing (existing functionality)
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function handle_print_pouch_label() {
		if ( ! isset( $_GET['pouch_id'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Invalid request', 'Error', array( 'response' => 400 ) );
		}
		
		$pouch_id = intval( $_GET['pouch_id'] );
		$pouch = get_post( $pouch_id );
		
		if ( ! $pouch || $pouch->post_type !== 'pouch' ) {
			wp_die( 'Invalid pouch', 'Error', array( 'response' => 404 ) );
		}
		
		// Simple label printing HTML output
		$barcode = get_post_meta( $pouch_id, '_barcode', true );
		$package_type = get_post_meta( $pouch_id, '_package_type', true );
		$recipient_info = get_post_meta( $pouch_id, '_recipient_info', true );
		
		header( 'Content-Type: text/html' );
		echo '<!DOCTYPE html><html><head><title>Pouch Label</title><style>body{font-family:Arial,sans-serif;padding:20px;}.label{border:2px solid #000;padding:15px;max-width:400px;}.barcode{font-family:monospace;font-size:18px;font-weight:bold;text-align:center;margin:10px 0;padding:10px;border:1px solid #333;}</style></head><body>';
		echo '<div class="label">';
		echo '<h2>Shipping Label</h2>';
		echo '<p><strong>Pouch ID:</strong> ' . esc_html( $pouch_id ) . '</p>';
		echo '<p><strong>Package Type:</strong> ' . esc_html( ucwords( str_replace( '_', ' ', $package_type ) ) ) . '</p>';
		echo '<div class="barcode">' . esc_html( $barcode ) . '</div>';
		echo '<p><strong>Ship To:</strong></p>';
		echo '<div style="white-space: pre-line;">' . esc_html( $recipient_info ) . '</div>';
		echo '</div>';
		echo '<script>window.print();</script>';
		echo '</body></html>';
		exit;
	} // End handle_print_pouch_label ()

	/**
	 * Initialize optimized shipping method
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function init_optimized_shipping_method() {
		// Class is already loaded via require_once above
	} // End init_optimized_shipping_method ()

	/**
	 * Add optimized shipping method to WooCommerce
	 * @access  public
	 * @since   2.0.0
	 * @param   array $methods
	 * @return  array
	 */
	public function add_optimized_shipping_method( $methods ) {
		$methods['optimized_special_rate'] = 'Optimized_Shipping_Method';
		return $methods;
	} // End add_optimized_shipping_method ()

}
