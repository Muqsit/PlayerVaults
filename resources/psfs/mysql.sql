-- #!mysql
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
SELECT data FROM vaults WHERE player=:player AND number=:number;
-- #  }

-- #  { save
-- #    :player string
-- #    :number int
-- #    :data string
INSERT INTO vaults(player, number, data) VALUES(:player, :number, :data)
ON DUPLICATE KEY UPDATE data=VALUES(data);
-- #  }

-- #}