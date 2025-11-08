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

		// Load meta box for pouchs.
		add_action( 'load-post.php', array( $this, 'pouch_meta_boxes_setup' ));
		add_action( 'load-post-new.php', array( $this, 'pouch_meta_boxes_setup' ));

		// Add admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

	} // End __construct ()


	/* Meta box setup function. */
	function pouch_meta_boxes_setup() {

		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( $this, 'pouch_add_post_meta_boxes' ));
	}

	/* Create the meta boxes to be displayed on the post editor screen. */
	function pouch_add_post_meta_boxes() {

		// Puch class define the state of the pouch:
		// New, Packed, Delivered, Received
		add_meta_box(
				'pouch-post-class',      // Unique ID
				esc_html__( 'Pouch ', 'textdomain' ),    // Title
				array($this,'pouch_post_class_meta_box'),   // Callback function
				'pouch',         // Admin page (or post type)
				'normal',         // Context
				'default',         // Priority
				array('bla'=>'ble','bli'=>'blo')           // Data for the $args in Callback function
				);
	}

	/* Display the post meta box. */
	function pouch_post_class_meta_box( $post, $args ) { ?>

		<?php wp_nonce_field( basename( __FILE__ ), 'pouch_post_class_nonce' ); ?>

			<p>
			<label for="pouch-post-class"><?php _e( "Add a custom CSS class, which will be applied to WordPress' post class.", 'textdomain' ); ?></label>
			<br />
			<input class="widefat" type="text" name="pouch-post-class" id="pouch-post-class" value="<?php echo esc_attr( get_post_meta( $post->ID, 'pouch_post_class', true ) ); ?>" size="30" />
			</p>
			<?php }

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
						'name' => __( 'Pouchs' ),
						'singular_name' => __( 'Pouch' ),
						'edit_item' => __( 'Edit Pouch' ),
						'add_new_item' => __( 'Add Pouch' ),
						'new_item' => __( 'New Pouch' ),
						'add_new' => __( 'Add new Pouch' ),
						),
					'public' => false,
					'menu_position' => 58,
					'show_ui' => true,
					'show_in_menu' => true,
					'has_archive' => true,
					'menu_icon'=> 'dashicons-products',
					'rewrite' => array( 'slug' => 'pouchs' ),
					//'supports' => array('title','editor','thumbnail','custom-fields','comments','revisions'),
					'supports' => array('title'),
					)
				);
	} // End create_post_types ()

	/**
	 * Add admin menu item for Special Rate Shipping settings
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Special Rate Shipping', 'special-rate-shipping' ),
			__( 'Special Rate Shipping', 'special-rate-shipping' ),
			'manage_woocommerce',
			'special-rate-shipping',
			array( $this, 'admin_menu_redirect' )
		);
	} // End add_admin_menu ()

	/**
	 * Redirect to WooCommerce shipping settings for Special Rate Shipping
	 * @access  public
	 * @since   2.0.0
	 * @return  void
	 */
	public function admin_menu_redirect() {
		wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=special_rate_shipping_method' ) );
		exit;
	} // End admin_menu_redirect ()

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
