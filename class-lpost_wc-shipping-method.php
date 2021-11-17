<?php
/**
 * LPost_WC Shipping Method.
 *
 * @version 1.0
 * @package LPost_WC/Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * LPost_WC_Shipping_Method class.
 */
class LPost_WC_Shipping_Method extends WC_Shipping_Method 
{
	private $requires;
	private $helper;

	/**
	 * Constructor
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) 
	{
		include_once __DIR__.'/lpost-wc-helper.php';
		$this->helper = new LPost_WC_Helper();

		$this->initInstanceFormFields();

		$this->id                 = 'lpost_wc_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __('Л-Пост', 'lpost-wc-delivery' );
		$this->method_description = __('Расчет доставки методом Л-Пост', 'lpost-wc-delivery' );
		
		$this->enabled = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
		$this->title = $this->get_option('title', __('Л-Пост', 'lpost-wc-delivery' ));
		
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
		);
	}

	/**
	 * Получить список регионов
	 * @return array
	 */
	public function getRegionsListOptions() 
	{
		$options = array();

		$fileName = __DIR__.'/regions.csv';
		$csv = array_map('str_getcsv', file($fileName));

		foreach ($csv as $key=>$row) 
		{
			if ($key == 0) continue;
			$options[$key] = strip_tags($row[2]);
		}

		return $options;
	}

	public function initInstanceFormFields() 
	{
		$receiveFieldOptions = $this->helper->getSortedReceivePointsOptions();
		$regionsListOptions = $this->getRegionsListOptions();

		$this->instance_form_fields = array(
			'title' => array(
				'title' 		=> __('Название', 'lpost-wc-delivery'),
				'type'  		=> 'text',
				'default'		=> $this->method_title,
			),
			'deliv_type' => array(
				'id'    		=> 'deliv_type',
				'title' 		=> __('Тип доставки', 'lpost-wc-delivery'),
				'desc'  		=> '',
				'type'  		=> 'select',
				'class'  		=> 'wc-enhanced-select',
				'default'  		=> 'pickup',
				'options' 		=> array(
					'pickup' 	=> __('Самовывоз', 'lpost-wc-delivery'),
					'courier' 	=> __('Курьер', 'lpost-wc-delivery'),
				),
			),
			'receive_id_warehouse' => array(
				'id'    		=> 'receive_id_warehouse',
				'title' 		=> __('Точка приёма отправлений', 'lpost-wc-delivery'),
				'description' 	=> __('Если отправления забираются с вашего склада силами Л-Пост, то выберите Родники, ул.Трудовая, д.10.', 'lpost-wc-delivery') .'<br>'. __('Если вы планируете самостоятельно доставлять отправления в Л-Пост, то выберете ближайшую к вам точку приема отправлений.', 'lpost-wc-delivery'),
				'type'  		=> 'select',
				'class'  		=> 'wc-enhanced-select',
				'default'  		=> '3',
				'options' 		=> $receiveFieldOptions
			),
			'days_for_picking' => array(
				'id'    		=> 'days_for_picking',
				'title' 		=> __('Дополнительное время на комплектацию в днях', 'lpost-wc-delivery'),
				'description' 	=> '',
				'type'  		=> 'select',
				'class'  		=> 'wc-enhanced-select',
				'default'  		=> 0,
				'options' 		=> array(
					0 => '0',
					1 => '1',
					2 => '2'
				),
			),
			'lab_shipment_fee' => array(
				'id'			=> 'lab_shipment_fee',
				'title'			=> __('Наценка доставки, %', 'lpost-wc-delivery'),
				'description'	=> 'может принимать отрицательные значения',
				'type'			=> 'text',
				'input_class'	=> array('short'),
			),
			'min_sum_for_discount' => array(
				'id'			=> 'min_sum_for_discount',
				'title'			=> __('Минимальная сумма корзины для скидки', 'lpost-wc-delivery'),
				'desc'			=> '',
				'type'			=> 'text',
				'input_class'	=> array('short'),
			),
			'discount_percent' => array(
				'id'			=> 'discount_percent',
				'title'			=> __('Размер скидки, %', 'lpost-wc-delivery'),
				'desc'			=> '',
				'type'			=> 'text',
				'input_class'	=> array('short'),
			),
			'issue_type' => array(
				'id'    		=> 'issue_type',
				'title' 		=> __('Тип выдачи отправлений', 'lpost-wc-delivery'),
				'type'  		=> 'select',
				'class'  		=> 'wc-enhanced-select',
				'default'  		=> 0,
				'options' 		=> array(
					0 => __('Полная без вскрытия', 'lpost-wc-delivery'),
					1 => __('Полная со вскрытием', 'lpost-wc-delivery'),
					2 => __('Частичная', 'lpost-wc-delivery'),
				),
			),
			'regions_restrictions' => array(
				'id'    		=> 'regions_restrictions',
				'title' 		=> __('Ограничение доставки по регионам', 'lpost-wc-delivery'),
				'type'  		=> 'multiselect',
				'class'  		=> 'wc-enhanced-select',
				'default'  		=> '',
				'options' 		=> $regionsListOptions,
			),
			'shipment_min_weight' => array(
				'id'    	=> 'shipment_min_weight',
				'title' 	=> __('Минимальный вес отправления (г.)', 'lpost-wc-delivery'),
				'type'  	=> 'number',
				'default'  	=> 1,
				'custom_attributes' => array(
					'min' 		=> 1,
					'max' 		=> 30000,
					'required' 	=> true,
				),
			),
			'shipment_max_weight' => array(
				'id'    	=> 'shipment_max_weight',
				'title' 	=> __('Максимальный вес отправления (г.)', 'lpost-wc-delivery'),
				'type'  	=> 'number',
				'default'  	=> 30000,
				'custom_attributes' => array(
					'min' 		=> 1,
					'max' 		=> 30000,
					'required' 	=> true,
				),
			),
			array(
				'title' => __('Габариты отправления по умолчанию', 'lpost-wc-delivery'),
				'desc'  => '',
				'type'  => 'title',
			),
			'dimensions_shipment_length' => array(
				'id'    	=> 'dimensions_shipment_length',
				'title' 	=> __('Длина (см.)', 'lpost-wc-delivery'),
				'type'  	=> 'number',
				'default'  	=> 38,
				'custom_attributes' => array(
					'min' 		=> 10,
					'max' 		=> 120,
					'required' 	=> true,
				),
			),
			'dimensions_shipment_width' => array(
				'id'    	=> 'dimensions_shipment_width',
				'title' 	=> __('Ширина (см.)', 'lpost-wc-delivery'),
				'type'  	=> 'number',
				'default'  	=> 31,
				'custom_attributes' => array(
					'min' 		=> 10,
					'max' 		=> 80,
					'required' 	=> true,
				),
			),
			'dimensions_shipment_height' => array(
				'id'    	=> 'dimensions_shipment_height',
				'title' 	=> __('Высота (см.)', 'lpost-wc-delivery'),
				'type'  	=> 'number',
				'default'  	=> 29,
				'custom_attributes' => array(
					'min' 		=> 10,
					'max' 		=> 50,
					'required' 	=> true,
				),
			),
			array(
				'title' => __('Габариты товара по умолчанию', 'lpost-wc-delivery'),
				'desc'  => '',
				'type'  => 'title',
			),
			'dimensions_product_weight' => array(
				'id'    	=> 'dimensions_product_weight',
				'title' 	=> __('Вес (г.)', 'lpost-wc-delivery'),
				'type'  	=> 'number',
				'default'  	=> 100,
				'custom_attributes' => array(
					'min' 		=> 1,
					'max' 		=> 10000,
					'required' 	=> true,
				),
			),
		);
	}

	/**
	 * Get all products dimensions
	 *
	 * @param array $package Package of items from cart.
	 *
	 * @return array
	 */
	public function getProductsDimensions( $package ) 
	{

		$arDimensions = array(
			'total_weight' => 0,
			'total_volume' => 0,
		);

		$productWeightDefault = floatval($this->get_option('dimensions_product_weight', 100));

		foreach ( $package['contents'] as $item_id => $item_values ) 
		{
			if ( ! $item_values['data']->needs_shipping() ) 
			{
				continue;
			}

			$productWeight = (wc_get_weight( floatval( $item_values['data']->get_weight() ), 'g' )) ? : $productWeightDefault;
			$productLength = wc_get_dimension( floatval( $item_values['data']->get_length() ), 'cm' ) ? : 1;
			$productWidth  = wc_get_dimension( floatval( $item_values['data']->get_width() ), 'cm' ) ? : 1;
			$productHeight = wc_get_dimension( floatval( $item_values['data']->get_height() ), 'cm' ) ? : 1;
			
			$productQuantity  = floatval($item_values['quantity']);
			$productVolume = $productLength * $productWidth * $productHeight * $productQuantity;

			$arDimensions['products'][] = array(
				'length'	=> $productLength,
				'width'		=> $productWidth,
				'height'	=> $productHeight,
				'weight'	=> $productWeight,
				'volume'	=> $productVolume,
				'quantity'	=> $productQuantity,
			);

			$arDimensions['total_weight'] += $productWeight * $productQuantity;
			$arDimensions['total_volume'] += $productVolume * $productQuantity;
		}

		return $arDimensions;
	}

	/**
	 * Print error for debugging
	 *
	 * @param string $message custom error message.
	 */
	public function maybePrintError( $message = '' ) 
	{
		if ( ! current_user_can( 'manage_woocommerce' ) ) 
		{
			return;
		}

		if (empty($message))
		{
			$message = __('Ошибка при расчете', 'lpost-wc-delivery');
		}
			
		$this->add_rate(
			array(
				'id'        => $this->get_rate_id(),
				'label'     => $this->title . ' - '. $message .'. ' . __('Это сообщение и метод видны только администратору сайта в целях отладки. ', 'lpost-wc-delivery'),
				'cost'      => 0,
				'meta_data' => array( 'lpost_wc_error' => true ),
			)
		);
	}
	
	/**
	 * Get settings fields for instances of this shipping method (within zones).
	 * Should be overridden by shipping methods to add options.
	 * @return array
	 */
	public function get_instance_form_fields() 
	{
		return parent::get_instance_form_fields();
	}
	
	/**
	 * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
	 *
	 * @param array $package Package array.
	 */
	public function calculate_shipping( $package = array() ) 
	{
		
		$delivType = $this->get_option('deliv_type', 'pickup');

		$city = $package['destination']['city'];
		if (empty($city))
		{
			$this->maybePrintError('Не определен город доставки');
			return false;
		}

		setlocale(LC_TIME, "ru_RU.utf8");
		
		$city = mb_strtolower($city);
		$city = trim(str_ireplace(array('г.'), '', $city));

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
			$this->maybePrintError('Нет доставок к указанном городе');
			return false;
		}
		
		// Проверка на допустимые регионы
		$regionsRestrs = $this->get_option('regions_restrictions', array());
		if (count($regionsRestrs) > 0 and !in_array($arCityPoints[0]->ID_Region, $regionsRestrs)) 
		{
			$this->maybePrintError('Не доступный регион');
			return false;
		}
		

		// Габариты отправления по умолчанию
		$shipmentLength = $this->get_option('dimensions_shipment_length', 38); // Длина (см.) 
		$shipmentWidth  = $this->get_option('dimensions_shipment_width',  31); // Ширина (см.) 
		$shipmentHeight = $this->get_option('dimensions_shipment_height', 29); // Высота (см.) 
		$shipmentVolume = floatval($shipmentLength) * floatval($shipmentWidth) * floatval($shipmentHeight); // Рассчитанный объем отправления
		
		$shipmentMinWeight = $this->get_option('shipment_min_weight', 1); // Минимальный вес отправления (г.)
		$shipmentMaxWeight = $this->get_option('shipment_max_weight', 30000); // Максимальный вес отправления (г.)
		
		$daysForPicking = intval($this->get_option('days_for_picking', 0)); // Дополнительное время на комплектацию в днях
		
		// Проверка на допустимый вес
		$arDimensions = $this->getProductsDimensions($package);
		if ($arDimensions['total_weight'] < $shipmentMinWeight || $arDimensions['total_weight'] > $shipmentMaxWeight)
		{
			$this->maybePrintError('Не доступный вес: '.esc_html($shipmentMinWeight).' &lt; '.esc_html($arDimensions['total_weight']).' &lt; '.esc_html($shipmentMaxWeight));
			return false;
		}
		
		
		$arData = (!empty($_POST['post_data']) ? $this->helper->proper_parse_str($_POST['post_data']) : array());
		$ID_PickupPointDef 	= intval($arCityPoints[0]->ID_PickupPoint);
		$ID_PickupPoint 	= (isset($arData['_lp_pickup_point_id']) ? esc_html($arData['_lp_pickup_point_id']) : '');
		$courierCoords 		= (isset($arData['_lp_courier_coords']) ? preg_replace("/[^,.0-9]/", '', $arData['_lp_courier_coords']) : '');
		$courierDate	 	= (isset($arData['_lp_delivery_date']) ? esc_html($arData['_lp_delivery_date']) : '');
		$courierTime 		= (isset($arData['_lp_delivery_interval']) ? esc_html($arData['_lp_delivery_interval']) : '');
		
		$arParams = array(
			'ID_Sklad' 			=> $this->get_option('receive_id_warehouse', 3),
			'Weight' 			=> $arDimensions['total_weight'],
			'Volume' 			=> $shipmentVolume,
			'SumPayment' 		=> 0,
			'Value' 			=> WC()->cart->get_subtotal(),
		);
		if (!empty($daysForPicking))
			$arParams['DateShipment'] = wp_date('Y-m-d', strtotime("+ $daysForPicking day"));
		
		if ($delivType == 'pickup') 
		{ 
			$arParams['ID_PickupPoint'] = ((!empty($ID_PickupPoint) || $ID_PickupPoint == 0) ? intval($ID_PickupPoint) : $ID_PickupPointDef);
		}
		else 
		{
			// курьер
			$arCoords = (!empty($courierCoords) ? explode(',', $courierCoords) : array());
			if (count($arCoords) == 2) 
			{
				$arParams['Latitude'] = $arCoords[0];
				$arParams['Longitude'] = $arCoords[1];
			} 
			else 
			{
				$arParams['Address'] = $arCityPoints[0]->Address;
				$arParams['isNotExactAddress'] = true;
			}
			
		}		
		

		// Запрос на расчет
		$response = $this->helper->makeCalcRequest($arParams);
		$body = json_decode($response['body']);
		if (empty($body->JSON_TXT)) 
		{
			if (!empty($body->Message)) 
			{
				$this->maybePrintError($body->Message);
			}
			elseif (!empty($body->errorMessage)) 
			{
				$this->maybePrintError($body->errorMessage);
			} 
			else
			{
				$this->maybePrintError();
			}
			return false;
		}
		$body = json_decode($body->JSON_TXT);
		$decodedBody = $body->JSON_TXT[0];
		
		$isErrorAddress = false;

		if ($delivType == 'courier') 
		{
			if (empty($arParams['Latitude']) && !empty($package['destination']['city']) && !empty($package['destination']['address'])) 
			{
				$arParams['Address'] = 'г. '. $package['destination']['city'] .', '. $package['destination']['address'];
				unset($arParams['isNotExactAddress']);
				$response = $this->helper->makeCalcRequest($arParams);
				$body = json_decode($response['body']);
				if (!empty($body->JSON_TXT)) 
				{
					// Уточняет расчет по указанному пользователем адреса
					$body = json_decode($body->JSON_TXT);
					$decodedBody = $body->JSON_TXT[0];
				} else {
					// Пользователь не верно указан адрес
					$isErrorAddress = true;
				}
			}
		}
		
		if (!isset($decodedBody->SumCost))
		{
			$this->maybePrintError('Не определенна стоимость доставки');
			return false;
		}
		
		$cost = $decodedBody->SumCost;
		
		$fee = (float)$this->get_option('lab_shipment_fee', '');
		$minSumForDiscount = (float)$this->get_option('min_sum_for_discount', '');
		$discountPercent = (int)$this->get_option('discount_percent', '');
		$fee = (float)$this->get_option('lab_shipment_fee', '');

		$arDelivDates = array();
		if (!empty($decodedBody->PossibleDelivDates) && is_array($decodedBody->PossibleDelivDates)) 
		{
			foreach($decodedBody->PossibleDelivDates as $possibleDelivDate) 
			{				
				if (!empty($possibleDelivDate->DateDelive))
					$arDelivDates[$possibleDelivDate->DateDelive] = wp_date('j F (D)', strtotime($possibleDelivDate->DateDelive));
			}
		}
		
		if (!empty($fee))
		{
			// наценка
			$cost += $cost * ( $fee / 100 ); 
		}
		if (!empty($minSumForDiscount) && !empty($discountPercent))
		{ 
			// скидка
			if (WC()->cart->get_subtotal() >= $minSumForDiscount) 
			{
				$cost = $cost - $cost * ($discountPercent / 100);
			}
		}

		$this->add_rate(
			array(
				'id'        => $this->get_rate_id(),
				'label'     => $this->get_option('title', 'Л-Пост'),
				'cost'      => $cost,
				'package'   => $package,
				'meta_data' => array(
					'_lp_pickup_point_id' 		=> $ID_PickupPoint,
					'_lp_courier_coords' 		=> $courierCoords,
					'_lp_possible_deliv_dates' 	=> $arDelivDates,
					'_lp_delivery_date' 		=> $courierDate,
					'_lp_delivery_interval' 	=> $courierTime,
					'_lp_is_error_address' 		=> $isErrorAddress,
				),
			)
		);
		return true;
	}
	

	/**
	 * Is this method available?
	 *
	 * @param array $package Package.
	 * @return bool
	 */
	public function is_available( $package ) 
	{
		$is_available = false;
		$delivType = $this->get_option('deliv_type', '');
		$points = $this->helper->GetPickupPoints($delivType);
		if (!empty($points))
		{
			$cityColumns = array_column($points->PickupPoint, 'CityName');
			$cityColumns = array_map('mb_strtolower', $cityColumns);
			
			$city = $package['destination']['city'];
			if (!empty($city))
			{
				$city = mb_strtolower($city);
				$city = trim(str_ireplace(array('г.'), '', $city));
				if (in_array($city, $cityColumns))
				{
					$is_available = true;
				}
			}
		}
		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this );
	}

}
