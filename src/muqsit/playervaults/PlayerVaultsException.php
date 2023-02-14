<?php

declare(strict_types=1);

namespace muqsit\playervaults;

use Exception;

final class PlayerVaultsException extends Exception{

	public const ERR_GENERIC = 0;
	public const ERR_INVALID_VAULT_NUMBER = 1; // vault number is < 0 or > 255
	public const ERR_UNAUTHORIZED_FOREIGN = 2; // not permitted to access a vault of another user
	public const ERR_UNAUTHORIZED_SELF = 3;    // not permitted to access a vault belonging to ourselves
	public const ERR_VIEWER_NOT_CONNECTED = 4; // viewer of the vault is not / no longer connected
}