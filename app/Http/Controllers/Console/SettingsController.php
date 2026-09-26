<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Exam;
use App\Models\Setting;
use App\Models\Subject;
use App\Services\PackBuilder;
use Illuminate\Http\Request;

/** Site-wide settings an admin can change without a developer. */
class SettingsController extends Controller
{
    /** Setting key => validation rule. Only these keys can be saved from the form. */
    private const FIELDS = [
        'app.play_store_url' => ['nullable', 'url:https', 'max:500'],
        'app.app_store_url' => ['nullable', 'url:https', 'max:500'],
        'app.apk_url' => ['nullable', 'url:http,https', 'max:500'],
        'support.email' => ['nullable', 'email', 'max:160'],
        'support.whatsapp' => ['nullable', 'string', 'max:40'],
        'bank.name' => ['nullable', 'string', 'max:80'],
        'bank.account_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 ]*$/'],
        'bank.account_name' => ['nullable', 'string', 'max:120'],
        'bank.note' => ['nullable', 'string', 'max:300'],
        'pricing.default_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        'pricing.free_questions' => ['nullable', 'integer', 'min:0', 'max:1000'],
        'pricing.access_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
    ];

    public function edit()
    {
        return view('console.settings', [
            'values' => collect(array_keys(self::FIELDS))->mapWithKeys(fn ($key) => [$key => Setting::get($key, '')])->all(),
            'paystack' => \App\Services\Payments\Paystack::configured(),
        ]);
    }

    public function update(Request $request, PackBuilder $packs)
    {
        // Form names use underscores (dots are awkward in HTML); map them back to the setting keys.
        $rules = [];
        foreach (self::FIELDS as $key => $rule) { $rules[str_replace('.', '_', $key)] = $rule; }

        $data = $request->validate($rules, [
            '*.url' => 'That link must start with https://',
            'bank_account_number.regex' => 'The account number can only contain digits.',
        ]);

        $pricingBefore = [Setting::get('pricing.default_price'), Setting::get('pricing.free_questions')];

        Setting::put(collect(self::FIELDS)->mapWithKeys(fn ($rule, $key) => [$key => $data[str_replace('.', '_', $key)] ?? null])->all());

        // The default price and free-question count decide what goes into the free offline packs.
        if ($pricingBefore !== [Setting::get('pricing.default_price'), Setting::get('pricing.free_questions')]) {
            foreach (ContentPack::current()->tier(ContentPack::FULL)->get(['exam_id', 'subject_id']) as $pair) {
                $exam = Exam::find($pair->exam_id);
                $subject = Subject::find($pair->subject_id);
                if ($exam && $subject) { $packs->buildFree($exam, $subject); }
            }
        }

        return back()->with('success', 'Settings saved.');
    }
}
