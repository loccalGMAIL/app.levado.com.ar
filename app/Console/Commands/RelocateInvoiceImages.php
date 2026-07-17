<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RelocateInvoiceImages extends Command
{
    protected $signature = 'invoices:relocate {--dry-run : Solo listar, sin mover archivos}';

    protected $description = 'Mueve los comprobantes de compras del disco público al disco privado (local)';

    public function handle(): int
    {
        $paths = Storage::disk('public')->allFiles('purchases');

        if (empty($paths)) {
            $this->info('No hay comprobantes en el disco público. Nada que mover.');

            return self::SUCCESS;
        }

        $moved = 0;

        foreach ($paths as $path) {
            if ($this->option('dry-run')) {
                $this->line("[dry-run] {$path}");

                continue;
            }

            if (! Storage::disk('local')->exists($path)) {
                Storage::disk('local')->put($path, (string) Storage::disk('public')->get($path));
            }

            Storage::disk('public')->delete($path);
            $moved++;
        }

        $this->info($this->option('dry-run')
            ? count($paths).' comprobante(s) pendientes de mover.'
            : "{$moved} comprobante(s) movidos al disco privado.");

        return self::SUCCESS;
    }
}
