<?php

namespace LaravelAcl\Console;

use Illuminate\Console\Command;

class MakeAclCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:acl
                            {--path=_elements }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'description';

    /**
     * The files that need to be exported.
     *
     * @var array
     */
    protected $files = [
        'config/acl.stub' => 'config_path(acl.php)',
        'views/sidebar.stub'  => 'resource_path(views/{{path}}/sidebar.blade.php)',
    ];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->exportFiles();

        file_put_contents(
            base_path('routes/web.php'),
            file_get_contents( dirname( __DIR__ ) .'/Resources/routes.stub'),
            FILE_APPEND
        );

    }

    /**
     * Create the directories for the files.
     *
     * @return void
     */
    protected function createDirectories( $path )
    {
        $dirpath = dirname( $path );

        if( ! is_dir( $dirpath ) )
            mkdir( $dirpath, 0755, true );
    }

    /**
     * Export the authentication views.
     *
     * @return void
     */
    protected function exportFiles()
    {
        foreach ( $this->files as $key => $value) {
            $destination = $this->normalizePath( $value );

            $this->createDirectories( $destination );

            copy(
                dirname( __DIR__ ) . '/Resources/' . $key,
                $destination
            );
        }
    }
    protected function normalizePath( $path )
    {
        preg_match( '{^(?<method>[a-z_]+)\((?<path>[a-z.\{\}/]+)\)$}', $path, $matches );

        $path = preg_replace_callback( '/\{\{(\w+)\}\}/', function( $matches ){
            return $this->option( $matches[1] );
        }, $matches['path'] );

        return $matches['method']( $path );
    }
}
