<?php

declare(strict_types=1);

namespace muqsit\playervaults\vault;

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
use function count;
use function spl_object_id;
use function strtolower;
use function strtr;
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
	private VaultInventoryListener $inventory_listener;

	/** @var array<int, Closure(Vault) : void> */
	private array $on_dispose = [];

	/** @var array<int, VaultAccessor> */
	private array $accessors = [];

	/** @var array<int, int> */
	private array $player_accessor_ids = [];

	public bool $_changed = false;

	public function __construct(
		private string $player_name,
		private int $number
	){
		$this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)
			->setListener(function(InvMenuTransaction $transaction) : InvMenuTransactionResult{
				$player = $transaction->getPlayer();
				if(strtolower($this->player_name) === strtolower($player->getName()) || $player->hasPermission("playervaults.others.edit")){
					$this->_changed = true;
					return $transaction->continue();
				}
				return $transaction->discard();
			})
			->setInventoryCloseListener(function(Player $viewer, Inventory $inventory) : void{
				$id = $viewer->getId();
				$this->release($this->accessors[$this->player_accessor_ids[$id]]);
				unset($this->player_accessor_ids[$id]);
			})
			->setName(self::$name_format === null ? null : strtr(self::$name_format, [
				"{PLAYER}" => $player_name,
				"{NUMBER}" => $number
			]));

		$this->inventory_listener = new VaultInventoryListener($this);
		$this->menu->getInventory()->getListeners()->add($this->inventory_listener);
	}

	/**
	 * @param Closure(Vault) : void $listener
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

	public function access() : VaultAccessor{
		$accessor = new VaultAccessor($this);
		return $this->accessors[spl_object_id($accessor)] = $accessor;
	}

	/**
	 * @param VaultAccessor $viewer
	 * @internal
	 */
	public function release(VaultAccessor $viewer) : void{
		if(isset($this->accessors[$id = spl_object_id($viewer)])){
			$this->accessors[$id]->_destroy();
			unset($this->accessors[$id]);
			if(count($this->accessors) === 0){
				$this->dispose();
			}
		}
	}

	private function dispose() : void{
		foreach($this->on_dispose as $callback){
			$callback($this);
		}

		$this->menu->setListener(InvMenu::readonly());
		$this->menu->setInventoryCloseListener(null);

		$this->menu->getInventory()->getListeners()->remove($this->inventory_listener);
		$this->inventory_listener->destroy();
	}

	/**
	 * @param Player $player
	 * @param string|null $custom_name
	 * @param (Closure(bool) : void)|null $callback
	 */
	public function send(Player $player, ?string $custom_name = null, ?Closure $callback = null) : void{
		$access = $this->access();
		$id = $player->getId();
		$this->menu->send($player, $custom_name, function(bool $success) use($id, $access, $callback) : void{
			if($success){
				$this->player_accessor_ids[$id] = spl_object_id($access);
			}else{
				$access->release();
			}
			if($callback !== null){
				$callback($success);
			}
		});
	}

	/**
	 * @param string $data
	 * @internal
	 */
	public function read(string $data) : void{
		$contents = [];
		$inventoryTag = self::$nbtSerializer->read(zlib_decode($data))->mustGetCompoundTag()->getListTag(self::TAG_INVENTORY);
		/** @var CompoundTag $tag */
		foreach($inventoryTag as $tag){
			$contents[$tag->getByte("Slot")] = Item::nbtDeserialize($tag);
		}

		$inventory = $this->menu->getInventory();
		$listeners = $inventory->getListeners();
		$listeners->remove($this->inventory_listener);
		$inventory->setContents($contents);
		$listeners->add($this->inventory_listener);
	}

	/**
	 * @return string
	 * @internal
	 */
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