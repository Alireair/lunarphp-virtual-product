<?php

namespace Armezit\Lunar\VirtualProduct\Jobs;

use Armezit\Lunar\VirtualProduct\Enums\CodePoolBatchStatus;
use Armezit\Lunar\VirtualProduct\Models\CodePoolBatch;
use Armezit\Lunar\VirtualProduct\Models\CodePoolItem;
use Armezit\Lunar\VirtualProduct\Models\CodePoolSchema;
use Armezit\Lunar\VirtualProduct\Utils\ChunkIterator;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;
use Throwable;

class ImportCodePoolDataFromCsvFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use Batchable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public CodePoolBatch  $codePoolBatch,
        public CodePoolSchema $codePoolSchema,
        public array          $columnsToMap,
        public string         $csvFilePath,
    )
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Throwable
     */
    public function handle()
    {
        $chunkSize = config('lunarphp-virtual-product.code_pool.import.chunk_size', 10);
        $chunks = (new ChunkIterator($this->getCsvDataReader()->getIterator(), $chunkSize))->get();

        $jobs = collect($chunks)->map(fn($chunk) => new ImportCodePoolData(
            $this->codePoolBatch,
            $this->codePoolSchema,
            $chunk,
            $this->columnsToMap
        ));

        // NOTE: "$this" is not allowed in serialized closures
        $codePoolBatchId = $this->codePoolBatch->id;

        Bus::batch($jobs)
            ->name(sprintf('Code pool csv import: purchasable_id=%s', $this->codePoolBatch->purchasable_id))
            ->withOption('tags', ['Virtual Product'])
            ->then(function (Batch $batch) use ($codePoolBatchId) {
                // All jobs completed successfully...

                CodePoolBatch::where('id', $codePoolBatchId)->update([
                    'status' => CodePoolBatchStatus::Completed->value
                ]);

            })->catch(function (Batch $batch, Throwable $e) use ($codePoolBatchId) {
                // First batch job failure detected...

                DB::transaction(function () use ($codePoolBatchId) {
                    CodePoolBatch::where('id', $codePoolBatchId)->update([
                        'status' => CodePoolBatchStatus::Failed->value
                    ]);
                    CodePoolItem::where('batch_id', $codePoolBatchId)->delete();
                });

            })->finally(function (Batch $batch) {
                // The batch has finished executing...
                $a = 1;
            })
            ->dispatch();
    }

    private function getCsvDataReader()
    {
        $csv = Reader::createFromPath($this->csvFilePath)
            ->setHeaderOffset(0)
            ->skipEmptyRecords();

        return Statement::create()->process($csv);
    }
}
