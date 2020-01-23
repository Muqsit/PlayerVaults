<?php

declare(strict_types=1);

namespace muqsit\playervaults\database;

use Closure;
use InvalidArgumentException;
use muqsit\playervaults\database\utils\BinaryStringParser;
use muqsit\playervaults\database\utils\BinaryStringParserInstance;
use muqsit\playervaults\PlayerVaults;

use pocketmine\plugin\PluginLogger;
use poggit\libasynql\libasynql;

class Database implements DatabaseStmts{

	private const PSFS = [
		"sqlite" => "psfs/sqlite.sql",
		"mysql" => "psfs/mysql.sql"
	];

	public static function vaultHash(string $playername, int $number) : string{
		return strtolower($playername) . "|" . $number;
	}

	/** @var PluginLogger */
	private $logger;

	/** @var libasynql */
	private $database;

	/** @var BinaryStringParserInstance */
	private $binary_string_parser;

	/** @var Closure[] */
	private $loading_vaults = [];

	/** @var Vault[] */
	private $loaded_vaults = [];

	public function __construct(PlayerVaults $plugin, array $configuration){
		$this->logger = $plugin->getLogger();

		Vault::init();

		if(!is_dir($plugin->getDataFolder() . "psfs/")){
			/** @noinspection MkdirRaceConditionInspection */
			mkdir($plugin->getDataFolder() . "psfs/");
		}

		foreach(self::PSFS as $path){
			$plugin->saveResource($path, true);
		}

		if(isset($configuration["worker-limit"]) && $configuration["worker-limit"] > 1){
			throw new InvalidArgumentException($plugin->getName() . " does not support multi-threading. Change worker-limit to 1");
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

	public function loadVault(string $playername, int $number, Closure $callback) : void{
		if(isset($this->loaded_vaults[$hash = self::vaultHash($playername, $number)])){
			$callback($this->loaded_vaults[$hash]);
			return;
		}

		if(isset($this->loading_vaults[$hash])){
			$this->loading_vaults[$hash][] = $callback;
			return;
		}

		$this->loading_vaults[$hash] = [$callback];
		$this->database->executeSelect(self::LOAD_VAULT, [
			"player" => strtolower($playername),
			"number" => $number
		], function(array $rows) use($playername, $number, $hash) : void{
			$vault = new Vault($this, $playername, $number);
			if(isset($rows[0])){
				$vault->read($this->binary_string_parser->decode($rows[0]["data"]));
			}

			$this->loaded_vaults[$hash] = $vault;
			$this->logger->debug("Loaded vault " . $vault->getPlayerName() . "#" . $vault->getNumber());

			foreach($this->loading_vaults[$hash] as $callback){
				$callback($vault);
			}

			unset($this->loading_vaults[$hash]);
		});
	}

	public function unloadVault(Vault $vault) : void{
		unset($this->loaded_vaults[$hash = self::vaultHash($vault->getPlayerName(), $vault->getNumber())], $this->loading_vaults[$hash]);
		$this->logger->debug("Unloaded vault " . $vault->getPlayerName() . "#" . $vault->getNumber());
	}

	public function saveVault(Vault $vault) : void{
		$this->database->executeChange(self::SAVE_VAULT, [
			"player" => strtolower($vault->getPlayerName()),
			"number" => $vault->getNumber(),
			"data" => $this->binary_string_parser->encode($vault->write())
		]);
		$this->logger->debug("Saved vault " . $vault->getPlayerName() . "#" . $vault->getNumber());
	}

	public function close() : void{
		$this->database->close();
	}
}