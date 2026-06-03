-- Copyright (C) 2026 NEYKINFO
--
-- Indexes for llx_mouvement_stock_extrafields

ALTER TABLE llx_mouvement_stock_extrafields ADD UNIQUE INDEX uk_mouvement_stock_extrafields_fk_object (fk_object);
