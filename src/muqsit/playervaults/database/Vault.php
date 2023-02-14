<?php

declare(strict_types=1);

namespace muqsit\playervaults\database;

use Closure;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use function zlib_decode;
use function zlib_encode;
use const ZLIB_ENCODING_GZIP;

class Vault{

	private const TAG_INVENTORY = "Inventory";

	private static BigEndianNbtSerializer $nbtSerializer;
	private static ?string $name_format = null;

	public static function init() : void{
		self::$nbtSerializer = new BigEndianNbtSerializer();
	}

	public static function setNameFormat(?string $format = null) : void{
		self::$name_format = $format;
	}

	private InvMenu $menu;

	/** @var Closure[] */
	private array $on_inventory_close = [];

	/** @var Closure[] */
	private array $on_dispose = [];

	public function __construct(
		private string $player_name,
		private int $number
	){
		$this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)
			->setListener(function(InvMenuTransaction $transaction) : InvMenuTransactionResult{
				$player = $transaction->getPlayer();
				return strtolower($this->player_name) === strtolower($player->getName()) || $player->hasPermission("playervaults.others.edit") ?
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
					$this->menu->setListener(InvMenu::readonly());
					$this->menu->setInventoryCloseListener(null);
				}
			})
			->setName(self::$name_format === null ? null : strtr(self::$name_format, [
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

	/**
	 * @param Player $player
	 * @param string|null $custom_name
	 * @param (Closure(bool) : void)|null $callback
	 */
	public function send(Player $player, ?string $custom_name = null, ?Closure $callback = null) : void{
		$this->menu->send($player, $custom_name, $callback);
	}

	public function read(string $data) : void{
		$contents = [];
		$inventoryTag = self::$nbtSerializer->read(zlib_decode($data))->mustGetCompoundTag()->getListTag(self::TAG_INVENTORY);
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

		return zlib_encode(self::$nbtSerializer->write(new TreeRoot(CompoundTag::create()
			->setTag(self::TAG_INVENTORY, new ListTag($contents, NBT::TAG_Compound))
		)), ZLIB_ENCODING_GZIP);
	}
}