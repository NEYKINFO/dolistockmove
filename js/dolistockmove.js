/* global dolistockmoveConfig */
/* eslint-env browser, jquery */

/**
 * DoliStockMove — JavaScript
 * Handles:
 *  - Product <select> change → update stock badge
 *  - Dynamic line add/remove (cloning <select> options)
 *  - Warehouse change → refresh all stock badges
 *  - Quick product creation modal
 *  - Basic form validation before submit
 */

(function ($) {
	'use strict';

	var cfg = window.dolistockmoveConfig || {};
	var lineIdx = 1;

	// ================================================================
	// Utilities
	// ================================================================

	function getWarehouseId() {
		return parseInt($('#fk_entrepot').val()) || cfg.fk_entrepot || 0;
	}

	function renderStockBadge(qty) {
		var cls = 'stock-ok';
		if (qty <= 0)  { cls = 'stock-zero'; }
		else if (qty < 5) { cls = 'stock-low'; }
		return '<span class="dsm-stock-badge ' + cls + '">' + parseFloat(qty).toFixed(2) + '</span>';
	}

	function ajaxGet(url, data) {
		return $.ajax({
			url: url,
			type: 'GET',
			data: data,
			dataType: 'json',
		});
	}

	function ajaxPost(url, data) {
		return $.ajax({
			url: url,
			type: 'POST',
			data: data,
			dataType: 'json',
		});
	}

	// ================================================================
	// Product select change → update stock badge
	// ================================================================
	function initProductSelect($row) {
		$row.find('.dsm-product-select').on('change', function () {
			var productId = parseInt($(this).val()) || 0;
			updateStockBadge($row, productId);
		});
	}

	function updateStockBadge($row, productId) {
		var $badge = $row.find('.dsm-stock-badge');
		var wh = getWarehouseId();
		if (!productId || productId <= 0) {
			$badge.removeClass('stock-ok stock-low stock-zero').text('—');
			return;
		}
		ajaxGet(cfg.ajaxUrl, { action: 'stock', product_id: productId, fk_entrepot: wh })
			.done(function (data) {
				if (data && typeof data.stock !== 'undefined') {
					var qty = parseFloat(data.stock);
					var cls = qty > 0 ? (qty < 5 ? 'stock-low' : 'stock-ok') : 'stock-zero';
					$badge.removeClass('stock-ok stock-low stock-zero')
						.addClass(cls)
						.text(qty.toFixed(2));
				}
			});
	}

	// ================================================================
	// Warehouse change → refresh all stock badges
	// ================================================================
	function initWarehouseChange() {
		$('#fk_entrepot').on('change', function () {
			$('#dolistockmove_lines_body .dolistockmove-line').each(function () {
				var $row = $(this);
				var pid = parseInt($row.find('.dsm-product-select').val());
				if (pid > 0) { updateStockBadge($row, pid); }
			});
		});
	}

	// ================================================================
	// Build an HTML line by cloning the first line's product <select>
	// ================================================================
	function buildLine(idx) {
		var sortieLabel = escHtml(cfg.lblSortie || 'Sortie');
		var retourLabel = escHtml(cfg.lblRetour || 'Retour');

		return '<tr class="dolistockmove-line oddeven" data-idx="' + idx + '">'
			+ '<td></td>'
			+ '<td class="center"><span class="dsm-stock-badge">—</span></td>'
			+ '<td>'
			+   '<select name="product_type[]" class="dsm-type flat minwidth100">'
			+     '<option value="sortie">' + sortieLabel + '</option>'
			+     '<option value="retour">' + retourLabel + '</option>'
			+   '</select>'
			+ '</td>'
			+ '<td>'
			+   '<input type="number" name="product_qty[]" class="dsm-qty form-control" min="0.001" step="0.001" value="" style="width:80px">'
			+ '</td>'
			+ '<td></td>'
			+ '<td class="center">'
			+   '<button type="button" class="dsm-remove-line" title="Supprimer"><i class="fa fa-trash-alt"></i></button>'
			+ '</td>'
			+ '</tr>';
	}

	// ================================================================
	// Add line
	// ================================================================
	function initAddLine() {
		$('#dolistockmove_add_line').on('click', function () {
			var html = buildLine(lineIdx++);
			var $row = $(html);
			$('#dolistockmove_lines_body').append($row);

			// Clone the product <select> from the first line
			var $firstProductSelect = $('#dolistockmove_lines_body .dolistockmove-line').first().find('.dsm-product-select');
			var $clonedProductSelect = $firstProductSelect.clone();
			$clonedProductSelect.val(0);
			$row.find('td').eq(0).append($clonedProductSelect);

			// Clone the salarie <select> from the first line
			var $firstSalarieSelect = $('#dolistockmove_lines_body .dolistockmove-line').first().find('.dsm-salarie-select');
			var $clonedSalarieSelect = $firstSalarieSelect.clone();
			$clonedSalarieSelect.val(0);
			$row.find('td').eq(4).append($clonedSalarieSelect);

			initSelect2($clonedProductSelect);
			initSelect2($clonedSalarieSelect);
			initSelect2($row.find('.dsm-type'));
			initProductSelect($row);
			$row.find('.dsm-product-select').trigger('focus');
		});
	}

	// ================================================================
	// Remove line (event delegation)
	// ================================================================
	function initRemoveLine() {
		$('#dolistockmove_lines_body').on('click', '.dsm-remove-line', function () {
			var $rows = $('#dolistockmove_lines_body .dolistockmove-line');
			if ($rows.length <= 1) {
				var $row = $rows.first();
				$row.find('.dsm-product-select').val(0);
				$row.find('.dsm-stock-badge').removeClass('stock-ok stock-low stock-zero').text('—');
				$row.find('.dsm-qty').val('');
			} else {
				$(this).closest('.dolistockmove-line').remove();
			}
		});
	}

	// ================================================================
	// Form validation
	// ================================================================
	function initFormValidation() {
		$('#dolistockmove_form').on('submit', function (e) {
			var hasError = false;
			var messages = [];

			var hasValidLine = false;
			$('#dolistockmove_lines_body .dolistockmove-line').each(function () {
				var pid = parseInt($(this).find('.dsm-product-select').val());
				var qty = parseFloat($(this).find('.dsm-qty').val());
				if (pid > 0 && qty > 0) {
					hasValidLine = true;
				}
				if (pid > 0 && (isNaN(qty) || qty <= 0)) {
					messages.push(cfg.lblNoQtyError || 'Quantity must be > 0');
					hasError = true;
				}
			});

			if (!hasValidLine && !hasError) {
				messages.push(cfg.lblNoLinesError || 'At least one valid product line is required.');
				hasError = true;
			}

			if (hasError) {
				e.preventDefault();
				alert(messages.join('\n'));
			}
		});
	}

	// ================================================================
	// Quick product creation modal
	// ================================================================
	function initModal() {
		var $overlay = $('#dolistockmove_modal_overlay');
		var $btnOpen = $('#dolistockmove_create_product');
		var $btnClose = $('#dolistockmove_modal_close, #dolistockmove_modal_cancel');
		var $btnCreate = $('#dolistockmove_modal_create');
		var $errorDiv  = $('#new_product_error');

		var $targetRow = null;

		$btnOpen.on('click', function () {
			$targetRow = $('#dolistockmove_lines_body .dolistockmove-line').last();
			$('#new_product_ref').val('');
			$('#new_product_label').val('');
			$('#new_product_desc').val('');
			$errorDiv.hide().text('');
			$overlay.show();
			$('#new_product_ref').trigger('focus');
		});

		$btnClose.on('click', function () {
			$overlay.hide();
		});

		$overlay.on('click', function (e) {
			if ($(e.target).is($overlay)) { $overlay.hide(); }
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') { $overlay.hide(); }
		});

		$btnCreate.on('click', function () {
			$errorDiv.hide().text('');
			var ref   = $.trim($('#new_product_ref').val());
			var label = $.trim($('#new_product_label').val());
			var desc  = $.trim($('#new_product_desc').val());

			if (!ref) { $errorDiv.text('La référence est obligatoire.').show(); return; }
			if (!label) { $errorDiv.text('Le libellé est obligatoire.').show(); return; }

			$btnCreate.prop('disabled', true).text('...');

			ajaxPost(cfg.createUrl, {
				action: 'create',
				token: cfg.token,
				ref: ref,
				label: label,
				description: desc,
			})
				.done(function (data) {
					if (data && data.id) {
						if ($targetRow && $targetRow.length) {
							var $sel = $targetRow.find('.dsm-product-select');
							$sel.append('<option value="' + data.id + '">' + escHtml(data.ref + (data.label ? ' — ' + data.label : '')) + '</option>');
							$sel.val(data.id);
							updateStockBadge($targetRow, data.id);
						}
						$overlay.hide();
					} else {
						var msg = (data && data.error) ? data.error : 'Erreur inconnue';
						$errorDiv.text(msg).show();
					}
				})
				.fail(function () {
					$errorDiv.text('Erreur réseau.').show();
				})
				.always(function () {
					$btnCreate.prop('disabled', false).text(cfg.lblCreateAndSelect || 'Créer et sélectionner');
				});
		});
	}

	// ================================================================
	// HTML escape helper
	// ================================================================
	function escHtml(str) {
		if (!str) { return ''; }
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	// ================================================================
	// Select2 — flexible search (substring match, no minimum)
	// ================================================================
	function initSelect2($sel) {
		if (!$.fn.select2) { return; }
		// Destroy any existing select2 instance (Dolibarr auto-init may have bad settings)
		try { $sel.select2('destroy'); } catch (e) { /* ignore */ }
		$sel.select2({
			width: '100%',
			minimumInputLength: 0,
			minimumResultsForSearch: 0,
			closeOnSelect: true,
			dropdownAutoWidth: true,
			matcher: function (params, data) {
				if ($.trim(params.term) === '') { return data; }
				if (!data.text) { return null; }
				var term = params.term.toLowerCase();
				var text = data.text.toLowerCase();
				if (text.indexOf(term) > -1) {
					var modified = $.extend({}, data, true);
					return modified;
				}
				return null;
			},
		});
	}

	function initAllSelect2() {
		$('#fk_proposal').each(function () { initSelect2($(this)); });
		$('#fk_entrepot').each(function () { initSelect2($(this)); });
		$('.dsm-product-select').each(function () { initSelect2($(this)); });
		$('.dsm-salarie-select').each(function () { initSelect2($(this)); });
		$('.dsm-type').each(function () { initSelect2($(this)); });
	}

	// ================================================================
	// Init on DOM ready
	// ================================================================
	$(function () {
		if (!window.dolistockmoveConfig) { return; }

		// Init select2 with flexible search on all dropdowns
		initAllSelect2();

		// Init product select change handlers on existing lines
		$('#dolistockmove_lines_body .dolistockmove-line').each(function () {
			initProductSelect($(this));
		});

		// Pre-fill first line if coming from a product card
		if (cfg.prefillProductId > 0) {
			var $firstRow = $('#dolistockmove_lines_body .dolistockmove-line').first();
			if ($firstRow.length) {
				$firstRow.find('.dsm-product-select').val(cfg.prefillProductId);
				updateStockBadge($firstRow, cfg.prefillProductId);
			}
		}

		initWarehouseChange();
		initAddLine();
		initRemoveLine();
		initFormValidation();
		initModal();
	});

}(jQuery));
