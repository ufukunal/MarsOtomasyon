<?php

namespace App\Actions\Printing;

use App\Support\Printing\PrintResult;
use InvalidArgumentException;
use Throwable;

final class RunBatchPrint
{
    /**
     * @param  array<int, callable(): PrintResult>  $items
     * @return array{done:int,failed:int,results:array<int,array<string,mixed>>}
     */
    public function execute(array $items): array
    {
        $done = 0;
        $failed = 0;
        $results = [];

        foreach ($items as $index => $item) {
            if (! is_callable($item)) {
                throw new InvalidArgumentException("Batch print item {$index} callable olmalıdır.");
            }

            try {
                $result = $item();

                if (! $result instanceof PrintResult) {
                    throw new InvalidArgumentException("Batch print item {$index} PrintResult döndürmelidir.");
                }

                $done++;
                $results[] = [
                    'index' => $index,
                    'status' => 'done',
                    'filename' => $result->filename,
                    'mime_type' => $result->mimeType,
                    'output_hash' => hash('sha256', $result->content),
                ];
            } catch (Throwable $exception) {
                $failed++;
                $results[] = [
                    'index' => $index,
                    'status' => 'failed',
                    'error_class' => class_basename($exception),
                    'error_summary' => 'Print operation failed.',
                ];
            }
        }

        return ['done' => $done, 'failed' => $failed, 'results' => $results];
    }
}
