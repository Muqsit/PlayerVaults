<?php
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