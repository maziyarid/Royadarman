<?php

namespace Database\Seeders;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Models\Cms\Post;
use App\Models\Cms\PostTranslation;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CmsStarterContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('role', 'owner')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($author === null) {
            $this->command?->warn('CmsStarterContentSeeder skipped: no user exists to own organisational articles.');

            return;
        }

        foreach ($this->articles() as $article) {
            $existing = PostTranslation::query()
                ->where('locale', 'fa')
                ->where('slug', $article['translations']['fa']['slug'])
                ->first();

            $post = $existing?->post ?? Post::query()->create([
                'author_user_id' => $author->id,
                'type' => PostType::Post->value,
                'status' => PostStatus::Published->value,
                'published_at' => now()->subDays($article['age_days']),
                'is_featured' => false,
            ]);

            foreach ($article['translations'] as $locale => $translation) {
                $post->translations()->updateOrCreate(
                    ['locale' => $locale, 'slug' => $translation['slug']],
                    [
                        'title' => $translation['title'],
                        'excerpt' => $translation['excerpt'],
                        'body' => $translation['body'],
                        'sanitized_body' => $translation['body'],
                    ],
                );
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function articles(): array
    {
        return [
            'what-royadarman-is' => [
                'age_days' => 12,
                'translations' => [
                    'fa' => [
                        'slug' => 'royadarman-chist',
                        'title' => 'رویا درمان چیست — و چه چیزی نیست',
                        'excerpt' => 'یک میز راهنمایی شبانه‌روزی برای مسیر دندانپزشکی؛ نه مطب واحد، نه تشخیص از راه دور.',
                        'body' => '<p>رویا درمان کلینیک نیست. دندان‌پزشک هم نیست. یک میز هماهنگی است که درخواست را می‌گیرد، صاحب مشخص برایش می‌گذارد و وقتی قضاوت بالینی لازم شود کار را به دندان‌پزشک دارای مجوز می‌سپارد.</p><p>سه مسیر اصلی وجود دارد: راهنمایی و معرفی مرکز، دندانپزشکی در منزل داخل شهر تهران، و بررسی اولیه تصویر OPG. انتخاب مسیر تشخیص نمی‌سازد؛ فقط درخواست را به میز درست می‌فرستد.</p><p>مالک مجموعه به‌صرف مالک بودن به پرونده بالینی دسترسی ندارد. مدارک بالینی روی سایت عمومی جمع نمی‌شوند.</p>',
                    ],
                    'en' => [
                        'slug' => 'what-royadarman-is',
                        'title' => 'What Royadarman is — and what it is not',
                        'excerpt' => 'A 24/7 dental guidance desk. Not a clinic, not a remote diagnosis.',
                        'body' => '<p>Royadarman is not a clinic and not a dentist. It is a coordination desk that takes a request, keeps a named person on it, and brings in a licensed dentist when clinical judgement is required.</p><p>There are three paths: guidance and referral, home dentistry inside Tehran city, and a preliminary OPG reading. Choosing a path does not create a diagnosis; it sends the request to the right desk.</p><p>The owner does not receive clinical access merely by being owner. Clinical files are not collected on public pages.</p>',
                    ],
                    'ar' => [
                        'slug' => 'ma-hiya-royadarman',
                        'title' => 'ما هي رويا درمان — وما ليست عليه',
                        'excerpt' => 'مكتب إرشاد للأسنان على مدار الساعة. ليست عيادة وليست تشخيصاً عن بعد.',
                        'body' => '<p>رويا درمان ليست عيادة وليست طبيب أسنان. هي مكتب تنسيق يستلم الطلب ويبقي شخصاً مسؤولاً عنه ويُشرك طبيباً مرخصاً عندما يلزم حكم سريري.</p><p>هناك ثلاثة مسارات: الإرشاد والإحالة، وطب الأسنان المنزلي داخل مدينة طهران، ومراجعة أولية لصورة OPG. اختيار المسار لا يصنع تشخيصاً؛ بل يرسل الطلب إلى المكتب الصحيح.</p><p>المالك لا يحصل على وصول سريري لمجرد أنه المالك. لا تُجمع الملفات السريرية في الصفحات العامة.</p>',
                    ],
                ],
            ],
            'opg-is-not-diagnosis' => [
                'age_days' => 8,
                'translations' => [
                    'fa' => [
                        'slug' => 'barrasi-opg-tashkhis-nist',
                        'title' => 'بررسی OPG تشخیص نیست',
                        'excerpt' => 'تصویر پانورامیک می‌تواند گام بعدی را روشن کند؛ جای معاینه و طرح درمان را نمی‌گیرد.',
                        'body' => '<p>اگر تصویر OPG دارید، مسیر خصوصی بارگذاری برای بررسی اولیه توسط دندان‌پزشک واگذارشده است. فایل در وب عمومی قرار نمی‌گیرد و پیش از دسترسی بالینی از مسیر امنیتی می‌گذرد.</p><p>نتیجه یک یادداشت گام بعدی است، نه تشخیص نهایی و نه طرح درمان. اگر تصویر کافی نباشد یا معاینه لازم باشد، همان را صریح می‌گوییم.</p><p>تصویر را در پیام‌رسان عادی نفرستید. مسیر احراز هویت‌شده برای همین است.</p>',
                    ],
                    'en' => [
                        'slug' => 'opg-is-not-a-diagnosis',
                        'title' => 'An OPG reading is not a diagnosis',
                        'excerpt' => 'A panoramic image can clarify the next step. It does not replace an examination.',
                        'body' => '<p>If you already have an OPG, there is a private upload path for a licensed dentist assigned to that case. The file is not placed on the public web and must pass a security check before clinical access.</p><p>The result is a next-step note, not a final diagnosis and not a treatment plan. If the image is inadequate or an examination is needed, that is stated plainly.</p><p>Do not send the image through an ordinary messenger. The signed-in path exists for that reason.</p>',
                    ],
                    'ar' => [
                        'slug' => 'murajaa-opg-laysat-tashkhis',
                        'title' => 'مراجعة OPG ليست تشخيصاً',
                        'excerpt' => 'الصورة البانورامية قد توضح الخطوة التالية. لا تغني عن الفحص.',
                        'body' => '<p>إذا كانت لديك صورة OPG فهناك مسار رفع خاص لطبيب مرخص مُسند إلى الحالة. لا يُوضع الملف على الويب العام ويمر بفحص أمني قبل الوصول السريري.</p><p>النتيجة ملاحظة للخطوة التالية، وليست تشخيصاً نهائياً ولا خطة علاج. إذا كانت الصورة غير كافية أو لزم الفحص، يُقال ذلك بوضوح.</p><p>لا ترسل الصورة عبر تطبيق مراسلة عادي. المسار الموثّق موجود لهذا السبب.</p>',
                    ],
                ],
            ],
            'home-dentistry-tehran' => [
                'age_days' => 4,
                'translations' => [
                    'fa' => [
                        'slug' => 'dandanpezeshki-dar-manzel-tehran',
                        'title' => 'دندانپزشکی در منزل فقط تهران است',
                        'excerpt' => 'پوشش فعلی شهر تهران است. هر خدمتی مناسب منزل نیست و درمان با ارائه‌دهنده واجد صلاحیت می‌ماند.',
                        'body' => '<p>هماهنگی خدمت در منزل به شهر تهران محدود است. منطقه، نوع نیاز و تناسب خدمت پیش از هماهنگی بررسی می‌شود.</p><p>اگر تجهیزات کلینیک یا ارزیابی فوری لازم باشد، مسیر مرکز پیشنهاد می‌شود. این به معنی رد درخواست نیست؛ به معنی انتخاب مسیر امن‌تر است.</p><p>رویا درمان هماهنگ می‌کند. درمان را دندان‌پزشک انجام می‌دهد.</p>',
                    ],
                    'en' => [
                        'slug' => 'home-dentistry-is-tehran-only',
                        'title' => 'Home dentistry is Tehran-only for now',
                        'excerpt' => 'Current coverage is Tehran city. Not every procedure belongs at home.',
                        'body' => '<p>Home-service coordination is limited to Tehran city. Area, need and suitability are checked before a visit is arranged.</p><p>If clinic equipment or an urgent assessment is needed, a clinic route is proposed. That is not a rejection of the person; it is a safer path.</p><p>Royadarman coordinates. A qualified provider treats.</p>',
                    ],
                    'ar' => [
                        'slug' => 'tibb-al-asnan-almanzili-tehran',
                        'title' => 'طب الأسنان المنزلي حالياً في طهران فقط',
                        'excerpt' => 'التغطية الحالية مدينة طهران. ليست كل الإجراءات مناسبة للمنزل.',
                        'body' => '<p>تنسيق الخدمة المنزلية محدود بمدينة طهران. تُراجع المنطقة والحاجة والملاءمة قبل ترتيب زيارة.</p><p>إذا لزم تجهيز العيادة أو تقييم عاجل، يُقترح مسار العيادة. هذا ليس رفضاً للشخص؛ بل مساراً أكثر أماناً.</p><p>رويا درمان تنسّق. يقدّم العلاج مقدّم مؤهل.</p>',
                    ],
                ],
            ],
        ];
    }
}
