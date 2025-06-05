<?php declare(strict_types=1);

use Composer\InstalledVersions;

$includes = [];

if (!InstalledVersions::isInstalled('symfony/doctrine-bridge')) {
	$includes[] = __DIR__ . '/../phpstan-baseline-without-symfony-bridge.neon';
}

$config = [];
$config['includes'] = $includes;

return $config;
