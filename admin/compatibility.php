<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Compatibility page for RexelSync module.
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
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once __DIR__.'/../lib/rexelsync.lib.php';
require_once __DIR__.'/../class/rexelsynccompatibility.class.php';

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'rexelsync@rexelsync'));

if (empty($user->admin) && !$user->hasRight('rexelsync', 'config', 'write')) {
	accessforbidden();
}

$title = $langs->trans('RexelSyncCompatibility');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('rexelsync').'">'.$langs->trans('BackToModuleList').'</a>';

llxHeader('', $title);

print load_fiche_titre($title, $linkback, 'rexelsync@rexelsync');

$head = rexelsyncAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('RexelSyncSetup'), -1, 'rexelsync@rexelsync');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('RexelSyncCompatibilityEnvironment').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('RexelSyncCompatibilityDetectedPhp').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncCompatibilityDetectedDolibarr').'</td><td>'.dol_escape_htmltag(defined('DOL_VERSION') ? DOL_VERSION : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncCompatibilityMinimumPhp').'</td><td>'.dol_escape_htmltag(RexelSyncCompatibility::MIN_PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncCompatibilityMinimumDolibarr').'</td><td>'.dol_escape_htmltag(RexelSyncCompatibility::MIN_DOLIBARR_VERSION).'</td></tr>';
print '</table>';

print '<br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Code').'</th>';
print '<th>'.$langs->trans('Label').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '<th>'.$langs->trans('Reason').'</th>';
print '</tr>';

foreach (RexelSyncCompatibility::getFeatures() as $code => $feature) {
	$status = RexelSyncCompatibility::getFeatureStatus($code, $feature);
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($code).'</td>';
	print '<td>'.$langs->trans($status['label']).'</td>';
	print '<td>'.$langs->trans($status['description']).'</td>';
	print '<td class="center">'.yn(!empty($status['available'])).'</td>';
	print '<td>'.$langs->trans($status['reason']).'</td>';
	print '</tr>';
}

print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
