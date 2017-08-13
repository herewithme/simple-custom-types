<?php
/**
 * Dump Simple Custom Post Types
 */

/*
 Copyright (C) 2017, Raphaël . Droz + floss @ gmail DOT com

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.
*/

class Scust_Dump extends WP_CLI_Command {

    /**
     * dump Simple Custom Post Types
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Only generate the output for that given post type
     * ---
     * default: print_r
     * options:
     *   - wp
     *   - var_export
     *   - json
     *   - print_r
     * ---
     *
     * [--one]
     * : If only one custom post type is dumped, dumps CPT data rather than a slug-indexed array/object.
     *
     * [<type>...]
     * : Generate export for the specified custom post type(s) instead of all of them.
     *
     *
     * ## EXAMPLES
     *
     *     $ wp scust dump --format=wp
     *     $ wp scust dump --one --format=var_export restaurant
     *     $ wp scust dump --format=json restaurant soiree
     *
     */
    public function dump( $args, $assoc_args ) {
        require_once( SCUST_DIR . '/inc/class.client.php' );
        require_once( SCUST_DIR . '/inc/class.admin.php' );
        require_once( SCUST_DIR . '/inc/class.admin.conversion.php' );

        $current_options = get_option( SCUST_OPTION )['customtypes'];
        $only = [];
        if (! empty($args)) {
            $only = array_filter(array_unique( $args ));
            $diff = array_diff_key( array_flip( $only ), $current_options );
            foreach($diff as $k => $v) {
                WP_CLI::warning( sprintf('"%s" is not a custom post type', $k) );
            }
            if (count($diff) == count($only)) {
                WP_CLI::error( 'no suitable post types' );
            }
        }

        $simple_customtypes['client'] = new SimpleCustomTypes_Client();

        // see checkExportCustomType()
        if ( $assoc_args['format'] == 'wp' ) {
            $template = <<<'EOF'
// Custom post type: %2$s
add_action( 'init', 'register_my_cpt_%1$s', 10 );
function register_my_cpt_%1$s() {
    register_post_type( "%1$s", %3$s
    );
}

EOF;

            $output = '';
            foreach ( $current_options as $k => $v ) {
                if ($only && ! in_array($k, $only)) continue;
                $args = $simple_customtypes['client']->prepareArgs( $v );
                if ($output) $output .= PHP_EOL;
                $output .= sprintf( $template, $k,
                                    $args['labels']['name'],
                                    // indent 6 spaces
                                    ltrim(preg_replace( '/^/ms', '      ', var_export($args, true) ) ));
            }

            printf("<?php" . PHP_EOL . PHP_EOL . $output . PHP_EOL . '?>' . PHP_EOL);
        }

        // wp-includes/class-wp-post-type.php :: set_props()
        else { // if ( in_array($assoc_args['format'], ['print_r', 'var_export', 'json']) ) {
            if (count($only)) $current_options = array_intersect_key($current_options, array_flip($only));
            $cpts = array_map(function($e) use($simple_customtypes) { return $simple_customtypes['client']->prepareArgs( $e ); },
                              $current_options);
            if ( count($only) == 1 && count($current_options) == 1 && isset($assoc_args['one']) ) {
                $cpts = array_pop($cpts);
            }

            switch($assoc_args['format']) {
            case 'var_export':
                var_export( $cpts ); break;
            case 'json':
                print ( json_encode($cpts, JSON_PRETTY_PRINT) ); break;
            case 'print_r':
            default:
                print_r($cpts);
            }
        }
    }
}
