<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Rexel customer cache administration page.
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
require_once __DIR__.'/../lib/rexelsync.lib.php';
require_once __DIR__.'/../class/rexelsync.class.php';
require_once __DIR__.'/../class/rexelsynccustomer.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'companies', 'rexelsync@rexelsync'));

if (empty($user->admin) && !$user->hasRight('rexelsync', 'config', 'write')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
if ($action === 'sync_customer' && function_exists('checkToken')) {
	checkToken();
}

$sync = new RexelSync($db);
$customerSync = new RexelSyncCustomer($db);
$config = $sync->getConfig();
$missing = $sync->getMissingConfiguration($config);

if ($action === 'sync_customer') {
	if (!empty($missing)) {
		setEventMessages($langs->trans('RexelSyncMissingConfiguration', implode(', ', $missing)), null, 'errors');
	} else {
		$result = $customerSync->syncCustomer($config, $user);
		if (!empty($result['success'])) {
			setEventMessages($langs->trans('RexelSyncCustomerSyncSuccess', (int) $result['addresses'], (int) $result['agreements']), null, 'mesgs');
		} else {
			$message = !empty($result['message']) ? rexelsyncTranslateMessage((string) $result['message']) : $langs->trans('Error');
			setEventMessages($message, null, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$profile = $customerSync->fetchCustomerProfile();
$addresses = $customerSync->fetchCustomerAddresses(500);
$agreements = $customerSync->fetchCustomerAgreements(500);

$title = $langs->trans('RexelSyncCustomers');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('rexelsync').'">'.$langs->trans('BackToModuleList').'</a>';

llxHeader('', $title);

print load_fiche_titre($title, $linkback, 'rexelsync@rexelsync');

$head = rexelsyncAdminPrepareHead();
print dol_get_fiche_head($head, 'customers', $langs->trans('RexelSyncSetup'), -1, 'rexelsync@rexelsync');

print '<div class="underbanner opacitymedium">'.$langs->trans('RexelSyncCustomerSyncHelp').'</div>';
print '<br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('RexelSyncCustomerSync').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('RexelSyncCustomerId').'</td><td>'.dol_escape_htmltag((string) $config['id_customer']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncCustomerLastSync').'</td><td>'.rexelsyncCustomerLastSyncDate().'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncCustomerLastSyncStatus').'</td><td>'.rexelsyncCustomerStatusBadge(getDolGlobalString('REXELSYNC_CUSTOMER_LAST_SYNC_STATUS')).'</td></tr>';
print '</table>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="sync_customer">';
print '<div class="center">';
if (!empty($missing)) {
	print '<input type="submit" class="button" disabled value="'.$langs->trans('RexelSyncCustomerSync').'">';
	print '<br><span class="opacitymedium">'.$langs->trans('RexelSyncMissingConfiguration', dol_escape_htmltag(implode(', ', $missing))).'</span>';
} else {
	print '<input type="submit" class="button" value="'.$langs->trans('RexelSyncCustomerSync').'">';
}
print '</div>';
print '</form>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('RexelSyncCustomerProfile').'</th></tr>';
if (empty($profile)) {
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('RexelSyncCustomerNoProfile').'</span></td></tr>';
} else {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('CompanyName').'</td><td>'.rexelsyncCustomerValue($profile, 'customer_name').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncCustomerId').'</td><td>'.rexelsyncCustomerValue($profile, 'customer_id').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncScope').'</td><td>'.rexelsyncCustomerValue($profile, 'scope').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncAgenceCode').'</td><td>'.rexelsyncCustomerValue($profile, 'branch_code').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Siren').'</td><td>'.rexelsyncCustomerValue($profile, 'siren').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncCommercialContact').'</td><td>'.rexelsyncCustomerContact($profile).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncBillingAddress').'</td><td>'.rexelsyncCustomerAddressBlock($profile).'</td></tr>';
}
print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('RexelSyncAddressIndex').'</th>';
print '<th>'.$langs->trans('RexelSyncAddressType').'</th>';
print '<th>'.$langs->trans('RexelSyncAddressName').'</th>';
print '<th>'.$langs->trans('Address').'</th>';
print '<th>'.$langs->trans('Zip').'</th>';
print '<th>'.$langs->trans('Town').'</th>';
print '<th>'.$langs->trans('Country').'</th>';
print '<th>'.$langs->trans('RexelSyncAddressOrigin').'</th>';
print '</tr>';
if (empty($addresses)) {
	rexelsyncCustomerEmptyRow(8);
} else {
	foreach ($addresses as $address) {
		print '<tr class="oddeven">';
		print '<td>'.rexelsyncCustomerValue($address, 'address_index').'</td>';
		print '<td>'.rexelsyncCustomerValue($address, 'address_type').'</td>';
		print '<td>'.rexelsyncCustomerValue($address, 'name').'</td>';
		print '<td>'.rexelsyncCustomerAddressBlock($address).'</td>';
		print '<td>'.rexelsyncCustomerValue($address, 'zip').'</td>';
		print '<td>'.rexelsyncCustomerValue($address, 'town').'</td>';
		print '<td>'.rexelsyncCustomerCountry($address).'</td>';
		print '<td>'.rexelsyncCustomerValue($address, 'origin').'</td>';
		print '</tr>';
	}
}
print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('RexelSyncAgreementNumber').'</th>';
print '<th>'.$langs->trans('RexelSyncAgreementLabel').'</th>';
print '<th>'.$langs->trans('RexelSyncAgreementSupplierCode').'</th>';
print '<th>'.$langs->trans('RexelSyncAgreementDerogationNumber').'</th>';
print '<th>'.$langs->trans('DateStart').'</th>';
print '<th>'.$langs->trans('DateEnd').'</th>';
print '<th class="right">'.$langs->trans('RexelSyncAgreementAmount').'</th>';
print '<th class="right">'.$langs->trans('RexelSyncAgreementAvailableAmount').'</th>';
print '<th>'.$langs->trans('RexelSyncAgreementOrigin').'</th>';
print '</tr>';
if (empty($agreements)) {
	rexelsyncCustomerEmptyRow(9);
} else {
	foreach ($agreements as $agreement) {
		print '<tr class="oddeven">';
		print '<td>'.rexelsyncCustomerValue($agreement, 'sales_agreement_number').'</td>';
		print '<td>'.rexelsyncCustomerValue($agreement, 'sales_agreement_label').'</td>';
		print '<td>'.rexelsyncCustomerValue($agreement, 'supplier_code').'</td>';
		print '<td>'.rexelsyncCustomerValue($agreement, 'derogation_number').'</td>';
		print '<td>'.rexelsyncCustomerSqlDate($agreement, 'application_start_date').'</td>';
		print '<td>'.rexelsyncCustomerSqlDate($agreement, 'application_end_date').'</td>';
		print '<td class="right">'.rexelsyncCustomerAmount($agreement, 'amount_agreement').'</td>';
		print '<td class="right">'.rexelsyncCustomerAmount($agreement, 'available_amount').'</td>';
		print '<td>'.rexelsyncCustomerValue($agreement, 'agreement_origin').'</td>';
		print '</tr>';
	}
}
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();

/**
 * Return an escaped value from a row.
 *
 * @param array<string,mixed> $row Row
 * @param string $key Key
 * @return string
 */
function rexelsyncCustomerValue(array $row, $key)
{
	$value = isset($row[$key]) ? trim((string) $row[$key]) : '';
	return $value === '' ? '<span class="opacitymedium">-</span>' : dol_escape_htmltag($value);
}

/**
 * Return formatted last sync date.
 *
 * @return string
 */
function rexelsyncCustomerLastSyncDate()
{
	$timestamp = getDolGlobalString('REXELSYNC_CUSTOMER_LAST_SYNC_DATE');
	if ($timestamp === '' || !is_numeric($timestamp)) {
		return '<span class="opacitymedium">-</span>';
	}

	return dol_print_date((int) $timestamp, 'dayhour');
}

/**
 * Return a customer sync status badge.
 *
 * @param string $status Status code
 * @return string
 */
function rexelsyncCustomerStatusBadge($status)
{
	global $langs;

	$status = (string) $status;
	if ($status === RexelSyncCustomer::STATUS_OK) {
		return '<span class="badge badge-status4">'.$langs->trans('RexelSyncCustomerStatusOk').'</span>';
	}
	if ($status === RexelSyncCustomer::STATUS_ERROR) {
		return '<span class="badge badge-status8">'.$langs->trans('RexelSyncCustomerStatusError').'</span>';
	}

	return '<span class="badge badge-status0">'.$langs->trans('RexelSyncStatusNeverSynced').'</span>';
}

/**
 * Render contact data.
 *
 * @param array<string,mixed> $row Row
 * @return string
 */
function rexelsyncCustomerContact(array $row)
{
	$parts = array();
	foreach (array('commercial_contact_fullname', 'commercial_contact_phone', 'commercial_contact_email') as $key) {
		if (!empty($row[$key])) {
			$parts[] = dol_escape_htmltag((string) $row[$key]);
		}
	}

	return empty($parts) ? '<span class="opacitymedium">-</span>' : implode('<br>', $parts);
}

/**
 * Render address fields.
 *
 * @param array<string,mixed> $row Row
 * @return string
 */
function rexelsyncCustomerAddressBlock(array $row)
{
	$parts = array();
	foreach (array('address', 'address2', 'address3') as $key) {
		if (!empty($row[$key])) {
			$parts[] = dol_escape_htmltag((string) $row[$key]);
		}
	}

	return empty($parts) ? '<span class="opacitymedium">-</span>' : implode('<br>', $parts);
}

/**
 * Render country fields.
 *
 * @param array<string,mixed> $row Row
 * @return string
 */
function rexelsyncCustomerCountry(array $row)
{
	$countryName = isset($row['country_name']) ? trim((string) $row['country_name']) : '';
	$countryCode = isset($row['country_code']) ? trim((string) $row['country_code']) : '';
	if ($countryName === '' && $countryCode === '') {
		return '<span class="opacitymedium">-</span>';
	}
	if ($countryName !== '' && $countryCode !== '') {
		return dol_escape_htmltag($countryName.' ('.$countryCode.')');
	}

	return dol_escape_htmltag($countryName !== '' ? $countryName : $countryCode);
}

/**
 * Render a SQL date value.
 *
 * @param array<string,mixed> $row Row
 * @param string $key Key
 * @return string
 */
function rexelsyncCustomerSqlDate(array $row, $key)
{
	global $db;

	$value = isset($row[$key]) ? trim((string) $row[$key]) : '';
	if ($value === '') {
		return '<span class="opacitymedium">-</span>';
	}

	return dol_print_date($db->jdate($value), 'day');
}

/**
 * Render a numeric amount.
 *
 * @param array<string,mixed> $row Row
 * @param string $key Key
 * @return string
 */
function rexelsyncCustomerAmount(array $row, $key)
{
	$value = isset($row[$key]) ? (string) $row[$key] : '';
	if ($value === '' || !is_numeric($value)) {
		return '<span class="opacitymedium">-</span>';
	}

	return price((float) $value);
}

/**
 * Render a native empty table row.
 *
 * @param int $colspan Colspan
 * @return void
 */
function rexelsyncCustomerEmptyRow($colspan)
{
	global $langs;

	print '<tr class="oddeven"><td colspan="'.((int) $colspan).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}