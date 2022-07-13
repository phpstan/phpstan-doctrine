<?php

if (\PHP_VERSION_ID < 80100) {
	if (interface_exists('BackedEnum', false)) {
		return;
	}

	interface BackedEnum extends UnitEnum
	{
		/**
		 * @param int|string $value
		 * @return static
		 */
		public static function from($value);

		/**
		 * @param int|string $value
		 * @return ?static
		 */
		public static function tryFrom($value);
	}
}
