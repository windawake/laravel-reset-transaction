<?php

namespace Laravel\ResetTransaction\Facades;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;

class ResetTransaction
{
    protected $transactIdArr = [];
    protected $transactRollback = [];

    public function beginTransaction()
    {
        $transactId = session_create_id();
        array_push($this->transactIdArr, $transactId);
        if (count($this->transactIdArr) == 1) {
            $data = [
                'transact_id' => $transactId,
                'transact_rollback' => '[]',
                'xids_info' => '[]',
            ];
            DB::connection('rt_center')->table('reset_transact')->insert($data);
        }

        $this->stmtBegin();

        return $this->getTransactId();
    }

    public function commit()
    {
        $this->stmtRollback();

        if (count($this->transactIdArr) > 1) {
            array_pop($this->transactIdArr);

            return true;
        }

        $this->logRT(RT::STATUS_COMMIT);

        $commitUrl = config('rt_database.center.commit_url');

        $client = new Client();
        $response = $client->post($commitUrl, [
            'json' =>[
                'transact_id' => $this->getTransactId(),
                'transact_rollback' => $this->transactRollback,
            ]
        ]);

        $this->removeRT();

        return $response;
    }

    public function rollBack()
    {
        $this->stmtRollback();

        if (count($this->transactIdArr) > 1) {
            $transactId = $this->getTransactId();
            foreach ($this->transactRollback as $i => $txId) {
                if (strpos($txId, $transactId) === 0) {
                    unset($this->transactRollback[$i]);
                }
            }
            array_push($this->transactRollback, $transactId);
            array_pop($this->transactIdArr);
            return true;
        }

        $this->logRT(RT::STATUS_ROLLBACK);

        $rollbackUrl = config('rt_database.center.rollback_url');

        $client = new Client();
        $response = $client->post($rollbackUrl, [
            'json' =>[
                'transact_id' => $this->getTransactId(),
                'transact_rollback' => $this->transactRollback,
            ]
        ]);
        $this->removeRT();

        return $response;
    }

    public function middlewareBeginTransaction($transactId)
    {
        $transactIdArr = explode('-', $transactId);
        $connection = DB::getDefaultConnection();
        $sqlArr = DB::connection('rt_center')
            ->table('reset_transact_sql')
            ->where('transact_id', $transactIdArr[0])
            ->where('connection', $connection)
            ->whereIn('transact_status', [RT::STATUS_START, RT::STATUS_COMMIT])
            ->pluck('sql')->toArray();
        $sql = implode(';', $sqlArr);
        $this->stmtBegin();
        if ($sqlArr) {
            DB::unprepared($sql);
        }

        $this->setTransactId($transactId);
    }

    public function middlewareRollback()
    {
        $this->stmtRollback();
        $this->logRT(RT::STATUS_COMMIT);

        if ($this->transactRollback) {
            $transactId = RT::getTransactId();
            $transactIdArr = explode('-', $transactId);
            $tid = $transactIdArr[0];
            
            $item = DB::connection('rt_center')->table('reset_transact')->where('transact_id', $tid)->first();
            $arr = $item->transact_rollback ? json_decode($item->transact_rollback, true) : [];
            $arr = array_merge($arr, $this->transactRollback);
            $arr = array_unique($arr);

            $data = ['transact_rollback' => json_encode($arr)];
            DB::connection('rt_center')->table('reset_transact')->where('transact_id', $tid)->update($data);
        }
        
        $this->removeRT();
    }

    public function commitTest()
    {
        $this->stmtRollback();

        if (count($this->transactIdArr) > 1) {
            array_pop($this->transactIdArr);

            return true;
        }

        $this->logRT(RT::STATUS_COMMIT);
    }

    public function rollBackTest()
    {
        $this->stmtRollback();

        if (count($this->transactIdArr) > 1) {
            $transactId = $this->getTransactId();
            foreach ($this->transactRollback as $i => $txId) {
                if (strpos($txId, $transactId) === 0) {
                    unset($this->transactRollback[$i]);
                }
            }
            array_push($this->transactRollback, $transactId);
            array_pop($this->transactIdArr);
            return true;
        }

        $this->logRT(RT::STATUS_ROLLBACK);
    }

    public function setTransactId($transactId)
    {
        $this->transactIdArr = explode('-', $transactId);
    }


    public function getTransactId()
    {
        return implode('-', $this->transactIdArr);
    }

    public function getTransactRollback()
    {
        return $this->transactRollback;
    }

    public function logRT($status)
    {
        $sqlArr = session()->get('rt_transact_sql');
        $requestId = session()->get('rt_request_id');
        if (is_null($requestId)) {
            $requestId = $this->transactIdArr[0];
        }
        
        if ($sqlArr) {
            foreach ($sqlArr as $item) {
                DB::connection('rt_center')->table('reset_transact_sql')->insert([
                    'request_id' => $requestId,
                    'transact_id' => $this->transactIdArr[0],
                    'chain_id' => $item['transact_id'],
                    'transact_status' => $status,
                    'sql' => value($item['sql']),
                    'result' => $item['result'],
                    'check_result' => $item['check_result'],
                    'connection' => $item['connection'],
                ]);
            }
        }
    }

    private function removeRT()
    {
        $this->transactIdArr = [];

        session()->remove('rt_transact_sql');
        session()->remove('rt_request_id');
    }

    public function saveQuery($query, $bindings, $result, $checkResult, $keyName = null, $id = null)
    {
        $rtTransactId = $this->getTransactId();
        if ($rtTransactId && $query && !strpos($query, 'reset_transact')) {
            $subString = strtolower(substr(trim($query), 0, 12));
            $actionArr = explode(' ', $subString);
            $action = $actionArr[0];

            $sql = str_replace("?", "'%s'", $query);
            $completeSql = vsprintf($sql, $bindings);

            if (in_array($action, ['insert', 'update', 'delete', 'set', 'savepoint', 'rollback'])) {
                $backupSql = $completeSql;
                if ($action == 'insert') {
                    // if only queryBuilder insert or batch insert then return false
                    if (is_null($id)) {
                        return false;
                    }

                    if (!strpos($query, "`{$keyName}`")) {
                        // extract variables from sql
                        preg_match("/insert into (.+) \((.+)\) values \((.+)\)/", $backupSql, $match);
                        $table = $match[1];
                        $columns = $match[2];
                        $parameters = $match[3];

                        $columns = "`{$keyName}`, " . $columns;
                        $parameters = "'{$id}', " . $parameters;
                        $backupSql = "insert into $table ($columns) values ($parameters)";
                    }
                }

                $connectionName = DB::connection()->getConfig('connection_name');
                $sqlItem = [
                    'transact_id' => $rtTransactId, 
                    'sql' => $backupSql, 
                    'result' => $result,
                    'check_result' => (int) $checkResult,
                    'connection' => $connectionName
                ];
                session()->push('rt_transact_sql', $sqlItem);
            }

        }
    }

    private function stmtBegin()
    {
        session()->put('rt_stmt', 'begin');
        DB::beginTransaction();
        session()->remove('rt_stmt', 'begin');
    }

    private function stmtRollback()
    {
        session()->put('rt_stmt', 'rollback');
        DB::rollBack();
        session()->remove('rt_stmt', 'rollback');
    }
}
