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
namespace PlayerVaults\Task;

use PlayerVaults\{PlayerVaults, Provider};

use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\ListTag;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class FetchInventoryTask extends AsyncTask {

    /** @var string */
    private $player;

    /** @var int */
    private $type;

    /** @var array|string */
    private $data;

    /** @var int */
    private $number;

    /** @var string */
    private $viewer;

    public function __construct(string $player, int $type, int $number, string $viewer, $data)
    {
        $this->player = $player;
        $this->data = serialize($data);
        $this->type = $type;
        $this->number = $number;
        $this->viewer = $viewer;
    }

    public function onRun() : void
    {
        $data = null;

        switch($this->type){
            case Provider::YAML:
                if(!is_file($path = unserialize($this->data).$this->player.".yml")){
                    $data = null;
                    break;
                }
                $data = base64_decode(yaml_parse_file($path)[$this->number] ?? "");
                break;
            case Provider::JSON:
                if(!is_file($path = unserialize($this->data).$this->player.".json")){
                    $data = null;
                    break;
                }
                $data = base64_decode(json_decode(file_get_contents($path), true)[$this->number] ?? "");
                break;
            case Provider::MYSQL:
                $mysql = new \mysqli(...unserialize($this->data));
                $stmt = $mysql->prepare("SELECT inventory FROM playervaults WHERE player=? AND number=?");
                $stmt->bind_param("si", $this->player, $this->number);
                $stmt->bind_result($data);
                $stmt->execute();
                if(!$stmt->fetch()){
                    $data = null;
                }
                $stmt->close();
                $mysql->close();
                break;
        }
        if(!empty($data)){
            $nbt = (new BigEndianNBTStream())->readCompressed($data);
            $items = $nbt->getListTag("ItemList") ?? new ListTag("ItemList");
            $contents = [];
            if(count($items) > 0){
                foreach($items->getValue() as $slot => $compoundTag){
                    $contents[$compoundTag["Slot"] ?? $slot] = Item::nbtDeserialize($compoundTag);
                }
            }
            $this->setResult($contents);
        }else{
            $this->setResult([]);
        }
    }

    public function onCompletion(Server $server) : void
    {
        $player = $server->getPlayerExact($this->viewer);
        if($player !== null){
            PlayerVaults::getInstance()->getData()->send($player, $this->getResult(), $this->number, $this->player);
        }
    }
}
