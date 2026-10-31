<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title_en', 150);
            $table->string('title_bn', 150)->nullable();
            $table->longText('body_en')->nullable();
            $table->longText('body_bn')->nullable();
            $table->string('meta_title_en', 160)->nullable();
            $table->string('meta_title_bn', 160)->nullable();
            $table->string('meta_description_en', 300)->nullable();
            $table->string('meta_description_bn', 300)->nullable();
            $table->boolean('show_in_header')->default(false);
            $table->boolean('show_in_footer')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->boolean('is_custom')->default(false);
            $table->string('title_en', 150)->nullable();
            $table->string('title_bn', 150)->nullable();
            $table->string('subtitle_en', 300)->nullable();
            $table->string('subtitle_bn', 300)->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_bn')->nullable();
            $table->string('button_text_en', 60)->nullable();
            $table->string('button_text_bn', 60)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->string('bg_color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20); // usp = home highlights, value = about page values
            $table->string('title_en', 120);
            $table->string('title_bn', 120)->nullable();
            $table->string('text_en', 400);
            $table->string('text_bn', 400)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group', 'is_active', 'sort_order']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('seo_title_en', 160)->nullable();
            $table->string('seo_title_bn', 160)->nullable();
            $table->string('seo_description_en', 300)->nullable();
            $table->string('seo_description_bn', 300)->nullable();
        });

        $now = now();

        foreach (['hero', 'categories', 'featured', 'intro', 'chef', 'offers', 'gallery', 'testimonials', 'visit', 'reserve'] as $i => $key) {
            DB::table('home_sections')->insert(['key' => $key, 'is_custom' => false, 'sort_order' => ($i + 1) * 10, 'is_visible' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $features = [
            ['usp', 'Fresh every day', 'প্রতিদিন তাজা', 'Ingredients are sourced and prepared daily.', 'উপকরণ প্রতিদিন সংগ্রহ ও প্রস্তুত করা হয়।'],
            ['usp', 'Honest recipes', 'খাঁটি রেসিপি', 'Traditional methods, no shortcuts.', 'ঐতিহ্যবাহী পদ্ধতি, কোনো শর্টকাট নয়।'],
            ['usp', 'Warm hospitality', 'আন্তরিক আতিথেয়তা', 'Dining in or ordering home, you are our guest.', 'রেস্তোরাঁয় খান বা বাসায় অর্ডার করুন, আপনি আমাদের অতিথি।'],
            ['value', 'Freshness', 'তাজা উপকরণ', 'We cook with ingredients prepared the same day.', 'আমরা সেদিনই প্রস্তুত করা উপকরণ দিয়ে রান্না করি।'],
            ['value', 'Authenticity', 'খাঁটি স্বাদ', 'We respect traditional recipes and the people who passed them on.', 'ঐতিহ্যবাহী রেসিপি ও যাঁরা তা এগিয়ে দিয়েছেন, তাঁদের প্রতি আমাদের শ্রদ্ধা।'],
            ['value', 'Hospitality', 'আতিথেয়তা', 'Every guest is treated like family.', 'প্রতিটি অতিথি আমাদের পরিবারের মতো।'],
        ];

        foreach ($features as $i => [$group, $titleEn, $titleBn, $textEn, $textBn]) {
            DB::table('features')->insert([
                'group' => $group,
                'title_en' => $titleEn,
                'title_bn' => $titleBn,
                'text_en' => $textEn,
                'text_bn' => $textBn,
                'sort_order' => ($i % 3 + 1) * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('menu_items', fn(Blueprint $t) => $t->dropColumn(['seo_title_en', 'seo_title_bn', 'seo_description_en', 'seo_description_bn']));
        Schema::dropIfExists('features');
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('pages');
    }
};
