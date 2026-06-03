<?php
/* Copyright (C) 2026 NEYKINFO <https://github.com/NEYKINFO>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/actions_dolistockmove.class.php
 * \ingroup    dolistockmove
 * \brief      Hook actions class for DoliStockMove
 *
 * Reserved for future hooks (e.g. adding a shortcut button on invoices, etc.)
 * The proposal tab is handled via $this->tabs in the module descriptor.
 */

/**
 * Class ActionsDolostockmove
 */
class ActionsDolistockmove
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error string
	 */
	public $error = '';

	/**
	 * @var array Errors array
	 */
	public $errors = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}
}
