<?php declare(strict_types = 1);

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;

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

$hasExpressionWithReturnType = $ormVersion !== null && InstalledVersions::satisfies(new VersionParser(), 'doctrine/orm', '>=3.7');
if (!$hasExpressionWithReturnType) {
	// those fixtures implement an interface that does not exist yet, PHPStan cannot ignore that
	$config['parameters']['excludePaths']['analyse'][] = __DIR__ . '/../tests/Platform/ExpressionWithReturnType*.php';
}

return $config;
