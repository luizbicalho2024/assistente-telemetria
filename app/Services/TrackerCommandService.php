<?php

namespace App\Services;

class TrackerCommandService
{
    public function generate(string $model, array $data): array
    {
        $model = strtoupper(trim($model));

        return match ($model) {
            'ST300' => $this->st300($data),
            'ST390' => $this->st390($data),
            'ST4315U', 'ST4315' => $this->st4315($data),
            default => throw new \InvalidArgumentException('Modelo não suportado.'),
        };
    }

    private function st300(array $d): array
    {
        $serial = $this->protocolValue($d['serial'] ?? '', 'serial', 40, true);
        $apn = $this->protocolValue($d['apn'] ?? 'allcom.claro.com.br', 'APN', 100, true);
        $user = $this->protocolValue($d['apn_user'] ?? 'allcom', 'usuário APN', 80);
        $password = $this->protocolValue($d['apn_password'] ?? 'allcom', 'senha APN', 80);
        $host = $this->host($d['host'] ?? '54.94.190.167');
        $port = $this->port($d['port'] ?? 9601);

        return [
            'Configurar rede' => "ST300NTW;{$serial};02;1;{$apn};{$user};{$password};{$host};{$port};;;",
            'Solicitar posição atual' => "ST300CMD;{$serial};02;StatusReq",
            'Reiniciar equipamento' => "ST300RST;{$serial};02;Reboot",
            'Ativar saída 1 — bloqueio' => "ST300OUT;{$serial};02;Enable1",
            'Desativar saída 1 — desbloqueio' => "ST300OUT;{$serial};02;Disable1",
        ];
    }

    private function st390(array $d): array
    {
        $serial = $this->protocolValue($d['serial'] ?? '', 'serial', 40, true);
        $apn = $this->protocolValue($d['apn'] ?? 'allcom.claro.com.br', 'APN', 100, true);
        $host = $this->host($d['host'] ?? '54.94.190.167');
        $port = $this->port($d['port'] ?? 9601);

        return [
            'Configurar APN' => "ST400CMD;{$serial};;{$apn};1",
            'Configurar IP e porta' => "ST400CMD;{$serial};;{$host};{$port};{$host};{$port}",
        ];
    }

    private function st4315(array $d): array
    {
        $esn = trim((string) ($d['esn'] ?? ''));
        if (!preg_match('/^\d{10}$/', $esn)) {
            throw new \InvalidArgumentException('O ESN do ST4315U deve possuir 10 dígitos.');
        }

        $authMap = ['CHAP'=>'01','PAP'=>'00','AUTOMATICO'=>'02','AUTOMÁTICO'=>'02','SEM'=>'03'];
        $authName = strtoupper(trim((string) ($d['auth'] ?? 'CHAP')));
        if (!array_key_exists($authName, $authMap)) {
            throw new \InvalidArgumentException('Método de autenticação inválido.');
        }

        $auth = $authMap[$authName];
        $apn = $this->protocolValue($d['apn'] ?? 'conexao.getrak.com', 'APN', 100, true);
        $user = $this->protocolValue($d['apn_user'] ?? '', 'usuário APN', 80);
        $password = $this->protocolValue($d['apn_password'] ?? '', 'senha APN', 80);
        $host = $this->host($d['host'] ?? 'st4315.getrak.com.br');
        $port = $this->port($d['port'] ?? 13018);
        $speed = max(0, min(300, (int) ($d['speed'] ?? 110)));
        $high = max(0, min(1000, (int) ($d['high_voltage'] ?? 132)));
        $low = max(0, min(1000, (int) ($d['low_voltage'] ?? 128)));

        if ($high < $low) {
            throw new \InvalidArgumentException('A tensão de ligar não pode ser menor que a tensão de desligar.');
        }

        return [
            'Configurar APN' => "PRG;{$esn};10;00#{$auth};01#{$apn};02#{$user};03#{$password}",
            'Configurar host e porta' => "PRG;{$esn};10;05#{$host};06#{$port};08#{$host};09#{$port}",
            'Configurar protocolo TCP' => "PRG;{$esn};10;07#00;10#00",
            'Desabilitar parâmetro ZIP' => "PRG;{$esn};10;55#00",
            'Tempos padrão — 1 hora desligado e 2 minutos ligado' => "PRG;{$esn};16;70#3600;71#0;72#0;73#120;74#0;75#0;76#0;77#0;78#0;79#120;80#0;81#30;82#120;83#0;84#30;85#120;86#0;87#30",
            'Tempos simplificados — ignição ligada/desligada' => "PRG;{$esn};16;70#3600;79#120",
            'Configurar excesso de velocidade' => "PRG;{$esn};16;21#{$speed}",
            'Ignição física — pós-chave' => "PRG;{$esn};17;00#01",
            'Ignição virtual por acelerômetro' => "PRG;{$esn};17;00#03",
            'Ignição virtual por tensão' => "PRG;{$esn};17;00#02",
            'Ajustar tensão de ignição virtual' => "PRG;{$esn};17;15#{$high};16#{$low}",
            'Reiniciar equipamento' => "CMD;{$esn};03;03",
            'Solicitar posição' => "CMD;{$esn};03;01",
            'Ativar saída 1' => "CMD;{$esn};04;01",
            'Desativar saída 1' => "CMD;{$esn};04;02",
            'Configurar string de dados' => "PRG;{$esn};10;80#00fff83f;82#00fff83f;84#00fff83f;86#00;87#00001ffbff;97#00",
            'Configuração global 1' => "PRG;{$esn};10;81#0007800f",
            'Configuração global 2' => "PRG;{$esn};11;00#02;01#01;02#00;03#50;04#00;05#00;06#00;07#00;08#00;09#00;10#00;11#00;12#00;13#00;14#00;40#01;41#02;42#06;43#00;44#00",
            'Configuração global 3' => "PRG;{$esn};11;45#00;46#00;47#00;60#00;61#00;62#00;63#00;64#00;65#00;66#00;67#00",
            'Configuração moto 1' => "PRG;{$esn};10;60#0;70#00",
            'Configuração moto 2' => "PRG;{$esn};19;00#02;01#0.10",
            'Configuração moto 3' => "PRG;{$esn};19;01",
            'Configuração moto 4' => "PRG;{$esn};17;01#120",
            'Configuração moto 5' => "PRG;{$esn};16;70#0",
        ];
    }

    private function protocolValue(mixed $value, string $field, int $maxLength, bool $required = false): string
    {
        $value = trim((string) $value);

        if ($required && $value === '') {
            throw new \InvalidArgumentException("Informe {$field}.");
        }

        if (mb_strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("O campo {$field} excede o tamanho permitido.");
        }

        if (preg_match('/[;#\x00-\x1F\x7F]/u', $value)) {
            throw new \InvalidArgumentException("O campo {$field} contém caracteres reservados do protocolo.");
        }

        return $value;
    }

    private function host(mixed $value): string
    {
        $host = $this->protocolValue($value, 'host', 180, true);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        if (!preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $host)) {
            throw new \InvalidArgumentException('Host/IP inválido.');
        }

        return $host;
    }

    private function port(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Porta inválida.');
        }

        $port = (int) $value;
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('A porta deve estar entre 1 e 65535.');
        }

        return $port;
    }
}
