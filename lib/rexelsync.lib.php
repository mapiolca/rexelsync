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
	global $langs;

	$langs->load('rexelsync@rexelsync');

	$head = array();
	$head[] = array(dol_buildpath('/rexelsync/admin/setup.php', 1), $langs->trans('Settings'), 'settings');

	return $head;
}

/**
 * Front tabs.
 *
 * @param string $active Active tab
 * @return array<int,array<int,string>>
 */
function rexelsyncPrepareHead($active = '')
{
	global $langs, $user;

	$langs->load('rexelsync@rexelsync');

	$head = array();
	$head[] = array(dol_buildpath('/rexelsync/sync.php', 1), $langs->trans('RexelSyncSync'), 'sync');
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
	if ($status === 'error') {
		return '<span class="badge badge-status8">'.$langs->trans('RexelSyncStatusError').'</span>';
	}

	return '<span class="badge badge-status0">'.dol_escape_htmltag($status).'</span>';
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
