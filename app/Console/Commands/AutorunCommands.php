<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use App\Models\Command as CommandModel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Exception\ProcessFailedException;

class AutorunCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'autorun:commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Autorun all commands';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->command = new CommandModel();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $data = json_decode(file_get_contents(storage_path('json/commands/commands.json')), true);

        if (!Schema::hasTable('migrations')) {
            $command = [
                'command' => 'php artisan migrate:fresh',
                'description' => 'Running fresh migrations',
                'script_type' => 'migration',
                'command_type' => 'recurring',
                'environtment' => 'both'
            ];
            $this->info("\033[1;37m[+] ".$command['description'].' ...');
            $this->executeCommand($command);
        }

        if (!Schema::hasTable('commands')) {
            $command = [
                'command' => 'php artisan migrate',
                'description' => 'Running migrations',
                'script_type' => 'migration',
                'command_type' => 'recurring',
                'environtment' => 'both'
            ];

            $this->info("\033[1;37m[+] ".$command['description'].' ...');
            $this->executeCommand($command);

            $this->command->create($command);
        }

        foreach ($data as $row) {
            if ($row['environtment'] == 'both' || $row['environtment'] == env('APP_ENV')) {
                $row['command'] = trim($row['command']);

                $command = $this->command->select('id')->where('command', $row['command'])->first();

                if (!$command) {
                    $this->info("\033[1;37m[+] ".$row['description'].' ...');
                    $this->command->create($row);
                    $this->executeCommand($row);
                    continue;
                }

                $command->update([
                    'description' => $row['description'],
                    'script_type' => $row['script_type'],
                    'command_type' => $row['command_type'],
                    'environtment' => $row['environtment'],
                ]);

                if ($row['command_type'] != 'one-times') {
                    $this->info("\033[1;37m[+] ".$row['description'].' ...');
                    $this->executeCommand($row);
                }
            }
        }
    }

    public function executeCommand($command)
    {
        $explodeCommand = explode(' ', $command['command']);

        $process = new Process($explodeCommand);

        if (env('APP_ENV') == 'production') {
            if ($command['script_type'] == 'seeder') {
                $process->setInput('yes');
            }

            if ($command['script_type'] == 'migration') {
                $process->setInput('yes');
            }

            if ($command['script_type'] == 'drop-database') {
                $process->setInput('yes');
            }
        }

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->info($process->getOutput());
    }
}
