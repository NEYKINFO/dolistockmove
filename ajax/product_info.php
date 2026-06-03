<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       ajax/product_info.php
 * \ingroup    dolistockmove
 * \brief      AJAX endpoint — search products and/or return stock info
 *
 * Actions:
 *   search   : GET ?action=search&term=xxx[&fk_entrepot=N]
 *              Returns JSON array of matching products with current stock
 *   stock    : GET ?action=stock&product_id=N[&fk_entrepot=N]
 *              Returns JSON {stock, ref, label}
 *   propal   : GET ?action=propal&term=xxx
 *              Returns JSON array of matching proposals
 */

// Restrict to AJAX calls from the same session
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

// Output JSON
top_httphead('application/json');

// Auth check
if (!isModEnabled('dolistockmove') || !$user->hasRight('stock', 'lire')) {
	echo json_encode(array('error' => 'Access forbidden'));
	exit;
}

$action      = GETPOST('action', 'aZ09');
$term        = GETPOST('term', 'alphanohtml');
$fk_entrepot = GETPOSTINT('fk_entrepot');
$product_id  = GETPOSTINT('product_id');

// -------------------------------------------------------
// action=search  →  search products by ref or label
// -------------------------------------------------------
if ($action === 'search') {
	if (strlen($term) < 2) {
		echo json_encode(array());
		exit;
	}

	$sql  = "SELECT p.rowid, p.ref, p.label, p.description,";
	$sql .= " COALESCE(SUM(ps.reel), 0) as stock";
	$sql .= " FROM ".MAIN_DB_PREFIX."product p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_stock ps ON ps.fk_product = p.rowid";
	if ($fk_entrepot > 0) {
		$sql .= " AND ps.fk_entrepot = ".((int) $fk_entrepot);
	}
	$sql .= " WHERE p.entity IN (".getEntity('product').")";
	$sql .= "   AND p.type IN (0, 1)";
	$sql .= "   AND (p.ref LIKE '%".$db->escape($term)."%' OR p.label LIKE '%".$db->escape($term)."%')";
	$sql .= " GROUP BY p.rowid, p.ref, p.label, p.description";
	$sql .= " ORDER BY p.ref ASC";
	$sql .= $db->plimit(20, 0);

	$resql = $db->query($sql);
	$results = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$results[] = array(
				'id'    => (int) $obj->rowid,
				'ref'   => $obj->ref,
				'label' => $obj->label,
				'stock' => (float) $obj->stock,
				'value' => $obj->ref.($obj->label ? ' — '.$obj->label : ''),
			);
		}
		$db->free($resql);
	}

	echo json_encode($results);
	exit;
}

// -------------------------------------------------------
// action=stock  →  get current stock for a product
// -------------------------------------------------------
if ($action === 'stock') {
	if ($product_id <= 0) {
		echo json_encode(array('error' => 'Invalid product_id'));
		exit;
	}

	$sql  = "SELECT p.rowid, p.ref, p.label,";
	$sql .= " COALESCE(SUM(ps.reel), 0) as stock";
	$sql .= " FROM ".MAIN_DB_PREFIX."product p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_stock ps ON ps.fk_product = p.rowid";
	if ($fk_entrepot > 0) {
		$sql .= " AND ps.fk_entrepot = ".((int) $fk_entrepot);
	}
	$sql .= " WHERE p.rowid = ".((int) $product_id);
	$sql .= " GROUP BY p.rowid, p.ref, p.label";

	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		echo json_encode(array(
			'id'    => (int) $obj->rowid,
			'ref'   => $obj->ref,
			'label' => $obj->label,
			'stock' => (float) $obj->stock,
		));
	} else {
		echo json_encode(array('error' => 'Product not found'));
	}
	exit;
}

// -------------------------------------------------------
// action=propal  →  search proposals by ref or label
// -------------------------------------------------------
if ($action === 'propal') {
	if (strlen($term) < 2) {
		echo json_encode(array());
		exit;
	}

	$sql  = "SELECT pr.rowid, pr.ref, s.nom as soc_name";
	$sql .= " FROM ".MAIN_DB_PREFIX."propal pr";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = pr.fk_soc";
	$sql .= " WHERE pr.entity IN (".getEntity('propal').")";
	$sql .= "   AND (pr.ref LIKE '%".$db->escape($term)."%' OR s.nom LIKE '%".$db->escape($term)."%')";
	$sql .= " ORDER BY pr.ref DESC";
	$sql .= $db->plimit(15, 0);

	$resql = $db->query($sql);
	$results = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$results[] = array(
				'id'    => (int) $obj->rowid,
				'ref'   => $obj->ref,
				'label' => $obj->soc_name ? $obj->ref.' — '.$obj->soc_name : $obj->ref,
				'value' => $obj->ref,
			);
		}
		$db->free($resql);
	}

	echo json_encode($results);
	exit;
}

echo json_encode(array('error' => 'Unknown action'));
exit;
