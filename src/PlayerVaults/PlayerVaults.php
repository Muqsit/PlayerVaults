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

use muqsit\invmenu\{InvMenu, InvMenuHandler};

use pocketmine\command\{Command, CommandSender};
use pocketmine\level\Level;
use pocketmine\nbt\{BigEndianNBTStream, NetworkLittleEndianNBTStream};
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Tile;
use pocketmine\utils\TextFormat as TF;

class PlayerVaults extends PluginBase {

    const CONFIG_VERSION = 1.1;

    /** @var Provider */
    private $data;

    /** @var array */
    private $mysqldata = [];

    /** @var PlayerVaults */
    private static $instance;

    /** @var array */
    private $parsedConfig = [];

    public function onEnable() : void
    {
        self::$instance = $this;

        if(!class_exists(InvMenu::class)){
            $this->getLogger()->warning($this->getName()." depends upon 'InvMenu' virion for it's functioning. If you would still like to continue running ".$this->getName()." from source, install the DEVirion plugin and download InvMenu to the /virions folder. Alternatively, you can download the pre-compiled PlayerVaults .phar file from poggit and not worry about installing the dependencies separately.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }

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
            $mysql->query("CREATE TABLE IF NOT EXISTS playervaults(
                player VARCHAR(16) NOT NULL,
                number TINYINT NOT NULL,
                inventory BLOB,
                PRIMARY KEY(player, number)
            )");
            $mysql->close();
        }

        $this->data = new Provider($type);
        $this->checkConfigVersion();
    }

    private function updateConfig() : void
    {
        $config = $this->getConfig();
        foreach(yaml_parse(stream_get_contents($this->getResource("config.yml"))) as $key => $value){
            if($key !== "version" && $config->get($key) === false){
                $config->set($key, $value);
            }
        }
        $config->save();
    }

    private function registerConfig() : void
    {
        $this->parsedConfig = yaml_parse_file($this->getDataFolder()."config.yml");
    }

    private function checkConfigVersion() : void
    {
        $oldVersion = $this->getConfig()->get("version", 1.0);
        if ($oldVersion !== self::CONFIG_VERSION) {
            $this->getLogger()->warning("Updating player vault config version, DO NOT stop the server...");
            $this->doOldVersionChecks($oldVersion);
            $this->getConfig()->set("version", self::CONFIG_VERSION);
            $this->getConfig()->save();
            $this->getLogger()->warning("Player vault config version updated successfully. The server can be safely stopped.");
        }
    }

    private function doOldVersionChecks(float $version) : void
    {
        $logger = $this->getLogger();

        switch ($version) {
            case 1.0://migrate NetworkEndianNBTStream -> BigEndianNBTStream
                switch ($type = $this->data->getType()) {
                    case Provider::JSON:
                    case Provider::YAML:
                        rename($newdir = $this->getDataFolder().'vaults', $dir = $this->getDataFolder()."network-endian-vaults");
                        mkdir($newdir);
                        $oldreader = new NetworkLittleEndianNBTStream();
                        $newreader = new BigEndianNBTStream();
                        foreach(scandir($dir) as $file){
                            if($type === Provider::JSON){
                                $json = json_decode(file_get_contents($dir."/".$file), true);
                                if(empty($json)){
                                    continue;
                                }
                                $data = [];
                                foreach ($json as $vaultNumber => $oldcdata) {
                                    $oldreader->readCompressed(base64_decode($oldcdata));
                                    $newreader->setData($oldreader->getData());
                                    $data[$vaultNumber] = base64_encode($newreader->writeCompressed(ZLIB_ENCODING_DEFLATE));
                                }
                                $logger->info("Updated $file from NetworkEndianNBTStream to BigEndianNBTStream");
                                file_put_contents($newdir."/".$file, json_encode($data));
                            }elseif($type === Provider::YAML){
                                $data = [];
                                $yaml = yaml_parse_file($dir."/".$file, true);
                                if(empty($yaml)){
                                    continue;
                                }
                                foreach ($yaml as $vaultNumber => $oldcdata) {
                                    $oldreader->readCompressed(base64_decode($oldcdata));
                                    $newreader->setData($oldreader->getData());
                                    $data[$vaultNumber] = base64_encode($newreader->writeCompressed(ZLIB_ENCODING_DEFLATE));
                                }
                                $logger->info("Updated $file from NetworkEndianNBTStream to BigEndianNBTStream");
                                yaml_emit_file($newdir."/".$file, $data);
                            }
                        }
                        return;
                    case Provider::MYSQL:
                        $mysql = new \mysqli(...$this->mysqldata);
                        $db = $this->mysqldata[3];
                        $query = $mysql->query("SELECT player, number, FROM_BASE64(inventory) FROM vaults");
                        $oldreader = new NetworkLittleEndianNBTStream();
                        $newreader = new BigEndianNBTStream();
                        while($row = $query->fetch_array(MYSQLI_ASSOC)){
                            $oldreader->readCompressed($row["inventory"]);
                            $newreader->setData($oldreader->getData());
                            $contents = $newreader->writeCompressed(ZLIB_ENCODING_DEFLATE);//no need to base64_encode this because mysql's sexy BLOB type is binary safe

                            $stmt = $mysql->prepare("INSERT INTO playervaults(player, number, inventory) ON DUPLICATE KEY UPDATE inventory=VALUES(inventory)");
                            $stmt->bind_param("sis", $row["player"], $row["number"], $contents);
                            $stmt->execute();
                            $stmt->close();
                            $logger->info("Updated $file from NetworkEndianNBTStream to BigEndianNBTStream");
                        }
                        $query->close();
                        $mysql->query("INSERT INTO playervaults SELECT player, number, FROM_BASE64(inventory) FROM vaults");
                        $mysql->close();
                        return;
                }
                return;
        }
    }

    public function getFromConfig($key)
    {
        return $this->parsedConfig[$key] ?? null;
    }

    public function getData() : Provider
    {
        return $this->data;
    }

    public function getMysqlData() : array
    {
        return $this->mysqldata;
    }

    public function getMaxVaults() : int
    {
        return $this->maxvaults;
    }

    public static function getInstance() : PlayerVaults
    {
        return self::$instance;
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool
    {
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
