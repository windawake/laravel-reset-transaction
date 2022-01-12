<?php

namespace Laravel\ResetTransaction\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\ResetTransaction\Exception\ResetTransactionException;
use Laravel\ResetTransaction\Facades\RT;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\go;

class AsyncCommit extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resetTransact:async-commit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to asynchronously commit transaction';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * 
     * @return mixed
     */
    public function handle()
    {
        Log::info("hello rt");
        $mode = config('rt_database.rt.mode');
        if ($mode == 'async') {
            $num = config('rt_database.rt.num_coroutine');
            $list = DB::table('reset_transact_act')->where('action', RT::ACTION_WAIT_COMMIT)->limit($num)->get();
    
            $count = $list->count();
            if ($count) {
                run(function()use($list){
                    foreach ($list as $item)
                    {
                        go(function() use($item) {
                            $transactId = $item->transact_id;
                            try {
                                DB::table('reset_transact_act')->where('transact_id', $transactId)->update([
                                    'action' => RT::ACTION_PREPARE_COMMIT
                                ]);
                                $this->commit($item);
                            } catch (Exception $ex) {

                                DB::table('reset_transact_act')->where('transact_id', $transactId)->update([
                                    'action' => RT::ACTION_WAIT_COMMIT
                                ]);

                                throw $ex;
                            }
                            
                    
                        });
                    }
                });
            }
        }
        
    }

    private function commit($transact)
    {
        $transactId = $transact->transact_id;
        $transactRollback = $transact->transact_rollback ? json_decode($transact->transact_rollback, true) : [];
        $xidArr = $this->getUsedXidArr($transactId);
        RT::xaBeginTransaction($xidArr);


        foreach ($xidArr as $conn) {
            $db = $conn['db'];
            $sqlCollects = $db->table('reset_transact')->where('transact_id', 'like', $transactId . '%')->get();
            if ($sqlCollects->count() > 0) {
                foreach ($sqlCollects as $item) {
                    if ($item->transact_status != RT::STATUS_ROLLBACK && !in_array($item->transact_id, $transactRollback)) {
                        $result = $db->getPdo()->exec($item->sql);
                        if ($item->check_result && $result != $item->result) {
                            Log::error("db had been changed by anothor transact_id");
                        }
                    }
                }
                $db->table('reset_transact')->where('transact_id', 'like', $transactId . '%')->delete();
            }
        }

        RT::xaCommit($xidArr);
        
    }

    private function getUsedXidArr($transactId)
    {
        $conList = config('rt_database.connections', []);
        $xidArr = [];
        foreach ($conList as $name => $config) {
            $db = DB::connection($name);
            $config = $db->getConfig();
            $db = app('db.factory')->make($config);

            $count = $db->table('reset_transact')->where('transact_id', 'like', $transactId . '%')->count();
            if ($count > 0) {
                $xid = session_create_id();
                $xidArr[] = [
                    'xid' => $xid, 
                    'db' => $db
                ];
            }
        }

        return $xidArr;
    }
}
