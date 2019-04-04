-- #!sqlite
-- #{ playervaults

-- #  { init
CREATE TABLE IF NOT EXISTS vaults(
  player VARCHAR(16) NOT NULL,
  number TINYINT UNSIGNED NOT NULL,
  data BLOB NOT NULL,
  PRIMARY KEY(player, number)
);
-- #  }

-- #  { load
-- #    :player string
-- #    :number int
SELECT HEX(data) AS data FROM vaults WHERE player=:player AND number=:number;
-- #  }

-- #  { save
-- #    :player string
-- #    :number int
-- #    :data string
INSERT OR REPLACE INTO vaults(player, number, data) VALUES(:player, :number, X:data);
-- #  }

-- #}