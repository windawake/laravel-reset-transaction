<?php

namespace Laravel\ResetTransaction\Console;

use Closure;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Facades\RTCenter;
use PDO;

class ReleaseRT extends Command
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
    protected $signature = 'resetTransact:release-rt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'release xa prepare lock';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $releaseAfter = (int) config('rt_database.center.crontab.release_after');
        if ($releaseAfter) {
            DB::setDefaultConnection('rt_center');
            $actionArr = [
                RTCenter::ACTION_PREPARE,
                RTCenter::ACTION_PREPARE_COMMIT,
                RTCenter::ACTION_PREPARE_ROLLBACK,
            ];

            $createdAt = date('Y-m-d H:i:s', time() - $releaseAfter);
            $list = DB::table('reset_transact')->whereIn('action', $actionArr)->where('created_at', '<', $createdAt)->get();

            $recover = $this->getXaRecover();

            foreach ($list as $item) {
                $xidArr = json_decode($item->xids_info, true);
                switch ($item->action) {
                case RTCenter::ACTION_PREPARE:
                    foreach ($xidArr as $name => $xid) {
                        $arr = $recover[$name] ?? [];
                        if (in_array($xid, $arr)) {
                            $this->tryCatch(function () use ($name, $xid) {
                                DB::connection($name)->getPdo()->exec("xa rollback '$xid'");
                            });
                        }
                    }
                    DB::table('reset_transact')->where('transact_id', $item->transact_id)->update(['action' => RTCenter::ACTION_START]);
                    break;
                case RTCenter::ACTION_PREPARE_COMMIT:
                    foreach ($xidArr as $name => $xid) {
                        $arr = $recover[$name] ?? [];
                        if (in_array($xid, $arr)) {
                            $this->tryCatch(function () use ($name, $xid) {
                                DB::connection($name)->getPdo()->exec("xa commit '$xid'");
                            });
                        }
                    }
                    DB::table('reset_transact')->where('transact_id', $item->transact_id)->update(['action' => RTCenter::ACTION_COMMIT]);
                    break;
                case RTCenter::ACTION_PREPARE_ROLLBACK:
                    foreach ($xidArr as $name => $xid) {
                        $arr = $recover[$name] ?? [];
                        if (in_array($xid, $arr)) {
                            $this->tryCatch(function () use ($name, $xid) {
                                DB::connection($name)->getPdo()->exec("xa rollback '$xid'");
                            });
                        }
                    }
                    DB::table('reset_transact')->where('transact_id', $item->transact_id)->update(['action' => RTCenter::ACTION_ROLLBACK]);
                    // no break
                default:
                    break;

                }
            }
        }
    }

    private function getXaRecover()
    {
        $recover = [];
        $configList = config('rt_database.service_connections', []);
        foreach ($configList as $name => $config) {
            $dsn="{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['database']}";
            $pdo = new PDO($dsn, $config['username'], $config['password']);
            $statement = $pdo->prepare("xa recover");
            $statement->execute();
            $list = $statement->fetchAll();
            if ($list) {
                $recover[$name] = array_column($list, 'data');
            }
        }
        
        return $recover;
    }

    private function tryCatch(Closure $tryCallback, Closure $catchCallback = null)
    {
        try {
            $tryCallback();
        } catch (Exception $ex) {
            if (!is_null($catchCallback)) {
                $catchCallback($ex);
            }
        }
    }
}
