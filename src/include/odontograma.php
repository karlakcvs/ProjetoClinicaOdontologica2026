<?php
// Odontograma (notação FDI): arcada superior 18→11 | 21→28 e inferior 48→41 | 31→38.
// $mapa = [dente => ['situacao' => ..., 'observacao' => ...]]
function odontograma_html(array $mapa, $editavel = false) {
    $linhas = [
        'Arcada superior' => [range(18, 11), range(21, 28)],
        'Arcada inferior' => [range(48, 41), range(31, 38)],
    ];
    $h = '<div class="odonto" role="group" aria-label="Odontograma">';
    foreach ($linhas as $rot => [$dir, $esq]) {
        $h .= '<div class="odonto-arcada"><span class="odonto-rot">' . e($rot) . '</span><div class="odonto-linha">';
        foreach ([$dir, $esq] as $i => $lado) {
            $h .= '<div class="odonto-lado' . ($i ? ' esq' : '') . '">';
            foreach ($lado as $d) {
                $s = $mapa[$d]['situacao'] ?? 'HIGIDO';
                [$nome, $cor] = SITUACOES_DENTE[$s] ?? SITUACOES_DENTE['HIGIDO'];
                $obs = $mapa[$d]['observacao'] ?? '';
                $tit = 'Dente ' . $d . ': ' . $nome . ($obs ? ' – ' . $obs : '');
                $molar = in_array($d % 10, [6, 7, 8], true) ? ' molar' : (in_array($d % 10, [4, 5], true) ? ' pre' : '');
                $attr = 'class="dente sit-' . strtolower($s) . $molar . '" style="--d:' . $cor . '" title="' . e($tit) . '"';
                $miolo = '<span class="dente-forma" aria-hidden="true"></span><span class="dente-num">' . $d . '</span><span class="visually-hidden">' . e($tit) . '</span>';
                $h .= $editavel
                    ? '<button type="button" ' . $attr . ' data-dente="' . $d . '" data-sit="' . $s . '" data-obs="' . e($obs) . '" data-bs-toggle="modal" data-bs-target="#mDente">' . $miolo . '</button>'
                    : '<span ' . $attr . '>' . $miolo . '</span>';
            }
            $h .= '</div>';
        }
        $h .= '</div></div>';
    }
    $h .= '<ul class="odonto-legenda">';
    foreach (SITUACOES_DENTE as $k => [$nome, $cor]) $h .= '<li><span style="--d:' . $cor . '" class="sit-' . strtolower($k) . '"></span>' . e($nome) . '</li>';
    return $h . '</ul></div>';
}

function odontograma_mapa(PDO $pdo, $idPac) {
    $st = $pdo->prepare("SELECT dente, situacao, observacao FROM odontograma WHERE id_paciente = ?");
    $st->execute([$idPac]);
    $m = [];
    foreach ($st->fetchAll() as $r) $m[(int) $r['dente']] = $r;
    return $m;
}
