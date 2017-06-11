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

class DeleteVaultTask extends AsyncTask{

    private $player;
    private $type;
    private $data;
    private $what;

    public function __construct(string $player, int $type, int $what, $data){
        $this->player = (string) $player;
        if($type === Provider::MYSQL){
            $this->data = (array) $data;
        }else{
            $this->data = (string) $data;
        }
        $this->what = (int) $what;
    }

    public function onRun(){
        switch($this->type){
            case Provider::YAML:
                if(is_file($path = $this->data.$this->player.".yml")){
                    if($this->what === -1){
                        unlink($path);
                    }else{
                        $data = yaml_parse_file($path);
                        unset($data[$this->what]);
                        yaml_emit_file($path, $data);
                    }
                }
                break;
            case Provider::JSON:
                if(is_file($path = $this->data.$this->player.".json")){
                    if($this->what === -1){
                        unlink($path);
                    }else{
                        $data = json_decode(file_get_contents($path), true);
                        unset($data[$this->what]);
                        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
                    }
                }
                break;
            case Provider::MYSQL:
                $data = new \mysqli(...$this->data);
                if($this->what === -1){
                    $stmt = $data->prepare("DELETE FROM vaults WHERE player=?");
                    $stmt->bind_param("s", $this->player);
                    $stmt->execute();
                    $stmt->close();
                }else{
                    $stmt = $data->prepare("DELETE FROM vaults WHERE player=? AND number=?");
                    $stmt->bind_param("si", $this->player, $this->number);
                    $stmt->execute();
                    $stmt->close();
                }
                $data->close();
                break;
        }
    }
}
