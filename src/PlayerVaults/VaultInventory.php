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

use muqsit\invmenu\inventories\DoubleChestInventory;

class VaultInventory extends DoubleChestInventory {

    /** @var string */
    protected $vaultof;

    /** @var int */
    protected $number;

    public function setVaultData(string $vaultof, int $number)
    {
        $this->vaultof = $vaultof;
        $this->number = $number;
    }

    public function getName() : string
    {
        return "Vault";
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
}
