<?php declare(strict_types = 1);

use Composer\InstalledVersions;

$includes = [];

$ormVersion = InstalledVersions::getVersion('doctrine/orm');
$hasOrm3 = $ormVersion !== null && strpos($ormVersion, '3.') === 0;
if ($hasOrm3) {
	$includes[] = __DIR__ . '/../phpstan-baseline-orm-3.neon';
} else {
	$includes[] = __DIR__ . '/../phpstan-baseline-orm-2.neon';
}

$config = [];
$config['includes'] = $includes;

return $config;
