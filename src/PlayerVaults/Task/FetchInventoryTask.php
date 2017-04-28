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
    private $spectating;
    private $spectator;

    public function __construct(string $player, int $type, int $number, bool $spectating, string $spectator, $data){
        $this->player = (string) $player;
        if($type === Provider::MYSQL){
            $this->data = (array) $data;
        }else{
            $this->data = (string) $data;
        }
        $this->type = (int) $type;
        $this->number = (int) $number;
        $this->spectating = (bool) $spectating;
        $this->spectator = (string) $spectator;
    }

    public function onRun(){
        $data = [];
        switch($this->type){
            case Provider::YAML:
                $data = yaml_parse_file($this->data)[$this->player][$this->number] ?? [];
                if(!empty($data)){
                    $data = base64_decode($data);
                }
                break;
            case Provider::JSON:
                $data = json_decode(file_get_contents($this->data), true)[$this->player][$this->number] ?? [];
                if(!empty($data)){
                    $data = base64_decode($data);
                }
                break;
            case Provider::MYSQL:
                $data = new \mysqli(...$this->data);
                $query = $data->query("SELECT inventory FROM vaults WHERE player='$this->player' AND number=$this->number");
                if($query === false){
                    $data = [];
                }else{
                    $data = $query->fetch_assoc()["inventory"];
                    if(!empty($data)){
                        $data = base64_decode($data);
                    }
                }
                $query->close();
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
        if($this->spectating){
            $player = $server->getPlayerExact($this->spectator);
        }else{
            $player = $server->getPlayerExact($this->player);
        }
        if($player !== null){
            $player->addWindow(PlayerVaults::getInstance()->getData()->get($player, $this->getResult(), $this->number, $this->spectating));
        }
    }
}