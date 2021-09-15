<?php
/**
 * LPost_WC setup
 *
 * @package LPost_WC
 * @since   0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main LPost_WC Class.
 *
 * @class LPost_WC
 */
class LPost_WC {
	/**
	 * @var LPost_WC_Shipping_Method
	 */
	private $helper;
	/**
	 * @var void
	 */
	public $secret;

	/**
	 * LPost_WC constructor.
	 */
	public function __construct() {
		include_once __DIR__.'/lpost-wc-helper.php';
		$this->helper = new LPost_WC_Helper();
		$this->secret = get_option('lpost_wc_api_secret', '');

		add_filter( 'woocommerce_get_sections_shipping', array( $this, 'settings_page' ) );
		add_filter( 'woocommerce_get_settings_shipping', array( $this, 'settings' ), 10, 2 );
		
		//add_filter( 'woocommerce_get_sections_shipping', array( $this, 'lpost_wc_invoice_page' ) );
		//add_filter( 'woocommerce_get_settings_shipping', array( $this, 'invoice_settings' ), 10, 2 );
			
		
		//add_action('admin_menu', array('LPost_WC', 'add_menu'));

		$this->init_hooks();
	}

	/**
	 * Hook into actions and filters.
	 */
	public function init_hooks() 
	{
			
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'woocommerce_shipping_init', array( $this, 'init_method' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_method' ) );
		
		// Добавляем админ меню и ссылку настроек
		add_filter( 'plugin_action_links_' . plugin_basename( __DIR__.'/lpost-wc-delivery.php' ), array( $this, 'plugin_action_links' ) ); 
		add_action( 'admin_menu', array($this, 'add_menu'));
		
		// Добавить в body CSS класс
		add_filter( 'admin_body_class', array($this, 'add_admin_body_class') );
		
		add_action( 'woocommerce_checkout_fields', array($this, 'shipping_checkout_fields') );
		add_action( 'woocommerce_after_order_notes', array($this, 'courier_checkout_fields') );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array($this, 'courier_checkout_fields_admin'));
		
		//add_filter( 'auto_update_plugin', array( $this, 'auto_update_plugin' ), 10, 2 );
		//add_action( 'woocommerce_debug_tools', array( $this, 'add_debug_tools' ) );
		//add_action( 'admin_head', array( $this, 'add_admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'addAdminScriptLP' ) );
		add_action( 'woocommerce_after_shipping_rate', array( $this, 'add_delivery_type' ), 10, 2 );
		
		// метабокс заказа
		add_action( 'add_meta_boxes', array( $this, 'add_meta_lpost_wc_box' ), 10, 2 );
		// add_action( 'woocommerce_process_shop_order_meta', array( $this, 'saveLabOrderMeta' ), 0, 2 );

		add_action( 'wp_ajax_lpcalcrequest', array($this, 'lpCalcRequest') );
		add_action( 'wp_ajax_createInvoice', array($this, 'lpCreateInvoice') );
		add_action( 'wp_ajax_nopriv_lpcalcrequest', array($this, 'lpCalcRequest') );
		// hide order item meta.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_itemmeta' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_meta_info_alt' ) );
		add_action( 'woocommerce_update_order', array( $this, 'updateWooOrder' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'checkNecFields' ) );
		add_action('init', function () { //кнопка обновления пунктов доставки
		    if (!isset($_GET['task'])) return true;
		    if ($_GET['task'] === 'updatePickupPoints') {
		        $token = $this->helper->getAuthToken();
		        $this->helper->GetPickupPoints($token, 'pickup', true);
		        $this->helper->GetPickupPoints($token, 'courier', true);
            };
        });
	}
    

	//Добавляем раздел в админку 
	public function add_menu()
	{
		add_submenu_page( 'woocommerce', 'Накладные доставки Лабиринт Пост' , 'Накладные ЛП', 'manage_woocommerce', 'lpost_wc-invoice', array( $this, 'invoice_settings_page') );
    }

	public function addAdminScriptLP() 
	{
		wp_enqueue_script('lpost-wc-helper-admin', plugin_dir_url(__FILE__).'/assets/js/helper-admin.js', array('jquery'));
		
		if (!empty($_GET['post']))
		{
			$map_api = get_option( 'lpost_wc_yandex_api' );
			if ( $map_api ) 
			{
				wp_enqueue_script( 'yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=' . esc_attr( $map_api ) . '&lang=ru_RU', array(), '2.1', false );
			}
		}
		
		wp_enqueue_script('lpost-wc-blockUI-js', plugin_dir_url(__FILE__).'/assets/js/jquery.blockUI.js', array('jquery'));
		wp_enqueue_script('lpost-wc-colorbox-js', plugin_dir_url(__FILE__).'/assets/js/colorbox/jquery.colorbox-min.js', array('jquery'));
		wp_enqueue_style('lpost-wc-colorbox-css', plugin_dir_url(__FILE__).'/assets/js/colorbox/colorbox.css');
		wp_enqueue_style('lpost-wc-helper-css', plugin_dir_url(__FILE__).'/assets/css/style-admin.css', false, '1.1');
		return true;
    }

    public function checkNecFields() 
	{
	    if (isset($_POST['delivery_date'])) 
		{
	        // if (!$_POST['courier_coords']) wc_add_notice('Пожалуйста, укажите адрес доставки на карте', 'error' );
        }
    }

	/**
	 * Обновить заказ вукомерса
	 * @param $order_id
	 */
	public function updateWooOrder($order_id) {

	    $order = wc_get_order($order_id);
		$shipping_methods = $order->get_shipping_methods();

		if ( ! $shipping_methods ) {
			return;
		}

		foreach ( $shipping_methods as $shipping ) 
		{
			if ($shipping->get_method_id() !== 'lpost_wc_shipping') 
			{
				return;
			}
		}
		$this->save_meta_info_alt($order_id);

	    if ($shipment_id = get_post_meta($order_id, 'shipment_id', true)) {
	    	$response = $this->helper->updateOrder($order_id);
	    } else {
	    	$response = $this->helper->createOrders(array($order_id));
	    }

	    foreach ($response as $key=>$resp) {
	    	if (!$shipment_id and isset($resp->ID_Order)) update_post_meta($order_id, 'shipment_id', $resp->ID_Order);
			if (!empty($resp->AddToAct)) update_post_meta($order_id, 'actBeforeTime', $resp->AddToAct);
	        if (!$resp->Message or $key !== 0 or $resp->Message === 'Заказ уже существует') break;
	        $this->add_admin_notice('Ошибка '.(!$shipment_id?'создания':'обновления').' отправления для заказа <a href="post.php?post='.$order_id.'&action=edit">'.$order_id.'</a>: '.$resp->Message, 'error');
        }

    }

    public function save_meta_info_alt($order_id) 
	{

		if (isset($_POST['courier_coords'])) 
			update_post_meta( $order_id, 'courier_coords', sanitize_text_field($_POST['courier_coords']));

		if (isset($_POST['deliv_type'])) 
			update_post_meta( $order_id, 'deliv_type', sanitize_text_field($_POST['deliv_type']));

		if (isset($_POST['issue_type'])) 
			update_post_meta( $order_id, 'issue_type', sanitize_text_field($_POST['issue_type']));

		if (isset($_POST['receive_id_warehouse'])) 
			update_post_meta( $order_id, 'receive_id_warehouse', sanitize_text_field($_POST['receive_id_warehouse']));
		
		if (isset($_POST['delivery_date'])) 
			update_post_meta( $order_id, 'delivery_date', sanitize_text_field($_POST['delivery_date']));
		
		if (isset($_POST['delivery_interval'])) 
			update_post_meta( $order_id, 'delivery_interval', sanitize_text_field($_POST['delivery_interval']));
		
		if (isset($_POST['pickuppoint_id'])) 
			update_post_meta( $order_id, 'pickuppoint_id', sanitize_text_field($_POST['pickuppoint_id']));
		
		// Номер подъезда
		if (isset($_POST['_lp_courier_porch'])) 
			update_post_meta( $order_id, '_lp_courier_porch', sanitize_text_field($_POST['_lp_courier_porch']));
		
		// Номер этажа
		if (isset($_POST['_lp_courier_floor'])) 
			update_post_meta( $order_id, '_lp_courier_floor', sanitize_text_field($_POST['_lp_courier_floor']));
		
		// Квартира/офис
		if (isset($_POST['_lp_courier_flat'])) 
			update_post_meta( $order_id, '_lp_courier_flat', sanitize_text_field($_POST['_lp_courier_flat']));
		
		// Код домофона
		if (isset($_POST['_lp_courier_code'])) 
			update_post_meta( $order_id, '_lp_courier_code', sanitize_text_field($_POST['_lp_courier_code']));
    }

	public function add_meta_lpost_wc_box( $post_type, $post ) {
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		$order            = wc_get_order( $post );
		$shipping_methods = $order->get_shipping_methods();

		if ( ! $shipping_methods ) {
			return;
		}

		foreach ( $shipping_methods as $shipping ) {
			if ($shipping->get_method_id() !== 'lpost_wc_shipping') {
				return;
			}
		}

		add_meta_box(
			'LPost_WC_MetaBox',
			esc_html__( 'Лабиринт Пост', 'lpost-wc-delivery' ),
			array(
				$this,
				'LPost_WC_MetaBox',
			),
			'shop_order',
			'side',
			'default'
		);
	}

	public function hide_order_itemmeta( $itemmeta ) {
		$itemmeta[] = 'deliv_type';
		$itemmeta[] = 'issue_type';
		$itemmeta[] = 'req_json';
		$itemmeta[] = 'pickup_points';
		$itemmeta[] = 'delivery_date';
		$itemmeta[] = 'delivery_interval';
		$itemmeta[] = 'deliv_time';
		$itemmeta[] = 'courier_coords';
		$itemmeta[] = 'ID_Sklad';
		$itemmeta[] = 'ID_PickupPoint';

		return $itemmeta;
	}

	public function LPost_WC_MetaBox() 
	{
		global $post;
		$order = wc_get_order($post->ID); ?>
        <style>
            #LPost_WC_MetaBox .optional {display: none;}
        </style>
        <?php

		$orderShippings = $order->get_items( 'shipping' );
		foreach ($orderShippings as $oKey => $orderShipping) {
			$metaMess = $orderShipping->get_formatted_meta_data();
			foreach ($metaMess as $mess) {
				$natOrderShpMeta[$mess->key] = $mess->value;
			}
		}

		$coordsArr = array();
		$reqJSON = json_decode($natOrderShpMeta['req_json'], true);
		
		if (isset($reqJSON['Latitude']) && isset($reqJSON['Longitude'])) 
			$coordsArr = array($reqJSON['Latitude'], $reqJSON['Longitude']);
		
		$delivType = ($order->get_meta('deliv_type', true)) ? : $natOrderShpMeta['deliv_type'];
		$issueType = ($order->get_meta('issue_type', true)) ? : $natOrderShpMeta['issue_type'];
		$coords = ($order->get_meta('courier_coords', true)) ? : implode(',', $coordsArr);
		$warehouseID = ($order->get_meta('receive_id_warehouse', true)) ? : $natOrderShpMeta['ID_Sklad'];
		$pickupPointID = ($order->get_meta('pickuppoint_id', true)) ? : $natOrderShpMeta['ID_PickupPoint'];
		$deliveryDate = ($order->get_meta('delivery_date', true)) ? : null;
		$deliveryInterval = ($order->get_meta('delivery_interval', true)) ? : null;

		$receiveFieldOptions = $this->helper->getSortedReceivePointsOptions();
		$pickupIDpointOptions = $this->helper->getPickupPointsOptions();

		$orderProducts = $order->get_items( 'line_item' );

		/*foreach ($orderProducts as $orderProduct) {
			echo '<pre>';
			print_r('<h2>$orderProduct</h2>');
			print_r($orderProduct->get_data());
			echo '</pre>';
		}*/

		woocommerce_form_field( 'deliv_type', [
			'type'  => 'select',
			'class' => ['wc-enhanced-select'],
			'label' => __( 'Тип доставки' ),
			'custom_attributes' => array(),
			'options' => array(
				'pickup' => 'Самовывоз',
				'courier' => 'Курьер',
			)
		], $delivType );

		woocommerce_form_field('receive_id_warehouse', array(
			'id'    => 'receive_id_warehouse',
			'label' => __( 'ID склада', 'lpost-wc-delivery' ),
			'desc' => ('для курьера всегда ID_Sklad = 3 (Родники, ул. Трудовая 10)'),
			'type'  => 'select',
			'class'  => ['wc-enhanced-select'],
			'default'  => '3',
			'options' => $receiveFieldOptions
		), $warehouseID);

		woocommerce_form_field('pickuppoint_id', array(
			'id'    => 'pickuppoint_id',
			'label' => __( 'ID конечного пункта', 'lpost-wc-delivery' ),
			//'desc' => ('для курьера всегда ID_Sklad = 3 (Родники, ул. Трудовая 10)'),
			'type'  => 'select',
			'class'  => ['enhanced'],
			'input_class'  => ['enhanced'],
			'default'  => '3',
			'options' => $pickupIDpointOptions
		), $pickupPointID);

		woocommerce_form_field('issue_type', array(
			'id'    => 'issue_type',
			'label' => __( 'Тип выдачи отправлений', 'lpost-wc-delivery' ),
			'type'  => 'select',
			'class'  => ['enhanced'],
			'input_class'  => ['enhanced'],
			'default'  => 0,
			'options' =>array(
				0 => __( 'Полная без вскрытия', 'lpost-wc-delivery' ),
				1 => __( 'Полная со вскрытием', 'lpost-wc-delivery' ),
				2 => __( 'Частичная', 'lpost-wc-delivery' ),
			)
		), $issueType);
		
		woocommerce_form_field( 'courier_coords', [
			'type'  => 'text',
			//'class' => '',
			'label' => __( 'Координаты' ),
			'custom_attributes' => array(
				//'required' => 'required'
			)
		], preg_replace("/[^,.0-9]/", '', $coords) );

		woocommerce_form_field( 'delivery_date', [
			'type'  => 'date',
			//'class' => '',
			'label' => __( 'Дата доставки' ),
			'custom_attributes' => array(
				'min' => date('Y-m-d'),
				//'max' => $delivTime[count($delivTime) - 1]->DateDelive,
				'required' => 'required',
				//'altdate' => date('d.m.Y', strtotime($delivTime[0]->DateDelive))
			)
		], $deliveryDate );

		woocommerce_form_field( 'delivery_interval', [
			'type'  => 'select',
			//'class' => '',
			'label' => __( 'Время доставки' ),
			'options' => array(
				0 => 'c 9 до 21',
				1 => 'c 9 до 12',
				2 => 'c 12 до 15',
				3 => 'c 15 до 18',
				4 => 'c 18 до 21',
			),
			'custom_attributes' => array(
				//'required' => 'required'
			)
		], $deliveryInterval );
	}

    public function shipping_checkout_fields($fields) 
	{
	    $fields['billing']['courier_coords'] = array(
		    'type'  => 'hidden',
		    'class' => ['courier_coords-field', 'hidden', 'hide', 'billing-dynamic'],
		    'label' => __( 'Courier Coordinates' ),
		    'custom_attributes' => array()
        );
		
		/*
		$pos = (true === WC()->cart->needs_shipping_address() ? 'shipping' : 'billing');
		$fields[$pos]['lp_courier_porch'] = array(
			'label'     => __('Номер подъезда'),
			'placeholder'   => '',
			'required'  => false,
			'class'     => array('form-row-first field-lp-courier'),
			'clear'     => true
		);
		$fields[$pos]['lp_courier_floor'] = array(
			'label'     => __('Номер этажа'),
			'placeholder'   => '',
			'required'  => false,
			'class'     => array('form-row-last field-lp-courier'),
			'clear'     => false
		);
		$fields[$pos]['lp_courier_flat'] = array(
			'label'     => __('Квартира/офис'),
			'placeholder'   => '',
			'required'  => false,
			'class'     => array('form-row-first field-lp-courier'),
			'clear'     => true
		);
		$fields[$pos]['lp_courier_code'] = array(
			'label'     => __('Код домофона'),
			'placeholder'   => '',
			'required'  => false,
			'class'     => array('form-row-last field-lp-courier'),
			'clear'     => false
		);
		*/
	    // $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		// if (!empty($chosen_methods))
		// {
			// foreach ($chosen_methods as $method) 
			// {
				// if (substr_count($method, 'lpost_wc_shipping') > 0) {
					// unset($fields['billing']['billing_postcode']);
					// unset($fields['billing']['billing_state']);
					// unset($fields['shipping']['shipping_postcode']);
					// unset($fields['shipping']['shipping_state']);
				// }
			// }
		// }

	    return $fields;
    }
	
	public function courier_checkout_fields($checkout) 
	{
		echo '<div id="lp_courier_checkout_field" style="display:none"><h3>' . __('Данные для курьера') . '</h3>';				
		woocommerce_form_field('_lp_courier_porch', array(
			'type'          => 'number',
			'class'         => array('field-courier-porch form-row-first'),
			'label'         => __('Номер подъезда'),
			'placeholder'   => '',
			'clear'     	=> true
		), $checkout->get_value( '_lp_courier_porch' ));
		
		woocommerce_form_field('_lp_courier_floor', array(
			'type'          => 'number',
			'class'         => array('field-courier-porch form-row-last'),
			'label'         => __('Номер этажа'),
			'placeholder'   => '',
		), $checkout->get_value( '_lp_courier_floor' ));
		
		woocommerce_form_field('_lp_courier_flat', array(
			'type'          => 'number',
			'class'         => array('field-courier-porch form-row-first'),
			'label'         => __('Квартира/офис'),
			'placeholder'   => '',
			'clear'     	=> true
		), $checkout->get_value( '_lp_courier_flat' ));
		
		woocommerce_form_field('_lp_courier_code', array(
			'type'          => 'text',
			'class'         => array('field-courier-porch form-row-last'),
			'label'         => __('Код домофона'),
			'placeholder'   => '',
		), $checkout->get_value('_lp_courier_code'));

		echo '<div class="form-row-wide"></div></div>';
	}
	public function courier_checkout_fields_admin($order)
	{
		
		$courier_porch = get_post_meta($order->id, '_lp_courier_porch', true);
		$courier_floor = get_post_meta($order->id, '_lp_courier_floor', true);
		$courier_flat = get_post_meta($order->id, '_lp_courier_flat', true);
		$courier_code = get_post_meta($order->id, '_lp_courier_code', true);
?>
    <div class="order_data_column" style="width:100%;clear:both;">
        <h4><?php _e( 'Данные для курьера', 'woocommerce' ); ?><!--a href="#" class="edit_address"><?php _e( 'Edit', 'woocommerce' ); ?></a--></h4>
        <div class="address">
			<p>
			<?php
            echo '<span>' . __( 'Номер подъезда' ) . ':</span> ' . esc_html($courier_porch) . '<br/>';
            echo '<span>' . __( 'Номер этажа' ) . ':</span> ' . esc_html($courier_floor) . '<br/>';
			echo '<span>' . __( 'Квартира/офис' ) . ':</span> ' . esc_html($courier_flat) . '<br/>';
			echo '<span>' . __( 'Код домофона' ) . ':</span> ' . esc_html($courier_code); 
			?>
			</p>
        </div>
        <div class="edit_address">
            <?php woocommerce_wp_text_input( array( 'id' => '_lp_courier_porch', 'label' => __( 'Номер подъезда' ), 'wrapper_class' => '' ) ); ?>
            <?php woocommerce_wp_text_input( array( 'id' => '_lp_courier_floor', 'label' => __( 'Номер этажа' ), 'wrapper_class' => 'last' ) ); ?>
			<?php woocommerce_wp_text_input( array( 'id' => '_lp_courier_flat', 'label' => __( 'Квартира/офис' ), 'wrapper_class' => '' ) ); ?>
			<?php woocommerce_wp_text_input( array( 'id' => '_lp_courier_code', 'label' => __( 'Код домофона' ), 'wrapper_class' => 'last' ) ); ?>
        </div>
    </div>
<?php


	}
	
	

	public function lpCreateInvoice() 
	{
		$orderID = 0;
		$shipment_id = 0;
		
		if (!empty($_POST['shipment_id']))
		{
			$shipment_id = intval($_POST['shipment_id']);
		} 
		elseif (!empty($_GET['shipment_id']))
		{
			$shipment_id = intval($_GET['shipment_id']);
		}
		
		if (!empty($_POST['orderID']))
		{
			$orderID = intval($_POST['orderID']);
		} 
		elseif (!empty($_GET['orderID']))
		{
			$orderID = intval($_GET['orderID']);
		}
		
		
		if (!empty($orderID) && !empty($shipment_id)) 
		{
			$response = $this->helper->createInvoice($shipment_id);
			foreach ($response as $responseItem) 
			{
				if ($responseItem->Message) 
				{
					wp_send_json_error($responseItem->Message);
				}
				update_post_meta($orderID, 'invoiceID', $responseItem->ActNumber);
				wp_send_json_success($responseItem->ActNumber);
			}
		} 
		else 
		{
			if (empty($orderID)) 
			{
				wp_send_json_error('Не указан номер заказа');
			}
			if (empty($shipment_id)) 
			{
				wp_send_json_error('Не указан ID отправления');
			}
		}
	    return true;
    }

	/**
	 * @param $orders WC_Order[]|stdClass
	 */
	public function lpCreateOrders($orders) {
	    $orderIDs = array();
	    foreach ($orders as $order) {
	        $shipment_id = get_post_meta($order->get_id(), 'shipment_id', true);
	        if ($shipment_id) continue;
	        $orderIDs[] = $order->get_id();
	    }
		$response = $this->helper->createOrders($orderIDs); // запрос на создание отправлений
		if (!$response) 
			return array();

		foreach ($response as $fResponse) 
		{

		    if (!isset($fResponse->ID_Order)) 
			{
		        continue;
		    }
		    update_post_meta($fResponse->PartnerNumber, 'shipment_id', $fResponse->ID_Order);
		    update_post_meta($fResponse->PartnerNumber, 'shipment_uml', $fResponse->LabelUml);
            //wc_update_order_item_meta()
        }

        return $response;
	}

	public function lpCalcRequest() {
		$response = $this->helper->makeCalcRequest(array());
		echo '<pre>';
		print_r('<h2>$response</h2>');
		print_r($response);
		echo '</pre>';
	    die('lpCalcRequest');
    }

	/**
	 * Load all frontend plugin scripts
	 */
	public function load_scripts() 
	{
		if ( is_checkout() ) 
		{
			wp_enqueue_script('lpost-wc-autocomplete', plugin_dir_url(__FILE__).'assets/js/jquery.autocomplete.min.js', array('jquery'));
			wp_enqueue_script('lpost-wc-helper', plugin_dir_url(__FILE__).'assets/js/helper.js', array('jquery'), date('dmYH'));
			wp_enqueue_script('lpost-wc-colorbox-js', plugin_dir_url(__FILE__).'assets/js/colorbox/jquery.colorbox-min.js', array('jquery'));
			
			wp_enqueue_style('lpost-wc-colorbox-css', plugin_dir_url(__FILE__).'assets/js/colorbox/colorbox.css');
			wp_enqueue_style('lpost-wc-main-css', plugin_dir_url(__FILE__).'assets/css/style.css', false, '1.1');
			
			$map_api = get_option( 'lpost_wc_yandex_api' );
			if ( $map_api ) 
			{
				wp_enqueue_script( 'yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=' . esc_attr( $map_api ) . '&lang=ru_RU', array(), '2.1', false );
			}
		}
	}

	/**
	 * Register settings page
	 *
	 * @param array $sections admin sections.
	 *
	 * @return mixed
	 */
	public function settings_page( $sections ) {
		$sections['lpost-wc-delivery'] = esc_html__( 'Лабиринт Пост', 'lpost-wc-delivery' );

		return $sections;
	}

	public function getLPostOrdersInfo($orderIDs) {
		if (!$orderIDs) return false;
		$response = $this->helper->getInfoForLPostOrders($orderIDs);

		return $response;
	}

	public function getOrdersDB($limit) 
	{
		global $wpdb;
		$query = array();
		$query['fields'] 	= 'SELECT order_id FROM '.$wpdb->prefix.'woocommerce_order_items AS order_items';
		$query['join'] 		= 'LEFT JOIN '.$wpdb->prefix.'woocommerce_order_itemmeta AS order_itemmeta ON order_items.order_item_id = order_itemmeta.order_item_id';
		$query['where'] 	= 'WHERE meta_value = "lpost_wc_shipping"';
		$query['order'] 	= 'ORDER BY order_id DESC';
		$query['limit'] 	= 'LIMIT '.$limit.', 10';

		$ordersDB = $wpdb->get_results(implode(' ', $query));
		return $ordersDB;
    }
	
	public function getCountOrdersDB($arParams = array()) 
	{
		global $wpdb;
		$query = array();
		$query['fields'] 	= 'SELECT COUNT(*) FROM '.$wpdb->prefix.'woocommerce_order_items AS order_items';
		$query['join'] 		= 'LEFT JOIN '.$wpdb->prefix.'woocommerce_order_itemmeta AS order_itemmeta ON order_items.order_item_id = order_itemmeta.order_item_id';
		$query['where'] 	= 'WHERE meta_value = "lpost_wc_shipping"';

		$rowCount = $wpdb->get_var(implode(' ', $query));
		return $rowCount;
    }
	
	// Функция склонения слов после чисел
	function num_decline( $number, $titles, $show_number = 1 ){
		if( is_string( $titles ) )
			$titles = preg_split( '/, */', $titles );
		// когда указано 2 элемента
		if( empty( $titles[2] ) )
			$titles[2] = $titles[1];

		$cases = [ 2, 0, 1, 1, 1, 2 ];
		$intnum = abs( (int) strip_tags( $number ) );
		$title_index = ( $intnum % 100 > 4 && $intnum % 100 < 20 )
			? 2
			: $cases[ min( $intnum % 10, 5 ) ];
		return ( $show_number ? "$number " : '' ) . $titles[ $title_index ];
	}

	// Страница Накладные
	public function invoice_settings_page() 
	{
		$pageSect = (!empty($_GET['pageSect']) && $_GET['pageSect'] > 0) ? intval($_GET['pageSect']) : 1;
		$limit = ( $pageSect - 1 ) * 20;

		$ordersDB = $this->getOrdersDB($limit);
		/*$orders = wc_get_orders(array(
			'paged' => $pageSect
		));*/
		
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Отправления Лабиринт Пост</h1>
			<hr class="wp-header-end" style="margin-bottom:20px;">
		<?
		if ($ordersDB)
		{

			$arOrders = array();
			foreach ($ordersDB as $orderDB) 
			{
				$arOrders[] = wc_get_order($orderDB->order_id);
			}

			$ordersCreateResult = array();
            foreach ($arOrders as $order) 
			{
				$shipment_id = $order->get_meta('shipment_id');
				if (empty($shipment_id)) 
				{
					// Если нет отправления, повторить попытку
					$order_id = $order->get_id();	
					$ordersCreateResult[$order_id] = $this->lpCreateOrders(array($order)); 
					$shipment_id = get_post_meta($order_id, 'shipment_id', true);
				}
				
				if (!empty($shipment_id)) 
				{
					$shipment_ids[] = $shipment_id;
				}
            }

			$arStateOrders = array();
			$arOrdersLPostInfo = $this->getLPostOrdersInfo($shipment_ids);
			if (!empty($arOrdersLPostInfo))
			{
				foreach ($arOrdersLPostInfo as $state) 
				{
					$arStateOrders[$state->ID_Order] = $state;
				}
			}
			
            ?>
			<table class="wp-list-table widefat fixed striped table-view-list invoices-table">
				<thead>
					<tr>
						<th>Статус отправления</th>
						<th>Информация об отправлении</th>
						<th></th>
						<th>Акт</th>
						<th>Этикетки заказа</th>
						<th>Информация об оплате/возврату</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th>Статус отправления</th>
						<th>Информация</th>
						<th></th>
						<th>Акт</th>
						<th>Этикетки заказа</th>
						<th>Информация об оплате/возврату</th>
					</tr>
				</tfoot>
				<tbody>
					<?php 
					foreach($arOrders as $key => $order):
						$order_id 	= $order->get_id();
						$shipment_id = get_post_meta($order_id, 'shipment_id', true);
						$invoiceID 	= get_post_meta($order_id, 'invoiceID', true);
						$shpLabel 	= get_post_meta($order_id, 'shipment_uml', true);
						$actBeforeTime 	= get_post_meta($order_id, 'actBeforeTime', true);
						
						$orderState = (!empty($arStateOrders[$order_id] ? $arStateOrders[$order_id] : (object)array()));
						
						$shipmentLabelLink = ($shpLabel ? $shpLabel .= $this->helper->getAuthToken() : '');
						
						$stateText = 'Черновик';
						if ($shipment_id) $stateText = 'Создан';
						if (!empty($invoiceID)) $stateText = 'Отправлен';
						if (!empty($orderState->StateDelivery)) $stateText = $this->convertDeliveryStates($orderState->StateDelivery);
						
						$arErrors = array();
						if (!empty($ordersCreateResult[$order_id])) 
						{
							if (!empty($ordersCreateResult[$order_id]->errorMessage))
							{
								$arErrors['shipment'] = $ordersCreateResult[$order_id]->errorMessage;
							}
						}
						if (!empty($arStateOrders[$order_id])) 
						{
							if (!empty($arStateOrders[$order_id]->Message))
							{
								$arErrors['state'] = $arStateOrders[$order_id]->Message;
							}
						}
						
						$editHref = 'post.php?post='.$order_id.'&action=edit&TB_iframe=true&iframe=order&width=900&height=700';
						?>
					<tr>
						<td class="state-cell">
							<strong><?php echo $stateText;?></strong>
							<?php if(!empty($arErrors)): ?>
							<ul class="errors"><li><?php echo implode('</li><li>', $arErrors); ?></li></ul>
							<?php endif;?>
						</td>
						<td class="info-cell">
							<?php if(!empty($shipment_id)): ?>
							<strong>Отправление: № <?php echo esc_html($shipment_id); ?></strong>
							<?php endif;?>
							<div><a class="row-title" href="post.php?post=<?php echo esc_html($order_id); ?>&action=edit">Заказ <?php echo esc_html($order_id); ?></a> (<?php echo wc_price($order->get_shipping_total())?>)</div>
							<div><span class="title">Получатель: </span><span class="value"> <?php echo $order->get_formatted_billing_full_name(); ?></span></div>
						</td>
						<td class="edit-order">
							<?if(!empty($invoiceID)):?>
							Создан акт, <br>редактирование не доступно
							<?else:?>
							<a class="thickbox" href="<?php echo esc_html($editHref); ?>" data-orderid="<?php echo esc_html($order_id); ?>">Изменить заказ</a>
							<?endif;?>
						</td>
						<td class="create-invoice-cell">
							<?if(!empty($invoiceID)):?>
								<strong><? echo esc_html($invoiceID); ?></strong>
							<?else:?>
							<a data-orderid="<?php echo esc_html($order_id); ?>" data-action="create-invoice" data-shipment_id="<?php echo esc_html($shipment_id); ?>" href="javascript:void(0)">Создать акт</a>
								<?if(!empty($actBeforeTime)):?>
								<br>Необходимо создать акт до <? echo date('d.m.Y H:i', strtotime($actBeforeTime)); ?>
								<?endif;?>
							<?endif;?>
						</td>
						<td class="create-label-cell">
							<?if(!empty($shipmentLabelLink)):?>
								<a href="<?php echo $shipmentLabelLink?>" target="_blank">Открыть</a>
							<?else:?>
							<?endif;?>
						</td>
						<td>

						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="orders-pagination">
				<?php
				$countOrders = $this->getCountOrdersDB();

				$page_links = paginate_links( array(
					'base' => '%_%',
					'format' => '?pageSect=%#%',
					'prev_text' => __( '&laquo;', 'lpost-wc-delivery' ),
					'next_text' => __( '&raquo;', 'lpost-wc-delivery' ),
					'total' => ceil($countOrders / 20),
					'current' => $pageSect
				) );

				if ( $page_links ) {
					?>
					<div class="tablenav tablenav-invoices">
						<div class="tablenav-pages">
							<span class="displaying-num"><? echo $this->num_decline($countOrders, 'элемент, элемента, элементов' ); ?></span>
							<div class="pagination-links"><? echo $page_links; ?></div>
						</div>
					</div>
					<?
				}
				?>
			</div>

            <?php
			if (!empty($_GET['log'])) 
			{
				echo '<h2>ordersCreateResult</h2><pre>'.print_r($ordersCreateResult,1).'</pre>';
				echo '<h2>arOrders</h2><pre>'.print_r($arOrders,1).'</pre>';
				echo '<h2>arOrdersLPostInfo</h2><pre>'.print_r($arOrdersLPostInfo,1).'</pre>';
			}

			
		}
		else 
		{
			?><div class="notice notice-warning"><p>Ничего не найдено</p></div><?
		}
		?>
		</div>
		<?
    }

    public function convertDeliveryStates($state) {

		$stateArrs = array(
			'CREATED' => 'Создано',
			'ACT_CREATED' => 'Создан акт',
			'READY_FOR_RETURN' => 'Готово к отгрузке на склад Лабиринт-Пост',
			'SENT_TO_WAREHOUSE' => 'Отправлено на склад Лабиринт-Пост',
			'ARRIVED_AT_THE_WAREHOUSE' => 'Прибыло на склад',
			'ACCEPTED_BY_PLACES' => 'Принято по местам',
			'SENT_TO_PICKUP_POINT' => 'Отправлено в Пункт доставки',
			'PLACED_IN_PICKUP_POINT' => 'Размещено в пункте доставки',
			'RECEIVED' => 'Выдано получателю',
			'DONE' => 'Выполнено',
			'CANCELLED' => 'Аннулировано',
			'ARCHIVE' => 'Архив',
		);

		return ($stateArrs[$state]) ? : $state;
    }

	/**
	 * Main settings page
	 *
	 * @param array  $settings section setting.
	 * @param string $current_section current admin section.
	 *
	 * @return array|mixed
	 */
	public function settings( $settings, $current_section ) {
		if ( 'lpost-wc-delivery' === $current_section ) {
			$settings = array(
				array(
					'title' => __( 'Лабиринт Пост', 'lpost-wc-delivery' ),
					'type'  => 'title',
				),
				array(
					'title' => __( 'Яндекс.Карты ключ API', 'lpost-wc-delivery' ),
					'desc'  => __( '<a href="https://developer.tech.yandex.ru/services/" target="_blank">Установите API-ключ для Яндекс Карт</a>, чтобы покупатели могли выбирать точки доставки на карте.', 'lpost-wc-delivery' ),
					'type'  => 'text',
					'id'    => 'lpost_wc_yandex_api',
				),
				array(
					'title' => __( 'Лабиринт Пост ключ API тест', 'lpost-wc-delivery' ),
					'type'  => 'text',
					'id'    => 'lpost_wc_api_secret',
				),
			    array(
					'title' => __( 'Лабиринт Пост ключ API боевой', 'lpost-wc-delivery' ),
					'type'  => 'text',
					'id'    => 'lpost_wc_api_prod_secret',
				),
				array(
					'title' => __( 'Включить тестовый режим', 'lpost-wc-delivery' ),
					'type'  => 'checkbox',
					'id'    => 'test_mode',
				),
				array(
					//'title' => __( 'Обновить пункты доставки', 'lpost-wc-delivery' ),
					'desc'  => '<a href="'.home_url().$_SERVER["REQUEST_URI"].'&task=updatePickupPoints" class="button-primary">Обновить пункты доставки</a>',
					'type'  => 'title',
				),
			);
		}

		return $settings;
	}

	public function add_delivery_type($method) {
		$meta_data = $method->meta_data;

		if ( ! is_checkout() ) {
			return;
		}

		if ( 'lpost_wc_shipping' !== $method->method_id ) {
			return;
		}

		if ( WC()->session->get( 'chosen_shipping_methods' )[0] !== $method->id ) {
			return;
		}

		if (!isset($meta_data['pickup_points'])) return;

		$cityPointsArr = $meta_data['pickup_points'];
		$delivType = $meta_data['deliv_type'];
		$delivTime = json_decode($meta_data['deliv_time']);
		$ID_PickupPoint = $meta_data['ID_PickupPoint'];

		$postDecode = array();
		if (isset($_POST['post_data'])) parse_str($_POST['post_data'], $postDecode);
		if (empty($delivType)) return true;

		?>
		<?php $linkText = ($delivType === 'pickup') ? 'Выбрать пункт выдачи' : 'Указать адрес доставки'?>
		<div class="deliv_type">
			<div><a href="javascript:void(0)" data-deliv_type="<?php echo esc_html($delivType); ?>" class="ch-pickup-pont"><?php echo esc_html($linkText); ?></a></div>
			<?php if (isset($cityPointsArr)): ?>
				<script type="text/javascript">var pickupPoints<?php echo esc_html($delivType); ?> = <?php echo $cityPointsArr; ?></script>
			<?php endif ?>
			<div class="map-holder" style="display: none;">
				<div id="map-container-<?php echo esc_html($delivType); ?>">
					<div class="map-element"></div>
					<?php if ($delivType !== 'pickup'): ?>
						<div class="control-div">
							<button class="pp-btn" data-json="" style="width: 100%;" disabled>Доставить сюда</button>
						</div>
					<?php endif ?>
				</div>
			</div>
			<?php if ($delivType === 'pickup'):
				$ship_to_different_address = $postDecode['ship_to_different_address'] ? esc_html($postDecode['ship_to_different_address']) : false;
			    $billingAddress = $postDecode['billing_address_1'] ?? '';

                $addressText = ($postDecode and !empty($postDecode['shipping_address_1']) and $ship_to_different_address) ? esc_html($postDecode['shipping_address_1']) : $billingAddress;
			    $addressText = str_ireplace('Самовывоз: ', '', $addressText);
				$addressTextKey = array_search($addressText, array_column(json_decode($cityPointsArr), 'Address'));
				if ($addressTextKey) $ID_PickupPoint = json_decode($cityPointsArr)[$addressTextKey]->ID_PickupPoint;
                ?>
				<input type="hidden" name="ID_PickupPoint" value="<?php echo esc_html($ID_PickupPoint); ?>">
            <?php else:?>
				<?php
				$calendarValue = $delivTime[0]->DateDelive;
				$timeValue = 0;
				if ($postDecode) {
					$calendarValue = $postDecode['delivery_date'] ? esc_html($postDecode['delivery_date']) : $delivTime[0]->DateDelive;
					$timeValue = $postDecode['delivery_interval'] ? esc_html($postDecode['delivery_interval']) : '';
				}

				woocommerce_form_field( 'delivery_date', [
					'type'  => 'date',
					'class' => ['delivery_date-field'],
					'label' => __( 'Выберите дату доставки' ),
					'required' => true,
					'default' => date('d.m.Y', strtotime($delivTime[0]->DateDelive)),
					'custom_attributes' => array(
						'min' => $delivTime[0]->DateDelive,
						'max' => $delivTime[count($delivTime) - 1]->DateDelive,
                        'required' => 'required',
                        'altdate' => date('d.m.Y', strtotime($delivTime[0]->DateDelive))
					)
				], $calendarValue);
				woocommerce_form_field( 'delivery_interval', [
					'type'  => 'select',
					'class' => ['delivery_interval-field', 'wc-enhanced-select'],
					'input_class' => ['wc-enhanced-select'],
					'label' => __( 'Выберите время' ),
					'required' => true,
					'options' => array(
						0 => 'c 9 до 21',
						1 => 'c 9 до 12',
						2 => 'c 12 до 15',
						3 => 'c 15 до 18',
						4 => 'c 18 до 21',
					),
					'custom_attributes' => array(
						'required' => 'required'
					)
				], $timeValue);
				?>
			<?php endif ?>
		</div>
		<?php
	}

	/**
	 * Load textdomain for a plugin
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'lpost-wc-delivery' );
	}

	/**
	 * Add shipping method
	 */
	public function init_method() {
		if ( ! class_exists( 'LPost_WC_Shipping_Method' ) ) {
			include_once __DIR__ . '/class-lpost_wc-shipping-method.php';
		}
	}

	/**
	 * Register shipping method
	 *
	 * @param array $methods shipping methods.
	 *
	 * @return array
	 */
	public function register_method( $methods ) {
		$methods['lpost_wc_shipping'] = 'LPost_WC_Shipping_Method';

		return $methods;
	}

	// Добавить в body CSS класс
	public function add_admin_body_class($classes) {  
		if (
			(!empty($_GET['iframe']) && 'order' == $_GET['iframe']) 
			|| strripos($_SERVER['HTTP_REFERER'], 'iframe=order') !== false
		) 
		{
			$classes .= 'iframe-order';
		}
		return $classes;
	}
	
	/**
	 * Добавляем ссылку на страницу настроек
	 *
	 * @param array $links key - link pair.
	 *
	 * @return array
	 */
    public function plugin_action_links($links) 
	{
		$settings = array( 'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=lpost-wc-delivery' ) . '">' . esc_html__( 'Настройки', 'lpost-wc-delivery' ) . '</a>' );
        $links = $settings + $links;
        return $links;
    }

	/**
	 * Auto update plugin
	 *
	 * @param bool   $should_update If should update.
	 * @param object $plugin Plugin data.
	 *
	 * @return bool
	 */
	public function auto_update_plugin( $should_update, $plugin ) {
		/*if ( 'lpost-wc-delivery.php' === $plugin->plugin ) {
			return true;
		}*/

		return $should_update;
	}

	/**
	 * Add debug tools
	 *
	 * @param array $tools List of available tools.
	 *
	 * @return array
	 */
	public function add_debug_tools( $tools ) {
		$tools['lpost_wc_clear_transients'] = array(
			'name'     => __( 'LPost transients', 'lpost-wc-delivery' ),
			'button'   => __( 'Clear transients', 'lpost-wc-delivery' ),
			'desc'     => __( 'This tool will clear the request transients cache.', 'lpost-wc-delivery' ),
			'callback' => array( $this, 'clear_transients' ),
		);

		return $tools;
	}

	/**
	 * Callback to clear transients
	 *
	 * @return string
	 */
	public function clear_transients() {
		global $wpdb;

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%_lpost_wc_cache__%'" );

		return __( 'Transients cleared', 'lpost-wc-delivery' );
	}

	/**
	 * Send message to logger
	 *
	 * @param string $message Log text.
	 * @param string $type Message type.
	 */
	public static function log_it( $message, $type = 'info' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$hide_log_info = get_option( 'lpost_wc_hide_info_log', 'no' );

		if ( 'yes' === $hide_log_info && 'info' === $type ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->{$type}( $message, array( 'source' => 'lpost_wc' ) );
	}

	/**
	 * Helper to add admin notice
	 *
	 * @param string $message Notice text.
	 * @param string $type Notice type.
	 */
	public function add_admin_notice( $message, $type = 'message' ) {
		$adminnotice = new WC_Admin_Notices();
		$adminnotice->add_custom_notice($type,"<div><p>$message</p></div>");
		$adminnotice->output_custom_notices();

		/*add_action(
			'admin_notices',
			function () use ( $message, $type ) {
				$notice_class = array(
					'type'   => 'notice-' . esc_html( $type ),
					'is-dis' => 'error' !== $type ? 'is-dismissible' : '',

				);
				echo '<div class="notice ' . esc_attr( implode( ' ', $notice_class ) ) . '">' . $message . '</div>';
			}
		);*/
	}
}