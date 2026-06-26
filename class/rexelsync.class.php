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
 * Synchronization orchestrator for Rexel supplier prices.
 */
class RexelSync
{
	const STATUS_UPDATED = 'updated';
	const STATUS_STOCK_UPDATED = 'stock_updated';
	const STATUS_UNCHANGED = 'unchanged';
	const STATUS_ERROR = 'error';
	const STATUS_NOT_FOUND = 'not_found';
	const STATUS_INVALID_REF = 'invalid_ref';

	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/** @var string Cron output */
	public $output = '';

	/** @var array<string,mixed> */
	public $lastStats = array();

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
	 * Cron entry point.
	 *
	 * @param int $limit Optional limit
	 * @param int $offset Optional offset
	 * @return int 0 if OK, <0 if KO
	 */
	public function syncAllSupplierPrices($limit = 0, $offset = 0)
	{
		$stats = $this->runSync((int) $limit, (int) $offset);
		$this->lastStats = $stats;
		$this->output = $this->buildStatsOutput($stats);

		return empty($stats['fatal']) ? 0 : -1;
	}

	/**
	 * Run synchronization for all configured Rexel supplier price rows.
	 *
	 * @param int $limit Optional limit
	 * @param int $offset Optional offset
	 * @return array<string,mixed>
	 */
	public function runSync($limit = 0, $offset = 0)
	{
		global $user;

		$stats = $this->emptyStats();
		$config = $this->getConfig();
		$missing = $this->getMissingConfiguration($config);
		if (!empty($missing)) {
			$stats['fatal'] = true;
			$stats['message'] = 'Configuration RexelSync incomplete: '.implode(', ', $missing);
			$this->error = $stats['message'];
			return $stats;
		}

		$rows = $this->getSupplierPriceRows($limit, $offset);
		$stats['total'] = count($rows);
		if (empty($rows)) {
			$stats['message'] = 'Aucune ligne de prix fournisseur Rexel a synchroniser.';
			return $stats;
		}

		$api = new RexelApi($config);
		$delayMs = max(0, (int) $config['delay_ms']);
		foreach ($rows as $row) {
			$result = $this->syncRow($row, $api, $config, $user);
			if (!empty($result['status']) && isset($stats[$result['status']])) {
				$stats[$result['status']]++;
			} elseif (empty($result['success'])) {
				$stats[self::STATUS_ERROR]++;
			}
			if (!empty($result['success'])) {
				$stats['success']++;
			}
			if ($delayMs > 0) {
				usleep($delayMs * 1000);
			}
		}

		$stats['message'] = $this->buildStatsOutput($stats);
		return $stats;
	}

	/**
	 * Synchronize one supplier price row.
	 *
	 * @param int $priceLineId Supplier price row id
	 * @return array<string,mixed>
	 */
	public function syncOneSupplierPriceLine($priceLineId)
	{
		global $user;

		$config = $this->getConfig();
		$missing = $this->getMissingConfiguration($config);
		if (!empty($missing)) {
			$this->error = 'Configuration RexelSync incomplete: '.implode(', ', $missing);
			return array('success' => false, 'status' => self::STATUS_ERROR, 'message' => $this->error);
		}

		$row = $this->getSupplierPriceRowById((int) $priceLineId);
		if (empty($row)) {
			$this->error = 'Ligne de prix fournisseur introuvable ou hors fournisseur Rexel';
			return array('success' => false, 'status' => self::STATUS_ERROR, 'message' => $this->error);
		}

		$api = new RexelApi($config);
		return $this->syncRow($row, $api, $config, $user);
	}

	/**
	 * Return current module configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function getConfig()
	{
		$authMode = getDolGlobalString('REXELSYNC_AUTH_MODE');
		if ($authMode === '') {
			$authMode = 'bearer';
		}

		return array(
			'supplier_id' => getDolGlobalInt('REXELSYNC_SUPPLIER_ID'),
			'id_customer' => getDolGlobalString('REXELSYNC_ID_CUSTOMER'),
			'base_url' => getDolGlobalString('REXELSYNC_BASE_URL') ?: 'https://api.rexel.fr',
			'auth_mode' => $authMode,
			'bearer_token' => dol_decode(getDolGlobalString('REXELSYNC_BEARER_TOKEN')),
			'api_key_header' => getDolGlobalString('REXELSYNC_API_KEY_HEADER') ?: 'Ocp-Apim-Subscription-Key',
			'api_key' => dol_decode(getDolGlobalString('REXELSYNC_API_KEY')),
			'client_id' => getDolGlobalString('REXELSYNC_CLIENT_ID'),
			'client_secret' => dol_decode(getDolGlobalString('REXELSYNC_CLIENT_SECRET')),
			'token_url' => getDolGlobalString('REXELSYNC_TOKEN_URL'),
			'token_resource' => getDolGlobalString('REXELSYNC_TOKEN_RESOURCE'),
			'token_scope' => getDolGlobalString('REXELSYNC_TOKEN_SCOPE'),
			'id_cod_origin' => getDolGlobalString('REXELSYNC_ID_COD_ORIGIN'),
			'agence_code' => getDolGlobalString('REXELSYNC_AGENCE_CODE'),
			'zip_code' => getDolGlobalString('REXELSYNC_ZIP_CODE'),
			'city' => getDolGlobalString('REXELSYNC_CITY'),
			'sales_agreement' => getDolGlobalString('REXELSYNC_SALES_AGREEMENT'),
			'batch_size' => getDolGlobalInt('REXELSYNC_BATCH_SIZE') ?: 0,
			'delay_ms' => getDolGlobalInt('REXELSYNC_DELAY_MS') ?: 0,
			'default_qty' => getDolGlobalInt('REXELSYNC_DEFAULT_QTY') ?: 1,
		);
	}

	/**
	 * Return missing mandatory settings.
	 *
	 * @param array<string,mixed> $config Config
	 * @return array<int,string>
	 */
	public function getMissingConfiguration(array $config)
	{
		$missing = array();
		if (empty($config['supplier_id'])) {
			$missing[] = 'fournisseur Dolibarr REXEL';
		}
		if (empty($config['id_customer'])) {
			$missing[] = 'numero client Rexel';
		}
		if (empty($config['base_url'])) {
			$missing[] = 'URL API Rexel';
		}
		if (!empty($config['agence_code']) && !preg_match('/^[0-9]+$/', (string) $config['agence_code'])) {
			$missing[] = 'code agence Rexel numerique';
		}
		if ($config['auth_mode'] === 'bearer' && empty($config['bearer_token'])) {
			$missing[] = 'jeton bearer';
		}
		if (in_array($config['auth_mode'], array('bearer', 'apikey', 'oauth2'), true) && empty($config['api_key'])) {
			$missing[] = 'cle de souscription Rexel';
		}
		if ($config['auth_mode'] === 'oauth2') {
			foreach (array('token_url' => 'URL token OAuth2', 'client_id' => 'client id', 'client_secret' => 'client secret') as $key => $label) {
				if (empty($config[$key])) {
					$missing[] = $label;
				}
			}
			if (empty($config['token_resource']) && empty($config['token_scope'])) {
				$missing[] = 'ressource ou scope OAuth2';
			}
		}

		return $missing;
	}

	/**
	 * Count Rexel supplier price rows.
	 *
	 * @param array<string,string> $filters Filters
	 * @return int
	 */
	public function countSupplierPriceRows(array $filters = array())
	{
		$config = $this->getConfig();
		if (empty($config['supplier_id'])) {
			return 0;
		}

		$sql = "SELECT COUNT(pfp.rowid) AS nb FROM ".MAIN_DB_PREFIX."product_fournisseur_price AS pfp";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = pfp.fk_product";
		$sql .= " WHERE pfp.fk_soc = ".((int) $config['supplier_id']);
		$sql .= " AND pfp.entity IN (".getEntity('productsupplierprice').")";
		$sql .= " AND pfp.ref_fourn IS NOT NULL AND TRIM(pfp.ref_fourn) <> ''";
		$sql .= $this->buildSupplierRowsFilterSql($filters);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		return $obj ? (int) $obj->nb : 0;
	}

	/**
	 * Return Rexel supplier price rows.
	 *
	 * @param int                  $limit Limit
	 * @param int                  $offset Offset
	 * @param array<string,string> $filters Filters
	 * @param string               $sortfield Sort field
	 * @param string               $sortorder Sort order
	 * @return array<int,array<string,mixed>>
	 */
	public function getSupplierPriceRows($limit = 0, $offset = 0, array $filters = array(), $sortfield = 'p.ref', $sortorder = 'ASC')
	{
		$config = $this->getConfig();
		if (empty($config['supplier_id'])) {
			return array();
		}

		$allowedSortFields = array(
			'p.ref' => 'p.ref',
			'p.label' => 'p.label',
			'pfp.ref_fourn' => 'pfp.ref_fourn',
			'pfp.unitprice' => 'pfp.unitprice',
			'ef.supplier_stock' => 'ef.supplier_stock',
		);
		if (empty($allowedSortFields[$sortfield])) {
			$sortfield = 'p.ref';
		}
		$sortorder = strtoupper($sortorder) === 'DESC' ? 'DESC' : 'ASC';

		$sql = "SELECT pfp.rowid AS price_line_id, pfp.fk_product, p.ref AS ref_product, p.label AS label_product,";
		$sql .= " pfp.ref_fourn, pfp.price, pfp.unitprice, pfp.quantity, pfp.tva_tx, pfp.remise_percent, pfp.remise,";
		$sql .= " pfp.charges, pfp.fk_availability, pfp.delivery_time_days, pfp.supplier_reputation, pfp.desc_fourn,";
		$sql .= " ef.supplier_stock";
		$sql .= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price AS pfp";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = pfp.fk_product";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_fournisseur_price_extrafields AS ef ON ef.fk_object = pfp.rowid";
		$sql .= " WHERE pfp.fk_soc = ".((int) $config['supplier_id']);
		$sql .= " AND pfp.entity IN (".getEntity('productsupplierprice').")";
		$sql .= " AND pfp.ref_fourn IS NOT NULL AND TRIM(pfp.ref_fourn) <> ''";
		$sql .= $this->buildSupplierRowsFilterSql($filters);
		$sql .= " ORDER BY ".$allowedSortFields[$sortfield]." ".$sortorder;
		if ($limit > 0) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$rows = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $rows;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$parsed = self::parseSupplierReference($obj->ref_fourn);
			$rows[] = array(
				'price_line_id' => (int) $obj->price_line_id,
				'fk_product' => (int) $obj->fk_product,
				'ref_product' => $obj->ref_product,
				'label_product' => $obj->label_product,
				'ref_fourn' => $obj->ref_fourn,
				'supplier_code' => $parsed['supplier_code'],
				'supplier_com_ref' => $parsed['supplier_com_ref'],
				'price' => ($obj->price !== null ? (float) $obj->price : null),
				'unitprice' => ($obj->unitprice !== null ? (float) $obj->unitprice : null),
				'quantity' => ($obj->quantity !== null ? (float) $obj->quantity : 1),
				'tva_tx' => ($obj->tva_tx !== null ? (float) $obj->tva_tx : 0),
				'remise_percent' => ($obj->remise_percent !== null ? (float) $obj->remise_percent : 0),
				'remise' => ($obj->remise !== null ? (float) $obj->remise : 0),
				'charges' => ($obj->charges !== null ? (float) $obj->charges : 0),
				'fk_availability' => ($obj->fk_availability !== null ? (int) $obj->fk_availability : 0),
				'delivery_time_days' => ($obj->delivery_time_days !== null ? (int) $obj->delivery_time_days : 0),
				'supplier_reputation' => $obj->supplier_reputation,
				'desc_fourn' => $obj->desc_fourn,
				'supplier_stock' => ($obj->supplier_stock !== null ? (float) $obj->supplier_stock : null),
				'valid_ref' => !empty($parsed['valid']),
			);
		}

		return $rows;
	}

	/**
	 * Return supplier price row by id.
	 *
	 * @param int $priceLineId Supplier price id
	 * @return array<string,mixed>|null
	 */
	public function getSupplierPriceRowById($priceLineId)
	{
		$rows = $this->getSupplierPriceRows(1, 0, array('price_line_id' => (string) $priceLineId));
		return !empty($rows[0]) ? $rows[0] : null;
	}

	/**
	 * Return latest logs keyed by supplier price row id.
	 *
	 * @param array<int,int> $priceLineIds Price line ids
	 * @return array<int,array<string,mixed>>
	 */
	public function getLatestLogsByPriceLine(array $priceLineIds)
	{
		$ids = array();
		foreach ($priceLineIds as $id) {
			if ((int) $id > 0) {
				$ids[] = (int) $id;
			}
		}
		if (empty($ids)) {
			return array();
		}

		$sql = "SELECT l.* FROM ".MAIN_DB_PREFIX."rexelsync_log AS l";
		$sql .= " INNER JOIN (";
		$sql .= " SELECT fk_product_fournisseur_price, MAX(datec) AS maxdate";
		$sql .= " FROM ".MAIN_DB_PREFIX."rexelsync_log";
		$sql .= " WHERE fk_product_fournisseur_price IN (".implode(',', $ids).")";
		$sql .= " GROUP BY fk_product_fournisseur_price";
		$sql .= " ) AS lastlog ON lastlog.fk_product_fournisseur_price = l.fk_product_fournisseur_price AND lastlog.maxdate = l.datec";
		$sql .= " WHERE l.fk_product_fournisseur_price IN (".implode(',', $ids).")";

		$logs = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $logs;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$logs[(int) $obj->fk_product_fournisseur_price] = array(
				'datec' => $obj->datec,
				'old_price' => ($obj->old_price !== null ? (float) $obj->old_price : null),
				'new_price' => ($obj->new_price !== null ? (float) $obj->new_price : null),
				'old_stock' => ($obj->old_stock !== null ? (float) $obj->old_stock : null),
				'new_stock' => ($obj->new_stock !== null ? (float) $obj->new_stock : null),
				'status' => $obj->status,
				'message' => $obj->message,
				'http_status' => ($obj->http_status !== null ? (int) $obj->http_status : null),
			);
		}

		return $logs;
	}

	/**
	 * Parse Dolibarr supplier ref to Rexel supplierCode and supplierComRef.
	 *
	 * @param string $ref Supplier ref
	 * @return array<string,mixed>
	 */
	public static function parseSupplierReference($ref)
	{
		$ref = trim((string) $ref);
		if (strlen($ref) <= 3) {
			return array('valid' => false, 'supplier_code' => '', 'supplier_com_ref' => '');
		}

		$supplierCode = strtoupper(substr($ref, 0, 3));
		$supplierComRef = trim(substr($ref, 3));
		$supplierComRef = ltrim($supplierComRef, " \t\n\r\0\x0B-_/");

		return array(
			'valid' => ($supplierCode !== '' && $supplierComRef !== ''),
			'supplier_code' => $supplierCode,
			'supplier_com_ref' => $supplierComRef,
		);
	}

	/**
	 * Sync one row.
	 *
	 * @param array<string,mixed> $row Supplier price row
	 * @param RexelApi            $api API client
	 * @param array<string,mixed> $config Config
	 * @param User                $user User
	 * @return array<string,mixed>
	 */
	private function syncRow(array $row, RexelApi $api, array $config, $user)
	{
		$parsed = self::parseSupplierReference($row['ref_fourn']);
		if (empty($parsed['valid'])) {
			$message = 'Reference fournisseur invalide: '.$row['ref_fourn'].' (format attendu: 3 caracteres fabricant puis reference commerciale)';
			$this->logSync($row, $row['unitprice'], null, $row['supplier_stock'], null, self::STATUS_INVALID_REF, $message, null);
			return array('success' => false, 'status' => self::STATUS_INVALID_REF, 'message' => $message);
		}

		$qty = (int) ceil(!empty($row['quantity']) && $row['quantity'] > 0 ? $row['quantity'] : $config['default_qty']);
		$apiResult = $api->fetchProductPriceAndStock($parsed['supplier_code'], $parsed['supplier_com_ref'], $qty);
		if (empty($apiResult['success'])) {
			$status = !empty($apiResult['status']) ? $apiResult['status'] : self::STATUS_ERROR;
			if ($status !== self::STATUS_NOT_FOUND) {
				$status = self::STATUS_ERROR;
			}
			$this->logSync($row, $row['unitprice'], null, $row['supplier_stock'], null, $status, $apiResult['message'], $apiResult['http_status']);
			return array('success' => false, 'status' => $status, 'message' => $apiResult['message']);
		}

		$newPrice = (float) $apiResult['price'];
		$newStock = isset($apiResult['stock']) ? (float) $apiResult['stock'] : null;
		$oldPrice = ($row['unitprice'] !== null ? (float) $row['unitprice'] : null);
		$oldStock = ($row['supplier_stock'] !== null ? (float) $row['supplier_stock'] : null);

		$priceChanged = ($oldPrice === null || abs($newPrice - $oldPrice) > 0.00001);
		$stockChanged = ($newStock !== null && ($oldStock === null || abs($newStock - $oldStock) > 0.00001));

		if ($priceChanged) {
			$res = $this->updateSupplierPrice($row, $newPrice, $config, $user);
			if ($res < 0) {
				$message = $this->error ?: 'Echec mise a jour prix fournisseur Dolibarr';
				$this->logSync($row, $oldPrice, $newPrice, $oldStock, $newStock, self::STATUS_ERROR, $message, $apiResult['http_status']);
				return array('success' => false, 'status' => self::STATUS_ERROR, 'message' => $message);
			}
		}

		if ($stockChanged) {
			$res = $this->updateSupplierStock((int) $row['price_line_id'], $newStock);
			if ($res < 0) {
				$message = $this->error ?: 'Echec mise a jour stock fournisseur Dolibarr';
				$this->logSync($row, $oldPrice, $newPrice, $oldStock, $newStock, self::STATUS_ERROR, $message, $apiResult['http_status']);
				return array('success' => false, 'status' => self::STATUS_ERROR, 'message' => $message);
			}
		}

		if ($priceChanged) {
			$status = self::STATUS_UPDATED;
		} elseif ($stockChanged) {
			$status = self::STATUS_STOCK_UPDATED;
		} else {
			$status = self::STATUS_UNCHANGED;
		}

		$this->logSync($row, $oldPrice, $newPrice, $oldStock, $newStock, $status, '', $apiResult['http_status']);
		return array('success' => true, 'status' => $status, 'message' => '');
	}

	/**
	 * Update Dolibarr supplier price line.
	 *
	 * @param array<string,mixed> $row Supplier price row
	 * @param float               $newUnitPrice New unit price
	 * @param array<string,mixed> $config Config
	 * @param User                $user User
	 * @return int
	 */
	private function updateSupplierPrice(array $row, $newUnitPrice, array $config, $user)
	{
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

		$qty = (!empty($row['quantity']) && $row['quantity'] > 0) ? (float) $row['quantity'] : 1.0;
		$totalPrice = (float) $newUnitPrice * $qty;

		$supplier = new Societe($this->db);
		$supplier->id = (int) $config['supplier_id'];
		$supplier->rowid = (int) $config['supplier_id'];

		$productFournisseur = new ProductFournisseur($this->db);
		$productFournisseur->id = (int) $row['fk_product'];
		$productFournisseur->fk_product = (int) $row['fk_product'];
		$productFournisseur->fourn_id = (int) $config['supplier_id'];
		$productFournisseur->ref_fourn = $row['ref_fourn'];
		$productFournisseur->fourn_ref = $row['ref_fourn'];
		$productFournisseur->fourn_qty = $qty;
		$productFournisseur->product_fourn_price_id = (int) $row['price_line_id'];

		if ($productFournisseur->fetch_product_fournisseur_price((int) $row['price_line_id']) <= 0) {
			$this->error = 'Ligne de prix fournisseur introuvable: '.$row['price_line_id'];
			return -1;
		}

		$productFournisseur->id = (int) $row['fk_product'];
		$productFournisseur->fk_product = (int) $row['fk_product'];
		$productFournisseur->fourn_id = (int) $config['supplier_id'];
		$productFournisseur->product_fourn_price_id = (int) $row['price_line_id'];

		$res = $productFournisseur->update_buyprice(
			$qty,
			$totalPrice,
			$user,
			'HT',
			$supplier,
			(int) $row['fk_availability'],
			$row['ref_fourn'],
			(float) $row['tva_tx'],
			(float) $row['charges'],
			(float) $row['remise_percent'],
			(float) $row['remise'],
			0,
			(int) $row['delivery_time_days'],
			(string) $row['supplier_reputation'],
			array(),
			'',
			0,
			'HT',
			1,
			'',
			(string) $row['desc_fourn']
		);

		if ($res < 0) {
			$this->error = $productFournisseur->error;
			if (!empty($productFournisseur->errors)) {
				$this->error .= ' '.implode(', ', $productFournisseur->errors);
			}
		}

		return $res;
	}

	/**
	 * Update supplier_stock extrafield on supplier price line.
	 *
	 * @param int   $priceLineId Supplier price id
	 * @param float $newStock New stock
	 * @return int
	 */
	private function updateSupplierStock($priceLineId, $newStock)
	{
		$table = MAIN_DB_PREFIX.'product_fournisseur_price_extrafields';
		$priceLineId = (int) $priceLineId;

		$resql = $this->db->query("SELECT fk_object FROM ".$table." WHERE fk_object = ".$priceLineId);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($resql) > 0) {
			$sql = "UPDATE ".$table." SET supplier_stock = ".price2num($newStock)." WHERE fk_object = ".$priceLineId;
		} else {
			$sql = "INSERT INTO ".$table." (fk_object, supplier_stock) VALUES (".$priceLineId.", ".price2num($newStock).")";
		}

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Insert sync log row.
	 *
	 * @param array<string,mixed> $row Supplier price row
	 * @param float|null          $oldPrice Old price
	 * @param float|null          $newPrice New price
	 * @param float|null          $oldStock Old stock
	 * @param float|null          $newStock New stock
	 * @param string              $status Status
	 * @param string              $message Message
	 * @param int|null            $httpStatus HTTP status
	 * @return int
	 */
	private function logSync(array $row, $oldPrice, $newPrice, $oldStock, $newStock, $status, $message, $httpStatus)
	{
		global $conf;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."rexelsync_log (";
		$sql .= "entity, fk_product, fk_product_fournisseur_price, ref_product, ref_fourn, old_price, new_price, old_stock, new_stock, status, message, http_status, datec";
		$sql .= ") VALUES (";
		$sql .= ((int) $conf->entity).",";
		$sql .= ((int) $row['fk_product']).",";
		$sql .= ((int) $row['price_line_id']).",";
		$sql .= "'".$this->db->escape((string) $row['ref_product'])."',";
		$sql .= "'".$this->db->escape((string) $row['ref_fourn'])."',";
		$sql .= ($oldPrice !== null ? price2num($oldPrice) : 'NULL').",";
		$sql .= ($newPrice !== null ? price2num($newPrice) : 'NULL').",";
		$sql .= ($oldStock !== null ? price2num($oldStock) : 'NULL').",";
		$sql .= ($newStock !== null ? price2num($newStock) : 'NULL').",";
		$sql .= "'".$this->db->escape($status)."',";
		$sql .= "'".$this->db->escape($message)."',";
		$sql .= ($httpStatus !== null ? (int) $httpStatus : 'NULL').",";
		$sql .= "'".$this->db->idate(dol_now())."'";
		$sql .= ")";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Build supplier rows SQL filters.
	 *
	 * @param array<string,string> $filters Filters
	 * @return string
	 */
	private function buildSupplierRowsFilterSql(array $filters)
	{
		$sql = '';
		if (!empty($filters['price_line_id'])) {
			$sql .= " AND pfp.rowid = ".((int) $filters['price_line_id']);
		}
		if (!empty($filters['search_ref_product'])) {
			$sql .= " AND p.ref LIKE '%".$this->db->escape($filters['search_ref_product'])."%'";
		}
		if (!empty($filters['search_label_product'])) {
			$sql .= " AND p.label LIKE '%".$this->db->escape($filters['search_label_product'])."%'";
		}
		if (!empty($filters['search_ref_fourn'])) {
			$sql .= " AND pfp.ref_fourn LIKE '%".$this->db->escape($filters['search_ref_fourn'])."%'";
		}

		return $sql;
	}

	/**
	 * Return empty stats.
	 *
	 * @return array<string,mixed>
	 */
	private function emptyStats()
	{
		return array(
			'total' => 0,
			'success' => 0,
			self::STATUS_UPDATED => 0,
			self::STATUS_STOCK_UPDATED => 0,
			self::STATUS_UNCHANGED => 0,
			self::STATUS_ERROR => 0,
			self::STATUS_NOT_FOUND => 0,
			self::STATUS_INVALID_REF => 0,
			'fatal' => false,
			'message' => '',
		);
	}

	/**
	 * Build a readable stats output.
	 *
	 * @param array<string,mixed> $stats Stats
	 * @return string
	 */
	private function buildStatsOutput(array $stats)
	{
		return 'RexelSync: '.((int) $stats['total']).' lignes, '
			.((int) $stats[self::STATUS_UPDATED]).' prix mis a jour, '
			.((int) $stats[self::STATUS_STOCK_UPDATED]).' stocks seuls mis a jour, '
			.((int) $stats[self::STATUS_UNCHANGED]).' inchangees, '
			.(((int) $stats[self::STATUS_ERROR]) + ((int) $stats[self::STATUS_NOT_FOUND]) + ((int) $stats[self::STATUS_INVALID_REF])).' erreurs.';
	}
}
