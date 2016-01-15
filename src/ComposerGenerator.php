<?php

namespace SilverStripe\TravisSupport;

/**
 * Given a set of environment variables, determine the appropriate composer script for this test run
 */
class ComposerGenerator {

	/**
	 * Denote that the build is against a branch
	 */
	const REF_BRANCH = 'Branch';

	/**
	 * Denote that the build is against a tag
	 */
	const REF_TAG = 'Tag';

	/**
	 * Framework version to use (CORE_RELEASE)
	 *
	 * @var string
	 */
	protected $coreVersion = null;

	/**
	 * Version of the module to use
	 *
	 * @var string
	 */
	protected $moduleVersion = null;

	/**
	 * Type of version of this module build, either a branch or tag
	 *
	 * @var string
	 */
	protected $moduleRef = null;

	/**
	 * Array form of framework composer information
	 *
	 * @var array
	 */
	protected $corePackageInfo = array();

	/**
	 * Array form of this module's composer information
	 *
	 * @var array
	 */
	protected $modulePackageInfo = array();

	/**
	 * @var array Packages that form part of a core release of SilverStripe
	 */
	protected $coreModules = array(
		'silverstripe/framework',
		'silverstripe/cms',
		'silverstripe/siteconfig',
		'silverstripe/reports',
	);

	/**
	 *
	 * @param string $coreVersion Framework version to use (CORE_RELEASE)
	 * @param string $moduleVersion Version of the module to use
	 * @param string $moduleRef Type of version of this module build, either a branch or tag
	 * @param array $corePackageInfo Decoded json for core package data
	 * @param array $modulePackageInfo Decoded json for module package data
	 */
	public function __construct($coreVersion, $moduleVersion, $moduleRef, $corePackageInfo = array(),
		$modulePackageInfo = array()
	) {
		$this->coreVersion = $coreVersion;
		$this->moduleVersion = $moduleVersion;
		$this->moduleRef = $moduleRef;
		$this->corePackageInfo = $corePackageInfo;
		$this->modulePackageInfo = $modulePackageInfo;
	}

	/**
	 * Parse a version in a form appropriate for a composer constraint
	 *
	 * @param string $version Version
	 * @param string $ref Reference type
	 * @return string
	 */
	public function parseComposerConstraint($version, $ref) {
		// Tags are given as literal constraints
		if($ref === self::REF_TAG) return $version;

		// Numeric branch (3.1, 3, 3.1.4.0) as (3.1.x-dev, 3.x-dev, 3.1.4.0.x-dev)
		if(preg_match('/^\d+(\.\d+)*$/', $version)) {
			return $version . '.x-dev';
		}

		// Non-numeric branch (master, develop) as dev-master, dev-develop as per composer rules
		return sprintf('dev-%s', $version);
	}

	/**
	 * Gets the version constraint to use for this module. E.g. 3.4.x-dev
	 *
	 * @return string
	 */
	public function getModuleComposerConstraint() {
		return $this->parseComposerConstraint($this->moduleVersion, $this->moduleRef);
	}

	/**
	 * Gets the version constraint to use for the framework.
	 * Note that major or minor versions are assumed to be branches, while
	 * constraints with patch version are assumed to be tags.
	 *
	 * @return string
	 */
	public function getCoreComposerConstraint() {
		$version = $this->parseComposerConstraint($this->coreVersion, self::REF_BRANCH);

		// Respect branch alias in core
		if( isset( $this->corePackageInfo['package']['versions'][$version]['extra']['branch-alias'][$version])) {
			return $this->corePackageInfo['package']['versions'][$version]['extra']['branch-alias'][$version];
		}
		
		return $version;
	}

	/**
	 * Generate the package composer data reperesenting this module, to be included in the root config
	 *
	 * @param string $installFromPath Specify path to install local module from
	 * @return array Composer config in array format
	 */
	public function generatePackageComposerConfig($installFromPath = null) {
		if ($this->isCoreModule($this->modulePackageInfo['name'])) {
			$moduleConstraint = $this->getCoreComposerConstraint();
		}
		else {
			$moduleConstraint = $this->getModuleComposerConstraint();
		}
		
		// Set the module version
		$composerConfig = array_replace_recursive(
			$this->modulePackageInfo,
			array(
				'version' => $moduleConstraint
			)
		);

		// If installing from a local archive, specify it
		if($installFromPath !== null) {
			$composerConfig = array_replace_recursive(
				$composerConfig,
				array(
					'dist' => array(
						'type' => 'tar',
						'url' => "file://{$installFromPath}"
					)
				)
			);
		}
		return $composerConfig;
	}

	/**
	 * Generates a root composer config, linked to a nested sub-package
	 *
	 * @param array $packageComposer Data of sub-package to include
	 * @return array
	 */
	public function generateRootComposerConfig($packageComposer) {
		// Generate the root level composer
		$composer = array(
			'repositories' => array(
				// Require this module via a direct package
				array(
					'type' => 'package',
					'package' => $packageComposer
				)
			),
			'require' => array(
				$packageComposer['name'] => $packageComposer['version']
			),
			// Always include DBs, allow module specific version dependencies though
			'require-dev' => array(
				'silverstripe/postgresql' => '*',
				'silverstripe/sqlite3' => '*',
				'phpunit/PHPUnit' => '~3.7@stable' // Default phpunit version if none specified
			),
			'minimum-stability' => 'dev',
			'config' => array(
				'notify-on-install' => false,
				'process-timeout' => 600, // double default timeout, github archive downloads tend to be slow
			)
		);

		// Promote repositories, require, and require-dev to the top level from the package
		foreach(array('repositories', 'require', 'require-dev') as $section) {
			if(!empty($packageComposer[$section])) {
				$composer[$section] = array_merge(
					$composer[$section],
					$packageComposer[$section]
				);
			}
		}

		return $composer;
	}

	/**
	 * Merge any custom options into the root composer config
	 *
	 * @param array $options Options in array form
	 * @param array $composer Root composer config
	 * @return array Updated composer config
	 */
	public function mergeCustomOptions($options, $composer) {
		// Add a custom requirement
		if(!empty($options['require'])) {
			// this allows for arguments like "silverstripe/behat-extension:dev-master" where "dev-master" would be the branch
			// to use for that requirement. If just specifying "silverstripe/behat-extension" without the separator, default
			// to the branch being "*"

			// If a single value is passed it's a string, if multiple then an array
			// In the string case check for commas, if so assume it's CSV splitting of packages
			// This ensure that $required is always an array
			$requiredPackages = $options['require'];
			if (is_string($options['require'])) {
				// if a comma is present split the CSV
				if (strpos($options['require'], ',') !== false) {
					$options['require'] = split(',', $options['require']);
				}
			} else {
				$requiredPackages = $options['require'];
			}
			$requiredPackages = is_string($options['require']) ? array($options['require']) : $options['require'];

			foreach ($requiredPackages as $requiredPackage) {
				$requireParts = explode(':', $requiredPackage);
				$requireName = $requireParts[0];
				$requireBranch = (isset($requireParts[1])) ? $requireParts[1] : '*';
				$composer['require'][$requireName] = $requireBranch;
			}
		}
		return $composer;
	}

	/**
	 * Generate a composer config appropriate for this build, given the additional constraints
	 *
	 * @param array $options custom options
	 * @param string $installFromPath Specify path to install local module from
	 * @return array Composer config in array format
	 */
	public function generateComposerConfig($options = array(), $installFromPath = null) {
		// Generate the sub-package config
		$moduleComposer = $this->generatePackageComposerConfig($installFromPath);

		// Merge this into the root package
		$rootComposer = $this->generateRootComposerConfig($moduleComposer);

		// Handle custom options
		$rootComposer = $this->mergeCustomOptions($options, $rootComposer);


		// Update framework / cms requirements
		$rootComposer = $this->mergeFrameworkRequirements($rootComposer, $moduleComposer);

		// Update theme requirements
		$rootComposer = $this->mergeThemeRequirements($rootComposer);
		
		return $rootComposer;
	}

	/**
	 * Adjust framework / cms reqirements as necessary
	 * 
	 * @param array $rootComposer
	 * @param array $moduleComposer
	 */
	public function mergeFrameworkRequirements($rootComposer, $moduleComposer) {
		$coreConstraint = $this->getCoreComposerConstraint();

		// Force 2.x framework dependencies to also require cms.
		if($this->coreVersion != 'master'
			&& version_compare($this->coreVersion, '3') < 0
			&& $moduleComposer['name'] == 'silverstripe/framework'
		) {
			$rootComposer['require']['silverstripe/framework'] .= ' as ' . $coreConstraint;
			$rootComposer['require']['silverstripe/cms'] = $coreConstraint;
		}

		// Override module dependencies in order to test with specific core branch.
		// This might be older than the latest permitted version based on the module definition.
		// Its up to the module author to declare compatible CORE_RELEASE values in the .travis.yml.
		// Leave dependencies alone if we're testing either of those modules directly.
		if(isset($rootComposer['require']['silverstripe/framework']) && $moduleComposer['name'] != 'silverstripe/framework') {
			$rootComposer['require']['silverstripe/framework'] = $coreConstraint;
		}
		if(isset($rootComposer['require']['silverstripe/cms']) && $moduleComposer['name'] != 'silverstripe/cms') {
			$rootComposer['require']['silverstripe/cms'] = $coreConstraint;
		}

		return $rootComposer;
	}

	/**
	 * Merge the theme into composer
	 * 
	 * @param array $rootComposer
	 */
	public function mergeThemeRequirements($rootComposer) {
		// Add theme based on version. Important for Behat testing.
		// TODO Determine dependency based on actual composer.json in silverstripe-installer
		if($this->coreVersion == 'master' || version_compare($this->coreVersion, '3') >= 0) {
			$rootComposer['require']['silverstripe-themes/simple'] = '*';
		} else {
			$rootComposer['require']['silverstripe-themes/blackcandy'] = '*';
		}

		return $rootComposer;
	}

	/**
	 * Determine if a package is part of the core release of SilverStripe packages
	 *
	 * @param $packageName string The name of a module to check (eg: silverstripe/framework)
	 * @return bool
	 */
	protected function isCoreModule($packageName) {
		return in_array($packageName, $this->coreModules);
	}

}
