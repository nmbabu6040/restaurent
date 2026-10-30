<x-admin.layout title="Backups">
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat label="Last backup" :value="$last ? $last->diffForHumans() : 'Never'" icon="clock" :tone="$last && $last->gt(now()->subDays(2)) ? 'emerald' : 'rose'" :sub="$last?->format('d M Y, g:i A')" />
        <x-admin.stat label="Backups kept" :value="number_format(count($backups))" icon="receipt" tone="indigo" :sub="'Newest ' . $keep . ' of each type'" />
        <x-admin.stat label="Daily backup" :value="$scheduled ? 'On' : 'Off'" icon="calendar" :tone="$scheduled ? 'cyan' : 'amber'" :sub="$scheduled
            ? 'at ' . config('restaurant.backup.time') . ' (needs the cron job)'
            : 'Turn on with BACKUP_ENABLED=true'" />
    </div>

    <div class="card mt-6 p-6">
        <form method="POST" action="{{ route('admin.backups.run') }}" class="flex flex-wrap items-center gap-4">
            @csrf
            <button type="submit" class="btn-accent">Create a backup now</button>
            <p class="text-sm">Saves the database and the uploaded photos. This can take a minute on a large site.</p>
        </form>
        @unless ($zipAvailable)
            <p class="mt-3 text-sm font-semibold text-red-700">The PHP zip extension is missing, so photos are not backed
                up. Ask your host to enable it.</p>
        @endunless
    </div>

    <div class="card mt-6 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-navy/10 text-navy">
                <tr>
                    <th scope="col" class="p-4">File</th>
                    <th scope="col" class="p-4">Type</th>
                    <th scope="col" class="p-4">Created</th>
                    <th scope="col" class="p-4 text-right">Size</th>
                    <th scope="col" class="p-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($backups as $b)
                    <tr class="border-b border-navy/5">
                        <td class="p-4 font-mono text-xs">{{ $b['name'] }}</td>
                        <td class="p-4">{{ $b['type'] }}</td>
                        <td class="p-4">{{ $b['time']->format('d M Y, g:i A') }}</td>
                        <td class="p-4 text-right">{{ number_format($b['size'] / 1024, $b['size'] > 1048576 ? 0 : 1) }}
                            KB</td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.backups.download', $b['name']) }}"
                                    class="font-semibold text-navy hover:text-terracotta-dark">Download</a>
                                <form method="POST" action="{{ route('admin.backups.destroy', $b['name']) }}"
                                    data-confirm="Delete {{ $b['name'] }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="font-semibold text-red-700 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center">No backups yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <section class="card mt-6 space-y-3 p-6 text-sm" aria-labelledby="notes-title">
        <h2 id="notes-title" class="text-xl font-semibold">Please read</h2>
        <p><strong>Keep a copy somewhere else.</strong> These files are on the same server as the website. If the server
            is lost, they are lost too. Download them regularly, or copy the folder <code
                class="rounded bg-navy/10 px-1">{{ $directory }}</code> to another place.</p>
        <p><strong>They contain customers' names, phone numbers and addresses.</strong> Store them safely and do not
            share them.</p>
        <p><strong>Restoring is done outside the admin</strong>, on purpose, so nobody can overwrite the live data by a
            mis-click. To restore: put the site in maintenance mode, then import the <code
                class="rounded bg-navy/10 px-1">db-….sql.gz</code> file in phpMyAdmin (Import, it reads .gz) or run
            <code class="rounded bg-navy/10 px-1">gunzip -c db-….sql.gz | mysql -u USER -p DATABASE</code>. Unzip the
            photos archive into <code class="rounded bg-navy/10 px-1">storage/app</code> (it contains a <code
                class="rounded bg-navy/10 px-1">public</code> folder). Try a restore once on a test database so you know
            it works.</p>
        <p>This backup is made by the website itself and covers tables and data only. For a very large database, <code
                class="rounded bg-navy/10 px-1">mysqldump</code> is faster and more complete.</p>
    </section>
</x-admin.layout>
