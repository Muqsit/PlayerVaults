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

use PlayerVaults\Provider;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class SaveInventoryTask extends AsyncTask{

    private $player;
    private $type;
    private $data;
    private $contents;
    private $number;

    public function __construct(string $player, int $type, $data, int $number, string $contents){
        $this->player = (string) $player;
        if($type === Provider::MYSQL){
            $this->data = (array) $data;
        }else{
            $this->data = (string) $data;
        }
        $this->type = (int) $type;
        $this->contents = (string) $contents;
        $this->number = (int) $number;
    }

    public function onRun(){
        switch($this->type){
            case Provider::YAML:
                if(is_file($path = $this->data.$this->player.".yml")){
                    $data = yaml_parse_file($path);
                }else{
                    $data = [];
                }
                $data[$this->number] = $this->contents;
                yaml_emit_file($path, $data);
                break;
            case Provider::JSON:
                if(is_file($path = $this->data.$this->player.".json")){
                    $data = json_decode(file_get_contents($path), true);
                }else{
                    $data = [];
                }
                $data[$this->number] = $this->contents;
                file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
                break;
            case Provider::MYSQL:
                $mysql = new \mysqli(...$this->data);
                $query = $mysql->query("SELECT player FROM vaults WHERE player='$this->player' AND number=$this->number");
                if($query->num_rows === 0){
                    $mysql->query("INSERT INTO vaults(player, inventory, number) VALUES('$this->player', '$this->contents', $this->number)");
                }else{
                    $mysql->query("UPDATE vaults SET inventory='$this->contents' WHERE player='$this->player' AND number=$this->number");
                }
                $mysql->close();
                break;
        }
    }

    public function onCompletion(Server $server){
    }
}