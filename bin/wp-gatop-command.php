<?php
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/GATopCommand.php';

WP_CLI::add_command( 'gatop', 'MWender\\GATopCommand\\GATopCommand' );
