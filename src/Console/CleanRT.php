<?php

namespace Laravel\ResetTransaction\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Facades\RTCenter;

class CleanRT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resetTransact:clean-rt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clean rt_center db records';

    /**
     * Execute the console command.
     * 
     * @return mixed
     */
    public function handle()
    {
        $cleanAfter = (int) config('rt_database.center.crontab.clean_after');

        if ($cleanAfter) {
            DB::setDefaultConnection('rt_center');
            $actionArr = [
                RTCenter::ACTION_START,
                RTCenter::ACTION_COMMIT,
                RTCenter::ACTION_ROLLBACK,
            ];

            $createdAt = date('Y-m-d H:i:s', time() - $cleanAfter);
            $list = DB::table('reset_transact')->whereIn('action', $actionArr)->where('created_at', '<', $createdAt)->get();

            if ($list->count()) {
                $rtIdArr = $list->pluck('transact_id')->toArray();
                DB::table('reset_transact')->whereIn('transact_id', $rtIdArr)->delete();
                DB::table('reset_transact_req')->whereIn('transact_id', $rtIdArr)->delete();
                DB::table('reset_transact_sql')->whereIn('transact_id', $rtIdArr)->delete();
            }
        }
    }
}
