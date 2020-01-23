<?php

declare(strict_types=1);

namespace muqsit\playervaults\database;

use muqsit\invmenu\InvMenu;

use muqsit\invmenu\SharedInvMenu;
use pocketmine\inventory\Inventory;
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

	/** @var SharedInvMenu */
	private $menu;

	public function __construct(Database $database, string $playername, int $number){
		$this->playername = $playername;
		$this->number = $number;

		$this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)
			->setListener(function(Player $player) : bool{ return strtolower($this->playername) === $player->getLowerCaseName() || $player->hasPermission("playervaults.others.edit"); })
			->setInventoryCloseListener(function(Player $viewer, Inventory $inventory) use($database) : void{
				$database->saveVault($this);
				if(count(array_diff($inventory->getViewers(), [$viewer])) === 0){
					$database->unloadVault($this);
				}
			})
			->setName(strtr(self::$name_format, [
				"{PLAYER}" => $playername,
				"{NUMBER}" => $number
			]));
	}

	public function getPlayerName() : string{
		return $this->playername;
	}

	public function getNumber() : int{
		return $this->number;
	}

	public function getInventory() : Inventory{
		return $this->menu->getInventory();
	}

	public function send(Player $player, ?string $custom_name = null) : void{
		$this->menu->send($player, $custom_name);
	}

	public function read(string $data) : void{
		$contents = [];
		$inventoryTag = self::$nbtSerializer->readCompressed($data)->getListTag(self::TAG_INVENTORY);
		/** @var CompoundTag $tag */
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