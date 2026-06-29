<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__.'/rexelapi.class.php';

/**
 * Synchronizes Rexel customer profile data into module cache tables.
 */
class RexelSyncCustomer
{
	const STATUS_OK = 'ok';
	const STATUS_ERROR = 'error';

	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @var array<string,int> */
	private $countryCache = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Synchronize Rexel customer profile, addresses and agreements.
	 *
	 * @param array<string,mixed> $config Module configuration
	 * @param User                $user   User running the sync
	 * @return array<string,mixed>
	 */
	public function syncCustomer(array $config, $user)
	{
		global $conf;

		$fkSoc = !empty($config['supplier_id']) ? (int) $config['supplier_id'] : 0;
		$idCustomer = !empty($config['id_customer']) ? trim((string) $config['id_customer']) : '';
		if ($fkSoc <= 0 || $idCustomer === '') {
			$this->error = 'Configuration RexelSync incomplete: fournisseur Dolibarr REXEL, numero client Rexel';
			return $this->buildResult(false, 0, 0, $this->error, 0);
		}

		$api = new RexelApi($config);
		$result = $api->fetchCustomer($idCustomer);
		if (empty($result['success']) || empty($result['customer']) || !is_array($result['customer'])) {
			$message = !empty($result['message']) ? (string) $result['message'] : 'Fiche client Rexel introuvable dans la reponse';
			$this->error = $message;
			$this->setLastSyncConstants(self::STATUS_ERROR);
			return $this->buildResult(false, 0, 0, $message, !empty($result['http_status']) ? (int) $result['http_status'] : 0);
		}

		$customer = $result['customer'];
		$entity = (int) $conf->entity;
		$now = $this->db->idate(dol_now());

		$this->db->begin();
		$error = 0;

		if ($this->deleteExistingCustomerData($entity, $fkSoc) < 0) {
			$error++;
		}
		if (!$error && $this->insertCustomerProfile($entity, $fkSoc, $idCustomer, $customer, $user, $now) < 0) {
			$error++;
		}

		$addressCount = 0;
		if (!$error) {
			$addresses = $this->normalizeList(isset($customer['shippingAddresses']) ? $customer['shippingAddresses'] : array());
			foreach ($addresses as $index => $address) {
				if ($this->insertCustomerAddress($entity, $fkSoc, $idCustomer, $customer, $address, (int) $index, $user, $now) < 0) {
					$error++;
					break;
				}
				$addressCount++;
			}
		}

		$agreementCount = 0;
		if (!$error) {
			$agreements = $this->normalizeList(isset($customer['customerAgreements']) ? $customer['customerAgreements'] : array());
			foreach ($agreements as $index => $agreement) {
				if ($this->insertCustomerAgreement($entity, $fkSoc, $idCustomer, $customer, $agreement, (int) $index, $user, $now) < 0) {
					$error++;
					break;
				}
				$agreementCount++;
			}
		}

		if ($error) {
			$this->db->rollback();
			$this->setLastSyncConstants(self::STATUS_ERROR);
			return $this->buildResult(false, $addressCount, $agreementCount, $this->error, !empty($result['http_status']) ? (int) $result['http_status'] : 0);
		}

		$this->db->commit();
		$this->setLastSyncConstants(self::STATUS_OK);

		return $this->buildResult(true, $addressCount, $agreementCount, '', !empty($result['http_status']) ? (int) $result['http_status'] : 0);
	}

	/**
	 * Fetch the cached customer profile for display.
	 *
	 * @param int $fkSoc Optional thirdparty id
	 * @return array<string,mixed>|null
	 */
	public function fetchCustomerProfile($fkSoc = 0)
	{
		global $conf;

		$fkSoc = $fkSoc > 0 ? (int) $fkSoc : getDolGlobalInt('REXELSYNC_SUPPLIER_ID');
		if ($fkSoc <= 0) {
			return null;
		}

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."rexelsync_customer_profile";
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND fk_soc = ".$fkSoc;
		$sql .= " ORDER BY rowid DESC".$this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return null;
		}

		return $this->objectToArray($obj);
	}

	/**
	 * Fetch cached delivery addresses.
	 *
	 * @param int $limit Max rows
	 * @return array<int,array<string,mixed>>
	 */
	public function fetchCustomerAddresses($limit = 100)
	{
		return $this->fetchCachedRows('rexelsync_customer_address', 'address_index ASC, rowid ASC', (int) $limit);
	}

	/**
	 * Fetch cached customer agreements.
	 *
	 * @param int $limit Max rows
	 * @return array<int,array<string,mixed>>
	 */
	public function fetchCustomerAgreements($limit = 100)
	{
		return $this->fetchCachedRows('rexelsync_customer_agreement', 'agreement_index ASC, rowid ASC', (int) $limit);
	}

	/**
	 * Delete previous cache rows for one entity and thirdparty.
	 *
	 * @param int $entity Entity id
	 * @param int $fkSoc Thirdparty id
	 * @return int
	 */
	private function deleteExistingCustomerData($entity, $fkSoc)
	{
		foreach (array('rexelsync_customer_profile', 'rexelsync_customer_address', 'rexelsync_customer_agreement') as $table) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX.$table." WHERE entity = ".((int) $entity)." AND fk_soc = ".((int) $fkSoc);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Insert customer profile row.
	 *
	 * @param int                 $entity Entity id
	 * @param int                 $fkSoc Thirdparty id
	 * @param string              $idCustomer Requested customer id
	 * @param array<string,mixed> $customer Customer payload
	 * @param User                $user User
	 * @param string              $now SQL date
	 * @return int
	 */
	private function insertCustomerProfile($entity, $fkSoc, $idCustomer, array $customer, $user, $now)
	{
		$countryCode = $this->firstString($customer, array('billCountryCode'), '');
		$countryName = $this->firstString($customer, array('billCountryName'), '');
		$countryId = $this->resolveCountryId($countryCode, '', $countryName);

		$columns = array(
			'entity' => (string) ((int) $entity),
			'fk_soc' => (string) ((int) $fkSoc),
			'customer_id' => $this->sqlString($this->firstString($customer, array('customerId', 'idCustomer'), $idCustomer), 32),
			'scope' => $this->sqlString($this->firstString($customer, array('scope', 'customerScope'), ''), 32),
			'branch_code' => $this->sqlString($this->firstString($customer, array('branchCode', 'agenceCode'), ''), 16),
			'customer_name' => $this->sqlString($this->firstString($customer, array('customerName', 'name'), ''), 255),
			'siren' => $this->sqlString($this->firstString($customer, array('siren'), ''), 32),
			'nic' => $this->sqlString($this->firstString($customer, array('nic'), ''), 32),
			'commercial_contact_fullname' => $this->sqlString($this->firstString($customer, array('commercialContactFullName'), ''), 255),
			'commercial_contact_phone' => $this->sqlString($this->firstString($customer, array('commercialContactPhone'), ''), 64),
			'commercial_contact_email' => $this->sqlString($this->firstString($customer, array('commercialContactEmail'), ''), 255),
			'address' => $this->sqlString($this->firstString($customer, array('billAddress1'), ''), 255),
			'address2' => $this->sqlString($this->firstString($customer, array('billAddress2'), ''), 255),
			'address3' => $this->sqlString($this->firstString($customer, array('billAddress3'), ''), 255),
			'zip' => $this->sqlString($this->firstString($customer, array('billZipcode', 'billZipCode'), ''), 25),
			'town' => $this->sqlString($this->firstString($customer, array('billCity'), ''), 128),
			'fk_pays' => $countryId > 0 ? (string) $countryId : 'NULL',
			'country_code' => $this->sqlString($countryCode, 8),
			'country_name' => $this->sqlString($countryName, 128),
			'datec' => "'".$this->db->escape($now)."'",
			'fk_user_creat' => (string) ((int) $user->id),
			'fk_user_modif' => (string) ((int) $user->id),
		);

		return $this->insertRow('rexelsync_customer_profile', $columns);
	}

	/**
	 * Insert delivery address row.
	 *
	 * @param int                 $entity Entity id
	 * @param int                 $fkSoc Thirdparty id
	 * @param string              $idCustomer Requested customer id
	 * @param array<string,mixed> $customer Customer payload
	 * @param array<string,mixed> $address Address payload
	 * @param int                 $index Address index
	 * @param User                $user User
	 * @param string              $now SQL date
	 * @return int
	 */
	private function insertCustomerAddress($entity, $fkSoc, $idCustomer, array $customer, array $address, $index, $user, $now)
	{
		$addressIndex = $this->firstString($address, array('index'), (string) $index);
		$countryCode = $this->firstString($address, array('countryCode'), '');
		$countryCodeIso3 = $this->firstString($address, array('countryCodeIso3'), '');
		$countryName = $this->firstString($address, array('countryName'), '');
		$countryId = $this->resolveCountryId($countryCode, $countryCodeIso3, $countryName);
		$externalKey = $this->buildExternalKey(array(
			$this->firstString($address, array('addressType'), ''),
			$addressIndex,
			$this->firstString($address, array('origin'), ''),
			$this->firstString($address, array('name'), ''),
		));

		$columns = array(
			'entity' => (string) ((int) $entity),
			'fk_soc' => (string) ((int) $fkSoc),
			'external_key' => $this->sqlString($externalKey, 128),
			'customer_id' => $this->sqlString($this->firstString($customer, array('customerId', 'idCustomer'), $idCustomer), 32),
			'scope' => $this->sqlString($this->firstString($customer, array('scope', 'customerScope'), ''), 32),
			'address_type' => $this->sqlString($this->firstString($address, array('addressType'), ''), 16),
			'address_index' => (string) ((int) $addressIndex),
			'origin' => $this->sqlString($this->firstString($address, array('origin'), ''), 64),
			'name' => $this->sqlString($this->firstString($address, array('name'), ''), 255),
			'address' => $this->sqlString($this->firstString($address, array('address1'), ''), 255),
			'address2' => $this->sqlString($this->firstString($address, array('address2'), ''), 255),
			'address3' => $this->sqlString($this->firstString($address, array('address3'), ''), 255),
			'zip' => $this->sqlString($this->firstString($address, array('zipCode', 'zipcode'), ''), 25),
			'town' => $this->sqlString($this->firstString($address, array('city'), ''), 128),
			'fk_pays' => $countryId > 0 ? (string) $countryId : 'NULL',
			'country_code' => $this->sqlString($countryCode, 8),
			'country_code_iso3' => $this->sqlString($countryCodeIso3, 3),
			'country_name' => $this->sqlString($countryName, 128),
			'datec' => "'".$this->db->escape($now)."'",
			'fk_user_creat' => (string) ((int) $user->id),
			'fk_user_modif' => (string) ((int) $user->id),
		);

		return $this->insertRow('rexelsync_customer_address', $columns);
	}

	/**
	 * Insert customer agreement row.
	 *
	 * @param int                 $entity Entity id
	 * @param int                 $fkSoc Thirdparty id
	 * @param string              $idCustomer Requested customer id
	 * @param array<string,mixed> $customer Customer payload
	 * @param array<string,mixed> $agreement Agreement payload
	 * @param int                 $index Agreement index
	 * @param User                $user User
	 * @param string              $now SQL date
	 * @return int
	 */
	private function insertCustomerAgreement($entity, $fkSoc, $idCustomer, array $customer, array $agreement, $index, $user, $now)
	{
		$externalKey = $this->buildExternalKey(array(
			$this->firstString($agreement, array('salesAgreementNumber'), ''),
			$this->firstString($agreement, array('derogationNumber'), ''),
			$this->firstString($agreement, array('supplierCode'), ''),
			(string) $index,
		));

		$columns = array(
			'entity' => (string) ((int) $entity),
			'fk_soc' => (string) ((int) $fkSoc),
			'external_key' => $this->sqlString($externalKey, 128),
			'customer_id' => $this->sqlString($this->firstString($customer, array('customerId', 'idCustomer'), $idCustomer), 32),
			'scope' => $this->sqlString($this->firstString($customer, array('scope', 'customerScope'), ''), 32),
			'agreement_index' => (string) ((int) $index),
			'sales_agreement_number' => $this->sqlString($this->firstString($agreement, array('salesAgreementNumber'), ''), 64),
			'sales_agreement_label' => $this->sqlString($this->firstString($agreement, array('salesAgreementLabel'), ''), 255),
			'supplier_code' => $this->sqlString($this->firstString($agreement, array('supplierCode'), ''), 16),
			'derogation_number' => $this->sqlString($this->firstString($agreement, array('derogationNumber'), ''), 64),
			'application_start_date' => $this->sqlDate(isset($agreement['applicationStartDate']) ? $agreement['applicationStartDate'] : null),
			'application_end_date' => $this->sqlDate(isset($agreement['applicationEndDate']) ? $agreement['applicationEndDate'] : null),
			'amount_agreement' => $this->sqlNumber(isset($agreement['amountAgreement']) ? $agreement['amountAgreement'] : null),
			'available_amount' => $this->sqlNumber(isset($agreement['availableAmount']) ? $agreement['availableAmount'] : null),
			'agreement_origin' => $this->sqlString($this->firstString($agreement, array('agreementOrigin'), ''), 64),
			'datec' => "'".$this->db->escape($now)."'",
			'fk_user_creat' => (string) ((int) $user->id),
			'fk_user_modif' => (string) ((int) $user->id),
		);

		return $this->insertRow('rexelsync_customer_agreement', $columns);
	}

	/**
	 * Insert one row into a module table.
	 *
	 * @param string $table Table without prefix
	 * @param array<string,string> $columns SQL columns and values
	 * @return int
	 */
	private function insertRow($table, array $columns)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$table." (".implode(', ', array_keys($columns)).") VALUES (".implode(', ', array_values($columns)).")";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Fetch rows from a cache table.
	 *
	 * @param string $table Table without prefix
	 * @param string $orderBy Safe order by clause
	 * @param int    $limit Limit
	 * @return array<int,array<string,mixed>>
	 */
	private function fetchCachedRows($table, $orderBy, $limit)
	{
		global $conf;

		$fkSoc = getDolGlobalInt('REXELSYNC_SUPPLIER_ID');
		if ($fkSoc <= 0) {
			return array();
		}

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$table;
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND fk_soc = ".((int) $fkSoc);
		$sql .= " ORDER BY ".$orderBy;
		if ($limit > 0) {
			$sql .= $this->db->plimit($limit);
		}

		$rows = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $rows;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $this->objectToArray($obj);
		}

		return $rows;
	}

	/**
	 * Normalize a Rexel array-or-object payload into a list.
	 *
	 * @param mixed $value Payload value
	 * @return array<int,array<string,mixed>>
	 */
	private function normalizeList($value)
	{
		if (!is_array($value) || empty($value)) {
			return array();
		}
		if (isset($value[0]) && is_array($value[0])) {
			$list = array();
			foreach ($value as $item) {
				if (is_array($item)) {
					$list[] = $item;
				}
			}
			return $list;
		}

		return array($value);
	}

	/**
	 * Return the first scalar value found in a payload.
	 *
	 * @param array<string,mixed> $data Payload
	 * @param array<int,string>   $keys Candidate keys
	 * @param string              $default Default value
	 * @return string
	 */
	private function firstString(array $data, array $keys, $default)
	{
		foreach ($keys as $key) {
			if (isset($data[$key]) && is_scalar($data[$key])) {
				return trim((string) $data[$key]);
			}
		}

		return $default;
	}

	/**
	 * Build a stable external key.
	 *
	 * @param array<int,string> $parts Key parts
	 * @return string
	 */
	private function buildExternalKey(array $parts)
	{
		$clean = array();
		foreach ($parts as $part) {
			$part = trim((string) $part);
			if ($part !== '') {
				$clean[] = $part;
			}
		}
		$key = !empty($clean) ? implode('|', $clean) : 'default';
		if (strlen($key) > 128) {
			$key = substr($key, 0, 95).'|'.md5($key);
		}

		return $key;
	}

	/**
	 * Return a SQL string value.
	 *
	 * @param string $value Value
	 * @param int    $maxLength Max length
	 * @return string
	 */
	private function sqlString($value, $maxLength)
	{
		$value = trim((string) $value);
		if ($maxLength > 0 && strlen($value) > $maxLength) {
			$value = substr($value, 0, $maxLength);
		}

		return "'".$this->db->escape($value)."'";
	}

	/**
	 * Return a SQL date value.
	 *
	 * @param mixed $value Raw date
	 * @return string
	 */
	private function sqlDate($value)
	{
		if (!is_scalar($value) || trim((string) $value) === '') {
			return 'NULL';
		}
		$timestamp = strtotime((string) $value);
		if ($timestamp === false) {
			return 'NULL';
		}

		return "'".$this->db->escape($this->db->idate($timestamp))."'";
	}

	/**
	 * Return a SQL number value.
	 *
	 * @param mixed $value Raw number
	 * @return string
	 */
	private function sqlNumber($value)
	{
		$number = $this->parseNumber($value);
		return $number === null ? 'NULL' : price2num($number);
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

	/**
	 * Resolve a country identifier from Rexel country fields.
	 *
	 * @param string $code Country code
	 * @param string $codeIso3 ISO3 country code
	 * @param string $label Country label
	 * @return int
	 */
	private function resolveCountryId($code, $codeIso3, $label)
	{
		$cacheKey = strtoupper(trim((string) $code).'|'.trim((string) $codeIso3).'|'.trim((string) $label));
		if (isset($this->countryCache[$cacheKey])) {
			return $this->countryCache[$cacheKey];
		}

		$clauses = array();
		$code = strtoupper(trim((string) $code));
		$codeIso3 = strtoupper(trim((string) $codeIso3));
		$label = trim((string) $label);
		if ($code !== '') {
			$clauses[] = "UPPER(code) = '".$this->db->escape($code)."'";
		}
		if ($codeIso3 !== '') {
			$clauses[] = "UPPER(code_iso) = '".$this->db->escape($codeIso3)."'";
		}
		if ($label !== '') {
			$clauses[] = "label = '".$this->db->escape($label)."'";
		}
		if (empty($clauses)) {
			$this->countryCache[$cacheKey] = 0;
			return 0;
		}

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_country WHERE active = 1 AND (".implode(' OR ', $clauses).")";
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->countryCache[$cacheKey] = 0;
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->countryCache[$cacheKey] = $obj ? (int) $obj->rowid : 0;

		return $this->countryCache[$cacheKey];
	}

	/**
	 * Store sync status constants for the active entity.
	 *
	 * @param string $status Status
	 * @return void
	 */
	private function setLastSyncConstants($status)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		dolibarr_set_const($this->db, 'REXELSYNC_CUSTOMER_LAST_SYNC_DATE', (string) dol_now(), 'chaine', 0, '', (int) $conf->entity);
		dolibarr_set_const($this->db, 'REXELSYNC_CUSTOMER_LAST_SYNC_STATUS', $status, 'chaine', 0, '', (int) $conf->entity);
	}

	/**
	 * Build a standard sync result.
	 *
	 * @param bool   $success Success
	 * @param int    $addressCount Address count
	 * @param int    $agreementCount Agreement count
	 * @param string $message Message
	 * @param int    $httpStatus HTTP status
	 * @return array<string,mixed>
	 */
	private function buildResult($success, $addressCount, $agreementCount, $message, $httpStatus)
	{
		return array(
			'success' => $success,
			'status' => $success ? self::STATUS_OK : self::STATUS_ERROR,
			'addresses' => (int) $addressCount,
			'agreements' => (int) $agreementCount,
			'message' => $message,
			'http_status' => (int) $httpStatus,
		);
	}

	/**
	 * Convert a database object to an array.
	 *
	 * @param object $obj Row object
	 * @return array<string,mixed>
	 */
	private function objectToArray($obj)
	{
		$row = array();
		foreach (get_object_vars($obj) as $key => $value) {
			$row[$key] = $value;
		}

		return $row;
	}
}