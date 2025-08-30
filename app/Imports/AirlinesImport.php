<?php

namespace App\Imports;

use App\Models\Airline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithValidation;

class AirlinesImport implements ShouldQueue, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithUpserts, WithValidation, SkipsOnFailure
{
    use Importable;
    use SkipsFailures;

    protected array $extractedLogos;

    public function __construct(array $extractedLogos = [])
    {
        $this->extractedLogos = $extractedLogos;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $icao = strtoupper($row['icao']);
        $logoPath = null;

        // Check if logo exists for this ICAO
        if (in_array($icao, $this->extractedLogos)) {
            $logoPath = 'airlines/' . $icao . '.gif';
        }

        return new Airline([
            'icao' => $icao,
            'name' => $row['name'],
            'callsign' => $row['callsign'] ?? null,
            'logo_path' => $logoPath,
        ]);
    }

    public function rules(): array
    {
        return [
            'icao' => ['required', 'string', Rule::unique('airlines', 'icao')],
            'name' => ['required', 'string'],
            'callsign' => ['nullable', 'string'],
        ];
    }

    public function uniqueBy()
    {
        return 'icao';
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
