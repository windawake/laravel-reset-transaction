<?php

namespace Tests\Transaction;

use App\Models\ResetAccountModel;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetOrderModel;
use App\Models\ResetStorageModel;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Laravel\ResetTransaction\Facades\RT;

class AsyncTest extends TestCase
{

    /**
     * Client
     *
     * @var \GuzzleHttp\Client
     */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testAsyncCommit()
    {
        $mode = config('rt_database.rt.mode');
        if ($mode == 'async') {
            $num = config('rt_database.rt.num_coroutine');
            $list = DB::table('reset_transact_act')->where('action', RT::ACTION_WAIT_COMMIT)->limit($num)->get();
    
            foreach ($list as $item)
            {
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

        DB::table('reset_transact_act')->where('transact_id', $transactId)->update([
            'action' => RT::ACTION_FINISH_COMMIT
        ]);
        
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

    private function responseToArray($response)
    {
        $contents = $response->getBody()->getContents();
        return json_decode($contents, true);
    }
}
