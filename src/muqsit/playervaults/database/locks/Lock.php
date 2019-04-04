<?php

declare(strict_types=1);

namespace muqsit\playervaults\database\locks;

use muqsit\playervaults\utils\DataConsistencyException;

interface Lock{

	public function getException() : DataConsistencyException;
}