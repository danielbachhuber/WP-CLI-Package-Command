<?php
/**
 * Browse, install, update, and uninstall WP-CLI community packages.
 */
use \Composer\Factory;
use \Composer\IO\NullIO;
use \Composer\Json\JsonFile;
use Composer\Package;
use \Composer\Repository;
use \Composer\Repository\RepositoryManager;

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

		$composer = $this->get_composer();
		$repo = $composer->getRepositoryManager()->getLocalRepository();

		$packages = array();
		foreach( $repo->getPackages() as $package ) {

			// WP-CLI community packages always start with 'wp-cli'
			if ( false === stripos( $package->getName(), '/wp-cli' ) )
				continue;

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
	 */
	public function uninstall() {

	}

	/**
	 * Get a Composer instance.
	 */
	private function get_composer() {

		$composer_json_path = WP_CLI\Utils\find_file_upward( 'composer.json', WP_CLI_ROOT );
		try {
			$composer = Factory::create( new NullIO, $composer_json_path );
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		// Composer expects vendorDir to be in ~/.composer, which isn't always the case
		// because we still support installing WP-CLI with `git clone`
		$rm = $composer->getRepositoryManager();
		$installed_json_path = pathinfo( $composer_json_path, PATHINFO_DIRNAME ) . '/vendor/composer/installed.json';
		$rm->setLocalRepository( new Repository\InstalledFilesystemRepository( new JsonFile( $installed_json_path ) ) );
		$composer->setRepositoryManager( $rm );

		return $composer;
	}

}
WP_CLI::add_command( 'package', 'WP_CLI_Package_Command' );