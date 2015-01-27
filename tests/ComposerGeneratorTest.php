<?php

use SilverStripe\TravisSupport\ComposerGenerator;

class ComposerGeneratorTest extends PHPUnit_Framework_TestCase {

	/**
	 * Get mock content for framework composer
	 *
	 * @return array
	 */
	protected function getMockFrameworkJson() {
		return json_decode(file_get_contents(__DIR__.'/framework.json'), true);
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
	}

	/**
	 * Tests evaluation of core constraints given aliased composer config
	 */
	public function testCoreConstraintAlias() {
		// dev-master is aliased as 4.0.x-dev
		$generator = new ComposerGenerator('master', null, null, $this->getMockFrameworkJson());
		$this->assertEquals('4.0.x-dev', $generator->getCoreComposerConstraint());

		// 3.x-dev is aliased as 3.2.x-dev
		$generator = new ComposerGenerator('3', null, null, $this->getMockFrameworkJson());
		$this->assertEquals('3.2.x-dev', $generator->getCoreComposerConstraint());

		// 3.1 has no alias
		$generator = new ComposerGenerator('3.1', null, null, $this->getMockFrameworkJson());
		$this->assertEquals('3.1.x-dev', $generator->getCoreComposerConstraint());

		// 3.2 has no alias (and no branch)
		$generator = new ComposerGenerator('3.2', null, null, $this->getMockFrameworkJson());
		$this->assertEquals('3.2.x-dev', $generator->getCoreComposerConstraint());
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
	 * Test custom options works
	 */
	public function testMergeCustomOptions() {
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
}
