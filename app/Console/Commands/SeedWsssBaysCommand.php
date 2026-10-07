<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\Bay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedWsssBaysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:wsss-bays';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Singapore Changi Airport (WSSS) bay data';

    /**
     * The WSSS airport bays to be seeded.
     *
     * @var array
     */
    protected $bays = [
        'A1', 'A2', 'A3', 'A4', 'A5', 'A9', 'A10', 'A11', 'A12', 'A13', 'A14', 'A15', 'A16', 'A17', 'A18', 'A19', 'A20', 'A21',
        'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
        'C1', 'C11', 'C13', 'C15', 'C16', 'C17', 'C17L', 'C17R', 'C18', 'C19', 'C20', 'C22', 'C23', 'C24', 'C25', 'C26',
        'D30', 'D32', 'D34', 'D35', 'D36', 'D37', 'D38', 'D40', 'D40L', 'D40R', 'D41', 'D42', 'D42L', 'D42R', 'D44', 'D46', 'D47', 'D48', 'D49',
        'E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7', 'E8', 'E10', 'E11', 'E12', 'E20', 'E22', 'E24', 'E24L', 'E24R', 'E26', 'E27', 'E27L', 'E27R', 'E28',
        'F30', 'F31', 'F32', 'F33', 'F34', 'F35', 'F35L', 'F35R', 'F36', 'F37', 'F40', 'F41', 'F42', 'F50', 'F52', 'F52L', 'F52R', 'F54', 'F56', 'F56L', 'F56R', 'F58', 'F59', 'F59L', 'F59R', 'F60',
        'G1', 'G2', 'G3', 'G4', 'G5', 'G6', 'G7', 'G8', 'G9', 'G10', 'G11', 'G12', 'G13', 'G14', 'G15', 'G16', 'G17', 'G18', 'G18L', 'G18R', 'G19', 'G19L', 'G19R', 'G20', 'G20L', 'G20R', 'G21', 'G21L', 'G21R',
        '200', '200L', '200R', '201', '202', '202L', '202R', '203', '205', '206', '207', '208', '208L', '208R',
        '300', '301', '302', '303', '304', '305', '306', '307', '308', '309', '310',
        '951', '951L', '951R', '952', '953', '953L', '953R', '954', '954L', '954R',
        '400', '401', '402', '403', '404',
        '461', '462', '462L', '462R', '463', '463L', '463R', '464', '465', '466', '467', '468', '469', '471', '472', '473', '474', '475', '476', '477', '478', '479', '480', '481', '482', '483', '484', '485', '486', '487',
        '502', '503', '504', '505', '506', '507', '508', '509', '510', '511', '512', '513', '514', '515', '516', '516L', '516R', '517', '517L', '517R',
        '600', '600L', '600R', '601', '602', '603', '604', '605', '606', '609', '611', '612'
    ];

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
        $this->info('Starting WSSS bay seeding...');

        // Check if WSSS airport exists
        $airport = Airport::where('icao', 'WSSS')->first();

        if (!$airport) {
            $this->error('WSSS airport not found in the airports table.');
            $this->error('Please ensure Singapore Changi Airport (WSSS) exists before running this command.');
            return Command::FAILURE;
        }

        $this->info("Found WSSS airport: {$airport->name} (ID: {$airport->id})");

        // Initialize counters
        $totalBays = count($this->bays);
        $createdCount = 0;
        $skippedCount = 0;

        // Create progress bar
        $progressBar = $this->output->createProgressBar($totalBays);
        $progressBar->start();

        // Process each bay
        DB::transaction(function () use ($airport, &$createdCount, &$skippedCount, $progressBar) {
            foreach ($this->bays as $bayName) {
                // Check if bay already exists
                $existingBay = Bay::where('airport_id', $airport->id)
                                 ->where('name', $bayName)
                                 ->first();

                if ($existingBay) {
                    $skippedCount++;
                } else {
                    // Create new bay
                    Bay::create([
                        'airport_id' => $airport->id,
                        'name' => $bayName,
                    ]);
                    $createdCount++;
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info("✅ WSSS bay seeding completed successfully!");
        $this->info("📊 Summary:");
        $this->info("   • Total bays processed: {$totalBays}");
        $this->info("   • New bays created: {$createdCount}");
        $this->info("   • Existing bays skipped: {$skippedCount}");

        if ($skippedCount > 0) {
            $this->warn("ℹ️  {$skippedCount} bays were skipped because they already exist in the database.");
        }

        return Command::SUCCESS;
    }
}
