<?php

namespace App\Jobs;

use App\Imports\AirlinesImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ImportAirlinesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            Log::info('Starting airline import job');

            // Extract airline logos first
            $extractedLogos = $this->extractAirlineLogos();

            // Import airlines from CSV and set logo paths
            $this->importAirlinesFromCsv($extractedLogos);

            Log::info('Airline import job completed successfully');

        } catch (\Exception $e) {
            Log::error('Airline import job failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Import airlines from the CSV file
     */
    private function importAirlinesFromCsv(array $extractedLogos): void
    {
        $csvPath = resource_path('airlines_data/airlines.csv');

        if (!file_exists($csvPath)) {
            throw new \Exception('Airlines CSV file not found at: ' . $csvPath);
        }

        Log::info('Importing airlines from CSV: ' . $csvPath);

        // Import using Laravel Excel with logo paths
        (new AirlinesImport($extractedLogos))->import($csvPath);

        Log::info('Airlines CSV import completed');
    }

    /**
     * Extract airline logos from the ZIP file
     *
     * @return array Array of ICAO codes that have logos available
     */
    private function extractAirlineLogos(): array
    {
        $zipPath = resource_path('airlines_data/logos.zip');
        $extractedLogos = [];

        if (!file_exists($zipPath)) {
            Log::warning('Airlines logos ZIP file not found at: ' . $zipPath);
            return $extractedLogos;
        }

        Log::info('Extracting airline logos from ZIP: ' . $zipPath);

        // Ensure the target directory exists
        $targetPath = storage_path('app/public/airlines');
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new \Exception('Failed to open logos ZIP file: ' . $zipPath);
        }

        $extractedCount = 0;
        $skippedCount = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Skip directories and hidden files
            if (substr($filename, -1) == '/' || strpos($filename, '.') === 0) {
                continue;
            }

            $basename = basename($filename);
            $targetFilePath = $targetPath . '/' . $basename;

            // Extract ICAO from filename (e.g., UAE.gif -> UAE)
            $icao = strtoupper(pathinfo($basename, PATHINFO_FILENAME));

            // Skip if file already exists (don't overwrite)
            if (file_exists($targetFilePath)) {
                Log::info('Skipping existing logo file: ' . $basename);
                $skippedCount++;
                // Still add to extracted logos since file exists
                $extractedLogos[] = $icao;
                continue;
            }

            // Extract the file
            $fileContent = $zip->getFromIndex($i);
            if ($fileContent !== false) {
                file_put_contents($targetFilePath, $fileContent);
                Log::info('Extracted logo file: ' . $basename);
                $extractedCount++;
                $extractedLogos[] = $icao;
            } else {
                Log::warning('Failed to extract file: ' . $filename);
            }
        }

        $zip->close();

        Log::info("Logo extraction completed. Extracted: {$extractedCount}, Skipped: {$skippedCount}");

        return $extractedLogos;
    }
}
