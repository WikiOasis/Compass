CREATE TABLE /*_*/compass_wikis (
  cpw_dbname VARCHAR(64) NOT NULL PRIMARY KEY,
  cpw_visible TINYINT NOT NULL DEFAULT '1',
  cpw_description VARCHAR(512) NULL,
  cpw_extended_description TEXT NULL,
  cpw_thumbnail TEXT NULL,
  cpw_highlighted TINYINT NOT NULL DEFAULT '0',
  cpw_highlight_order INT NOT NULL DEFAULT '0',
  cpw_touched BINARY(14) NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/cpw_highlighted ON /*_*/compass_wikis (cpw_highlighted, cpw_highlight_order);
CREATE INDEX /*i*/cpw_visible ON /*_*/compass_wikis (cpw_visible);
