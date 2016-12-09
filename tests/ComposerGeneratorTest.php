<?php

use SilverStripe\TravisSupport\ComposerGenerator;

class ComposerGeneratorTest extends PHPUnit_Framework_TestCase {

	/**
	 * Get mock content for framework composer
	 *
	 * @return array
	 */
	protected function getMockFrameworkJson() {
		return $this->getMockModuleJson('framework');
	}

	/**
	 * Get mock content for module composer
	 *
	 * @param string $name
	 * @return array
	 */
	protected function getMockModuleJson($name) {
		return json_decode(file_get_contents(__DIR__.'/'.$name.'.json'), true);
	}

	/**
	 * Test module and core composer evaluation
	 */
	public function testCoreModuleConstraint() {
		// Test module / core constraints
		$generator = new ComposerGenerator('master', '1.1', ComposerGenerator::REF_BRANCH);
		$this->assertEquals('1.1.x-dev', $generator->getModuleComposerConstraint());
		$this->assertEquals('dev-master', $generator->getCoreComposerConstraint());

		$generator = new ComposerGenerator('3', '1', ComposerGenerator::REF_BRANCH);
		$this->assertEquals('1.x-dev', $generator->getModuleComposerConstraint());
		$this->assertEquals('3.x-dev', $generator->getCoreComposerConstraint());

		$generator = new ComposerGenerator('3.1', '1.1.0', ComposerGenerator::REF_BRANCH);
		$this->assertEquals('1.1.0.x-dev', $generator->getModuleComposerConstraint());
		$this->assertEquals('3.1.x-dev', $generator->getCoreComposerConstraint());

		$generator = new ComposerGenerator('3.2', '1.1.0', ComposerGenerator::REF_TAG);
		$this->assertEquals('1.1.0', $generator->getModuleComposerConstraint());
		$this->assertEquals('3.2.x-dev', $generator->getCoreComposerConstraint());

		$generator = new ComposerGenerator('master', '1.1', ComposerGenerator::REF_BRANCH);
		$generator->setCoreAlias('2.0.x-dev');
		$this->assertEquals('1.1.x-dev', $generator->getModuleComposerConstraint());
		$this->assertEquals('dev-master as 2.0.x-dev', $generator->getCoreComposerConstraint());
	}

	/**
	 * Tests evaluation of core constraints given aliased composer config
	 */
	public function testCoreConstraintAlias() {
		// dev-master is aliased as 4.0.x-dev
		$generator = new ComposerGenerator('master', null, null, $this->getMockFrameworkJson());
		$this->assertEquals('4.0.x-dev', $generator->getCoreComposerConstraint());

		// 3.x-dev is aliased as 3.6.x-dev
		$generator = new ComposerGenerator('3', null, null, $this->getMockFrameworkJson());
		$this->assertEquals('3.6.x-dev', $generator->getCoreComposerConstraint());

		// 3.1 has no branch or alias - fallback to latest release
		$generator = new ComposerGenerator('3.1', null, null, $this->getMockFrameworkJson());
		$this->assertEquals('3.1.21', $generator->getCoreComposerConstraint());

		// 3.4 has no alias
		$generator = new ComposerGenerator('3.4', null, null, $this->getMockFrameworkJson());
		$this->assertEquals('3.4.x-dev', $generator->getCoreComposerConstraint());
	}

	public function testCoreInstallerConstraint() {
		$generator = new ComposerGenerator(
			'3.3',
			null,
			null,
			$this->getMockModuleJson('installer')
		);

		$this->assertEquals('3.3.4', $generator->getCoreComposerConstraint());
	}

	/**
	 * Test constraint evaluation
	 */
	public function testVersionConstraints() {
		$generator = new ComposerGenerator(null, null, null);

		// Test branch evaluation
		$this->assertEquals(
			'1.1.0.2.x-dev',
			$generator->parseComposerConstraint('1.1.0.2', ComposerGenerator::REF_BRANCH)
		);
		$this->assertEquals(
			'1.1.0.x-dev',
			$generator->parseComposerConstraint('1.1.0', ComposerGenerator::REF_BRANCH)
		);
		$this->assertEquals(
			'1.1.x-dev',
			$generator->parseComposerConstraint('1.1', ComposerGenerator::REF_BRANCH)
		);
		$this->assertEquals(
			'1.x-dev',
			$generator->parseComposerConstraint('1', ComposerGenerator::REF_BRANCH)
		);
		$this->assertEquals(
			'dev-master',
			$generator->parseComposerConstraint('master', ComposerGenerator::REF_BRANCH)
		);
		$this->assertEquals(
			'dev-develop',
			$generator->parseComposerConstraint('develop', ComposerGenerator::REF_BRANCH)
		);
		$this->assertEquals(
			'dev-post-2.4',
			$generator->parseComposerConstraint('post-2.4', ComposerGenerator::REF_BRANCH)
		);

		// Test tag evaluation
		$this->assertEquals(
			'1.1.0.2',
			$generator->parseComposerConstraint('1.1.0.2', ComposerGenerator::REF_TAG)
		);
		$this->assertEquals(
			'1.1.0',
			$generator->parseComposerConstraint('1.1.0', ComposerGenerator::REF_TAG)
		);
		$this->assertEquals(
			'1.1',
			$generator->parseComposerConstraint('1.1', ComposerGenerator::REF_TAG)
		);
		$this->assertEquals(
			'1',
			$generator->parseComposerConstraint('1', ComposerGenerator::REF_TAG)
		);
		$this->assertEquals(
			'master',
			$generator->parseComposerConstraint('master', ComposerGenerator::REF_TAG)
		);
		$this->assertEquals(
			'develop',
			$generator->parseComposerConstraint('develop', ComposerGenerator::REF_TAG)
		);
		$this->assertEquals(
			'post-2.4',
			$generator->parseComposerConstraint('post-2.4', ComposerGenerator::REF_TAG)
		);
	}

	/**
	 * Test that the package sub-section of the composer config is generated properly
	 */
	public function testGeneratePackageConfig() {
		$frameworkComposer = $this->getMockFrameworkJson();

		// Test subsites/master (1.1) vs framework/3 (3.2)
		$generator = new ComposerGenerator(
			'3',
			'master',
			ComposerGenerator::REF_BRANCH,
			$frameworkComposer,
			$this->getMockModuleJson('subsites-master')
		);

		$result = $generator->generatePackageComposerConfig('/home/root/builds/ss/subsites.tar');

		$this->assertEquals(
			array(
				'name' => 'silverstripe/subsites',
				'type' => 'silverstripe-module',
				'require' => array(
					'silverstripe/framework' => '~3.2',
					'silverstripe/cms' => '~3.2'
				),
				'extra' => array(
					'branch-alias' => array(
						'dev-master' => '1.1.x-dev'
					)
				),
				'version' => 'dev-master',
				'dist' => array(
					'type' => 'tar',
					'url' => 'file:///home/root/builds/ss/subsites.tar'
				)
			),
			$result
		);

		// Test subsites/1.0 vs framework/3.1 (3.1)
		$generator = new ComposerGenerator(
			'3.1',
			'1.0',
			ComposerGenerator::REF_BRANCH,
			$frameworkComposer,
			$this->getMockModuleJson('subsites-1.0')
		);

		$result = $generator->generatePackageComposerConfig('/home/root/builds/ss/subsites.tar');
		$this->assertEquals(
			array(
				'name' => 'silverstripe/subsites',
				'type' => 'silverstripe-module',
				'require' => array(
					'silverstripe/framework' => '~3.1.0',
					'silverstripe/cms' => '~3.1.0'
				),
				'version' => '1.0.x-dev',
				'dist' => array(
					'type' => 'tar',
					'url' => 'file:///home/root/builds/ss/subsites.tar'
				)
			),
			$result
		);
	}

	public function testGetDistLocationForVersion() {
		$generator = new ComposerGenerator(
			'3.1',
			'1.0',
			ComposerGenerator::REF_BRANCH,
			$this->getMockFrameworkJson()
		);
		$this->assertEquals("https://api.github.com/repos/silverstripe/silverstripe-framework/zipball/5f5d682176fa3e27dc227465dba516d3cc0d29af", $generator->getDistLocationForVersion('3.x-dev'));
		$this->assertEquals("https://api.github.com/repos/silverstripe/silverstripe-framework/zipball/5f5d682176fa3e27dc227465dba516d3cc0d29af", $generator->getDistLocationForVersion('3.6.x-dev'));
	}

	/**
	 * Test that the package sub-section of the composer config is generated properly
	 */
	public function testGeneratePackageConfigCoreModule() {
		$frameworkComposer = $this->getMockFrameworkJson();

		// Test subsites/master (1.1) vs framework/3 (3.2)
		$generator = new ComposerGenerator(
			'3',
			'master',
			ComposerGenerator::REF_BRANCH,
			$frameworkComposer,
			$this->getMockModuleJson('silverstripe-reports')
		);

		$result = $generator->generatePackageComposerConfig('/home/root/builds/ss/subsites.tar');
		$expected = array(
			'name' => 'silverstripe/reports',
			'type' => 'silverstripe-module',
			'homepage' => 'http://silverstripe.org',
			'license' => 'BSD-3-Clause',
			'keywords' => array(
				'0' => 'silverstripe',
				'1' => 'cms',
				'2' => 'reports'
			),
			'authors' => array(
				'0' => array(
					'name' => 'SilverStripe',
					'homepage' => 'http://silverstripe.com'
				),
				'1' => array(
					'name' => 'The SilverStripe Community',
					'homepage' => 'http://silverstripe.org'
				),
			),
			'require' => array(
				'php' => '>=5.3.3',
				'silverstripe/framework' => '>=3.1.x-dev'
			),
			'extra' => array(
				'branch-alias' => array(
					'dev-master' => '4.0.x-dev'
				),
			),
			'version' => '3.6.x-dev',
			'dist' => array(
				'type' => 'tar',
				'url' => 'file:///home/root/builds/ss/subsites.tar'
			),
		);
		$this->assertEquals(
			$expected,
			$result
		);

		// Test subsites/1.0 vs framework/3.1 (3.1)
		$generator = new ComposerGenerator(
			'3.1',
			'1.0',
			ComposerGenerator::REF_BRANCH,
			$frameworkComposer,
			$this->getMockModuleJson('subsites-1.0')
		);

		$result = $generator->generatePackageComposerConfig('/home/root/builds/ss/subsites.tar');
		$this->assertEquals(
			array(
				'name' => 'silverstripe/subsites',
				'type' => 'silverstripe-module',
				'require' => array(
					'silverstripe/framework' => '~3.1.0',
					'silverstripe/cms' => '~3.1.0'
				),
				'version' => '1.0.x-dev',
				'dist' => array(
					'type' => 'tar',
					'url' => 'file:///home/root/builds/ss/subsites.tar'
				)
			),
			$result
		);
	}

	/*
	Test a condition where framework < 3 as the module required
	 */
	public function testMergeFramework24() {
		$frameworkComposer = $this->getMockFrameworkJson();

		// Test subsites/master (1.1) vs framework/3 (3.2)
		$generator = new ComposerGenerator(
			'2,4',
			'master',
			ComposerGenerator::REF_BRANCH,
			$frameworkComposer,
			$this->getMockModuleJson('silverstripe-framework')
		);

		$result = $generator->generateComposerConfig('/home/root/builds/ss/subsites.tar');
		$expected = array(
			'repositories' => array(
				'0' => array(
					'type' => 'package',
					'package' => array(
						'name' => 'silverstripe/framework',
						'type' => 'silverstripe-module',
						'description' => 'The SilverStripe framework',
						'homepage' => 'http://silverstripe.org',
						'license' => 'BSD-3-Clause',
						'keywords' => array(
							'0' => 'silverstripe',
							'1' => 'framework'
						),
						'authors' => array(
							'0' => array(
								'name' => 'SilverStripe',
								'homepage' => 'http://silverstripe.com'
							),
							'1' => array(
								'name' => 'The SilverStripe Community',
								'homepage' => 'http://silverstripe.org'
							),
						),
						'require' => array(
							'php' => '>=5.5.0',
							'composer/installers' => '~1.0',
							'monolog/monolog' => '~1.11',
							'league/flysystem' => '~1.0.12',
							'symfony/yaml' => '~2.7'
						),
						'require-dev' => array(
							'phpunit/PHPUnit' => '~3.7'
						),
						'extra' => array(
							'branch-alias' => array(
								'dev-master' => '4.0.x-dev'
							),
						),
						'autoload' => array(
							'classmap' => array(
								'0' => 'tests/behat/features/bootstrap'
							),
						),
						'version' => 'dev-2,4'
					),
				),
			),
			'require' => array(
				'silverstripe/framework' => 'dev-2,4 as dev-2,4',
				'php' => '>=5.5.0',
				'composer/installers' => '~1.0',
				'monolog/monolog' => '~1.11',
				'league/flysystem' => '~1.0.12',
				'symfony/yaml' => '~2.7',
				'silverstripe/cms' => 'dev-2,4',
				'silverstripe-themes/blackcandy' => '*'
			),
			'require-dev' => array(
				'silverstripe/postgresql' => '*',
				'silverstripe/sqlite3' => '*',
				'phpunit/PHPUnit' => '~3.7'
			),
			'minimum-stability' => 'dev',
			'config' => array(
				'notify-on-install' => '',
				'process-timeout' => '600'
			),
		);


		$this->assertEquals(
			$expected,
			$result
		);

		// Test subsites/1.0 vs framework/3.1 (3.1)
		$generator = new ComposerGenerator(
			'3.1',
			'1.0',
			ComposerGenerator::REF_BRANCH,
			$frameworkComposer,
			$this->getMockModuleJson('subsites-1.0')
		);

		$result = $generator->generatePackageComposerConfig('/home/root/builds/ss/subsites.tar');
		$this->assertEquals(
			array(
				'name' => 'silverstripe/subsites',
				'type' => 'silverstripe-module',
				'require' => array(
					'silverstripe/framework' => '~3.1.0',
					'silverstripe/cms' => '~3.1.0'
				),
				'version' => '1.0.x-dev',
				'dist' => array(
					'type' => 'tar',
					'url' => 'file:///home/root/builds/ss/subsites.tar'
				)
			),
			$result
		);
	}

	/**
	 * Test that requirements from packaged composer are copied to root level
	 */
	public function testRootRequirements() {
		$frameworkComposer = $this->getMockFrameworkJson();

		// Test sitetree/master vs framework/master
		$generator = new ComposerGenerator(
			'master',
			'master',
			ComposerGenerator::REF_BRANCH,
			$frameworkComposer,
			$this->getMockModuleJson('sitetree')
		);
		$moduleComposer = $generator->generatePackageComposerConfig('/home/root/builds/ss/subsites.tar');
		$root = $generator->generateRootComposerConfig($moduleComposer);

		// Has requirements
		$this->assertEquals($root['require']['silverstripe/cms'], '~3.1');
		$this->assertEquals($root['require']['silverstripe/googlesitemaps'], '*');

		// has require-dev
		$this->assertEquals($root['require-dev']['tractorcow/testcase'], '1.x-dev');

		// Has repository
		$this->assertContains(
			array(
				'type' => 'vcs',
				'url' => 'https://github.com/tractorcow/testcase'
			),
			$root['repositories']
		);
	}

	/**
	 * Test custom options works with one package required
	 */
	public function testMergeCustomOptionsArrayNoneRequired() {
		$generator = new ComposerGenerator('master', 'master', ComposerGenerator::REF_BRANCH);

		$base = array(
			'require' => array(
				'silverstripe/framework' => '~3.1'
			)
		);
		$this->assertEquals(
			array(
				'require' => array(
					'silverstripe/framework' => '~3.1'
				)
			),
			$generator->mergeCustomOptions(array(), $base)
		);

		$this->assertEquals(
			array(
				'require' => array(
					'silverstripe/framework' => '~3.1'
				)
			),
			$generator->mergeCustomOptions('', $base)
		);
	}


	/**
	 * Test custom options works with one package required
	 */
	public function testMergeCustomOptionsArrayAndStringOneRequired() {
		$generator = new ComposerGenerator('master', 'master', ComposerGenerator::REF_BRANCH);

		$base = array(
			'require' => array(
				'silverstripe/framework' => '~3.1'
			)
		);
		$this->assertEquals(
			array(
				'require' => array(
					'silverstripe/framework' => '~3.1',
					'silverstripe/subsites' => 'dev-master'
				)
			),
			$generator->mergeCustomOptions(array('require' => 'silverstripe/subsites:dev-master'), $base)
		);
		$this->assertEquals(
			array(
				'require' => array(
					'silverstripe/framework' => '~3.1',
					'silverstripe/translatable' => '*'
				)
			),
			$generator->mergeCustomOptions(array('require' => 'silverstripe/translatable'), $base)
		);
	}

	/**
	 * Test custom options works when an array of required packages is provided
	 */
	public function testMergeCustomOptionsArrayMoreThanOneRequired() {
		$generator = new ComposerGenerator('master', 'master', ComposerGenerator::REF_BRANCH);

		$base = array(
			'require' => array(
				'silverstripe/framework' => '~3.1'
			)
		);

		$requiredPackages = array(
			'silverstripe/subsites:dev-master',
			'silverstripe/comments:2.0.2'
		);
		$this->assertEquals(
			array(
				'require' => array(
					'silverstripe/framework' => '~3.1',
					'silverstripe/subsites' => 'dev-master',
					'silverstripe/comments' => '2.0.2'
				)
			),
			$generator->mergeCustomOptions(array('require' => $requiredPackages), $base)
		);

		$requiredPackages = array(
			'silverstripe/subsites:dev-master',
			'silverstripe/comments:2.0.2',
			'silverstripe/tagfield:1.2.1'
		);
		$this->assertEquals(
			array(
				'require' => array(
					'silverstripe/framework' => '~3.1',
					'silverstripe/subsites' => 'dev-master',
					'silverstripe/comments' => '2.0.2',
					'silverstripe/tagfield' => '1.2.1'
				)
			),
			$generator->mergeCustomOptions(array('require' => $requiredPackages), $base)
		);
	}

	/**
	 * Test custom options works when an array of required packages is provided
	 */
	public function testMergeCustomOptionsStringMoreThanOneRequired() {
		$generator = new ComposerGenerator('master', 'master', ComposerGenerator::REF_BRANCH);

		$base = array(
			'require' => array(
				'silverstripe/framework' => '~3.1'
			)
		);

		// Expressed as CSV instead of separate --require options
		$requiredPackages = 'silverstripe/subsites:dev-master,silverstripe/comments:2.0.2';
		$this->assertEquals(
			array(
				'require' => array(
					'silverstripe/framework' => '~3.1',
					'silverstripe/subsites' => 'dev-master',
					'silverstripe/comments' => '2.0.2'
				)
			),
			$generator->mergeCustomOptions(array('require' => $requiredPackages), $base)
		);

		$requiredPackages = 'silverstripe/subsites:dev-master,silverstripe/comments:2.0.2,silverstripe/tagfield:1.2.1';
		$this->assertEquals(
			array(
				'require' => array(
					'silverstripe/framework' => '~3.1',
					'silverstripe/subsites' => 'dev-master',
					'silverstripe/comments' => '2.0.2',
					'silverstripe/tagfield' => '1.2.1'
				)
			),
			$generator->mergeCustomOptions(array('require' => $requiredPackages), $base)
		);
	}

	/**
	 * Test custom options works when an array of required packages is provided
	 */
	public function testGenerateConfig() {
		$frameworkComposer = $this->getMockFrameworkJson();

		$generator = new ComposerGenerator(
			'master',
			'master',
			ComposerGenerator::REF_BRANCH,
			$frameworkComposer,
			$this->getMockModuleJson('subsites-master')
		);

		$base = array(
			'require' => array(
				'silverstripe/framework' => '~3.1'
			)
		);

		$requiredPackages = array(
			'silverstripe/subsites:dev-master',
			'silverstripe/comments:2.0.2',
			'silverstripe/tagfield:1.2.1'
		);

		$options = array(
			'require' => $requiredPackages,
			'source' => '/home/user/checkout/travis/silverstripe/comments',
 			'target' => '/home/user/builds/ss'
		);

		$expected = array(
			'repositories' => array(
				0 => array(
					'type' => 'package',
					'package' => array(
						'name' => 'silverstripe/subsites',
						'type' => 'silverstripe-module',
						'require' => array(
							'silverstripe/framework' => '~3.2',
							'silverstripe/cms' => '~3.2'
						),
						'extra' => array(
							'branch-alias' => array('dev-master' => '1.1.x-dev')
						),
						'version' => 'dev-master'
					)
				)
			),
			'require' => array(
				'silverstripe/subsites' => 'dev-master',
				'silverstripe/framework' => '4.0.x-dev',
				'silverstripe/cms' => '4.0.x-dev',
				'silverstripe/comments' => '2.0.2',
				'silverstripe/tagfield' => '1.2.1',
				'silverstripe-themes/simple' => '*'
			),
			'require-dev' => array(
				'silverstripe/postgresql' => '*',
				'silverstripe/sqlite3' => '*',
				'phpunit/PHPUnit' => '~3.7@stable'
			),
			'minimum-stability' => 'dev',
			'config' => array(
				'notify-on-install' => false,
				'process-timeout' => 600
			)
		);
		$this->assertEquals($expected, $generator->generateComposerConfig($options));
	}
}
