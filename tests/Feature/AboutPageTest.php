<?php

namespace Tests\Feature;

use App\Models\GalleryImage;
use App\Models\SpacePhoto;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    private function photo(array $attributes = []): SpacePhoto
    {
        return SpacePhoto::create(array_merge([
            'image_path' => 'space/' . uniqid() . '.webp',
            'alt_en' => 'The dining room',
            'sort_order' => 1,
            'is_active' => true,
        ], $attributes));
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    private function saveSettings(array $extra)
    {
        return $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), array_merge([
            'name_en' => 'Meher Cuisine',
            'phone_en' => '01712345678',
            'email_en' => 'hello@example.com',
        ], $extra));
    }

    public function test_the_section_stays_out_until_there_is_a_photo(): void
    {
        $this->get('/about')->assertOk()->assertDontSee('Our Space');

        // Gallery photos are a different thing and never fill this section.
        GalleryImage::create(['image_path' => 'gallery/x.webp', 'alt_en' => 'A gallery photo']);
        $this->get('/about')->assertDontSee('Our Space')->assertDontSee('A gallery photo');
    }

    public function test_photos_show_in_order_with_captions_and_hidden_ones_stay_hidden(): void
    {
        $this->photo(['alt_en' => 'Second photo', 'caption_en' => 'Second caption', 'sort_order' => 2]);
        $this->photo(['alt_en' => 'First photo', 'caption_en' => 'First caption', 'sort_order' => 1]);
        $this->photo(['alt_en' => 'Hidden photo', 'is_active' => false]);

        $this->get('/about')
            ->assertSee('Our Space')
            ->assertSeeInOrder(['First caption', 'Second caption'])
            ->assertSee('alt="First photo"', false)
            ->assertDontSee('Hidden photo')
            ->assertSee('id="lightbox"', false)
            ->assertSee('data-lightbox', false);
    }

    public function test_the_heading_and_text_are_editable_in_both_languages(): void
    {
        $this->photo();

        $this->saveSettings([
            'about_space_title_en' => 'Inside Meher',
            'about_space_title_bn' => 'মেহেরের ভেতরে',
            'about_space_text_en' => 'Warm lights and wooden tables.',
            'about_space_text_bn' => 'নরম আলো আর কাঠের টেবিল।',
        ])->assertRedirect();

        $this->get('/about')->assertSee('Inside Meher')->assertSee('Warm lights and wooden tables.')->assertDontSee('Our Space');

        $this->get('/lang/bn');
        $this->get('/about')->assertSee('মেহেরের ভেতরে')->assertSee('নরম আলো আর কাঠের টেবিল।');
    }

    public function test_the_other_about_headings_are_editable_too(): void
    {
        TeamMember::create(['name' => 'Chef Karim', 'role_en' => 'Head Chef', 'is_active' => true]);

        $this->get('/about')->assertSee('Meet the Team')->assertSee('Our Story')->assertSee('About Us');

        $this->saveSettings([
            'about_title_en' => 'Who we are',
            'about_subtitle_en' => 'Food, family, fire.',
            'about_story_title_en' => 'How it began',
            'about_mission_title_en' => 'What we do',
            'about_vision_title_en' => 'Where we are going',
            'about_values_title_en' => 'What we stand for',
            'about_team_title_en' => 'The crew',
        ])->assertRedirect();

        $this->get('/about')
            ->assertSee('Who we are')->assertSee('Food, family, fire.')->assertSee('How it began')
            ->assertSee('What we do')->assertSee('Where we are going')->assertSee('What we stand for')
            ->assertSee('The crew')->assertSee('Chef Karim')
            ->assertDontSee('Meet the Team')->assertDontSee('Our Story');
    }

    public function test_a_manager_manages_the_photos(): void
    {
        Storage::fake('public');
        $manager = $this->manager();

        $this->actingAs($manager)->get('/admin/content/space')->assertOk()->assertSee('Our space photos');

        $this->actingAs($manager)->post(route('admin.content.store', 'space'), ['alt_en' => 'No photo', 'is_active' => 1])
            ->assertSessionHasErrors('image');

        $this->actingAs($manager)->post(route('admin.content.store', 'space'), [
            'alt_en' => 'The terrace',
            'caption_en' => 'Evenings on the terrace',
            'is_active' => 1,
            'image' => UploadedFile::fake()->image('terrace.jpg', 1200, 800),
        ])->assertRedirect(route('admin.content.index', 'space'));

        $photo = SpacePhoto::firstOrFail();
        $this->assertStringStartsWith('space/', $photo->image_path);
        Storage::disk('public')->assertExists($photo->image_path);
        $this->get('/about')->assertSee('Evenings on the terrace');

        $this->actingAs($manager)->put(route('admin.content.update', ['space', $photo->id]), [
            'alt_en' => 'The terrace at night',
            'caption_en' => 'Night view',
            'is_active' => 1,
        ])->assertRedirect();
        $this->get('/about')->assertSee('Night view')->assertDontSee('Evenings on the terrace');

        $this->actingAs($manager)->delete(route('admin.content.destroy', ['space', $photo->id]))->assertRedirect();
        $this->assertModelMissing($photo);
        Storage::disk('public')->assertMissing($photo->image_path);
        $this->get('/about')->assertDontSee('Our Space');
    }

    public function test_unsafe_uploads_and_staff_are_turned_away(): void
    {
        Storage::fake('public');

        $this->actingAs($this->manager())->post(route('admin.content.store', 'space'), [
            'alt_en' => 'Bad',
            'is_active' => 1,
            'image' => UploadedFile::fake()->create('shell.php', 5, 'application/x-php'),
        ])->assertSessionHasErrors('image');

        $this->actingAs(User::factory()->staff()->create())->get('/admin/content/space')->assertForbidden();
        $this->assertSame(0, SpacePhoto::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_photos_are_not_mixed_with_the_gallery_or_home_page(): void
    {
        $this->photo(['alt_en' => 'Only for about']);

        $this->get('/gallery')->assertDontSee('Only for about');
        $this->get('/')->assertDontSee('Only for about');
    }

    public function test_text_from_the_admin_is_escaped(): void
    {
        $this->photo(['alt_en' => '<script>alert(1)</script>', 'caption_en' => '<b>Bold</b>']);
        $this->saveSettings(['about_space_title_en' => '<img src=x onerror=alert(1)>']);

        $html = $this->get('/about')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
    }
}
