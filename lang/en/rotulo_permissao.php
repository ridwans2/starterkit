<?php

/*
 * Rótulo das permissões que o Shield mostra na tela de papéis.
 *
 * O nome gravado em `permissions.name` é IDENTIDADE (é o que `authorize()` e o banco
 * comparam) e por isso não é renomeado. A chave abaixo é o `Str::snake()` do prefixo,
 * que é o que `Utils::toLocalizationKey()` procura (vendor/bezhansalleh/filament-shield/
 * src/Support/Utils.php:362). Prefixos padrão (view/create/update/…) não estão aqui:
 * eles caem no mapa do próprio pacote, que já responde em cada idioma.
 */

return [
    'rotulos' => [
        'aceitar'                   => 'Accept',
        'recusar'                   => 'Decline',
        'reenviar'                  => 'Resend',
        'desativar'                 => 'Deactivate',
        'reativar'                  => 'Reactivate',
        'atribuir_papeis'           => 'Assign roles',
        'vincular_usuario'          => 'Link user',
        'desvincular_usuario'       => 'Unlink user',

        // Permissão CUSTOM usa outra chave no resolvedor: `getCustomPermissionLabel()` passes
        // o NOME COMPLETO (`Aceitar:Convite`), e o `Str::snake()` do separador vira
        // `aceitar_convite`. Sem estas linhas a tela cai no `headline()` do nome PT.
        'aceitar_convite'            => 'Accept invitation',
        'recusar_convite'            => 'Decline invitation',
        'reenviar_convite'           => 'Resend invitation',
        'atribuir_papeis_tenant'     => 'Assign roles',
        'vincular_usuario_tenant'    => 'Link user',
        'desvincular_usuario_tenant' => 'Unlink user',
    ],
];
