<?php

declare(strict_types=1);

namespace muqsit\playervaults;

use muqsit\invmenu\InvMenuHandler;
use muqsit\playervaults\database\Database;
use muqsit\playervaults\database\Vault;
use muqsit\playervaults\utils\DataConsistencyException;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;

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
		if(!class_exists(InvMenuHandler::class)){
			throw new \RuntimeError($this->getName() . " depends upon 'InvMenu' virion for it's functioning. If you would still like to continue running " . $this->getName() . " from source, install the DEVirion plugin and download InvMenu to the /virions folder. Alternatively, you can download the pre-compiled PlayerVaults .phar file from poggit and not worry about installing the dependencies separately.");
		}

		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
	}

	private function createDatabase() : void{
		$this->saveDefaultConfig();
		$this->database = new Database($this, $this->getConfig()->get("database"));
	}

	private function loadConfiguration() : void{
		Vault::setNameFormat($this->getConfig()->get("inventory-name"));
	}

	public function getDatabase() : Database{
		return $this->database;
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
		if(isset($args[0])){
			$number = (int) $args[0];
			if($number > 0){
				$player = $sender->getName();

				if(isset($args[1]) && strtolower($args[1]) !== strtolower($player)){
					if($sender->hasPermission("playervaults.others.view")){
						$player = $args[1];
					}else{
						$sender->sendMessage(TF::RED . "You don't have permission to view " . $args[1] . "'s vault #" . $number . ".");
						return false;
					}
				}else{
					if(!$sender->hasPermission("playervaults.vault." . $number)){
						$sender->sendMessage(TF::RED . "You don't have permission to use vault #" . $number . ".");
						return false;
					}
				}

				$sender->sendMessage(TF::GRAY . "Opening" . ($player === $sender->getName() ? "" : " " . $player . "'s") . " vault #" . $number . "...");

				try{
					$this->getDatabase()->loadVault($player, $number, function(Vault $vault) use($sender) : void{
						if($sender->isOnline()){
							$vault->send($sender);
						}
					});
				}catch(DataConsistencyException $e){
					$sender->sendMessage(TF::RED . $e->getMessage());
					return false;
				}
				return true;
			}
		}

		$sender->sendMessage(TF::RED . "Usage: /" . $label . " <number> " . ($sender->hasPermission("playervaults.others.view") ? "[player=YOU]" : ""));
		return false;
	}
}
