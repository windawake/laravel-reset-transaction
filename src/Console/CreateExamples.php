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
            $directory = $testsuite->addChild('directory', './tests/Transaction');
            $directory->addAttribute('suffix', 'Test.php');

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
        $transactTable = 'reset_transact';
        $transactSqlTable = 'reset_transact_sql';
        $transactReqTable = 'reset_transact_req';
        $orderTable = 'reset_order';
        $storageTable = 'reset_storage';
        $accountTable = 'reset_account';

        $orderService = 'service_order';
        $storageService = 'service_storage';
        $accountService = 'service_account';
        $rtCenter = 'rt_center';

        $serviceMap = [
            $orderService => [
                $orderTable
            ],
            $storageService => [
                $storageTable
            ],
            $accountService => [
                $accountTable
            ],
            $rtCenter => [
                $transactTable, $transactSqlTable, $transactReqTable,
            ]
        ];

        $manager = DB::getDoctrineSchemaManager();
        $dbList = $manager->listDatabases();

        foreach ($serviceMap as $service => $tableList) {
            if (!in_array($service, $dbList)) {
                $manager->createDatabase($service);
            }

            foreach ($tableList as $table) {
                if ($table == $transactTable) {
                    $fullTable = $service . '.' . $transactTable;
                    Schema::dropIfExists($fullTable);
                    Schema::create($fullTable, function (Blueprint $table) {
                        $table->bigIncrements('id');
                        $table->string('transact_id', 32);
                        $table->tinyInteger('action')->default(0);
                        $table->text('transact_rollback');
                        $table->dateTime('created_at')->useCurrent();
                        $table->unique('transact_id');
                    });
                }

                if ($table == $transactSqlTable) {
                    $fullTable = $service . '.' . $transactSqlTable;
                    Schema::dropIfExists($fullTable);
                    Schema::create($fullTable, function (Blueprint $table) {
                        $table->bigIncrements('id');
                        $table->string('request_id', 32);
                        $table->string('transact_id', 512);
                        $table->tinyInteger('transact_status')->default(0);
                        $table->string('connection', 32);
                        $table->text('sql');
                        $table->integer('result')->default(0);
                        $table->tinyInteger('check_result')->default(0);
                        $table->dateTime('created_at')->useCurrent();
                        $table->index('request_id');
                        $table->index('transact_id');
                    });
                }

                if ($table == $transactReqTable) {
                    $fullTable = $service . '.' . $transactReqTable;
                    Schema::dropIfExists($fullTable);
                    Schema::create($fullTable, function (Blueprint $table) {
                        $table->bigIncrements('id');
                        $table->string('request_id', 32);
                        $table->string('transact_id', 32);
                        $table->text('response');
                        $table->dateTime('created_at')->useCurrent();
                        $table->unique('request_id');
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
                    DB::unprepared("insert into {$fullTable} values(1, 1000)");
                }

                if ($table == $accountTable) {
                    $fullTable = $service . '.' . $accountTable;
                    Schema::dropIfExists($fullTable);
                    Schema::create($fullTable, function (Blueprint $table) {
                        $table->increments('id');
                        $table->decimal('amount')->default(0);
                    });
                    DB::unprepared("insert into {$fullTable} values(1, 10000)");
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
