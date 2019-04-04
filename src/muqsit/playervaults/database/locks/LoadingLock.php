<?php

declare(strict_types=1);

namespace muqsit\playervaults\database\locks;

use muqsit\playervaults\utils\DataConsistencyException;

class LoadingLock implements Lock{

	public function getException() : DataConsistencyException{
		return new DataConsistencyException("This vault is currently loading");
	}
}