<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

class BackupService
{
    /** Data that rebuilds itself, so saving it would only make the backup bigger. */
    private const SKIP_DATA = ['cache', 'cache_locks', 'sessions'];

    public function directory(): string
    {
        return rtrim(config('restaurant.backup.path') ?: storage_path('app/backups'), '/\\');
    }

    /** @return array{db: string, uploads: ?string} */
    public function create(bool $withUploads = true): array
    {
        File::ensureDirectoryExists($this->directory(), 0750);

        $stamp = now()->format('Ymd-His');
        $db = $this->dumpDatabase($this->directory() . "/db-{$stamp}.sql.gz");
        $uploads = $withUploads ? $this->zipUploads($this->directory() . "/uploads-{$stamp}.zip") : null;

        $this->prune();

        return ['db' => $db, 'uploads' => $uploads];
    }

    /** @return array<int, array{name: string, type: string, size: int, time: Carbon}> newest first */
    public function list(): array
    {
        if (! is_dir($this->directory())) {
            return [];
        }

        return collect(File::files($this->directory()))
            ->filter(fn($file) => $this->validName($file->getFilename()))
            ->map(fn($file) => [
                'name' => $file->getFilename(),
                'type' => str_starts_with($file->getFilename(), 'db-') ? 'Database' : 'Photos',
                'size' => $file->getSize(),
                'time' => Carbon::createFromTimestamp($file->getMTime()),
            ])
            ->sortByDesc('name')
            ->values()
            ->all();
    }

    /** Only names this service creates are accepted, so a request can never reach any other file. */
    public function validName(string $name): bool
    {
        return preg_match('/^(db-\d{8}-\d{6}\.sql\.gz|uploads-\d{8}-\d{6}\.zip)$/', $name) === 1;
    }

    public function path(string $name): ?string
    {
        $path = $this->directory() . '/' . $name;

        return $this->validName($name) && is_file($path) ? $path : null;
    }

    public function delete(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && @unlink($path);
    }

    public function prune(): void
    {
        $keep = max(1, (int) config('restaurant.backup.keep'));

        foreach (['db-', 'uploads-'] as $prefix) {
            collect($this->list())
                ->filter(fn($file) => str_starts_with($file['name'], $prefix))
                ->sortByDesc('name')
                ->slice($keep)
                ->each(fn($file) => @unlink($this->directory() . '/' . $file['name']));
        }
    }

    private function dumpDatabase(string $target): string
    {
        $temporary = $target . '.tmp';
        $gz = gzopen($temporary, 'wb6');

        if ($gz === false) {
            throw new RuntimeException('Cannot write the backup file. Check the folder permissions.');
        }

        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $pdo = $connection->getPdo();
            $quote = fn(string $name) => '`' . str_replace('`', '``', $name) . '`';

            gzwrite($gz, '-- Database backup ' . now()->toDateTimeString() . "\n");
            gzwrite($gz, $driver === 'sqlite' ? "PRAGMA foreign_keys=OFF;\n" : "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n");

            foreach ($this->tables($driver) as $table) {
                gzwrite($gz, "\nDROP TABLE IF EXISTS {$quote($table)};\n" . $this->createStatement($driver, $table) . ";\n");

                if (in_array($table, self::SKIP_DATA, true)) {
                    continue;
                }

                $columns = Schema::getColumnListing($table);
                $list = implode(',', array_map($quote, $columns));

                DB::table($table)->orderBy($columns[0])->chunk(500, function ($rows) use ($gz, $pdo, $quote, $table, $list, $columns) {
                    $values = [];

                    foreach ($rows as $row) {
                        $cells = [];

                        foreach ($columns as $column) {
                            $value = $row->{$column};
                            $cells[] = match (true) {
                                $value === null => 'NULL',
                                is_bool($value) => $value ? '1' : '0',
                                is_int($value), is_float($value) => (string) $value,
                                default => $pdo->quote((string) $value),
                            };
                        }

                        $values[] = '(' . implode(',', $cells) . ')';
                    }

                    gzwrite($gz, "INSERT INTO {$quote($table)} ({$list}) VALUES\n" . implode(",\n", $values) . ";\n");
                });
            }

            gzwrite($gz, $driver === 'sqlite' ? "PRAGMA foreign_keys=ON;\n" : "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($temporary);

            throw $e;
        }

        gzclose($gz);
        rename($temporary, $target);

        return $target;
    }

    private function tables(string $driver): array
    {
        $names = $driver === 'sqlite'
            ? DB::table('sqlite_master')->where('type', 'table')->where('name', 'not like', 'sqlite_%')->pluck('name')
            : collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))->map(fn($row) => array_values((array) $row)[0]);

        return $names->sort()->values()->all();
    }

    private function createStatement(string $driver, string $table): string
    {
        if ($driver === 'sqlite') {
            return (string) DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql');
        }

        $row = (array) DB::selectOne('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');

        return (string) ($row['Create Table'] ?? array_values($row)[1] ?? '');
    }

    private function zipUploads(string $target): ?string
    {
        $source = storage_path('app/public');

        if (! class_exists(ZipArchive::class) || ! is_dir($source)) {
            return null;
        }

        $files = File::allFiles($source);

        if ($files === []) {
            return null;
        }

        $zip = new ZipArchive;
        $temporary = $target . '.tmp';

        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the photos archive.');
        }

        foreach ($files as $file) {
            $zip->addFile($file->getRealPath(), 'public/' . str_replace('\\', '/', $file->getRelativePathname()));
        }

        $zip->close();
        rename($temporary, $target);

        return $target;
    }
}
