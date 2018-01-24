# PlayerVaults
Per-player inventory-based vaults plugin for PocketMine-MP

You may download the **phar file** [here](https://poggit.pmmp.io/p/PlayerVaults).

PlayerVaults is a plugin that allows players on your server to have another online inventory. It works like ender chest inventories EXCEPT you can view your vault inventory anywhere you want.

### Features:
* **Lag-free**: PlayerVaults saves inventory contents asynchronously so the server doesn't need to halt for get-set operations.
* **Less harddrive space**: The inventories are compressed using zlib and takes very less hard drive space. To attain maximum compression, use MySQL. JSON and YAML require the data to be base64-encoded so MySQL takes nearly 66% lesser space than JSON and YAML. base64-encoding is required for JSON and YAML because they aren't binary safe.
* **Fake-inventory mechanisms**: PlayerVaults sends a fake Chest tile and a fake Chest block packet to the client so there's no way for other clients to view or even know of the existence of a fake Chest tile or a fake Chest block.

### How to use:
The usage is very simple. Just execute **/pv [vault-number]** in-game to access your private vault. You can store anything you like in it. It uses the same mechanism of storing and fetching as PocketMine so the items are saved along with their NBT tags.
