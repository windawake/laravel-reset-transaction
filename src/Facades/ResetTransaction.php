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


    public function centerCommit($transactId, $transactRollback)
    {
        $item = DB::connection('rt_center')->table('reset_transact')->where('transact_id', $transactId)->first();
        if ($item->transact_rollback) {
            $rollArr = json_decode($item->transact_rollback, true);
            $transactRollback = array_merge($transactRollback, $rollArr);
        }
        foreach ($transactRollback as $tid) {
            DB::connection('rt_center')->table('reset_transact_sql')->where('transact_id', 'like', $tid . '%')->update(['transact_status' => RT::STATUS_ROLLBACK]);
        }
        $xidMap = $this->getUsedXidMap($transactId, 'commit');
        $xidArr = [];
        foreach ($xidMap as $name => $item) {
            $xidArr[$name] = $item['xid'];
        }

        $this->xaBeginTransaction($xidArr);
        foreach ($xidMap as $name => $item) {
            $sqlCollects = $item['sql_list'];
            foreach ($sqlCollects as $item) {
                $result = DB::connection($name)->getPdo()->exec($item->sql);
                if ($item->check_result && $result != $item->result) {
                    throw new ResetTransactionException("db had been changed by anothor transact_id");
                }
            }
        }
        $this->xaCommit($xidArr);
        DB::connection('rt_center')->table('reset_transact_sql')->where('transact_id', 'like', $transactId . '%')->delete();
        DB::connection('rt_center')->table('reset_transact_req')->where('transact_id', $transactId)->delete();
        DB::connection('rt_center')->table('reset_transact')->where('transact_id', $transactId)->delete();
    }

    public function centerRollback($transactId, $transactRollback)
    {
        if (strpos('-', $transactId)) {
            foreach ($transactRollback as $txId) {
                DB::connection('rt_center')->table('reset_transact_sql')->where('transact_id', 'like', $txId . '%')->update(['transact_status' => RT::STATUS_ROLLBACK]);
            }
            DB::connection('rt_center')->table('reset_transact_sql')->where('transact_id', 'like',  $transactId . '%')->update(['transact_status' => RT::STATUS_ROLLBACK]);
        } else {
            DB::connection('rt_center')->table('reset_transact_sql')->where('transact_id', 'like', $transactId . '%')->delete();
            DB::connection('rt_center')->table('reset_transact_req')->where('transact_id', $transactId)->delete();
            DB::connection('rt_center')->table('reset_transact')->where('transact_id', $transactId)->delete();
        }

        $this->removeRT();
    }

    public function middlewareBeginTransaction($transactId, $connection)
    {
        $transactIdArr = explode('-', $transactId);
        $sqlArr = DB::connection('rt_center')
            ->table('reset_transact_sql')
            ->where('transact_id', 'like', $transactIdArr[0].'%')
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

    private function getUsedXidMap($transactId, $action)
    {
        $xidMap = [];
        $query = DB::connection('rt_center')->table('reset_transact_sql')->where('transact_id', 'like', $transactId . '%');
        if ($action == 'commit') {
            $query->whereIn('transact_status', [RT::STATUS_START, RT::STATUS_COMMIT]);
        }
        $list = $query->get();
        foreach ($list as $item) {
            $name = $item->connection;
            $xidMap[$name]['sql_list'][] = $item;
        }

        foreach ($xidMap as $name => &$item){
            $xid = session_create_id();
            $item['xid'] = $xid;
        }

        return $xidMap;
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
                    'transact_id' => $item['transact_id'],
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


    /**
     * beginTransaction
     *
     */
    public function xaBeginTransaction($xidArr)
    {
        $this->_XAStart($xidArr);
    }

    /**
     * commit
     * @param $xidArr
     */
    public function xaCommit($xidArr)
    {
        $this->_XAEnd($xidArr);
        $this->_XAPrepare($xidArr);
        $this->_XACommit($xidArr);
    }

    /**
     * rollback
     * @param $xidArr
     */
    public function xaRollBack($xidArr)
    {
        $this->_XAEnd($xidArr);
        $this->_XAPrepare($xidArr);
        $this->_XARollback($xidArr);
    }

    private function _XAStart($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA START '{$xid}'");
        }
    }


    private function _XAEnd($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA END '{$xid}'");
        }
    }


    private function _XAPrepare($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA PREPARE '{$xid}'");
        }
    }


    private function _XACommit($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA COMMIT '{$xid}'");
        }
    }

    private function _XARollback($xidArr)
    {
        foreach ($xidArr as $name => $xid) {
            DB::connection($name)->getPdo()->exec("XA ROLLBACK '{$xid}'");
        }
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

                $sqlItem = [
                    'transact_id' => $rtTransactId, 
                    'sql' => $backupSql, 
                    'result' => $result,
                    'check_result' => (int) $checkResult,
                    'connection' => DB::getDefaultConnection()
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
