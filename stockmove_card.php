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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
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
	$product_ids = GETPOST('product_id', 'array');
	$product_qtys = GETPOST('product_qty', 'array');
	$product_types = GETPOST('product_type', 'array');
	$product_labels = GETPOST('product_label_line', 'array');

	if (empty($product_ids) || count($product_ids) == 0) {
		$error++;
		$errors[] = $langs->trans('NoLinesError');
	}

	// Build clean lines array
	$lines = array();
	if (!$error && is_array($product_ids)) {
		foreach ($product_ids as $idx => $pid) {
			$pid  = (int) $pid;
			$qty  = isset($product_qtys[$idx]) ? (float) str_replace(',', '.', $product_qtys[$idx]) : 0;
			$type = isset($product_types[$idx]) ? $product_types[$idx] : 'sortie';
			$lbl  = isset($product_labels[$idx]) ? dol_sanitizeFileName($product_labels[$idx]) : '';

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
				'type'       => $type,  // 'sortie' or 'retour'
				'label'      => $lbl,
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

			// Signed qty: negative for exit, positive for return/entry
			if ($line['type'] === 'sortie') {
				$signed_qty = -abs($line['qty']);
				$mvt_type   = 1; // exit
			} else {
				$signed_qty = abs($line['qty']);
				$mvt_type   = 0; // entry / return
			}

			$line_label = $global_label;
			if (!empty($line['label'])) {
				$line_label = ($global_label ? $global_label.' — ' : '').$line['label'];
			}

			$result = $mouvement->_create(
				$user,
				$line['product_id'],
				$fk_entrepot,
				$signed_qty,
				$mvt_type,
				$line_label,
				'',            // inventory code
				$date_mouvement
			);

			if ($result > 0) {
				// Link this movement to the proposal via extrafield
				if (!empty($fk_proposal)) {
					$mouvement->id = $result;
					$mouvement->array_options['options_fk_proposal'] = $fk_proposal;
					$res2 = $mouvement->insertExtraFields();
					if ($res2 < 0) {
						// Log warning but don't abort — movement already created
						dol_syslog('DoliStockMove: insertExtraFields failed for movement '.$result, LOG_WARNING);
					}
				}
				$created++;
			} else {
				// Fetch product ref for error message
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
			// Reset form after success
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

// Show messages
if (!empty($errors)) {
	setEventMessages(implode('<br>', $errors), null, 'errors');
}
if (!empty($messages)) {
	setEventMessages(implode('<br>', $messages), null, 'mesgs');
}

// Opening form
print '<form id="dolistockmove_form" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print dol_get_fiche_head(array(), '', '', -1);

// ---- Header fields ----
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield">';

// Proposal
$allowNoProposal = getDolGlobalInt('DOLISTOCKMOVE_ALLOW_NO_PROPOSAL');
print '<tr>';
print '<td class="titlefieldcreate'.($allowNoProposal ? '' : ' fieldrequired').'">'.$langs->trans('ProposalCommerciale').'</td>';
print '<td>';

// Autocomplete proposal selector
$selected_propal_ref = '';
if (!empty($fk_proposal)) {
	$sqlp = 'SELECT ref FROM '.MAIN_DB_PREFIX.'propal WHERE rowid = '.((int) $fk_proposal);
	$rp = $db->query($sqlp);
	if ($rp && $obj = $db->fetch_object($rp)) {
		$selected_propal_ref = $obj->ref;
	}
}

print '<input type="hidden" id="fk_proposal" name="fk_proposal" value="'.dol_escape_htmltag($fk_proposal).'">';
print '<input type="text" id="fk_proposal_search" class="form-control minwidth300"';
print ' placeholder="'.$langs->trans('SearchProposal').'"';
print ' value="'.dol_escape_htmltag($selected_propal_ref).'"';
print ' autocomplete="off">';
print '<div id="propal_autocomplete_results" class="dolistockmove-autocomplete-list" style="display:none;"></div>';
print '</td></tr>';

// Warehouse
print '<tr>';
print '<td class="titlefieldcreate fieldrequired">'.$langs->trans('SelectWarehouse').'</td>';
print '<td>';
print $form->select_entrepots($fk_entrepot, 'fk_entrepot', '', 1, 0, 'minwidth200 select2bs4');
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
print '<th style="min-width:220px">'.$langs->trans('Product').'</th>';
print '<th style="width:100px;text-align:center">'.$langs->trans('CurrentStock').'</th>';
print '<th style="width:130px">'.$langs->trans('MoveType').'</th>';
print '<th style="width:90px">'.$langs->trans('Qty').'</th>';
print '<th>'.$langs->trans('LineComment').'</th>';
print '<th style="width:40px"></th>';
print '</tr>';
print '</thead>';
print '<tbody id="dolistockmove_lines_body">';

// Initial empty line
print dolistockmove_render_line(0);

print '</tbody>';
print '</table>';
print '</div>';

// Action buttons below the table
print '<div class="dolistockmove-table-actions">';
print '<button type="button" id="dolistockmove_add_line" class="button buttonaction">';
print img_picto('', 'fa-plus', 'class="paddingright"').$langs->trans('AddLine');
print '</button>';
print ' &nbsp; ';
print '<button type="button" id="dolistockmove_create_product" class="button buttonaction button-secondary">';
print img_picto('', 'fa-plus-circle', 'class="paddingright"').$langs->trans('CreateProduct');
print '</button>';
print '</div>';

print '</div>'; // dolistockmove-lines-container

print dol_get_fiche_end();

// Submit / Cancel
print '<div class="center">';
print '<input type="submit" class="button buttonaction btn-lg" name="save" value="'.dol_escape_htmltag($langs->trans('ValidateMoves')).'">';
print ' &nbsp; ';
print '<a class="button button-cancel btn-lg" href="'.dol_buildpath('/dolistockmove/dolistockmoveindex.php', 1).'">'.$langs->trans('Cancel').'</a>';
print '</div>';

print '</form>';

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

// Pass config to JS
print '<script>';
print 'var dolistockmoveConfig = {';
print '  ajaxUrl: "'.dol_buildpath('/dolistockmove/ajax/product_info.php', 1).'",';
print '  createUrl: "'.dol_buildpath('/dolistockmove/ajax/create_product.php', 1).'",';
print '  propalUrl: "'.dol_buildpath('/dolistockmove/ajax/product_info.php', 1).'",';
print '  token: "'.currentToken().'",';
print '  fk_entrepot: '.(int) $fk_entrepot.',';
print '  prefillProductId: '.(int) $prefill_product_id.',';
print '  prefillProductRef: "'.dol_escape_js($prefill_product_ref).'",';
print '  lblSortie: "'.dol_escape_js($langs->trans('Sortie')).'",';
print '  lblRetour: "'.dol_escape_js($langs->trans('Retour')).'",';
print '  lblCurrentStock: "'.dol_escape_js($langs->trans('CurrentStock')).'",';
print '};';
print '</script>';

llxFooter();
$db->close();

/**
 * Render one product line (server-side, for the initial line)
 *
 * @param  int $idx  Line index
 * @return string    HTML
 */
function dolistockmove_render_line($idx)
{
	global $langs;

	$html  = '<tr class="dolistockmove-line oddeven" data-idx="'.$idx.'">';

	// Product field (text autocomplete)
	$html .= '<td>';
	$html .= '<input type="hidden" name="product_id[]" class="dsm-product-id" value="">';
	$html .= '<input type="text" class="dsm-product-search form-control"';
	$html .= ' placeholder="'.dol_escape_htmltag($langs->trans('TypeToSearch')).'"';
	$html .= ' autocomplete="off">';
	$html .= '<div class="dolistockmove-autocomplete-list dsm-product-results" style="display:none;"></div>';
	$html .= '</td>';

	// Current stock badge
	$html .= '<td class="center"><span class="dsm-stock-badge badge">—</span></td>';

	// Type (sortie / retour)
	$html .= '<td>';
	$html .= '<select name="product_type[]" class="dsm-type flat select2 minwidth100">';
	$html .= '<option value="sortie" selected>'.$langs->trans('Sortie').'</option>';
	$html .= '<option value="retour">'.$langs->trans('Retour').'</option>';
	$html .= '</select>';
	$html .= '</td>';

	// Quantity
	$html .= '<td>';
	$html .= '<input type="number" name="product_qty[]" class="dsm-qty form-control" min="0.001" step="0.001" value="" style="width:80px">';
	$html .= '</td>';

	// Line comment
	$html .= '<td>';
	$html .= '<input type="text" name="product_label_line[]" class="form-control" placeholder="'.$langs->trans('OptionalComment').'">';
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
