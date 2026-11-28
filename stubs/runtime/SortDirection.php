<?php

if (\PHP_VERSION_ID < 80600 && \PHP_VERSION_ID >= 80100) {
	if (enum_exists('SortDirection', false)) {
		return;
	}

	enum SortDirection
	{
		case Ascending;
		case Descending;
	}
}
