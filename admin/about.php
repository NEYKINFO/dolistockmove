<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       admin/about.php
 * \ingroup    dolistockmove
 * \brief      About page
 */

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
if (!$res && file_exists('../../main.inc.php')) { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/dolistockmove/lib/dolistockmove.lib.php');

$langs->loadLangs(array('admin', 'dolistockmove@dolistockmove'));

if (!$user->admin) { accessforbidden(); }
if (!isModEnabled('dolistockmove')) { accessforbidden(); }

llxHeader('', $langs->trans('About'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.img_picto('', 'back').' '.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ModuleDolistockmoveName'), $linkback, 'title_setup');

$head = dolistockmoveAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('Module'), -1, 'stock');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder" style="width:100%">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('ModuleDolistockmoveName').' v1.0.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Author').'</td><td>NEYKINFO</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DescriptionModule').'</td><td>'.$langs->trans('DolistockmoveDescriptionLong').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('NeedDolibarrVersion').'</td><td>20.0+</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
