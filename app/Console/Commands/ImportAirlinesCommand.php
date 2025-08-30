<?php

namespace App\Console\Commands;

use App\Jobs\ImportAirlinesJob;
use Illuminate\Console\Command;

class ImportAirlinesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:airlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import airlines from airlines.csv file and extract logos';

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
     * @return int
     */
    public function handle()
    {
        $this->info('Starting airline import...');

        ImportAirlinesJob::dispatch();

        $this->info('Airline import job dispatched successfully!');

        return Command::SUCCESS;
    }
}
