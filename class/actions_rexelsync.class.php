<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * RexelSync hooks.
 */
class ActionsRexelSync extends CommonHookActions
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $resprints = '';

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
	 * Add supplier stock badge on supplier proposal/order lines.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject        $object Object
	 * @param string              $action Action
	 * @param HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function objectLineView_ProductSupplier($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$this->resprints = '';
		$langs->load('rexelsync@rexelsync');

		if (!$this->isRexelSupplierObject($object)) {
			return 0;
		}

		$line = !empty($parameters['line']) ? $parameters['line'] : null;
		if (empty($line) || empty($line->fk_product)) {
			return 0;
		}

		$priceLineId = $this->resolveSupplierPriceLineId($line, (int) getDolGlobalInt('REXELSYNC_SUPPLIER_ID'));
		if ($priceLineId <= 0) {
			return 0;
		}

		$stock = $this->fetchSupplierStock($priceLineId);
		$label = $stock !== null ? price($stock, 0, '', 0, 0) : '-';
		$css = $stock === null ? 'badge-status0' : ((float) $stock > 0 ? 'badge-status4' : 'badge-status8');
		$title = $langs->trans('RexelSyncLineStockTooltip');

		$this->resprints .= '<div class="clearboth rexelsync-line-stock">';
		$this->resprints .= '<span class="opacitymedium">'.$langs->trans('RexelSyncSupplierStock').' : </span>';
		$this->resprints .= '<span class="badge '.$css.'" title="'.dol_escape_htmltag($title).'">'.$label.'</span>';
		$this->resprints .= '</div>';

		return 0;
	}

	/**
	 * Check if current object is a Rexel supplier proposal/order.
	 *
	 * @param CommonObject $object Object
	 * @return bool
	 */
	private function isRexelSupplierObject($object)
	{
		if (empty($object) || !is_object($object)) {
			return false;
		}

		$allowed = array('supplier_proposal', 'order_supplier', 'commande_fournisseur');
		if (empty($object->element) || !in_array($object->element, $allowed, true)) {
			return false;
		}

		$supplierId = (int) getDolGlobalInt('REXELSYNC_SUPPLIER_ID');
		if ($supplierId <= 0) {
			return false;
		}

		$objectSupplierId = 0;
		if (!empty($object->socid)) {
			$objectSupplierId = (int) $object->socid;
		} elseif (!empty($object->thirdparty) && !empty($object->thirdparty->id)) {
			$objectSupplierId = (int) $object->thirdparty->id;
		}

		return $objectSupplierId === $supplierId;
	}

	/**
	 * Resolve supplier price line id from object line.
	 *
	 * @param CommonObjectLine $line Line
	 * @param int              $supplierId Supplier id
	 * @return int
	 */
	private function resolveSupplierPriceLineId($line, $supplierId)
	{
		if (!empty($line->fk_fournprice)) {
			return (int) $line->fk_fournprice;
		}

		$refSupplier = '';
		if (!empty($line->ref_fourn)) {
			$refSupplier = $line->ref_fourn;
		} elseif (!empty($line->ref_supplier)) {
			$refSupplier = $line->ref_supplier;
		}
		if (empty($line->fk_product) || $refSupplier === '') {
			return 0;
		}

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
		$sql .= " WHERE fk_product = ".((int) $line->fk_product);
		$sql .= " AND fk_soc = ".((int) $supplierId);
		$sql .= " AND ref_fourn = '".$this->db->escape($refSupplier)."'";
		$sql .= " ORDER BY quantity ASC";
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (int) $obj->rowid;
		}

		return 0;
	}

	/**
	 * Fetch supplier_stock extrafield.
	 *
	 * @param int $priceLineId Supplier price line id
	 * @return float|null
	 */
	private function fetchSupplierStock($priceLineId)
	{
		$sql = "SELECT supplier_stock FROM ".MAIN_DB_PREFIX."product_fournisseur_price_extrafields";
		$sql .= " WHERE fk_object = ".((int) $priceLineId);

		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return $obj->supplier_stock !== null ? (float) $obj->supplier_stock : null;
		}

		return null;
	}
}
