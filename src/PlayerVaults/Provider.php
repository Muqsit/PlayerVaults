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

use muqsit\invmenu\InvMenu;

use PlayerVaults\Task\{DeleteVaultTask, FetchInventoryTask, SaveInventoryTask};

use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\{CompoundTag, ListTag};
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;

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

    /** @var BigEndianNBTStream */
    private static $nbtWriter;

    /** @var array|string */
    private $data;//data for provider

    /** @var Server */
    private $server;

    /** @var int */
    private $type = Provider::JSON;

    /** @var string */
    private $inventoryName = "";

    /** @var string[] */
    private $processing = [];//the vaults that are being saved, for safety

    /** @var InvMenu */
    private $menu;

    public function __construct(int $type)
    {
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

        self::$nbtWriter = new BigEndianNBTStream();

        $this->menu = InvMenu::create(InvMenu::TYPE_CUSTOM, VaultInventory::class)
            ->sessionize()
            ->setInventoryCloseListener([$this, "saveContents"]);
    }

    public function getType() : int
    {
        return $this->type;
    }

    public function markAsProcessed(string $player, string $hash) : void
    {
        if ($this->processing[$player] === $hash) {
            unset($this->processing[$player]);
        }
    }

    private function getInventoryName(int $vaultno) : string
    {
        return str_replace("{VAULTNO}", $vaultno, $this->inventoryName);
    }

    public function setInventoryName(string $name) : void
    {
        $this->inventoryName = $name;
    }

    public function sendContents($player, int $number = 1, ?string $viewer = null) : void
    {
        $name = $player instanceof Player ? $player->getLowerCaseName() : strtolower($player);
        $this->server->getScheduler()->scheduleAsyncTask(new FetchInventoryTask($name, $this->type, $number, $viewer ?? $name, $this->data));
    }

    /**
     * @internal
     */
    public function send(Player $player, array $contents, int $number = 1, ?string $vaultof = null) : void
    {
        $vaultof = $vaultof ?? $player->getLowerCaseName();

        if(isset($this->processing[$vaultof])){
            $player->sendMessage(TF::RED."You cannot open this vault as it is already in use by ".TF::GRAY.$this->processing[$vaultof].TF::RED.".");
            return;
        }

        $this->processing[$vaultof] = $player->getLowerCaseName();

        $inventory = $this->menu->getInventory($player);
        $inventory->setVaultData($vaultof, $number);
        $inventory->setContents($contents);
        $this->menu->setName($this->getInventoryName($number));
        $this->menu->send($player);
    }

    public function saveContents(Player $viewer, VaultInventory $inventory) : void
    {
        //TODO: Allow one viewer process too happen at a time too, just like one VaultInventory::getVaultOf() process.
        $player = $inventory->getVaultOf();

        $contents = $inventory->getContents();
        foreach($contents as $slot => &$item){
            $item = $item->nbtSerialize($slot);
        }

        $contents = self::$nbtWriter->writeCompressed(new CompoundTag("Items", [new ListTag("ItemList", $contents)]));//maybe do compression in SaveInventoryTask?

        $this->processing[$player] = SaveInventoryTask::class;
        $this->server->getScheduler()->scheduleAsyncTask(new SaveInventoryTask($player, $this->type, $this->data, $inventory->getNumber(), $contents));
    }

    public function deleteVault(string $player, int $vault) : void
    {
        $this->processing[$player] = DeleteVaultTask::class;
        $this->server->getScheduler()->scheduleAsyncTask(new DeleteVaultTask($player, $this->type, $vault, $this->data));
    }
}
