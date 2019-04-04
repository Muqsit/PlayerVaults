<?php

declare(strict_types=1);

namespace muqsit\playervaults\database;

use muqsit\playervaults\database\locks\LoadingLock;
use muqsit\playervaults\database\locks\LockFactory;
use muqsit\playervaults\database\locks\SavingLock;
use muqsit\playervaults\database\utils\BinaryStringParser;
use muqsit\playervaults\database\utils\BinaryStringParserInstance;
use muqsit\playervaults\PlayerVaults;

use poggit\libasynql\libasynql;

class Database implements DatabaseStmts{

	private const PSFS = [
		"sqlite" => "psfs/sqlite.sql",
		"mysql" => "psfs/mysql.sql"
	];

	public static function vaultHash(string $playername, int $number) : string{
		return strtolower($playername) . pack("C", $number);
	}

	/** @var libasynql */
	private $database;

	/** @var BinaryStringParserInstance */
	private $binary_string_parser;

	/** @var VaultHolder[] */
	private $loaded_vaults = [];

	public function __construct(PlayerVaults $plugin, array $configuration){
		LockFactory::init();
		Vault::init();

		if(!is_dir($plugin->getDataFolder() . "psfs/")){
			mkdir($plugin->getDataFolder() . "psfs/");
		}

		foreach(self::PSFS as $path){
			$plugin->saveResource($path, true);
		}

		$this->binary_string_parser = BinaryStringParser::fromDatabase($configuration["type"]);
		$this->database = libasynql::create($plugin, $configuration, self::PSFS);
		$this->init();
	}

	private function init() : void{
		foreach((new \ReflectionClass(DatabaseStmts::class))->getConstants() as $name => $stmt){
			if(strpos($name, "INIT_") === 0){
				$this->database->executeGeneric($stmt);
			}
		}

		$this->database->waitAll();
	}

	public function loadVault(string $playername, int $number, callable $callback) : void{
		if(isset($this->loaded_vaults[$hash = self::vaultHash($playername, $number)])){
			$callback($this->loaded_vaults[$hash]->getVault());
			return;
		}

		$this->loaded_vaults[$hash] = $holder = new VaultHolder($playername, $number, [$this, "unloadVault"]);

		$holder->lock(LockFactory::get(LoadingLock::class));

		$this->database->executeSelect(self::LOAD_VAULT, [
			"player" => $playername,
			"number" => $number
		], function(array $rows) use($holder, $callback) : void{
			$holder->unlock(LockFactory::get(LoadingLock::class));

			$vault = $holder->getVault();
			if(isset($rows[0])){
				$vault->read($this->binary_string_parser->decode($rows[0]["data"]));
			}
			$callback($vault);
		});
	}

	public function unloadVault(VaultHolder $holder) : void{
		if(isset($this->loaded_vaults[$hash = self::vaultHash($holder->getPlayerName(), $holder->getNumber())])){
			$this->saveVault($holder, (function() use($hash) : void{
				unset($this->loaded_vaults[$hash]);
			})->bindTo($this));
		}
	}

	private function saveVault(VaultHolder $holder, callable $callback) : void{
		$vault = $holder->getVault();
		$holder->lock(LockFactory::get(SavingLock::class));

		$this->database->executeChange(self::SAVE_VAULT, [
			"player" => $holder->getPlayerName(),
			"number" => $holder->getNumber(),
			"data" => $this->binary_string_parser->encode($vault->write())
		], function() use($callback, $holder) : void{
			$holder->unlock(LockFactory::get(SavingLock::class));
			$callback();
		});
	}

	public function close() : void{
		$this->database->close();
	}
}