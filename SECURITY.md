# Política de Segurança

## Versões suportadas

A branch `main` representa a versão suportada do Assistente Telemetria.

## Como reportar uma vulnerabilidade

Não publique credenciais, tokens, dados de clientes ou detalhes exploráveis em issues públicas.

Prefira o recurso **Security > Advisories > Report a vulnerability** do próprio GitHub para enviar o relato de forma privada.

Inclua, quando possível:

- arquivo/rota afetada;
- impacto observado;
- pré-condições necessárias;
- passos mínimos para reprodução;
- sugestão de mitigação.

## Regras de operação

- segredos reais nunca devem ser versionados;
- o `.env` deve ser mantido fora do Git;
- `APP_DEBUG` deve permanecer `false` em produção;
- a aplicação deve ficar atrás de HTTPS/reverse proxy;
- a porta do container da aplicação deve permanecer vinculada a loopback quando o proxy estiver no mesmo host;
- atualizações de dependências devem passar por `composer audit`, testes e secret scanning antes do merge.
