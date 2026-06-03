<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/setup.php
 * \ingroup    dolistockmove
 * \brief      Module settings page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/dolistockmove/lib/dolistockmove.lib.php');

$langs->loadLangs(array('admin', 'dolistockmove@dolistockmove'));

// Security check
if (!$user->admin) {
	accessforbidden();
}
if (!isModEnabled('dolistockmove')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */
if ($action === 'update') {
	$dolistockmove_default_warehouse = GETPOSTINT('DOLISTOCKMOVE_DEFAULT_WAREHOUSE');
	$dolistockmove_allow_no_proposal = GETPOST('DOLISTOCKMOVE_ALLOW_NO_PROPOSAL', 'alpha');

	dolibarr_set_const($db, 'DOLISTOCKMOVE_DEFAULT_WAREHOUSE', $dolistockmove_default_warehouse, 'integer', 0, '', $conf->entity);
	dolibarr_set_const($db, 'DOLISTOCKMOVE_ALLOW_NO_PROPOSAL', ($dolistockmove_allow_no_proposal ? 1 : 0), 'integer', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}

/*
 * View
 */
$page_name = $langs->trans('DolistockmoveSetup');
llxHeader('', $page_name);

$linkback = '<a href="'.($conf->global->MAIN_FEATURES_LEVEL == 2 ? DOL_URL_ROOT.'/admin/modules.php?show_contrib=1' : DOL_URL_ROOT.'/admin/modules.php').'">';
$linkback .= img_picto('', 'back').' '.$langs->trans('BackToModuleList').'</a>';

print load_fiche_titre($page_name, $linkback, 'title_setup');

// Tab head
$head = dolistockmoveAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('Module'), -1, 'stock');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '</tr>';

// Default warehouse
$warehouses = array(0 => '-- '.$langs->trans('None').' --');
$sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'entrepot WHERE entity IN ('.getEntity('stock').') ORDER BY label';
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$warehouses[$obj->rowid] = $obj->ref.($obj->label ? ' - '.$obj->label : '');
	}
}
print '<tr class="oddeven">';
print '<td class="titlefieldcreate">';
print $langs->trans('DefaultWarehouse');
print '<br><span class="opacitymedium">'.$langs->trans('DefaultWarehouseDesc').'</span>';
print '</td>';
print '<td>';
print Form::selectarray('DOLISTOCKMOVE_DEFAULT_WAREHOUSE', $warehouses, getDolGlobalInt('DOLISTOCKMOVE_DEFAULT_WAREHOUSE'), 0, 0, 0, '', 0, 0, 0, 'ASC', 'minwidth200');
print '</td></tr>';

// Allow no proposal
print '<tr class="oddeven">';
print '<td>';
print $langs->trans('AllowNoProposal');
print '<br><span class="opacitymedium">'.$langs->trans('AllowNoProposalDesc').'</span>';
print '</td>';
print '<td>';
print ajax_constantonoff('DOLISTOCKMOVE_ALLOW_NO_PROPOSAL', array(), $conf->entity, 0, 0, 1, 2, 0, 0);
print '</td></tr>';

print '</table>';
print '<br>';
print '<center><input type="submit" class="button" name="save" value="'.$langs->trans('Save').'"></center>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
