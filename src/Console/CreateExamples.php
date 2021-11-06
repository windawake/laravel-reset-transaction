<?php

namespace Laravel\ResetTransaction\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CreateExamples extends Command
{
    /**
     * @var Filesystem $files
     */
    protected $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resetTransact:create-examples';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to create examples';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $boolean = $this->files->copyDirectory(__DIR__.'/../../examples/Controllers', app_path('Http/Controllers'));

        $boolean = $this->files->copyDirectory(__DIR__.'/../../examples/Models', app_path('Models'));

        if(!$boolean) {
            $this->error('Failed to create Example models!');
        }

        $this->info('Example models created successfully!');
    }
}
