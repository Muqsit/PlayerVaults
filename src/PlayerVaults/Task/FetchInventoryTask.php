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
use pocketmine\nbt\NBT;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class FetchInventoryTask extends AsyncTask{

    private $player;
    private $type;
    private $data;
    private $number;
    private $viewer;

    public function __construct(string $player, int $type, int $number, string $viewer, $data){
        $this->player = (string) $player;
        if($type === Provider::MYSQL){
            $this->data = (array) $data;
        }else{
            $this->data = (string) $data;
        }
        $this->type = (int) $type;
        $this->number = (int) $number;
        $this->viewer = (string) $viewer;
    }

    public function onRun(){
        $data = [];
        switch($this->type){
            case Provider::YAML:
                if(!is_file($path = $this->data.$this->player.".yml")){
                    $data = [];
                    break;
                }
                $data = yaml_parse_file($path)[$this->number] ?? [];
                if(!empty($data)){
                    $data = base64_decode($data);
                }
                break;
            case Provider::JSON:
                if(!is_file($path = $this->data.$this->player.".json")){
                    $data = [];
                    break;
                }
                $data = json_decode(file_get_contents($path), true)[$this->number] ?? [];
                if(!empty($data)){
                    $data = base64_decode($data);
                }
                break;
            case Provider::MYSQL:
                $mysql = new \mysqli(...$this->data);
                $stmt = $mysql->prepare("SELECT inventory FROM vaults WHERE player=? AND number=?");
                $stmt->bind_param("si", $this->player, $this->number);
                $stmt->bind_result($data);
                $stmt->execute();
                if(!$stmt->fetch()){
                    $data = [];
                }else{
                    if(!empty($data)){
                        $data = base64_decode($data);
                    }
                }
                $stmt->close();
                $mysql->close();
                break;
        }
        if(empty($data)){
            $this->setResult([]);
        }else{
            $nbt = new NBT(NBT::BIG_ENDIAN);
            $nbt->readCompressed($data);
            $nbt = $nbt->getData();
            $items = $nbt->ItemList ?? [];
            $contents = [];
            if(!empty($items)){
                $items = $items->getValue();
                foreach($items as $slot => $compoundTag){
                    $contents[$slot] = Item::nbtDeserialize($compoundTag);
                }
            }
            $this->setResult($contents);
        }
    }

    public function onCompletion(Server $server){
        $player = $server->getPlayerExact($this->viewer);
        if($player !== null){
            $player->addWindow(PlayerVaults::getInstance()->getData()->get($player, $this->getResult(), $this->number, $this->player));
        }
    }
}
