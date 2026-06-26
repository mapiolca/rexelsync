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
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/lib/rexelsync.lib.php';
require_once __DIR__.'/class/rexelsync.class.php';

$langs->loadLangs(array('products', 'suppliers', 'rexelsync@rexelsync'));

if (!$user->hasRight('rexelsync', 'sync', 'read')) {
	accessforbidden();
}

$form = new Form($db);

$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'l.datec';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'DESC';
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone', 'int') - 1) : GETPOST('page', 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$limit = GETPOST('limit', 'int') ?: $conf->liste_limit;
$offset = $limit * $page;

$searchRefProduct = trim(GETPOST('search_ref_product', 'alphanohtml'));
$searchRefFourn = trim(GETPOST('search_ref_fourn', 'alphanohtml'));
$searchStatus = GETPOST('search_status', 'alpha');
$dateStart = trim(GETPOST('date_start', 'restricthtml'));
$dateEnd = trim(GETPOST('date_end', 'restricthtml'));
if (GETPOST('button_removefilter', 'alpha')) {
	$searchRefProduct = '';
	$searchRefFourn = '';
	$searchStatus = '';
	$dateStart = '';
	$dateEnd = '';
}

$allowedSortFields = array(
	'l.datec' => 'l.datec',
	'l.ref_product' => 'l.ref_product',
	'l.ref_fourn' => 'l.ref_fourn',
	'l.old_price' => 'l.old_price',
	'l.new_price' => 'l.new_price',
	'l.old_stock' => 'l.old_stock',
	'l.new_stock' => 'l.new_stock',
	'l.status' => 'l.status',
);
if (empty($allowedSortFields[$sortfield])) {
	$sortfield = 'l.datec';
}
$sortorder = strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC';

$sqlWhere = " WHERE l.entity IN (".getEntity('product').")";
if ($searchRefProduct !== '') {
	$sqlWhere .= " AND l.ref_product LIKE '%".$db->escape($searchRefProduct)."%'";
}
if ($searchRefFourn !== '') {
	$sqlWhere .= " AND l.ref_fourn LIKE '%".$db->escape($searchRefFourn)."%'";
}
if ($searchStatus !== '') {
	$sqlWhere .= " AND l.status = '".$db->escape($searchStatus)."'";
}
if ($dateStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
	$sqlWhere .= " AND l.datec >= '".$db->escape($dateStart)." 00:00:00'";
}
if ($dateEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
	$sqlWhere .= " AND l.datec <= '".$db->escape($dateEnd)." 23:59:59'";
}

$sqlCount = "SELECT COUNT(l.rowid) AS nb FROM ".MAIN_DB_PREFIX."rexelsync_log AS l".$sqlWhere;
$resCount = $db->query($sqlCount);
$totalRows = 0;
if ($resCount && ($obj = $db->fetch_object($resCount))) {
	$totalRows = (int) $obj->nb;
}

$sql = "SELECT l.* FROM ".MAIN_DB_PREFIX."rexelsync_log AS l";
$sql .= $sqlWhere;
$sql .= " ORDER BY ".$allowedSortFields[$sortfield]." ".$sortorder;
$sql .= $db->plimit($limit, $offset);
$resql = $db->query($sql);

$param = '';
foreach (array(
	'search_ref_product' => $searchRefProduct,
	'search_ref_fourn' => $searchRefFourn,
	'search_status' => $searchStatus,
	'date_start' => $dateStart,
	'date_end' => $dateEnd,
) as $key => $value) {
	if ($value !== '') {
		$param .= '&'.$key.'='.urlencode($value);
	}
}

$statusOptions = array(
	RexelSync::STATUS_UPDATED => $langs->trans('RexelSyncStatusUpdated'),
	RexelSync::STATUS_STOCK_UPDATED => $langs->trans('RexelSyncStatusStockUpdated'),
	RexelSync::STATUS_UNCHANGED => $langs->trans('RexelSyncStatusUnchanged'),
	RexelSync::STATUS_NOT_FOUND => $langs->trans('RexelSyncStatusNotFound'),
	RexelSync::STATUS_INVALID_REF => $langs->trans('RexelSyncStatusInvalidRef'),
	RexelSync::STATUS_ERROR => $langs->trans('RexelSyncStatusError'),
);

$title = $langs->trans('RexelSyncLogs');
llxHeader('', $title);
print '<div class="rexelsync-page">';

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $totalRows, $totalRows, 'rexelsync@rexelsync', 0, '', '', $limit, 0, 0, 1);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre center">'.$form->showFilterButtons('left').'</td>';
print '<td class="liste_titre"><input type="date" class="flat maxwidth100" name="date_start" value="'.dol_escape_htmltag($dateStart).'"><br><input type="date" class="flat maxwidth100" name="date_end" value="'.dol_escape_htmltag($dateEnd).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref_product" value="'.dol_escape_htmltag($searchRefProduct).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref_fourn" value="'.dol_escape_htmltag($searchRefFourn).'"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre">'.$form->selectarray('search_status', $statusOptions, $searchStatus, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre"></td>';
print '</tr>';

print '<tr class="liste_titre">';
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans('Date'), $_SERVER['PHP_SELF'], 'l.datec', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 'l.ref_product', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncSupplierRef'), $_SERVER['PHP_SELF'], 'l.ref_fourn', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncPriceEvolution'), $_SERVER['PHP_SELF'], 'l.new_price', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncStockEvolution'), $_SERVER['PHP_SELF'], 'l.new_stock', '', $param, 'class="right"', $sortfield, $sortorder);
print '<td class="center">'.$langs->trans('RexelSyncHttpStatus').'</td>';
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 'l.status', '', $param, 'class="center"', $sortfield, $sortorder);
print '<td>'.$langs->trans('Message').'</td>';
print '</tr>';

if (!$resql || $db->num_rows($resql) === 0) {
	print '<tr class="oddeven"><td colspan="9" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}

if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$priceDiff = ($obj->old_price !== null && $obj->new_price !== null) ? ((float) $obj->new_price - (float) $obj->old_price) : null;
		$stockDiff = ($obj->old_stock !== null && $obj->new_stock !== null) ? ((float) $obj->new_stock - (float) $obj->old_stock) : null;

		print '<tr class="oddeven">';
		print '<td class="center">'.((int) $obj->rowid).'</td>';
		print '<td>'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';
		print '<td>';
		if (!empty($obj->fk_product)) {
			print '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.((int) $obj->fk_product).'">'.dol_escape_htmltag($obj->ref_product).'</a>';
		} else {
			print dol_escape_htmltag($obj->ref_product);
		}
		print '</td>';
		print '<td>'.dol_escape_htmltag($obj->ref_fourn).'</td>';
		print '<td class="right">'.rexelsyncFormatEvolution($obj->old_price, $obj->new_price, $priceDiff, true).'</td>';
		print '<td class="right">'.rexelsyncFormatEvolution($obj->old_stock, $obj->new_stock, $stockDiff, false).'</td>';
		print '<td class="center">'.($obj->http_status !== null ? (int) $obj->http_status : '').'</td>';
		print '<td class="center">'.rexelsyncStatusBadge($obj->status).'</td>';
		print '<td><span class="small">'.dol_escape_htmltag(dol_trunc(rexelsyncTranslateMessage($obj->message), 160)).'</span></td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';
print '</form>';

print '</div>';

llxFooter();
$db->close();

/**
 * Format old/new value evolution.
 *
 * @param mixed    $oldValue Old value
 * @param mixed    $newValue New value
 * @param float|null $diff Diff
 * @param bool     $isPrice Is price
 * @return string
 */
function rexelsyncFormatEvolution($oldValue, $newValue, $diff, $isPrice)
{
	if ($oldValue === null && $newValue === null) {
		return '-';
	}

	$old = $oldValue !== null ? (float) $oldValue : null;
	$new = $newValue !== null ? (float) $newValue : null;
	$html = '';
	$html .= $old !== null ? ($isPrice ? price($old) : price($old, 0, '', 0, 0)) : '-';
	$html .= ' -> ';
	$html .= $new !== null ? ($isPrice ? price($new) : price($new, 0, '', 0, 0)) : '-';
	if ($diff !== null && abs($diff) > 0.00001) {
		$style = $diff > 0 ? 'color: var(--colortextdanger);' : 'color: var(--colortextsuccess);';
		$sign = $diff > 0 ? '+' : '-';
		$html .= '<br><span style="'.$style.'">'.$sign.($isPrice ? price(abs($diff)) : price(abs($diff), 0, '', 0, 0)).'</span>';
	}

	return $html;
}
