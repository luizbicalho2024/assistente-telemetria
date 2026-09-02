<?php

return [
    'groups' => [
        'comercial' => 'Comercial',
        'operacional' => 'Operacional',
        'financeiro' => 'Financeiro',
        'administracao' => 'Administração',
    ],

    'modules' => [
        'dashboard' => ['label' => 'Visão geral', 'group' => 'comercial', 'route' => 'dashboard'],
        'simulador.pj' => ['label' => 'Simulador PJ', 'group' => 'comercial', 'route' => 'simulator.pj'],
        'simulador.pf' => ['label' => 'Simulador PF', 'group' => 'comercial', 'route' => 'simulator.pf'],
        'simulador.licitacao' => ['label' => 'Licitações e editais', 'group' => 'comercial', 'route' => 'simulator.licitacao'],
        'propostas.dashboard' => ['label' => 'Dashboard de propostas', 'group' => 'comercial', 'route' => 'proposals.index'],
        'churn' => ['label' => 'Churn e base ativa', 'group' => 'comercial', 'route' => 'module.churn'],
        'aprovacoes' => ['label' => 'Aprovações comerciais', 'group' => 'comercial', 'route' => 'proposals.approvals'],
        'consultas' => ['label' => 'Consultas gerais', 'group' => 'operacional', 'route' => 'module.consultas'],
        'jornada' => ['label' => 'Análise de jornada', 'group' => 'operacional', 'route' => 'module.jornada'],
        'mercado' => ['label' => 'Pesquisa de mercado', 'group' => 'comercial', 'route' => 'module.mercado'],
        'clientes' => ['label' => 'Dados de clientes', 'group' => 'operacional', 'route' => 'module.clientes'],
        'estoque' => ['label' => 'Gestão de estoque', 'group' => 'operacional', 'route' => 'inventory.index'],
        'comandos' => ['label' => 'Comandos de rastreadores', 'group' => 'operacional', 'route' => 'trackers.commands'],
        'terminais' => ['label' => 'Análise de terminais', 'group' => 'operacional', 'route' => 'module.terminais'],

        'financeiro.sugesp' => ['label' => 'Relatório SUGESP', 'group' => 'financeiro', 'route' => 'finance.sugesp'],
        'financeiro.faturamento' => ['label' => 'Faturamento Verdio', 'group' => 'financeiro', 'route' => 'finance.billing'],
        'financeiro.parceiros' => ['label' => 'Faturamento de parceiros', 'group' => 'financeiro', 'route' => 'finance.partners'],
        'financeiro.resumo' => ['label' => 'Resumo mensal', 'group' => 'financeiro', 'route' => 'finance.summary'],
        'financeiro.contratos' => ['label' => 'Contratos de clientes', 'group' => 'financeiro', 'route' => 'finance.contracts'],
        'financeiro.historico' => ['label' => 'Histórico de faturamento', 'group' => 'financeiro', 'route' => 'finance.history'],
        'financeiro.comissoes' => ['label' => 'Comissão de vendedores', 'group' => 'financeiro', 'route' => 'finance.commissions'],

        'admin.users' => ['label' => 'Usuários', 'group' => 'administracao', 'route' => 'admin.users.index'],
        'admin.roles' => ['label' => 'Perfis e permissões', 'group' => 'administracao', 'route' => 'admin.roles.index'],
        'admin.settings' => ['label' => 'Identidade visual', 'group' => 'administracao', 'route' => 'admin.settings.edit'],
        'admin.logs' => ['label' => 'Auditoria e logs', 'group' => 'administracao', 'route' => 'admin.logs.index'],
    ],

    'defaults' => [
        'admin' => ['*'],
        'head_comercial' => [
            'dashboard','simulador.pj','simulador.pf','simulador.licitacao',
            'propostas.dashboard','churn','aprovacoes','mercado','clientes',
            'consultas','jornada','estoque','comandos','terminais'
        ],
        'user' => [
            'dashboard','simulador.pj','simulador.pf','simulador.licitacao',
            'propostas.dashboard','churn','aprovacoes','mercado','clientes',
            'consultas','jornada','estoque','comandos','terminais'
        ],
        'financeiro' => [
            'dashboard','financeiro.sugesp','financeiro.faturamento','financeiro.parceiros',
            'financeiro.resumo','financeiro.contratos','financeiro.historico',
            'financeiro.comissoes','estoque','clientes'
        ],
        'operacional' => [
            'dashboard','consultas','jornada','clientes','estoque','comandos','terminais'
        ],
    ],
];
