@extends('layouts.app')
@section('title','Comandos de rastreadores')
@section('page-title','Comandos de rastreadores')
@section('page-subtitle','Geração de comandos Suntech e envio opcional por SMS.')

@section('content')
<div class="panel">
<form method="post" action="{{ route('trackers.generate') }}" class="form-grid">@csrf
<label>Modelo<select name="model"><option>ST300</option><option>ST390</option><option>ST4315U</option></select></label>
<label>Serial ST300/ST390<input name="serial" value="{{ old('serial',$data['serial']??'') }}"></label>
<label>ESN ST4315U (10 dígitos)<input name="esn" maxlength="10" value="{{ old('esn',$data['esn']??'') }}"></label>
<label>Número do chip<input name="phone" placeholder="69912345678" value="{{ old('phone',$data['phone']??'') }}"></label>
<label>APN<input name="apn" value="{{ old('apn',$data['apn']??'') }}" placeholder="allcom.claro.com.br"></label>
<label>Usuário APN<input name="apn_user" value="{{ old('apn_user',$data['apn_user']??'') }}"></label>
<label>Senha APN<input name="apn_password" value="{{ old('apn_password',$data['apn_password']??'') }}"></label>
<label>Host/IP<input name="host" value="{{ old('host',$data['host']??'') }}"></label>
<label>Porta<input type="number" min="1" max="65535" name="port" value="{{ old('port',$data['port']??'') }}"></label>
<label>Autenticação ST4315<select name="auth"><option>CHAP</option><option>PAP</option><option>AUTOMÁTICO</option><option value="SEM">Sem autenticação</option></select></label>
<label>Velocidade alerta<input type="number" name="speed" min="0" max="300" value="{{ old('speed',$data['speed']??110) }}"></label>
<label>Tensão ligar (132 = 13,2V)<input type="number" name="high_voltage" min="0" max="1000" value="{{ old('high_voltage',$data['high_voltage']??132) }}"></label>
<label>Tensão desligar (128 = 12,8V)<input type="number" name="low_voltage" min="0" max="1000" value="{{ old('low_voltage',$data['low_voltage']??128) }}"></label>
<div class="span-3"><button class="button primary">Gerar comandos</button></div>
</form>
</div>
@if($commands)
<div class="panel"><h2>Comandos gerados</h2><p>Os comandos são assinados no servidor e expiram em 10 minutos. O SMS só é habilitado quando um telefone válido foi informado na geração.</p>
@foreach($commands as $title=>$command)
<details @if($loop->first) open @endif>
<summary>{{ $title }}</summary>
<div class="code-box">{{ $command }}</div>
@if(!empty($phone))
<form method="post" action="{{ route('trackers.sms') }}" class="form-grid two" style="margin-top:10px">@csrf
<input type="hidden" name="command_token" value="{{ $tokens[$title] }}">
<label>Telefone<input value="{{ $phone }}" readonly></label>
<div class="actions"><button class="button secondary small">Enviar por SMS</button></div>
</form>
@else
<div class="alert info">Informe o número do chip e gere novamente para habilitar o envio por SMS.</div>
@endif
</details>
@endforeach
</div>
@endif
@endsection
