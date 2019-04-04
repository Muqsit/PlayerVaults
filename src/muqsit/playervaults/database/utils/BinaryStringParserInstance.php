<?php

declare(strict_types=1);

namespace muqsit\playervaults\database\utils;

interface BinaryStringParserInstance{

	public function encode(string $string) : string;

	public function decode(string $string) : string;
}