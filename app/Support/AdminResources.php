<?php

namespace App\Support;

use App\Models\Faq;
use App\Models\GalleryImage;
use App\Models\Promotion;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\Coupon;
use App\Models\Supplier;
use Closure;

final class AdminResources
{
    private static function text(string $name, string $label, bool $required = false, int $max = 200): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'type' => 'text',
            'required' => $required,
            'rules' => [$required ? 'required' : 'nullable', 'string', 'max:' . $max],
        ];
    }

    private static function area(string $name, string $label, bool $required = false, int $max = 1000): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'type' => 'textarea',
            'required' => $required,
            'rules' => [$required ? 'required' : 'nullable', 'string', 'max:' . $max],
        ];
    }

    private static function order(): array
    {
        return [
            'name' => 'sort_order',
            'label' => 'Sort order',
            'type' => 'number',
            'required' => false,
            'min' => 0,
            'max' => 65535,
            'default' => 0,
            'rules' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private static function active(): array
    {
        return [
            'name' => 'is_active',
            'label' => 'Visible on the website',
            'type' => 'checkbox',
            'required' => false,
            'default' => true,
            'rules' => ['required', 'boolean'],
        ];
    }

    private static function percentCap(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (request('type') === 'percent' && (float) $value > 100) {
                $fail('A percent discount cannot be more than 100.');
            }
        };
    }

    public static function all(): array
    {
        return [
            'gallery' => [
                'model' => GalleryImage::class,
                'title' => 'Gallery',
                'singular' => 'gallery image',
                'image' => ['field' => 'image_path', 'accessor' => 'image_url', 'dir' => 'gallery', 'required' => true, 'label' => 'Photo (JPG, PNG or WebP, max 2 MB)'],
                'columns' => ['alt_en' => 'Alt text', 'sort_order' => 'Order'],
                'fields' => [
                    self::text('alt_en', 'Alt text (English): describe the photo', true),
                    self::text('alt_bn', 'Alt text (বাংলা)'),
                    self::text('title_en', 'Title (English)'),
                    self::text('title_bn', 'Title (বাংলা)'),
                    self::order(),
                    self::active(),
                ],
            ],
            'testimonials' => [
                'model' => Testimonial::class,
                'title' => 'Testimonials',
                'singular' => 'testimonial',
                'image' => null,
                'columns' => ['name' => 'Name', 'rating' => 'Rating', 'quote_en' => 'Quote'],
                'fields' => [
                    self::text('name', 'Guest name', true, 100),
                    [
                        'name' => 'rating',
                        'label' => 'Rating (1 to 5)',
                        'type' => 'number',
                        'required' => true,
                        'min' => 1,
                        'max' => 5,
                        'default' => 5,
                        'rules' => ['required', 'integer', 'between:1,5'],
                    ],
                    self::text('title_en', 'Label (English), e.g. Regular guest', false, 100),
                    self::text('title_bn', 'Label (বাংলা)', false, 100),
                    self::area('quote_en', 'Quote (English)', true, 600),
                    self::area('quote_bn', 'Quote (বাংলা)', false, 600),
                    self::order(),
                    self::active(),
                ],
            ],
            'promotions' => [
                'model' => Promotion::class,
                'title' => 'Offers',
                'singular' => 'offer',
                'image' => ['field' => 'image_path', 'accessor' => 'image_url', 'dir' => 'promotions', 'required' => false, 'label' => 'Image, optional (JPG, PNG or WebP, max 2 MB)'],
                'columns' => ['title_en' => 'Title', 'starts_at' => 'Starts', 'ends_at' => 'Ends'],
                'fields' => [
                    self::text('title_en', 'Title (English)', true),
                    self::text('title_bn', 'Title (বাংলা)'),
                    self::area('description_en', 'Description (English)', false, 500),
                    self::area('description_bn', 'Description (বাংলা)', false, 500),
                    ['name' => 'starts_at', 'label' => 'Starts (optional)', 'type' => 'datetime-local', 'required' => false, 'rules' => ['nullable', 'date']],
                    ['name' => 'ends_at', 'label' => 'Ends (optional)', 'type' => 'datetime-local', 'required' => false, 'rules' => ['nullable', 'date', 'after_or_equal:starts_at']],
                    self::order(),
                    self::active(),
                ],
            ],
            'faqs' => [
                'model' => Faq::class,
                'title' => 'FAQ',
                'singular' => 'question',
                'image' => null,
                'columns' => ['question_en' => 'Question', 'sort_order' => 'Order'],
                'fields' => [
                    self::text('question_en', 'Question (English)', true, 250),
                    self::text('question_bn', 'Question (বাংলা)', false, 250),
                    self::area('answer_en', 'Answer (English)', true, 1500),
                    self::area('answer_bn', 'Answer (বাংলা)', false, 1500),
                    self::order(),
                    self::active(),
                ],
            ],
            'team' => [
                'model' => TeamMember::class,
                'title' => 'Team',
                'singular' => 'team member',
                'image' => ['field' => 'photo_path', 'accessor' => 'photo_url', 'dir' => 'team', 'required' => false, 'label' => 'Photo, optional (JPG, PNG or WebP, max 2 MB)'],
                'columns' => ['name' => 'Name', 'role_en' => 'Role'],
                'fields' => [
                    self::text('name', 'Name', true, 100),
                    self::text('role_en', 'Role (English)', true, 100),
                    self::text('role_bn', 'Role (বাংলা)', false, 100),
                    self::area('bio_en', 'Bio (English)', false, 600),
                    self::area('bio_bn', 'Bio (বাংলা)', false, 600),
                    self::order(),
                    self::active(),
                ],
            ],

            'suppliers' => [
                'model' => Supplier::class,
                'title' => 'Suppliers',
                'singular' => 'supplier',
                'image' => null,
                'order' => ['name', 'asc'],
                'columns' => ['name' => 'Name', 'phone' => 'Phone', 'email' => 'Email'],
                'fields' => [
                    self::text('name', 'Supplier name', true, 120),
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false, 'rules' => ['nullable', 'string', 'max:30']],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => false, 'rules' => ['nullable', 'email:rfc', 'max:150']],
                    self::area('notes', 'Notes', false, 500),
                    self::active(),
                ],
            ],

            'coupons' => [
                'model' => Coupon::class,
                'title' => 'Coupons',
                'singular' => 'coupon',
                'image' => null,
                'order' => ['id', 'desc'],
                'columns' => ['code' => 'Code', 'type' => 'Type', 'value' => 'Value', 'used_count' => 'Used', 'ends_at' => 'Ends'],
                'fields' => [
                    [
                        'name' => 'code',
                        'label' => 'Code (letters, numbers, dash)',
                        'type' => 'text',
                        'required' => true,
                        'unique' => 'coupons',
                        'rules' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/']
                    ],
                    [
                        'name' => 'type',
                        'label' => 'Discount type',
                        'type' => 'select',
                        'required' => true,
                        'default' => 'percent',
                        'options' => ['percent' => 'Percent (%)', 'fixed' => 'Fixed amount'],
                        'rules' => ['required', 'in:percent,fixed']
                    ],
                    [
                        'name' => 'value',
                        'label' => 'Value (percent, or amount in Taka)',
                        'type' => 'number',
                        'required' => true,
                        'min' => 0.01,
                        'step' => '0.01',
                        'rules' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,2', self::percentCap()]
                    ],
                    [
                        'name' => 'min_subtotal',
                        'label' => 'Minimum order subtotal',
                        'type' => 'number',
                        'required' => true,
                        'min' => 0,
                        'step' => '0.01',
                        'default' => 0,
                        'rules' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2']
                    ],
                    [
                        'name' => 'max_discount',
                        'label' => 'Maximum discount for percent coupons (optional)',
                        'type' => 'number',
                        'required' => false,
                        'min' => 0,
                        'step' => '0.01',
                        'rules' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2']
                    ],
                    ['name' => 'starts_at', 'label' => 'Starts (optional)', 'type' => 'datetime-local', 'required' => false, 'rules' => ['nullable', 'date']],
                    ['name' => 'ends_at', 'label' => 'Ends (optional)', 'type' => 'datetime-local', 'required' => false, 'rules' => ['nullable', 'date', 'after_or_equal:starts_at']],
                    [
                        'name' => 'usage_limit',
                        'label' => 'Total uses allowed (optional)',
                        'type' => 'number',
                        'required' => false,
                        'min' => 1,
                        'rules' => ['nullable', 'integer', 'min:1', 'max:1000000']
                    ],
                    self::active(),
                ],
            ],
        ];
    }
}
