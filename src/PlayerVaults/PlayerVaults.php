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
    private $parsedConfig = [];

    public function onEnable(){
        self::$instance = $this;
        $this->getLogger()->notice(implode(TF::RESET.PHP_EOL.TF::YELLOW, [
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
            mkdir($this->getDataFolder());
        }
        if(!is_dir($this->getDataFolder()."vaults")){
            mkdir($this->getDataFolder()."vaults");
        }
        if(!file_exists($this->getDataFolder()."config.yml")){
            $this->saveDefaultConfig();
        }

        $this->updateConfig();
        $this->registerConfig();

        $type = $this->getConfig()->get("provider", "json");
        $type = Provider::TYPE_FROM_STRING[strtolower($type)] ?? Provider::UNKNOWN;
        $this->mysqldata = array_values($this->getConfig()->get("mysql", []));
        $this->maxvaults = $this->getConfig()->get("max-vaults", 25);
        if($type === Provider::MYSQL){
            $mysql = new \mysqli(...$this->mysqldata);
            $db = $this->mysqldata[3];
            $mysql->query("CREATE TABLE IF NOT EXISTS vaults(player VARCHAR(16), inventory TEXT, number TINYINT)");
            $mysql->close();
        }
        $this->data = new Provider($type);

        Tile::registerTile(Vault::class);
    }

    private function updateConfig(){
        $config = $this->getConfig();
        foreach(yaml_parse(stream_get_contents($this->getResource("config.yml"))) as $key => $value){
            if($config->get($key) === false){
                $config->set($key, $value);
            }
        }
        $config->save();
    }

    private function registerConfig(){
        $this->parsedConfig = yaml_parse_file($this->getDataFolder()."config.yml");
    }

    public function getFromConfig($key){
        return $this->parsedConfig[$key] ?? null;
    }

    public function getData() : Provider{
        return $this->data;
    }

    public function getMysqlData() : array{
        return $this->mysqldata;
    }

    public function getMaxVaults() : int{
        return $this->maxvaults;
    }

    public static function getInstance() : self{
        return self::$instance;
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
        if(isset($args[0]) && $args[0] !== "help" && $args[0] !== ""){
            if(is_numeric($args[0])){
                if(strpos($args[0], ".") !== false){
                    $sender->sendMessage(TF::RED."Please insert a valid number.");
                }elseif($args[0] < 1 || $args[0] > $this->getMaxVaults()){
                    $sender->sendMessage(TF::YELLOW."Usage: ".TF::WHITE."/pv <1-".$this->getMaxVaults().">");
                }else{
                    if($sender->y + Provider::INVENTORY_HEIGHT > Level::Y_MAX){
                        $sender->sendMessage(TF::RED."Cannot open vault at this height. Please lower down to at least Y=".Level::Y_MAX - Provider::INVENTORY_HEIGHT);
                    }else{
                        if($sender->hasPermission("playervaults.vault.".$args[0])){
                            $sender->sendMessage(TF::YELLOW."Opening vault ".TF::AQUA."#".$args[0]."...");
                            $this->getData()->sendContents($sender, $args[0]);
                        }else{
                            $sender->sendMessage(TF::RED."You don't have permission to access vault #".$args[0]);
                        }
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
                                $args[2] = $args[2] ?? 1;
                                if(!is_numeric($args[2])){
                                    $sender->sendMessage(TF::RED."Usage: /$cmd of <player> <1-".$this->getMaxVaults().">");
                                    return false;
                                }
                                $this->getData()->sendContents($args[1], $args[2] ?? 1, $sender->getName());
                                $sender->sendMessage(TF::YELLOW."Opening vault ".TF::AQUA."#".($args[2] ?? 1)." of ".($player ?? $args[1])."...");
                            }
                            return true;
                        case "empty":
                            if(!isset($args[1])){
                                $sender->sendMessage(TF::RED."Usage: /$cmd empty <player> <number|all>");
                            }else{
                                if(($player = $this->getServer()->getPlayerExact($args[1])) !== null){
                                    $args[1] = $player->getLowerCaseName();
                                    $player = $player->getName();
                                }
                                if(!isset($args[2]) || ($args[2] != "all" && !is_numeric($args[2]))){
                                    $sender->sendMessage(TF::RED."Usage: /$cmd empty <player> <number|all>");
                                }else{
                                    if((is_numeric($args[2]) && ($args[2] >= 1 || $args[2] <= $this->getMaxVaults())) || $args[2] == "all"){
                                        $this->getData()->deleteVault(strtolower($player ?? $args[1]), $args[2] == "all" ? -1 : $args[2]);
                                        if($args[2] == "all"){
                                            $sender->sendMessage(TF::YELLOW."Deleted all vaults of ".($player ?? $args[1]).".");
                                        }else{
                                            $sender->sendMessage(TF::YELLOW."Deleted ".($player ?? $args[1])."'s vault #".$args[2].".");
                                        }
                                    }else{
                                        $sender->sendMessage(TF::RED."Usage: /$cmd empty ".$args[1]." <1-".$this->getMaxVaults().">");
                                    }
                                }
                            }
                            return true;
                    }
                }
                switch(strtolower($args[0])){
                    case "about":
                        $sender->sendMessage(implode(TF::RESET.PHP_EOL, [
                            TF::GREEN."PlayerVaults v".$this->getDescription()->getVersion()." by ".TF::YELLOW."Muqsit",
                            TF::GREEN."Twitter: ".TF::AQUA."@muqsitrayyan",
                            TF::GREEN."GitHub Repo: ".TF::DARK_PURPLE."http://github.com/Muqsit/PlayerVaults"
                        ]));
                        return true;
                    case "admin":
                        $sender->sendMessage(implode(TF::RESET.PHP_EOL, [
                            TF::GREEN."/$cmd of <player> <number=1> - ".TF::YELLOW."Show <player>'s vault contents.",
                            TF::GREEN."/$cmd empty <player> <number|all> - ".TF::YELLOW."Empty <player>'s vault #number or all their vaults."
                        ]));
                        return true;
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
        return false;
    }
}
