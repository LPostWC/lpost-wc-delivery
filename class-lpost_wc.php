<?php

/**
 * LPost_WC setup
 *
 * @package LPost_WC
 * @since   1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main LPost_WC Class.
 *
 * @class LPost_WC
 */
class LPost_WC 
{

	private $helper;

	public function __construct() 
	{
		include_once __DIR__.'/lpost-wc-helper.php';
		$this->helper = new LPost_WC_Helper();
		$this->init();
	}

	// Регистрация событий и фильтров
	public function init() 
	{
		// Создание страницы с главными настройками доставки
		add_filter( 'woocommerce_get_sections_shipping', 	array( $this, 'shippingSectionAdd' ) );
		add_action( 'woocommerce_settings_shipping', 		array( $this, 'shippingSectionOutput' ) );
		add_filter( 'woocommerce_get_settings_shipping', 	array( $this, 'shippingSectionSettings' ), 10, 2 );
	
		// Добавляем админ меню и ссылку настроек
		add_action( 'admin_menu', array($this, 'addMenu'));
		add_filter( 'plugin_action_links_' . plugin_basename( __DIR__.'/lpost-wc-delivery.php' ), array( $this, 'pluginActionLinks' ) ); 
		
		
		add_action( 'wp_enqueue_scripts', array( $this, 'addScripts' ) );
		add_action( 'plugins_loaded', array( $this, 'loadTextDomain' ) );
		
		add_action( 'woocommerce_shipping_init', array( $this, 'initShippingMethod' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'registerShippingMethod' ) );
		

		// Добавить в body CSS класс
		add_filter( 'admin_body_class', array($this, 'addAdminBodyClass') );
		
		//add_action( 'woocommerce_checkout_fields', array($this, 'shipping_checkout_fields') );
		add_action( 'woocommerce_after_order_notes', array($this, 'courierCheckoutFields') );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array($this, 'courierCheckoutFieldsAdmin'));
		
		add_action( 'admin_enqueue_scripts', array( $this, 'addScriptsAdmin' ) );
		add_action( 'woocommerce_after_shipping_rate', array( $this, 'showShippingFormParams' ), 10, 2 );
		
		// метабокс заказа
		add_action( 'add_meta_boxes', array( $this, 'addMetaBoxOrder' ), 10, 2 );
		
		// ajax Проверка адреса при изменение заказа
		add_action( 'wp_ajax_check_address', array($this, 'ajaxCheckAddress') );

		add_action( 'wp_ajax_create_invoice', array($this, 'ajaxCreateInvoice') );
		
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hideOrderItemMeta' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'updateWooOrderMeta' ) );
		
		add_action( 'woocommerce_after_order_object_save', array( $this, 'updateWooOrder' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'updateWooOrderStatus' ), 10, 3 );
		add_action( 'woocommerce_checkout_process', array( $this, 'checkoutValidation' ) );
		
		add_action( 'woocommerce_order_before_calculate_totals', array( $this, 'calculateTotals' ), 10, 2 );
		add_action( 'lp_wc_schedule_send_order', array($this, 'scheduleSendOrder'), 10, 1);
	}
    	
	// Добавление ссылки на страницу настроек
    public function pluginActionLinks($links) 
	{
		$settings = array( 'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=lpost-wc-delivery' ) . '">' . esc_html__( 'Настройки', 'lpost-wc-delivery' ) . '</a>' );
        $links = $settings + $links;
        return $links;
    }
	
	// Подключение .mo файла перевода
	public function loadTextDomain() 
	{
		load_plugin_textdomain( 'lpost-wc-delivery' );
	}
	
	// Добавление пункта меню в адин.панель
	public function addMenu()
	{
		add_submenu_page( 'woocommerce', 'Накладные доставки Л-Пост' , 'Накладные Л-Пост', 'manage_woocommerce', 'lpost_wc-invoice', array( $this, 'showPageInvoice') );
    }

	// Подключение класса для метода доставки в WooCommerce, подключение файла class-lpost_wc-shipping-method.php
	public function initShippingMethod() 
	{
		if ( ! class_exists( 'LPost_WC_Shipping_Method' ) ) 
		{
			include_once __DIR__ . '/class-lpost_wc-shipping-method.php';
		}
	}

	// Регистрация метода доставки
	public function registerShippingMethod( $methods ) 
	{
		$methods['lpost_wc_shipping'] = 'LPost_WC_Shipping_Method';
		return $methods;
	}
	
	// Добавление страницы с настройками
	public function shippingSectionAdd( $sections ) 
	{
		$sections['lpost-wc-delivery'] = esc_html__( 'Л-Пост', 'lpost-wc-delivery' );
		return $sections;
	}
	
	// Вывод информации на странице с настройками
	public function shippingSectionOutput() 
	{
		session_start();
		global $current_section;
		
		if ('lpost-wc-delivery' !== $current_section) 
			return;
		
		echo '<h2>'. __( 'Л-Пост', 'lpost-wc-delivery' ).'</h2>';
		
		$this->helper->getAuthToken();
		$arErrorApi = $this->helper->errors;
		
		$secret = get_option( 'lpost_wc_api_secret', '');
		$map_api = get_option( 'lpost_wc_yandex_api', '');
		
		$mapError = '';
		$isMapConnect = false;
		if (!empty($map_api))
		{
			$httpHeadersApiMap = get_headers('http://geocode-maps.yandex.ru/1.x/?' . http_build_query(array('geocode' => 'Москва, ул. Льва Толстого, 16', 'format' => 'json', 'results' => 1, 'apikey' => $map_api), '', '&'));
			if (!empty($httpHeadersApiMap[0]))
			{
				if (strpos($httpHeadersApiMap[0], '200') !== false) 
				{
					$isMapConnect = true;
					
				}
			}
		}
		
		if (!empty($_POST))
		{
			if (!empty($arErrorApi['token']) || !$isMapConnect)
			{
				echo '<style>#message.updated{display:none}</style>';
				echo '<div class="error inline"><p>';
				echo '<strong>'. __( 'Настройки не сохранены', 'lpost-wc-delivery' ) .'</strong>';
				if (!empty($arErrorApi['token'])) echo '<br>'. __( 'Ошибка при подключении', 'lpost-wc-delivery' ) .' ('.$arErrorApi['token'].')';
				if (!$isMapConnect) echo '<br>'.__( 'Нет доступа к API Яндекс.Карт', 'lpost-wc-delivery' );
				echo '</p></div>';
				
				// Вернуть прежние настройки
				update_option('lpost_wc_yandex_api', (!empty($_SESSION['lpost_wc_yandex_api']) ? esc_html($_SESSION['lpost_wc_yandex_api']) : ''));
				update_option('lpost_wc_api_secret', (!empty($_SESSION['lpost_wc_api_secret']) ? esc_html($_SESSION['lpost_wc_api_secret']) : ''));
				update_option('lpost_wc_api_test', (!empty($_SESSION['lpost_wc_api_test']) ? esc_html($_SESSION['lpost_wc_api_test']) : ''));
				update_option('lpost_wc_api_status_send', (!empty($_SESSION['lpost_wc_api_status_send']) ? esc_html($_SESSION['lpost_wc_api_status_send']) : ''));	
			
			}
		} 
		else 
		{
			if (!empty($arErrorApi['token']) && !empty($secret))
			{
				echo '<div class="error inline"><p><strong>'. __( 'Ошибка при подключении', 'lpost-wc-delivery' ) .': '.$arErrorApi['token'].'</strong></p></div>';
			}
			if (!$isMapConnect && !empty($map_api))
			{
				echo '<div class="error inline"><p><strong>'. __( 'Нет доступа к API Яндекс.Карт', 'lpost-wc-delivery' ) .'</strong></p></div>';
			}
		}
		
		// Временно заполнить текущие настройки
		$_SESSION['lpost_wc_yandex_api'] 		= get_option( 'lpost_wc_yandex_api', '');
		$_SESSION['lpost_wc_api_secret'] 		= get_option( 'lpost_wc_api_secret', '');
		$_SESSION['lpost_wc_api_test'] 			= get_option( 'lpost_wc_api_test', '');
		$_SESSION['lpost_wc_api_status_send'] 	= get_option( 'lpost_wc_api_status_send', '');
		
		if (empty($arErrorApi['token'])) 
		{
			echo '<p><a href="'.home_url().$_SERVER["REQUEST_URI"].'&task=updatePickupPoints" class="button-primary">'. __( 'Обновить пункты доставки', 'lpost-wc-delivery' ) .'</a></p>';
			if (!empty($_GET['task']))
			{
				// кнопка обновления пунктов доставки
				if ($_GET['task'] === 'updatePickupPoints') 
				{
					$this->helper->GetPickupPoints('pickup', true);
					$this->helper->GetPickupPoints('courier', true);
					echo '<div class="updated inline"><p><strong>'. __( 'Пункты доставки обновлены', 'lpost-wc-delivery' ) .'</strong></p></div>';
				}
			}
		}
	}

	// Добавление полей для страницы настроек
	public function shippingSectionSettings( $settings, $current_section ) 
	{
		if ( 'lpost-wc-delivery' === $current_section ) 
		{			
			$settings = array(
				array(
					'title' => __( 'Настройки', 'lpost-wc-delivery' ),
					'type'  => 'title',
				),
				array(
					'title' => __( 'Яндекс.Карты ключ API', 'lpost-wc-delivery' ),
					'desc'  => __( '<a href="https://developer.tech.yandex.ru/services/" target="_blank">Установите API-ключ для Яндекс Карт</a>, чтобы покупатели могли выбирать точки доставки на карте.', 'lpost-wc-delivery' ),
					'type'  => 'text',
					'id'    => 'lpost_wc_yandex_api',
				),
				array(
					'title' => __( 'Секретный ключ', 'lpost-wc-delivery' ),
					'type'  => 'text',
					'id'    => 'lpost_wc_api_secret',
				),
				array(
					'title' => __( 'Включить тестовый режим', 'lpost-wc-delivery' ),
					'type'  => 'checkbox',
					'id'    => 'lpost_wc_api_test',
				),
				array(
					'title' => __( 'Отправлять заказ при переходе в статус', 'lpost-wc-delivery' ),
					'type'  => 'select',
					'id'    => 'lpost_wc_api_status_send',
					'options' 	=> wc_get_order_statuses()
				),
				array(
					'type' => 'sectionend'
				),
			);
		}

		return $settings;
	}

	// Подключения в очередь на вывод файлов стилей и скриптов
	public function addScripts() 
	{
		if ( is_checkout() ) 
		{
			$map_api = get_option( 'lpost_wc_yandex_api' );
			if ( $map_api ) 
			{
				wp_enqueue_script( 'yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=' . esc_attr( $map_api ) . '&lang=ru_RU', array(), '2.1', false );
			}
			
			//wp_enqueue_script('lpost-wc-autocomplete', plugin_dir_url(__FILE__).'assets/js/jquery.autocomplete.min.js', array('jquery'));
			wp_enqueue_script('lpost-wc-colorbox-js', plugin_dir_url(__FILE__).'assets/js/colorbox/jquery.colorbox-min.js', array('jquery'));
			wp_enqueue_style('lpost-wc-colorbox-css', plugin_dir_url(__FILE__).'assets/js/colorbox/colorbox.css');
			
			wp_enqueue_script('lpost-wc-scripts', plugin_dir_url(__FILE__).'assets/js/scripts.js', array('jquery'));
			wp_localize_script('lpost-wc-scripts', 'WPLPURLS', array('images' => plugin_dir_url(__FILE__).'assets/images/'));
			
			wp_enqueue_style('lpost-wc-main-css', plugin_dir_url(__FILE__).'assets/css/style.css', false, '1.1');
		}
	}
	
	// Вывод дополнительный параметров доставки на странице отправления заказа
	public function showShippingFormParams( $method ) 
	{

		if ( ! is_checkout() ) 
			return;
		
		if ( 'lpost_wc_shipping' !== $method->method_id )
			return;
		
		if ( WC()->session->get( 'chosen_shipping_methods' )[0] !== $method->id )
			return;

		$arParams = get_option( 'woocommerce_' . $method->method_id . '_' . $method->instance_id . '_settings' ); 
		$arMeta = $method->meta_data;
		$arData = (!empty($_POST['post_data']) ? $this->helper->proper_parse_str($_POST['post_data']) : array());
		
		
		$regionsRestrs = (!empty($arParams['regions_restrictions']) ? $arParams['regions_restrictions'] : array());
		$ship_to_different_address = (!empty($arData['ship_to_different_address']) ? esc_html($arData['ship_to_different_address']) : false);
		
		$daysForPicking = (!empty($arParams['days_for_picking']) ? intval($arParams['days_for_picking']) : 0); // Дополнительное время на комплектацию в днях
		
		$city = (!empty($arData['billing_city']) ? esc_html($arData['billing_city']) : '');
        $city = ((!empty($arData['shipping_city']) && $ship_to_different_address) ? esc_html($arData['shipping_city']) : $city);

		if (empty($city))
			return;
		
		$city = mb_strtolower($city);
		$city = trim(str_ireplace(array('г.'), '', $city));

		$delivType = (!empty($arParams['deliv_type']) ? $arParams['deliv_type'] : 'pickup');
		
		$points = $this->helper->GetPickupPoints($delivType);
		
		$arCityPoints = array();
		if (!empty($points))
		{
			foreach ($points->PickupPoint as $pointKey=>$point) 
			{
				if ($city == mb_strtolower($point->CityName)) 
				{
					$arCityPoints[] = $point;
				}
			}
		}
		if (empty($arCityPoints)) 
		{
			return false;
		}
		// Проверка на допустимые регионы
		if (count($regionsRestrs) > 0 and !in_array($arCityPoints[0]->ID_Region, $regionsRestrs)) 
		{
			return false;
		}
		
		if ($delivType == 'pickup') 
		{ 
			// самовывоз
			$dayKeys = array(
				'понедельник' 	=> array('day' => 0, 'title' => 'пн'),
				'вторник' 		=> array('day' => 1, 'title' => 'вт'),
				'среда' 		=> array('day' => 2, 'title' => 'ср'),
				'четверг' 		=> array('day' => 3, 'title' => 'чт'),
				'пятница' 		=> array('day' => 4, 'title' => 'пт'),
				'суббота' 		=> array('day' => 5, 'title' => 'сб'),
				'воскресенье' 	=> array('day' => 6, 'title' => 'вс'),
			);
			foreach ($arCityPoints as $cityPoint) 
			{
				foreach ($cityPoint->PickupPointWorkHours as $dayWork) 
				{
					$dayWork->shortTitle = $dayKeys[$dayWork->Day]['title'];
					$dayWork->From = preg_replace('/:00$/m', '', $dayWork->From);
					$dayWork->To = preg_replace('/:00$/m', '', $dayWork->To);

					$sortedDaysInfo[$dayKeys[$dayWork->Day]['day']] = $dayWork;
				}

				$cityPoint->DayLogistic = intval($cityPoint->DayLogistic) + $daysForPicking;
				$cityPoint->SimpleWorkHours = $sortedDaysInfo;
				
				if ($cityPoint->DayLogistic == 0)
					$cityPoint->DeliveryDate = __('сегодня', 'lpost-wc-delivery');
				elseif ($cityPoint->DayLogistic == 1)
					$cityPoint->DeliveryDate = __('завтра', 'lpost-wc-delivery');
				elseif ($cityPoint->DayLogistic == 2)
					$cityPoint->DeliveryDate = __('послезавтра', 'lpost-wc-delivery');
				else
					$cityPoint->DeliveryDate = date_i18n('j F', strtotime('+'. $cityPoint->DayLogistic .' days'));
			}
		} 
		else 
		{ 
			// курьер
			foreach ($arCityPoints as $cityPoint) 
			{
				foreach ($cityPoint->Zone as $cityZone) 
				{
					$cityZone->WKT = json_decode('{'. $cityZone->WKT .'}'); //волшебное превращение

					foreach ($cityZone->WKT->Coordinates as &$zoneBlock) 
					{
						foreach ($zoneBlock as &$innerCoords) 
						{
							// переворачивание координат
							$innerCoords = array_reverse($innerCoords);
						}
					} 
				}
			}
		}

		$ID_PickupPoint = intval($arMeta['_lp_pickup_point_id']);
		
		$isErrorAddress = $arMeta['_lp_is_error_address'];
		?>
		<?php $linkText = ($delivType === 'pickup') ? 'Выбрать пункт выдачи' : 'Указать адрес доставки'?>
		<div class="deliv_type">
			<div><a href="javascript:void(0)" data-deliv_type="<?php echo esc_html($delivType); ?>" class="ch-pickup-pont"><?php echo esc_html($linkText); ?></a></div>
			<?php if (isset($arCityPoints)): ?>
				<script type="text/javascript">var pickupPoints<?php echo esc_html($delivType); ?> = <?php echo json_encode($arCityPoints); ?></script>
			<?php endif ?>
			<div class="map-holder" style="display: none;">
				<div id="map-container-<?php echo esc_html($delivType); ?>">
					<div class="map-element"></div>
				</div>
			</div>
			<?php 
			if ($delivType === 'pickup')
			{
			    $billingAddress = (!empty($arData['billing_address_1']) ? esc_html($arData['billing_address_1']) : '');

                $addressText = ((!empty($arData['shipping_address_1']) && $ship_to_different_address) ? esc_html($arData['shipping_address_1']) : $billingAddress);
			    $addressText = str_ireplace('Самовывоз: ', '', $addressText);
				
				$addressTextKey = array_search($addressText, array_column($arCityPoints, 'Address'));
				if ($addressTextKey) 
					$ID_PickupPoint = intval($arCityPoints[$addressTextKey]->ID_PickupPoint);
				
				if (empty($ID_PickupPoint) && $ID_PickupPoint !== 0) 
				{
					
				?><input type="hidden" name="_lp_is_error_point_id" value="1"><?php
				}
				?><input type="hidden" name="_lp_pickup_point_id" value="<?php echo intval($ID_PickupPoint); ?>"><?php
			}	
			else
			{
				$deliveryDate = (!empty($arData['_lp_delivery_date']) ? esc_html($arData['_lp_delivery_date']) : '');
				$courierCoords = (!empty($arData['_lp_courier_coords']) ? preg_replace("/[^,.0-9]/", '', $arData['_lp_courier_coords']) : '');
				$deliveryInterval = (!empty($arData['_lp_delivery_interval']) ? esc_html($arData['_lp_delivery_interval']) : 0);
		
				woocommerce_form_field( '_lp_delivery_date', [
					'type'  	=> 'select',
					'class'		=> array('_lp_delivery_interval-field', 'wc-enhanced-select'),
					'required' 	=> true,
					'label' 	=> __( 'Выберите дату доставки', 'lpost-wc-delivery' ),
					'options' 	=> (!empty($arMeta['_lp_possible_deliv_dates']) ? $arMeta['_lp_possible_deliv_dates'] : array()),
					'custom_attributes' => array(
						'required' 	=> 'required',
					),
				], $deliveryDate);
				
				woocommerce_form_field( '_lp_delivery_interval', array(
					'type'  		=> 'select',
					'class'		 	=> array('_lp_delivery_interval-field', 'wc-enhanced-select'),
					'input_class' 	=> array('wc-enhanced-select'),
					'label' 		=> __( 'Выберите время', 'lpost-wc-delivery' ),
					'required' 		=> true,
					'options' 		=> array(
						0 => 'c 9 до 21',
						1 => 'c 9 до 12',
						2 => 'c 12 до 15',
						3 => 'c 15 до 18',
						4 => 'c 18 до 21',
					),
				), $deliveryInterval);
				
				if ($isErrorAddress) 
				{
				?><input type="hidden" name="_lp_is_error_address" value="1"><?php
				}
				?><input type="hidden" name="_lp_courier_coords" value="<?php echo esc_html($courierCoords); ?>"><?php
			}
			?>
		</div>
		<?php
	}
	
	
	// Дополнительная проверка полей при оформлении заказа 
	public function checkoutValidation() 
	{
	    if (isset($_POST['_lp_delivery_date'])) 
		{
	        // if (!$_POST['_lp_courier_coords']) wc_add_notice('Пожалуйста, укажите адрес доставки на карте', 'error' );
        }

		if (!empty($_POST['_lp_is_error_address']))
		{
			wc_add_notice( __( 'Указан не верный адрес. Измените адрес доставки или укажите на карте.', 'lpost-wc-delivery' ), 'error' );
		}
		if (!empty($_POST['_lp_is_error_point_id']))
		{
			wc_add_notice( __( 'Не указан пункт вывоза заказа. Пожалуйста укажите пункт доставки на карте.', 'lpost-wc-delivery' ), 'error' );
		}
		
    }

	// При обновлении заказа на сайте 
	public function updateWooOrder($WC_Order) 
	{
		if (is_admin()) 
		{
			$this->updateWooOrderMeta($WC_Order->id);
		}
		
		$this->setScheduleSendOrder($WC_Order->id);
    }
	

	// При обновлении статуса заказа
	public function updateWooOrderStatus($order_id, $old_status, $new_status) 
	{
		$this->setScheduleSendOrder($order_id);
	}
	
	// Добавление в крон вызов функции создания/обновления отправления 
	public function setScheduleSendOrder($order_id=0) 
	{
		if( ! wp_next_scheduled('lp_wc_schedule_send_order', array($order_id)))
		{
			wp_schedule_single_event(time(), 'lp_wc_schedule_send_order', array($order_id));
		}
	}
	
	// Крон - функция создания/обновления отправления 
	public function scheduleSendOrder($order_id=0) 
	{
		if (!empty($order_id))
			$this->sendOrder($order_id);
	}
	
	// Перерасчет заказа - обновление доставки
	public function calculateTotals($and_taxes, $WC_Order) 
	{
		$shipping_methods = $WC_Order->get_shipping_methods();
		if ( ! $shipping_methods ) 
		{
			return;
		}

		$method = false;
		foreach ( $shipping_methods as $shipping ) 
		{
			if ($method) continue;
			if ($shipping->get_method_id() === 'lpost_wc_shipping' && $shipping->get_instance_id())
			{
				$method = $shipping;
			}
		}
		if (!$method) return;
		
		$arMethodParams = get_option( 'woocommerce_' . $method->get_method_id() . '_' . $method->get_instance_id() . '_settings' ); 
		
		if (empty($arMethodParams['deliv_type']))
			$arMethodParams['deliv_type'] = 'pickup';
		
		if ($arMethodParams['deliv_type'] == 'pickup') 
		{
			$pickupPointID = ($WC_Order->get_meta('_lp_pickup_point_id', true)) ? : null;
			if (empty($pickupPointID))
				return;
		} 
		else
		{
			$shippingAddress = $WC_Order->get_address('shipping');
			if (empty($shippingAddress['city']) || empty($shippingAddress['address_1']))
				return;
			
			$address = $shippingAddress['city'] . ', ' . $shippingAddress['address_1'];
		}

		// Получить товары
		$total_weight = 0;
		$order_items = $WC_Order->get_items();
		
		if (!$order_items)
			return;

		$productWeightDefault = (!empty($arMethodParams['dimensions_product_weight']) ? intval($arMethodParams['dimensions_product_weight']) : 100); // Вес товара по умолчанию
		foreach( $order_items as $item_id => $item )
		{
			// данные элемента заказа в виде массива
			$arProduct = $item->get_data();
			$product = wc_get_product($arProduct['product_id']);
			$weight = wc_get_weight($product->get_weight(), 'g');
			$total_weight += (!empty($weight) ? $weight : $productWeightDefault);
		}

		// Проверка на допустимый вес
		$shipmentMinWeight = (!empty($arMethodParams['shipment_min_weight']) ? intval($arMethodParams['shipment_min_weight']) : 1);; // Минимальный вес отправления (г.)
		$shipmentMaxWeight = (!empty($arMethodParams['shipment_max_weight']) ? intval($arMethodParams['shipment_max_weight']) : 30000); // Максимальный вес отправления (г.)		
		if ($total_weight < $shipmentMinWeight || $total_weight > $shipmentMaxWeight)
		{
			return false;
		}

		$daysForPicking = (!empty($arMethodParams['days_for_picking']) ? intval($arMethodParams['days_for_picking']) : 0); // Дополнительное время на комплектацию в днях
		
		// Габариты отправления по умолчанию
		$shipmentLength = (!empty($arMethodParams['dimensions_shipment_length']) ? intval($arMethodParams['dimensions_shipment_length']) : 38); // Длина (см.) 
		$shipmentWidth  = (!empty($arMethodParams['dimensions_shipment_width']) ? intval($arMethodParams['dimensions_shipment_width']) : 31);   // Ширина (см.) 
		$shipmentHeight = (!empty($arMethodParams['dimensions_shipment_height']) ? intval($arMethodParams['dimensions_shipment_height']) : 29); // Высота (см.) 
		
		$arParams = array(
			'ID_Sklad' 		=> (!empty($arMethodParams['receive_id_warehouse']) ? intval($arMethodParams['receive_id_warehouse']) : 3),
			'Weight' 		=> $total_weight,
			'Volume' 		=> floatval($shipmentLength) * floatval($shipmentWidth) * floatval($shipmentHeight),
			'SumPayment'	=> 0,
			'Value' 	 	=> $WC_Order->get_subtotal(),
		);

		if (!empty($daysForPicking))
			$arParams['DateShipment'] = wp_date('Y-m-d', strtotime("+ $daysForPicking day"));

		if ($arMethodParams['deliv_type'] == 'pickup') 
		{
			$arParams['ID_PickupPoint'] = intval($pickupPointID);
		} 
		else
		{
			$arParams['Address'] = $address;
		}

		$response = $this->helper->makeCalcRequest($arParams);
		$arResult = $this->helper->resDecode($response, true);
		if (!isset($arResult['result']['SumCost']))
			return;

		$cost = $arResult['result']['SumCost'];

		$minSumForDiscount = (!empty($arParams['min_sum_for_discount']) ? float($arParams['min_sum_for_discount']) : 0); 
		$discountPercent = (!empty($arParams['discount_percent']) ? intval($arParams['discount_percent']) : 0); 
		$fee = (!empty($arParams['lab_shipment_fee']) ? float($arParams['lab_shipment_fee']) : 0); 

		if (!empty($fee))
		{
			// наценка
			$cost += $cost * ( $fee / 100 ); 
		}
		if (!empty($minSumForDiscount) && !empty($discountPercent))
		{ 
			// скидка
			if ($WC_Order->get_subtotal() >= $minSumForDiscount) 
			{
				$cost = $cost - $cost * ($discountPercent / 100);
			}
		}

		if (!empty($arResult['result']['PossibleDelivDates']) && is_array($arResult['result']['PossibleDelivDates'])) 
		{
			$arPossibleDelivDates = array();
			foreach($arResult['result']['PossibleDelivDates'] as $possibleDelivDate) 
			{
				if (!empty($possibleDelivDate['DateDelive']))
					$arPossibleDelivDates[$possibleDelivDate['DateDelive']] = wp_date('j F (D)', strtotime($possibleDelivDate['DateDelive']));
			}
			$WC_Order->update_meta_data( '_lp_possible_deliv_dates', $arPossibleDelivDates);
		}

		$method->set_total($cost);
		$method->save();

		$this->setScheduleSendOrder($WC_Order->id);

	}

	// Создания/обновления отправления 
	public function sendOrder($order_id)
	{
	    $WC_Order = wc_get_order($order_id);
		$shipping_methods = $WC_Order->get_shipping_methods();
	
		if ( ! $shipping_methods ) 
		{
			return;
		}

		foreach ( $shipping_methods as $shipping ) 
		{
			if ($shipping->get_method_id() !== 'lpost_wc_shipping') 
			{
				return;
			}
		}

	    if ($shipment_id = get_post_meta($order_id, '_lp_shipment_id', true)) 
		{
			$response = $this->helper->updateOrder($order_id);
	    } 
		else 
		{
			$status_send = get_option('lpost_wc_api_status_send', 'wc-pending');
			$status = $WC_Order->get_status();
			$status_send_num = 0;
			$status_num = 0;
			$i=0;
			foreach (wc_get_order_statuses() as $code=>$statusName)
			{
				if ($code == $status_send)
					$status_send_num = $i;
				
				if ($code == $status || $code == 'wc-'.$status)
					$status_num = $i;
				
				$i++;
			}
			
			// if ($status_send_num<=$status_num) 
			if ($status_send_num > 0 && $status_send_num == $status_num)
				$response = $this->helper->createOrders(array($order_id));
	    }
		
		if (!empty($response))
		{
			foreach ($response as $key=>$resp) 
			{
				if (isset($resp->ID_Order)) 
					update_post_meta($order_id, '_lp_shipment_id', $resp->ID_Order);
				
				if (isset($resp->LabelUml)) 
					update_post_meta($order_id, '_lp_shipment_uml', $resp->LabelUml);

				if (!empty($resp->AddToAct)) 
					update_post_meta($order_id, '_lp_act_before_time', $resp->AddToAct);
				
				if (!empty($resp->Message)) 
					$this->addAdminNotice('Ошибка '.(!$shipment_id?'создания':'обновления').' отправления для заказа <a href="post.php?post='.$order_id.'&action=edit">'.$order_id.'</a>: '.$resp->Message, 'error');
			}
		}
    }
	
	// Обновление полей заказа
    public function updateWooOrderMeta($order_id) 
	{

		// Координаты
		if (isset($_POST['_lp_courier_coords'])) 
			update_post_meta( $order_id, '_lp_courier_coords', sanitize_text_field($_POST['_lp_courier_coords']));
		
		// Требуемая дата доставки
		if (isset($_POST['_lp_delivery_date'])) 
			update_post_meta( $order_id, '_lp_delivery_date', sanitize_text_field($_POST['_lp_delivery_date']));
		
		// Тип интервала времени доставки
		if (isset($_POST['_lp_delivery_interval'])) 
			update_post_meta( $order_id, '_lp_delivery_interval', sanitize_text_field($_POST['_lp_delivery_interval']));
		
		// Точка приёма отправлений - переопределение
		if (isset($_POST['_lp_receive_id_warehouse'])) 
			update_post_meta( $order_id, '_lp_receive_id_warehouse', sanitize_text_field($_POST['_lp_receive_id_warehouse']));
		
		// Идентификатор Пункта доставки
		if (isset($_POST['_lp_pickup_point_id'])) 
			update_post_meta( $order_id, '_lp_pickup_point_id', sanitize_text_field($_POST['_lp_pickup_point_id']));
		
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


		// Подстановка адреса
		if (!empty($_POST['_lp_courier_address']) && !empty($_POST['_lp_courier_city'])) 
		{
			$arAddress = array(
				'city' 		=> esc_html($_POST['_lp_courier_city']),
				'address_1' => esc_html($_POST['_lp_courier_address'])
			);
			$this->setOrderCourierAddress($order_id, $arAddress);
		}
		
		// Изменение способа доставки
		if (!empty($_POST['lp_method_instance_id_new'])) 
		{
			$this->setOrderMethodInstanceId($order_id, intval($_POST['lp_method_instance_id_new']));
		}
		
    }

	// Обновление адреса доставки в заказе
	public function setOrderCourierAddress($order_id, $arAddress) 
	{
		$WC_Order = wc_get_order($order_id);
		$raw_address = $WC_Order->get_address('shipping');
		
		if (isset($arAddress['city']))
			$raw_address['city'] = $arAddress['city'];
		
		if (isset($arAddress['address_1']))
			$raw_address['address_1'] = $arAddress['address_1'];
		
		$WC_Order->set_address($raw_address, 'shipping');
	}

	// Изменение способа доставки
	public function setOrderMethodInstanceId($order_id, $instance_id) 
	{
		$instance_id = intval($instance_id);
		if ($instance_id<=0) 
			return;
		
		$WC_Order = wc_get_order( $order_id );
		$shipping_methods = $WC_Order->get_shipping_methods();

		if ( ! $shipping_methods ) 
		{
			return;
		}

		$method = false;
		foreach ( $shipping_methods as $shipping ) 
		{
			if ($method) continue;
			if ($shipping->get_method_id() === 'lpost_wc_shipping')
			{
				$method = $shipping;
			}
		}
		if (!$method) return;


		$zones = WC_Shipping_Zones::get_zones();
		if (empty($zones))
			return;
		
		$options = array();
		foreach($zones as $zone)
		{
			if (empty($zone['shipping_methods']))
				continue;

			foreach($zone['shipping_methods'] as $zoneMethod)
			{
				if ($zoneMethod->id === 'lpost_wc_shipping')
				{
					if ($instance_id === $zoneMethod->instance_id)
					{
						$method->set_instance_id($instance_id);
						$method->set_method_title($zoneMethod->title);
						$method->save();
					}
				}
			}
		}
	}
	
	// Скрытие системный полей в заказе  
	public function hideOrderItemMeta( $itemMeta ) 
	{
		$itemMeta[] = '_lp_possible_deliv_dates';
		$itemMeta[] = '_lp_delivery_date';
		$itemMeta[] = '_lp_delivery_interval';
		$itemMeta[] = '_lp_courier_coords';
		$itemMeta[] = '_lp_pickup_point_id';
		return $itemMeta;
	}

	// Получение настроек способа доставки для заказа 
	public function getOrderShippingParams($order_id) 
	{
		$arParams = array();
		$order = wc_get_order($order_id); 
		
		$shipping_methods = $order->get_shipping_methods();
		if ( ! $shipping_methods ) 
		{
			return array();
		}
		
		$method = false;
		foreach ( $shipping_methods as $shipping ) 
		{
			if ($method) continue;
			if ($shipping->get_method_id() === 'lpost_wc_shipping')
			{
				$method = $shipping;
			}
		}
		if (!$method) return array();
			
		$arParams = get_option( 'woocommerce_' . $method->get_method_id() . '_' . $method->get_instance_id() . '_settings' ); 
		
		if (empty($arParams) || !is_array($arParams))
			return array();
		 
		return $arParams;
	}
	
	// Добавление в body CSS класс
	public function addAdminBodyClass( $classes ) 
	{
		if (
			(!empty($_GET['iframe']) && 'order' == $_GET['iframe']) 
			|| strripos($_SERVER['HTTP_REFERER'], 'iframe=order') !== false
		) 
		{
			$classes .= 'iframe-order';
		}
		return $classes;
	}

	// Подключения в очередь на вывод файлов стилей и скриптов в админ.панель
	public function addScriptsAdmin() 
	{
		if (!empty($_GET['post']))
		{
			$map_api = get_option( 'lpost_wc_yandex_api' );
			if ( $map_api ) 
			{
				wp_enqueue_script( 'yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=' . esc_attr( $map_api ) . '&lang=ru_RU', array(), '2.1', false );
			}
		}
		
		wp_enqueue_script('lpost-wc-colorbox-js', plugin_dir_url(__FILE__).'/assets/js/colorbox/jquery.colorbox-min.js', array('jquery'));
		wp_enqueue_style('lpost-wc-colorbox-css', plugin_dir_url(__FILE__).'/assets/js/colorbox/colorbox.css');
		
		wp_enqueue_script('lpost-wc-scripts-admin', plugin_dir_url(__FILE__).'/assets/js/scripts-admin.js', array('jquery'));
		
		wp_enqueue_style('lpost-wc-admin-css', plugin_dir_url(__FILE__).'/assets/css/style-admin.css', false, '1.1');
		return true;
    }
	
	// Добавление дополнительных блоков (meta box) на странице редактирования/создания заказа
	public function addMetaBoxOrder( $post_type, $post ) 
	{
		if ( 'shop_order' !== $post_type ) 
			return;

		$order            = wc_get_order( $post );
		$shipping_methods = $order->get_shipping_methods();

		if ( ! $shipping_methods ) 
		{
			return;
		}

		$method = false;
		foreach ( $shipping_methods as $shipping ) 
		{
			if ($method) continue;
			if ($shipping->get_method_id() === 'lpost_wc_shipping')
			{
				$method = $shipping;
			}
		}
		if (!$method) return;

		if ($method->get_instance_id()) 
		{
			add_meta_box(
				'lpost_wc_metabox',
				$method->get_name(),
				array(
					$this,
					'adminOrderMetaBox',
				),
				'shop_order',
				'side',
				'default'
			);
		} 
		else 
		{
			add_meta_box(
				'lpost_wc_metabox_new',
				'Способ доставки',
				array(
					$this,
					'adminOrderMetaBoxNewShipping',
				),
				'shop_order',
				'side',
				'default'
			);
		}
	}
		
	// Вывод полей в форме редактирования заказа
	public function adminOrderMetaBox() 
	{
		global $post;
		$order = wc_get_order($post->ID); 
		
		$arParams = $this->getOrderShippingParams($post->ID); 
		
		if(empty($arParams)) return;
		
		$delivType = (!empty($arParams['deliv_type']) ? $arParams['deliv_type'] : 'pickup');
		
		$receiveFieldOptions = $this->helper->getSortedReceivePointsOptions();
		$pickupIDpointOptions = $this->helper->getPickupPointsOptions();
		$orderProducts = $order->get_items( 'line_item' );


		// Точка приёма отправлений - переопределение
		$warehouseID 		= ($order->get_meta('_lp_receive_id_warehouse', true)) ? : null;
		woocommerce_form_field('_lp_receive_id_warehouse', array(
			'label' 	=> __('Точка приёма отправлений', 'lpost-wc-delivery'),
			'desc' 		=> __('для курьера всегда ID_Sklad = 3 (Родники, ул. Трудовая 10)', 'lpost-wc-delivery'),
			'type'  	=> 'select',
			'required'	=> false,
			'class'  	=> array('enhanced'),
			'input_class'  => array('enhanced'),
			'default'  	=> '',
			'options' 	=> array(''=>'-- Использовать из настроек --') + $receiveFieldOptions
		), $warehouseID);
		
		
		if ($delivType === 'pickup')
		{
			// Самовывоз
			$pickupPointID 		= ($order->get_meta('_lp_pickup_point_id', true)) ? : null;
			woocommerce_form_field('_lp_pickup_point_id', array(
				'label' 	=> __('Адрес ПВЗ', 'lpost-wc-delivery'),
				'desc' 		=> '',
				'type'  	=> 'select',
				'required'	=> true,
				'class'  	=> array('enhanced'),
				'input_class'  => array('enhanced'),
				'default'  	=> '3',
				'options' 	=> $pickupIDpointOptions
			), $pickupPointID);
		}
		else 
		{
			// Курьер
			$arPossibleDelivDates = ($order->get_meta('_lp_possible_deliv_dates', true)) ? : array();
			$deliveryDate 		= ($order->get_meta('_lp_delivery_date', true)) ? : null;
			$deliveryInterval 	= ($order->get_meta('_lp_delivery_interval', true)) ? : null;

			if (empty($arPossibleDelivDates)) 
			{
				$shipping_methods = $order->get_shipping_methods();
				
				$method = false;
				foreach ( $shipping_methods as $shipping ) 
				{
					if ($method) continue;
					if ($shipping->get_method_id() === 'lpost_wc_shipping' && $shipping->get_instance_id())
					{
						$method = $shipping;
						$arPossibleDelivDates = ($method->get_meta('_lp_possible_deliv_dates', true)) ? : array();
					}
				}
			}

			$arDelivDates = array();
			if (!empty($arPossibleDelivDates) && is_array($arPossibleDelivDates)) 
			{
				$arDelivDates = $arPossibleDelivDates;
			}
			
			if (empty($arDelivDates) && !empty($deliveryDate)) 
			{
				$arDelivDates = array($deliveryDate => wp_date('j F (D)', strtotime($deliveryDate)));
			}

			woocommerce_form_field( '_lp_delivery_date', [
				'type'  	=> 'select',
				'class'		=> array('wc-enhanced-select'),
				'required' 	=> true,
				'label' 	=> __( 'Дата доставки', 'lpost-wc-delivery' ),
				'options' 	=> $arDelivDates,
				'custom_attributes' => array(
					'required' 	=> 'required',
				),
			], $deliveryDate );
			
			woocommerce_form_field( '_lp_delivery_interval', [
				'type'  	=> 'select',
				'class'		=> array('wc-enhanced-select'),
				'required' 	=> true,
				'label' 	=> __( 'Время доставки', 'lpost-wc-delivery' ),
				'options' 	=> array(
					0 => 'c 9 до 21',
					1 => 'c 9 до 12',
					2 => 'c 12 до 15',
					3 => 'c 15 до 18',
					4 => 'c 18 до 21',
				),
				'custom_attributes' => array(
					'required' 	=> 'required',
				),
			], $deliveryInterval );
		}
	}
	
	// Вывод полей в форме редактирования заказа, у которого не указан способ доставки
	public function adminOrderMetaBoxNewShipping() 
	{
		$zones = WC_Shipping_Zones::get_zones();
		if (empty($zones))
			return;
		
		$options = array();
		foreach($zones as $zone)
		{
			if (empty($zone['shipping_methods']))
				continue;

			foreach($zone['shipping_methods'] as $zoneMethod)
			{
				if ($zoneMethod->id == 'lpost_wc_shipping')
				{
					$deliv_type = (!empty($zoneMethod->instance_settings['deliv_type']) ? $zoneMethod->instance_settings['deliv_type'] : 'pickup');
					$options[$zone['zone_name']][$zoneMethod->instance_id] = array('title' => $zoneMethod->title, 'deliv_type' => $deliv_type);
				}
			}
		}
		
		if (empty($options))
			return;
		
		echo '<p class="form-row form-row-wide lp_method_instance_id_new-dropdown" id="lp_method_instance_id_new_field" data-placeholder="Способ доставки"><span class="woocommerce-input-wrapper"><select name="lp_method_instance_id_new" id="lp_method_instance_id_new" class="select" required><option value="">Укажите доставку...</option>';
		foreach( $options as $optgroup_label => $optgroup_options ) 
		{
			echo '<optgroup label="'.$optgroup_label.'">';
			foreach( $optgroup_options as $key => $item ) 
			{
				echo '<option value="'.$key.'" data-deliv_type="'.$item['deliv_type'].'">'.$item['title'].'</option>';
			}
			echo '</optgroup>';
		}
		echo '</select></span></p>';
		

		// Изменение точки приёма отправлений
		$receiveFieldOptions = $this->helper->getSortedReceivePointsOptions();
		woocommerce_form_field('_lp_receive_id_warehouse', array(
			'label' 	=> __('Точка приёма отправлений', 'lpost-wc-delivery'),
			'desc' 		=> __('для курьера всегда ID_Sklad = 3 (Родники, ул. Трудовая 10)', 'lpost-wc-delivery'),
			'type'  	=> 'select',
			'required'	=> false,
			'class'  	=> array('enhanced'),
			'input_class'  => array('enhanced'),
			'default'  	=> '',
			'options' 	=> array(''=>'-- Использовать из настроек --') + $receiveFieldOptions
		), null);
		
		$pickupIDpointOptions = $this->helper->getPickupPointsOptions();
		woocommerce_form_field('_lp_pickup_point_id', array(
			'label' 	=> __('Адрес ПВЗ', 'lpost-wc-delivery'),
			'desc' 		=> '',
			'type'  	=> 'select',
			'required'	=> true,
			'class'  	=> array('pickup'),
			'input_class'  => array('pickup-field', 'enhanced'),
			'default'  	=> '3',
			'options' 	=> $pickupIDpointOptions
		), null);
		
		woocommerce_form_field( '_lp_courier_city', array(
			'type'  	=> 'text',
			'input_class'  	=> array('courier-field'),
			'label' 	=> __( 'Укажите город доставки', 'lpost-wc-delivery' ),
			'required' 	=> true,
		), null);
		
		woocommerce_form_field( '_lp_courier_address', array(
			'type'  	=> 'text',
			'input_class'  	=> array('courier-field'),
			'label' 	=> __( 'Укажите адрес доставки', 'lpost-wc-delivery' ),
			'required' 	=> true,
		), null);
		
		woocommerce_form_field( '_lp_delivery_date', array(
			'type'  	=> 'select',
			'input_class'  	=> array('courier-field', 'enhanced'),
			'label' 	=> __( 'Выберите дату доставки', 'lpost-wc-delivery' ),
			'required' 	=> true,
			'options' 	=> array('' => '-'),
			'custom_attributes' => array(
				'required' 	=> 'required',
			),
		), null);
		
		woocommerce_form_field( '_lp_delivery_interval', array(
			'type'  	=> 'select',
			'class'  	=> array('courier-field'),
			'input_class' 	=> array('courier-field', 'enhanced'),
			'label' 	=> __( 'Выберите время', 'lpost-wc-delivery' ),
			'required' 	=> true,
			'options' 	=> array(
				0 => 'c 9 до 21',
				1 => 'c 9 до 12',
				2 => 'c 12 до 15',
				3 => 'c 15 до 18',
				4 => 'c 18 до 21',
			),
			'custom_attributes' => array(
				'required' 	=> 'required',
			),
		), null);
		
	}
	
	// Вывод полей - уточнение адреса для курьера, на странице оформление заказа
	public function courierCheckoutFields($checkout) 
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
	
	// Вывод полей - уточнение адреса для курьера, на странице редактирования заказа
	public function courierCheckoutFieldsAdmin($order)
	{
		
		$arParams = $this->getOrderShippingParams($order->id); 
		if(empty($arParams)) return;
		
		$delivType = (!empty($arParams['deliv_type']) ? $arParams['deliv_type'] : 'pickup');
		if ($delivType == 'pickup') return;
		
		$courier_porch = get_post_meta($order->id, '_lp_courier_porch', true);
		$courier_floor = get_post_meta($order->id, '_lp_courier_floor', true);
		$courier_flat = get_post_meta($order->id, '_lp_courier_flat', true);
		$courier_code = get_post_meta($order->id, '_lp_courier_code', true);
		?>
		<div class="order_data_column" style="width:100%;clear:both;">
			<input type="hidden" id="_deliv_type" value="<?php echo esc_html($delivType); ?>">
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
	
	// Проверка адреса при изменение заказа (ajax)
	public function ajaxCheckAddress() 
	{
		if (empty($_POST['address']))
			wp_send_json_error('Не указан адрес');
			
		$address = esc_html($_POST['address']);
		
		$daysForPicking = 0;
		if (!empty($_POST['instance_id']))
		{
			$arParams = get_option( 'woocommerce_lpost_wc_shipping_' . intval($instance_id) . '_settings' ); 
			$daysForPicking = (!empty($arParams['days_for_picking']) ? intval($arParams['days_for_picking']) : 0); // Дополнительное время на комплектацию в днях
		}			

		$arRequesParams = array(
			'Address' => $address,
		);
		if (!empty($daysForPicking))
			$arRequesParams['DateShipment'] = wp_date('Y-m-d', strtotime("+ $daysForPicking day"));
		
		
		$response = $this->helper->makeCalcRequest($arRequesParams);
		
		$arResult = $this->helper->resDecode($response, true);
		if (isset($arResult['result']['SumCost']))
		{

			$arPossibleDelivDates = array();
			if (!empty($arResult['result']['PossibleDelivDates']) && is_array($arResult['result']['PossibleDelivDates'])) 
			{
				foreach($arResult['result']['PossibleDelivDates'] as $possibleDelivDate) 
				{
					if (!empty($possibleDelivDate['DateDelive']))
						$arPossibleDelivDates[$possibleDelivDate['DateDelive']] = wp_date('j F (D)', strtotime($possibleDelivDate['DateDelive']));
				}
			}

			$arData = array(
				'SumCost' 			 => floatval($arResult['result']['SumCost']),
				'PossibleDelivDates' => $arPossibleDelivDates,
			);
			wp_send_json_success($arData);
		} 
		else 
		{
			wp_send_json_error('Не удалось определить адрес');
		}
	    return true;
	}
	
	// Создание накладной (ajax)
	public function ajaxCreateInvoice() 
	{
		$arOrders = array();
		$arShipment = array();
		if (!empty($_POST['orders']) && is_array($_POST['orders']))
		{
			foreach($_POST['orders'] as $order_id) 
			{
				if (intval($order_id)>0) 
				{
					$shipment_id = get_post_meta($order_id, '_lp_shipment_id', true);
					if (!empty($shipment_id))
					{
						$arOrders[] = intval($order_id);
						$arShipment[] = $shipment_id;
					}
				}
			}
		} 

		if (!empty($arShipment))
		{
			$response = $this->helper->createInvoice($arShipment);
			foreach ($response as $responseItem) 
			{
				if (!empty($responseItem->ActNumber))
				{
					foreach($arOrders as $order_id) 
					{
						update_post_meta($order_id, '_lp_invoice_id', $responseItem->ActNumber);
					}
					wp_send_json_success($responseItem->ActNumber);
				} 
				elseif ($responseItem->Message) 
				{
					wp_send_json_error($responseItem->Message);
				} 
				else 
				{
					wp_send_json_error('Ошибка запроса');
				}
			}
		}
		else 
		{
			wp_send_json_error('Нет доступных отправлений');
		} 
	    return true;
    }

	// Получение заказов, у которых указан метод доставки Л-Пост
	public function getOrdersDB($pageSect) 
	{
		global $wpdb;
		$count = 20;
		$limit = ( $pageSect - 1 ) * $count;
		$query = array();
		$query['fields'] 	= 'SELECT order_id FROM '.$wpdb->prefix.'woocommerce_order_items AS order_items';
		$query['join'] 		= 'LEFT JOIN '.$wpdb->prefix.'woocommerce_order_itemmeta AS order_itemmeta ON order_items.order_item_id = order_itemmeta.order_item_id';
		$query['where'] 	= 'WHERE meta_value = "lpost_wc_shipping"';
		$query['order'] 	= 'ORDER BY order_id DESC';
		$query['limit'] 	= 'LIMIT '.$limit.', '.$count;

		$ordersDB = $wpdb->get_results(implode(' ', $query));
		return $ordersDB;
    }
	
	// Получение количества заказов, у которых указан метод доставки Л-Пост
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
	
	// Вывод страницы с накладными
	public function showPageInvoice() 
	{
		$pageSect = (!empty($_GET['pageSect']) && $_GET['pageSect'] > 0) ? intval($_GET['pageSect']) : 1;
		$ordersDB = $this->getOrdersDB($pageSect);
		/*$orders = wc_get_orders(array(
			'paged' => $pageSect
		));*/
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Отправления Л-Пост</h1>
			<hr class="wp-header-end" style="margin-bottom:20px;">
		<?
		if ($ordersDB)
		{

			$arOrders = array();
			$shipment_ids = array();
			$arOrdersLPostInfo = array();
			$arStateOrders = array();
			
			foreach ($ordersDB as $orderDB) 
			{
				$arOrders[] = wc_get_order($orderDB->order_id);
			}
			
            foreach ($arOrders as $order) 
			{
				$shipment_id = $order->get_meta('_lp_shipment_id');
				if (!empty($shipment_id)) 
				{
					$shipment_ids[] = $shipment_id;
				}
            }

			if (!empty($shipment_ids))
			{
				$arOrdersLPostInfo = $this->helper->getInfoForLPostOrders($shipment_ids);
			}

			if (!empty($arOrdersLPostInfo))
			{
				foreach ($arOrdersLPostInfo as $state) 
				{
					$arStateOrders[$state->ID_Order] = $state;
				}
			}
			
            ?>
			<div class="invoices-table-wrapper">
				<div class="tablenav top">
					<div class="alignleft actions">
						<button type="button" data-action="create-invoice-select" class="button" value="Фильтр">Создать акт</button>
					</div>
				</div>
				<table class="wp-list-table widefat fixed striped table-view-list invoices-table">
					<thead>
						<tr>
							<td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1">Выделить все</label><input id="cb-select-all-1" type="checkbox"></td>
							<th>Заказ</th>
							<th>Статус отправления</th>
							<th>Информация об отправлении</th>
							<th>Акт</th>
							<th>Этикетки заказа</th>
							<th>Дополнительная информация</th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<td class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-2">Выделить все</label><input id="cb-select-all-2" type="checkbox"></td>
							<th>Заказ</th>
							<th>Статус отправления</th>
							<th>Информация</th>
							<th>Акт</th>
							<th>Этикетки заказа</th>
							<th>Дополнительная информация</th>
						</tr>
					</tfoot>
					<tbody>
						<?php 
						foreach($arOrders as $key => $order):
							$order_id 		= $order->get_id();
							$shipment_id 	= get_post_meta($order_id, '_lp_shipment_id', true);
							$invoiceID 		= get_post_meta($order_id, '_lp_invoice_id', true);
							$shpLabel 		= get_post_meta($order_id, '_lp_shipment_uml', true);
							$actBeforeTime 	= get_post_meta($order_id, '_lp_act_before_time', true);
							
							$orderState = (!empty($arStateOrders[$order_id] ? $arStateOrders[$order_id] : (object)array()));
							
							$shipmentLabelLink = (!empty($shpLabel) ? $shpLabel . $this->helper->token : false);
							
							$stateText = 'Не создано';
							if ($shipment_id) $stateText = 'Создано';
							if (!empty($invoiceID)) $stateText = 'Отправлено';
							if (!empty($orderState->StateDelivery)) $stateText = $this->helper->convertDeliveryStates($orderState->StateDelivery);
							
							$arErrors = array();
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
							<th scope="row" class="check-column">
								<?php if(!empty($shipment_id)): ?><input type="checkbox" name="order[]" value="<?php echo esc_html($order_id); ?>"><?php endif; ?>
							</th>
							<td class="edit-order">
								<div>
									<a class="row-title" href="post.php?post=<?php echo esc_html($order_id); ?>&action=edit">Заказ <?php echo esc_html($order_id); ?></a>
									<br><? echo date('d.m.Y H:i', strtotime($order->get_date_created())); ?>
								</div>
								
								<div>
									<?php if(!empty($invoiceID)): ?>
									Создан акт, редактирование не доступно
									<?php else: ?>
									<a class="thickbox" href="<?php echo esc_html($editHref); ?>" data-orderid="<?php echo esc_html($order_id); ?>">Изменить заказ</a>
									<?php endif; ?>
								</div>
							</td>
							<td class="state-cell">
								<?php echo $stateText;?>
								<?php if (!empty($orderState->DateChangeStateDelivery)):?>
									<br><?php echo date('d.m.Y H:i', strtotime($orderState->DateChangeStateDelivery)); ?>
								<?php endif; ?>							
								<?php if(!empty($arErrors)): ?>
								<ul class="errors"><li><?php echo implode('</li><li>', $arErrors); ?></li></ul>
								<?php endif;?>
							</td>
							<td class="info-cell">
								<?php if(!empty($shipment_id)): ?>
								<strong>Отправление: № <?php echo esc_html($shipment_id); ?></strong>
								<?php endif; ?>
								<div><span class="title">Получатель: </span><span class="value"> <?php echo $order->get_formatted_billing_full_name(); ?></span></div>
								<div><span class="title">Стоимость: </span><span class="value"> <?php echo wc_price($order->get_shipping_total())?></span></div>
							</td>
							<td class="create-invoice-cell">
								<?php if(!empty($shipment_id)): ?>
									<?php if(!empty($invoiceID)):?>
										<strong><?php echo esc_html($invoiceID); ?></strong>
									<?php else: ?>
									<a data-order_id="<?php echo esc_html($order_id); ?>" data-action="create-invoice" href="javascript:void(0)">Создать акт</a>
										<?if(!empty($actBeforeTime)):?>
										<br>Необходимо создать акт до <? echo date('d.m.Y H:i', strtotime($actBeforeTime)); ?>
										<?php endif; ?>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<td class="create-label-cell">
								<?php if($shipmentLabelLink):?>
									<a href="<?php echo $shipmentLabelLink; ?>" target="_blank">Открыть</a>
								<?php endif; ?>
							</td>
							
							<td class="info-cell">
								<?php if (!empty($orderState->StateInf)):?>
									<div><span class="value"><?php echo esc_html($orderState->StateInf); ?></span></div>
								<?php endif; ?>
								<?php if (!empty($orderState->StateReturn)):?>
									<div><span class="title">Статус возврата отправления:</span><span class="value"> <?php echo esc_html($orderState->StateReturn); ?></span></div>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="tablenav bottom">
					<div class="alignleft actions">
						<button type="button" data-action="create-invoice-select" class="button" value="Фильтр">Создать акт</button>
					</div>
					<?php
					$countOrders = $this->getCountOrdersDB();
					if($countOrders>0)
					{
					?>
					<div class="tablenav-invoices">
						<span class="displaying-num"><?php echo $this->getNumDecline($countOrders, 'элемент, элемента, элементов' ); ?></span>
						<div class="pagination-links">
							<?php
							echo paginate_links( array(
								'base' => '%_%',
								'format' => '?pageSect=%#%',
								'prev_text' => __( '&laquo;', 'lpost-wc-delivery' ),
								'next_text' => __( '&raquo;', 'lpost-wc-delivery' ),
								'total' => ceil($countOrders / 20),
								'current' => $pageSect
							) );
							?>
						</div>
					</div>
					<?php
					}
					?>
				</div>
			</div>
			<?php
		}
		else 
		{
			?><div class="notice notice-warning"><p>Ничего не найдено</p></div><?php
		}
		?>
		</div>
		<?php
    }

	// Вывод сообщения в админ панели 
	public function addAdminNotice( $message, $type = 'message' ) 
	{
		$adminNotice = new WC_Admin_Notices();
		$adminNotice->add_custom_notice($type, '<div><p>'.$message.'</p></div>');
		$adminNotice->output_custom_notices();
	}
	
	// Функция склонения слов после чисел
	function getNumDecline( $number, $titles, $show_number = 1 )
	{
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
	
}