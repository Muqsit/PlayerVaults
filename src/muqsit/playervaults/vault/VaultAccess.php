<?php

declare(strict_types=1);

namespace muqsit\playervaults\vault;

final class VaultAccess{

	public function __construct(
		/** @readonly */ public Vault $vault
	){}

	public function release() : void{
		$this->vault->release($this);
	}

	/**
	 * @internal
	 */
	public function _destroy() : void{
		unset($this->vault);
	}
}