<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       stockmove_list.php
 * \ingroup    dolistockmove
 * \brief      List of stock movements created via DoliStockMove (with proposal link)
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

require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/dolistockmove/lib/dolistockmove.lib.php');

$langs->loadLangs(array('dolistockmove@dolistockmove', 'stocks', 'products', 'propal'));

if (!isModEnabled('dolistockmove')) { accessforbidden(); }
if (!$user->hasRight('stock', 'lire')) { accessforbidden(); }

$action        = GETPOST('action', 'aZ09');
$confirm       = GETPOST('confirm', 'alpha');
$rowid_del     = GETPOSTINT('rowid');

// Filters
$search_propal   = GETPOST('search_propal', 'alphanohtml');
$search_product  = GETPOST('search_product', 'alphanohtml');
$search_datefrom = GETPOST('search_datefrom', 'alpha');
$search_dateto   = GETPOST('search_dateto', 'alpha');

// Sort
$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = GETPOST('sortorder', 'aZ');
if (empty($sortfield)) { $sortfield = 'm.datem'; }
if (empty($sortorder))  { $sortorder = 'DESC'; }

// Pagination
$limit  = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
$page   = GETPOSTINT('page');
if (empty($page) || $page < 0) { $page = 0; }
$offset = $limit * $page;

$form = new Form($db);

/*
 * Actions
 */
if (($action === 'confirm_delete' || ($action === 'delete' && $confirm === 'yes')) && $user->hasRight('stock', 'mouvement', 'creer')) {
	if ($rowid_del > 0) {
		// Dolibarr does not support deleting MouvementStock natively — we use direct SQL
		// but we also need to revert the product_stock entry.
		// We use the MouvementStock class if it has a delete method, otherwise direct SQL.
		$db->begin();
		$error = 0;

		// Fetch movement to reverse stock
		$sql_mv = "SELECT fk_product, fk_entrepot, value FROM ".MAIN_DB_PREFIX."stock_mouvement WHERE rowid = ".((int) $rowid_del);
		$res_mv = $db->query($sql_mv);
		if ($res_mv && ($obj_mv = $db->fetch_object($res_mv))) {
			// Reverse the qty in product_stock
			$sql_ps  = "UPDATE ".MAIN_DB_PREFIX."product_stock";
			$sql_ps .= " SET reel = reel - ".((float) $obj_mv->value);
			$sql_ps .= " WHERE fk_product = ".((int) $obj_mv->fk_product);
			$sql_ps .= "   AND fk_entrepot = ".((int) $obj_mv->fk_entrepot);
			if (!$db->query($sql_ps)) { $error++; }

			// Delete extrafield
			$sql_ef = "DELETE FROM ".MAIN_DB_PREFIX."stock_mouvement_extrafields WHERE fk_object = ".((int) $rowid_del);
			if (!$db->query($sql_ef)) { $error++; }

			// Delete movement
			$sql_del = "DELETE FROM ".MAIN_DB_PREFIX."stock_mouvement WHERE rowid = ".((int) $rowid_del);
			if (!$db->query($sql_del)) { $error++; }
		} else {
			$error++;
		}

		if ($error) {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			$db->commit();
			setEventMessages($langs->trans('MoveDeleted'), null, 'mesgs');
		}
		$action = '';
	}
}

/*
 * View
 */
$title = $langs->trans('MovesList');
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'mod-dolistockmove page-stockmove_list');

print load_fiche_titre(img_picto('', 'fa-dolly', 'class="paddingright"').$title, '', '');

// ---- Search bar ----
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" id="searchFormList">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';

print '<div class="dolistockmove-search-bar">';
print '<input type="text" name="search_propal" class="form-control" placeholder="'.dol_escape_htmltag($langs->trans('FilterByProposal')).'" value="'.dol_escape_htmltag($search_propal).'">';
print '<input type="text" name="search_product" class="form-control" placeholder="'.dol_escape_htmltag($langs->trans('FilterByProduct')).'" value="'.dol_escape_htmltag($search_product).'">';
print '<label>'.$langs->trans('FilterDateFrom').'</label>';
print '<input type="date" name="search_datefrom" class="form-control" value="'.dol_escape_htmltag($search_datefrom).'">';
print '<label>'.$langs->trans('FilterDateTo').'</label>';
print '<input type="date" name="search_dateto" class="form-control" value="'.dol_escape_htmltag($search_dateto).'">';
print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print '<a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Clear').'</a>';
print '</div>';
print '</form>';

// ---- New movement button ----
if ($user->hasRight('stock', 'mouvement', 'creer')) {
	print '<div style="margin-bottom:10px;">';
	print '<a class="button buttonaction" href="'.dol_buildpath('/dolistockmove/stockmove_card.php', 1).'">';
	print img_picto('', 'fa-plus', 'class="paddingright"').$langs->trans('NewStockMove');
	print '</a>';
	print '</div>';
}

// ---- Build SQL query ----
$sql  = "SELECT m.rowid, m.datem, m.label, m.value, m.type_mouvement, m.fk_product,";
$sql .= " p.ref as product_ref, p.label as product_label,";
$sql .= " e.ref as entrepot_ref,";
$sql .= " u.login as user_login, u.firstname, u.lastname,";
$sql .= " pr.ref as propal_ref, pr.rowid as propal_id, pr.fk_soc,";
$sql .= " s.nom as soc_name,";
$sql .= " mef.devis, mef.chantier, mef.salarie,";
$sql .= " us.lastname as salarie_lastname, us.firstname as salarie_firstname, us.login as salarie_login";
$sql .= " FROM ".MAIN_DB_PREFIX."stock_mouvement m";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = m.fk_product";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."stock_mouvement_extrafields mef ON mef.fk_object = m.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = m.fk_entrepot";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = m.fk_user_author";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."propal pr ON pr.rowid = mef.devis";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = pr.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user us ON us.rowid = mef.salarie";
$sql .= " WHERE p.entity IN (".getEntity('product').")";

// Filters
if (!empty($search_propal)) {
	$sql .= " AND pr.ref LIKE '%".$db->escape($search_propal)."%'";
}
if (!empty($search_product)) {
	$sql .= " AND (p.ref LIKE '%".$db->escape($search_product)."%' OR p.label LIKE '%".$db->escape($search_product)."%')";
}
if (!empty($search_datefrom)) {
	$sql .= " AND m.datem >= '".$db->escape($search_datefrom)." 00:00:00'";
}
if (!empty($search_dateto)) {
	$sql .= " AND m.datem <= '".$db->escape($search_dateto)." 23:59:59'";
}

// Count total
$sql_count = "SELECT COUNT(*) as total FROM (".rtrim($sql).")  as sub";
$rescount  = $db->query($sql_count);
$total     = 0;
if ($rescount) {
	$objc  = $db->fetch_object($rescount);
	$total = (int) $objc->total;
}

// Sort + paginate
$allowed_sorts = array('m.datem', 'm.value', 'p.ref', 'p.label', 'pr.ref', 'e.ref', 'u.login');
if (!in_array($sortfield, $allowed_sorts)) { $sortfield = 'm.datem'; }
$sortorder_safe = ($sortorder === 'ASC') ? 'ASC' : 'DESC';

$sql .= " ORDER BY ".$sortfield." ".$sortorder_safe;
$sql .= $db->plimit($limit, $offset);

$resql = $db->query($sql);

// Pagination links
print_barre_liste(
	$langs->trans('MovesList'),
	$page,
	$_SERVER['PHP_SELF'],
	'&search_propal='.urlencode($search_propal).'&search_product='.urlencode($search_product).'&search_datefrom='.urlencode($search_datefrom).'&search_dateto='.urlencode($search_dateto),
	$sortfield,
	$sortorder,
	'',
	$total,
	$limit,
	'',
	0,
	'',
	'',
	$limit
);

// ---- Table ----
print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste" id="tablelines">';

print '<thead><tr class="liste_titre">';
print getTitleFieldOfList($langs->trans('Date'), 0, $_SERVER['PHP_SELF'], 'm.datem', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('Product'), 0, $_SERVER['PHP_SELF'], 'p.ref', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('MoveDirection'), 0, $_SERVER['PHP_SELF'], 'm.value', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('Qty'), 0, $_SERVER['PHP_SELF'], 'm.value', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('Warehouse'), 0, $_SERVER['PHP_SELF'], 'e.ref', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('LinkedProposal'), 0, $_SERVER['PHP_SELF'], 'pr.ref', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('ThirdParty'), 0, $_SERVER['PHP_SELF'], 's.nom', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('User'), 0, $_SERVER['PHP_SELF'], 'u.login', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('SalarieConcerne'), 0, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, '');
print getTitleFieldOfList($langs->trans('Note'), 0, $_SERVER['PHP_SELF'], 'm.label', '', '', '', $sortfield, $sortorder, '');
if ($user->hasRight('stock', 'mouvement', 'creer')) {
	print '<th></th>';
}
print '</tr></thead>';

print '<tbody>';
if ($resql) {
	$num = $db->num_rows($resql);
	if ($num == 0) {
		$colspan = $user->hasRight('stock', 'mouvement', 'creer') ? 11 : 10;
		print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans('NoMovementsFound').'</span></td></tr>';
	}
	$total_sortie = 0;
	$total_retour = 0;
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';

		// Date
		print '<td>'.dol_print_date($db->jdate($obj->datem), 'day').'</td>';

		// Product
		print '<td>';
		print '<strong>'.dol_escape_htmltag($obj->product_ref).'</strong>';
		if ($obj->product_label) {
			print '<br><span class="opacitymedium">'.dol_trunc($obj->product_label, 40).'</span>';
		}
		print '</td>';

		// Direction badge
		$qty = (float) $obj->value;
		if ($qty < 0) {
			$total_sortie += abs($qty);
		} else {
			$total_retour += $qty;
		}
		if ($qty < 0) {
			print '<td><span class="badge badge-warning">'.$langs->trans('Sortie').'</span></td>';
		} else {
			print '<td><span class="badge badge-success">'.$langs->trans('Retour').'</span></td>';
		}

		// Qty (absolute, signed display)
		print '<td class="right" style="font-weight:bold;">';
		$sign = $qty < 0 ? '-' : '+';
		print $sign.price(abs($qty));
		print '</td>';

		// Warehouse
		print '<td>'.dol_escape_htmltag($obj->entrepot_ref ?: '—').'</td>';

		// Proposal
		if (!empty($obj->propal_ref)) {
			print '<td><a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$obj->propal_id.'">'.dol_escape_htmltag($obj->propal_ref).'</a></td>';
		} else {
			print '<td>—</td>';
		}

		// Tiers
		print '<td>'.dol_trunc($obj->soc_name, 30).'</td>';

		// User
		$user_display = trim(($obj->firstname ? $obj->firstname.' ' : '').$obj->lastname);
		if (empty($user_display)) { $user_display = $obj->user_login; }
		print '<td>'.dol_escape_htmltag($user_display).'</td>';

		// Salarié concerné
		$salarie_display = trim(($obj->salarie_firstname ? $obj->salarie_firstname.' ' : '').$obj->salarie_lastname);
		if (empty($salarie_display)) { $salarie_display = $obj->salarie_login ?: ''; }
		print '<td>'.($salarie_display ? dol_escape_htmltag($salarie_display) : '—').'</td>';

		// Label
		print '<td>'.dol_trunc($obj->label, 50).'</td>';

		// Actions
		if ($user->hasRight('stock', 'mouvement', 'creer')) {
			$deleteUrl = $_SERVER['PHP_SELF'].'?rowid='.$obj->rowid.'&action=confirm_delete&token='.newToken()
				.'&search_propal='.urlencode($search_propal).'&search_product='.urlencode($search_product)
				.'&search_datefrom='.urlencode($search_datefrom).'&search_dateto='.urlencode($search_dateto);
			print '<td class="nowrap center">';
			print '<a href="'.$deleteUrl.'" title="'.dol_escape_htmltag($langs->trans('DeleteMove')).'" onclick="return confirm(\''.dol_escape_js($langs->transnoentities('ConfirmDeleteMove')).'\');">';
			print img_picto('', 'fa-trash-alt');
			print '</a>';
			print '</td>';
		}

		print '</tr>';
	}
	$db->free($resql);
} else {
	$colspan = $user->hasRight('stock', 'mouvement', 'creer') ? 11 : 10;
	print '<tr><td colspan="'.$colspan.'">'.dol_print_error($db).'</td></tr>';
}
print '</tbody>';

// Total row
if ($num > 0) {
	$net = $total_retour - $total_sortie;
	$colspanTotal = 3; // Date + Product + Direction
	print '<tr class="liste_titre nodrag nodrop">';
	print '<td colspan="'.$colspanTotal.'" class="right" style="font-weight:bold;">'.$langs->trans('Total').'</td>';
	print '<td class="right" style="font-weight:bold;">';
	print '<span style="color:var(--colorwarning);">-'.price(abs($total_sortie)).'</span>';
	print ' / <span style="color:var(--colorsuccess);">+'.price($total_retour).'</span>';
	print ' / <span style="font-weight:bold;">'.($net > 0 ? '+' : '').price($net).'</span>';
	print '</td>';
	$colspanRest = $user->hasRight('stock', 'mouvement', 'creer') ? 6 : 5;
	print '<td colspan="'.$colspanRest.'"></td>';
	print '</tr>';
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
