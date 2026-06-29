<?php
/* Copyright (C) 2026 RexelSync contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * About page for RexelSync module.
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
require_once __DIR__.'/../class/rexelsynccompatibility.class.php';
require_once __DIR__.'/../core/modules/modRexelSync.class.php';

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'rexelsync@rexelsync'));

if (empty($user->admin) && !$user->hasRight('rexelsync', 'config', 'write')) {
	accessforbidden();
}

$moduleDescriptor = new modRexelSync($db);
$title = $langs->trans('RexelSyncAbout');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('rexelsync').'">'.$langs->trans('BackToModuleList').'</a>';

llxHeader('', $title);

print load_fiche_titre($title, $linkback, 'rexelsync@rexelsync');

$head = rexelsyncAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('RexelSyncSetup'), -1, 'rexelsync@rexelsync');

print '<div class="underbanner opacitymedium">'.$langs->trans('RexelSyncAboutPage').'</div>';
print '<br>';

print '<div class="fichecenter">';

print '<div class="fichehalfleft">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('RexelSyncAboutGeneral').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Module').'</td><td>'.dol_escape_htmltag($langs->trans('RexelSync')).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag($moduleDescriptor->version).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Family').'</td><td>'.dol_escape_htmltag($moduleDescriptor->family).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Description').'</td><td>'.dol_escape_htmltag($langs->trans($moduleDescriptor->description)).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Editor').'</td><td>'.dol_escape_htmltag($moduleDescriptor->editor_name).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Compatibility').'</td><td>'.$langs->trans('RexelSyncAboutCompatibilityValue', RexelSyncCompatibility::MIN_DOLIBARR_VERSION, RexelSyncCompatibility::MIN_PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Dependencies').'</td><td>'.$langs->trans('RexelSyncAboutDependenciesValue').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('RexelSyncAboutPrerequisites').'</td><td>'.$langs->trans('RexelSyncAboutPrerequisitesValue').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('License').'</td><td>'.$langs->trans('RexelSyncAboutLicenseValue').'</td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '<div class="fichehalfright">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('RexelSyncAboutResources').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Documentation').'</td><td><a href="'.dol_buildpath('/rexelsync/README.md', 1).'" target="_blank" rel="noopener">'.$langs->trans('RexelSyncAboutDocumentationLink').'</a></td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Support').'</td><td>'.dol_escape_htmltag($langs->trans('RexelSyncAboutSupportValue')).'</td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '</div>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('RexelSyncAboutFeatures').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncAboutFeaturePrices').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncAboutFeatureStock').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncAboutFeatureCustomers').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncAboutFeatureLogs').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncAboutFeatureAjax').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncAboutFeatureCron').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RexelSyncAboutFeatureAuth').'</td></tr>';
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
