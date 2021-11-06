<?php

namespace Laravel\ResetTransaction\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

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
        $transactTable = 'reset_transaction';
        $productTable = 'reset_product';
        Schema::dropIfExists($transactTable);
        Schema::create($transactTable, function (Blueprint $table) {
            $table->increments('id');
            $table->string('transact_id', 32);
            $table->string('action', 10);
            $table->text('sql');
            $table->date('created_at');
            $table->index('transact_id');
        });

        Schema::dropIfExists($productTable);
        Schema::create($productTable, function (Blueprint $table) {
            $table->increments('pid');
            $table->integer('store_id')->default(0);
            $table->string('product_name');
            $table->tinyInteger('status')->default(0);
            $table->date('created_at');
        });


        $boolean = $this->files->copyDirectory(__DIR__.'/../../examples/Controllers', app_path('Http/Controllers'));

        $boolean = $this->files->copyDirectory(__DIR__.'/../../examples/Models', app_path('Models'));

        if(!$boolean) {
            $this->error('Failed to create Example models!');
        }

        $this->info('Example models created successfully!');
    }
}
