<?php declare(strict_types = 1);

namespace PHPStan\Rules\Properties;

use PHPStan\Rules\DeadCode\UnusedPrivatePropertyRule;
use PHPStan\Rules\Gedmo\PropertiesExtension;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<UnusedPrivatePropertyRule>
 */
class MissingGedmoByPhpDocPropertyAssignRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(UnusedPrivatePropertyRule::class);
	}

	protected function getReadWritePropertiesExtensions(): array
	{
		return [
			new PropertiesExtension(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', __DIR__ . '/../../../../tmp')),
		];
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/../../../extension.neon'];
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/gedmo-property-assign-phpdoc.php'], [
			// No errors expected
		]);
	}

}
