<?php

namespace Database\Seeders;

use App\Models\AppPolicy;
use Illuminate\Database\Seeder;

/**
 * Seeds the four legal-page sections managed from the admin dashboard:
 *   1. شروط الاستخدام  (terms)
 *   2. سياسة الخصوصية (privacy)
 *   3. شروط التاجر     (merchant_terms)
 *   4. قواعد المنصة   (platform_rules)
 *
 * Safe to run multiple times — uses updateOrCreate so existing rows are patched.
 */
class LegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            // ─────────────────────────────────────────────
            // 1. شروط الاستخدام — Terms of Use
            // ─────────────────────────────────────────────
            [
                'type'           => AppPolicy::TYPE_TERMS,
                'title_ar'       => 'شروط الاستخدام',
                'title_en'       => 'Terms of Use',
                'description_ar' => <<<'AR'
مرحباً بك في تطبيق OFROO. باستخدامك لهذا التطبيق، فإنك توافق على الالتزام بالشروط والأحكام الآتية:

1. **الاستخدام المقبول**: يُسمح لك باستخدام التطبيق للأغراض الشخصية غير التجارية فحسب.
2. **الحساب**: أنت مسؤول عن سرية بيانات تسجيل الدخول وعن جميع الأنشطة التي تجري تحت حسابك.
3. **المحتوى**: يحظر نشر أي محتوى مسيء أو مضلل أو يُخلّ بحقوق الآخرين.
4. **تعديل الشروط**: تحتفظ OFROO بالحق في تعديل هذه الشروط في أي وقت، مع إشعار مسبق للمستخدمين.
5. **إنهاء الخدمة**: يحق لـ OFROO إيقاف الحساب في حال مخالفة الشروط.
AR
                ,
                'description_en' => <<<'EN'
Welcome to OFROO. By using this application, you agree to comply with the following terms and conditions:

1. **Acceptable Use**: You may use the app for personal, non-commercial purposes only.
2. **Account**: You are responsible for maintaining the confidentiality of your login credentials and all activities under your account.
3. **Content**: Posting offensive, misleading, or rights-infringing content is strictly prohibited.
4. **Modification**: OFROO reserves the right to modify these terms at any time with prior notice to users.
5. **Termination**: OFROO may suspend accounts that violate these terms.
EN
                ,
                'order_index' => 0,
                'is_active'   => true,
            ],

            // ─────────────────────────────────────────────
            // 2. سياسة الخصوصية — Privacy Policy
            // ─────────────────────────────────────────────
            [
                'type'           => AppPolicy::TYPE_PRIVACY,
                'title_ar'       => 'سياسة الخصوصية',
                'title_en'       => 'Privacy Policy',
                'description_ar' => <<<'AR'
تلتزم OFROO بحماية خصوصيتك. توضّح هذه السياسة كيفية جمع بياناتك واستخدامها:

1. **البيانات التي نجمعها**: الاسم، البريد الإلكتروني، رقم الهاتف، بيانات الجهاز، وسجل الاستخدام.
2. **الغرض من الجمع**: تحسين الخدمة، معالجة الطلبات، وإرسال العروض ذات الصلة.
3. **مشاركة البيانات**: لا نبيع بياناتك لأطراف ثالثة. قد نشاركها مع التجار المعتمدين لأغراض تتعلق بالطلبات فحسب.
4. **الأمان**: نستخدم تشفير SSL وأفضل الممارسات الأمنية لحماية بياناتك.
5. **حقوقك**: يحق لك الاطلاع على بياناتك، تعديلها، أو طلب حذفها في أي وقت.
AR
                ,
                'description_en' => <<<'EN'
OFROO is committed to protecting your privacy. This policy explains how we collect and use your data:

1. **Data We Collect**: Name, email, phone number, device data, and usage history.
2. **Purpose**: To improve the service, process orders, and send relevant offers.
3. **Data Sharing**: We do not sell your data. It may be shared with approved merchants solely for order-related purposes.
4. **Security**: We use SSL encryption and industry best practices to protect your data.
5. **Your Rights**: You may access, modify, or request deletion of your data at any time.
EN
                ,
                'order_index' => 0,
                'is_active'   => true,
            ],

            // ─────────────────────────────────────────────
            // 3. شروط التاجر — Merchant Terms
            // ─────────────────────────────────────────────
            [
                'type'           => AppPolicy::TYPE_MERCHANT_TERMS,
                'title_ar'       => 'شروط التاجر',
                'title_en'       => 'Merchant Terms',
                'description_ar' => <<<'AR'
للانضمام إلى منصة OFROO كتاجر، يجب الموافقة على الشروط الآتية:

1. **التسجيل**: يُلزَم التاجر بتقديم بيانات صحيحة وكاملة. يخضع الطلب لمراجعة الإدارة قبل التفعيل.
2. **العروض والكوبونات**: يتحمل التاجر مسؤولية صحة العروض المنشورة والتزامه بتنفيذها.
3. **العمولات**: يوافق التاجر على نسبة العمولة المحددة من قِبَل المنصة على كل عملية بيع.
4. **الجودة**: يجب على التاجر الحفاظ على جودة الخدمة وسمعة المنصة.
5. **الإلغاء**: تحتفظ OFROO بالحق في إيقاف حساب التاجر عند مخالفة الشروط.
AR
                ,
                'description_en' => <<<'EN'
To join the OFROO platform as a merchant, you must agree to the following terms:

1. **Registration**: Merchants must provide accurate and complete information. Applications are reviewed by the admin before activation.
2. **Offers & Coupons**: The merchant is responsible for the accuracy of published offers and their fulfilment.
3. **Commissions**: The merchant agrees to the commission rate set by the platform on each transaction.
4. **Quality**: Merchants must maintain service quality and uphold the platform's reputation.
5. **Termination**: OFROO reserves the right to suspend merchant accounts for violations.
EN
                ,
                'order_index' => 0,
                'is_active'   => true,
            ],

            // ─────────────────────────────────────────────
            // 4. قواعد المنصة — Platform Rules
            // ─────────────────────────────────────────────
            [
                'type'           => AppPolicy::TYPE_PLATFORM_RULES,
                'title_ar'       => 'قواعد المنصة',
                'title_en'       => 'Platform Rules',
                'description_ar' => <<<'AR'
تهدف قواعد المنصة إلى ضمان تجربة آمنة وعادلة للجميع:

1. **الاحترام المتبادل**: يُحظر أي شكل من أشكال الإساءة أو التحرش بين المستخدمين والتجار.
2. **الاحتيال**: تُعدّ أي محاولة للتحايل أو الاحتيال مخالفة صريحة تستوجب الإيقاف الفوري.
3. **التقييمات**: يجب أن تكون التقييمات حقيقية ومبنية على تجربة فعلية.
4. **الإبلاغ**: يحق لأي مستخدم الإبلاغ عن محتوى مشبوه عبر قسم الدعم الفني.
5. **الامتثال القانوني**: يجب أن تمتثل جميع العمليات للقوانين واللوائح المعمول بها.
AR
                ,
                'description_en' => <<<'EN'
Platform rules are designed to ensure a safe and fair experience for everyone:

1. **Mutual Respect**: Any form of abuse or harassment between users and merchants is strictly prohibited.
2. **Fraud**: Any attempt to deceive or defraud is an explicit violation resulting in immediate suspension.
3. **Reviews**: Reviews must be genuine and based on real experiences.
4. **Reporting**: Any user may report suspicious content through the support section.
5. **Legal Compliance**: All activities must comply with applicable laws and regulations.
EN
                ,
                'order_index' => 0,
                'is_active'   => true,
            ],
        ];

        foreach ($sections as $data) {
            AppPolicy::updateOrCreate(
                ['type' => $data['type'], 'title_ar' => $data['title_ar']],
                $data
            );
        }

        $this->command->info('✅ LegalPagesSeeder: seeded ' . count($sections) . ' legal page sections.');
    }
}
