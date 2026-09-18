<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class SimpleArrayExport implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private array $rows, private string $title = 'Laporan')
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}
