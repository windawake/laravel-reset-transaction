<?php

namespace Laravel\ResetTransaction\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

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
        $this->addFileToApp();
        $this->addTableToDatabase();
        $this->addTestsuitToPhpunit();

        $this->info('Example created successfully!');
    }

    /**
     * rewrite phpunit.xml
     *
     * @return void
     */
    private function addTestsuitToPhpunit()
    {
        $content = file_get_contents(base_path('phpunit.xml'));
        $xml = new \SimpleXMLElement($content);
        $hasTransaction = false;

        foreach ($xml->testsuites->testsuite as $testsuite) {
            if ($testsuite->attributes()->name == 'Transaction') {
                $hasTransaction = true;
            }
        }

        if ($hasTransaction == false) {
            $testsuite = $xml->testsuites->addChild('testsuite');
            $testsuite->addAttribute('name', 'Transaction');
            $testsuite->addChild('directory', './tests/Transaction');

            $domxml = new \DOMDocument('1.0');
            $domxml->preserveWhiteSpace = false;
            $domxml->formatOutput = true;
            $domxml->loadXML($xml->asXML());
            $domxml->save(base_path('phpunit.xml'));
        }
    }

    /**
     * db
     *
     * @return void
     */
    private function addTableToDatabase()
    {
        $transactTable = 'reset_transaction';
        $orderTable = 'reset_order';
        $storageTable = 'reset_storage';
        $accountTable = 'reset_account';

        $orderService = 'service_order';
        $storageService = 'service_storage';
        $accountService = 'service_account';

        $serviceMap = [
            $orderService => [
                $transactTable, $orderTable
            ],
            $storageService => [
                $transactTable, $storageTable
            ],
            $accountService => [
                $transactTable, $accountTable
            ]
        ];

        $manager = DB::getDoctrineSchemaManager();
        $dbList = $manager->listDatabases();

        foreach ($serviceMap as $service => $tableList) {
            if (!in_array($orderService, $dbList)) {
                $manager->createDatabase($service);
            }

            foreach ($tableList as $table) {
                if ($table == $transactTable) {
                    $fullTable = $service . '.' . $transactTable;
                    Schema::dropIfExists($fullTable);
                    Schema::create($fullTable, function (Blueprint $table) {
                        $table->increments('id');
                        $table->string('request_id', 32)->default('');
                        $table->string('transact_id', 512);
                        $table->text('sql');
                        $table->integer('result')->default(0);
                        $table->dateTime('created_at')->useCurrent();
                        $table->index('request_id');
                        $table->index('transact_id');
                    });
                }

                if ($table == $orderTable) {
                    $fullTable = $service . '.' . $orderTable;
                    Schema::dropIfExists($fullTable);
                    Schema::create($fullTable, function (Blueprint $table) {
                        $table->increments('id');
                        $table->string('order_no')->default('');
                        $table->integer('stock_qty')->default(0);
                        $table->decimal('amount')->default(0);
                        $table->tinyInteger('status')->default(0);
                        $table->unique('order_no');
                    });
                }

                if ($table == $storageTable) {
                    $fullTable = $service . '.' . $storageTable;
                    Schema::dropIfExists($fullTable);
                    Schema::create($fullTable, function (Blueprint $table) {
                        $table->increments('id');
                        $table->integer('stock_qty')->default(0);
                    });
                    DB::unprepared("insert into {$fullTable} values(1, 10)");
                }

                if ($table == $accountTable) {
                    $fullTable = $service . '.' . $accountTable;
                    Schema::dropIfExists($fullTable);
                    Schema::create($fullTable, function (Blueprint $table) {
                        $table->increments('id');
                        $table->decimal('amount')->default(0);
                    });
                    DB::unprepared("insert into {$fullTable} values(1, 100)");
                }
            }
        }
    }

    private function addFileToApp()
    {
        $this->files->copyDirectory(__DIR__ . '/../../examples/Controllers', app_path('Http/Controllers'));

        $this->files->copyDirectory(__DIR__ . '/../../examples/Models', app_path('Models'));
        $this->files->copyDirectory(__DIR__ . '/../../examples/config', config_path());
        $this->files->copyDirectory(__DIR__ . '/../../examples/tests', base_path('tests'));
    }
}
