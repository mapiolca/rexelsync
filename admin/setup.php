<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/../lib/rexelsync.lib.php';
require_once __DIR__.'/../class/rexelsync.class.php';

$langs->loadLangs(array('admin', 'companies', 'suppliers', 'rexelsync@rexelsync'));

if (empty($user->admin) && !$user->hasRight('rexelsync', 'config', 'write')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if (($action === 'update' || $action === 'create_supplier') && function_exists('checkToken')) {
	checkToken();
}

if ($action === 'create_supplier') {
	$supplierId = rexelsyncCreateOrFetchSupplier($db, $user);
	if ($supplierId > 0) {
		dolibarr_set_const($db, 'REXELSYNC_SUPPLIER_ID', (string) $supplierId, 'chaine', 0, '', $conf->entity);
		setEventMessages($langs->trans('RexelSyncSupplierLinked'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('RexelSyncSupplierCreateError'), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'update') {
	$environment = RexelSync::normalizeEnvironment(GETPOST('REXELSYNC_ENVIRONMENT', 'alpha'));
	$tenantId = trim(GETPOST('REXELSYNC_TENANT_ID', 'restricthtml'));
	if ($tenantId === '') {
		$tenantId = RexelSync::DEFAULT_TENANT_ID;
	}

	$baseUrl = trim(GETPOST('REXELSYNC_BASE_URL', 'restricthtml'));
	$tokenUrl = trim(GETPOST('REXELSYNC_TOKEN_URL', 'restricthtml'));
	$tokenScope = trim(GETPOST('REXELSYNC_TOKEN_SCOPE', 'restricthtml'));
	if ($environment !== RexelSync::ENV_CUSTOM) {
		$baseUrl = RexelSync::getDefaultBaseUrl($environment);
		$tokenUrl = RexelSync::buildDefaultTokenUrl($tenantId);
		$tokenScope = RexelSync::getDefaultTokenScope($environment);
	} else {
		if ($baseUrl === '') {
			$baseUrl = RexelSync::getDefaultBaseUrl(RexelSync::ENV_DEV);
		}
		if ($tokenUrl === '') {
			$tokenUrl = RexelSync::buildDefaultTokenUrl($tenantId);
		}
	}

	dolibarr_set_const($db, 'REXELSYNC_SUPPLIER_ID', (string) GETPOST('REXELSYNC_SUPPLIER_ID', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_ID_CUSTOMER', trim(GETPOST('REXELSYNC_ID_CUSTOMER', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_ENVIRONMENT', $environment, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_TENANT_ID', $tenantId, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_BASE_URL', $baseUrl, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_AUTH_MODE', 'oauth2', 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_API_KEY_HEADER', RexelSync::SUBSCRIPTION_KEY_HEADER, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_CLIENT_ID', trim(GETPOST('REXELSYNC_CLIENT_ID', 'restricthtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_TOKEN_URL', $tokenUrl, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_TOKEN_SCOPE', $tokenScope, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_ID_COD_ORIGIN', trim(GETPOST('REXELSYNC_ID_COD_ORIGIN', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_AGENCE_CODE', trim(GETPOST('REXELSYNC_AGENCE_CODE', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_ZIP_CODE', trim(GETPOST('REXELSYNC_ZIP_CODE', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_CITY', trim(GETPOST('REXELSYNC_CITY', 'restricthtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_SALES_AGREEMENT', trim(GETPOST('REXELSYNC_SALES_AGREEMENT', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_DELAY_MS', (string) max(0, GETPOST('REXELSYNC_DELAY_MS', 'int')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'REXELSYNC_DEFAULT_QTY', (string) max(1, GETPOST('REXELSYNC_DEFAULT_QTY', 'int')), 'chaine', 0, '', $conf->entity);

	foreach (array(
		'REXELSYNC_API_KEY' => 'REXELSYNC_API_KEY',
		'REXELSYNC_CLIENT_SECRET' => 'REXELSYNC_CLIENT_SECRET',
	) as $postName => $constName) {
		$value = GETPOST($postName, 'restricthtml');
		if ($value !== '') {
			dolibarr_set_const($db, $constName, dol_encode($value), 'chaine', 0, '', $conf->entity);
		}
	}

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$form = new Form($db);
$title = $langs->trans('RexelSyncSetup');

llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

$head = rexelsyncAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $title, -1, 'fa-sync');

$supplierOptions = rexelsyncGetSupplierOptions($db);
$currentSupplierId = getDolGlobalInt('REXELSYNC_SUPPLIER_ID');
$rawEnvironment = getDolGlobalString('REXELSYNC_ENVIRONMENT');
if ($rawEnvironment === '' && getDolGlobalString('REXELSYNC_BASE_URL') === RexelSync::PRD_BASE_URL) {
	$rawEnvironment = RexelSync::ENV_PRD;
}
$environment = RexelSync::normalizeEnvironment($rawEnvironment);
$tenantId = getDolGlobalString('REXELSYNC_TENANT_ID') ?: RexelSync::DEFAULT_TENANT_ID;
$baseUrl = getDolGlobalString('REXELSYNC_BASE_URL');
if ($environment !== RexelSync::ENV_CUSTOM || $baseUrl === '') {
	$baseUrl = RexelSync::getDefaultBaseUrl($environment);
}
$tokenUrl = getDolGlobalString('REXELSYNC_TOKEN_URL');
if ($environment !== RexelSync::ENV_CUSTOM || $tokenUrl === '') {
	$tokenUrl = RexelSync::buildDefaultTokenUrl($tenantId);
}
$tokenScope = getDolGlobalString('REXELSYNC_TOKEN_SCOPE');
if ($environment !== RexelSync::ENV_CUSTOM || $tokenScope === '') {
	$tokenScope = RexelSync::getDefaultTokenScope($environment);
}
$hasSubscriptionKey = getDolGlobalString('REXELSYNC_API_KEY') !== '';
$hasClientSecret = getDolGlobalString('REXELSYNC_CLIENT_SECRET') !== '';
$advancedOpen = $environment === RexelSync::ENV_CUSTOM ? ' open' : '';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('RexelSyncSupplierMapping').'</td></tr>';
print '<tr class="oddeven">';
print '<td class="titlefield">'.rexelsyncLabelWithTooltip('RexelSyncSupplier', 'RexelSyncSupplierHelp', true).'</td>';
print '<td>'.$form->selectarray('REXELSYNC_SUPPLIER_ID', $supplierOptions, $currentSupplierId, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td>';
print '<td class="opacitymedium">'.$langs->trans('RexelSyncSupplierHelp').'</td>';
print '</tr>';

print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('RexelSyncApiSettings').'</td></tr>';
print rexelsyncSelectRow('RexelSyncEnvironment', 'REXELSYNC_ENVIRONMENT', array(
	RexelSync::ENV_DEV => $langs->trans('RexelSyncEnvironmentDev'),
	RexelSync::ENV_PRD => $langs->trans('RexelSyncEnvironmentPrd'),
	RexelSync::ENV_CUSTOM => $langs->trans('RexelSyncEnvironmentCustom'),
), $environment, 'RexelSyncEnvironmentHelp', true, $form);
print rexelsyncInputRow('RexelSyncCustomerId', 'REXELSYNC_ID_CUSTOMER', getDolGlobalString('REXELSYNC_ID_CUSTOMER'), 'text', 'RexelSyncCustomerIdHelp', true);
print rexelsyncSecretRow('RexelSyncSubscriptionKey', 'REXELSYNC_API_KEY', $hasSubscriptionKey, 'RexelSyncSubscriptionKeyHelp', true);
print rexelsyncInputRow('RexelSyncClientId', 'REXELSYNC_CLIENT_ID', getDolGlobalString('REXELSYNC_CLIENT_ID'), 'text', 'RexelSyncClientIdHelp', true);
print rexelsyncSecretRow('RexelSyncClientSecret', 'REXELSYNC_CLIENT_SECRET', $hasClientSecret, 'RexelSyncClientSecretHelp', true);
print '</table>';

print '<details class="rexelsync-advanced-config"'.$advancedOpen.'>';
print '<summary class="opacitymedium">'.$langs->trans('RexelSyncAdvancedSettings').'</summary>';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans('RexelSyncAdvancedSettingsHelp').'</td></tr>';
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('RexelSyncAdvancedConnectionSettings').'</td></tr>';
print rexelsyncInputRow('RexelSyncBaseUrl', 'REXELSYNC_BASE_URL', $baseUrl, 'url', 'RexelSyncBaseUrlHelp', false);
print rexelsyncInputRow('RexelSyncTenantId', 'REXELSYNC_TENANT_ID', $tenantId, 'text', 'RexelSyncTenantIdHelp', false);
print rexelsyncInputRow('RexelSyncTokenUrl', 'REXELSYNC_TOKEN_URL', $tokenUrl, 'url', 'RexelSyncTokenUrlHelp', false);
print rexelsyncInputRow('RexelSyncTokenScope', 'REXELSYNC_TOKEN_SCOPE', $tokenScope, 'text', 'RexelSyncTokenScopeHelp', false);

print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('RexelSyncRequestSettings').'</td></tr>';
print rexelsyncInputRow('RexelSyncIdCodOrigin', 'REXELSYNC_ID_COD_ORIGIN', getDolGlobalString('REXELSYNC_ID_COD_ORIGIN'), 'text', 'RexelSyncIdCodOriginHelp', false);
print rexelsyncInputRow('RexelSyncAgenceCode', 'REXELSYNC_AGENCE_CODE', getDolGlobalString('REXELSYNC_AGENCE_CODE'), 'text', 'RexelSyncAgenceCodeHelp', false);
print rexelsyncInputRow('RexelSyncZipCode', 'REXELSYNC_ZIP_CODE', getDolGlobalString('REXELSYNC_ZIP_CODE'), 'text', 'RexelSyncZipCodeHelp', false);
print rexelsyncInputRow('RexelSyncCity', 'REXELSYNC_CITY', getDolGlobalString('REXELSYNC_CITY'), 'text', 'RexelSyncCityHelp', false);
print rexelsyncInputRow('RexelSyncSalesAgreement', 'REXELSYNC_SALES_AGREEMENT', getDolGlobalString('REXELSYNC_SALES_AGREEMENT'), 'text', 'RexelSyncSalesAgreementHelp', false);
print rexelsyncInputRow('RexelSyncDelayMs', 'REXELSYNC_DELAY_MS', (string) (getDolGlobalInt('REXELSYNC_DELAY_MS') ?: 0), 'number', 'RexelSyncDelayMsHelp', false, ' min="0" step="100"');
print rexelsyncInputRow('RexelSyncDefaultQty', 'REXELSYNC_DEFAULT_QTY', (string) (getDolGlobalInt('REXELSYNC_DEFAULT_QTY') ?: 1), 'number', 'RexelSyncDefaultQtyHelp', false, ' min="1" step="1"');
print '</table>';
print '</details>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';

print '<br>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="create_supplier">';
print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('RexelSyncCreateSupplierButton').'">';
print '</div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();

/**
 * Create or fetch REXEL supplier.
 *
 * @param DoliDB $db Database handler
 * @param User   $user User
 * @return int
 */
function rexelsyncCreateOrFetchSupplier($db, $user)
{
	global $conf;

	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe";
	$sql .= " WHERE entity IN (".getEntity('societe').")";
	$sql .= " AND UPPER(nom) = 'REXEL'";
	$sql .= " ORDER BY fournisseur DESC, status DESC, rowid ASC";
	$sql .= $db->plimit(1);
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$db->query("UPDATE ".MAIN_DB_PREFIX."societe SET fournisseur = 1, status = 1 WHERE rowid = ".((int) $obj->rowid));
		return (int) $obj->rowid;
	}

	$soc = new Societe($db);
	$soc->name = 'REXEL';
	$soc->nom = 'REXEL';
	$soc->client = 0;
	$soc->fournisseur = 1;
	$soc->status = 1;
	$soc->code_fournisseur = 'REXEL';
	$soc->entity = $conf->entity;

	$result = $soc->create($user);
	return $result > 0 ? (int) $result : -1;
}

/**
 * Return suppliers for select.
 *
 * @param DoliDB $db Database handler
 * @return array<int,string>
 */
function rexelsyncGetSupplierOptions($db)
{
	$options = array();
	$sql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe";
	$sql .= " WHERE fournisseur = 1 AND status = 1 AND entity IN (".getEntity('societe').")";
	$sql .= " ORDER BY nom ASC";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$options[(int) $obj->rowid] = $obj->nom;
		}
	}

	return $options;
}

/**
 * Render a translated label with a tooltip helper.
 *
 * @param string $labelKey Label translation key
 * @param string $helpKey Help translation key
 * @param bool   $required Required
 * @return string
 */
function rexelsyncLabelWithTooltip($labelKey, $helpKey, $required = false)
{
	global $langs;

	$label = $langs->trans($labelKey);
	$help = $langs->trans($helpKey);
	$html = $label;
	if ($required) {
		$html .= '<span class="fieldrequired"> *</span>';
	}
	if (function_exists('img_help')) {
		$html .= ' '.img_help(1, $help);
	} else {
		$html .= ' <span class="classfortooltip opacitymedium" title="'.dol_escape_htmltag($help).'">?</span>';
	}

	return $html;
}

/**
 * Render a select row.
 *
 * @param string            $labelKey Label translation key
 * @param string            $name Field name
 * @param array<string,string> $options Select options
 * @param string            $value Selected value
 * @param string            $helpKey Help translation key
 * @param bool              $required Required
 * @param Form              $form HTML form helper
 * @return string
 */
function rexelsyncSelectRow($labelKey, $name, array $options, $value, $helpKey, $required, $form)
{
	global $langs;

	$html = '<tr class="oddeven">';
	$html .= '<td>'.rexelsyncLabelWithTooltip($labelKey, $helpKey, $required).'</td>';
	$html .= '<td>'.$form->selectarray($name, $options, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td>';
	$html .= '<td class="opacitymedium">'.$langs->trans($helpKey).'</td>';
	$html .= '</tr>';

	return $html;
}

/**
 * Render an input row.
 *
 * @param string $labelKey Label translation key
 * @param string $name Field name
 * @param string $value Field value
 * @param string $type Input type
 * @param string $helpKey Help translation key
 * @param bool   $required Required
 * @param string $extra Extra attributes
 * @return string
 */
function rexelsyncInputRow($labelKey, $name, $value, $type, $helpKey, $required, $extra = '')
{
	global $langs;

	$html = '<tr class="oddeven">';
	$html .= '<td>'.rexelsyncLabelWithTooltip($labelKey, $helpKey, $required).'</td>';
	$html .= '<td><input type="'.$type.'" name="'.$name.'" class="minwidth300" value="'.dol_escape_htmltag($value).'"'.$extra.'></td>';
	$html .= '<td class="opacitymedium">'.$langs->trans($helpKey).'</td>';
	$html .= '</tr>';

	return $html;
}

/**
 * Render a secret row.
 *
 * @param string $labelKey Label translation key
 * @param string $name Field name
 * @param bool   $hasValue Existing secret
 * @param string $helpKey Help translation key
 * @param bool   $required Required
 * @return string
 */
function rexelsyncSecretRow($labelKey, $name, $hasValue, $helpKey, $required = false)
{
	global $langs;

	$placeholder = $hasValue ? $langs->trans('RexelSyncSecretAlreadySaved') : '';
	$html = '<tr class="oddeven">';
	$html .= '<td>'.rexelsyncLabelWithTooltip($labelKey, $helpKey, $required).'</td>';
	$html .= '<td><input type="password" name="'.$name.'" class="minwidth300" value="" autocomplete="new-password" placeholder="'.dol_escape_htmltag($placeholder).'"></td>';
	$html .= '<td class="opacitymedium">'.$langs->trans($helpKey).'</td>';
	$html .= '</tr>';

	return $html;
}
