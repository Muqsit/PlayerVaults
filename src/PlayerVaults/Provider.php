<?php
namespace PlayerVaults;

use PlayerVaults\Task\{FetchInventoryTask, SaveInventoryTask};
use PlayerVaults\Vault\VaultInventory;

use pocketmine\block\Block;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\{ByteTag, CompoundTag, IntTag, ListTag, StringTag};
use pocketmine\Player;
use pocketmine\tile\Tile;

class Provider{

    const INVENTORY_HEIGHT = 2;

    const TYPE_FROM_STRING = [
        'json' => Provider::JSON,
        'yaml' => Provider::YAML,
        'yml' => Provider::YAML,
        'mysql' => Provider::MYSQL
    ];

    const JSON = 0;
    const YAML = 1;
    const MYSQL = 2;
    const UNKNOWN = 3;

    private $data = [];
    private $server = null;
    private $type = Provider::JSON;

    public function __construct(int $type){
        if($type === Provider::UNKNOWN){
            throw new \Exception("Class constant '$type' does not exist. Switching to JSON.");
            $type = Provider::JSON;
        }
        $this->type = $type;

        $core = PlayerVaults::getInstance();
        $this->server = $core->getServer();
        switch($type){
            case Provider::JSON:
                $this->data = $core->getDataFolder().'vaults.json';
                if(!is_file($this->data)){
                    file_put_contents($this->data, json_encode([]));
                }
                break;
            case Provider::YAML:
                $this->data = $core->getDataFolder().'vaults.yml';
                if(!is_file($this->data)){
                    yaml_emit_file($this->data, []);
                }
                break;
            case Provider::MYSQL:
                $this->data = $core->getMysqlData();
                break;
        }
    }

    private function getServer(){
        return $this->server;
    }

    public function sendContents($player, int $number = 1, bool $spectating = false, string $spectator = ""){
        $player = $player instanceof Player ? $player->getLowerCaseName() : strtolower($player);
        $this->getServer()->getScheduler()->scheduleAsyncTask(new FetchInventoryTask($player, $this->type, $number, $spectating, $spectator, $this->data));
    }

    public function get($player, array $contents, int $number = 1, bool $spectating = false){
        $nbt = new CompoundTag("", [
            new StringTag("id", Tile::CHEST),
            new IntTag("x", floor($player->x)),
            new IntTag("y", floor($player->y) + Provider::INVENTORY_HEIGHT),
            new IntTag("z", floor($player->z)),
            new ByteTag("Vault", 1)
        ]);
        $tile = Tile::createTile("Vault", $level = $player->getLevel(), $nbt);

        if(!$spectating){
            $tile->namedtag->VaultNumber = new IntTag("VaultNumber", $number);
        }

        $block = Block::get(Block::CHEST);
        $block->x = floor($tile->x);
        $block->y = floor($tile->y);
        $block->z = floor($tile->z);
        $block->level = $level;
        $block->level->sendBlocks([$player], [$block]);
        $inventory = new VaultInventory($tile);
        $inventory->setContents($contents);
        return $inventory;
    }

    public function saveContents($player, array $contents, int $number = 1){
        $player = $player instanceof Player ? $player->getLowerCaseName() : strtolower($player);
        foreach ($contents as &$item) {
            if ($item->getId() !== 0) {
                $item = $item->nbtSerialize(-1, "Item");
            }
        }

        $nbt = new NBT(NBT::BIG_ENDIAN);
        $tag = new CompoundTag("Items", [
            "ItemList" => new ListTag("ItemList", $contents)
        ]);
        $nbt->setData($tag);
        $contents = base64_encode($nbt->writeCompressed(ZLIB_ENCODING_DEFLATE));

        $this->getServer()->getScheduler()->scheduleAsyncTask(new SaveInventoryTask($player, $this->type, $this->data, $number, $contents));
    }
}