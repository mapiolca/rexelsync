<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Compatibility helpers for RexelSync module.
 *
 * @phpstan-type RexelSyncCompatibilityFeature array{
 *     label: string,
 *     description: string,
 *     min_dolibarr: string,
 *     min_php: string,
 *     checks: list<string>,
 *     available?: bool,
 *     reason?: string
 * }
 * @phpstan-type RexelSyncCompatibilityStatus array{
 *     label: string,
 *     description: string,
 *     min_dolibarr: string,
 *     min_php: string,
 *     checks: list<string>,
 *     code: string,
 *     available: bool,
 *     reason: string
 * }
 */
class RexelSyncCompatibility
{
	public const MIN_DOLIBARR_VERSION = '20.0.0';
	public const MIN_PHP_VERSION = '8.0.0';

	/**
	 * Check current Dolibarr version.
	 *
	 * @param string $version Version to compare with
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Check current PHP version.
	 *
	 * @param string $version Version to compare with
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Return feature compatibility matrix.
	 *
	 * @return array<string,RexelSyncCompatibilityFeature>
	 */
	public static function getFeatures()
	{
		return array(
			'core_module' => array(
				'label' => 'RexelSyncCompatibilityFeatureCore',
				'description' => 'RexelSyncCompatibilityFeatureCoreDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array(),
			),
			'rexel_api_curl' => array(
				'label' => 'RexelSyncCompatibilityFeatureApiCurl',
				'description' => 'RexelSyncCompatibilityFeatureApiCurlDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('curl_init'),
			),
			'price_sync_decouverte' => array(
				'label' => 'RexelSyncCompatibilityFeaturePriceSync',
				'description' => 'RexelSyncCompatibilityFeaturePriceSyncDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('curl_init'),
			),
			'stock_sync_decouverte' => array(
				'label' => 'RexelSyncCompatibilityFeatureStockSync',
				'description' => 'RexelSyncCompatibilityFeatureStockSyncDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('curl_init', 'ExtraFields'),
			),
			'customer_profile_decouverte' => array(
				'label' => 'RexelSyncCompatibilityFeatureCustomer',
				'description' => 'RexelSyncCompatibilityFeatureCustomerDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('curl_init'),
			),
			'product_units_decouverte' => array(
				'label' => 'RexelSyncCompatibilityFeatureProductUnits',
				'description' => 'RexelSyncCompatibilityFeatureProductUnitsDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('curl_init'),
				'available' => false,
				'reason' => 'RexelSyncCompatibilityReasonNotIntegrated',
			),
			'quotes_decouverte' => array(
				'label' => 'RexelSyncCompatibilityFeatureQuotes',
				'description' => 'RexelSyncCompatibilityFeatureQuotesDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('curl_init'),
				'available' => false,
				'reason' => 'RexelSyncCompatibilityReasonNotIntegrated',
			),
			'orders_decouverte' => array(
				'label' => 'RexelSyncCompatibilityFeatureOrders',
				'description' => 'RexelSyncCompatibilityFeatureOrdersDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('curl_init'),
				'available' => false,
				'reason' => 'RexelSyncCompatibilityReasonNotIntegrated',
			),
			'ajax_batch_sync' => array(
				'label' => 'RexelSyncCompatibilityFeatureAjaxBatch',
				'description' => 'RexelSyncCompatibilityFeatureAjaxBatchDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array(),
			),
			'native_cron' => array(
				'label' => 'RexelSyncCompatibilityFeatureCron',
				'description' => 'RexelSyncCompatibilityFeatureCronDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array(),
			),
			'supplier_stock_extrafield' => array(
				'label' => 'RexelSyncCompatibilityFeatureSupplierStock',
				'description' => 'RexelSyncCompatibilityFeatureSupplierStockDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('ExtraFields'),
			),
			'sync_logs' => array(
				'label' => 'RexelSyncCompatibilityFeatureLogs',
				'description' => 'RexelSyncCompatibilityFeatureLogsDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array(),
			),
			'premium_media' => self::premiumOnlyFeature('RexelSyncCompatibilityFeaturePremiumMedia', 'RexelSyncCompatibilityFeaturePremiumMediaDesc'),
			'premium_product_cee' => self::premiumOnlyFeature('RexelSyncCompatibilityFeaturePremiumCee', 'RexelSyncCompatibilityFeaturePremiumCeeDesc'),
			'premium_product_environmental' => self::premiumOnlyFeature('RexelSyncCompatibilityFeaturePremiumEnvironmental', 'RexelSyncCompatibilityFeaturePremiumEnvironmentalDesc'),
			'premium_product_replacement' => self::premiumOnlyFeature('RexelSyncCompatibilityFeaturePremiumReplacement', 'RexelSyncCompatibilityFeaturePremiumReplacementDesc'),
			'premium_product_sustainable' => self::premiumOnlyFeature('RexelSyncCompatibilityFeaturePremiumSustainable', 'RexelSyncCompatibilityFeaturePremiumSustainableDesc'),
		);
	}

	/**
	 * Check if a feature is available.
	 *
	 * @param string $feature Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($feature)
	{
		$features = self::getFeatures();
		if (empty($features[$feature])) {
			return false;
		}

		return self::getFeatureStatus($feature, $features[$feature])['available'];
	}

	/**
	 * Return unavailable feature statuses.
	 *
	 * @return array<string,RexelSyncCompatibilityStatus>
	 */
	public static function getUnavailableFeatures()
	{
		$unavailable = array();
		foreach (self::getFeatures() as $code => $feature) {
			$status = self::getFeatureStatus($code, $feature);
			if (empty($status['available'])) {
				$unavailable[$code] = $status;
			}
		}

		return $unavailable;
	}

	/**
	 * Return computed feature status.
	 *
	 * @param string $code Feature code
	 * @param RexelSyncCompatibilityFeature $feature Feature definition
	 * @return RexelSyncCompatibilityStatus
	 */
	public static function getFeatureStatus($code, $feature)
	{
		$available = true;
		$reason = 'RexelSyncCompatibilityReasonAvailable';

		if (!self::isDolibarrVersionAtLeast($feature['min_dolibarr'])) {
			$available = false;
			$reason = 'RexelSyncCompatibilityReasonDolibarr';
		}
		if ($available && !self::isPhpVersionAtLeast($feature['min_php'])) {
			$available = false;
			$reason = 'RexelSyncCompatibilityReasonPhp';
		}
		if ($available) {
			foreach ($feature['checks'] as $check) {
				if ($check === 'curl_init' && !function_exists('curl_init')) {
					$available = false;
					$reason = 'RexelSyncCompatibilityReasonCurl';
					break;
				}
				if ($check === 'ExtraFields' && !class_exists('ExtraFields')) {
					$available = false;
					$reason = 'RexelSyncCompatibilityReasonClassMissing';
					break;
				}
			}
		}
		if ($available && array_key_exists('available', $feature) && empty($feature['available'])) {
			$available = false;
			$reason = !empty($feature['reason']) ? $feature['reason'] : 'RexelSyncCompatibilityReasonUnavailable';
		}

		return array(
			'label' => $feature['label'],
			'description' => $feature['description'],
			'min_dolibarr' => $feature['min_dolibarr'],
			'min_php' => $feature['min_php'],
			'checks' => $feature['checks'],
			'code' => $code,
			'available' => $available,
			'reason' => $reason,
		);
	}

	/**
	 * Build a Premium-only feature definition.
	 *
	 * @param string $label Label translation key
	 * @param string $description Description translation key
	 * @return RexelSyncCompatibilityFeature
	 */
	private static function premiumOnlyFeature($label, $description)
	{
		return array(
			'label' => $label,
			'description' => $description,
			'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
			'min_php' => self::MIN_PHP_VERSION,
			'checks' => array(),
			'available' => false,
			'reason' => 'RexelSyncCompatibilityReasonPremiumOnly',
		);
	}
}