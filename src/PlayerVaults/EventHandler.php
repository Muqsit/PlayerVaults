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

use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;

class EventHandler implements Listener{

    public function onTransaction(InventoryTransactionEvent $event)
    {
        $array = $event->getTransaction()->getTransactions();
        $transaction = $array[array_keys($array)[0]];

        $inventory = $transaction->getInventory() ?? null;
        if($inventory !== null){
            $namedtag = $inventory->getHolder()->namedtag;
            if(!isset($namedtag->VaultNumber)){
                $inventory->setItem($transaction->getSlot(), Item::get(0));
                $event->setCancelled();
            }
        }
    }
}