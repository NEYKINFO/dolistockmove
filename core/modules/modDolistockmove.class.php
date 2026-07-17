<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   dolistockmove     Module DoliStockMove
 * \brief      Saisie rapide de mouvements de stock liés aux propositions commerciales.
 *
 * \file       core/modules/modDolistockmove.class.php
 * \ingroup    dolistockmove
 * \brief      Description and activation file for module DoliStockMove
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module DoliStockMove
 */
class modDolistockmove extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Unique numeric ID for the module (pick a free slot in the 500000+ range)
		$this->numero = 500023;

		$this->rights_class = 'dolistockmove';

		$this->family = "Neykinfo";

		$this->module_position = '90';

		$this->name = preg_replace('/^mod/i', '', get_class($this));

		$this->description = "DolistockmoveDescription";
		$this->descriptionlong = "DolistockmoveDescriptionLong";

		$this->editor_name = 'NEYKINFO';
		$this->editor_url = 'https://github.com/NEYKINFO';

		$this->version = '1.0.0';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		$this->picto = 'fa-dolly';

		$this->module_parts = array(
			'triggers'        => 0,
			'login'           => 0,
			'substitutions'   => 0,
			'menus'           => 0,
			'tpl'             => 0,
			'barcode'         => 0,
			'models'          => 0,
			'printing'        => 0,
			'theme'           => 0,
			'css'             => array('/dolistockmove/css/dolistockmove.css'),
			'js'              => array('/dolistockmove/js/dolistockmove.js'),
			'hooks'           => array(),
			'moduleforexternal' => 0,
		);

		$this->dirs = array('/dolistockmove', '/dolistockmove/temp');

		$this->config_page_url = array('setup.php@dolistockmove');

		$this->hidden = false;
		$this->depends    = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array('dolistockmove@dolistockmove');

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0);
		$this->need_javascript_ajax = 0;

		$this->warnings_activation     = array();
		$this->warnings_activation_ext = array();

		$this->const = array();

		if (!isModEnabled('dolistockmove')) {
			$conf->dolistockmove = new stdClass();
			$conf->dolistockmove->enabled = 0;
		}

		// Add a tab on the Proposal (devis) card showing linked stock movements
		$this->tabs = array(
			'propal:+dolistockmove_moves:StockMovements:dolistockmove@dolistockmove:$user->hasRight("stock","lire"):/dolistockmove/propal_stockmovements.php?id=__ID__',
		);

		$this->dictionaries = array();
		$this->boxes        = array();
		$this->cronjobs     = array();

		// No custom permissions — we reuse native Dolibarr stock permissions:
		//   read  : $user->hasRight('stock', 'lire')
		//   create: $user->hasRight('stock', 'mouvement', 'creer')
		$this->rights = array();

		// -------------------------
		// Left-menu entries under Products/Services top menu
		// -------------------------
		$this->menu = array();
		$r = 0;

		// New movement
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=products',
			'type'     => 'left',
			'titre'    => 'NewStockMove',
			'prefix'   => img_picto('', 'fa-dolly', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'products',
			'leftmenu' => 'dolistockmove_new',
			'url'      => '/dolistockmove/stockmove_card.php',
			'langs'    => 'dolistockmove@dolistockmove',
			'position' => 500,
			'enabled'  => 'isModEnabled("dolistockmove")',
			'perms'    => '$user->hasRight("stock","mouvement","creer")',
			'target'   => '',
			'user'     => 2,
		);

		// Movement list
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=products',
			'type'     => 'left',
			'titre'    => 'StockMoveList',
			'prefix'   => img_picto('', 'fa-list', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'products',
			'leftmenu' => 'dolistockmove_list',
			'url'      => '/dolistockmove/stockmove_list.php',
			'langs'    => 'dolistockmove@dolistockmove',
			'position' => 501,
			'enabled'  => 'isModEnabled("dolistockmove")',
			'perms'    => '$user->hasRight("stock","lire")',
			'target'   => '',
			'user'     => 2,
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param  string $options Space separated list of options
	 * @return int              1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);

		$extrafields->addExtraField(
			'salarie',
			'Salarié concerné',
			'select',
			101,
			'',
			'stock_mouvement',
			0,
			0,
			'',
			'a:1:{s:7:"options";a:1:{s:0:"";N;}}',
			1,
			'',
			1,
			'',
			'',
			'',
			'',
			1,
			1,
			0,
			array()
		);

		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param  string $options Space separated list of options
	 * @return int              1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
