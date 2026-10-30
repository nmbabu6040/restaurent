<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Promotion;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        if (Testimonial::count() === 0) {
            $testimonials = [
                [
                    'Sample Guest A',
                    'Regular guest',
                    'নিয়মিত অতিথি',
                    'The biryani is the best I have had in months. Generous portions and friendly staff.',
                    'গত কয়েক মাসে খাওয়া সেরা বিরিয়ানি। পরিবেশন বেশ ভালো, আর স্টাফরাও আন্তরিক।'
                ],
                [
                    'Sample Guest B',
                    'Family dinner',
                    'পারিবারিক ডিনার',
                    'We booked a table for a family gathering and everything was smooth. The ilish was perfect.',
                    'পারিবারিক আয়োজনে টেবিল বুক করেছিলাম, সবকিছু সুন্দরভাবে হয়েছে। ইলিশটা দারুণ ছিল।'
                ],
                [
                    'Sample Guest C',
                    'Online order',
                    'অনলাইন অর্ডার',
                    'Food arrived hot and on time. The packaging was neat too.',
                    'খাবার গরম আর সময়মতো পৌঁছেছে। প্যাকেজিংও পরিপাটি ছিল।'
                ],
            ];

            foreach ($testimonials as $i => [$name, $titleEn, $titleBn, $quoteEn, $quoteBn]) {
                Testimonial::create([
                    'name' => $name,
                    'title_en' => $titleEn,
                    'title_bn' => $titleBn,
                    'quote_en' => $quoteEn,
                    'quote_bn' => $quoteBn,
                    'rating' => 5,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ]);
            }
        }

        if (Promotion::count() === 0) {
            Promotion::create([
                'title_en' => 'Free Borhani with every Biryani',
                'title_bn' => 'প্রতিটি বিরিয়ানির সঙ্গে বোরহানি ফ্রি',
                'description_en' => 'Order any biryani and get a glass of borhani on the house.',
                'description_bn' => 'যেকোনো বিরিয়ানি অর্ডার করুন, সঙ্গে বোরহানি আমাদের পক্ষ থেকে।',
                'sort_order' => 1,
                'is_active' => true,
            ]);
            Promotion::create([
                'title_en' => 'Weekend Family Platter',
                'title_bn' => 'সাপ্তাহিক ফ্যামিলি প্ল্যাটার',
                'description_en' => 'A shareable platter for four, available every Friday and Saturday.',
                'description_bn' => 'চারজনের জন্য শেয়ার করার মতো প্ল্যাটার, প্রতি শুক্র ও শনিবার।',
                'sort_order' => 2,
                'is_active' => true,
            ]);
        }

        if (Faq::count() === 0) {
            $faqs = [
                [
                    'Do you take table reservations?',
                    'আপনারা কি টেবিল রিজার্ভেশন নেন?',
                    'Yes. You can reserve online and we will confirm by email or phone.',
                    'জি। অনলাইনে রিজার্ভ করতে পারবেন, আমরা ইমেইল বা ফোনে নিশ্চিত করব।'
                ],
                [
                    'Do you offer home delivery?',
                    'আপনারা কি হোম ডেলিভারি দেন?',
                    'Yes, within our delivery area. Pickup is also available.',
                    'জি, আমাদের ডেলিভারি এলাকার মধ্যে। পিকআপের সুবিধাও আছে।'
                ],
                [
                    'Which payment methods do you accept?',
                    'কোন কোন পেমেন্ট পদ্ধতি চলে?',
                    'Cash on delivery and online payment at checkout.',
                    'ক্যাশ অন ডেলিভারি এবং চেকআউটে অনলাইন পেমেন্ট।'
                ],
                [
                    'Is your food halal?',
                    'আপনাদের খাবার কি হালাল?',
                    'Dietary labels are shown on each dish. Please ask us if you need details.',
                    'প্রতিটি খাবারে ডায়েটারি লেবেল দেওয়া আছে। বিস্তারিত জানতে আমাদের জিজ্ঞেস করুন।'
                ],
                [
                    'Can I order for a large group or event?',
                    'বড় দল বা অনুষ্ঠানের জন্য অর্ডার করা যাবে?',
                    'Yes. Please contact us in advance so we can plan your order.',
                    'জি। আগে থেকে আমাদের জানালে আমরা ব্যবস্থা করতে পারব।'
                ],
            ];

            foreach ($faqs as $i => [$qEn, $qBn, $aEn, $aBn]) {
                Faq::create([
                    'question_en' => $qEn,
                    'question_bn' => $qBn,
                    'answer_en' => $aEn,
                    'answer_bn' => $aBn,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ]);
            }
        }
    }
}
