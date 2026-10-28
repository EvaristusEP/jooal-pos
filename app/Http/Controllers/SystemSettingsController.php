<?php

namespace App\Http\Controllers;

use App\Services\MailDeliveryStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function show(MailDeliveryStatus $mailStatus): View
    {
        return view('settings.system', [
            'settings' => [
                ['label' => 'Nama aplikasi', 'value' => config('app.name')],
                ['label' => 'Zona waktu', 'value' => config('app.timezone')],
            ],
            'technicalSettings' => [
                ['label' => 'Session', 'value' => config('session.driver')],
                ['label' => 'Cache', 'value' => config('cache.default')],
                ['label' => 'Queue', 'value' => config('queue.default')],
            ],
            'mailStatus' => $mailStatus->current(),
        ]);
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', Rule::in(['id', 'en'])]]);
        $request->session()->put('locale', $data['locale']);
        app()->setLocale($data['locale']);
        return redirect()->back()->withCookie(cookie('jooal_locale', $data['locale'], 60 * 24 * 365));
    }
}
