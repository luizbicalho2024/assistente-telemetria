<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\TrackerCommandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class TrackerController extends Controller
{
    public function commands(): View
    {
        return view('trackers.commands', ['commands' => null]);
    }

    public function generate(Request $request, TrackerCommandService $service, ActivityLogger $logger): View
    {
        $data = $request->validate([
            'model' => ['required','in:ST300,ST390,ST4315U'],
            'serial' => ['nullable','string','max:80'],
            'esn' => ['nullable','string','max:20'],
            'phone' => ['nullable','string','max:40'],
            'apn' => ['nullable','string','max:180'],
            'apn_user' => ['nullable','string','max:120'],
            'apn_password' => ['nullable','string','max:120'],
            'host' => ['nullable','string','max:180'],
            'port' => ['nullable','string','max:10'],
            'auth' => ['nullable','string','max:30'],
            'speed' => ['nullable','integer','min:0','max:300'],
            'high_voltage' => ['nullable','integer','min:0','max:1000'],
            'low_voltage' => ['nullable','integer','min:0','max:1000'],
        ]);

        try {
            $commands = $service->generate($data['model'], $data);
        } catch (\Throwable $e) {
            return view('trackers.commands', ['commands' => null])->withErrors(['model' => $e->getMessage()]);
        }

        $logger->log('Comandos de rastreador gerados', [
            'model' => $data['model'],
            'equipment' => $data['serial'] ?? $data['esn'] ?? null,
        ]);

        return view('trackers.commands', compact('commands', 'data'));
    }

    public function sendSms(Request $request, ActivityLogger $logger)
    {
        $data = $request->validate([
            'phone' => ['required','string','max:40'],
            'command' => ['required','string','max:2000'],
            'title' => ['nullable','string','max:180'],
        ]);

        $sid = (string) env('TWILIO_ACCOUNT_SID', '');
        $token = (string) env('TWILIO_AUTH_TOKEN', '');
        $from = (string) env('TWILIO_PHONE_NUMBER', '');

        if ($sid === '' || $token === '' || $from === '') {
            return back()->withErrors(['sms' => 'Twilio não configurada. O comando foi gerado, mas o SMS não foi enviado.']);
        }

        $to = preg_replace('/\D+/', '', $data['phone']);
        if (!str_starts_with($to, '55')) {
            $to = '55'.$to;
        }
        $to = '+'.$to;

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $data['command'],
            ]);

        if (!$response->successful()) {
            return back()->withErrors(['sms' => 'Falha no envio SMS: '.$response->status().' '.$response->body()]);
        }

        $logger->log('Comando enviado por SMS', [
            'title' => $data['title'] ?? 'Comando',
            'phone_final' => substr($to, -4),
        ]);

        return back()->with('success', 'Comando enviado por SMS.');
    }
}
