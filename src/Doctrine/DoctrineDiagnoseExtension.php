<?php declare(strict_types = 1);

namespace PHPStan\Doctrine;

use Composer\InstalledVersions;
use Doctrine\ORM\EntityManagerInterface;
use OutOfBoundsException;
use PHPStan\Command\Output;
use PHPStan\Diagnose\DiagnoseExtension;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use function count;
use function sprintf;

class DoctrineDiagnoseExtension implements DiagnoseExtension
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var DriverDetector */
	private $driverDetector;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		DriverDetector $driverDetector
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->driverDetector = $driverDetector;
	}

	public function print(Output $output): void
	{
		$output->writeLineFormatted(sprintf(
			'<info>Doctrine\'s objectManagerLoader:</info> %s',
			$this->objectMetadataResolver->hasObjectManagerLoader() ? 'In use' : 'No'
		));

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager instanceof EntityManagerInterface) {
			$connection = $objectManager->getConnection();
			$driver = $this->driverDetector->detect($connection);

			$output->writeLineFormatted(sprintf(
				'<info>Detected driver:</info> %s',
				$driver === null ? 'None' : $driver
			));
		}

		$packages = [];
		$candidates = [
			'doctrine/dbal',
			'doctrine/orm',
			'doctrine/common',
			'doctrine/collections',
			'doctrine/persistence',
		];
		foreach ($candidates as $package) {
			try {
				$installedVersion = InstalledVersions::getPrettyVersion($package);
			} catch (OutOfBoundsException $e) {
				continue;
			}

			if ($installedVersion === null) {
				continue;
			}

			$packages[$package] = $installedVersion;
		}

		if (count($packages) > 0) {
			$output->writeLineFormatted('<info>Installed Doctrine packages:</info>');
			foreach ($packages as $package => $version) {
				$output->writeLineFormatted(sprintf('%s: %s', $package, $version));
			}
		}

		$output->writeLineFormatted('');
	}

}
