ALTER TABLE llx_rexelsync_log ADD INDEX idx_rexelsync_log_entity (entity);
ALTER TABLE llx_rexelsync_log ADD INDEX idx_rexelsync_log_product (fk_product);
ALTER TABLE llx_rexelsync_log ADD INDEX idx_rexelsync_log_supplier_price (fk_product_fournisseur_price);
ALTER TABLE llx_rexelsync_log ADD INDEX idx_rexelsync_log_ref_fourn (ref_fourn);
ALTER TABLE llx_rexelsync_log ADD INDEX idx_rexelsync_log_status (status);
ALTER TABLE llx_rexelsync_log ADD INDEX idx_rexelsync_log_datec (datec);
