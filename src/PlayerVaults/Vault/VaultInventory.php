<?php
namespace PlayerVaults\Vault;

use PlayerVaults\PlayerVaults;

use pocketmine\inventory\{ChestInventory, InventoryType};
use pocketmine\Player;

class VaultInventory extends ChestInventory{

    public function __construct(Vault $tile){
        parent::__construct($tile, InventoryType::get(InventoryType::CHEST));
    }

    public function onClose(Player $who){
        if(isset($this->holder->namedtag["VaultNumber"])){
            PlayerVaults::getInstance()->getData()->saveContents($who, $this->getContents(), $this->holder->namedtag["VaultNumber"]);
        }
        $this->holder->sendReplacement($who);
        $this->holder->close();
    }
}