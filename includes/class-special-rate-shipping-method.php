<?php
/**
 * Special Rate Shipping Method
 * 
 * @package Special_Rate_Shipping
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if WC_Shipping_Method exists before extending
if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

function special_rate_shipping_method_init() {
	if ( ! class_exists( 'Special_Rate_Shipping_Method' ) ) {
		class Special_Rate_Shipping_Method extends WC_Shipping_Method {
				/**
				 * Constructor for Special Rate shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					$this->id                 = 'special_rate_shipping_method'; // Id for your shipping method. Should be uunique.
					$this->method_title       = __( 'Special Rate Shipping' );  // Title shown in admin
					$this->method_description = __( 'Shipping by product and quantity' ); // Description shown in admin

					$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
					$this->title              = "Special Rate"; // This can be added as an setting but for this example its forced.
					$this->plugin_id          = 'woocommerce_'.$this->id;

					$this->init();
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields(); // TODO This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // TODO This is part of the settings API. Loads settings you previously init.

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
					//add_filter( 'woocommerce_get_settings_shipping', array( $this, 'special_rate_pkg_settings', 10, 2 ));
					//add_filter( 'woocommerce_get_sections_shipping', array($this, 'special_rate_packages_section' ));
					//add_filter( 'woocommerce_get_sections_shipping', array($this, 'special_rate_packages_section' ));

				}

				/**
				 * Extra Section Form Fields for Packages
				 */
				function special_rate_packages_section( $sections ) {
					$sections['special_rate_pkg'] = __( 'Special Rate Packages', 'text-domain' );
					return $sections;
				}

				/**
				 * After Initialise Gateway Settings
				 function init_settings() {
				 echo "<div style='position:absolute;z-index:9999999999;background:#FFF'>";
				 var_dump(woocommerce_admin_fields());
				 echo "</div>";
				 }
				 */

				/**
				 * Initialise Gateway Settings Form Fields
				 */
	function init_form_fields() {
		$fields = array(
				'default_rate' => array(
					'title' => __( 'Default Rate', 'woocommerce' ),
					'type' => 'price',
					'description' => __( 'Rate to be used if have no other.', 'woocommerce' ),
					'default' => __( '6.35', 'woocommerce' ),
					'placeholder' => '6.35'
					),

				'line1' => array(
					'title' => __( 'HR1', 'woocommerce' ),
					'type' => 'hr',
					),

				'packages' => array(
					'title' => __( 'Special Packages', 'woocommerce' ),
					'type' => 'title',
					'description' => __( 'Fill product shipping details', 'woocommerce' ),
					),
				'pk1' => array(
					'title' => __( 'Small Box', 'woocommerce' ),
					'type' => 'title',
					'description' => __( 'Small Recipient', 'woocommerce' ),
					),
				'rate_pk1' => array(
						'title' => __( 'Rate', 'woocommerce' ),
						'type' => 'price',
						'description' => __( 'Rate for Small Box.', 'woocommerce' ),
						'default' => __( '6.35', 'woocommerce' ),
						'placeholder' => '6.35'
						),

				'pk2' => array(
						'title' => __( 'Medium Box', 'woocommerce' ),
						'type' => 'title',
						'description' => __( 'Normal Recipient', 'woocommerce' ),
						),
				'rate_pk2' => array(
						'title' => __( 'Rate', 'woocommerce' ),
						'type' => 'price',
						'description' => __( 'Rate for Medium Box.', 'woocommerce' ),
						'default' => __( '9.35', 'woocommerce' ),
						'placeholder' => '9.35'
						),

				'pk3' => array(
						'title' => __( 'Big Box', 'woocommerce' ),
						'type' => 'title',
						'description' => __( 'Big Recipient', 'woocommerce' ),
						),
				'rate_pk3' => array(
						'title' => __( 'Rate', 'woocommerce' ),
						'type' => 'price',
						'description' => __( 'Rate for Big Box.', 'woocommerce' ),
						'default' => __( '13.35', 'woocommerce' ),
						'placeholder' => '13.35'
						),

				'line2'=>array('title'=>__('HR2','woocommerce'),'type'=>'hr'),

				'classes' => array(
						'title' => __( 'Shipping Classes', 'woocommerce' ),
						'type' => 'title',
						'description' => __( 'Fill product shipping details', 'woocommerce' ),
						),
				);
					foreach (WC()->shipping->get_shipping_classes() as $cid => $class){
						$fields[$class->term_id] = array (
								'title' => __($class->name,'woocommerce'),
								'type' => 'title',
								'description' => __($class->description, 'woocommerce'),
								);
						$fields[$class->term_id.'_small'] = array (
								'title' => __('Small package','woocommerce'),
								'type' => 'checkbox',
								'description' => __('Small box is used on '.$class->name, 'woocommerce'),
								);
						$fields[$class->term_id.'_max_per_pack_small'] = array (
								'title' => __('Small Box max items for '.$class->name,'woocommerce'),
								'type' => 'text',
								'description' => __('Max items in each Small Box for '.$class->name, 'woocommerce'),
								);
						$fields[$class->term_id.'_medium'] = array (
								'title' => __('Medium package','woocommerce'),
								'type' => 'checkbox',
								'description' => __('Medium box is used on '.$class->name, 'woocommerce'),
								);
						$fields[$class->term_id.'_max_per_pack_medium'] = array (
								'title' => __('Medium Box max items for '.$class->name,'woocommerce'),
								'type' => 'text',
								'description' => __('Max items in Medium Box for '.$class->name, 'woocommerce'),
								);
						$fields[$class->term_id.'_big'] = array (
								'title' => __('Big package','woocommerce'),
								'type' => 'checkbox',
								'description' => __('Big box is used on '.$class->name, 'woocommerce'),
								);
						$fields[$class->term_id.'_max_per_pack_big'] = array (
								'title' => __('Big Box max items for '.$class->name,'woocommerce'),
								'type' => 'text',
								'description' => __('Max items in Big Box for '.$class->name, 'woocommerce'),
								);
						$fields[$class->term_id.'_line'] = array('title'=>__('HR'),'type'=>'hr');
					}
					$this->form_fields = $fields;
				} // End init_form_fields()


				/**
				 * Add settings to the packages section
				 */
				function special_rate_pkg_settings( $settings, $current_section ) {
					/**
					 * Check the current section is what we want
					 **/
					if ( $current_section == 'special_rate_pkg' ) {
						$settings_slider = array();
						// Add Title to the Settings
						$settings_slider[] = array( 'name' => __( 'Packages for Special Rate', 'text-domain' ), 'type' => 'title', 'desc' => __( 'The following options are used to configure the Packages', 'text-domain' ), 'id' => 'special_rate_pkg' );
						// Add first checkbox option
						$settings_slider[] = array(
								'name'     => __( 'Auto-insert into single product page', 'text-domain' ),
								'desc_tip' => __( 'This will automatically insert your slider into the single product page', 'text-domain' ),
								'id'       => 'special_rate_pkg_auto_insert',
								'type'     => 'checkbox',
								'css'      => 'min-width:300px;',
								'desc'     => __( 'Enable Auto-Insert', 'text-domain' ),
								);
						// Add second text field option
						$settings_slider[] = array(
								'name'     => __( 'Slider Title', 'text-domain' ),
								'desc_tip' => __( 'This will add a title to your slider', 'text-domain' ),
								'id'       => 'special_rate_pkg_title',
								'type'     => 'text',
								'desc'     => __( 'Any title you want can be added to your slider with this option!', 'text-domain' ),
								);

						$settings_slider[] = array( 'type' => 'sectionend', 'id' => 'special_rate_pkg' );
						return $settings_slider;

						/**
						 * If not, return the standard settings
						 **/
					} else {
						return $settings;
					}
				}

				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package = array() ) {
					// The pouch is the shipping recipient for all packages
					$pouch = array();
					// load default rate from settings
					$rate = $this->settings['default_rate']; 

					// get items from cart
					$items = array_keys( $package['contents'] );
					//$items = array_keys( WC()->cart->get_cart() );
					//var_dump($items);
					$cost = 0;
					//$cost = $rate;
					// Define user set variables
					//$this->title = $this->settings['title'];
					//$this->description = $this->settings['description'];
					//var_dump($this->settings); // die();
					//var_dump($package); // die();
					// shipping classes
					// var_dump(WC()->shipping->get_shipping_classes());
					//var_dump(WC()->cart->get_shipping_packages());

					// TODO load the packages from Settings
					$packages = array (
							'small' => (object) array(
								'name' => "Small Box",
								'rate' => $this->settings['rate_pk1']
								),
							'medium' => (object) array(
								'name' => "Medium Box",
								'rate' => $this->settings['rate_pk2']
								),
							'big' => (object) array(
								'name' => "Big Box",
								'rate' => $this->settings['rate_pk3']
								)

							);
					// load products shipping classes from settings
					foreach (WC()->shipping->get_shipping_classes() as $sp){
						$prod_sh_class[$sp->term_id] = (object) array(
								'slug'=>$sp->slug,
								'packages' => array (
									(object) array(
										'id' =>  'small',
										'enable' => $this->settings[$sp->term_id.'_small'],
										'name' => $packages['small']->name,
										'rate' => $packages['small']->rate,
										'max_per_pack' => $this->settings[$sp->term_id.'_max_per_pack_small'] -1 
										),
									(object) array(
										'id' =>  'medium',
										'enable' => $this->settings[$sp->term_id.'_medium'],
										'name' => $packages['medium']->name,
										'rate' => $packages['medium']->rate,
										'max_per_pack' => $this->settings[$sp->term_id.'_max_per_pack_medium'] -1 
										),
									(object) array(
										'id' =>  'big',
										'enable' => $this->settings[$sp->term_id.'_big'],
										'name' => $packages['big']->name,
										'rate' => $packages['big']->rate,
										'max_per_pack' => $this->settings[$sp->term_id.'_max_per_pack_big']-1
										)
										)
										);
					}
					foreach ( $items as $id => $cid ){
						$item = (object) WC()->cart->get_cart_item($cid);
						//var_dump($item);
						// get product object
						$_pf = new WC_Product_Factory();
						$_prod = $_pf->get_product($item->product_id);
						//$class_id = $item->data->shipping_class_id;
						$class_id = $_prod->get_shipping_class_id();
						//var_dump($prod_sh_class[$class_id]);
						$qty = $item->quantity;
						$price = 0;
						$tobag = $qty;
						$bagid = null;
						$class = null;
						while ($tobag > 0){
							//echo "<h2>To bag: ".$tobag." items of product #".$item->product_id."</h2>";
							//var_dump($item);
							// select the shippest package for $tobag
							foreach ($prod_sh_class[$class_id]->packages as $pkid=>$pk){
								$rest = 0;
								if($pk->enable == "yes"){
									$si = $pk->max_per_pack + 1;
									if ($tobag > $si){
										$rest = $tobag % $si;
									}
									//echo "<p>That package accepts up tp ".$si." items. We have ".$tobag." items to bag.</p>";
									//var_dump($pk);
									$units = (int) floor($tobag/$si);
									if ($units == 0 && $tobag < $si){
										$units = 1; 
									}
									if ($price == 0 || $price >  $pk->rate * $units){
										$price = $pk->rate * $units;
										$bagid = $pkid;
										//echo "<p>If we put in ".$units." ".$pk->name." pks, will rest ".$rest." and will price: $".$price."</p>"; 
									}
								}
							}
							// Choose package
							//echo "<h2>Package choosed: ".$bagid."</h2>";
							$pkg = $prod_sh_class[$class_id]->packages[$bagid];
							//var_dump($pkg);
							// Packing items
							//echo "<p>Packing ".$tobag." items</p>";
							$qty = $tobag;
							$price = 0;
							if ($pkg){
								// it can be a rest to bag
								if ( $tobag < $item->quantity) {
									if ($tobag > ($pkg->max_per_pack + 1)){ // more than 1
										$tobag = $tobag % ($pkg->max_per_pack + 1);
									} else {
										$tobag = 0;
									} 
									// it is the first package for this item
								} else { 
									if ($tobag > ($pkg->max_per_pack + 1)){
										$tobag = $tobag % ($pkg->max_per_pack + 1);
										//echo "<p>First pack. Still rest ".$tobag." items.";
									} else { // everything fit on this pack
										$tobag = 0;
									} 
								}
								$units = ((int) ($qty/($pkg->max_per_pack + 1)));
								if ($units == 0){$units = 1;}
								//echo "<p>Packed! Max: ".($pkg->max_per_pack +1).", To bag: ".$qty.", rest ".$tobag.", using ".$units. units."</p>";
								// put bag in pouch
								//   prepare recipient
								$recipient = (object) array(
										'name'=>$pkg->name,
										'rate'=>$pkg->rate,
										'load'=>$item->quantity/($pkg->max_per_pack + 1),
										'units'=> $units,
										'products'=>array(
											(object) array(
												'item_id'=>$item->product_id,
												'qty'=>$item->quantity,
												), 
											),
										);
								array_push ($pouch, $recipient);

							} else {$tobag = 0;} // its an error ... hmmm ...
						}

					}

					//echo "<pre>";
					//var_dump($pouch);
					//echo "</pre>";

					foreach ($pouch as $pid=>$recp){
						$fee = $recp->units * $recp->rate;
						$cost += $fee;
					}

					if ($cost == 0){$cost = $rate;}

					$rate = array(
							'id' => $this->id,
							'label' => $this->title,
							'cost' => $cost,
							'calc_tax' => 'per_order'
							);

					// Register the rate
					$this->add_rate( $rate );

					/*
						 $prod_sh_class = array (
						 17 => (object) [
						 'slug'=>'simple',
						 'packages' => array (
						 (object) [
						 'id' =>  'small',
						 'name' => $packages['small']->name,
						 'rate' => $packages['small']->rate,
						 'max_per_pack' => 5
						 ],
						 (object) [
						 'id' =>  'big',
						 'name' => $packages['big']->name,
						 'rate' => $packages['big']->rate,
						 'max_per_pack' => 12
						 ]
						 )
						 ],
						 18 => (object) [
						 'slug'=>'complex',
						 'packages' => array (
						 (object) [
						 'id' =>  'big',
						 'name' => $packages['big']->name,
						 'rate' => $packages['big']->rate,
						 'max_per_pack' => 3
						 ]
						 )
						 ],
						 );

					// do while have items to put in the pouch
					while ($items){
					// choose the shippest package for that item
					$bg_item = (object)[];
					$bg_id = null;
					$bg_pk =  (object)[];
					$n = 0;
					foreach ( $items as $id => $cid ){
					$item = (object) WC()->cart->get_cart_item($cid);
					//$class_id = 18; 
					$class_id = $item->data->shipping_class_id;
					$qty = $item->quantity;
					$price = 0;
					foreach ($prod_sh_class[$class_id]->packages as $pk){
					if ($pk->max_per_pack && $pk->enable == 'yes' && $qty/$pk->max_per_pack > $n){
					$n = $qty/$pk->max_per_pack;
					$bg_item = $item;
					$bg_id = $id;
					$bg_pk = $pk;
					}
					}
					}
					// remove bg from items
					$bg_class = array_splice($items,$bg_id,1);
					//var_dump($items);
					//var_dump($bg_item);
					//var_dump($bg_pk);

					// TODO get the smalest space enough in the pouch
					$p = null;
					foreach ($pouch as $pid => $pk){
					$p=$pid;
					}
					// if does not exist space enough in pouch, insert a new recipient
					if (!$p){
					$class_id = $bg_item->data->shipping_class_id;
					// prepare recipient:
					$recipient = (object)[
					'name'=>$bg_pk->name,
					'rate'=>$bg_pk->rate,
						'load'=>$bg_item->quantity/$bg_pk->max_per_pack,
						'units'=>(int) ($bg_item->quantity/$bg_pk->max_per_pack)+1,
						'products'=>array(
								(object) [
								'item_id'=>$bg_item->product_id,
								'qty'=>$bg_item->quantity,
								], 
								),
						];
					array_push ($pouch, $recipient);
				} else {
					// merge bg_item with pouch recipient

				}
				// put item
				//var_dump($pouch);
				}
				*/



				} // end claculate_shiping

				/**
				 * Generate HR HTML.
				 *
				 * @access public
				 * @param mixed $key
				 * @param mixed $data
				 * @since 1.0.0
				 * @return string
				 */
				public function generate_hr_html( $key, $data ) {
					$field    = $this->plugin_id . $this->id . '_' . $key;
					$defaults = array(
							'class'             => 'button-secondary',
							'css'               => '',
							'custom_attributes' => array(),
							'desc_tip'          => false,
							'description'       => '',
							'title'             => '',
							);

					$data = wp_parse_args( $data, $defaults );

					ob_start();
					?>
						<tr><td colspan="2"><hr></td></tr>
						<?php
						return ob_get_clean();
				}





				/**
				 * Generate Button HTML.
				 *
				 * @access public
				 * @param mixed $key
				 * @param mixed $data
				 * @since 1.0.0
				 * @return string
				 */
				public function generate_button_html( $key, $data ) {
					$field    = $this->plugin_id . $this->id . '_' . $key;
					$defaults = array(
							'class'             => 'button-secondary',
							'css'               => '',
							'custom_attributes' => array(),
							'desc_tip'          => false,
							'description'       => '',
							'title'             => '',
							);

					$data = wp_parse_args( $data, $defaults );

					ob_start();
					?>
						<tr valign="top">
						<th scope="row" class="titledesc">
						<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
						<?php echo $this->get_tooltip_html( $data ); ?>
						</th>
						<td class="forminp">
						<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
						<button class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
						<?php echo $this->get_description_html( $data ); ?>
						</fieldset>
						</td>
						</tr>
						<?php
						return ob_get_clean();
				}


			} // end main class
		} // end if

} // end shipping_method_init function

add_action( 'woocommerce_shipping_init', 'special_rate_shipping_method_init' );

function add_special_rate_shipping_method( $methods ) {
	$methods['special_rate_shipping_method'] = 'Special_Rate_Shipping_Method';
	$methods['special_rate_shipping_enhanced_method'] = 'Special_Rate_Shipping_Enhanced_Method';
	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_special_rate_shipping_method' );

add_filter( 'woocommerce_cart_totals_before_shipping',  'flush_the_cache', 1, 1 );
function flush_the_cache(){
	wp_cache_flush();
}

//add_filter( 'woocommerce_get_settings_pages',  'special_rate_pkg_settings', 2, 1 );
//add_filter( 'woocommerce_get_sections_shipping',  'special_rate_pkg_settings', 1, 1 );
