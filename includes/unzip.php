<?php

if (!isset($_GET['bpz'])) {
	$path    = plugin_dir_path( __FILE__ ) . '../app/js/' . $_GET['decode'] . '.js';
	$content = file_get_contents( $path );
} else {
	$path    = plugin_dir_path( __FILE__ ) . '../app/js/' . $_GET['decode'] . '.bpz';
	$content = gzdecode( file_get_contents( $path ) );
}
exit( $content );