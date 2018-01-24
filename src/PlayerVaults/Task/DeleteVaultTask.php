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

class DeleteVaultTask extends AsyncTask {

    /** @var string */
    private $player;

    /** @var int */
    private $type;

    /** @var Volatile */
    private $data;

    /** @var int */
    private $vaultNumber;

    public function __construct(string $player, int $type, int $vaultNumber, $data)
    {
        $this->player = $player;
        $this->type = $type;//type of provider
        $this->data = serialize($data);//array|string
        $this->vaultNumber = $vaultNumber;
    }

    public function onRun() : void
    {
        $data = unserialize($this->data);
        switch($this->type){
            case Provider::YAML:
                if(is_file($path = $data.$this->player.".yml")){
                    if($this->vaultNumber === -1){
                        unlink($path);
                    }else{
                        $data = yaml_parse_file($path);
                        unset($data[$this->vaultNumber]);
                        yaml_emit_file($path, $data);
                    }
                }
                break;
            case Provider::JSON:
                if(is_file($path = $data.$this->player.".json")){
                    if($this->vaultNumber === -1){
                        unlink($path);
                    }else{
                        $data = json_decode(file_get_contents($path), true);
                        unset($data[$this->vaultNumber]);
                        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
                    }
                }
                break;
            case Provider::MYSQL:
                $data = new \mysqli(...$data);

                if($this->vaultNumber === -1){
                    $stmt = $data->prepare("DELETE FROM playervaults WHERE player=?");
                    $stmt->bind_param("s", $this->player);
                }else{
                    $stmt = $data->prepare("DELETE FROM playervaults WHERE player=? AND number=?");
                    $stmt->bind_param("si", $this->player, $this->vaultNumber);
                }
                $stmt->execute();
                $stmt->close();

                $data->close();
                break;
        }
    }

    public function onCompletion(Server $server) : void
    {
        PlayerVaults::getInstance()->getData()->markAsProcessed($this->player, DeleteVaultTask::class);
    }
}
