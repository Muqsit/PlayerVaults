<?php

declare(strict_types=1);

namespace muqsit\playervaults\vault;

use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryListener;
use pocketmine\item\Item;

/**
 * @internal
 */
final class VaultInventoryListener implements InventoryListener{

	public function __construct(
		private Vault $vault
	){}

	public function destroy() : void{
		unset($this->vault);
	}

	public function onSlotChange(Inventory $inventory, int $slot, Item $oldItem) : void{
		$this->vault->_changed = true;
	}

	public function onContentChange(Inventory $inventory, array $oldContents) : void{
		$this->vault->_changed = true;
	}
}