<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('concurrent owners cannot remove each others access', function (string $change): void {
    expect(DB::transactionLevel())->toBe(0);
    $owners = User::factory()->count(2)->create(['role' => 'owner']);
    $processes = [];
    $worker = <<<'PHP'
    <?php
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['database.default' => 'pgsql', 'database.connections.pgsql' => json_decode(getenv('STAFF_TEST_DATABASE'), true), 'session.driver' => 'array', 'cache.default' => 'array']);
    $connection = Illuminate\Support\Facades\DB::connection();
    if (!$app->environment('testing') || $connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== 'cafe_pos_test' || $connection->selectOne('SELECT current_database() AS name')->name !== 'cafe_pos_test') {
        throw new RuntimeException('Refusing to run outside cafe_pos_test.');
    }
    $actor = App\Models\User::findOrFail((int) getenv('STAFF_ACTOR_ID'));
    $target = App\Models\User::findOrFail((int) getenv('STAFF_TARGET_ID'));
    Illuminate\Support\Facades\Auth::setUser($actor);
    $pid = $connection->selectOne('SELECT pg_backend_pid() AS pid')->pid;
    $connection->beforeExecuting(function (string $sql) use ($pid): void {
        if (str_contains(strtolower($sql), 'for update')) {
            fwrite(STDOUT, 'READY:'.$pid.PHP_EOL);
            fflush(STDOUT);
        }
    });
    $request = Illuminate\Http\Request::create('/admin/staff/'.$target->id, 'PUT', [
        'name' => $target->name, 'email' => $target->email,
        'role' => getenv('STAFF_CHANGE') === 'demote' ? 'cashier' : 'owner',
        'is_active' => getenv('STAFF_CHANGE') !== 'deactivate',
        'password' => '', 'password_confirmation' => '',
    ], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
    fwrite(STDOUT, 'STATUS:'.$response->getStatusCode().PHP_EOL);
    PHP;

    try {
        DB::beginTransaction();
        User::orderBy('id')->lockForUpdate()->get();
        foreach ([0, 1] as $index) {
            $process = new Process([PHP_BINARY], base_path(), [
                'APP_ENV' => 'testing', 'DB_URL' => '', 'STAFF_TEST_DATABASE' => json_encode(config('database.connections.pgsql'), JSON_THROW_ON_ERROR),
                'STAFF_ACTOR_ID' => (string) $owners[$index]->id, 'STAFF_TARGET_ID' => (string) $owners[1 - $index]->id, 'STAFF_CHANGE' => $change,
            ], $worker, 20);
            $processes[] = $process;
            $process->start();
        }
        $pids = [];
        foreach ($processes as $process) {
            expect($process->waitUntil(fn (string $type, string $output): bool => str_contains($process->getOutput(), 'READY:')))->toBeTrue();
            preg_match('/READY:(\d+)/', $process->getOutput(), $matches);
            $pids[] = (int) $matches[1];
        }
        $deadline = microtime(true) + 5;
        do {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $waiting = (int) DB::selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE pid IN (?, ?) AND wait_event_type = 'Lock'", $pids)->total;
            if ($waiting === 2) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        expect($waiting)->toBe(2);
        DB::commit();
        $statuses = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue();
            preg_match('/STATUS:(\d+)/', $process->getOutput(), $matches);
            $statuses[] = (int) ($matches[1] ?? 0);
        }
        sort($statuses);
        expect($statuses)->toBe([302, 403])
            ->and(User::whereKey($owners->modelKeys())->where('role', 'owner')->where('is_active', true)->count())->toBe(1);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        User::whereKey($owners->modelKeys())->delete();
    }
})->with(['demote', 'deactivate']);
