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
namespace PlayerVaults;

use PlayerVaults\PlayerVaults;

use pocketmine\inventory\ContainerInventory;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;

class VaultInventory extends ContainerInventory {

    const INVENTORY_SIZE = 27;

    /** @var string */
    protected $vaultof;

    /** @var int */
    protected $number;

    public function __construct(Position $pos, string $vaultof, int $number, array $items = [])
    {
        $this->vaultof = $vaultof;
        $this->number = $number;
        parent::__construct($pos, $items, self::INVENTORY_SIZE);
    }

    public function getNetworkType() : int
    {
        return WindowTypes::CONTAINER;
    }

    public function getName() : string
    {
        return "Vault";
    }

    public function getDefaultSize() : int
    {
        return self::INVENTORY_SIZE;
    }

    /**
     * Returns the name of the player to whom belongs
     * this inventory.
     *
     * @return string
     */
    public function getVaultOf() : string
    {
        return $this->vaultof;
    }

    /**
     * Returns the vault number.
     *
     * @return int
     */
    public function getNumber() : int
    {
        return $this->number;
    }

    public function onClose(Player $who) : void
    {
        PlayerVaults::getInstance()->getData()->saveContents($this);
        $this->sendRealBlocks($who);
    }

    private function sendRealBlocks(Player ...$players) : void
    {
        $holder = $this->getHolder();
        $holder->getLevel()->sendBlocks($players, [$holder->getLevel()->getBlockAt($holder->x, $holder->y, $holder->z)]);
    }
}
