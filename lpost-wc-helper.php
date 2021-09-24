<?php


class LPost_WC_Helper extends LPost_WC
{
	/**
	 * @var void
	 */
	private $testMode;
	/**
	 * @var string
	 */
	private $url;

	public function __construct()
	{
		$this->testMode = get_option('test_mode', 'no');
		$this->url = ($this->testMode === 'yes') ? 'https://apitest.l-post.ru/' : 'https://api.l-post.ru/';
	}
	
	/**
	 * @return bool|mixed
	 */
	public function getAuthToken() {

		$token = false;
		$secret = ($this->testMode === 'yes') ? get_option('lpost_wc_api_secret', '') : get_option('lpost_wc_api_prod_secret', '');
		$tokenEx = get_option('lp-token', '');
		$tokenDate = get_option('lp-token-updated', '');

		if ($tokenEx) {
			if (((time() - strtotime($tokenDate)) / 60) < 55) return $tokenEx;
		}

		$response = wp_remote_get($this->url, array(
			'body' => array(
				'method' => 'Auth',
				'secret' => $secret,
			)
		));

		$respMessage = json_decode($response['body']);
		if (!isset($respMessage->token)) return false;

		update_option('lp-token', $respMessage->token);
		update_option('lp-token-updated', date('d-m-Y H:i'));
		$token = $respMessage->token;

		//echo '<pre><h2>token</h2>'.print_r($token,1).'</pre>';die();

		return $token;
	}
	
	public function createOrders($orderIDs) {
		if (!$orderIDs) return false;

		$token = $this->getAuthToken();
		$orderInfo = array();
		foreach ($orderIDs as $orderID) {
			$this->prepareCreateOrder($orderID, $orderInfo);
		}

		$json = array(
			'Order' => $orderInfo
		);
		$response = wp_remote_post($this->url, array(
			'timeout' => 30,
			'body' => array(
				'method' => 'CreateOrders',
				'token' => $token,
				'ver' => '1',
				'json' => json_encode($json)
			)
		));

		if (isset($response->errors)) return $response->errors;

		$responseDecoded = json_decode($response['body']);
		$finalResponse = (isset($responseDecoded->JSON_TXT)) ? json_decode($responseDecoded->JSON_TXT) : $responseDecoded;

		return $finalResponse;
	}
	
	public function updateOrder($orderID) 
	{
		$ID_Order = get_post_meta($orderID, 'shipment_id', true);
		if (!$orderID or !$ID_Order) return false;

		$token = $this->getAuthToken();
		$orderInfo = array();

		$this->prepareCreateOrder($orderID, $orderInfo);
		$orderInfo[0]['ID_Order'] = $ID_Order;

		$json = array(
			'Order' => $orderInfo
		);

		$response = wp_remote_request($this->url, array(
			'method' => 'PUT',
			'timeout' => 30,
			'body' => array(
				'method' => 'UpdateOrders',
				'token' => $token,
				'ver' => '1',
				'json' => json_encode($json)
			)
		));

		if ($response->errors) return $response->errors;

		$responseDecoded = json_decode($response['body']);
		$finalResponse = ($responseDecoded->JSON_TXT) ? json_decode($responseDecoded->JSON_TXT) : $responseDecoded;

		$shipment_uml = get_post_meta($orderID, 'shipment_uml', true);
		foreach ($finalResponse as $resp) {
			if (!empty($resp->LabelUml) and !$shipment_uml) update_post_meta($orderID, 'shipment_uml', $resp->LabelUml);
		}

		return $finalResponse;
	}

	public function prepareCreateOrder($orderID, &$orderInfo) {
		$order = wc_get_order($orderID);
		$natOrderShpMeta = array();

		$orderShippings = $order->get_items( 'shipping' );
		foreach ($orderShippings as $oKey => $orderShipping) {
			$metaMess = $orderShipping->get_formatted_meta_data();
			foreach ($metaMess as $mess) {
				$natOrderShpMeta[$mess->key] = $mess->value;
			}
		}

		if (!isset($natOrderShpMeta['req_json'])) return false;
		$reqJSON = json_decode($natOrderShpMeta['req_json']);

		$totalQty = 0;
		$products = array();
		$orderProducts = $order->get_items( 'line_item' );

		foreach ($orderProducts as $orderProduct) {
			$orderDataProduct = $orderProduct->get_data();
			$products[] = array(
				'NameShort' => $orderDataProduct['name'],
				'Price' => $orderDataProduct['total'] / $orderDataProduct['quantity'],
				'NDS' => 10,
				'Quantity' => $orderDataProduct['quantity'],
			);
			$totalQty += $orderDataProduct['quantity'];
		}

		$productVolumeNat = $reqJSON->Volume / $totalQty;
		$productWeightNat = round($reqJSON->Weight / $totalQty);
		$productParcelSide = round(($productVolumeNat ** (1 / 3)) * 10);
		
		$arOrderAddress = $order->get_address('shipping');
		$arAddress = array();
		if (!empty($arOrderAddress['city'])) $arAddress[] = $arOrderAddress['city'];
		if (!empty($arOrderAddress['address_1'])) $arAddress[] = str_ireplace('Самовывоз: ', '', $arOrderAddress['address_1']);
		if (!empty($arOrderAddress['address_2'])) $arAddress[] = $arOrderAddress['address_2'];
		
		$ID_Sklad = ($order->get_meta('receive_id_warehouse')) ? : $natOrderShpMeta['ID_Sklad'];
		$ID_Pickupoint = ($order->get_meta('pickuppoint_id')) ? : $natOrderShpMeta['ID_PickupPoint'];
		$delivType = ($order->get_meta('deliv_type')) ? : $natOrderShpMeta['deliv_type'];
		$IssueType = ($order->get_meta('issue_type')) ? : 0;
		$courier_coords = ($order->get_meta('courier_coords')) ? : null;
		$calendarValue = ($order->get_meta('delivery_date')) ? : null;
		$timeValue = ($order->get_meta('delivery_interval')) ? : null;
		
		$courier_porch = ($order->get_meta('_lp_courier_porch')) ? : null;
		$courier_floor = ($order->get_meta('_lp_courier_floor')) ? : null;
		$courier_flat = ($order->get_meta('_lp_courier_flat')) ? : null;
		$courier_code = ($order->get_meta('_lp_courier_code')) ? : null;
	

		foreach ($products as $product) {
			$cargoes[] = array(
				'Weight' => $productWeightNat,
				'Length' => $productParcelSide,
				'Width' => $productParcelSide,
				'Height' => $productParcelSide,
				'Product' => array($product),
			);
		}

		$SumPayment = 0;
		$SumPrePayment = $order->get_total();

		if ($order->get_status() !== 'completed') {
			$SumPayment = $order->get_total();
			$SumPrePayment = 0;
		}

		$phone = $order->get_billing_phone();
		if ($phone) $phone = preg_replace("/[^0-9]/", '', $phone);
		if (strlen($phone) > 10) $phone = preg_replace('/^7|^8/m', '', $phone);

		$infoArrToAdd = array(
			'PartnerNumber' => $orderID,
			'ID_Sklad' => $ID_Sklad,
			//'ID_PickupPoint' => $ID_Pickupoint,
			'IssueType' => $IssueType,
			//'Address' => implode(', ', $arAddress),
			'Value' => $order->get_total(),
			'SumPayment' => $SumPayment,
			'SumPrePayment' => $SumPrePayment,
			'SumDelivery' => $order->get_shipping_total(),
			'CustomerNumber' => $orderID,
			'CustomerName' => $order->get_formatted_billing_full_name(),
			'Phone' => $phone,
			'Cargoes' => $cargoes,
			'Email' => $order->get_billing_email(),
		);


		if ($delivType !== 'courier') {
			$infoArrToAdd['ID_PickupPoint'] = $ID_Pickupoint;
		} else {
			$infoArrToAdd['Address'] = implode(', ', $arAddress);
			if ($courier_coords) {
				$courier_coords = preg_replace("/[^,.0-9]/", '', $courier_coords);
				$explodedCoords = explode(',', $courier_coords);
				$infoArrToAdd['Latitude'] = trim($explodedCoords[0]);
				$infoArrToAdd['Longitude'] = trim($explodedCoords[1]);
			}
			if ($timeValue) $infoArrToAdd['TypeIntervalDeliv'] = $timeValue;
			if ($calendarValue) $infoArrToAdd['DateDelivery'] = $calendarValue;
			
			if ($courier_porch) $infoArrToAdd['Porch'] = $courier_porch;
			if ($courier_floor) $infoArrToAdd['Floor'] = $courier_floor;
			if ($courier_flat) $infoArrToAdd['Flat'] = $courier_flat;
			if ($courier_code) $infoArrToAdd['Code'] = $courier_code;
			
		}
		$orderInfo[] = $infoArrToAdd;

		return true;
	}

	public function getInfoForLPostOrders($orderIDs) {
		$token = $this->getAuthToken();

		foreach ($orderIDs as $orderID) {
			$json[] = array('ID_Order' => $orderID);
		}

		$response = wp_remote_get($this->url, array(
			'body' => array(
				'method' => 'GetStateOrders',
				'token' => $token,
				'ver' => '1',
				'json' => json_encode($json)
			)
		));

		$responseDecoded = json_decode($response['body']);
		$finalResponse = json_decode($responseDecoded->JSON_TXT);

		return $finalResponse;
	}

	public function createInvoice($shipment_id) {
		if (!$shipment_id) return false;

		$token = $this->getAuthToken();

		$json = array(
			'Act' => array(
				array('ID_Order' => $shipment_id)
			)
		);

		$response = wp_remote_post($this->url, array(
			'body' => array(
				'method' => 'CreateAct',
				'token' => $token,
				'ver' => '1',
				'json' => json_encode($json)
			)
		));

		$responseDecoded = json_decode($response['body']);
		$finalResponse = json_decode($responseDecoded->JSON_TXT);

		return $finalResponse;
	}

	public function getPickupPointsOptions() 
	{
		$token = $this->getAuthToken();
		$optionsRawPickup = $this->GetPickupPoints($token, 'pickup');
		$optionsRawCourier = $this->GetPickupPoints($token, 'courier');
		$optionsRaw = array();
		if (!empty($optionsRawPickup->PickupPoint)) $optionsRaw = array_merge($optionsRaw, $optionsRawPickup->PickupPoint);
		if (!empty($optionsRawCourier->PickupPoint)) $optionsRaw = array_merge($optionsRaw, $optionsRawCourier->PickupPoint);

		//echo '<pre><h2>optionsRaw</h2>'.print_r($optionsRaw,1).'</pre>';die();
		$options = array();
		foreach ($optionsRaw as $item) 
		{
			$options[$item->ID_PickupPoint] = $item->CityName.', '.$item->Address;
		}

		asort($options);
		return $options;
	}

	public function GetPickupPoints($token, $delivType = 'pickup', $forceUpdate = false) {
		$points = false;
		if (!$token) return array();

		$pickupFileName = __DIR__.'/pickup-points-'. $delivType .'.json';
		if (!file_exists($pickupFileName)) $pickupFile = fopen($pickupFileName, 'a+');

		if (filesize($pickupFileName) === 0 or date('d', filemtime($pickupFileName)) !== date('d') or $forceUpdate) {
			$json = array(
				'isCourier' => ($delivType === 'pickup') ? '0' : '1'
			);
			$response = wp_remote_get($this->url.'?method=GetPickupPoints', array(
				'timeout' => 30,
				'body' => array(
					'ver' => 1,
					'token' => $token,
					'json' => json_encode($json)
				)
			)); //запрос на получение

			$body = json_decode($response['body']);

			if (isset($body->errorMessage)) return array();

			//fclose($pickupFile);
			$pickupFile = fopen($pickupFileName, 'w+');
			fwrite($pickupFile, $response['body']);
			$points = $body;
		} else {
			$points = json_decode(file_get_contents($pickupFileName));
		}
		$res = json_decode($points->JSON_TXT);
		return (!empty($res) ? $res : array());
	}

	/**
	 * @return bool|mixed
	 */
	public function GetReceivePoints() {
		$token = $this->getAuthToken();

		$pickupFileName = __DIR__.'/receive-points.json';
		if (!file_exists($pickupFileName)) $pickupFile = fopen($pickupFileName, 'a+');

		if (filesize($pickupFileName) === 0 or date('d', filemtime($pickupFileName)) !== date('d')) {
			$response = wp_remote_get($this->url.'?method=GetReceivePoints', array(
				'timeout' => 10,
				'body' => array(
					'token' => $token,
					'ver' => '1',
					'json' => '{}'
				)
			));

			$body = json_decode($response['body']);
			if (isset($body->errorMessage)) return false;

			$pickupFile = fopen($pickupFileName, 'w+');
			fwrite($pickupFile, $response['body']);
			$points = $body;
		} else {
			$points = json_decode(file_get_contents($pickupFileName));
		}

		return json_decode($points->JSON_TXT);
	}
	
	public function getSortedReceivePointsOptions() 
	{
		$receiveFieldOptions = array();
		$receivePoints = $this->GetReceivePoints();
		if (!empty($receivePoints)) 
		{
			foreach ($receivePoints->ReceivePoints as $point) 
			{
				$receiveFieldOptions[$point->ID_Sklad] = $point->City.', '.$point->Address;
			}
			asort($receiveFieldOptions);
		}
		return $receiveFieldOptions;
	}
	
	public function makeCalcRequest($json) {
		$token = $this->getAuthToken();

		$response = wp_remote_get($this->url.'?method=GetServicesCalc', array(
			'timeout' => 10,
			'body' => array(
				'token' => $token,
				'ver' => '1',
				'json' => json_encode($json)
			)
		));

		return $response;
	}
	
	public function proper_parse_str($queryString, $argSeparator = '&', $decType = PHP_QUERY_RFC1738) 
	{
		if (empty($queryString)) return array();
		$result = array();
		$parts = explode($argSeparator, $queryString);
		foreach ($parts as $part) 
		{
			list($paramName, $paramValue) = explode('=', $part, 2);
			switch ($decType) 
			{
				case PHP_QUERY_RFC3986:
					$paramName = rawurldecode($paramName);
					$paramValue = rawurldecode($paramValue);
				break;
				case PHP_QUERY_RFC1738:
				default:
					$paramName = urldecode($paramName);
					$paramValue = urldecode($paramValue);
				break;
			}
			if (preg_match_all('/\[([^\]]*)\]/m', $paramName, $matches)) 
			{
				$paramName = substr($paramName, 0, strpos($paramName, '['));
				$keys = array_merge(array($paramName), $matches[1]);
			} 
			else 
			{
				$keys = array($paramName);
			}
			$target = &$result;
			foreach ($keys as $index) 
			{
				if ($index === '') 
				{
					if (isset($target)) 
					{
						if (is_array($target)) 
						{
							$intKeys = array_filter(array_keys($target), 'is_int');
							$index  = count($intKeys) ? max($intKeys)+1 : 0;
						} 
						else 
						{
							$target = array($target);
							$index  = 1;
						}
					} 
					else 
					{
						$target = array();
						$index = 0;
					}
				} 
				elseif (isset($target[$index]) && !is_array($target[$index])) 
				{
					$target[$index] = array($target[$index]);
				}
				$target = &$target[$index];
			}
			
			if (is_array($target)) 
			{
				$target[] = $paramValue;
			} 
			else 
			{
				$target = $paramValue;
			}
		}
		
		return $result;
	}
}