<?php

declare(strict_types=1);

namespace muqsit\playervaults\database;

use muqsit\playervaults\database\locks\Lock;

class VaultHolder{

	/** @var Vault */
	private $vault;

	/** @var callable */
	private $on_garbage;

	/** @var Lock|null */
	private $lock = null;

	public function __construct(string $playername, int $number, callable $on_garbage){
		$this->vault = new Vault($playername, $number, [$this, "onGarbage"]);
		$this->on_garbage = $on_garbage;
	}

	public function onGarbage() : void{
		($this->on_garbage)($this);
	}

	public function getPlayerName() : string{
		return $this->vault->getPlayerName();
	}

	public function getNumber() : int{
		return $this->vault->getNumber();
	}

	public function getVault() : Vault{
		if($this->lock !== null){
			throw $this->lock->getException();
		}

		return $this->vault;
	}

	public function lock(Lock $lock) : void{
		if($this->lock !== null){
			throw new \InvalidArgumentException("Tried to lock an already locked vault holder instance");
		}

		$this->lock = $lock;
	}

	public function unlock(Lock $lock) : void{
		if($this->lock !== $lock){
			throw new \InvalidArgumentException("Tried to unlock a vault holder with the wrong key");
		}

		$this->lock = null;
	}
}