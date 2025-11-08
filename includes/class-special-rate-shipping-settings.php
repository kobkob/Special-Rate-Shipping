<?php
/**
 * Special Rate Shipping Settings
 *
 * Handles the plugin settings and configuration interface.
 *
 * @package KobKob\SpecialRateShipping
 * @author Monsenhor Ricardo Filipo <filipo@kobkob.org>
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Special_Rate_Shipping_Settings {

	/**
	 * The single instance of Special_Rate_Shipping_Settings.
	 * @var 	object
	 * @access  private
	 * @since 	2.0.0
	 */
	private static $_instance = null;

	/**
	 * The main plugin object.
	 * @var 	object
	 * @access  public
	 * @since 	2.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 * @var     string
	 * @access  public
	 * @since   2.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 * @var     array
	 * @access  public
	 * @since   2.0.0
	 */
	public $settings = array();

	public function __construct ( $parent ) {
		$this->parent = $parent;

		$this->base = 'srs_';

		// Initialise settings
		add_action( 'init', array( $this, 'init_settings' ), 11 );

		// Register plugin settings
		add_action( 'admin_init' , array( $this, 'register_settings' ) );

		// Add settings page to menu
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->parent->file ) , array( $this, 'add_settings_link' ) );
	}

	/**
	 * Initialise settings
	 * @return void
	 */
	public function init_settings () {
		$this->settings = $this->settings_fields();
	}

	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_item () {
		$page = add_options_page( __( 'Special Rate Shipping', 'special-rate-shipping' ) , __( 'Special Rate Shipping', 'special-rate-shipping' ) , 'manage_options' , $this->parent->_token . '_settings' ,  array( $this, 'settings_page' ) );
		add_action( 'admin_print_styles-' . $page, array( $this, 'settings_assets' ) );
	}

	/**
	 * Load settings JS & CSS
	 * @return void
	 */
	public function settings_assets () {
		// Load settings JavaScript for enhanced functionality
    	wp_register_script( $this->parent->_token . '-settings-js', $this->parent->assets_url . 'js/settings' . $this->parent->script_suffix . '.js', array( 'jquery' ), '2.0.0' );
    	wp_enqueue_script( $this->parent->_token . '-settings-js' );
	}

	/**
	 * Add settings link to plugin list table
	 * @param  array $links Existing links
	 * @return array 		Modified links
	 */
	public function add_settings_link ( $links ) {
		$settings_link = '<a href="options-general.php?page=' . $this->parent->_token . '_settings">' . __( 'Settings', 'special-rate-shipping' ) . '</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}

	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields () {

		$settings['api'] = array(
			'title'					=> __( 'API Configuration', 'special-rate-shipping' ),
			'description'			=> __( 'Configure API settings for shipping rate calculations and label generation.', 'special-rate-shipping' ),
			'fields'				=> array(
				array(
					'id' 			=> 'api_key',
					'label'			=> __( 'API Key' , 'special-rate-shipping' ),
					'description'	=> __( 'Enter your shipping carrier API key for rate calculations and label generation.', 'special-rate-shipping' ),
					'type'			=> 'text_secret',
					'default'		=> '',
					'placeholder'	=> __( 'Your API Key', 'special-rate-shipping' )
				),
				array(
					'id' 			=> 'api_environment',
					'label'			=> __( 'API Environment', 'special-rate-shipping' ),
					'description'	=> __( 'Select the API environment to use for shipping calculations.', 'special-rate-shipping' ),
					'type'			=> 'select',
					'options'		=> array( 
						'sandbox' => __( 'Sandbox (Testing)', 'special-rate-shipping' ), 
						'production' => __( 'Production (Live)', 'special-rate-shipping' ) 
					),
					'default'		=> 'sandbox'
				),
				array(
					'id' 			=> 'enable_debug',
					'label'			=> __( 'Enable Debug Mode', 'special-rate-shipping' ),
					'description'	=> __( 'Enable debug mode to log API requests and responses for troubleshooting.', 'special-rate-shipping' ),
					'type'			=> 'checkbox',
					'default'		=> ''
				)
			)
		);

		$settings['sender'] = array(
			'title'					=> __( 'Sender Information', 'special-rate-shipping' ),
			'description'			=> __( 'Configure your business information for shipping labels and rate calculations.', 'special-rate-shipping' ),
			'fields'				=> array(
				array(
					'id' 			=> 'sender_name',
					'label'			=> __( 'Company/Sender Name' , 'special-rate-shipping' ),
					'description'	=> __( 'Enter your company or sender name for shipping labels.', 'special-rate-shipping' ),
					'type'			=> 'text',
					'default'		=> get_bloginfo( 'name' ),
					'placeholder'	=> __( 'Your Company Name', 'special-rate-shipping' )
				),
				array(
					'id' 			=> 'sender_email',
					'label'			=> __( 'Contact Email' , 'special-rate-shipping' ),
					'description'	=> __( 'Enter your business contact email address.', 'special-rate-shipping' ),
					'type'			=> 'text',
					'default'		=> get_option( 'admin_email' ),
					'placeholder'	=> __( 'contact@yourcompany.com', 'special-rate-shipping' )
				),
				array(
					'id' 			=> 'sender_phone',
					'label'			=> __( 'Phone Number' , 'special-rate-shipping' ),
					'description'	=> __( 'Enter your business phone number for shipping purposes.', 'special-rate-shipping' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> __( '+1-555-123-4567', 'special-rate-shipping' )
				),
				array(
					'id' 			=> 'sender_address',
					'label'			=> __( 'Business Address' , 'special-rate-shipping' ),
					'description'	=> __( 'Enter your complete business address for shipping calculations and labels.', 'special-rate-shipping' ),
					'type'			=> 'textarea',
					'default'		=> '',
					'placeholder'	=> __( '123 Main Street\nCity, State 12345\nCountry', 'special-rate-shipping' )
				)
			)
		);

		$settings['packages'] = array(
			'title'					=> __( 'Package Settings', 'special-rate-shipping' ),
			'description'			=> __( 'Configure default package types and shipping rates for your products.', 'special-rate-shipping' ),
			'fields'				=> array(
				array(
					'id' 			=> 'small_box_rate',
					'label'			=> __( 'Small Box Rate' , 'special-rate-shipping' ),
					'description'	=> __( 'Default shipping rate for small packages.', 'special-rate-shipping' ),
					'type'			=> 'number',
					'default'		=> '6.35',
					'placeholder'	=> __( '6.35', 'special-rate-shipping' )
				),
				array(
					'id' 			=> 'medium_box_rate',
					'label'			=> __( 'Medium Box Rate' , 'special-rate-shipping' ),
					'description'	=> __( 'Default shipping rate for medium packages.', 'special-rate-shipping' ),
					'type'			=> 'number',
					'default'		=> '9.35',
					'placeholder'	=> __( '9.35', 'special-rate-shipping' )
				),
				array(
					'id' 			=> 'big_box_rate',
					'label'			=> __( 'Big Box Rate' , 'special-rate-shipping' ),
					'description'	=> __( 'Default shipping rate for large packages.', 'special-rate-shipping' ),
					'type'			=> 'number',
					'default'		=> '13.35',
					'placeholder'	=> __( '13.35', 'special-rate-shipping' )
				),
				array(
					'id' 			=> 'free_shipping_threshold',
					'label'			=> __( 'Free Shipping Threshold' , 'special-rate-shipping' ),
					'description'	=> __( 'Order total required for free shipping (leave empty to disable).', 'special-rate-shipping' ),
					'type'			=> 'number',
					'default'		=> '',
					'placeholder'	=> __( '100.00', 'special-rate-shipping' )
				),
				array(
					'id' 			=> 'enable_package_optimization',
					'label'			=> __( 'Enable Package Optimization', 'special-rate-shipping' ),
					'description'	=> __( 'Automatically optimize packaging to find the most cost-effective shipping solution.', 'special-rate-shipping' ),
					'type'			=> 'checkbox',
					'default'		=> 'on'
				)
			)
		);

		$settings = apply_filters( $this->parent->_token . '_settings_fields', $settings );

		return $settings;
	}

	/**
	 * Register plugin settings
	 * @return void
	 */
	public function register_settings () {
		if ( is_array( $this->settings ) ) {

			// Check posted/selected tab
			$current_section = '';
			if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = $_POST['tab'];
			} else {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = $_GET['tab'];
				}
			}

			foreach ( $this->settings as $section => $data ) {

				if ( $current_section && $current_section != $section ) continue;

				// Add section to page
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), $this->parent->_token . '_settings' );

				foreach ( $data['fields'] as $field ) {

					// Validation callback for field
					$validation = '';
					if ( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}

					// Register field
					$option_name = $this->base . $field['id'];
					register_setting( $this->parent->_token . '_settings', $option_name, $validation );

					// Add field to page
					add_settings_field( $field['id'], $field['label'], array( $this->parent->admin, 'display_field' ), $this->parent->_token . '_settings', $section, array( 'field' => $field, 'prefix' => $this->base ) );
				}

				if ( ! $current_section ) break;
			}
		}
	}

	public function settings_section ( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo $html;
	}

	/**
	 * Load settings page content
	 * @return void
	 */
	public function settings_page () {

		// Build page HTML
		$html = '<div class="wrap" id="' . $this->parent->_token . '_settings">' . "\n";
			$html .= '<h2>' . __( 'Special Rate Shipping Settings' , 'special-rate-shipping' ) . '</h2>' . "\n";

			$tab = '';
			if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
				$tab .= $_GET['tab'];
			}

			// Show page tabs
			if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

				$html .= '<h2 class="nav-tab-wrapper">' . "\n";

				$c = 0;
				foreach ( $this->settings as $section => $data ) {

					// Set tab class
					$class = 'nav-tab';
					if ( ! isset( $_GET['tab'] ) ) {
						if ( 0 == $c ) {
							$class .= ' nav-tab-active';
						}
					} else {
						if ( isset( $_GET['tab'] ) && $section == $_GET['tab'] ) {
							$class .= ' nav-tab-active';
						}
					}

					// Set tab link
					$tab_link = add_query_arg( array( 'tab' => $section ) );
					if ( isset( $_GET['settings-updated'] ) ) {
						$tab_link = remove_query_arg( 'settings-updated', $tab_link );
					}

					// Output tab
					$html .= '<a href="' . $tab_link . '" class="' . esc_attr( $class ) . '">' . esc_html( $data['title'] ) . '</a>' . "\n";

					++$c;
				}

				$html .= '</h2>' . "\n";
			}

			$html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

				// Get settings fields
				ob_start();
				settings_fields( $this->parent->_token . '_settings' );
				do_settings_sections( $this->parent->_token . '_settings' );
				$html .= ob_get_clean();

				$html .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings' , 'special-rate-shipping' ) ) . '" />' . "\n";
				$html .= '</p>' . "\n";
			$html .= '</form>' . "\n";
		$html .= '</div>' . "\n";

		echo $html;
	}

	/**
	 * Main Special_Rate_Shipping_Settings Instance
	 *
	 * Ensures only one instance of Special_Rate_Shipping_Settings is loaded or can be loaded.
	 *
	 * @since 2.0.0
	 * @static
	 * @see Special_Rate_Shipping()
	 * @return Main Special_Rate_Shipping_Settings instance
	 */
	public static function instance ( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 2.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->parent->_version );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 2.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->parent->_version );
	} // End __wakeup()

}