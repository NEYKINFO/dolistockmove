<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       stockmove_card.php
 * \ingroup    dolistockmove
 * \brief      Form to enter bulk stock movements linked to a proposal
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"].'/main.inc.php';
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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/dolistockmove/lib/dolistockmove.lib.php');

$langs->loadLangs(array('dolistockmove@dolistockmove', 'stocks', 'products', 'propal'));

// Permissions
if (!isModEnabled('dolistockmove')) { accessforbidden(); }
if (!$user->hasRight('stock', 'mouvement', 'creer')) { accessforbidden(); }

$action           = GETPOST('action', 'aZ09');
$fk_proposal      = GETPOSTINT('fk_proposal');
$fk_entrepot      = GETPOSTINT('fk_entrepot');
$datem            = GETPOST('datem', 'alpha');
$global_label     = GETPOST('global_label', 'alphanohtml');
$prefill_product_id  = GETPOSTINT('prefill_product_id');
$prefill_product_ref = GETPOST('prefill_product_ref', 'alphanohtml');

// Default warehouse from config
if (empty($fk_entrepot)) {
	$fk_entrepot = getDolGlobalInt('DOLISTOCKMOVE_DEFAULT_WAREHOUSE');
}

$form = new Form($db);

$errors   = array();
$messages = array();

// ─── Pre-load data for dropdowns ───────────────────────────────────────────

// All proposals (ref + third party name)
$propalOptions = array();
$sqlPropals  = "SELECT pr.rowid, pr.ref, s.nom as soc_name";
$sqlPropals .= " FROM ".MAIN_DB_PREFIX."propal pr";
$sqlPropals .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = pr.fk_soc";
$sqlPropals .= " WHERE pr.entity IN (".getEntity('propal').")";
$sqlPropals .= " ORDER BY pr.ref DESC";
$resPropals = $db->query($sqlPropals);
if ($resPropals) {
	while ($obj = $db->fetch_object($resPropals)) {
		$label = $obj->ref;
		if (!empty($obj->soc_name)) {
			$label .= ' — '.$obj->soc_name;
		}
		$propalOptions[(int) $obj->rowid] = $label;
	}
	$db->free($resPropals);
}

// All warehouses
$warehouseOptions = dolistockmoveGetWarehouses($db, 0, true);

// All products (ref + label)
$productOptions = array();
$sqlProducts  = "SELECT p.rowid, p.ref, p.label";
$sqlProducts .= " FROM ".MAIN_DB_PREFIX."product p";
$sqlProducts .= " WHERE p.entity IN (".getEntity('product').")";
$sqlProducts .= " AND p.fk_product_type IN (0, 1)";
$sqlProducts .= " ORDER BY p.ref ASC";
$resProducts = $db->query($sqlProducts);
if ($resProducts) {
	while ($obj = $db->fetch_object($resProducts)) {
		$label = $obj->ref;
		if (!empty($obj->label)) {
			$label .= ' — '.dol_trunc($obj->label, 50);
		}
		$productOptions[(int) $obj->rowid] = $label;
	}
	$db->free($resProducts);
}

// All users (for "Salarié concerné" dropdown)
$userOptions = array();
$sqlUsers  = "SELECT u.rowid, u.lastname, u.firstname, u.login";
$sqlUsers .= " FROM ".MAIN_DB_PREFIX."user u";
$sqlUsers .= " WHERE u.statut = 1";
$sqlUsers .= " ORDER BY u.lastname, u.firstname ASC";
$resUsers = $db->query($sqlUsers);
if ($resUsers) {
	while ($obj = $db->fetch_object($resUsers)) {
		$name = trim(($obj->firstname ? $obj->firstname.' ' : '').$obj->lastname);
		if (empty($name)) { $name = $obj->login; }
		$userOptions[(int) $obj->rowid] = $name;
	}
	$db->free($resUsers);
}

/*
 * Actions
 */
if ($action === 'save' && !GETPOST('cancel')) {
	$error = 0;

	// Validate token
	if (!newToken() && !GETPOST('token', 'alpha')) {
		$error++;
		$errors[] = $langs->trans('ErrorBadToken');
	}

	// Validate proposal (required unless AllowNoProposal)
	if (empty($fk_proposal) && !getDolGlobalInt('DOLISTOCKMOVE_ALLOW_NO_PROPOSAL')) {
		$error++;
		$errors[] = $langs->trans('SelectProposal');
	}

	// Validate warehouse
	if (empty($fk_entrepot)) {
		$error++;
		$errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('SelectWarehouse'));
	}

	// Parse product lines
	$product_ids    = GETPOST('product_id', 'array');
	$product_qtys   = GETPOST('product_qty', 'array');
	$product_types  = GETPOST('product_type', 'array');
	$product_salaries = GETPOST('product_salarie', 'array');

	if (empty($product_ids) || count($product_ids) == 0) {
		$error++;
		$errors[] = $langs->trans('NoLinesError');
	}

	// Build clean lines array
	$lines = array();
	if (!$error && is_array($product_ids)) {
		foreach ($product_ids as $idx => $pid) {
			$pid     = (int) $pid;
			$qty     = isset($product_qtys[$idx]) ? (float) str_replace(',', '.', $product_qtys[$idx]) : 0;
			$type    = isset($product_types[$idx]) ? $product_types[$idx] : 'sortie';
			$salarie = isset($product_salaries[$idx]) ? (int) $product_salaries[$idx] : 0;

			if ($pid <= 0) {
				$error++;
				$errors[] = $langs->trans('NoProductError');
				break;
			}
			if ($qty <= 0) {
				$error++;
				$errors[] = $langs->trans('NoQtyError');
				break;
			}

			$lines[] = array(
				'product_id' => $pid,
				'qty'        => $qty,
				'type'       => $type,
				'salarie'    => $salarie,
			);
		}
	}

	// Parse date
	$date_mouvement = '';
	if (!empty($datem)) {
		$date_mouvement = dol_stringtotime($datem);
	}
	if (empty($date_mouvement)) {
		$date_mouvement = dol_now();
	}

	if (!$error && count($lines) > 0) {
		$db->begin();

		$created = 0;
		foreach ($lines as $line) {
			$mouvement = new MouvementStock($db);

			if ($line['type'] === 'sortie') {
				$signed_qty = -abs($line['qty']);
				$mvt_type   = 1;
			} else {
				$signed_qty = abs($line['qty']);
				$mvt_type   = 0;
			}

			$line_label = $global_label;

			$result = $mouvement->_create(
				$user,
				$line['product_id'],
				$fk_entrepot,
				$signed_qty,
				$mvt_type,
				0,
				$line_label,
				'',
				$date_mouvement
			);

			if ($result > 0) {
				// Save devis + salarie via direct SQL (bypasses link-type validation)
				$sqlDel = "DELETE FROM ".MAIN_DB_PREFIX."stock_mouvement_extrafields WHERE fk_object = ".(int) $result;
				$db->query($sqlDel);

				$devisVal  = !empty($fk_proposal) ? (int) $fk_proposal : 'NULL';
				$salarieVal = $line['salarie'] > 0 ? (int) $line['salarie'] : 'NULL';

				$sqlEf  = "INSERT INTO ".MAIN_DB_PREFIX."stock_mouvement_extrafields (fk_object, devis, salarie)";
				$sqlEf .= " VALUES (".(int) $result.", ".$devisVal.", ".$salarieVal.")";
				if (!$db->query($sqlEf)) {
					dol_syslog('DoliStockMove: extrafields insert failed for movement '.$result.': '.$db->lasterror(), LOG_WARNING);
				}
				$created++;
			} else {
				$product = new Product($db);
				$product->fetch($line['product_id']);
				$errors[] = $langs->trans('MovementError', $product->ref);
				$error++;
				break;
			}
		}

		if ($error) {
			$db->rollback();
		} else {
			$db->commit();
			$messages[] = $langs->trans('MovementsCreated');
			$fk_proposal  = 0;
			$global_label = '';
			$action       = '';
		}
	}
}

/*
 * View
 */
$title = $langs->trans('StockMoveForm');
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'mod-dolistockmove page-stockmove_card');

print load_fiche_titre(img_picto('', 'fa-dolly', 'class="paddingright"').$title, '', '');

if (!empty($errors)) {
	setEventMessages(implode('<br>', $errors), null, 'errors');
}
if (!empty($messages)) {
	setEventMessages(implode('<br>', $messages), null, 'mesgs');
}

print '<form id="dolistockmove_form" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print dol_get_fiche_head(array(), '', '', -1);

// ---- Header fields ----
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield">';

// Proposal — <select> dropdown with ref + third party name
$allowNoProposal = getDolGlobalInt('DOLISTOCKMOVE_ALLOW_NO_PROPOSAL');
print '<tr>';
print '<td class="titlefieldcreate'.($allowNoProposal ? '' : ' fieldrequired').'">'.$langs->trans('ProposalCommerciale').'</td>';
print '<td>';
print '<select id="fk_proposal" name="fk_proposal" class="flat minwidth400">';
if ($allowNoProposal) {
	print '<option value="0">—</option>';
}
foreach ($propalOptions as $pid => $plabel) {
	print '<option value="'.$pid.'"'.($pid == $fk_proposal ? ' selected' : '').'>';
	print dol_escape_htmltag($plabel);
	print '</option>';
}
print '</select>';
print '</td></tr>';

// Warehouse — <select> dropdown
print '<tr>';
print '<td class="titlefieldcreate fieldrequired">'.$langs->trans('SelectWarehouse').'</td>';
print '<td>';
print '<select id="fk_entrepot" name="fk_entrepot" class="flat minwidth300">';
print '<option value="0">—</option>';
foreach ($warehouseOptions as $wid => $wlabel) {
	if ($wid === 0) { continue; }
	print '<option value="'.$wid.'"'.($wid == $fk_entrepot ? ' selected' : '').'>';
	print dol_escape_htmltag($wlabel);
	print '</option>';
}
print '</select>';
print '</td></tr>';

// Date
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('MoveDate').'</td>';
print '<td>';
print '<input type="date" name="datem" class="form-control" value="'.dol_escape_htmltag($datem ?: dol_print_date(dol_now(), '%Y-%m-%d')).'">';
print '</td></tr>';

// Global comment
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('GlobalComment').'</td>';
print '<td>';
print '<input type="text" name="global_label" class="form-control minwidth300" value="'.dol_escape_htmltag($global_label).'" placeholder="'.$langs->trans('OptionalComment').'">';
print '</td></tr>';

print '</table>';
print '</div>';

print '<br>';

// ---- Product lines ----
print '<div class="dolistockmove-lines-container">';
print '<h3>'.img_picto('', 'fa-boxes', 'class="paddingright"').$langs->trans('ProductLines').'</h3>';

print '<div class="table-responsive">';
print '<table class="noborder centpercent dolistockmove-lines-table" id="dolistockmove_lines">';
print '<thead>';
print '<tr class="liste_titre">';
print '<th style="min-width:300px">'.$langs->trans('Product').'</th>';
print '<th style="width:100px;text-align:center">'.$langs->trans('CurrentStock').'</th>';
print '<th style="width:130px">'.$langs->trans('MoveType').'</th>';
print '<th style="width:90px">'.$langs->trans('Qty').'</th>';
print '<th>'.$langs->trans('SalarieConcerne').'</th>';
print '<th style="width:40px"></th>';
print '</tr>';
print '</thead>';
print '<tbody id="dolistockmove_lines_body">';

// Initial line
print dolistockmove_render_line(0, $productOptions, $userOptions, $prefill_product_id);

print '</tbody>';
print '</table>';
print '</div>';

// Action buttons
print '<div class="dolistockmove-table-actions">';
print '<button type="button" id="dolistockmove_add_line" class="button buttonaction">';
print img_picto('', 'fa-plus', 'class="paddingright"').$langs->trans('AddLine');
print '</button>';
print ' &nbsp; ';
print '<button type="button" id="dolistockmove_create_product" class="button buttonaction button-secondary">';
print img_picto('', 'fa-plus-circle', 'class="paddingright"').$langs->trans('CreateProduct');
print '</button>';
print '</div>';

print '</div>';

print dol_get_fiche_end();

// Submit / Cancel
print '<div class="center">';
print '<input type="submit" class="button buttonaction btn-lg" name="save" value="'.dol_escape_htmltag($langs->trans('ValidateMoves')).'">';
print ' &nbsp; ';
print '<a class="button button-cancel btn-lg" href="'.dol_buildpath('/dolistockmove/dolistockmoveindex.php', 1).'">'.$langs->trans('Cancel').'</a>';
print '</div>';

print '</form>';

// Link to movement list
print '<div class="center" style="margin-top:15px;">';
print '<a class="button buttonaction" href="'.dol_buildpath('/dolistockmove/stockmove_list.php', 1).'">';
print img_picto('', 'fa-list', 'class="paddingright"').$langs->trans('StockMoveList');
print '</a>';
print '</div>';

// ================================================================
// Quick product creation modal
// ================================================================
print '
<div id="dolistockmove_modal_overlay" class="dolistockmove-modal-overlay" style="display:none;">
  <div class="dolistockmove-modal">
    <div class="dolistockmove-modal-header">
      <h4>'.dol_escape_htmltag($langs->trans('QuickCreateProduct')).'</h4>
      <button type="button" id="dolistockmove_modal_close" class="dolistockmove-modal-close">&times;</button>
    </div>
    <div class="dolistockmove-modal-body">
      <table class="border centpercent tableforfield">
        <tr>
          <td class="titlefieldcreate fieldrequired">'.dol_escape_htmltag($langs->trans('ProductRef')).'</td>
          <td><input type="text" id="new_product_ref" class="form-control minwidth200" placeholder="REF001"></td>
        </tr>
        <tr>
          <td class="titlefieldcreate fieldrequired">'.dol_escape_htmltag($langs->trans('ProductLabel')).'</td>
          <td><input type="text" id="new_product_label" class="form-control minwidth200" placeholder="Libellé produit"></td>
        </tr>
        <tr>
          <td class="titlefieldcreate">'.dol_escape_htmltag($langs->trans('ProductDesc')).'</td>
          <td><textarea id="new_product_desc" class="form-control" rows="2" style="width:100%"></textarea></td>
        </tr>
      </table>
      <div id="new_product_error" class="error" style="display:none;margin-top:8px;"></div>
    </div>
    <div class="dolistockmove-modal-footer">
      <button type="button" id="dolistockmove_modal_create" class="button buttonaction">'.dol_escape_htmltag($langs->trans('CreateAndSelect')).'</button>
      <button type="button" id="dolistockmove_modal_cancel" class="button button-cancel">'.dol_escape_htmltag($langs->trans('Cancel')).'</button>
    </div>
  </div>
</div>';

// Pass config + product options HTML to JS
$productOptionsJson = json_encode($productOptions, JSON_UNESCAPED_UNICODE);
print '<script>';
print 'var dolistockmoveConfig = {';
print '  ajaxUrl: "'.dol_buildpath('/dolistockmove/ajax/product_info.php', 1).'",';
print '  createUrl: "'.dol_buildpath('/dolistockmove/ajax/create_product.php', 1).'",';
print '  token: "'.currentToken().'",';
print '  fk_entrepot: '.(int) $fk_entrepot.',';
print '  prefillProductId: '.(int) $prefill_product_id.',';
print '  lblSortie: "'.dol_escape_js($langs->trans('Sortie')).'",';
print '  lblRetour: "'.dol_escape_js($langs->trans('Retour')).'",';
print '  lblCurrentStock: "'.dol_escape_js($langs->trans('CurrentStock')).'",';
print '  productOptions: '.$productOptionsJson.',';
print '};';
print '</script>';

llxFooter();
$db->close();

/**
 * Render one product line with <select> dropdowns
 *
 * @param  int     $idx             Line index
 * @param  array   $productOptions  [id => label, ...]
 * @param  array   $userOptions     [id => name, ...]
 * @param  int     $selectedId      Pre-selected product ID (0 = none)
 * @return string  HTML
 */
function dolistockmove_render_line($idx, $productOptions = array(), $userOptions = array(), $selectedId = 0)
{
	global $langs;

	$html  = '<tr class="dolistockmove-line oddeven" data-idx="'.$idx.'">';

	// Product — <select> dropdown
	$html .= '<td>';
	$html .= '<select name="product_id[]" class="dsm-product-select flat minwidth300">';
	$html .= '<option value="0">—</option>';
	foreach ($productOptions as $pid => $plabel) {
		$html .= '<option value="'.$pid.'"'.($pid == $selectedId ? ' selected' : '').'>';
		$html .= dol_escape_htmltag($plabel);
		$html .= '</option>';
	}
	$html .= '</select>';
	$html .= '</td>';

	// Current stock badge
	$html .= '<td class="center"><span class="dsm-stock-badge badge">—</span></td>';

	// Type (sortie / retour)
	$html .= '<td>';
	$html .= '<select name="product_type[]" class="dsm-type flat minwidth100">';
	$html .= '<option value="sortie" selected>'.$langs->trans('Sortie').'</option>';
	$html .= '<option value="retour">'.$langs->trans('Retour').'</option>';
	$html .= '</select>';
	$html .= '</td>';

	// Quantity
	$html .= '<td>';
	$html .= '<input type="number" name="product_qty[]" class="dsm-qty form-control" min="0.001" step="0.001" value="" style="width:80px">';
	$html .= '</td>';

	// Salarié concerné — user <select> dropdown
	$html .= '<td>';
	$html .= '<select name="product_salarie[]" class="dsm-salarie-select flat minwidth200">';
	$html .= '<option value="0">—</option>';
	foreach ($userOptions as $uid => $uname) {
		$html .= '<option value="'.$uid.'">'.dol_escape_htmltag($uname).'</option>';
	}
	$html .= '</select>';
	$html .= '</td>';

	// Remove button
	$html .= '<td class="center">';
	$html .= '<button type="button" class="dsm-remove-line btn btn-sm" title="'.$langs->trans('Delete').'">';
	$html .= img_picto('', 'fa-trash-alt');
	$html .= '</button>';
	$html .= '</td>';

	$html .= '</tr>';

	return $html;
}
