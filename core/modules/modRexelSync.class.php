<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * RexelSync module descriptor.
 */
class modRexelSync extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		parent::__construct($db);

		$this->numero = 450015;
		$this->rights_class = 'rexelsync';
		$this->family = 'Les Metiers du Batiment';
		$this->module_position = '91';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'RexelSyncDesc';
		$this->descriptionlong = 'RexelSyncDescLong';
		$this->editor_name = 'RexelSync';
		$this->editor_url = '';
		$this->version = '1.0.3';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-sync';
		$this->langfiles = array('rexelsync@rexelsync');

		$this->dirs = array('/rexelsync/temp');
		$this->config_page_url = array('setup.php@rexelsync');
		$this->depends = array('modProduct', 'modFournisseur');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);

		$this->module_parts = array(
			'hooks' => array('ordersuppliercard', 'supplier_proposalcard'),
		);

		$this->const = array();

		$this->cronjobs = array(
			array(
				'label' => 'RexelSyncCronSync',
				'jobtype' => 'method',
				'class' => '/rexelsync/class/rexelsync.class.php',
				'objectname' => 'RexelSync',
				'method' => 'syncAllSupplierPrices',
				'parameters' => '',
				'comment' => 'RexelSyncCronComment',
				'frequency' => 24,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => 'isModEnabled("rexelsync")',
			),
		);

		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Read RexelSync data and logs';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'sync';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Run RexelSync synchronization';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'sync';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Configure RexelSync';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'config';
		$this->rights[$r][5] = 'write';

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=products',
			'type' => 'left',
			'titre' => 'Rexel Sync',
			'mainmenu' => 'products',
			'leftmenu' => 'rexelsync',
			'url' => '/rexelsync/sync.php',
			'langs' => 'rexelsync@rexelsync',
			'position' => 910,
			'enabled' => 'isModEnabled("rexelsync")',
			'perms' => '$user->hasRight("rexelsync", "sync", "read")',
			'target' => '',
			'user' => 2,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=products,fk_leftmenu=rexelsync',
			'type' => 'left',
			'titre' => 'RexelSyncSync',
			'mainmenu' => 'products',
			'leftmenu' => 'rexelsync_sync',
			'url' => '/rexelsync/sync.php',
			'langs' => 'rexelsync@rexelsync',
			'position' => 911,
			'enabled' => 'isModEnabled("rexelsync")',
			'perms' => '$user->hasRight("rexelsync", "sync", "read")',
			'user' => 2,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=products,fk_leftmenu=rexelsync',
			'type' => 'left',
			'titre' => 'RexelSyncLogs',
			'mainmenu' => 'products',
			'leftmenu' => 'rexelsync_logs',
			'url' => '/rexelsync/logs.php',
			'langs' => 'rexelsync@rexelsync',
			'position' => 912,
			'enabled' => 'isModEnabled("rexelsync")',
			'perms' => '$user->hasRight("rexelsync", "sync", "read")',
			'user' => 2,
		);
	}

	/**
	 * Module activation.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/rexelsync/sql/');
		if ($result < 0) {
			return -1;
		}

		$result = $this->_init(array(), $options);
		if ($result < 0) {
			return -1;
		}

		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

		dolibarr_set_const($this->db, 'REXELSYNC_SUPPLIER_STOCK_EXTRAFIELD', '1', 'yesno', 0, '', 0);

		$extrafields = new ExtraFields($this->db);
		$existing = $extrafields->fetch_name_optionals_label('product_fournisseur_price');
		if (!isset($existing['supplier_stock'])) {
			$res = $extrafields->addExtraField(
				'supplier_stock',
				'RexelSyncSupplierStockLabel',
				'double',
				100,
				'24,8',
				'product_fournisseur_price',
				0,
				0,
				'',
				'',
				1,
				'',
				1,
				'RexelSyncSupplierStockTooltip',
				'',
				0,
				'rexelsync@rexelsync'
			);
			if ($res < 0) {
				$this->error = $extrafields->error;
				return -1;
			}
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."extrafields";
		$sql .= " SET enabled = '(getDolGlobalInt(\"REXELSYNC_SUPPLIER_STOCK_EXTRAFIELD\") == 1)', entity = 0";
		$sql .= " WHERE name = 'supplier_stock' AND elementtype = 'product_fournisseur_price'";
		$this->db->query($sql);

		return $result;
	}

	/**
	 * Module removal.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		dolibarr_del_const($this->db, 'REXELSYNC_SUPPLIER_STOCK_EXTRAFIELD', 0);

		return $this->_remove(array(), $options);
	}
}
