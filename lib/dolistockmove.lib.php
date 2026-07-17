<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       lib/dolistockmove.lib.php
 * \ingroup    dolistockmove
 * \brief      Lib file for admin and common helpers
 */

/**
 * Prepare admin/module header tabs
 *
 * @return array Head tabs
 */
function dolistockmoveAdminPrepareHead()
{
	global $langs, $conf;
	$langs->load('dolistockmove@dolistockmove');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/dolistockmove/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistockmove/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolistockmove@dolistockmove');

	return $head;
}

/**
 * Return a list of warehouses as an array for select
 *
 * @param  DoliDB $db           Database
 * @param  int    $selected     Selected warehouse ID
 * @param  bool   $addempty     Add empty choice
 * @return array                [id => label, ...]
 */
function dolistockmoveGetWarehouses($db, $selected = 0, $addempty = false)
{
	$warehouses = array();
	if ($addempty) {
		$warehouses[0] = '';
	}

	$sql = "SELECT rowid, ref, description, lieu FROM ".MAIN_DB_PREFIX."entrepot";
	$sql .= " WHERE statut = 1";
	$sql .= " AND entity IN (".getEntity('stock').")";
	$sql .= " ORDER BY ref";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$label = $obj->ref;
			if (!empty($obj->lieu)) {
				$label .= ' - '.$obj->lieu;
			} elseif (!empty($obj->description)) {
				$label .= ' - '.$obj->description;
			}
			$warehouses[$obj->rowid] = $label;
		}
		$db->free($resql);
	}
	return $warehouses;
}

/**
 * Return current stock of a product in a warehouse
 *
 * @param  DoliDB $db           Database
 * @param  int    $fk_product   Product ID
 * @param  int    $fk_entrepot  Warehouse ID (0 = all warehouses)
 * @return float                Stock quantity
 */
function dolistockmoveGetStock($db, $fk_product, $fk_entrepot = 0)
{
	$sql = "SELECT SUM(ps.reel) as stock";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_stock ps";
	$sql .= " WHERE ps.fk_product = ".((int) $fk_product);
	if ($fk_entrepot > 0) {
		$sql .= " AND ps.fk_entrepot = ".((int) $fk_entrepot);
	}

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return isset($obj->stock) ? (float) $obj->stock : 0;
	}
	return 0;
}
