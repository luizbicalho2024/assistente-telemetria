<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\TrackerCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
            'serial' => ['nullable','string','max:40','regex:/^[A-Za-z0-9._-]*$/'],
            'esn' => ['nullable','digits:10'],
            'phone' => ['nullable','string','max:24'],
            'apn' => ['nullable','string','max:100'],
            'apn_user' => ['nullable','string','max:80'],
            'apn_password' => ['nullable','string','max:80'],
            'host' => ['nullable','string','max:180'],
            'port' => ['nullable','integer','min:1','max:65535'],
            'auth' => ['nullable','in:CHAP,PAP,AUTOMÁTICO,AUTOMATICO,SEM'],
            'speed' => ['nullable','integer','min:0','max:300'],
            'high_voltage' => ['nullable','integer','min:0','max:1000'],
            'low_voltage' => ['nullable','integer','min:0','max:1000'],
        ]);

        try {
            $commands = $service->generate($data['model'], $data);
            $phone = !empty($data['phone']) ? $this->normalizeBrazilPhone($data['phone']) : null;
        } catch (\Throwable $e) {
            return view('trackers.commands', ['commands' => null])
                ->withErrors(['model' => $e->getMessage()]);
        }

        $tokens = [];
        foreach ($commands as $title => $command) {
            $payload = [
                'uid' => (string) $request->user()->getAuthIdentifier(),
                'title' => (string) $title,
                'command' => (string) $command,
                'phone' => $phone,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ];
            $tokens[$title] = Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        $logger->log('Comandos de rastreador gerados', [
            'model' => $data['model'],
            'equipment' => $data['serial'] ?? $data['esn'] ?? null,
        ]);

        return view('trackers.commands', compact('commands', 'tokens', 'data', 'phone'));
    }

    public function sendSms(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'command_token' => ['required','string','max:12000'],
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($data['command_token']), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return back()->withErrors(['sms' => 'Comando inválido ou expirado. Gere o comando novamente.']);
        }

        if (!is_array($payload)
            || !hash_equals((string) ($payload['uid'] ?? ''), (string) $request->user()->getAuthIdentifier())
            || (int) ($payload['expires_at'] ?? 0) < time()
            || !is_string($payload['command'] ?? null)
            || !is_string($payload['title'] ?? null)
            || !is_string($payload['phone'] ?? null)
            || $payload['phone'] === '') {
            return back()->withErrors(['sms' => 'Comando inválido ou expirado. Gere o comando novamente.']);
        }

        $command = $payload['command'];
        $title = $payload['title'];
        $to = $payload['phone'];

        if (strlen($command) > 2000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $command)) {
            return back()->withErrors(['sms' => 'O comando gerado não passou pela validação de segurança.']);
        }

        $userKey = (string) $request->user()->getAuthIdentifier();
        $perDestinationKey = "sms:destination:{$userKey}:{$to}";
        $hourlyKey = "sms:hour:{$userKey}";

        if (RateLimiter::tooManyAttempts($perDestinationKey, 5)) {
            return back()->withErrors(['sms' => 'Limite de envios para este número atingido. Aguarde antes de tentar novamente.']);
        }
        if (RateLimiter::tooManyAttempts($hourlyKey, 30)) {
            return back()->withErrors(['sms' => 'Limite horário de comandos por SMS atingido.']);
        }

        $sid = (string) env('TWILIO_ACCOUNT_SID', '');
        $token = (string) env('TWILIO_AUTH_TOKEN', '');
        $from = (string) env('TWILIO_PHONE_NUMBER', '');

        if ($sid === '' || $token === '' || $from === '') {
            return back()->withErrors(['sms' => 'Twilio não configurada. O comando foi gerado, mas o SMS não foi enviado.']);
        }

        RateLimiter::hit($perDestinationKey, 60);
        RateLimiter::hit($hourlyKey, 3600);

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->connectTimeout(5)
                ->timeout(12)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $command,
                ]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['sms' => 'Falha de comunicação com o provedor de SMS. Tente novamente mais tarde.']);
        }

        if (!$response->successful()) {
            Log::warning('Falha no envio Twilio', [
                'status' => $response->status(),
                'user_id' => $userKey,
                'phone_final' => substr($to, -4),
            ]);
            return back()->withErrors(['sms' => 'O provedor de SMS recusou o envio. Consulte os logs administrativos.']);
        }

        $logger->log('Comando enviado por SMS', [
            'title' => $title,
            'phone_final' => substr($to, -4),
        ]);

        return back()->with('success', 'Comando enviado por SMS.');
    }

    private function normalizeBrazilPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        if (!preg_match('/^55\d{10,11}$/', $digits)) {
            throw new \InvalidArgumentException('Informe um telefone brasileiro válido com DDD.');
        }

        return '+'.$digits;
    }
}
