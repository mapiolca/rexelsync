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
if (($action === 'syncone' || $action === 'syncall' || ($action === 'syncbatch' && $_SERVER['REQUEST_METHOD'] === 'POST')) && function_exists('checkToken')) {
	checkToken();
}

$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'p.ref';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'ASC';
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone', 'int') - 1) : GETPOST('page', 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$limit = GETPOST('limit', 'int') ?: $conf->liste_limit;
$limitWasSet = GETPOSTISSET('limit');
$offset = $limit * $page;
$removeFilter = (bool) GETPOST('button_removefilter', 'alpha');

$statusOptions = rexelsyncStatusOptions($langs);
$searchPriceLineId = GETPOST('search_price_line_id', 'int') > 0 ? (string) GETPOST('search_price_line_id', 'int') : '';
$searchRefProduct = trim(GETPOST('search_ref_product', 'alphanohtml'));
$searchLabelProduct = trim(GETPOST('search_label_product', 'alphanohtml'));
$searchRefFourn = trim(GETPOST('search_ref_fourn', 'alphanohtml'));
$searchParsedRef = trim(GETPOST('search_parsed_ref', 'alphanohtml'));
$searchPriceMin = trim(GETPOST('search_price_min', 'restricthtml'));
$searchPriceMax = trim(GETPOST('search_price_max', 'restricthtml'));
$searchStockMin = trim(GETPOST('search_stock_min', 'restricthtml'));
$searchStockMax = trim(GETPOST('search_stock_max', 'restricthtml'));
$searchStatuses = rexelsyncGetStatusFilter(array_keys($statusOptions));
$searchLastSyncStart = rexelsyncGetDateFilterValues('search_last_sync_start', false);
$searchLastSyncEnd = rexelsyncGetDateFilterValues('search_last_sync_end', true);
if ($removeFilter) {
	$searchPriceLineId = '';
	$searchRefProduct = '';
	$searchLabelProduct = '';
	$searchRefFourn = '';
	$searchParsedRef = '';
	$searchPriceMin = '';
	$searchPriceMax = '';
	$searchStockMin = '';
	$searchStockMax = '';
	$searchStatuses = array();
	$searchLastSyncStart = rexelsyncEmptyDateFilterValues();
	$searchLastSyncEnd = rexelsyncEmptyDateFilterValues();
}

$filters = array(
	'search_price_line_id' => $searchPriceLineId,
	'price_line_id' => $searchPriceLineId,
	'search_ref_product' => $searchRefProduct,
	'search_label_product' => $searchLabelProduct,
	'search_ref_fourn' => $searchRefFourn,
	'search_parsed_ref' => $searchParsedRef,
	'search_price_min' => $searchPriceMin,
	'search_price_max' => $searchPriceMax,
	'search_stock_min' => $searchStockMin,
	'search_stock_max' => $searchStockMax,
	'search_status' => $searchStatuses,
	'search_last_sync_start' => !empty($searchLastSyncStart['timestamp']) ? $db->idate((int) $searchLastSyncStart['timestamp']) : '',
	'search_last_sync_end' => !empty($searchLastSyncEnd['timestamp']) ? $db->idate((int) $searchLastSyncEnd['timestamp']) : '',
	'search_last_sync_startday' => (string) $searchLastSyncStart['day'],
	'search_last_sync_startmonth' => (string) $searchLastSyncStart['month'],
	'search_last_sync_startyear' => (string) $searchLastSyncStart['year'],
	'search_last_sync_endday' => (string) $searchLastSyncEnd['day'],
	'search_last_sync_endmonth' => (string) $searchLastSyncEnd['month'],
	'search_last_sync_endyear' => (string) $searchLastSyncEnd['year'],
);
$listContextQuery = rexelsyncBuildListContextQuery($sortfield, $sortorder, $page, $limit, $limitWasSet, $filters);
$listContextUrl = $_SERVER['PHP_SELF'].($listContextQuery !== '' ? '?'.$listContextQuery : '');

$form = new Form($db);
$sync = new RexelSync($db);
$config = $sync->getConfig();
$missing = $sync->getMissingConfiguration($config);

if ($action === 'syncbatch') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		rexelsyncJsonResponse(array(
			'success' => false,
			'fatal' => true,
			'token' => function_exists('newToken') ? newToken() : '',
			'total' => 0,
			'offset' => 0,
			'limit' => RexelSync::normalizeBatchSize((int) $config['batch_size']),
			'processed' => 0,
			'next_offset' => 0,
			'done' => true,
			'stats' => rexelsyncEmptyJsonStats(),
			'message' => $langs->trans('RexelSyncBatchAjaxError'),
		), 405);
	}
	if (!$user->hasRight('rexelsync', 'sync', 'write')) {
		rexelsyncJsonResponse(array(
			'success' => false,
			'fatal' => true,
			'token' => function_exists('newToken') ? newToken() : '',
			'total' => 0,
			'offset' => 0,
			'limit' => RexelSync::normalizeBatchSize((int) $config['batch_size']),
			'processed' => 0,
			'next_offset' => 0,
			'done' => true,
			'stats' => rexelsyncEmptyJsonStats(),
			'message' => $langs->trans('RexelSyncRunAllDisabled'),
		), 403);
	}
	if (!empty($missing)) {
		rexelsyncJsonResponse(array(
			'success' => false,
			'fatal' => true,
			'token' => function_exists('newToken') ? newToken() : '',
			'total' => 0,
			'offset' => max(0, GETPOST('offset', 'int')),
			'limit' => RexelSync::normalizeBatchSize((int) $config['batch_size']),
			'processed' => 0,
			'next_offset' => max(0, GETPOST('offset', 'int')),
			'done' => true,
			'stats' => rexelsyncEmptyJsonStats(),
			'message' => $langs->trans('RexelSyncMissingConfiguration', implode(', ', $missing)),
		), 200);
	}

	$batchLimit = RexelSync::normalizeBatchSize((int) $config['batch_size']);
	$batchOffset = max(0, GETPOST('offset', 'int'));
	$totalBatchRows = $sync->countSupplierPriceRows(array());
	if ($batchOffset >= $totalBatchRows) {
		rexelsyncJsonResponse(array(
			'success' => true,
			'fatal' => false,
			'token' => function_exists('newToken') ? newToken() : '',
			'total' => $totalBatchRows,
			'offset' => $batchOffset,
			'limit' => $batchLimit,
			'processed' => 0,
			'next_offset' => $totalBatchRows,
			'done' => true,
			'stats' => rexelsyncEmptyJsonStats(),
			'message' => $langs->trans('RexelSyncBatchNoRows'),
		));
	}

	$stats = $sync->runSyncBatch($batchLimit, $batchOffset);
	$processed = (int) $stats['total'];
	$nextOffset = $processed > 0 ? min($totalBatchRows, $batchOffset + $processed) : $totalBatchRows;
	$done = !empty($stats['fatal']) || $processed <= 0 || $nextOffset >= $totalBatchRows;

	rexelsyncJsonResponse(array(
		'success' => empty($stats['fatal']),
		'fatal' => !empty($stats['fatal']),
		'token' => function_exists('newToken') ? newToken() : '',
		'total' => $totalBatchRows,
		'offset' => $batchOffset,
		'limit' => $batchLimit,
		'processed' => $processed,
		'next_offset' => $nextOffset,
		'done' => $done,
		'stats' => rexelsyncStatsForJson($stats),
		'message' => (string) $stats['message'],
	));
}

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
	header('Location: '.$listContextUrl);
	exit;
}

if ($action === 'syncall') {
	if (!$user->hasRight('rexelsync', 'sync', 'write')) {
		accessforbidden();
	}
	setEventMessages($langs->trans('RexelSyncBatchLegacyDisabled'), null, 'mesgs');
	header('Location: '.$listContextUrl);
	exit;
}

$totalRows = $sync->countSupplierPriceRows($filters);
$totalBatchRows = $sync->countSupplierPriceRows(array());
$rows = $sync->getSupplierPriceRows($limit, $offset, $filters, $sortfield, $sortorder);

$param = rexelsyncBuildListFilterParam($filters);

$title = $langs->trans('RexelSyncSync');
llxHeader('', $title);
print load_fiche_titre($title, '', 'fa-sync');

$head = rexelsyncPrepareHead('sync', $totalRows);
print dol_get_fiche_head($head, 'sync', $langs->trans('RexelSync'), -1, 'fa-sync');

if (!empty($missing)) {
	print '<div class="warning">';
	print img_warning().' '.$langs->trans('RexelSyncMissingConfiguration', dol_escape_htmltag(implode(', ', $missing))).' ';
	print '<a href="'.dol_buildpath('/rexelsync/admin/setup.php', 1).'">'.$langs->trans('RexelSyncOpenSetup').'</a>';
	print '</div><br>';
}

print '<div class="tabsAction">';
if ($user->hasRight('rexelsync', 'sync', 'write') && empty($missing)) {
	print '<a class="butAction" href="#" id="rexelsync-run-all" data-token="'.dol_escape_htmltag(newToken()).'" data-total="'.((int) $totalBatchRows).'" data-limit="'.RexelSync::normalizeBatchSize((int) $config['batch_size']).'">'.$langs->trans('RexelSyncRunAll').'</a>';
} else {
	print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('RexelSyncRunAllDisabled').'">'.$langs->trans('RexelSyncRunAll').'</span>';
}
print '</div>';

print '<div id="rexelsync-batch-dialog" title="'.dol_escape_htmltag($langs->trans('RexelSyncBatchModalTitle')).'" style="display:none;">';
print '<div id="rexelsync-batch-status" class="opacitymedium">'.$langs->trans('RexelSyncBatchPreparing').'</div>';
print '<div style="margin-top: 12px;"><progress id="rexelsync-batch-progress" value="0" max="100" style="width: 100%; height: 22px;"></progress></div>';
print '<div id="rexelsync-batch-progress-text" class="center" style="margin-top: 8px;">0 / 0</div>';
print '<div id="rexelsync-batch-stats" class="small opacitymedium" style="margin-top: 10px;"></div>';
print '<div id="rexelsync-batch-message" class="opacitymedium" style="margin-top: 10px;"></div>';
print '<div id="rexelsync-batch-error" class="error" style="display:none; margin-top: 10px;"></div>';
print '<div class="center" style="margin-top: 14px;">';
print '<button type="button" class="button" id="rexelsync-batch-stop">'.$langs->trans('RexelSyncBatchStop').'</button> ';
print '<button type="button" class="button" id="rexelsync-batch-close" disabled="disabled">'.$langs->trans('RexelSyncBatchCloseRefresh').'</button>';
print '</div>';
print '</div>';

print_barre_liste('', $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $totalRows, $totalRows, 'fa-sync', 0, '', '', $limit);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre center nowrap">'.$form->showFilterButtons('left').'<br><input type="text" class="flat width50" name="search_price_line_id" value="'.dol_escape_htmltag($searchPriceLineId).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref_product" value="'.dol_escape_htmltag($searchRefProduct).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_label_product" value="'.dol_escape_htmltag($searchLabelProduct).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref_fourn" value="'.dol_escape_htmltag($searchRefFourn).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_parsed_ref" value="'.dol_escape_htmltag($searchParsedRef).'"></td>';
print '<td class="liste_titre right nowrap">';
print '<input type="text" class="flat width75" name="search_price_min" placeholder="'.dol_escape_htmltag($langs->trans('RexelSyncFilterMin')).'" value="'.dol_escape_htmltag($searchPriceMin).'"><br>';
print '<input type="text" class="flat width75" name="search_price_max" placeholder="'.dol_escape_htmltag($langs->trans('RexelSyncFilterMax')).'" value="'.dol_escape_htmltag($searchPriceMax).'">';
print '</td>';
print '<td class="liste_titre right nowrap">';
print '<input type="text" class="flat width75" name="search_stock_min" placeholder="'.dol_escape_htmltag($langs->trans('RexelSyncFilterMin')).'" value="'.dol_escape_htmltag($searchStockMin).'"><br>';
print '<input type="text" class="flat width75" name="search_stock_max" placeholder="'.dol_escape_htmltag($langs->trans('RexelSyncFilterMax')).'" value="'.dol_escape_htmltag($searchStockMax).'">';
print '</td>';
print '<td class="liste_titre nowrap">';
print '<span class="small opacitymedium">'.$langs->trans('RexelSyncFilterFrom').'</span><br>';
print $form->selectDate(!empty($searchLastSyncStart['timestamp']) ? (int) $searchLastSyncStart['timestamp'] : '', 'search_last_sync_start', 0, 0, 1, '', 1, 0);
print '<br><span class="small opacitymedium">'.$langs->trans('RexelSyncFilterTo').'</span><br>';
print $form->selectDate(!empty($searchLastSyncEnd['timestamp']) ? (int) $searchLastSyncEnd['timestamp'] : '', 'search_last_sync_end', 0, 0, 1, '', 1, 0);
print '</td>';
print '<td class="liste_titre">'.rexelsyncRenderStatusFilter($statusOptions, $searchStatuses).'</td>';
print '<td class="liste_titre"></td>';
print '</tr>';

print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('ID'), $_SERVER['PHP_SELF'], 'pfp.rowid', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Label'), $_SERVER['PHP_SELF'], 'p.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncSupplierRef'), $_SERVER['PHP_SELF'], 'pfp.ref_fourn', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncParsedRef'), $_SERVER['PHP_SELF'], 'parsed_ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncCurrentPrice'), $_SERVER['PHP_SELF'], 'pfp.unitprice', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncSupplierStock'), $_SERVER['PHP_SELF'], 'ef.supplier_stock', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RexelSyncLastSync'), $_SERVER['PHP_SELF'], 'lastlog.datec', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 'lastlog.status', '', $param, 'class="center"', $sortfield, $sortorder);
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';

if (empty($rows)) {
	print '<tr class="oddeven"><td colspan="10" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}

foreach ($rows as $row) {
	$log = null;
	if (!empty($row['last_sync_status']) || !empty($row['last_sync_datec'])) {
		$log = array(
			'datec' => $row['last_sync_datec'],
			'status' => $row['last_sync_status'],
			'message' => $row['last_sync_message'],
		);
	}
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
	} else {
		print rexelsyncStatusBadge(RexelSync::STATUS_NEVER_SYNCED);
	}
	print '</td>';
	print '<td class="center">';
	if ($user->hasRight('rexelsync', 'sync', 'write') && empty($missing)) {
		$synconeUrl = $_SERVER['PHP_SELF'].'?action=syncone&lineid='.((int) $row['price_line_id']).'&token='.newToken();
		if ($listContextQuery !== '') {
			$synconeUrl .= '&'.$listContextQuery;
		}
		print '<a class="reposition butActionSmall" href="'.dol_escape_htmltag($synconeUrl).'">'.$langs->trans('RexelSyncRunOne').'</a>';
	}
	print '</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

if ($user->hasRight('rexelsync', 'sync', 'write') && empty($missing)) {
	$batchTexts = array(
		'preparing' => $langs->transnoentitiesnoconv('RexelSyncBatchPreparing'),
		'running' => $langs->transnoentitiesnoconv('RexelSyncBatchRunning'),
		'done' => $langs->transnoentitiesnoconv('RexelSyncBatchDone'),
		'stopped' => $langs->transnoentitiesnoconv('RexelSyncBatchStopped'),
		'ajax_error' => $langs->transnoentitiesnoconv('RexelSyncBatchAjaxError'),
		'no_rows' => $langs->transnoentitiesnoconv('RexelSyncBatchNoRows'),
		'progress' => $langs->transnoentitiesnoconv('RexelSyncBatchProgress'),
		'updated' => $langs->transnoentitiesnoconv('RexelSyncStatusUpdated'),
		'stock_updated' => $langs->transnoentitiesnoconv('RexelSyncStatusStockUpdated'),
		'unchanged' => $langs->transnoentitiesnoconv('RexelSyncStatusUnchanged'),
		'error' => $langs->transnoentitiesnoconv('RexelSyncStatusError'),
		'not_found' => $langs->transnoentitiesnoconv('RexelSyncStatusNotFound'),
		'invalid_ref' => $langs->transnoentitiesnoconv('RexelSyncStatusInvalidRef'),
	);
	print '<script>';
	print 'jQuery(function($) {';
	print 'var rexelsyncTexts = '.json_encode($batchTexts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';';
	print 'var rexelsyncUrl = '.json_encode($_SERVER['PHP_SELF'], JSON_UNESCAPED_SLASHES).';';
	print 'var $dialog = $("#rexelsync-batch-dialog");';
	print 'var state = null;';
	print 'function formatText(template, values) { var i = 0; return String(template).replace(/%s/g, function() { return values[i++] || "0"; }); }';
	print 'function emptyStats() { return {success: 0, updated: 0, stock_updated: 0, unchanged: 0, error: 0, not_found: 0, invalid_ref: 0}; }';
	print 'function addStats(target, source) { $.each(target, function(key) { target[key] += parseInt(source && source[key] ? source[key] : 0, 10); }); }';
	print 'function renderStats() { var s = state.stats; $("#rexelsync-batch-stats").text(rexelsyncTexts.updated + ": " + s.updated + " | " + rexelsyncTexts.stock_updated + ": " + s.stock_updated + " | " + rexelsyncTexts.unchanged + ": " + s.unchanged + " | " + rexelsyncTexts.not_found + ": " + s.not_found + " | " + rexelsyncTexts.invalid_ref + ": " + s.invalid_ref + " | " + rexelsyncTexts.error + ": " + s.error); }';
	print 'function renderProgress(message) { var total = Math.max(0, parseInt(state.total || 0, 10)); var processed = Math.max(0, parseInt(state.processed || 0, 10)); var percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0; $("#rexelsync-batch-progress").attr("max", 100).val(percent); $("#rexelsync-batch-progress-text").text(formatText(rexelsyncTexts.progress, [processed, total])); $("#rexelsync-batch-status").text(message || ""); renderStats(); }';
	print 'function finish(message) { state.running = false; $("#rexelsync-batch-status").text(message); $("#rexelsync-batch-stop").prop("disabled", true); $("#rexelsync-batch-close").prop("disabled", false); }';
	print 'function showError(message) { $("#rexelsync-batch-error").text(message || rexelsyncTexts.ajax_error).show(); finish(message || rexelsyncTexts.ajax_error); }';
	print 'function runNextBatch() { if (!state || state.stopped) { finish(rexelsyncTexts.stopped); return; } $.ajax({type: "POST", url: rexelsyncUrl, dataType: "json", data: {action: "syncbatch", token: state.token, offset: state.offset}}).done(function(response) { if (response && response.token) { state.token = response.token; } if (!response || response.success === false || response.fatal) { showError(response && response.message ? response.message : rexelsyncTexts.ajax_error); return; } state.total = parseInt(response.total || state.total || 0, 10); state.offset = parseInt(response.next_offset || 0, 10); state.processed = Math.min(state.total, state.processed + parseInt(response.processed || 0, 10)); addStats(state.stats, response.stats || {}); $("#rexelsync-batch-message").text(response.message || ""); if (state.total <= 0) { renderProgress(rexelsyncTexts.no_rows); finish(rexelsyncTexts.no_rows); return; } renderProgress(formatText(rexelsyncTexts.running, [state.processed, state.total])); if (state.stopped) { finish(rexelsyncTexts.stopped); return; } if (response.done) { renderProgress(rexelsyncTexts.done); finish(rexelsyncTexts.done); return; } window.setTimeout(runNextBatch, 100); }).fail(function(xhr) { var message = rexelsyncTexts.ajax_error; if (xhr.responseJSON && xhr.responseJSON.message) { message = xhr.responseJSON.message; } showError(message); }); }';
	print '$("#rexelsync-run-all").on("click", function(event) { event.preventDefault(); var $button = $(this); state = {token: $button.data("token"), total: parseInt($button.data("total") || 0, 10), limit: parseInt($button.data("limit") || 50, 10), offset: 0, processed: 0, stats: emptyStats(), stopped: false, running: true}; $("#rexelsync-batch-error").hide().text(""); $("#rexelsync-batch-message").text(""); $("#rexelsync-batch-stop").prop("disabled", false); $("#rexelsync-batch-close").prop("disabled", true); renderProgress(rexelsyncTexts.preparing); if ($.fn.dialog) { $dialog.dialog({modal: true, width: 620, closeOnEscape: false}); } else { $dialog.show(); } runNextBatch(); });';
	print '$("#rexelsync-batch-stop").on("click", function() { if (state) { state.stopped = true; $("#rexelsync-batch-stop").prop("disabled", true); $("#rexelsync-batch-status").text(rexelsyncTexts.stopped); } });';
	print '$("#rexelsync-batch-close").on("click", function() { window.location.href = window.location.href; });';
	print '});';
	print '</script>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();

/**
 * Build list context query string for post-action redirects.
 *
 * @param string               $sortfield Sort field
 * @param string               $sortorder Sort order
 * @param int                  $page Page
 * @param int                  $limit Limit
 * @param bool                 $includeLimit Include limit in query
 * @param array<string,mixed>  $filters Filters
 * @return string
 */
function rexelsyncBuildListContextQuery($sortfield, $sortorder, $page, $limit, $includeLimit, array $filters)
{
	$params = array(
		'sortfield' => (string) $sortfield,
		'sortorder' => (string) $sortorder,
		'page' => (string) max(0, (int) $page),
	);
	if (!empty($includeLimit) && (int) $limit > 0) {
		$params['limit'] = (string) ((int) $limit);
	}
	$params = array_merge($params, rexelsyncBuildListFilterParams($filters));

	return http_build_query($params, '', '&');
}

/**
 * Build filter-only query string for list links.
 *
 * @param array<string,mixed> $filters Filters
 * @return string
 */
function rexelsyncBuildListFilterParam(array $filters)
{
	$params = rexelsyncBuildListFilterParams($filters);
	$query = http_build_query($params, '', '&');

	return $query !== '' ? '&'.$query : '';
}

/**
 * Build filter-only parameters.
 *
 * @param array<string,mixed> $filters Filters
 * @return array<string,mixed>
 */
function rexelsyncBuildListFilterParams(array $filters)
{
	$params = array();
	foreach (array(
		'search_price_line_id',
		'search_ref_product',
		'search_label_product',
		'search_ref_fourn',
		'search_parsed_ref',
		'search_price_min',
		'search_price_max',
		'search_stock_min',
		'search_stock_max',
		'search_last_sync_startday',
		'search_last_sync_startmonth',
		'search_last_sync_startyear',
		'search_last_sync_endday',
		'search_last_sync_endmonth',
		'search_last_sync_endyear',
	) as $key) {
		if (!empty($filters[$key])) {
			$params[$key] = (string) $filters[$key];
		}
	}
	if (!empty($filters['search_status']) && is_array($filters['search_status'])) {
		$params['search_status'] = array_values($filters['search_status']);
	}

	return $params;
}

/**
 * Return status filter options.
 *
 * @param Translate $langs Language handler
 * @return array<string,string>
 */
function rexelsyncStatusOptions($langs)
{
	return array(
		RexelSync::STATUS_UPDATED => $langs->trans('RexelSyncStatusUpdated'),
		RexelSync::STATUS_STOCK_UPDATED => $langs->trans('RexelSyncStatusStockUpdated'),
		RexelSync::STATUS_UNCHANGED => $langs->trans('RexelSyncStatusUnchanged'),
		RexelSync::STATUS_NOT_FOUND => $langs->trans('RexelSyncStatusNotFound'),
		RexelSync::STATUS_INVALID_REF => $langs->trans('RexelSyncStatusInvalidRef'),
		RexelSync::STATUS_ERROR => $langs->trans('RexelSyncStatusError'),
		RexelSync::STATUS_NEVER_SYNCED => $langs->trans('RexelSyncStatusNeverSynced'),
	);
}

/**
 * Read and validate selected statuses.
 *
 * @param array<int,string> $allowedStatuses Allowed statuses
 * @return array<int,string>
 */
function rexelsyncGetStatusFilter(array $allowedStatuses)
{
	$rawStatuses = GETPOST('search_status', 'array');
	if (!is_array($rawStatuses)) {
		return array();
	}

	$statuses = array();
	foreach ($rawStatuses as $status) {
		$status = (string) $status;
		if (in_array($status, $allowedStatuses, true) && !in_array($status, $statuses, true)) {
			$statuses[] = $status;
		}
	}

	return $statuses;
}

/**
 * Render the native multiselect2 status filter.
 *
 * @param array<string,string> $statusOptions Status options
 * @param array<int,string>    $selectedStatuses Selected statuses
 * @return string
 */
function rexelsyncRenderStatusFilter(array $statusOptions, array $selectedStatuses)
{
	$html = '<select class="flat maxwidth150 multiselect2" multiple="multiple" name="search_status[]">';
	foreach ($statusOptions as $status => $label) {
		$selected = in_array((string) $status, $selectedStatuses, true) ? ' selected="selected"' : '';
		$html .= '<option value="'.dol_escape_htmltag((string) $status).'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
	}
	$html .= '</select>';

	return $html;
}

/**
 * Return empty date filter values.
 *
 * @return array{timestamp:int,day:int,month:int,year:int}
 */
function rexelsyncEmptyDateFilterValues()
{
	return array(
		'timestamp' => 0,
		'day' => 0,
		'month' => 0,
		'year' => 0,
	);
}

/**
 * Read date filter values generated by Form::selectDate().
 *
 * @param string $prefix Field prefix
 * @param bool   $endOfDay Use end of selected day
 * @return array{timestamp:int,day:int,month:int,year:int}
 */
function rexelsyncGetDateFilterValues($prefix, $endOfDay)
{
	$values = rexelsyncEmptyDateFilterValues();
	$day = GETPOST($prefix.'day', 'int');
	$month = GETPOST($prefix.'month', 'int');
	$year = GETPOST($prefix.'year', 'int');
	if ($day <= 0 || $month <= 0 || $year <= 0) {
		return $values;
	}

	$values['day'] = (int) $day;
	$values['month'] = (int) $month;
	$values['year'] = (int) $year;
	$values['timestamp'] = dol_mktime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0, (int) $month, (int) $day, (int) $year);

	return $values;
}

/**
 * Return the JSON stats shape expected by the batch UI.
 *
 * @return array<string,int>
 */
function rexelsyncEmptyJsonStats()
{
	return array(
		'success' => 0,
		RexelSync::STATUS_UPDATED => 0,
		RexelSync::STATUS_STOCK_UPDATED => 0,
		RexelSync::STATUS_UNCHANGED => 0,
		RexelSync::STATUS_ERROR => 0,
		RexelSync::STATUS_NOT_FOUND => 0,
		RexelSync::STATUS_INVALID_REF => 0,
	);
}

/**
 * Normalize sync stats for JSON responses.
 *
 * @param array<string,mixed> $stats Stats
 * @return array<string,int>
 */
function rexelsyncStatsForJson(array $stats)
{
	$jsonStats = rexelsyncEmptyJsonStats();
	foreach ($jsonStats as $key => $value) {
		$jsonStats[$key] = !empty($stats[$key]) ? (int) $stats[$key] : 0;
	}

	return $jsonStats;
}

/**
 * Emit a JSON response and stop execution.
 *
 * @param array<string,mixed> $payload Payload
 * @param int                 $httpStatus HTTP status
 * @return void
 */
function rexelsyncJsonResponse(array $payload, $httpStatus = 200)
{
	if (!headers_sent()) {
		http_response_code((int) $httpStatus);
		header('Content-Type: application/json; charset=UTF-8');
	}

	print json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}
