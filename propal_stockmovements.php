<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       propal_stockmovements.php
 * \ingroup    dolistockmove
 * \brief      Tab on proposal showing linked stock movements
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

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';

$langs->loadLangs(array('dolistockmove@dolistockmove', 'propal', 'stocks', 'products'));

$id  = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');

// Security check
if (!isModEnabled('dolistockmove')) { accessforbidden(); }
if (!isModEnabled('propal')) { accessforbidden(); }
if (!$user->hasRight('stock', 'lire')) { accessforbidden(); }

// Load proposal
$object = new Propal($db);
if ($id > 0) {
	$result = $object->fetch($id);
} elseif (!empty($ref)) {
	$result = $object->fetch(0, $ref);
} else {
	accessforbidden();
}
if ($result < 0 || empty($object->id)) {
	dol_print_error($db);
	exit;
}

// Check access to this proposal
$permissionToRead = $user->hasRight('propal', 'lire');
if (!$permissionToRead) { accessforbidden(); }

/*
 * View
 */
$title = $object->ref.' — '.$langs->trans('StockMovements');
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'mod-dolistockmove page-propal_stockmovements');

// Proposal tabs (reuse native propal header)
$head = propal_prepare_head($object);
print dol_get_fiche_head($head, 'dolistockmove', $langs->trans('Proposal'), -1, 'propal');

// Proposal banner
$linkback = '<a href="'.DOL_URL_ROOT.'/comm/propal/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '');

print dol_get_fiche_end();

// ---- New movement button ----
if ($user->hasRight('stock', 'mouvement', 'creer')) {
	print '<div style="margin:10px 0;">';
	print '<a class="button buttonaction" href="'.dol_buildpath('/dolistockmove/stockmove_card.php', 1).'?fk_proposal='.$object->id.'">';
	print img_picto('', 'fa-plus', 'class="paddingright"').$langs->trans('GoToNewMove');
	print '</a>';
	print '</div>';
}

// ---- Query movements for this proposal ----
$sql  = "SELECT m.rowid, m.datem, m.label, m.qty, m.type, m.fk_product,";
$sql .= " p.ref as product_ref, p.label as product_label,";
$sql .= " e.ref as entrepot_ref,";
$sql .= " u.login as user_login, u.firstname, u.lastname";
$sql .= " FROM ".MAIN_DB_PREFIX."mouvement_stock m";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."mouvement_stock_extrafields mef ON mef.fk_object = m.rowid";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = m.fk_product";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = m.fk_entrepot";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = m.fk_user_author";
$sql .= " WHERE mef.fk_proposal = ".((int) $object->id);
$sql .= " AND m.entity IN (".getEntity('stock').")";
$sql .= " ORDER BY p.ref ASC, m.datem ASC";

$resql = $db->query($sql);

// Aggregate per product for the summary table
$by_product = array(); // product_id => [ref, label, sortie, retour]
$rows = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
		$pid = (int) $obj->fk_product;
		if (!isset($by_product[$pid])) {
			$by_product[$pid] = array(
				'ref'    => $obj->product_ref,
				'label'  => $obj->product_label,
				'sortie' => 0,
				'retour' => 0,
			);
		}
		$qty = (float) $obj->qty;
		if ($qty < 0) {
			$by_product[$pid]['sortie'] += abs($qty);
		} else {
			$by_product[$pid]['retour'] += $qty;
		}
	}
	$db->free($resql);
}

if (empty($rows)) {
	print '<div class="info">'.$langs->trans('NoMovementsForProposal').'</div>';
} else {
	// ---- Summary per product ----
	print '<h3>'.img_picto('', 'fa-chart-bar', 'class="paddingright"').$langs->trans('ProposalStockMovements').'</h3>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder" style="width:100%">';
	print '<thead><tr class="liste_titre">';
	print '<th>'.$langs->trans('Product').'</th>';
	print '<th class="right">'.$langs->trans('TotalSortie').'</th>';
	print '<th class="right">'.$langs->trans('TotalRetour').'</th>';
	print '<th class="right">'.$langs->trans('NetQty').'</th>';
	print '</tr></thead><tbody>';

	foreach ($by_product as $pid => $data) {
		$net = $data['retour'] - $data['sortie'];
		$net_class = $net < 0 ? 'color:var(--colorwarning)' : 'color:var(--colorsuccess)';
		print '<tr class="oddeven">';
		print '<td><strong>'.dol_escape_htmltag($data['ref']).'</strong>';
		if ($data['label']) { print ' — '.dol_escape_htmltag(dol_trunc($data['label'], 50)); }
		print '</td>';
		print '<td class="right"><span style="color:var(--colorwarning);font-weight:bold;">'.price($data['sortie']).'</span></td>';
		print '<td class="right"><span style="color:var(--colorsuccess);">'.price($data['retour']).'</span></td>';
		print '<td class="right"><span style="'.$net_class.';font-weight:bold;">'.($net > 0 ? '+' : '').price($net).'</span></td>';
		print '</tr>';
	}
	print '</tbody></table>';
	print '</div>';

	// ---- Detail all movements ----
	print '<br>';
	print '<h3>'.img_picto('', 'fa-list', 'class="paddingright"').$langs->trans('Details').'</h3>';
	print '<div class="div-table-responsive">';
	print '<table class="tagtable nobottomiftotal liste" style="width:100%">';
	print '<thead><tr class="liste_titre">';
	print '<th>'.$langs->trans('Date').'</th>';
	print '<th>'.$langs->trans('Product').'</th>';
	print '<th>'.$langs->trans('MoveDirection').'</th>';
	print '<th class="right">'.$langs->trans('Qty').'</th>';
	print '<th>'.$langs->trans('Warehouse').'</th>';
	print '<th>'.$langs->trans('User').'</th>';
	print '<th>'.$langs->trans('Note').'</th>';
	print '</tr></thead><tbody>';

	foreach ($rows as $obj) {
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($obj->datem), 'dayhour').'</td>';
		print '<td><strong>'.dol_escape_htmltag($obj->product_ref).'</strong>';
		if ($obj->product_label) { print '<br><span class="opacitymedium">'.dol_trunc($obj->product_label, 40).'</span>'; }
		print '</td>';

		$qty = (float) $obj->qty;
		if ($qty < 0) {
			print '<td><span class="badge badge-warning">'.$langs->trans('Sortie').'</span></td>';
			print '<td class="right" style="color:var(--colorwarning);font-weight:bold;">-'.price(abs($qty)).'</td>';
		} else {
			print '<td><span class="badge badge-success">'.$langs->trans('Retour').'</span></td>';
			print '<td class="right" style="color:var(--colorsuccess);">+'.price($qty).'</td>';
		}

		print '<td>'.dol_escape_htmltag($obj->entrepot_ref ?: '—').'</td>';
		$user_display = trim(($obj->firstname ? $obj->firstname.' ' : '').$obj->lastname);
		if (empty($user_display)) { $user_display = $obj->user_login; }
		print '<td>'.dol_escape_htmltag($user_display).'</td>';
		print '<td>'.dol_trunc($obj->label, 60).'</td>';
		print '</tr>';
	}

	print '</tbody></table>';
	print '</div>';
}

llxFooter();
$db->close();
