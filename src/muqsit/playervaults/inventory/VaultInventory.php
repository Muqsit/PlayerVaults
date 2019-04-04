<?php

declare(strict_types=1);

namespace muqsit\playervaults\inventory;

use muqsit\invmenu\inventories\DoubleChestInventory;
use muqsit\playervaults\database\Vault;

class VaultInventory extends DoubleChestInventory{

	/** @var Vault */
	private $vault_data;

	public function setVaultData(Vault $vault) : void{
		$this->vault_data = $vault;
	}

	public function getVaultData() : Vault{
		return $this->vault_data;
	}
}