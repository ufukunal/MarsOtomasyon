<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Doc2AuditCommand extends Command
{
    protected $signature = 'doc2:audit {--strict : Başarısız dönüşüm referansları varsa sıfırdan farklı kodla çık}';

    protected $description = 'DOC2 dönem bağımlılıklarını salt okunur tarar; veritabanına bağlanmaz veya migration çalıştırmaz.';

    /** @var array<string,string> */
    private const MARKERS = [
        'PeriodContext::' => 'Dönem bağlamı çağrısı',
        'period_user_access' => 'Dönem bazlı kullanıcı erişimi',
        'last_period_id' => 'Son dönem kimliği',
        'CreatePeriod' => 'Dönem veritabanı oluşturma',
        'CarryPeriod' => 'Dönem devri',
        'period.select' => 'Dönem seçimi rotası',
        "->connection('period')" => 'Dönem veritabanı bağlantısı',
        "DB::connection('period')" => 'Dönem veritabanı bağlantısı',
    ];

    public function handle(): int
    {
        $totals = array_fill_keys(array_keys(self::MARKERS), 0);
        $scanned = 0;

        foreach (['app', 'routes', 'database/migrations', 'resources/views'] as $directory) {
            $root = base_path($directory);

            if (! is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\\.(php|blade\\.php)$/', $file->getFilename())) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if ($contents === false) {
                    $this->error('DOC2 audit okunamayan dosya: '.$file->getPathname());

                    return self::FAILURE;
                }

                $scanned++;

                foreach (self::MARKERS as $needle => $label) {
                    $totals[$needle] += substr_count($contents, $needle);
                }
            }
        }

        $this->info('DOC2 salt okunur bağımlılık envanteri: '.$scanned.' kaynak dosyası');

        foreach (self::MARKERS as $needle => $label) {
            $this->line(sprintf('%s: %d', $label, $totals[$needle]));
        }

        $count = array_sum($totals);
        $this->line('İncelenmesi gereken toplam referans: '.$count);
        $this->warn('Bu sayı benzersiz hatalı iş akışı veya tamamlanma yüzdesi değildir.');

        if ($this->option('strict') && $count !== 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
