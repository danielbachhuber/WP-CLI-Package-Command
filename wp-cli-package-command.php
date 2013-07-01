<?php
/**
 * Browse, install, update, and uninstall WP-CLI community packages.
 */
use \Composer\Factory;
use \Composer\IO\NullIO;
use \Composer\Json\JsonFile;
use \Composer\Json\JsonManipulator;
use \Composer\Package;
use \Composer\Repository;
use \Composer\Repository\RepositoryManager;
use \Composer\Util\Filesystem;

class WP_CLI_Package_Command extends WP_CLI_Command {

	private $fields = array(
			'name',
			'description',
			'authors',
		);

	/**
	 * Browse available WP-CLI community packages.
	 * 
	 * @subcommand browse
	 */
	public function browse() {

	}

	/**
	 * Install a WP-CLI community package.
	 * 
	 * @subcommand install
	 * @synopsis <package> [--version=<version>]
	 */
	public function install( $args, $assoc_args ) {

		list( $package ) = $args;

		$defaults = array(
				'version' => 'dev-master',
			);
		$assoc_args = array_merge( $defaults, $assoc_args );

	}

	/**
	 * List installed WP-CLI community packages.
	 * 
	 * @subcommand list
	 * @synopsis [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {

		$defaults = array(
			'fields'    => implode( ',', $this->fields ),
			'format'    => 'table'
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$packages = array();
		foreach( $this->get_installed_packages() as $package ) {

			$package_output = new stdClass;
			$package_output->name = $package->getName();
			$package_output->description = $package->getDescription();
			$package_output->authors = implode( ',', wp_list_pluck( (array)$package->getAuthors(), 'name' ) );
			$packages[] = $package_output;
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $packages, $assoc_args['fields'] );
	}

	/**
	 * Uninstall a WP-CLI community package.
	 * 
	 * @subcommand uninstall
	 * @synopsis <package-name>
	 */
	public function uninstall( $args ) {

		list( $package_name ) = $args;

		$composer = $this->get_composer();
		if ( false === ( $package = $this->get_installed_package_by_name( $package_name ) ) )
			WP_CLI::error( "Package not installed." );

		$composer_json_obj = $this->get_composer_json();

		// Remove the 'require' from composer.json
		$contents = file_get_contents( $composer_json_obj->getPath() );
		$manipulator = new JsonManipulator( $contents );
		$manipulator->removeSubNode( 'require', $package_name );
		file_put_contents( $composer_json_obj->getPath(), $manipulator->getContents() );

		// Delete the directory
		$filesystem = new Filesystem;
		$package_path = getcwd() . '/' . $composer->getConfig()->get('vendor-dir') . '/' . $package->getName();
		$filesystem->removeDirectory( $package_path );

		// Reset Composer and regenerate the auto-loader
		$composer = $this->get_composer();
		$this->regenerate_autoloader();

		WP_CLI::success( "Uninstalled package." );
	}

	/**
	 * Check whether a package is a WP-CLI community package
	 * WP-CLI community packages always start with 'wp-cli'
	 */
	private function is_community_package( $package ) {
		return false !== stripos( $package->getName(), '/wp-cli' );
	}

	/**
	 * Get a Composer instance.
	 */
	private function get_composer() {

		$this->set_composer_json_path( WP_CLI\Utils\find_file_upward( 'composer.json', WP_CLI_ROOT ) );
	
		// Composer's auto-load generating code makes some assumptions about where
		// the 'vendor-dir' is, and where Composer is running from.	
		// Best to just pretend we're installing a package from ~/.composer or similar
		chdir( pathinfo( $this->get_composer_json_path(), PATHINFO_DIRNAME ) );

		try {
			$composer = Factory::create( new NullIO, $this->get_composer_json_path() );
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$this->composer = $composer;
		return $this->composer;
	}

	/**
	 * Get the installed community packages.
	 */
	private function get_installed_packages() {

		$composer = $this->get_composer();
		$repo = $composer->getRepositoryManager()->getLocalRepository();
		$installed_packages = array();
		foreach( $repo->getPackages() as $package ) {
			if ( ! $this->is_community_package( $package ) )
				continue;
			$installed_packages[] = $package;
		}
		return $installed_packages;
	}

	/**
	 * Get an installed package by its name.
	 */
	private function get_installed_package_by_name( $package_name ) {
		foreach( $this->get_installed_packages() as $package ) {
			if ( $package_name == $package->getName() )
				return $package;
		}
		return false;
	}

	/**
	 * Check if the package name provided is already installed.
	 */
	private function is_package_installed( $package_name ) {
		if ( $this->get_installed_package_by_name( $package_name ) )
			return true;
		else
			return false;
	}

	/**
	 * Get the composer.json object
	 */
	private function get_composer_json() {
		return new JsonFile( $this->get_composer_json_path() );
	}

	/**
	 * Set the path to composer.json
	 */
	private function set_composer_json_path( $path ) {
		$this->composer_json_path = $path;
	}

	/**
	 * Get the path to composer.json
	 */
	private function get_composer_json_path() {
		return $this->composer_json_path;
	}

	/**
	 * Regenerate the Composer autoloader
	 */
	private function regenerate_autoloader() {
		$rm = $this->composer->getRepositoryManager();
		$this->composer->getAutoloadGenerator()->dump(
			$this->composer->getConfig(),
			$this->composer->getRepositoryManager()->getLocalRepository(),
			$this->composer->getPackage(),
			$this->composer->getInstallationManager(),
			'composer'
		);
	}

}
WP_CLI::add_command( 'package', 'WP_CLI_Package_Command' );