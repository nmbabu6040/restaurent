<?php

namespace Tests\Feature;

use App\Mail\DailySalesSummaryMail;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperationsToolsTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = sys_get_temp_dir() . '/meher-backups-' . uniqid();
        config(['restaurant.backup.path' => $this->backupDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function staff(): User
    {
        return User::factory()->staff()->create();
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    private function orderWithItems(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge(['notes' => 'No chili please', 'subtotal' => '380.00', 'delivery_fee' => '60.00', 'total' => '440.00'], $attributes));
        $order->items()->create([
            'name_en' => 'Kacchi Biryani',
            'variation_en' => 'Full',
            'addons' => [['name_en' => 'Egg', 'name_bn' => null, 'price' => 30]],
            'quantity' => 2,
            'unit_price' => '380.00',
            'line_total' => '760.00',
        ]);

        return $order;
    }

    // Backups

    public function test_a_backup_contains_the_structure_and_the_data(): void
    {
        $order = Order::factory()->create(['reference' => 'MO-BACKUP01']);
        DB::table('sessions')->insert(['id' => 'secret-session-id', 'payload' => 'x', 'last_activity' => time()]);

        $result = app(BackupService::class)->create(false);

        $this->assertFileExists($result['db']);
        $this->assertNull($result['uploads']);

        $sql = gzdecode(file_get_contents($result['db']));
        $this->assertStringContainsString('DROP TABLE IF EXISTS `orders`', $sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO `orders`', $sql);
        $this->assertStringContainsString('MO-BACKUP01', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `sessions`', $sql);   // structure yes
        $this->assertStringNotContainsString('secret-session-id', $sql);             // login sessions never
        $this->assertFileDoesNotExist($result['db'] . '.tmp');
    }

    public function test_the_photos_are_zipped(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('The zip extension is not installed.');
        }

        $dir = storage_path('app/public/backup-test');
        File::ensureDirectoryExists($dir);
        File::put($dir . '/photo.txt', 'hello');

        try {
            $result = app(BackupService::class)->create(true);

            $this->assertNotNull($result['uploads']);
            $zip = new \ZipArchive;
            $zip->open($result['uploads']);
            $this->assertNotFalse($zip->locateName('public/backup-test/photo.txt'));
            $zip->close();
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_only_the_newest_backups_are_kept(): void
    {
        File::ensureDirectoryExists($this->backupDir);
        foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $stamp) {
            File::put("{$this->backupDir}/db-{$stamp}.sql.gz", 'x');
            File::put("{$this->backupDir}/uploads-{$stamp}.zip", 'x');
        }
        File::put("{$this->backupDir}/notes.txt", 'not a backup');
        config(['restaurant.backup.keep' => 2]);

        app(BackupService::class)->prune();

        $names = array_column(app(BackupService::class)->list(), 'name');
        $this->assertSame(['uploads-20260103-000000.zip', 'uploads-20260102-000000.zip', 'db-20260103-000000.sql.gz', 'db-20260102-000000.sql.gz'], $names);
        $this->assertFileExists("{$this->backupDir}/notes.txt"); // unrelated files are never touched
    }

    public function test_only_names_made_by_the_service_are_valid(): void
    {
        $service = app(BackupService::class);

        $this->assertTrue($service->validName('db-20260101-000000.sql.gz'));
        $this->assertTrue($service->validName('uploads-20260101-000000.zip'));
        foreach (['../.env', '..%2f.env', 'db-20260101-000000.sql.gz/../x', 'db-1.sql.gz', 'passwd', '.env', 'db-20260101-000000.php'] as $bad) {
            $this->assertFalse($service->validName($bad), $bad);
        }
    }

    public function test_only_admins_reach_the_backup_pages(): void
    {
        $this->get('/admin/backups')->assertRedirect('/admin/login');
        $this->actingAs($this->staff())->get('/admin/backups')->assertForbidden();
        $this->actingAs($this->manager())->get('/admin/backups')->assertForbidden();
        $this->actingAs($this->manager())->post('/admin/backups')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/backups')->assertOk()->assertSee('Create a backup now');
    }

    public function test_an_admin_can_create_download_and_delete_a_backup(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/backups')->assertSessionHas('status');

        $name = app(BackupService::class)->list()[0]['name'];
        $this->actingAs($admin)->get('/admin/backups')->assertSee($name);

        $response = $this->actingAs($admin)->get(route('admin.backups.download', $name))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $this->actingAs($admin)->delete(route('admin.backups.destroy', $name))->assertSessionHas('status');
        $this->assertSame([], array_filter(app(BackupService::class)->list(), fn($f) => $f['name'] === $name));

        foreach (['backup.created', 'backup.downloaded', 'backup.deleted'] as $action) {
            $this->assertDatabaseHas('activity_logs', ['action' => $action, 'user_id' => $admin->id]);
        }
    }

    public function test_backup_downloads_cannot_reach_other_files(): void
    {
        $admin = $this->admin();
        File::ensureDirectoryExists($this->backupDir);
        File::put($this->backupDir . '/secret.txt', 'private');

        $this->actingAs($admin)->get('/admin/backups/secret.txt')->assertNotFound();
        $this->actingAs($admin)->get('/admin/backups/db-20260101-000000.sql.gz')->assertNotFound(); // valid name, but no such file
        $this->actingAs($admin)->get('/admin/backups/..%2F..%2F.env')->assertNotFound();
        $this->actingAs($admin)->delete('/admin/backups/secret.txt')->assertNotFound();
        $this->assertFileExists($this->backupDir . '/secret.txt');
    }

    public function test_the_backup_command_runs(): void
    {
        $this->artisan('backup:run', ['--no-uploads' => true])->assertSuccessful();

        $files = app(BackupService::class)->list();
        $this->assertCount(1, $files);
        $this->assertSame('Database', $files[0]['type']);
    }

    // Printing

    public function test_the_kitchen_ticket_has_no_prices_and_shows_the_notes(): void
    {
        $order = $this->orderWithItems();

        $this->actingAs($this->staff())->get(route('admin.orders.print', ['order' => $order, 'type' => 'kot']))
            ->assertOk()
            ->assertSee('KITCHEN ORDER')
            ->assertSee('2 x Kacchi Biryani')
            ->assertSee('Full, + Egg')
            ->assertSee('No chili please')
            ->assertSee($order->reference)
            ->assertDontSee('380')
            ->assertDontSee('TOTAL');
    }

    public function test_the_receipt_shows_prices_and_totals(): void
    {
        $order = $this->orderWithItems(['coupon_code' => 'WELCOME10', 'discount' => '38.00']);

        $this->actingAs($this->staff())->get(route('admin.orders.print', ['order' => $order, 'type' => 'receipt']))
            ->assertOk()
            ->assertSee('Kacchi Biryani')
            ->assertSee('৳ 760')
            ->assertSee('TOTAL')
            ->assertSee('Discount (WELCOME10)')
            ->assertSee('Thank you!');
    }

    public function test_printing_rules(): void
    {
        $order = $this->orderWithItems();

        $this->get(route('admin.orders.print', ['order' => $order, 'type' => 'kot']))->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->rider()->create())->get(route('admin.orders.print', ['order' => $order, 'type' => 'kot']))->assertForbidden();
        $this->actingAs($this->staff())->get('/admin/orders/' . $order->id . '/print/invoice')->assertNotFound();

        $this->actingAs($this->staff())->get(route('admin.orders.print', ['order' => $order, 'type' => 'kot', 'autoprint' => 1]))
            ->assertSee('data-autoprint="1"', false);
        $this->actingAs($this->staff())->get(route('admin.orders.print', ['order' => $order, 'type' => 'kot']))
            ->assertSee('data-autoprint="0"', false);

        $this->assertDatabaseHas('activity_logs', ['action' => 'order.printed']);
    }

    public function test_the_order_page_links_to_both_prints(): void
    {
        $order = $this->orderWithItems();

        $this->actingAs($this->staff())->get(route('admin.orders.show', $order))
            ->assertSee('Print kitchen ticket')->assertSee('Print receipt');
    }

    // The alert check

    public function test_the_alert_check_counts_what_needs_attention(): void
    {
        $cash = Order::factory()->create();                                    // counts
        $paid = Order::factory()->online()->paid()->create();                  // counts
        Order::factory()->online()->create();                                  // waiting for payment: no
        Order::factory()->create(['status' => 'confirmed']);                   // already handled: no
        $reservation = Reservation::factory()->create();

        $this->actingAs($this->staff())->getJson('/admin/orders/poll')
            ->assertOk()
            ->assertExactJson(['orders' => 2, 'latest' => $paid->id, 'reservations' => 1, 'reservation_latest' => $reservation->id]);
    }

    public function test_the_alert_check_is_private_and_not_confused_with_an_order(): void
    {
        $this->getJson('/admin/orders/poll')->assertUnauthorized();
        $this->actingAs(User::factory()->rider()->create())->getJson('/admin/orders/poll')->assertForbidden();

        $this->actingAs($this->staff())->get('/admin')->assertSee('data-alert-url', false)->assertSee('data-sound-toggle', false);
    }

    // Daily email

    public function test_the_daily_summary_is_emailed(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-15 22:00:00'));
        $order = Order::factory()->create(['created_at' => now()->subHour(), 'total' => '620.00', 'subtotal' => '620.00']);
        $order->items()->create(['name_en' => 'Biryani', 'quantity' => 2, 'unit_price' => '310.00', 'unit_cost' => '100.00', 'line_total' => '620.00']);

        $this->artisan('report:daily-summary', ['--to' => 'owner@example.com', '--date' => '2026-11-15'])->assertSuccessful();

        Mail::assertQueued(DailySalesSummaryMail::class, function ($mail) {
            return $mail->hasTo('owner@example.com')
                && $mail->summary['revenue'] === 620.0
                && $mail->summary['orders'] === 1
                && $mail->top[0]['name'] === 'Biryani'
                && $mail->profit['cogs'] === 200.0
                && str_contains($mail->envelope()->subject, '15 Nov 2026');
        });

        $html = (new DailySalesSummaryMail(
            '15 Nov 2026',
            ['revenue' => 620.0, 'orders' => 1, 'aov' => 620.0, 'items' => 2, 'delivery' => 0.0, 'discount' => 0.0],
            ['revenue' => 0.0],
            [['name' => 'Biryani', 'qty' => 2, 'revenue' => 620.0]],
            ['pending' => 1],
            ['cogs' => 200.0, 'net' => 420.0, 'uncosted' => 0],
            1
        ))->render();
        $this->assertStringContainsString('Biryani', $html);
        $this->assertStringContainsString('1 ingredient is', $html);
    }

    public function test_the_daily_summary_needs_a_recipient_and_a_valid_date(): void
    {
        Mail::fake();

        config(['restaurant.reports.daily_email' => null]);
        $this->artisan('report:daily-summary')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->artisan('report:daily-summary', ['--to' => 'owner@example.com', '--date' => 'yesterday-ish'])->assertFailed();
        Mail::assertNothingQueued();

        config(['restaurant.reports.daily_email' => 'owner@example.com']);
        $this->artisan('report:daily-summary')->assertSuccessful();
        Mail::assertQueued(DailySalesSummaryMail::class, 1);
    }

    // QR codes

    public function test_the_qr_page_shows_a_code_for_the_menu(): void
    {
        $this->actingAs($this->manager())->get('/admin/qr')
            ->assertOk()->assertSee('<svg', false)->assertSee(route('menu.index'), false);
    }

    public function test_the_qr_targets_and_custom_paths(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->get('/admin/qr?target=reserve')->assertSee(route('reservations.create'), false);
        $this->actingAs($manager)->get('/admin/qr?target=custom&path=/offers')->assertSee(url('/offers'), false);
        $this->actingAs($manager)->get('/admin/qr?target=custom')->assertSessionHasErrors('path');
        $this->actingAs($manager)->get('/admin/qr?target=custom&path=//evil.example')->assertSessionHasErrors('path');
        $this->actingAs($manager)->get('/admin/qr?target=custom&path=http://evil.example')->assertSessionHasErrors('path');
        $this->actingAs($manager)->get('/admin/qr?target=bogus')->assertSessionHasErrors('target');
    }

    public function test_the_qr_code_can_be_downloaded_and_printed(): void
    {
        $manager = $this->manager();

        $download = $this->actingAs($manager)->get('/admin/qr/download?target=menu')->assertOk();
        $this->assertStringContainsString('image/svg+xml', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="qr-menu.svg"', $download->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('<svg', $download->getContent());

        $this->actingAs($manager)->get('/admin/qr/print?target=menu&label=Scan+and+eat')
            ->assertOk()->assertSee('Scan and eat')->assertSee('<svg', false)->assertSee('data-autoprint="0"', false);
    }

    public function test_only_managers_and_admins_can_use_the_qr_tool(): void
    {
        $this->get('/admin/qr')->assertRedirect('/admin/login');
        $this->actingAs($this->staff())->get('/admin/qr')->assertForbidden();
        $this->actingAs($this->staff())->get('/admin/qr/download')->assertForbidden();
    }

    public function test_the_qr_label_is_escaped(): void
    {
        $this->actingAs($this->manager())->get('/admin/qr/print?target=menu&label=' . urlencode('<script>alert(1)</script>'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
