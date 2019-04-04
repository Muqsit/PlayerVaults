<?php

declare(strict_types=1);

namespace muqsit\playervaults\database\utils;

class MySQLBinaryStringParser implements BinaryStringParserInstance{

	public function encode(string $string) : string{
		return $string;
	}

	public function decode(string $string) : string{
		return $string;
	}
}