<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Rexel API client for the Decouverte price and stock endpoints.
 */
class RexelApi
{
	const CLIENT_VERSION = '1.0.2';
	const PRICE_PATH = '/external/productprices/productSalePrices';
	const STOCK_PATH = '/external/stocks/positions';

	/** @var array<string,mixed> */
	private $config;

	/** @var string */
	public $error = '';

	/** @var string|null */
	private $oauthAccessToken = null;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $config Configuration
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
	}

	/**
	 * Fetch price and stock for a product.
	 *
	 * @param string $supplierCode Supplier code
	 * @param string $supplierComRef Supplier commercial reference
	 * @param int    $qty Ordered quantity
	 * @return array<string,mixed>
	 */
	public function fetchProductPriceAndStock($supplierCode, $supplierComRef, $qty)
	{
		$price = $this->fetchPrice($supplierCode, $supplierComRef, $qty);
		if (empty($price['success'])) {
			return $price;
		}

		$stock = $this->fetchStock($supplierCode, $supplierComRef, $qty);
		if (empty($stock['success'])) {
			return $stock;
		}

		return array(
			'success' => true,
			'status' => 'ok',
			'price' => $price['price'],
			'stock' => $stock['stock'],
			'http_status' => max((int) $price['http_status'], (int) $stock['http_status']),
			'message' => '',
			'price_detail' => $price['detail'],
			'stock_detail' => $stock['detail'],
		);
	}

	/**
	 * Fetch customer net price.
	 *
	 * @param string $supplierCode Supplier code
	 * @param string $supplierComRef Supplier commercial reference
	 * @param int    $qty Ordered quantity
	 * @return array<string,mixed>
	 */
	public function fetchPrice($supplierCode, $supplierComRef, $qty)
	{
		$commonPayload = $this->buildCommonPayload($supplierCode, $supplierComRef, $qty, true, false);
		if ($commonPayload === false) {
			return $this->buildClientError($this->error);
		}

		$payload = array(
			'getProductSalePricesExt' => $commonPayload,
		);

		$response = $this->postJson(self::PRICE_PATH, $payload);
		if (empty($response['success']) && $this->isRestJsonSchemaError($response['message'])) {
			$commonPayload = $this->buildCommonPayload($supplierCode, $supplierComRef, $qty, true, true);
			if ($commonPayload === false) {
				return $this->buildClientError($this->error);
			}

			$this->debugLog('RexelSync API price retry with string orderingQty after BW-RESTJSON-100016');
			$payload = array(
				'getProductSalePricesExt' => $commonPayload,
			);
			$response = $this->postJson(self::PRICE_PATH, $payload);
		}
		if (empty($response['success']) && $this->isRestJsonSchemaError($response['message'])) {
			$commonPayload = $this->buildCommonPayload($supplierCode, $supplierComRef, $qty, true, false, true);
			if ($commonPayload === false) {
				return $this->buildClientError($this->error);
			}

			$this->debugLog('RexelSync API price retry with numeric scalar fields after BW-RESTJSON-100016');
			$payload = array(
				'getProductSalePricesExt' => $commonPayload,
			);
			$response = $this->postJson(self::PRICE_PATH, $payload);
		}
		if (empty($response['success']) && $this->isRestJsonSchemaError($response['message'])) {
			$commonPayload = $this->buildCommonPayload($supplierCode, $supplierComRef, $qty, true, false, false, false);
			if ($commonPayload === false) {
				return $this->buildClientError($this->error);
			}

			$this->debugLog('RexelSync API price retry with single productDetails object after BW-RESTJSON-100016');
			$payload = array(
				'getProductSalePricesExt' => $commonPayload,
			);
			$response = $this->postJson(self::PRICE_PATH, $payload);
		}
		if (empty($response['success'])) {
			return array(
				'success' => false,
				'status' => 'error',
				'price' => null,
				'stock' => null,
				'http_status' => (int) $response['http_status'],
				'message' => $response['message'],
			);
		}

		$detail = $this->findProductDetail($response['data'], 'productSalePricesExt', $supplierCode, $supplierComRef);
		if (empty($detail)) {
			return array(
				'success' => false,
				'status' => 'not_found',
				'price' => null,
				'stock' => null,
				'http_status' => (int) $response['http_status'],
				'message' => $this->extractApiMessage($response['data'], 'Produit non retourne par l API prix Rexel'),
			);
		}

		$price = $this->parseNumber(isset($detail['clientNetPrice']) ? $detail['clientNetPrice'] : null);
		if ($price === null) {
			return array(
				'success' => false,
				'status' => 'error',
				'price' => null,
				'stock' => null,
				'http_status' => (int) $response['http_status'],
				'message' => $this->extractApiMessage($response['data'], 'Prix net client introuvable dans la reponse Rexel'),
			);
		}

		return array(
			'success' => true,
			'status' => 'ok',
			'price' => $price,
			'http_status' => (int) $response['http_status'],
			'message' => '',
			'detail' => $detail,
		);
	}

	/**
	 * Fetch supplier stock.
	 *
	 * @param string $supplierCode Supplier code
	 * @param string $supplierComRef Supplier commercial reference
	 * @param int    $qty Ordered quantity
	 * @return array<string,mixed>
	 */
	public function fetchStock($supplierCode, $supplierComRef, $qty)
	{
		$commonPayload = $this->buildCommonPayload($supplierCode, $supplierComRef, $qty, true, false);
		if ($commonPayload === false) {
			return $this->buildClientError($this->error);
		}

		$payload = array(
			'getPositionsExtRequest' => $commonPayload,
		);

		$response = $this->postJson(self::STOCK_PATH, $payload);
		if (empty($response['success']) && $this->isRestJsonSchemaError($response['message'])) {
			$commonPayload = $this->buildCommonPayload($supplierCode, $supplierComRef, $qty, true, true);
			if ($commonPayload === false) {
				return $this->buildClientError($this->error);
			}

			$this->debugLog('RexelSync API stock retry with string orderingQty after BW-RESTJSON-100016');
			$payload = array(
				'getPositionsExtRequest' => $commonPayload,
			);
			$response = $this->postJson(self::STOCK_PATH, $payload);
		}
		if (empty($response['success']) && $this->isRestJsonSchemaError($response['message'])) {
			$commonPayload = $this->buildCommonPayload($supplierCode, $supplierComRef, $qty, true, false, true);
			if ($commonPayload === false) {
				return $this->buildClientError($this->error);
			}

			$this->debugLog('RexelSync API stock retry with numeric scalar fields after BW-RESTJSON-100016');
			$payload = array(
				'getPositionsExtRequest' => $commonPayload,
			);
			$response = $this->postJson(self::STOCK_PATH, $payload);
		}
		if (empty($response['success']) && $this->isRestJsonSchemaError($response['message'])) {
			$commonPayload = $this->buildCommonPayload($supplierCode, $supplierComRef, $qty, true, false, false, false);
			if ($commonPayload === false) {
				return $this->buildClientError($this->error);
			}

			$this->debugLog('RexelSync API stock retry with single productDetails object after BW-RESTJSON-100016');
			$payload = array(
				'getPositionsExtRequest' => $commonPayload,
			);
			$response = $this->postJson(self::STOCK_PATH, $payload);
		}
		if (empty($response['success'])) {
			return array(
				'success' => false,
				'status' => 'error',
				'price' => null,
				'stock' => null,
				'http_status' => (int) $response['http_status'],
				'message' => $response['message'],
			);
		}

		$detail = $this->findProductDetail($response['data'], 'getPositionsExt', $supplierCode, $supplierComRef);
		if (empty($detail)) {
			return array(
				'success' => false,
				'status' => 'not_found',
				'price' => null,
				'stock' => null,
				'http_status' => (int) $response['http_status'],
				'message' => $this->extractApiMessage($response['data'], 'Produit non retourne par l API stock Rexel'),
			);
		}

		$stock = 0.0;
		$hasStockField = false;
		foreach (array('availableBranchStock', 'availableCLRStock', 'availableServiceCenterStock') as $field) {
			if (array_key_exists($field, $detail)) {
				$value = $this->parseNumber($detail[$field]);
				$stock += ($value === null ? 0 : $value);
				$hasStockField = true;
			}
		}

		if (!$hasStockField) {
			return array(
				'success' => false,
				'status' => 'error',
				'price' => null,
				'stock' => null,
				'http_status' => (int) $response['http_status'],
				'message' => $this->extractApiMessage($response['data'], 'Stocks Rexel introuvables dans la reponse'),
			);
		}

		return array(
			'success' => true,
			'status' => 'ok',
			'stock' => $stock,
			'http_status' => (int) $response['http_status'],
			'message' => '',
			'detail' => $detail,
		);
	}

	/**
	 * Build the Rexel request body payload shared by price and stock endpoints.
	 *
	 * @param string $supplierCode Supplier code
	 * @param string $supplierComRef Supplier commercial reference
	 * @param int    $qty Ordered quantity
	 * @param bool   $includeDeliveryFields Include optional delivery fields
	 * @param bool   $quantityAsString Send orderingQty as a JSON string
	 * @param bool   $numericScalarFields Send numeric root fields as JSON numbers
	 * @param bool   $productDetailsAsArray Send productDetails as a JSON array
	 * @return array<string,mixed>|false
	 */
	private function buildCommonPayload($supplierCode, $supplierComRef, $qty, $includeDeliveryFields, $quantityAsString = true, $numericScalarFields = false, $productDetailsAsArray = true)
	{
		$agenceCode = $this->getOptionalNumericConfig('agence_code', 'Code agence Rexel invalide');
		if ($agenceCode === false) {
			return false;
		}
		if ($numericScalarFields && !$this->isNumericString((string) $this->config['id_customer'])) {
			$this->error = 'Numero client Rexel invalide: idCustomer doit etre numerique';
			return false;
		}

		$orderingQty = max(1, (int) $qty);
		$productDetails = array(
			'supplierCode' => (string) $supplierCode,
			'supplierComRef' => (string) $supplierComRef,
			'orderingQty' => $quantityAsString ? (string) $orderingQty : $orderingQty,
		);
		// TIBCO converts JSON to XML and validates element sequence, so keep the documented Rexel order.
		$payload = array();
		if (!empty($this->config['id_cod_origin'])) {
			$payload['idCodOrigin'] = (string) $this->config['id_cod_origin'];
		}
		$payload['idNumVersion'] = $numericScalarFields ? 1 : '1';
		$payload['idCustomer'] = $numericScalarFields ? (int) $this->config['id_customer'] : (string) $this->config['id_customer'];
		$payload['productDetails'] = $productDetailsAsArray ? array($productDetails) : $productDetails;

		if ($agenceCode !== '') {
			$payload['agenceCode'] = $numericScalarFields ? (int) $agenceCode : $agenceCode;
		}

		if ($includeDeliveryFields) {
			foreach (array(
				'zipCode' => 'zip_code',
				'city' => 'city',
			) as $apiField => $configField) {
				if (!empty($this->config[$configField])) {
					$payload[$apiField] = (string) $this->config[$configField];
				}
			}
		}
		if (!empty($this->config['sales_agreement'])) {
			$payload['salesAgreement'] = (string) $this->config['sales_agreement'];
		}

		return $payload;
	}

	/**
	 * POST JSON to Rexel.
	 *
	 * @param string              $path API path
	 * @param array<string,mixed> $payload JSON payload
	 * @return array<string,mixed>
	 */
	private function postJson($path, array $payload)
	{
		if (!function_exists('curl_init')) {
			return array('success' => false, 'http_status' => 0, 'message' => 'Extension PHP cURL indisponible', 'data' => null);
		}

		$url = rtrim((string) $this->config['base_url'], '/').$path;
		$headers = array('Accept: application/json', 'Content-Type: application/json');
		$authHeaders = $this->getAuthHeaders();
		if ($authHeaders === false) {
			return array('success' => false, 'http_status' => 0, 'message' => $this->error, 'data' => null);
		}
		$headers = array_merge($headers, $authHeaders);

		$jsonPayload = json_encode($payload);
		if ($jsonPayload === false) {
			return array('success' => false, 'http_status' => 0, 'message' => 'Payload Rexel non JSON: '.json_last_error_msg(), 'data' => null);
		}

		$this->debugLog('RexelSync API client_version='.self::CLIENT_VERSION.' request path='.$path.' headers='.implode(',', $this->getHeaderNames($headers)).' payload='.$this->jsonForLog($this->maskPayloadForLog($payload)));

		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $jsonPayload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_HTTPHEADER => $headers,
		));

		$raw = curl_exec($ch);
		$curlError = curl_error($ch);
		$httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$this->debugLog('RexelSync API response path='.$path.' http_status='.$httpStatus);

		if ($raw === false) {
			return array('success' => false, 'http_status' => $httpStatus, 'message' => 'Erreur cURL Rexel: '.$curlError, 'data' => null);
		}

		$data = json_decode((string) $raw, true);
		if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
			return array('success' => false, 'http_status' => $httpStatus, 'message' => 'Reponse Rexel non JSON: '.json_last_error_msg(), 'data' => null);
		}

		if ($httpStatus < 200 || $httpStatus >= 300) {
			$this->debugLog('RexelSync API error response path='.$path.' body='.$this->truncateLogString($this->jsonForLog($this->maskPayloadForLog($data))));

			return array(
				'success' => false,
				'http_status' => $httpStatus,
				'message' => $this->extractApiMessage($data, 'Erreur HTTP Rexel '.$httpStatus),
				'data' => $data,
			);
		}

		return array('success' => true, 'http_status' => $httpStatus, 'message' => '', 'data' => $data);
	}

	/**
	 * Return auth headers according to configured mode.
	 *
	 * @return array<int,string>|false
	 */
	private function getAuthHeaders()
	{
		$mode = !empty($this->config['auth_mode']) ? (string) $this->config['auth_mode'] : 'none';
		if ($mode === 'none') {
			return array();
		}
		if ($mode === 'bearer') {
			if (empty($this->config['bearer_token'])) {
				$this->error = 'Jeton bearer Rexel manquant';
				return false;
			}
			$subscriptionHeader = $this->getSubscriptionHeader();
			if ($subscriptionHeader === false) {
				return false;
			}
			return array_merge(array('Authorization: Bearer '.$this->config['bearer_token']), $subscriptionHeader);
		}
		if ($mode === 'apikey') {
			return $this->getSubscriptionHeader();
		}
		if ($mode === 'oauth2') {
			$token = $this->getOauthAccessToken();
			if ($token === false) {
				return false;
			}
			$subscriptionHeader = $this->getSubscriptionHeader();
			if ($subscriptionHeader === false) {
				return false;
			}
			return array_merge(array('Authorization: Bearer '.$token), $subscriptionHeader);
		}

		$this->error = 'Mode authentification Rexel inconnu: '.$mode;
		return false;
	}

	/**
	 * Fetch OAuth2 client credentials token.
	 *
	 * @return string|false
	 */
	private function getOauthAccessToken()
	{
		if ($this->oauthAccessToken !== null) {
			return $this->oauthAccessToken;
		}
		if (empty($this->config['token_url']) || empty($this->config['client_id']) || empty($this->config['client_secret'])) {
			$this->error = 'Configuration OAuth2 Rexel incomplete';
			return false;
		}

		$postFields = array(
			'grant_type' => 'client_credentials',
			'client_id' => (string) $this->config['client_id'],
			'client_secret' => (string) $this->config['client_secret'],
		);
		if (!empty($this->config['token_resource'])) {
			$postFields['resource'] = (string) $this->config['token_resource'];
		} elseif (!empty($this->config['token_scope'])) {
			$postFields['scope'] = (string) $this->config['token_scope'];
		}

		$ch = curl_init((string) $this->config['token_url']);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($postFields),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 45,
			CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'),
		));

		$raw = curl_exec($ch);
		$curlError = curl_error($ch);
		$httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$this->debugLog('RexelSync OAuth2 token response http_status='.$httpStatus);

		if ($raw === false) {
			$this->error = 'Erreur cURL token Rexel: '.$curlError;
			return false;
		}
		$data = json_decode((string) $raw, true);
		if ($httpStatus < 200 || $httpStatus >= 300 || empty($data['access_token'])) {
			$this->error = $this->extractApiMessage($data, 'Impossible de recuperer le token Rexel OAuth2');
			return false;
		}

		$this->oauthAccessToken = (string) $data['access_token'];
		return $this->oauthAccessToken;
	}

	/**
	 * Find the first product detail from Rexel response.
	 *
	 * @param mixed  $data Response data
	 * @param string $root Root response name
	 * @param string $supplierCode Supplier code
	 * @param string $supplierComRef Supplier commercial reference
	 * @return array<string,mixed>|null
	 */
	private function findProductDetail($data, $root, $supplierCode, $supplierComRef)
	{
		if (!is_array($data)) {
			return null;
		}

		$container = null;
		if (isset($data['data'][$root])) {
			$container = $data['data'][$root];
		} elseif (isset($data[$root])) {
			$container = $data[$root];
		} elseif (isset($data['data']) && is_array($data['data'])) {
			$container = $data['data'];
		} else {
			$container = $data;
		}

		if (!is_array($container) || !isset($container['productDetails'])) {
			return null;
		}

		$details = $container['productDetails'];
		if (isset($details['supplierComRef']) || isset($details['rexelRef'])) {
			$details = array($details);
		}
		if (!is_array($details)) {
			return null;
		}

		$fallback = null;
		foreach ($details as $detail) {
			if (!is_array($detail)) {
				continue;
			}
			if ($fallback === null) {
				$fallback = $detail;
			}
			$detailCode = isset($detail['supplierCode']) ? trim((string) $detail['supplierCode']) : '';
			$detailRef = isset($detail['supplierComRef']) ? trim((string) $detail['supplierComRef']) : '';
			if ($detailCode === $supplierCode && $detailRef === $supplierComRef) {
				return $detail;
			}
		}

		return $fallback;
	}

	/**
	 * Extract a readable message from a Rexel response.
	 *
	 * @param mixed  $data Response data
	 * @param string $fallback Fallback message
	 * @return string
	 */
	private function extractApiMessage($data, $fallback)
	{
		if (!is_array($data)) {
			return $fallback;
		}

		$readableMessage = '';
		$errorCode = '';

		foreach (array('message', 'errorMessage', 'error_message', 'error_description', 'faultstring', 'description', 'libelle') as $field) {
			if ($readableMessage === '' && !empty($data[$field]) && is_scalar($data[$field])) {
				$readableMessage = (string) $data[$field];
			}
		}
		foreach (array('errorCode', 'error_code', 'code', 'error') as $field) {
			if ($errorCode === '' && !empty($data[$field]) && is_scalar($data[$field])) {
				$errorCode = (string) $data[$field];
			}
		}

		$iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($data));
		foreach ($iterator as $key => $value) {
			$key = (string) $key;
			if (!is_scalar($value)) {
				continue;
			}
			if ($readableMessage === '' && preg_match('/errormessage|message|faultstring|libelle|description/i', $key) && !preg_match('/code/i', $key)) {
				$readableMessage = (string) $value;
			}
			if ($errorCode === '' && preg_match('/^error$|errorcode|code/i', $key)) {
				$errorCode = (string) $value;
			}
		}

		if ($readableMessage !== '' && $errorCode !== '' && $readableMessage !== $errorCode) {
			return $errorCode.' - '.$readableMessage;
		}
		if ($readableMessage !== '') {
			return $readableMessage;
		}
		if ($errorCode !== '') {
			return $errorCode;
		}

		return $fallback;
	}

	/**
	 * Return a subscription key header for Rexel API Management.
	 *
	 * @return array<int,string>|false
	 */
	private function getSubscriptionHeader()
	{
		if (empty($this->config['api_key'])) {
			$this->error = 'Cle de souscription Rexel manquante';
			return false;
		}

		$header = !empty($this->config['api_key_header']) ? (string) $this->config['api_key_header'] : 'Ocp-Apim-Subscription-Key';
		if (!preg_match('/^[A-Za-z0-9-]+$/', $header)) {
			$this->error = 'Nom d en-tete de souscription Rexel invalide';
			return false;
		}

		return array($header.': '.$this->config['api_key']);
	}

	/**
	 * Read and validate an optional numeric config value.
	 *
	 * @param string $key Config key
	 * @param string $error Error message
	 * @return string|false Empty string when the config value is not set
	 */
	private function getOptionalNumericConfig($key, $error)
	{
		$value = isset($this->config[$key]) ? trim((string) $this->config[$key]) : '';
		if ($value !== '' && !preg_match('/^[0-9]+$/', $value)) {
			$this->error = $error;
			return false;
		}

		return $value;
	}

	/**
	 * Return a standard client-side error payload.
	 *
	 * @param string $message Error message
	 * @return array<string,mixed>
	 */
	private function buildClientError($message)
	{
		return array(
			'success' => false,
			'status' => 'error',
			'price' => null,
			'stock' => null,
			'http_status' => 0,
			'message' => $message,
		);
	}

	/**
	 * Return HTTP header names only, without values.
	 *
	 * @param array<int,string> $headers HTTP headers
	 * @return array<int,string>
	 */
	private function getHeaderNames(array $headers)
	{
		$names = array();
		foreach ($headers as $header) {
			$parts = explode(':', $header, 2);
			$names[] = trim($parts[0]);
		}

		return $names;
	}

	/**
	 * Mask customer and commercial data before debug logging.
	 *
	 * @param mixed $value Value
	 * @return mixed
	 */
	private function maskPayloadForLog($value)
	{
		if (!is_array($value)) {
			return $value;
		}

		$masked = array();
		foreach ($value as $key => $item) {
			$keyName = strtolower((string) $key);
			if ($keyName === 'idcustomer' || $keyName === 'customerid') {
				$masked[$key] = $this->isNumericString((string) $item) ? '*** (numeric)' : '*** (non-numeric)';
				continue;
			}
			if ($keyName === 'salesagreement') {
				$masked[$key] = '***';
				continue;
			}
			if (in_array($keyName, array('authorization', 'access_token', 'refresh_token', 'client_secret', 'api_key', 'subscription_key', 'ocp-apim-subscription-key'), true)) {
				$masked[$key] = '***';
				continue;
			}
			$masked[$key] = $this->maskPayloadForLog($item);
		}

		return $masked;
	}

	/**
	 * Encode debug payload safely.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private function jsonForLog($value)
	{
		$json = json_encode($value);
		return $json === false ? '[unavailable]' : $json;
	}

	/**
	 * Truncate a log string to avoid oversized Dolibarr log lines.
	 *
	 * @param string $value Log value
	 * @param int    $maxLength Maximum length
	 * @return string
	 */
	private function truncateLogString($value, $maxLength = 2000)
	{
		if (strlen($value) <= $maxLength) {
			return $value;
		}

		return substr($value, 0, $maxLength).'...';
	}

	/**
	 * Write a debug log entry when Dolibarr logging is available.
	 *
	 * @param string $message Message
	 * @return void
	 */
	private function debugLog($message)
	{
		if (function_exists('dol_syslog')) {
			dol_syslog($message, defined('LOG_DEBUG') ? LOG_DEBUG : 7);
		}
	}

	/**
	 * Check if Rexel/TIBCO rejected JSON schema conversion.
	 *
	 * @param mixed $message Response message
	 * @return bool
	 */
	private function isRestJsonSchemaError($message)
	{
		return is_string($message) && strpos($message, 'BW-RESTJSON-100016') !== false;
	}

	/**
	 * Check if a value contains only digits.
	 *
	 * @param string $value Value
	 * @return bool
	 */
	private function isNumericString($value)
	{
		return (bool) preg_match('/^[0-9]+$/', trim($value));
	}

	/**
	 * Parse a number returned by Rexel.
	 *
	 * @param mixed $value Raw value
	 * @return float|null
	 */
	private function parseNumber($value)
	{
		if ($value === null || $value === '') {
			return null;
		}
		if (is_numeric($value)) {
			return (float) $value;
		}

		$value = preg_replace('/[^0-9,.\-]/', '', (string) $value);
		if ($value === '' || $value === null) {
			return null;
		}
		if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
			$value = str_replace(' ', '', $value);
			$value = str_replace('.', '', $value);
			$value = str_replace(',', '.', $value);
		} elseif (strpos($value, ',') !== false) {
			$value = str_replace(',', '.', $value);
		}

		return is_numeric($value) ? (float) $value : null;
	}
}
