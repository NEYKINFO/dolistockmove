<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       product_stockmovements.php
 * \ingroup    dolistockmove
 * \brief      Tab on product/service card showing linked stock movements
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

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';

$langs->loadLangs(array('dolistockmove@dolistockmove', 'products', 'stocks', 'propal'));

$id  = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');

// Security check
if (!isModEnabled('dolistockmove')) { accessforbidden(); }
if (!isModEnabled('product') && !isModEnabled('service')) { accessforbidden(); }
if (!$user->hasRight('stock', 'lire')) { accessforbidden(); }

// Load product
$object = new Product($db);
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

// Check read permission on products
if (!$user->hasRight('produit', 'lire')) { accessforbidden(); }

/*
 * View
 */
$title = $object->ref.' — '.$langs->trans('StockMovements');
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'mod-dolistockmove page-product_stockmovements');

// Product tabs (reuse native product header)
$head = product_prepare_head($object);
$titre = $langs->trans('ProductServiceCard');
$picto = ($object->type == Product::TYPE_SERVICE) ? 'service' : 'product';
print dol_get_fiche_head($head, 'dolistockmove_moves', $titre, -1, $picto);

// Product banner
$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '');

print dol_get_fiche_end();

// ---- New movement button ----
if ($user->hasRight('stock', 'mouvement', 'creer')) {
	print '<div style="margin:10px 0;">';
	print '<a class="button buttonaction" href="'.dol_buildpath('/dolistockmove/stockmove_card.php', 1).'?prefill_product_id='.$object->id.'&prefill_product_ref='.urlencode($object->ref).'">';
	print img_picto('', 'fa-plus', 'class="paddingright"').$langs->trans('GoToNewMove');
	print '</a>';
	print '</div>';
}

// ---- Query movements for this product ----
// We join mouvement_stock_extrafields to list only movements created by this module.
// If no extrafields row exists for a movement, it was created by another module and is excluded.
$sql  = "SELECT m.rowid, m.datem, m.label, m.qty, m.type, m.fk_entrepot,";
$sql .= " e.ref as entrepot_ref,";
$sql .= " u.login as user_login, u.firstname, u.lastname,";
$sql .= " mef.fk_proposal,";
$sql .= " pr.ref as propal_ref, pr.title as propal_title";
$sql .= " FROM ".MAIN_DB_PREFIX."mouvement_stock m";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."mouvement_stock_extrafields mef ON mef.fk_object = m.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = m.fk_entrepot";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = m.fk_user_author";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."propal pr ON pr.rowid = mef.fk_proposal";
$sql .= " WHERE m.fk_product = ".((int) $object->id);
$sql .= "   AND m.entity IN (".getEntity('stock').")";
$sql .= " ORDER BY m.datem DESC";

$resql = $db->query($sql);

// Aggregate per proposal for the summary table
$by_proposal = array(); // proposal_id => [ref, title, sortie, retour]
$rows = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
		$pid = (int) $obj->fk_proposal;
		$key = $pid > 0 ? $pid : 0;
		if (!isset($by_proposal[$key])) {
			$by_proposal[$key] = array(
				'ref'    => $pid > 0 ? $obj->propal_ref : '—',
				'title'  => $pid > 0 ? $obj->propal_title : '',
				'id'     => $pid,
				'sortie' => 0,
				'retour' => 0,
			);
		}
		$qty = (float) $obj->qty;
		if ($qty < 0) {
			$by_proposal[$key]['sortie'] += abs($qty);
		} else {
			$by_proposal[$key]['retour'] += $qty;
		}
	}
	$db->free($resql);
}

if (empty($rows)) {
	print '<div class="info">'.$langs->trans('NoMovementsForProduct').'</div>';
} else {
	// ---- Summary per proposal ----
	print '<h3>'.img_picto('', 'fa-chart-bar', 'class="paddingright"').$langs->trans('SummaryByProposal').'</h3>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder" style="width:100%">';
	print '<thead><tr class="liste_titre">';
	print '<th>'.$langs->trans('Proposal').'</th>';
	print '<th class="right">'.$langs->trans('TotalSortie').'</th>';
	print '<th class="right">'.$langs->trans('TotalRetour').'</th>';
	print '<th class="right">'.$langs->trans('NetQty').'</th>';
	print '</tr></thead><tbody>';

	foreach ($by_proposal as $key => $data) {
		$net = $data['retour'] - $data['sortie'];
		$net_class = $net < 0 ? 'color:var(--colorwarning)' : 'color:var(--colorsuccess)';
		print '<tr class="oddeven">';
		if ($data['id'] > 0) {
			$propal_url = dol_buildpath('/comm/propal/card.php', 1).'?id='.$data['id'];
			print '<td><a href="'.dol_escape_htmltag($propal_url).'"><strong>'.dol_escape_htmltag($data['ref']).'</strong></a>';
			if ($data['title']) { print '<br><span class="opacitymedium">'.dol_escape_htmltag(dol_trunc($data['title'], 60)).'</span>'; }
			print '</td>';
		} else {
			print '<td><span class="opacitymedium">'.$langs->trans('NoProposal').'</span></td>';
		}
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
	print '<th>'.$langs->trans('MoveDirection').'</th>';
	print '<th class="right">'.$langs->trans('Qty').'</th>';
	print '<th>'.$langs->trans('Warehouse').'</th>';
	print '<th>'.$langs->trans('Proposal').'</th>';
	print '<th>'.$langs->trans('User').'</th>';
	print '<th>'.$langs->trans('Note').'</th>';
	print '</tr></thead><tbody>';

	foreach ($rows as $obj) {
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($obj->datem), 'dayhour').'</td>';

		$qty = (float) $obj->qty;
		if ($qty < 0) {
			print '<td><span class="badge badge-warning">'.$langs->trans('Sortie').'</span></td>';
			print '<td class="right" style="color:var(--colorwarning);font-weight:bold;">-'.price(abs($qty)).'</td>';
		} else {
			print '<td><span class="badge badge-success">'.$langs->trans('Retour').'</span></td>';
			print '<td class="right" style="color:var(--colorsuccess);">+'.price($qty).'</td>';
		}

		print '<td>'.dol_escape_htmltag($obj->entrepot_ref ?: '—').'</td>';

		$pid = (int) $obj->fk_proposal;
		if ($pid > 0) {
			$propal_url = dol_buildpath('/comm/propal/card.php', 1).'?id='.$pid;
			print '<td><a href="'.dol_escape_htmltag($propal_url).'">'.dol_escape_htmltag($obj->propal_ref).'</a></td>';
		} else {
			print '<td>—</td>';
		}

		$user_display = trim(($obj->firstname ? $obj->firstname.' ' : '').$obj->lastname);
		if (empty($user_display)) { $user_display = $obj->user_login; }
		print '<td>'.dol_escape_htmltag($user_display).'</td>';
		print '<td>'.dol_escape_htmltag(dol_trunc($obj->label, 60)).'</td>';
		print '</tr>';
	}

	print '</tbody></table>';
	print '</div>';
}

llxFooter();
$db->close();
