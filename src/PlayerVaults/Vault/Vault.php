<?php
/*
*
* Copyright (C) 2017 Muqsit Rayyan
*
*    ___ _                                        _ _       
*   / _ \ | __ _ _   _  ___ _ __/\   /\__ _ _   _| | |_ ___ 
*  / /_)/ |/ _" | | | |/ _ \ "__\ \ / / _" | | | | | __/ __|
* / ___/| | (_| | |_| |  __/ |   \ V / (_| | |_| | | |_\__ \
* \/    |_|\__,_|\__, |\___|_|    \_/ \__,_|\__,_|_|\__|___/
*                |___/                                      
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
*
* @author Muqsit Rayyan
* Twiter: http://twitter.com/muqsitrayyan
* GitHub: http://github.com/Muqsit
*
*/
namespace PlayerVaults\Vault;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\tile\Chest;

class Vault extends Chest{

    public function __construct(Level $level, CompoundTag $nbt){
        parent::__construct($level, $nbt);
        $this->inventory = new VaultInventory($this);
        $this->replacement = [$this->getBlock()->getId(), $this->getBlock()->getDamage()];
    }

    private function getReplacement() : Block{
        return Block::get(...$this->replacement);
    }

    public function sendReplacement(Player $player){
        $block = $this->getReplacement();
        $block->x = (int) $this->x;
        $block->y = (int) $this->y;
        $block->z = (int) $this->z;
        $block->level = $this->getLevel();
        if($block->level !== null){
            $block->level->sendBlocks([$player], [$block]);
        }
    }

    public function spawnToAll(){
    }
}