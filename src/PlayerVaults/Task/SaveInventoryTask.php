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

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class SaveInventoryTask extends AsyncTask {

    /** @var string */
    private $player;

    /** @var int */
    private $type;

    /** @var string */
    private $data;

    /** @var string */
    private $contents;

    /** @var int */
    private $number;

    public function __construct(string $player, int $type, $data, int $number, string $contents)
    {
        $this->player = $player;
        $this->data = serialize($data);
        $this->type = $type;
        $this->contents = $contents;
        $this->number = $number;
    }

    public function onRun() : void
    {
        switch($this->type){
            case Provider::YAML:
                if(is_file($path = unserialize($this->data).$this->player.".yml")){
                    $data = yaml_parse_file($path);
                }else{
                    $data = [];
                }
                $data[$this->number] = base64_encode($this->contents);
                yaml_emit_file($path, $data);
                break;
            case Provider::JSON:
                if(is_file($path = unserialize($this->data).$this->player.".json")){
                    $data = json_decode(file_get_contents($path), true) ?? [];
                }else{
                    $data = [];
                }
                $data[$this->number] = base64_encode($this->contents);
                file_put_contents($path, json_encode($data));
                break;
            case Provider::MYSQL:
                $mysql = new \mysqli(...unserialize($this->data));

                $stmt = $mysql->prepare("INSERT INTO playervaults(player, number, inventory) VALUES(?, ?, ?) ON DUPLICATE KEY UPDATE inventory=VALUES(inventory)");
                $stmt->bind_param("sis", $this->player, $this->number, $this->contents);
                $stmt->execute();
                $stmt->close();

                $mysql->close();
                break;
        }
    }

    public function onCompletion(Server $server) : void
    {
        PlayerVaults::getInstance()->getData()->markAsProcessed($this->player, SaveInventoryTask::class);
    }
}
