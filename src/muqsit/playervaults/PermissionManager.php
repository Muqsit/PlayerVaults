<?php

declare(strict_types=1);

namespace muqsit\playervaults;

use pocketmine\Player;
use pocketmine\utils\Config;

final class PermissionManager{

	/** @var array<string, string>[] */
	private $grouping = [];

	public function __construct(Config $config){
		if($config->get("enabled", false)){
			foreach($config->get("permissions") as $permission => $vaults){
				$this->registerGroup($permission, $vaults);
			}
		}
	}

	public function registerGroup(string $permission, int $vaults) : void{
		if(!isset($this->grouping[$vaults])){
			$this->grouping[$vaults] = [];
			krsort($this->grouping);
		}

		$this->grouping[$vaults][$permission] = $permission;
	}

	public function hasPermission(Player $player, int $vault) : bool{
		if($player->hasPermission("playervaults.vault.{$vault}")){
			return true;
		}

		foreach($this->grouping as $max_vaults => $permissions){
			if($max_vaults < $vault){
				break;
			}
			foreach($permissions as $permission){
				if($player->hasPermission($permission)){
					return true;
				}
			}
		}
		return false;
	}
}