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
			'propal:+dolistockmove:StockMovements:dolistockmove@dolistockmove:$user->hasRight("stock","lire"):/dolistockmove/propal_stockmovements.php?id=__ID__',
		);

		$this->dictionaries = array();
		$this->boxes        = array();
		$this->cronjobs     = array();

		// No custom permissions — we reuse native Dolibarr stock permissions:
		//   read  : $user->hasRight('stock', 'lire')
		//   create: $user->hasRight('stock', 'mouvement', 'creer')
		$this->rights = array();

		// -------------------------
		// Left-menu entries under the native "Stock" top menu
		// -------------------------
		$this->menu = array();
		$r = 0;

		// Group entry
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=stock',
			'type'     => 'left',
			'titre'    => 'DoliStockMove',
			'prefix'   => img_picto('', 'fa-dolly', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'stock',
			'leftmenu' => 'dolistockmove',
			'url'      => '/dolistockmove/dolistockmoveindex.php',
			'langs'    => 'dolistockmove@dolistockmove',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolistockmove")',
			'perms'    => '$user->hasRight("stock","lire")',
			'target'   => '',
			'user'     => 2,
		);

		// New movement
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=stock,fk_leftmenu=dolistockmove',
			'type'     => 'left',
			'titre'    => 'NewStockMove',
			'mainmenu' => 'stock',
			'leftmenu' => 'dolistockmove_new',
			'url'      => '/dolistockmove/stockmove_card.php',
			'langs'    => 'dolistockmove@dolistockmove',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolistockmove")',
			'perms'    => '$user->hasRight("stock","mouvement","creer")',
			'target'   => '',
			'user'     => 2,
		);

		// Movement list
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=stock,fk_leftmenu=dolistockmove',
			'type'     => 'left',
			'titre'    => 'StockMoveList',
			'mainmenu' => 'stock',
			'leftmenu' => 'dolistockmove_list',
			'url'      => '/dolistockmove/stockmove_list.php',
			'langs'    => 'dolistockmove@dolistockmove',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("dolistockmove")',
			'perms'    => '$user->hasRight("stock","lire")',
			'target'   => '',
			'user'     => 2,
		);
	}

	/**
	 * Function called when module is enabled.
	 * Creates SQL tables and adds the fk_proposal extrafield on mouvement_stock.
	 *
	 * @param  string $options Space separated list of options
	 * @return int              1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		// Load SQL tables (creates llx_mouvement_stock_extrafields if needed)
		$result = $this->_load_tables('/dolistockmove/sql/');
		if ($result < 0) {
			return -1;
		}

		// Create the fk_proposal extrafield on mouvement_stock
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);

		$listef = $extrafields->fetch_name_optionals_label('mouvement_stock');

		if (empty($listef['fk_proposal'])) {
			$result2 = $extrafields->addExtraField(
				'fk_proposal',              // code
				'Proposition commerciale',  // label
				'link',                     // type
				100,                        // pos
				'',                         // size
				'mouvement_stock',          // elementtype
				0,                          // unique
				0,                          // required
				'',                         // default_value
				array('options' => array('propal:ref:rowid:' => null)), // param
				1,                          // alwayseditable
				'',                         // perms
				'-1',                       // list (hidden in standard forms)
				0,                          // computed
				'',                         // entity
				'',                         // langfile
				'',                         // help
				1                           // enabled
			);
			if ($result2 < 0) {
				$this->errors[] = $extrafields->error;
				return -1;
			}
		}

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
