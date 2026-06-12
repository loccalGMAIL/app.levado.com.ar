<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('invoices:move-to-private {--dry-run : Listar lo que se movería sin tocar archivos}')]
#[Description('Mueve las imágenes de facturas del disco público al privado (local).')]
class MoveInvoiceImagesToPrivateDisk extends Command
{
    /**
     * Migrate existing invoice images from the public disk (web-accessible via
     * the /storage symlink) to the private local disk, so they are only served
     * through authenticated controller routes.
     */
    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $dryRun = (bool) $this->option('dry-run');

        $purchases = Purchase::whereNotNull('invoice_image_path')->get();

        $moved = 0;
        $missing = 0;
        $alreadyPrivate = 0;

        foreach ($purchases as $purchase) {
            $path = $purchase->invoice_image_path;

            if ($private->exists($path)) {
                $alreadyPrivate++;

                continue;
            }

            if (! $public->exists($path)) {
                $missing++;
                $this->warn("Sin archivo en ninguno de los dos discos: {$path} (compra #{$purchase->id})");

                continue;
            }

            if ($dryRun) {
                $this->line("Se movería: {$path} (compra #{$purchase->id})");
                $moved++;

                continue;
            }

            $private->put($path, $public->get($path));
            $public->delete($path);
            $moved++;
            $this->line("Movido: {$path}");
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '').sprintf(
            '%d movido(s), %d ya estaban en privado, %d sin archivo.',
            $moved,
            $alreadyPrivate,
            $missing,
        ));

        return self::SUCCESS;
    }
}
