<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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
    ];

    public function edit()
    {
        return view('console.settings', [
            'values' => collect(array_keys(self::FIELDS))->mapWithKeys(fn ($key) => [$key => Setting::get($key, '')])->all(),
        ]);
    }

    public function update(Request $request)
    {
        // Form names use underscores (dots are awkward in HTML); map them back to the setting keys.
        $rules = [];
        foreach (self::FIELDS as $key => $rule) { $rules[str_replace('.', '_', $key)] = $rule; }

        $data = $request->validate($rules, [
            '*.url' => 'That link must start with https://',
        ]);

        Setting::put(collect(self::FIELDS)->mapWithKeys(fn ($rule, $key) => [$key => $data[str_replace('.', '_', $key)] ?? null])->all());

        return back()->with('success', 'Settings saved.');
    }
}
