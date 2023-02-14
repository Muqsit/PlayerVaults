<?php

declare(strict_types=1);

namespace muqsit\playervaults;

use Closure;
use muqsit\invmenu\InvMenuHandler;
use muqsit\playervaults\database\Database;
use muqsit\playervaults\vault\Vault;
use muqsit\playervaults\vault\VaultAccess;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use RuntimeException;
use function gettype;
use function is_array;
use function is_string;
use function strtolower;

final class PlayerVaults extends PluginBase{

	private Database $database;
	private PermissionManager $permission_manager;

	protected function onEnable() : void{
		$this->initVirions();
		$this->createDatabase();
		$this->loadConfiguration();
	}

	protected function onDisable() : void{
		$this->getDatabase()->close();
	}

	private function initVirions() : void{
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
	}

	private function createDatabase() : void{
		$this->saveDefaultConfig();

		$database = $this->getConfig()->get("database");
		if(!is_array($database)){
			throw new RuntimeException("Database configuration must be an array, got " . gettype($database));
		}
		$this->database = new Database($this, $database);
	}

	private function loadConfiguration() : void{
		$inventory_name = $this->getConfig()->get("inventory-name");
		Vault::setNameFormat(is_string($inventory_name) ? $inventory_name : null);

		$this->saveResource("permission-grouping.yml");
		$this->permission_manager = new PermissionManager(new Config($this->getDataFolder() . "permission-grouping.yml", Config::YAML));
	}

	public function getDatabase() : Database{
		return $this->database;
	}

	/**
	 * Loads a specific vault.
	 *
	 * @param string $player
	 * @param int $number
	 * @param Closure(Vault, VaultAccess) : void $callback
	 * @throws PlayerVaultsException
	 */
	public function loadVault(string $player, int $number, Closure $callback) : void{
		if($number < 1){
			throw new PlayerVaultsException("Vault number cannot be < 1, got {$number}", PlayerVaultsException::ERR_INVALID_VAULT_NUMBER);
		}
		if($number > 255){
			throw new PlayerVaultsException("Vault number cannot be > 255, got {$number}", PlayerVaultsException::ERR_INVALID_VAULT_NUMBER);
		}
		$this->getDatabase()->loadVault($player, $number, $callback);
	}

	/**
	 * Validates criteria required to access a specific vault as a player.
	 *
	 * @param Player $opener
	 * @param string $player
	 * @param int $number
	 * @throws PlayerVaultsException
	 */
	public function tryAccessingVault(Player $opener, string $player, int $number) : void{
		if(strtolower($opener->getName()) !== strtolower($player)){
			if(!$opener->hasPermission("playervaults.others.view")){
				throw new PlayerVaultsException("Cannot access {$player}'s vault #{$number} as {$opener->getName()}", PlayerVaultsException::ERR_UNAUTHORIZED_FOREIGN);
			}
		}else{ // trying to access their own vault
			if(!$this->permission_manager->hasPermission($opener, $number)){
				throw new PlayerVaultsException("Cannot access self ({$opener->getName()})'s vault #{$number}", PlayerVaultsException::ERR_UNAUTHORIZED_SELF);
			}
		}
	}

	/**
	 * Opens a specific vault as a player. This does not validate access permissions.
	 * @see PlayerVaults::tryAccessingVault()
	 * @see PlayerVaults::openVaultWithPermission()
	 *
	 * @param Player $opener
	 * @param string $player
	 * @param int $number
	 * @param (Closure(Vault) : void)|null $on_success
	 * @param (Closure(PlayerVaultsException) : void)|null $on_failure
	 */
	public function openVault(Player $opener, string $player, int $number, ?Closure $on_success = null, ?Closure $on_failure = null) : void{
		$on_success ??= static function(Vault $vault) : void{};
		$on_failure ??= static function(PlayerVaultsException $_) : void{ /* throw exception by default? */ };

		$callback = function(Vault $vault, VaultAccess $access) use($opener, $on_success, $on_failure) : void{
			if(!$opener->isConnected()){
				$access->release();
				$on_failure(new PlayerVaultsException("Player is not connected", PlayerVaultsException::ERR_VIEWER_NOT_CONNECTED));
				return;
			}
			$vault->send($opener, null, function(bool $success) use($opener, $vault, $access, $on_success, $on_failure) : void{
				if($success){
					$vault->releaseAccessWithPlayer($opener, $access);
					$on_success($vault);
				}else{
					$access->release();
					$on_failure(new PlayerVaultsException("Failed opening vault inventory", PlayerVaultsException::ERR_GENERIC));
				}
			});
		};

		try{
			$this->loadVault($player, $number, $callback);
		}catch(PlayerVaultsException $e){
			$on_failure($e);
			return;
		}
	}

	/**
	 * @param Player $opener
	 * @param string $player
	 * @param int $number
	 * @param (Closure(Vault) : void)|null $on_success
	 * @param (Closure(PlayerVaultsException) : void)|null $on_failure
	 */
	public function openVaultWithPermission(Player $opener, string $player, int $number, ?Closure $on_success = null, ?Closure $on_failure = null) : void{
		try{
			$this->tryAccessingVault($opener, $player, $number);
		}catch(PlayerVaultsException $e){
			if($on_failure !== null){
				$on_failure($e);
			}
			return;
		}
		$this->openVault($opener, $player, $number, $on_success, $on_failure);
	}

	public function handlePlayerVaultsException(Player $player, PlayerVaultsException $exception) : void{
		$code = $exception->getCode();
		if($code === PlayerVaultsException::ERR_VIEWER_NOT_CONNECTED){
			return;
		}
		$player->sendMessage(TextFormat::RED . match($exception->getCode()){
			PlayerVaultsException::ERR_GENERIC => "Failed to access vault, please try again.",
			PlayerVaultsException::ERR_INVALID_VAULT_NUMBER => "Vault number must be between 0 and 256.",
			PlayerVaultsException::ERR_UNAUTHORIZED_FOREIGN, PlayerVaultsException::ERR_UNAUTHORIZED_SELF => "You are not allowed to access that vault."
		});
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
		if(!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED . "This command can only be executed as a player.");
			return true;
		}
		
		if(!isset($args[0])){
			$sender->sendMessage(TextFormat::RED . "Usage: /" . $label . " <number> " . ($sender->hasPermission("playervaults.others.view") ? "[player=YOU]" : ""));
			return true;
		}

		$number = (int) $args[0];
		$player = $args[1] ?? $sender->getName();
		try{
			$this->tryAccessingVault($sender, $player, $number);
		}catch(PlayerVaultsException $e){
			$this->handlePlayerVaultsException($sender, $e);
			return true;
		}

		$sender->sendMessage(TextFormat::GRAY . "Opening" . (strtolower($player) === strtolower($sender->getName()) ? "" : " {$player}'s") . " vault #{$number}...");
		$this->openVault($sender, $player, $number, static function(Vault $_) : void{}, function(PlayerVaultsException $e) : void{
			throw $e;
		});
		return true;
	}
}
