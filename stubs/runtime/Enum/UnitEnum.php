<?php

if (\PHP_VERSION_ID < 80100) {
	if (interface_exists('UnitEnum', false)) {
		return;
	}

	interface UnitEnum
	{
		/**
		 * @return static[]
		 */
		public static function cases(): array;
	}
}
