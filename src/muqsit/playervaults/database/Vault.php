<?php

declare(strict_types=1);

namespace muqsit\playervaults\database;

use muqsit\invmenu\InvMenu;
use muqsit\playervaults\inventory\VaultInventory;

use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Player;

class Vault{

	private const TAG_INVENTORY = "Inventory";

	/** @var BigEndianNBTStream */
	private static $nbtSerializer;

	/** @var string|null */
	private static $name_format = null;

	public static function init() : void{
		self::$nbtSerializer = new BigEndianNBTStream();
	}

	public static function setNameFormat(?string $format = null) : void{
		self::$name_format = $format;
	}

	/** @var string */
	private $playername;

	/** @var int */
	private $number;

	/** @var InvMenu */
	private $menu;

	/** @var callable */
	private $on_garbage;

	public function __construct(string $playername, int $number, callable $on_garbage){
		$this->playername = $playername;
		$this->number = $number;
		$this->on_garbage = $on_garbage;

		$this->menu = InvMenu::create(VaultInventory::class);
		$this->menu->getInventory()->setVaultData($this);
		$this->menu->setListener([$this, "onInventoryTransaction"]);
		$this->menu->setInventoryCloseListener([$this, "onInventoryClose"]);
		$this->menu->setName(strtr(self::$name_format, [
			"{PLAYER}" => $playername,
			"{NUMBER}" => $number
		]));
	}

	public function onInventoryTransaction(Player $player) : bool{
		return strtolower($this->playername) === $player->getLowerCaseName() || $player->hasPermission("playervaults.others.edit");
	}

	public function onInventoryClose(Player $viewer, VaultInventory $inventory) : void{
		if(empty(array_diff($inventory->getViewers(), [$viewer]))){
			($this->on_garbage)();
		}
	}

	public function getPlayerName() : string{
		return $this->playername;
	}

	public function getNumber() : int{
		return $this->number;
	}

	public function getInventory() : VaultInventory{
		return $this->menu->getInventory();
	}

	public function send(Player $player, ?string $custom_name = null) : void{
		$this->menu->send($player, $custom_name);
	}

	public function read(string $data) : void{
		$contents = [];
		$inventoryTag = self::$nbtSerializer->readCompressed($data)->getListTag(self::TAG_INVENTORY);
		foreach($inventoryTag as $tag){
			$contents[$tag->getByte("Slot")] = Item::nbtDeserialize($tag);
		}

		$this->menu->getInventory()->setContents($contents);
	}

	public function write() : string{
		$contents = [];
		foreach($this->menu->getInventory()->getContents() as $slot => $item){
			$contents[] = $item->nbtSerialize($slot);
		}

		return self::$nbtSerializer->writeCompressed(
			new CompoundTag("", [
				new ListTag(self::TAG_INVENTORY, $contents)
			])
		);
	}
}