<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       dolistockmoveindex.php
 * \ingroup    dolistockmove
 * \brief      Home page for DoliStockMove module
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
if (!$res && file_exists('../main.inc.php')) { $res = @include '../main.inc.php'; }
if (!$res && file_exists('../../main.inc.php')) { $res = @include '../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

$langs->loadLangs(array('dolistockmove@dolistockmove'));

if (!isModEnabled('dolistockmove')) { accessforbidden(); }
if (!$user->hasRight('stock', 'lire')) { accessforbidden(); }

/*
 * View
 */
llxHeader('', $langs->trans('DolistockmoveIndex'));

print load_fiche_titre(img_picto('', 'fa-dolly', 'class="paddingright"').$langs->trans('DoliStockMove'), '', '');

print '<div class="fichecenter">';
print '<div class="fichethirdleft">';

// Quick actions box
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder" style="width:100%">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('QuickActions').'</td></tr>';

if ($user->hasRight('stock', 'mouvement', 'creer')) {
	print '<tr class="oddeven">';
	print '<td>'.img_picto('', 'fa-plus-circle', 'class="paddingright"');
	print '<a href="'.dol_buildpath('/dolistockmove/stockmove_card.php', 1).'">';
	print $langs->trans('NewStockMove').'</a></td>';
	print '</tr>';
}

print '<tr class="oddeven">';
print '<td>'.img_picto('', 'fa-list', 'class="paddingright"');
print '<a href="'.dol_buildpath('/dolistockmove/stockmove_list.php', 1).'">';
print $langs->trans('StockMoveList').'</a></td>';
print '</tr>';

print '</table>';
print '</div>';

print '</div>'; // fichethirdleft

print '<div class="fichetwothirdright">';

// Last 10 movements
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder" style="width:100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Date').'</td>';
print '<td>'.$langs->trans('Product').'</td>';
print '<td>'.$langs->trans('Qty').'</td>';
print '<td>'.$langs->trans('LinkedProposal').'</td>';
print '</tr>';

$sql  = "SELECT m.rowid, m.datem, m.value, m.fk_product,";
$sql .= " p.ref as product_ref, p.label as product_label,";
$sql .= " pr.ref as propal_ref, pr.rowid as propal_id";
$sql .= " FROM ".MAIN_DB_PREFIX."stock_mouvement m";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = m.fk_product";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."stock_mouvement_extrafields mef ON mef.fk_object = m.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."propal pr ON pr.rowid = mef.devis";
$sql .= " WHERE p.entity IN (".getEntity('product').")";
$sql .= " ORDER BY m.datem DESC";
$sql .= $db->plimit(10, 0);

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	if ($num == 0) {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoMovementsFound').'</span></td></tr>';
	}
	$i = 0;
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($obj->datem), 'day').'</td>';
		print '<td><strong>'.$obj->product_ref.'</strong> — '.dol_trunc($obj->product_label, 30).'</td>';
		$qty = (float) $obj->value;
		$class = $qty < 0 ? 'badge badge-warning' : 'badge badge-success';
		$sign = $qty < 0 ? '' : '+';
		print '<td><span class="'.$class.'">'.$sign.price($qty).'</span></td>';
		if ($obj->propal_ref) {
			print '<td><a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$obj->propal_id.'">'.$obj->propal_ref.'</a></td>';
		} else {
			print '<td>—</td>';
		}
		print '</tr>';
		$i++;
	}
	$db->free($resql);
} else {
	print '<tr><td colspan="4">'.dol_print_error($db).'</td></tr>';
}

print '</table>';
print '</div>';

print '</div>'; // fichetwothirdright
print '</div>'; // fichecenter

llxFooter();
$db->close();
