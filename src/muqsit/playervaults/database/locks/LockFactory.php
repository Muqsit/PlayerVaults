<?php

declare(strict_types=1);

namespace muqsit\playervaults\database\locks;

class LockFactory{

	/** @var Lock[] */
	private static $locks = [];

	public static function init() : void{
		self::register(new LoadingLock());
		self::register(new SavingLock());
	}

	public static function register(Lock $lock) : void{
		self::$locks[get_class($lock)] = $lock;
	}

	public static function get(string $lock_class) : Lock{
		return self::$locks[$lock_class];
	}
}