<?php

declare(strict_types=1);

namespace muqsit\playervaults\database;

interface DatabaseStmts{

	public const INIT_PLAYER_VAULTS = "playervaults.init";
	public const LOAD_VAULT = "playervaults.load";
	public const SAVE_VAULT = "playervaults.save";
}