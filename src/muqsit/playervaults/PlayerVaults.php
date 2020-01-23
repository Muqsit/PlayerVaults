<?php

declare(strict_types=1);

namespace muqsit\playervaults;

use muqsit\invmenu\InvMenuHandler;
use muqsit\playervaults\database\Database;
use muqsit\playervaults\database\Vault;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class PlayerVaults extends PluginBase{

	/** @var Database */
	private $database;

	public function onEnable() : void{
		$this->initVirions();
		$this->createDatabase();
		$this->loadConfiguration();
	}

	public function onDisable() : void{
		$this->getDatabase()->close();
	}

	private function initVirions() : void{
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
	}

	private function createDatabase() : void{
		$this->saveDefaultConfig();
		$this->database = new Database($this, $this->getConfig()->get("database"));
	}

	private function loadConfiguration() : void{
		Vault::setNameFormat((string) $this->getConfig()->get("inventory-name"));
	}

	public function getDatabase() : Database{
		return $this->database;
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
		if(!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED . "This command can only be executed as a player.");
			return true;
		}
		
		if(isset($args[0])){
			$number = (int) $args[0];
			if($number > 0){
				$player = $sender->getName();

				if(isset($args[1]) && strtolower($args[1]) !== strtolower($player)){
					if($sender->hasPermission("playervaults.others.view")){
						$player = $args[1];
					}else{
						$sender->sendMessage(TextFormat::RED . "You don't have permission to view " . $args[1] . "'s vault #" . $number . ".");
						return false;
					}
				}else{
					if(!$sender->hasPermission("playervaults.vault." . $number)){
						$sender->sendMessage(TextFormat::RED . "You don't have permission to use vault #" . $number . ".");
						return false;
					}
				}

				$sender->sendMessage(TextFormat::GRAY . "Opening" . ($player === $sender->getName() ? "" : " " . $player . "'s") . " vault #" . $number . "...");

				$this->getDatabase()->loadVault($player, $number, function(Vault $vault) use($sender) : void{
					if($sender->isOnline()){
						$vault->send($sender);
					}
				});
				return true;
			}
		}

		$sender->sendMessage(TextFormat::RED . "Usage: /" . $label . " <number> " . ($sender->hasPermission("playervaults.others.view") ? "[player=YOU]" : ""));
		return false;
	}
}
