<?php

declare(strict_types=1);

namespace muqsit\playervaults\database;

use Closure;
use muqsit\invmenu\InvMenu;

use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
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
	private $player_name;

	/** @var int */
	private $number;

	/** @var InvMenu */
	private $menu;

	/** @var Closure[] */
	private $on_inventory_close = [];

	/** @var Closure[] */
	private $on_dispose = [];

	public function __construct(string $player_name, int $number){
		$this->player_name = $player_name;
		$this->number = $number;

		$this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)
			->setListener(function(InvMenuTransaction $transaction) : InvMenuTransactionResult{
				$player = $transaction->getPlayer();
				return strtolower($this->player_name) === $player->getLowerCaseName() || $player->hasPermission("playervaults.others.edit") ?
					$transaction->continue() :
					$transaction->discard();
			})
			->setInventoryCloseListener(function(Player $viewer, Inventory $inventory) : void{
				foreach($this->on_inventory_close as $callback){
					$callback($this);
				}
				if(count(array_diff($inventory->getViewers(), [$viewer])) === 0){
					foreach($this->on_dispose as $callback){
						$callback($this);
					}
				}
			})
			->setName(strtr(self::$name_format, [
				"{PLAYER}" => $player_name,
				"{NUMBER}" => $number
			]));
	}

	/**
	 * @param Closure $listener
	 *
	 * @phpstan-param Closure(Vault) : void $listener
	 */
	public function addInventoryCloseListener(Closure $listener) : void{
		$this->on_inventory_close[spl_object_id($listener)] = $listener;
	}

	/**
	 * @param Closure $listener
	 *
	 * @phpstan-param Closure(Vault) : void $listener
	 */
	public function addDisposeListener(Closure $listener) : void{
		$this->on_dispose[spl_object_id($listener)] = $listener;
	}

	public function getPlayerName() : string{
		return $this->player_name;
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