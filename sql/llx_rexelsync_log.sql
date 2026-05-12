CREATE TABLE llx_rexelsync_log (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_product integer NULL,
  fk_product_fournisseur_price integer NULL,
  ref_product varchar(128) NULL,
  ref_fourn varchar(128) NULL,
  old_price double(24,8) NULL,
  new_price double(24,8) NULL,
  old_stock double(24,8) NULL,
  new_stock double(24,8) NULL,
  status varchar(32) NOT NULL,
  message text NULL,
  http_status integer NULL,
  datec datetime NOT NULL
) ENGINE=innodb;
