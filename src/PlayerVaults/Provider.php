<?php
/*
*
* Copyright (C) 2017 Muqsit Rayyan
*
*    ___ _                                        _ _       
*   / _ \ | __ _ _   _  ___ _ __/\   /\__ _ _   _| | |_ ___ 
*  / /_)/ |/ _" | | | |/ _ \ "__\ \ / / _" | | | | | __/ __|
* / ___/| | (_| | |_| |  __/ |   \ V / (_| | |_| | | |_\__ \
* \/    |_|\__,_|\__, |\___|_|    \_/ \__,_|\__,_|_|\__|___/
*                |___/                                      
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
*
* @author Muqsit Rayyan
* Twiter: http://twitter.com/muqsitrayyan
* GitHub: http://github.com/Muqsit
*
*/
namespace PlayerVaults;

use PlayerVaults\Task\{DeleteVaultTask, FetchInventoryTask, SaveInventoryTask};
use PlayerVaults\Vault\{Vault, VaultInventory};

use pocketmine\block\Block;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
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
    private $inventoryName = "";

    public function __construct(int $type){
        if($type === Provider::UNKNOWN){
            throw new \Exception("Class constant '$type' does not exist. Switching to JSON.");
            $type = Provider::JSON;
        }
        $this->type = $type;

        $core = PlayerVaults::getInstance();
        $this->server = $core->getServer();
        $this->setInventoryName($core->getFromConfig("vaultinv-name") ?? "");

        if(is_file($oldfile = $core->getDataFolder()."vaults.json")){
            $data = json_decode(file_get_contents($oldfile));
            $logger = $core->getLogger();
            foreach($data as $k => $v){
                file_put_contents($core->getDataFolder()."vaults/".$k.".json", json_encode($v, JSON_PRETTY_PRINT));
                $logger->notice("Moved $k's vault data to /vaults.");
            }
            rename($oldfile, $oldfile.".bak");
        }elseif(is_file($oldfile = $core->getDataFolder()."vaults.yml")){
            $data = yaml_parse_file($oldfile);
            $logger = $core->getLogger();
            foreach($data as $k => $v){
                yaml_emit_file($core->getDataFolder()."vaults/".$k.".yml", $v);
                $logger->notice("Moved $k's vault data to /vaults.");
            }
            rename($oldfile, $oldfile.".bak");
        }

        switch($type){
            case Provider::JSON:
            case Provider::YAML:
                $this->data = $core->getDataFolder().'vaults/';
                break;
            case Provider::MYSQL:
                $this->data = $core->getMysqlData();
                break;
        }

    }

    private function getServer(){
        return $this->server;
    }

    private function getInventoryName(int $vaultno) : string{
        return str_replace("{VAULTNO}", $vaultno, $this->inventoryName);
    }

    public function setInventoryName(string $name){
        $this->inventoryName = $name;
    }

    public function sendContents($player, int $number = 1, string $viewer = null){
        $player = $player instanceof Player ? $player->getLowerCaseName() : strtolower($player);
        if($viewer === null){
            $viewer = $player;
        }
        $this->getServer()->getScheduler()->scheduleAsyncTask(new FetchInventoryTask($player, $this->type, $number, $viewer, $this->data));
    }

    public function get(Player $player, array $contents, int $number = 1, string $vaultof = null) : VaultInventory{
        if($vaultof === null){
            $vaultof = $player->getLowerCaseName();
        }
        $tile = Tile::createTile("Vault", $level = $player->getLevel(), new CompoundTag("", [
            new StringTag("id", Tile::CHEST),
            new StringTag("CustomName", $this->getInventoryName($number)),
            new IntTag("x", (int) $player->x),
            new IntTag("y", (int) $player->y + Provider::INVENTORY_HEIGHT),
            new IntTag("z", (int) $player->z),
            new ByteTag("Vault", 1),
            new IntTag("VaultNumber", $number),
            new StringTag("VaultOf", $vaultof)
        ]));

        $block = Block::get(Block::CHEST);
        $block->x = (int) $tile->x;
        $block->y = (int) $tile->y;
        $block->z = (int) $tile->z;
        $block->level = $level;
        $block->level->sendBlocks([$player], [$block]);
        $inventory = new VaultInventory($tile);
        $inventory->setContents($contents);
        $tile->spawnTo($player);
        return $inventory;
    }

    public function saveContents(Vault $tile, array $contents){
        $player = $tile->namedtag["VaultOf"];

        foreach ($contents as &$item) {
            $item = $item->nbtSerialize(-1, "Item");
        }

        $nbt = new NetworkLittleEndianNBTStream();
        $tag = new CompoundTag("Items", [
            "ItemList" => new ListTag("ItemList", $contents)
        ]);
        $nbt->setData($tag);
        $contents = base64_encode($nbt->writeCompressed(ZLIB_ENCODING_DEFLATE));

        $this->getServer()->getScheduler()->scheduleAsyncTask(new SaveInventoryTask($player, $this->type, $this->data, $tile->namedtag["VaultNumber"], $contents));
    }

    public function deleteVault(string $player, int $vault){
        $this->getServer()->getScheduler()->scheduleAsyncTask(new DeleteVaultTask($player, $this->type, $vault, $this->data));
    }
}
