<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Admin tabs.
 *
 * @return array<int,array<int,string>>
 */
function rexelsyncAdminPrepareHead()
{
	global $conf, $langs;

	$langs->load('rexelsync@rexelsync');

	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/rexelsync/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/rexelsync/admin/compatibility.php', 1);
	$head[$h][1] = $langs->trans('Compatibility');
	$head[$h][2] = 'compatibility';
	$h++;

	$head[$h][0] = dol_buildpath('/rexelsync/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'rexelsync@rexelsync');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'rexelsync@rexelsync', 'remove');

	return $head;
}

/**
 * Front tabs.
 *
 * @param string   $active Active tab
 * @param int|null $syncCount Optional synchronization row count
 * @return array<int,array<int,string>>
 */
function rexelsyncPrepareHead($active = '', $syncCount = null)
{
	global $langs, $user;

	$langs->load('rexelsync@rexelsync');

	$head = array();
	$syncLabel = $langs->trans('RexelSyncSync');
	if ($syncCount !== null) {
		$syncLabel .= ' <span class="badge badge-info">'.((int) $syncCount).'</span>';
	}
	$head[] = array(dol_buildpath('/rexelsync/sync.php', 1), $syncLabel, 'sync');
	$head[] = array(dol_buildpath('/rexelsync/logs.php', 1), $langs->trans('RexelSyncLogs'), 'logs');
	if (!empty($user->admin) || $user->hasRight('rexelsync', 'config', 'write')) {
		$head[] = array(dol_buildpath('/rexelsync/admin/setup.php', 1), $langs->trans('RexelSyncSetup'), 'settings');
	}

	return $head;
}

/**
 * Render a sync status badge.
 *
 * @param string $status Status code
 * @return string
 */
function rexelsyncStatusBadge($status)
{
	global $langs;

	$status = (string) $status;
	if ($status === 'updated') {
		return '<span class="badge badge-status4">'.$langs->trans('RexelSyncStatusUpdated').'</span>';
	}
	if ($status === 'stock_updated') {
		return '<span class="badge badge-status4">'.$langs->trans('RexelSyncStatusStockUpdated').'</span>';
	}
	if ($status === 'unchanged') {
		return '<span class="badge badge-status1">'.$langs->trans('RexelSyncStatusUnchanged').'</span>';
	}
	if ($status === 'not_found') {
		return '<span class="badge badge-status8">'.$langs->trans('RexelSyncStatusNotFound').'</span>';
	}
	if ($status === 'invalid_ref') {
		return '<span class="badge badge-status8">'.$langs->trans('RexelSyncStatusInvalidRef').'</span>';
	}
	if ($status === 'never_synced') {
		return '<span class="badge badge-status0">'.$langs->trans('RexelSyncStatusNeverSynced').'</span>';
	}
	if ($status === 'error') {
		return '<span class="badge badge-status8">'.$langs->trans('RexelSyncStatusError').'</span>';
	}

	return '<span class="badge badge-status0">'.dol_escape_htmltag($status).'</span>';
}

/**
 * Translate known RexelSync or Rexel API messages for display.
 *
 * @param string $message Raw message
 * @return string
 */
function rexelsyncTranslateMessage($message)
{
	global $langs;

	$message = trim((string) $message);
	if ($message === '') {
		return '';
	}

	if (preg_match('/^([0-9A-Z_-]+)\s*-\s*The product with the reference\s*:\s*(.+?)\s+and supplier code\s*:\s*(.+?)\s+was either prohibited or unavailable for sale\.?$/i', $message, $matches)) {
		return $langs->trans('RexelSyncApiErrorProductUnavailable', $matches[1], $matches[2], $matches[3]);
	}
	if (preg_match('/^The product with the reference\s*:\s*(.+?)\s+and supplier code\s*:\s*(.+?)\s+was either prohibited or unavailable for sale\.?$/i', $message, $matches)) {
		return $langs->trans('RexelSyncApiErrorProductUnavailableNoCode', $matches[1], $matches[2]);
	}
	if (preg_match('/^BW-RESTJSON-100016\b/i', $message)) {
		return $langs->trans('RexelSyncApiErrorRestJsonSchema', $message);
	}
	if (preg_match('/^Configuration RexelSync incomplete: (.+)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorConfigurationIncomplete', $matches[1]);
	}
	if (preg_match('/^Reference fournisseur invalide: (.+?) \(format attendu: 3 caracteres fabricant puis reference commerciale\)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorInvalidSupplierRef', $matches[1]);
	}
	if (preg_match('/^Ligne de prix fournisseur introuvable: ([0-9]+)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorSupplierPriceLineMissingWithId', $matches[1]);
	}
	if ($message === 'Aucune ligne de prix fournisseur Rexel a synchroniser.') {
		return $langs->trans('RexelSyncErrorNoSupplierRows');
	}
	if ($message === 'Ligne de prix fournisseur introuvable ou hors fournisseur Rexel') {
		return $langs->trans('RexelSyncErrorSupplierPriceLineMissing');
	}
	if ($message === 'Echec mise a jour prix fournisseur Dolibarr') {
		return $langs->trans('RexelSyncErrorPriceUpdateFailed');
	}
	if ($message === 'Echec mise a jour stock fournisseur Dolibarr') {
		return $langs->trans('RexelSyncErrorStockUpdateFailed');
	}
	if ($message === 'Produit non retourne par l API prix Rexel') {
		return $langs->trans('RexelSyncErrorPriceApiProductMissing');
	}
	if ($message === 'Produit non retourne par l API stock Rexel') {
		return $langs->trans('RexelSyncErrorStockApiProductMissing');
	}
	if ($message === 'Prix net client introuvable dans la reponse Rexel') {
		return $langs->trans('RexelSyncErrorNetPriceMissing');
	}
	if ($message === 'Stocks Rexel introuvables dans la reponse') {
		return $langs->trans('RexelSyncErrorStockMissing');
	}
	if ($message === 'Numero client Rexel invalide: idCustomer doit etre numerique') {
		return $langs->trans('RexelSyncErrorCustomerIdInvalid');
	}
	if ($message === 'Configuration OAuth2 Rexel incomplete') {
		return $langs->trans('RexelSyncErrorOAuth2ConfigurationIncomplete');
	}
	if ($message === 'Jeton bearer Rexel manquant') {
		return $langs->trans('RexelSyncErrorBearerTokenMissing');
	}
	if ($message === 'Cle de souscription Rexel manquante') {
		return $langs->trans('RexelSyncErrorSubscriptionKeyMissing');
	}
	if ($message === 'Nom d en-tete de souscription Rexel invalide') {
		return $langs->trans('RexelSyncErrorSubscriptionHeaderInvalid');
	}
	if (preg_match('/^Mode authentification Rexel inconnu: (.+)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorUnknownAuthMode', $matches[1]);
	}
	if (preg_match('/^Payload Rexel non JSON: (.+)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorPayloadJson', $matches[1]);
	}
	if (preg_match('/^Reponse Rexel non JSON: (.+)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorResponseJson', $matches[1]);
	}
	if (preg_match('/^Erreur cURL Rexel: (.+)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorCurl', $matches[1]);
	}
	if (preg_match('/^Erreur cURL token Rexel: (.+)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorCurlToken', $matches[1]);
	}
	if (preg_match('/^Erreur HTTP Rexel ([0-9]+)$/', $message, $matches)) {
		return $langs->trans('RexelSyncErrorHttp', $matches[1]);
	}
	if ($message === 'Extension PHP cURL indisponible') {
		return $langs->trans('RexelSyncErrorCurlExtensionMissing');
	}
	if ($message === 'Impossible de recuperer le token Rexel OAuth2') {
		return $langs->trans('RexelSyncErrorOAuth2Token');
	}

	return $message;
}

/**
 * Return escaped current page URL.
 *
 * @return string
 */
function rexelsyncCurrentPage()
{
	return dol_escape_htmltag($_SERVER['PHP_SELF']);
}
