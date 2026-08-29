<?php
/**
 * Jo Modas - Testes da normalizacao do numero de WhatsApp
 *
 * Rodar pela linha de comando, a partir da raiz do projeto:
 *   php tests/whatsapp_number_test.php
 *
 * Sem framework: o projeto nao usa Composer. Sai com codigo 0 quando
 * tudo passa e 1 quando algo falha, entao serve em automacao.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$casos = [
    // [entrada, esperado, motivo]

    // --- exigidos ---
    ['42999874363',        '5542999874363', 'nacional com 11 digitos ganha o 55'],
    ['+55 42 99987-4363',  '5542999874363', 'internacional com simbolos'],
    ['5542999874363',      '5542999874363', 'ja internacional, fica igual'],
    ['123',                '',              'curto demais'],
    ['abcdefghijk',        '',              'sem digito nenhum'],

    // --- formatacao livre ---
    ['55 (42) 99987 4363', '5542999874363', 'parenteses e espacos'],
    ['  +55-42-99987.4363 ', '5542999874363', 'tracos, pontos e espacos'],
    ['(42) 99987-4363',    '5542999874363', 'nacional com parenteses'],

    // --- fixo, 8 digitos ---
    ['4232241234',         '554232241234',  'fixo nacional com 10 digitos'],
    ['554232241234',       '554232241234',  'fixo ja internacional'],

    // --- DDD 55 e a armadilha do prefixo ---
    // O DDD 55 e de Santa Maria (RS). Decidir pelo prefixo, e nao pelo
    // comprimento, deixaria este numero sem codigo do pais.
    ['55999874363',        '5555999874363', 'DDD 55 nacional ganha o 55 do pais'],
    ['5555999874363',      '5555999874363', 'DDD 55 ja internacional'],

    // --- recusados ---
    ['',                   '',              'vazio'],
    ['0',                  '',              'um digito'],
    ['999874363',          '',              '9 digitos, falta o DDD'],
    ['55429998743631',     '',              '14 digitos, longo demais'],
    ['5542999874363999',   '',              'muito longo'],
    ['1042999874363',      '',              'nao comeca com 55'],
    ['3442999874363',      '',              'codigo de outro pais'],
    ['5540999874363',      '',              'DDD 40 nao existe (termina em zero)'],
    ['5502999874363',      '',              'DDD 02 nao existe'],
    ['5542899874363',      '',              'celular de 9 digitos tem de comecar com 9'],
];

$passou = 0;
$falhou = 0;

echo "Normalizacao do numero de WhatsApp\n";
echo str_repeat('-', 74), "\n";

foreach ($casos as [$entrada, $esperado, $motivo]) {
    $obtido = normalize_whatsapp_number($entrada);
    $ok     = $obtido === $esperado;

    $ok ? $passou++ : $falhou++;

    printf(
        "%-4s %-22s -> %-16s %s\n",
        $ok ? ' ok ' : 'FALHA',
        '"' . $entrada . '"',
        $obtido === '' ? '(recusado)' : $obtido,
        $ok ? $motivo : 'ESPERADO: ' . ($esperado === '' ? '(recusado)' : $esperado)
    );
}

// A leitura da loja tem de devolver exatamente o que foi gravado.
$guardado = store_whatsapp_number();
$okLeitura = $guardado === normalize_whatsapp_number($guardado);

$okLeitura ? $passou++ : $falhou++;

echo str_repeat('-', 74), "\n";
printf("%-4s leitura de settings devolve numero normalizado: %s\n",
    $okLeitura ? ' ok ' : 'FALHA',
    $guardado === '' ? '(nao cadastrado)' : $guardado);

echo str_repeat('-', 74), "\n";
printf("%d passaram, %d falharam\n", $passou, $falhou);

exit($falhou === 0 ? 0 : 1);
