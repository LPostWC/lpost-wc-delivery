<?php
/**
 * LPost Shipping Method.
 *
 * @version 1.0.0
 * @package LPost/Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * LPost_WC_Shipping_Method class.
 */
class LPost_WC_Shipping_Method extends WC_Shipping_Method {
	private $requires;
	/**
	 * @var void
	 */
	private $apikey;
	/**
	 * @var LPost_WC_Helper
	 */
	private $helper;

	/**
	 * Constructor
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		include_once __DIR__.'/lpost-wc-helper.php';
		$this->helper = new LPost_WC_Helper();

		$this->init_form_fields();
		$this->init_settings();

		$this->id                 = 'lpost_wc_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Л-Пост', 'lpost-wc-delivery' );
		$this->method_description = __( 'Расчет доставки методом Л-Пост', 'lpost-wc-delivery' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
		);
		$this->init();

		// $this->apikey = $this->get_option('lpost_wc_password', '');
	}

	/**
	 * Init variables
	 */
	public function init() {
		$this->title = $this->get_option('title', __( 'Л-Пост', 'lpost-wc-delivery' ));
	}

	public function init_settings() {
		parent::init_settings();
		$this->enabled = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}

	/**
	 * Get setting form fields for instances of this shipping method within zones.
	 *
	 * @return array
	 */
	public function get_instance_form_fields() {
		return parent::get_instance_form_fields();
	}

	/**
	 * Получить список регионов
	 * @return array
	 */
	public function getRegionsListOptions() {
		$options = array();

		$fileName = __DIR__.'/regions.csv';
		$csv = array_map('str_getcsv', file($fileName));

		foreach ($csv as $key=>$row) {
			//if ($key == 0 or $row[1] == 'г') continue;
			if ($key == 0) continue;
			//$index = mb_substr($row[8], 0, 2);
			$options[$key] = strip_tags($row[2]);
		}

		return $options;
	}

	public function init_form_fields() {
		$receiveFieldOptions = $this->helper->getSortedReceivePointsOptions();
		$regionsListOptions = $this->getRegionsListOptions();

		$this->instance_form_fields = array(
			'title' => array(
				//'title' => __( 'Title', 'lpost-wc-delivery' ),
				'title' => __( 'Название', 'lpost-wc-delivery' ),
				'type'  => 'text',
				'default' => __( 'Л-Пост', 'lpost-wc-delivery' ),
			),
			'deliv_type' => array(
				'id'    => 'deliv_type',
				//'title' => __( 'Delivery type', 'lpost-wc-delivery' ),
				'title' => __( 'Тип доставки', 'lpost-wc-delivery' ),
				//'desc'  => __( 'Secret key for API integration.', 'lpost-wc-delivery' ) . ' ' . __( 'If you do not have API credentials you can get it by sending a request to integrator@cdek.ru. In the request, you must indicate your contract number with CDEK and e-mail to receive keys and notifications from the API integration.', 'lpost-wc-delivery' ),
				'type'  => 'select',
				'class'  => 'wc-enhanced-select',
				'default'  => 'pickup',
				'options' => array(
					'pickup' => __( 'Самовывоз', 'lpost-wc-delivery' ),
					'courier' => __( 'Курьер', 'lpost-wc-delivery' ),
				),
			),
			/*'warehouse_deliv_type' => array(
				'id'    => 'warehouse_deliv_type',
				'title' => __( 'Способ отгрузки', 'lpost-wc-delivery' ),
				//'desc'  => __( 'Secret key for API integration.', 'lpost-wc-delivery' ) . ' ' . __( 'If you do not have API credentials you can get it by sending a request to integrator@cdek.ru. In the request, you must indicate your contract number with CDEK and e-mail to receive keys and notifications from the API integration.', 'lpost-wc-delivery' ),
				'type'  => 'select',
				'class'  => 'wc-enhanced-select',
				'default'  => 'selfservice',
				'options' => array(
					'selfservice' => __( 'На склад', 'lpost-wc-delivery' ),
					'courier' => __( 'Курьером', 'lpost-wc-delivery' ),
				),
			),*/
			'receive_id_warehouse' => array(
				'id'    => 'receive_id_warehouse',
				'title' => __( 'ID склада', 'lpost-wc-delivery' ),
				'description' => ('для курьера всегда ID_Sklad = 3 (Родники, ул. Трудовая 10)'),
				'type'  => 'select',
				'class'  => 'wc-enhanced-select',
				'default'  => '3',
				'options' => $receiveFieldOptions
			),
			'lab_shipment_fee' => array(
				'id'                => 'lab_shipment_fee',
				'title'             => __( 'Наценка доставки, %', 'lpost-wc-delivery' ),
				/* translators: %s are links. */
				'description'       => 'может принимать отрицательные значения',
				'type'              => 'text',
				'input_class'       => ['short'],
				'custom_attributes' => array(
					//'required' => true,
				),
			),
			'min_sum_for_discount' => array(
				'id'                => 'min_sum_for_discount',
				'title'             => __( 'Минимальная сумма корзины для скидки', 'lpost-wc-delivery' ),
				/* translators: %s are links. */
				'desc'              => '',
				'type'              => 'text',
				'input_class'       => ['short'],
				'custom_attributes' => array(
					//'required' => true,
				),
			),
			'discount_percent' => array(
				'id'                => 'discount_percent',
				'title'             => __( 'Размер скидки, %', 'lpost-wc-delivery' ),
				/* translators: %s are links. */
				'desc'              => '',
				'type'              => 'text',
				'input_class'       => ['short'],
				'custom_attributes' => array(
					//'required' => true,
				),
			),
			'issue_type' => array(
				'id'    => 'issue_type',
				'title' => __( 'Тип выдачи отправлений', 'lpost-wc-delivery' ),
				'type'  => 'select',
				'class'  => 'wc-enhanced-select',
				'default'  => 'pickup',
				'options' => array(
					0 => __( 'Полная без вскрытия', 'lpost-wc-delivery' ),
					1 => __( 'Полная со вскрытием', 'lpost-wc-delivery' ),
					2 => __( 'Частичная', 'lpost-wc-delivery' ),
				),
			),
			'regions_restrictions' => array(
				'id'    => 'regions_restrictions',
				'title' => __( 'Ограничение доставки по регионам', 'lpost-wc-delivery' ),
				'type'  => 'multiselect',
				'class'  => 'wc-enhanced-select',
				'default'  => '',
				'options' => $regionsListOptions,
				'custom_attributes' => array(
					//'multiple' => 'multiple',
				),
			),
			'hr' => array(
				'title' => __( 'Габариты товара по умолчанию', 'lpost-wc-delivery' ),
				'desc'  => __( 'These values will be taken into account in the absence of overall characteristics of the product.', 'cdek-for-woocommerce' ),
				'type'  => 'title',
			),
			'lab_dimensions_item_length' => array(
				'id'    => 'lab_dimensions_item_length',
				'title' => __( 'Длина (см.)', 'lpost-wc-delivery' ),
				'type'  => 'number',
			),
			'lab_dimensions_item_width' => array(
				'title' => __( 'Ширина (см.)', 'lpost-wc-delivery' ),
				'type'  => 'number',
				'id'    => 'lab_dimensions_item_width',
			),
			'lab_dimensions_item_height' => array(
				'title' => __( 'Высота (см.)', 'lpost-wc-delivery' ),
				'type'  => 'number',
				'id'    => 'lab_dimensions_item_height',
			),
			'lab_dimensions_item_weight' => array(
				'title' => __( 'Вес (г.)', 'lpost-wc-delivery' ),
				'type'  => 'number',
				'id'    => 'lab_dimensions_item_weight',
			),
			/*'token' => array(
				'id'    => 'auth_token',
				'title' => __( 'Token', 'lpost-wc-delivery' ),
				'desc'  => __( 'Temp token to provide services', 'lpost-wc-delivery' ),
				'type'  => 'text',
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
			'token_updated' => array(
				'id'    => 'token_updated',
				'title' => __( 'Token Updated', 'lpost-wc-delivery' ),
				'desc'  => __( '' ),
				'type'  => 'text',
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),*/
			/*'title' => array(
				'type' => 'sectionend',
			),*/
		);
	}

	/**
	 * Calculate shipping rate
	 *
	 * @param array $package Package of items from cart.
	 */
	public function calculate_shipping( $package = array() ) 
	{
		$delivType = $this->get_option('deliv_type', '');
		$issueType = $this->get_option('issue_type', '');
		$label     = $this->get_option('title', '');
		$ID_Sklad     = $this->get_option('receive_id_warehouse', '3');
		$regionsRestrs = $this->get_option('regions_restrictions', array());
		$time         = '';
		$to_code      = '';
		$rate         = array();
		//$services     = $this->services ? $this->services : array();
		$from_country = get_option( 'woocommerce_default_country', 'RU' );
		$from_country = $from_country ? explode( ':', $from_country )[0] : 'RU';
		$to_country   = $package['destination']['country'] ? $package['destination']['country'] : 'RU';
		$to_postcode  = wc_format_postcode( $package['destination']['postcode'], $to_country );
		$to_state     = $package['destination']['state'];
		$to_city      = $package['destination']['city'];
		
		$postData = (!empty($_POST['post_data']) ? $this->helper->proper_parse_str($_POST['post_data']) : array());

		if (!$to_city) {
			$this->add_rate(
				array(
					'label'   => $label,
					'cost'    => 0,
					'taxes'   => false,
					'package' => $package,
				)
			);

			return false;
		}

		setlocale(LC_TIME, "ru_RU.utf8");

		$token = $this->helper->getAuthToken();
		
		$to_city = mb_strtolower($to_city);
		$to_city = trim(str_ireplace(array('г.'), '', $to_city));

		$points = $this->helper->GetPickupPoints($token, $delivType);
		$cityPointsArr = array();

		foreach ($points->PickupPoint as $pointKey=>$point) {
			if ($to_city == mb_strtolower($point->CityName)) {
				$cityPointsArr[] = $point;
			}
		}

		if (!$cityPointsArr) 
		{
			return false;
		}
		//разрешенные региона
		if (count($regionsRestrs) > 0 and !in_array($cityPointsArr[0]->ID_Region, $regionsRestrs)) 
		{
			return false;
		}

		$goodsDimensions = $this->get_goods_dimensions($package);

		$ID_PickupPoint = (isset($postData['ID_PickupPoint']) and $delivType === 'pickup') ? $postData['ID_PickupPoint'] : $cityPointsArr[0]->ID_PickupPoint;

		$json = array(
			'ID_Sklad' => $ID_Sklad,
			'ID_PickupPoint' => $ID_PickupPoint,
			'Weight' => $goodsDimensions['weight'],
			'Volume' => $goodsDimensions['volume'],
			'SumPayment' => 0,
			'Value' => WC()->cart->get_subtotal(),
		);

		$pickup = true;
		$courier_coords = (isset($postData['courier_coords']) ? $postData['courier_coords'] : '');
		$courierCalendar = (isset($postData['delivery_date']) ? $postData['delivery_date'] : '');
		$courierTime = (isset($postData['delivery_interval']) ? $postData['delivery_interval'] : '');

		if ($delivType !== 'pickup') { //если курьер
			$pickup = false;

			if ($courier_coords) {
				$arCoords = json_decode($courier_coords);
				$json['Latitude'] = $arCoords[0];
				$json['Longitude'] = $arCoords[1];
			}
			$json['isNotExactAddress'] = true;
			$json['Address'] = $cityPointsArr[0]->CityName;

			foreach ($cityPointsArr as $cityPoint) {
				foreach ($cityPoint->Zone as $cityZone) {
					$cityZone->WKT = json_decode('{'. $cityZone->WKT .'}'); //волшебное превращение

					foreach ($cityZone->WKT->Coordinates as &$zoneBlock) {
						foreach ($zoneBlock as &$innerCoords) {
							$innerCoords = array_reverse($innerCoords);
						}
					} //переворачивание координат
				}
			}
		} else { //самовывоз
			$dayKeys = array(
				'понедельник' => array('day' => 0, 'title' => 'пн'),
				'вторник' => array('day' => 1, 'title' => 'вт'),
				'среда' => array('day' => 2, 'title' => 'ср'),
				'четверг' => array('day' => 3, 'title' => 'чт'),
				'пятница' => array('day' => 4, 'title' => 'пт'),
				'суббота' => array('day' => 5, 'title' => 'сб'),
				'воскресенье' => array('day' => 6, 'title' => 'вс'),
			);

			foreach ($cityPointsArr as $cityPoint) {
				foreach ($cityPoint->PickupPointWorkHours as $dayWork) {
					$dayWork->shortTitle = $dayKeys[$dayWork->Day]['title'];
					$dayWork->From = preg_replace('/:00$/m', '', $dayWork->From);
					$dayWork->To = preg_replace('/:00$/m', '', $dayWork->To);

					$sortedDaysInfo[$dayKeys[$dayWork->Day]['day']] = $dayWork;
					//переворачивание координат
				}

				$cityPoint->SimpleWorkHours = $sortedDaysInfo;
				$cityPoint->DeliveryDate = strftime('%d %B', strtotime('+'.$cityPoint->DayLogistic.' days'));
			}
		}

		$response = $this->helper->makeCalcRequest($json);
		$body = json_decode($response['body']);
		if (!$body->JSON_TXT) {
			$this->maybe_print_error($body->errorMessage);
			return false;
		}

		$body = json_decode($body->JSON_TXT);
		$decodedBody = $body->JSON_TXT[0];
		$cost = $decodedBody->SumCost;

		$delivTime = $decodedBody->PossibleDateDeliv ?? '';

		$label = ($cost == 0 ) ? $label . ' (' . $cost .' руб.)' : $label;
		//$label.= '<br/><select name="del-type"><option>Доставка</option><option>Самовывоз</option></select>';
		$fee = (float)$this->get_option('lab_shipment_fee', '');
		$minSumForDiscount = (float)$this->get_option('min_sum_for_discount', '');
		$discountPercent = (int)$this->get_option('discount_percent', '');
		$fee = (float)$this->get_option('lab_shipment_fee', '');


		if ($fee) $cost += $cost * ( $fee / 100 ); //наценка
		if ($minSumForDiscount) { //скидка
			if (WC()->cart->get_subtotal() >= $minSumForDiscount) $cost = $cost - $cost * ($discountPercent / 100);
		} //скидка

		$this->add_rate(
			array(
				'id'        => $this->get_rate_id(),
				'label'     => $label,
				'cost'      => $cost,
				'package'   => $package,
				'meta_data' => array(
					'deliv_type' => $delivType,
					'issue_type' => $issueType,
					'deliv_time' => $delivTime,
					'req_json' => json_encode($json),
					'tariff_name' => '',
					'ID_Sklad' => $ID_Sklad,
					'ID_PickupPoint' => $ID_PickupPoint,
					'pickup_points' => json_encode($cityPointsArr),
					'courier_coords' => $courier_coords,
					'delivery_date' => $courierCalendar,
					'delivery_interval' => $courierTime,
				),
			)
		);
		return true;
	}
	

	/**
	 * See if shipping is available based on the package and cart.
	 *
	 * @param array $package Shipping package.
	 * @return bool
	 */
	public function is_available( $package ) 
	{
		$is_available = false;
		$delivType = $this->get_option('deliv_type', '');

		$token = $this->helper->getAuthToken($this->apikey);
		if (!$token) 
		{
			$this->maybe_print_error('No auth-token');
		} 
		else 
		{
			$points = $this->helper->GetPickupPoints($token, $delivType);
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

	/**
	 * Check all condition to display a method before calculation
	 *
	 * @param array $package Shipping package.
	 *
	 * @return bool
	 */
	public function check_condition_for_disable( $package ) {
		$total_val = WC()->cart->get_cart_subtotal();
		$weight    = wc_get_weight( WC()->cart->get_cart_contents_weight(), 'g' );

		return false;

		// check if cost is less than provided in options.
		if ( $this->cond_min_cost && intval( $this->cond_min_cost ) > 0 && $total_val < $this->cond_min_cost ) {
			//return true;
		}

		// check conditional weights.
		if ( ( $this->cond_min_weight && $weight < intval( $this->cond_min_weight ) ) || ( $this->cond_max_weight && $weight > intval( $this->cond_max_weight ) ) ) {
			//return true;
		}

		// check if has specific shipping class.
		if ( isset( $this->cond_has_shipping_class ) ) {
			$found_shipping_classes  = $this->find_shipping_classes( $package );
			$is_shipping_class_found = false;
			foreach ( $found_shipping_classes as $shipping_class => $products ) {
				$shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
				if ( $shipping_class_term && $shipping_class_term->term_id && in_array( (string) $shipping_class_term->term_id, $this->cond_has_shipping_class, true ) ) {
					$is_shipping_class_found = true;
					break;
				}
			}

			if ( $is_shipping_class_found ) {
				//return true;
			}
		}

		return false;
	}

	/**
	 * Additional percentage cost.
	 *
	 * @param int $shipping_cost Shipping cost.
	 *
	 * @return float|int
	 * @since 1.0.3
	 */
	public function get_percentage_cost( $shipping_cost ) {
		$percentage = floatval( $this->add_percentage_cost ) / 100;
		$type       = $this->add_percentage_cost_type;

		if ( ! $percentage ) {
			return 0;
		}

		switch ( $type ) {
			case 'percentage_shipping_cost':
				return $shipping_cost * $percentage;
			case 'percentage_total':
				return ( WC()->cart->get_subtotal() + WC()->cart->get_fee_total() + $shipping_cost ) * $percentage;
			default:
				return WC()->cart->get_subtotal() * $percentage;
		}
	}

	/**
	 * Add additional cost based on shipping classes
	 *
	 * @param array $package Shipping package.
	 *
	 * @return int
	 */
	public function get_shipping_class_cost( $package ) {
		$shipping_classes = WC()->shipping()->get_shipping_classes();
		$cost             = 0;

		if ( ! empty( $shipping_classes ) && isset( $this->class_cost_calc_type ) ) {
			$found_shipping_classes = $this->find_shipping_classes( $package );
			$highest_class_cost     = 0;

			foreach ( $found_shipping_classes as $shipping_class => $products ) {
				// Also handles BW compatibility when slugs were used instead of ids.
				$shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
				$class_cost_string   = $shipping_class_term && $shipping_class_term->term_id ? $this->get_option( 'class_cost_' . $shipping_class_term->term_id, $this->get_option( 'class_cost_' . $shipping_class, '' ) ) : $this->get_option( 'no_class_cost', '' );

				if ( '' === $class_cost_string ) {
					continue;
				}

				$class_cost = $this->evaluate_cost(
					$class_cost_string,
					array(
						'qty'  => array_sum( wp_list_pluck( $products, 'quantity' ) ),
						'cost' => array_sum( wp_list_pluck( $products, 'line_total' ) ),
					)
				);

				if ( 'class' === $this->class_cost_calc_type ) {
					$cost += $class_cost;
				} else {
					$highest_class_cost = $class_cost > $highest_class_cost ? $class_cost : $highest_class_cost;
				}
			}

			if ( 'order' === $this->class_cost_calc_type && $highest_class_cost ) {
				$cost += $highest_class_cost;
			}
		}

		return $cost;
	}

	/**
	 * Finds and returns shipping classes and the products with said class.
	 *
	 * @param mixed $package Package of items from cart.
	 *
	 * @return array
	 */
	public function find_shipping_classes( $package ) {
		$found_shipping_classes = array();

		foreach ( $package['contents'] as $item_id => $values ) {
			if ( $values['data']->needs_shipping() ) {
				$found_class = $values['data']->get_shipping_class();

				if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
					$found_shipping_classes[ $found_class ] = array();
				}

				$found_shipping_classes[ $found_class ][ $item_id ] = $values;
			}
		}

		return $found_shipping_classes;
	}

	/**
	 * Work out fee (shortcode).
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public function fee( $atts ) {
		$atts = shortcode_atts(
			array(
				'percent' => '',
				'min_fee' => '',
				'max_fee' => '',
			),
			$atts,
			'fee'
		);

		$calculated_fee = 0;

		if ( $atts['percent'] ) {
			$calculated_fee = $this->fee_cost * ( floatval( $atts['percent'] ) / 100 );
		}

		if ( $atts['min_fee'] && $calculated_fee < $atts['min_fee'] ) {
			$calculated_fee = $atts['min_fee'];
		}

		if ( $atts['max_fee'] && $calculated_fee > $atts['max_fee'] ) {
			$calculated_fee = $atts['max_fee'];
		}

		return $calculated_fee;
	}


	/**
	 * Evaluate a cost from a sum/string.
	 *
	 * @param string $sum Sum of shipping.
	 * @param array  $args Args.
	 *
	 * @return string
	 */
	protected function evaluate_cost( $sum, $args = array() ) {
		include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';

		// Allow 3rd parties to process shipping cost arguments.
		$args           = apply_filters( 'woocommerce_evaluate_shipping_cost_args', $args, $sum, $this );
		$locale         = localeconv();
		$decimals       = array(
			wc_get_price_decimal_separator(),
			$locale['decimal_point'],
			$locale['mon_decimal_point'],
			',',
		);
		$this->fee_cost = $args['cost'];

		// Expand shortcodes.
		add_shortcode( 'fee', array( $this, 'fee' ) );

		$sum = do_shortcode(
			str_replace(
				array(
					'[qty]',
					'[cost]',
				),
				array(
					$args['qty'],
					$args['cost'],
				),
				$sum
			)
		);

		remove_shortcode( 'fee', array( $this, 'fee' ) );

		// Remove whitespace from string.
		$sum = preg_replace( '/\s+/', '', $sum );

		// Remove locale from string.
		$sum = str_replace( $decimals, '.', $sum );

		// Trim invalid start/end characters.
		$sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

		// Do the math.
		return $sum ? WC_Eval_Math::evaluate( $sum ) : 0;
	}

	/**
	 * Get all goods dimensions
	 *
	 * @param array $package Package of items from cart.
	 * @param array $services Method services.
	 *
	 * @return array
	 */
	public function get_goods_dimensions( $package ) {

		$goodsDimensions = array(
			'weight' => 0,
			'volume' => 0,
		);

		$item_stock_weight = ceil( $this->get_option('lab_dimensions_item_weight', 1000) );
		$item_stock_length = ceil( $this->get_option('lab_dimensions_item_length', 40) );
		$item_stock_width  = ceil( $this->get_option('lab_dimensions_item_width', 30) );
		$item_stock_height = ceil( $this->get_option('lab_dimensions_item_height', 15) );
		$volume = 0;

		foreach ( $package['contents'] as $item_id => $item_values ) 
		{
			if ( ! $item_values['data']->needs_shipping() ) 
			{
				continue;
			}

			$weight = (wc_get_weight( floatval( $item_values['data']->get_weight() ), 'g' )) ? : $item_stock_weight;
			$length = wc_get_dimension( floatval( $item_values['data']->get_length() ), 'cm' ) ? : $item_stock_length;
			$width  = wc_get_dimension( floatval( $item_values['data']->get_width() ), 'cm' ) ? : $item_stock_width;
			$height = wc_get_dimension( floatval( $item_values['data']->get_height() ), 'cm' ) ? : $item_stock_height;
			$volume = $length * $width * $height * $item_values['quantity'];

			$goodsDimensions['goods'][] = array(
				'length' => $length,
				'width' => $width,
				'height' => $height,
				'weight' => $weight,
				'volume' => $volume,
				'quantity' => $item_values['quantity'],
			);

			$goodsDimensions['weight'] += $weight;
			$goodsDimensions['volume'] += $volume;
		}

		return $goodsDimensions;
	}

	/**
	 * Print error for debugging
	 *
	 * @param string $message custom error message.
	 */
	public function maybe_print_error( $message = '' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$this->add_rate(
			array(
				'id'        => $this->get_rate_id(),
				'label'     => $message ? $this->title . '. ' . $message : $this->title . '. ' . __( 'Ошибка при расчете. Это сообщение и метод видны только администратору сайта в целях отладки. ', 'lpost-wc-delivery' ),
				'cost'      => 0,
				'meta_data' => array( 'lpost_wc_error' => true ),
			)
		);
	}


	/**
	 * Check if free shipping is available based on the package and cart.
	 *
	 * @return bool
	 */
	public function is_free_shipping_available() {
		$has_coupon         = false;
		$has_met_min_amount = false;

		if ( in_array( $this->free_shipping_cond, array( 'coupon', 'either', 'both' ), true ) ) {
			$coupons = WC()->cart->get_coupons();

			if ( $coupons ) {
				foreach ( $coupons as $code => $coupon ) {
					if ( $coupon->is_valid() && $coupon->get_free_shipping() ) {
						$has_coupon = true;
						break;
					}
				}
			}
		}

		if ( in_array( $this->free_shipping_cond, array( 'min_amount', 'either', 'both' ), true ) ) {
			$total = WC()->cart->get_displayed_subtotal();

			if ( WC()->cart->display_prices_including_tax() ) {
				$total = $total - WC()->cart->get_discount_tax();
			}

			if ( 'no' === $this->free_shipping_ignore_discounts ) {
				$total = $total - WC()->cart->get_discount_total();
			}

			$total = round( $total, wc_get_price_decimals() );

			if ( $total >= $this->free_shipping_cond_amount ) {
				$has_met_min_amount = true;
			}
		}

		switch ( $this->free_shipping_cond ) {
			case 'min_amount':
				$is_available = $has_met_min_amount;
				break;
			case 'coupon':
				$is_available = $has_coupon;
				break;
			case 'both':
				$is_available = $has_met_min_amount && $has_coupon;
				break;
			case 'either':
				$is_available = $has_met_min_amount || $has_coupon;
				break;
			default:
				$is_available = true;
				break;
		}

		return $is_available;
	}
}
