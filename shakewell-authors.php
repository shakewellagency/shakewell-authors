<?php
/**
 * Plugin Name:       Shakewell Multiple Authors
 * Plugin URI:        https://github.com/shakewellagency/shakewell-authors
 * Description:       Give a post more than one author, in order, with private author profiles. Imports from Co-Authors Plus.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Shakewell
 * Author URI:        https://shakewell.agency
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shakewell-authors
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.1';
const FILE    = __FILE__;

require_once __DIR__ . '/src/Authors.php';
require_once __DIR__ . '/src/Admin.php';
require_once __DIR__ . '/src/Importer.php';
require_once __DIR__ . '/src/Plugin.php';
require_once __DIR__ . '/src/functions.php';

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
