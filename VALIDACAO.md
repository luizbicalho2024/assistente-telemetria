# Validação da entrega

- PHP: 61 arquivos validados com `php -l`, sem erros de sintaxe.
- PricingService: cálculo de preço para margem de 30% validado (`100 / 0,70 = 142,86`).
- PricingService: instalação gratuita continua contabilizando o custo interno e payback.
- LegacyPasswordService: PBKDF2-SHA256 legado do Financeiro validado.
- docker-compose.yml: YAML válido.
- Serviços Docker: exatamente `assistente` e `mongo`.
- MongoDB: não publica porta 27017 no host.
- composer.json: JSON válido.
- Referências estáticas de rotas Blade: sem rotas ausentes.
- Referências de views nos controllers: sem views ausentes.
- docker/entrypoint.sh: validado com `bash -n`.
- Varredura simples de segredos: nenhum token/chave privada/URI real encontrado.

Observação: o ambiente de geração não possui Docker/Composer com acesso de rede, portanto o `docker compose build` não foi executado aqui. O Dockerfile usa PHP 8.4 + Apache e instala as extensões exigidas; a instalação final das dependências acontece no build por Composer.
