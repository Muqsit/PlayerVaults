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

use PlayerVaults\Vault\Vault;

use pocketmine\command\{Command, CommandSender};
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Tile;
use pocketmine\utils\TextFormat as TF;

class PlayerVaults extends PluginBase{

    private $data = null;
    private $mysqldata = [];
    private static $instance = null;

    public function onEnable(){
        self::$instance = $this;
        $this->getLogger()->notice(implode(TF::RESET.PHP_EOL.TF::AQUA, [
            'Loaded PlayerVaults by Muqsit (Twitter: @muqsitrayyan)',
            '   ___ _                                        _ _       ',
            '  / _ \ | __ _ _   _  ___ _ __/\   /\__ _ _   _| | |_ ___ ',
            ' / /_)/ |/ _" | | | |/ _ \ "__\ \ / / _" | | | | | __/ __|',
            '/ ___/| | (_| | |_| |  __/ |   \ V / (_| | |_| | | |_\__ \ ',
            '\/    |_|\__,_|\__, |\___|_|    \_/ \__,_|\__,_|_|\__|___/',
            '               |___/                                      ',
            ' ',
            'GitHub: http://github.com/Muqsit/PlayerVaults'
        ]));

        if(!is_dir($this->getDataFolder())){
            @mkdir($this->getDataFolder());
        }
        if(!is_file($path = $this->getDataFolder()."config.yml")){
            file_put_contents($path, $this->getResource("config.yml"));
        }

        $type = $this->getConfig()->get("provider", "json");
        $type = Provider::TYPE_FROM_STRING[strtolower($type)] ?? Provider::UNKNOWN;
        $this->mysqldata = array_values($this->getConfig()->get("mysql", []));
        if($type === Provider::MYSQL){
            $mysql = new \mysqli(...$this->mysqldata);
            $db = $this->mysqldata[3];
            $mysql->query("CREATE TABLE IF NOT EXISTS vaults(player VARCHAR(16), inventory TEXT, number TINYINT)");
            $mysql->close();
        }
        $this->data = new Provider($type);

        Tile::registerTile(Vault::class);
        $this->getServer()->getPluginManager()->registerEvents(new EventHandler($this), $this);
    }

    public function getData() : Provider{
        return $this->data;
    }

    public function getMysqlData() : array{
        return $this->mysqldata;
    }

    public static function getInstance() : self{
        return self::$instance;
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args){
        if(isset($args[0]) && $args[0] !== "help"){
            if(is_numeric($args[0])){
                if(strpos($args[0], ".") !== false){
                    $sender->sendMessage(TF::RED."Please insert a valid number.");
                }elseif($args[0] < 1 || $args[0] > 25){
                    $sender->sendMessage(TF::YELLOW."Usage: ".TF::WHITE."/pv <1-25>");
                }else{
                    if($sender->y + Provider::INVENTORY_HEIGHT > Level::Y_MAX){
                        $sender->sendMessage(TF::RED."Cannot open vault at this height. Please lower down to at least Y=".Level::Y_MAX - Provider::INVENTORY_HEIGHT);
                    }else{
                        $sender->sendMessage(TF::YELLOW."Opening vault ".TF::AQUA."#".$args[0]."...");
                        $this->getData()->sendContents($sender, $args[0]);
                    }
                }
            }else{
                if($sender->isOp()){
                    switch(strtolower($args[0])){
                        case "of":
                            if(!isset($args[1])){
                                $sender->sendMessage(TF::RED."Usage: /$cmd of <player> <number=1>");
                            }else{
                                if(($player = $this->getServer()->getPlayer($args[1])) !== null){
                                    $args[1] = $player->getLowerCaseName();
                                    $player = $player->getName();
                                }
                                $this->getData()->sendContents($args[1], $args[2] ?? 1, true, $sender->getName());
                                $sender->sendMessage(TF::YELLOW."Opening vault ".TF::AQUA."#".($args[2] ?? 1)." of ".($player ?? $args[1])."...");
                            }
                            break;
                    }
                }
                switch(strtolower($args[0])){
                    case "about":
                        $sender->sendMessage(implode(TF::RESET.PHP_EOL, [
                            TF::GREEN."PlayerVaults v".$this->getDescription()->getVersion()." by ".TF::YELLOW."Muqsit",
                            TF::GREEN."Twitter: ".TF::AQUA."@muqsitrayyan",
                            TF::GREEN."GitHub Repo: ".TF::DARK_PURPLE."http://github.com/Muqsit/PlayerVaults"
                        ]));
                        break;
                    case "admin":
                        $sender->sendMessage(implode(TF::RESET.PHP_EOL, [
                            TF::GREEN."/$cmd of <player> <number=1> - ".TF::YELLOW."Show <player>'s vault contents."
                        ]));
                        break;
                }
            }
        }else{
            $sender->sendMessage(implode(TF::RESET.PHP_EOL, [
                TF::GREEN."/$cmd <#> - ".TF::YELLOW."Open vault #.",
                TF::GREEN."/$cmd about - ".TF::YELLOW."Get information about plugin."
            ]));
            if($sender->isOp()){
                $sender->sendMessage(TF::RED."Use '/$cmd admin' for a list of admin commands.");
            }
        }
    }
}