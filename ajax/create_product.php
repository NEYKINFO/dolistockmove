<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       ajax/create_product.php
 * \ingroup    dolistockmove
 * \brief      AJAX endpoint — quick product creation (ref + label + description)
 *
 * POST ?action=create
 * Body: token, ref, label, description
 * Returns: JSON {id, ref, label} or {error: "..."}
 */

if (!defined('NOTOKENRENEWAL')) { define('NOTOKENRENEWAL', '1'); }
if (!defined('NOREQUIREMENU'))  { define('NOREQUIREMENU', '1'); }
if (!defined('NOREQUIREHTML'))  { define('NOREQUIREHTML', '1'); }
if (!defined('NOREQUIREPRINTNOCACHE')) { define('NOREQUIREPRINTNOCACHE', '1'); }

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
if (!$res) { die('{"error":"main.inc not found"}'); }

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

top_httphead('application/json');

// Auth check
if (!isModEnabled('dolistockmove') || !$user->hasRight('stock', 'mouvement', 'creer')) {
	echo json_encode(array('error' => 'Access forbidden'));
	exit;
}

// Token check
if (!newToken() && !GETPOST('token', 'alpha') === currentToken()) {
	echo json_encode(array('error' => 'Bad token'));
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(array('error' => 'POST required'));
	exit;
}

$ref   = trim(GETPOST('ref', 'alphanohtml'));
$label = trim(GETPOST('label', 'alphanohtml'));
$desc  = trim(GETPOST('description', 'restricthtml'));

if (empty($ref)) {
	echo json_encode(array('error' => 'Référence obligatoire'));
	exit;
}
if (empty($label)) {
	echo json_encode(array('error' => 'Libellé obligatoire'));
	exit;
}

// Check ref uniqueness
$sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref = '".$db->escape($ref)."' AND entity IN (".getEntity('product').")";
$res_check  = $db->query($sql_check);
if ($res_check && $db->num_rows($res_check) > 0) {
	echo json_encode(array('error' => 'La référence "'.$ref.'" existe déjà'));
	exit;
}

// Create product
$product = new Product($db);
$product->ref         = $ref;
$product->label       = $label;
$product->description = $desc;
$product->type        = 0;  // 0=Product, 1=Service
$product->status      = 1;  // For sale
$product->status_buy  = 1;  // For purchase
$product->finished    = 0;
$product->price       = 0;
$product->price_ttc   = 0;
$product->tva_tx      = 0;
$product->entity      = $conf->entity;

$result = $product->create($user);

if ($result > 0) {
	echo json_encode(array(
		'id'    => (int) $result,
		'ref'   => $product->ref,
		'label' => $product->label,
		'stock' => 0,
		'value' => $product->ref.($product->label ? ' — '.$product->label : ''),
	));
} else {
	$errmsg = !empty($product->errors) ? implode('; ', $product->errors) : $product->error;
	echo json_encode(array('error' => $errmsg));
}
exit;
