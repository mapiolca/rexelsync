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

$action = GETPOST('action', 'aZ09');
if (($action === 'syncone' || $action === 'syncall') && function_exists('checkToken')) {
	checkToken();
}

$form = new Form($db);
$sync = new RexelSync($db);
$config = $sync->getConfig();
$missing = $sync->getMissingConfiguration($config);

if ($action === 'syncone') {
	if (!$user->hasRight('rexelsync', 'sync', 'write')) {
		accessforbidden();
	}
	$result = $sync->syncOneSupplierPriceLine(GETPOST('lineid', 'int'));
	if (!empty($result['success'])) {
		setEventMessages($langs->trans('RexelSyncOneSuccess'), null, 'mesgs');
	} else {
		setEventMessages(!empty($result['message']) ? $result['message'] : $sync->error, null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'syncall') {
	if (!$user->hasRight('rexelsync', 'sync', 'write')) {
		accessforbidden();
	}
	$stats = $sync->runSync(0, 0);
	setEventMessages($stats['message'], null, !empty($stats['fatal']) ? 'errors' : 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'p.ref';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'ASC';
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone', 'int') - 1) : GETPOST('page', 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$limit = GETPOST('limit', 'int') ?: $conf->liste_limit;
$offset = $limit * $page;

$searchRefProduct = trim(GETPOST('search_ref_product', 'alphanohtml'));
$searchLabelProduct = trim(GETPOST('search_label_product', 'alphanohtml'));
$searchRefFourn = trim(GETPOST('search_ref_fourn', 'alphanohtml'));
if (GETPOST('button_removefilter', 'alpha')) {
	$searchRefProduct = '';
	$searchLabelProduct = '';
	$searchRefFourn = '';
}

$filters = array(
	'search_ref_product' => $searchRefProduct,
	'search_label_product' => $searchLabelProduct,
	'search_ref_fourn' => $searchRefFourn,
);

$totalRows = $sync->countSupplierPriceRows($filters);
$rows = $sync->getSupplierPriceRows($limit, $offset, $filters, $sortfield, $sortorder);
$priceLineIds = array();
foreach ($rows as $row) {
	$priceLineIds[] = (int) $row['price_line_id'];
}
$latestLogs = $sync->getLatestLogsByPriceLine($priceLineIds);

$param = '';
foreach (array(
	'search_ref_product' => $searchRefProduct,
	'search_label_product' => $searchLabelProduct,
	'search_ref_fourn' => $searchRefFourn,
) as $key => $value) {
	if ($value !== '') {
		$param .= '&'.$key.'='.urlencode($value);
	}
}

$title = $langs->trans('RexelSyncSync');
llxHeader('', $title);
print load_fiche_titre($title, '', 'fa-sync');

$head = rexelsyncPrepareHead('sync');
print dol_get_fiche_head($head, 'sync', $langs->trans('RexelSync'), -1, 'fa-sync');

if (!empty($missing)) {
	print '<div class="warning">';
	print img_warning().' '.$langs->trans('RexelSyncMissingConfiguration', dol_escape_htmltag(implode(', ', $missing))).' ';
	print '<a href="'.dol_buildpath('/rexelsync/admin/setup.php', 1).'">'.$langs->trans('RexelSyncOpenSetup').'</a>';
	print '</div><br>';
}

print '<div class="tabsAction">';
if ($user->hasRight('rexelsync', 'sync', 'write') && empty($missing)) {
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=syncall&token='.newToken().'">'.$langs->trans('RexelSyncRunAll').'</a>';
} else {
	print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('RexelSyncRunAllDisabled').'">'.$langs->trans('RexelSyncRunAll').'</span>';
}
print '</div>';

print '<div class="opacitymedium">';
print $langs->trans('RexelSyncRowsFound', $totalRows);
if (!empty($config['supplier_id'])) {
	print ' - '.$langs->trans('RexelSyncSupplierId', (int) $config['supplier_id']);
}
print '</div><br>';

print_barre_liste('', $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $totalRows, $totalRows, 'fa-sync', 0, '', '', $limit);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre center">'.$form->showFilterButtons('left').'</td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref_product" value="'.dol_escape_htmltag($searchRefProduct).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_label_product" value="'.dol_escape_htmltag($searchLabelProduct).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref_fourn" value="'.dol_escape_htmltag($searchRefFourn).'"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '</tr>';

print '<tr class="liste_titre">';
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Label'), $_SERVER['PHP_SELF'], 'p.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncSupplierRef'), $_SERVER['PHP_SELF'], 'pfp.ref_fourn', '', $param, '', $sortfield, $sortorder);
print '<td>'.$langs->trans('RexelSyncParsedRef').'</td>';
print_liste_field_titre($langs->trans('RexelSyncCurrentPrice'), $_SERVER['PHP_SELF'], 'pfp.unitprice', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncSupplierStock'), $_SERVER['PHP_SELF'], 'ef.supplier_stock', '', $param, 'class="right"', $sortfield, $sortorder);
print '<td class="center">'.$langs->trans('RexelSyncLastSync').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';

if (empty($rows)) {
	print '<tr class="oddeven"><td colspan="10" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}

foreach ($rows as $row) {
	$log = !empty($latestLogs[$row['price_line_id']]) ? $latestLogs[$row['price_line_id']] : null;
	print '<tr class="oddeven">';
	print '<td class="center">'.((int) $row['price_line_id']).'</td>';
	print '<td><a href="'.DOL_URL_ROOT.'/product/card.php?id='.((int) $row['fk_product']).'">'.dol_escape_htmltag($row['ref_product']).'</a></td>';
	print '<td>'.dol_escape_htmltag($row['label_product']).'</td>';
	print '<td>'.dol_escape_htmltag($row['ref_fourn']).'</td>';
	print '<td>';
	if (!empty($row['valid_ref'])) {
		print dol_escape_htmltag($row['supplier_code']).' / '.dol_escape_htmltag($row['supplier_com_ref']);
	} else {
		print '<span class="warning">'.$langs->trans('RexelSyncInvalidRefShort').'</span>';
	}
	print '</td>';
	print '<td class="right">'.($row['unitprice'] !== null ? price($row['unitprice']) : '').'</td>';
	print '<td class="right">'.($row['supplier_stock'] !== null ? price($row['supplier_stock'], 0, '', 0, 0) : '-').'</td>';
	print '<td class="center">';
	if ($log && !empty($log['datec'])) {
		print dol_print_date($db->jdate($log['datec']), 'dayhour');
	}
	print '</td>';
	print '<td class="center">';
	if ($log) {
		print rexelsyncStatusBadge($log['status']);
		if (!empty($log['message'])) {
			print '<br><span class="small opacitymedium">'.dol_escape_htmltag(dol_trunc($log['message'], 80)).'</span>';
		}
	}
	print '</td>';
	print '<td class="center">';
	if ($user->hasRight('rexelsync', 'sync', 'write') && empty($missing)) {
		print '<a class="reposition butActionSmall" href="'.$_SERVER['PHP_SELF'].'?action=syncone&lineid='.((int) $row['price_line_id']).'&token='.newToken().'">'.$langs->trans('RexelSyncRunOne').'</a>';
	}
	print '</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
