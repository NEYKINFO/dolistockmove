-- Copyright (C) 2026 NEYKINFO
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

-- Create the extrafields table for mouvement_stock if it does not yet exist.
-- The fk_proposal column is added programmatically via ExtraFields API
-- in modDolistockmove::init() to stay consistent with Dolibarr's extrafield registry.

CREATE TABLE IF NOT EXISTS llx_mouvement_stock_extrafields (
	rowid      integer  AUTO_INCREMENT PRIMARY KEY NOT NULL,
	tms        timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_object  integer  NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
