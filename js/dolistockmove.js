/* global dolistockmoveConfig */
/* eslint-env browser, jquery */

/**
 * DoliStockMove — JavaScript
 * Handles:
 *  - Proposal autocomplete (header field)
 *  - Product search autocomplete per line
 *  - Stock badge update after product selection
 *  - Dynamic line add/remove
 *  - Quick product creation modal
 *  - Warehouse change → refresh all stock badges
 *  - Basic form validation before submit
 */

(function ($) {
	'use strict';

	// Config injected server-side (see stockmove_card.php)
	var cfg = window.dolistockmoveConfig || {};

	var lineIdx = 1; // counter for new lines (line 0 already in DOM)

	// ================================================================
	// Utilities
	// ================================================================

	/**
	 * Get the currently selected warehouse ID from the select field.
	 */
	function getWarehouseId() {
		return parseInt($('#fk_entrepot').val()) || cfg.fk_entrepot || 0;
	}

	/**
	 * Render a stock badge with colour class based on quantity.
	 */
	function renderStockBadge(qty) {
		var cls = 'stock-ok';
		if (qty <= 0)  { cls = 'stock-zero'; }
		else if (qty < 5) { cls = 'stock-low'; }
		return '<span class="dsm-stock-badge ' + cls + '">' + parseFloat(qty).toFixed(2) + '</span>';
	}

	/**
	 * AJAX helper — returns a jQuery Deferred.
	 */
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

	/**
	 * Position a fixed dropdown below an input field.
	 * Uses fixed positioning to escape table overflow:hidden constraints.
	 */
	function positionDropdown($input, $dropdown) {
		var offset = $input.offset();
		$dropdown.css({
			top:   (offset.top + $input.outerHeight()) + 'px',
			left:  offset.left + 'px',
			width: Math.max($input.outerWidth(), 280) + 'px',
		});
	}

	// ================================================================
	// Proposal autocomplete
	// ================================================================
	function initProposalAutocomplete() {
		var $input   = $('#fk_proposal_search');
		var $hidden  = $('#fk_proposal');
		var $results = $('#propal_autocomplete_results');
		var timer;

		$input.on('input', function () {
			clearTimeout(timer);
			var term = $(this).val().trim();
			$hidden.val('');
			if (term.length < 2) { $results.hide().empty(); return; }

			timer = setTimeout(function () {
				ajaxGet(cfg.ajaxUrl, { action: 'propal', term: term })
					.done(function (data) {
						$results.empty();
						if (!data || data.length === 0) {
							$results.hide();
							return;
						}
						$.each(data, function (i, item) {
							var $item = $('<div class="dsm-ac-item">')
								.html('<span class="dsm-ac-ref">' + escHtml(item.ref) + '</span>'
									+ '<span class="dsm-ac-label">' + escHtml(item.label) + '</span>');
							$item.on('click', function () {
								$input.val(item.ref);
								$hidden.val(item.id);
								$results.hide().empty();
							});
							$results.append($item);
						});					positionDropdown($input, $results);						$results.show();
					});
			}, 280);
		});

		$(document).on('click', function (e) {
			if (!$(e.target).closest('#fk_proposal_search, #propal_autocomplete_results').length) {
				$results.hide();
			}
		});
	}

	// ================================================================
	// Product autocomplete (per line)
	// ================================================================
	function initProductAutocomplete($row) {
		var $input   = $row.find('.dsm-product-search');
		var $hidden  = $row.find('.dsm-product-id');
		var $results = $row.find('.dsm-product-results');
		var $badge   = $row.find('.dsm-stock-badge');
		var timer;

		$input.on('input', function () {
			clearTimeout(timer);
			var term = $(this).val().trim();
			$hidden.val('');
			$badge.removeClass('stock-ok stock-low stock-zero').text('—');
			if (term.length < 2) { $results.hide().empty(); return; }

			var wh = getWarehouseId();
			timer = setTimeout(function () {
				ajaxGet(cfg.ajaxUrl, { action: 'search', term: term, fk_entrepot: wh })
					.done(function (data) {
						$results.empty();
						if (!data || data.length === 0) { $results.hide(); return; }
						$.each(data, function (i, item) {
							var $item = $('<div class="dsm-ac-item">').html(
								'<span class="dsm-ac-ref">' + escHtml(item.ref) + '</span>'
								+ '<span class="dsm-ac-label">' + escHtml(item.label) + '</span>'
							+ '<span class="dsm-ac-stock">Stock : ' + parseFloat(item.stock).toFixed(2) + '</span>'
						);
						$item.on('click', function () {
							$input.val(item.value);
							$hidden.val(item.id);
							$results.hide().empty();
							updateStockBadge($row, item.id);
						});
						$results.append($item);
					});
					positionDropdown($input, $results);
						$results.show();
					});
			}, 280);
		});

		$(document).on('click', function (e) {
			if (!$(e.target).closest($row).length) {
				$results.hide();
			}
		});
	}

	function updateStockBadge($row, productId) {
		var $badge = $row.find('.dsm-stock-badge');
		var wh = getWarehouseId();
		if (!productId) { $badge.removeClass('stock-ok stock-low stock-zero').text('—'); return; }
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
				var pid  = parseInt($row.find('.dsm-product-id').val());
				if (pid > 0) { updateStockBadge($row, pid); }
			});
		});
	}

	// ================================================================
	// Build an HTML line (clone of the first line structure)
	// ================================================================
	function buildLine(idx) {
		var sortieLabel = escHtml(cfg.lblSortie  || 'Sortie');
		var retourLabel = escHtml(cfg.lblRetour || 'Retour');
		var searchLabel = escHtml(cfg.lblSearchProduct || 'Rechercher un produit...');

		return '<tr class="dolistockmove-line oddeven" data-idx="' + idx + '">'
			+ '<td style="position:relative">'
			+   '<input type="hidden" name="product_id[]" class="dsm-product-id" value="">'
			+   '<input type="text" class="dsm-product-search form-control" placeholder="' + searchLabel + '" autocomplete="off">'
			+   '<div class="dolistockmove-autocomplete-list dsm-product-results" style="display:none;"></div>'
			+ '</td>'
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
			+ '<td>'
			+   '<input type="text" name="product_label_line[]" class="form-control" placeholder="">'
			+ '</td>'
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
			initProductAutocomplete($row);
			$row.find('.dsm-product-search').trigger('focus');
		});
	}

	// ================================================================
	// Remove line (event delegation)
	// ================================================================
	function initRemoveLine() {
		$('#dolistockmove_lines_body').on('click', '.dsm-remove-line', function () {
			var $rows = $('#dolistockmove_lines_body .dolistockmove-line');
			if ($rows.length <= 1) {
				// Reset the last line instead of removing it
				var $row = $rows.first();
				$row.find('.dsm-product-id').val('');
				$row.find('.dsm-product-search').val('');
				$row.find('.dsm-stock-badge').removeClass('stock-ok stock-low stock-zero').text('—');
				$row.find('.dsm-qty').val('');
				$row.find('.dsm-product-search').trigger('focus');
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

			// Check at least one line with a product + qty
			var hasValidLine = false;
			$('#dolistockmove_lines_body .dolistockmove-line').each(function () {
				var pid = parseInt($(this).find('.dsm-product-id').val());
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

		// Remember which row triggered the modal
		var $targetRow = null;

		$btnOpen.on('click', function () {
			// Use the last focused / last row
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
						// Fill the target row
						if ($targetRow && $targetRow.length) {
							$targetRow.find('.dsm-product-id').val(data.id);
							$targetRow.find('.dsm-product-search').val(data.value || data.ref);
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
	// Init on DOM ready
	// ================================================================
	$(function () {
		// Only init on the card page (config object exists)
		if (!window.dolistockmoveConfig) { return; }

		// Proposal autocomplete
		if ($('#fk_proposal_search').length) {
			initProposalAutocomplete();
		}

		// Init product autocomplete on existing lines
		$('#dolistockmove_lines_body .dolistockmove-line').each(function () {
			initProductAutocomplete($(this));
		});

		// Pre-fill first line if coming from a product card
		if (cfg.prefillProductId > 0 && cfg.prefillProductRef) {
			var $firstRow = $('#dolistockmove_lines_body .dolistockmove-line').first();
			if ($firstRow.length) {
				$firstRow.find('.dsm-product-id').val(cfg.prefillProductId);
				$firstRow.find('.dsm-product-search').val(cfg.prefillProductRef);
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
