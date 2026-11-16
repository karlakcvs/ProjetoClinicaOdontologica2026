<?php
// =====================================================================
//  Versão 6 (ajustes finais para a apresentação):
//  - avaliações: todas 5 estrelas, mês/ano e "paciente desde"
//  - preço próprio de cada dentista por procedimento (dentista_procedimento.valor)
//  - formação dos dentistas igual à universidade bordada no jaleco
//  - 10 pacientes novos com foto (alguns com cadastro incompleto)
//  - pacientes e atendimentos dos anos anteriores (2023 a 2026) para os gráficos
//  - agenda cheia nesta semana e nas duas seguintes
//  - faltas e cancelamentos nunca ficam no financeiro
//  Só mexe em dados de DEMONSTRAÇÃO; nada que você cadastrou é apagado.
// =====================================================================

function migracao_6_estrutura(PDO $pdo) {
    add_coluna($pdo, 'avaliacao', 'paciente_desde', 'SMALLINT NULL');
    add_coluna($pdo, 'dentista_procedimento', 'valor', 'DECIMAL(10,2) NULL');
    try { $pdo->exec("CREATE INDEX idx_fin_data ON registro_financeiro (data)"); } catch (PDOException $e) {}
    try { $pdo->exec("CREATE INDEX idx_cons_data ON consulta (data, id_dentista)"); } catch (PDOException $e) {}
}

function migracao_6(PDO $pdo) {
    // ---------- 1. faltas e cancelamentos não entram no financeiro ----------
    $pdo->exec("DELETE FROM registro_financeiro WHERE id_consulta IN (SELECT id_consulta FROM consulta WHERE status IN ('CANCELADA','FALTOU'))");

    // ---------- 2. formação = universidade bordada no jaleco da foto ----------
    $formacao = [
        'CRO-SP 00.001' => 'Graduação em Odontologia pela Unicamp (Faculdade de Odontologia de Piracicaba – FOP), especialização em Ortodontia e Ortopedia Facial e mestrado em Ortodontia, também pela FOP-Unicamp.',
        'CRO-SP 00.002' => 'Graduação em Odontologia pela UFMG (Faculdade de Odontologia – Belo Horizonte), especialização em Implantodontia e mestrado e doutorado em Cirurgia e Traumatologia Bucomaxilofacial, também pela UFMG.',
        'CRO-SP 00.003' => 'Graduação em Odontologia pela USP (Faculdade de Odontologia de São Paulo – FOUSP), especialização em Dentística Restauradora pela FOUSP e aperfeiçoamento em Harmonização Orofacial.',
    ];
    $up = $pdo->prepare("UPDATE dentista SET formacao = ? WHERE cro = ?");
    foreach ($formacao as $cro => $txt) $up->execute([$txt, $cro]);

    // ---------- 3. preço próprio de cada dentista ("a partir de" no site) ----------
    $precosDent = [
        // CRO => [parte do nome do procedimento => valor]
        'CRO-SP 00.001' => ['Primeira consulta' => 100, 'Limpeza' => 150],
        'CRO-SP 00.002' => ['Primeira consulta' => 150],
        'CRO-SP 00.003' => ['Primeira consulta' => 120, 'Limpeza' => 180, 'Restauração' => 180],
    ];
    $idsDent = $pdo->query("SELECT cro, id_dentista FROM dentista")->fetchAll(PDO::FETCH_KEY_PAIR);
    $procs = $pdo->query("SELECT id_procedimento, nome FROM procedimento")->fetchAll(PDO::FETCH_KEY_PAIR);
    $upP = $pdo->prepare("UPDATE dentista_procedimento SET valor = ? WHERE id_dentista = ? AND id_procedimento = ? AND valor IS NULL");
    foreach ($precosDent as $cro => $lista) {
        if (empty($idsDent[$cro])) continue;
        foreach ($lista as $trecho => $valor) {
            foreach ($procs as $idP => $nomeP) if (stripos($nomeP, $trecho) !== false) $upP->execute([$valor, $idsDent[$cro], $idP]);
        }
    }

    // ---------- 4. avaliações: 5 estrelas, mês/ano e "paciente desde" ----------
    $pdo->exec("UPDATE avaliacao SET nota = 5 WHERE nota < 5");
    $desde = [
        'Mariana C.' => [2021, 210], 'José R.' => [2020, 160], 'Patrícia L.' => [2023, 120], 'Lucas M.' => [2022, 95],
        'Renata F.' => [2019, 300], 'Carlos A.' => [2024, 70], 'Fernanda T.' => [2025, 40], 'Gustavo P.' => [2023, 260],
        'Sônia M.' => [2018, 25], 'André L.' => [2024, 140], 'Beatriz N.' => [2022, 55], 'Cláudia R.' => [2021, 12],
        'Henrique B.' => [2019, 0], 'Ana Beatriz L.' => [2019, 1],
    ];
    $upA = $pdo->prepare("UPDATE avaliacao SET paciente_desde = ?, criado_em = ? WHERE nome = ? AND demo = 1");
    foreach ($desde as $nome => [$ano, $dias]) {
        $upA->execute([$ano, date('Y-m-d H:i:s', strtotime("-$dias days 10:30")), $nome]);
    }
    $pdo->exec("UPDATE avaliacao SET comentario = 'Ótima ortodontista, explica tudo com calma. As manutenções são rápidas e sempre no horário.' WHERE nome = 'Lucas M.' AND demo = 1");
    $ids = array_map('intval', $pdo->query("SELECT id_dentista FROM dentista ORDER BY id_dentista")->fetchAll(PDO::FETCH_COLUMN));
    if (count($ids) >= 3 && !(int) $pdo->query("SELECT COUNT(*) FROM avaliacao WHERE nome = 'Otávio R.'")->fetchColumn()) {
        $st = $pdo->prepare("INSERT INTO avaliacao (id_dentista, nome, nota, comentario, status, demo, paciente_desde, criado_em) VALUES (?,?,5,?, 'APROVADA', 1, ?, ?)");
        foreach ([
            [$ids[1], 'Otávio R.',  'Coloquei dois implantes e não senti nada. O Dr. Rafael mostrou a tomografia e explicou cada etapa.', 2022, 33],
            [$ids[0], 'Letícia C.', 'Minha filha usa aparelho com a Dra. Helena e adora as consultas. Atendimento muito humano.', 2024, 48],
            [$ids[2], 'Juliana F.', 'Fiz lentes com a Dra. Camila e ficou super natural. Recebo elogios toda semana!', 2024, 18],
            [null,    'Thiago M.',  'Recepção atenciosa, lembrete pelo WhatsApp e consultório impecável. Recomendo a clínica.', 2023, 6],
        ] as [$idD, $nome, $txt, $ano, $dias]) {
            $st->execute([$idD, $nome, $txt, $ano, date('Y-m-d H:i:s', strtotime("-$dias days 15:10"))]);
        }
    }

    // ---------- dados de procedimentos usados nas próximas etapas ----------
    $procPorDentista = []; $procInfo = [];
    foreach ($pdo->query("SELECT dp.id_dentista, p.id_procedimento, p.duracao_min, COALESCE(dp.valor, p.valor) AS valor, p.nome
                          FROM dentista_procedimento dp JOIN procedimento p ON p.id_procedimento = dp.id_procedimento")->fetchAll() as $r) {
        $procPorDentista[$r['id_dentista']][] = (int) $r['id_procedimento'];
        $procInfo[$r['id_dentista']][$r['id_procedimento']] = ['dur' => (int) $r['duracao_min'], 'valor' => (float) $r['valor'], 'nome' => $r['nome']];
    }
    if (!$procPorDentista) return;

    // ---------- 5. dez pacientes novos com foto (alguns com cadastro incompleto) ----------
    $novos = demo6_pacientes_novos($pdo);

    // ---------- 6. pacientes e atendimentos dos anos anteriores ----------
    demo6_historico($pdo, $procPorDentista, $procInfo);

    // ---------- 7. agenda cheia nesta semana e nas duas seguintes ----------
    $seg = date('Y-m-d', strtotime(date('N') >= 6 ? 'next monday' : 'monday this week'));
    demo6_agenda($pdo, $procPorDentista, $procInfo, $novos, $seg, date('Y-m-d', strtotime("$seg +18 days")));
}

// ---------------------------------------------------------------------
function demo6_pacientes_novos(PDO $pdo) {
    // [nome, foto, nascimento, desde, completo?, cpf mesmo sem endereço?, observação]
    $lista = [
        ['Juliana Ferraz',   'unsplash:1438761681033-6461ffad8d80', '1992-06-14', '2024-03-11', true,  true,  null],
        ['Thiago Moreira',   'unsplash:1500648767791-00dcc994a43e', '1987-02-03', '2023-05-22', true,  true,  'Prefere atendimento no fim da tarde.'],
        ['Mariana Teixeira', 'unsplash:1534528741775-53994a69daeb', '1996-10-27', '2025-02-17', true,  true,  null],
        ['Letícia Campos',   'unsplash:1517841905240-472988babdf9', '1984-08-09', '2024-07-01', true,  true,  'Mãe da Sofia (8 anos), também paciente da Dra. Helena.'],
        ['Otávio Ramos',     'unsplash:1472099645785-5658abf4ff4e', '1975-12-01', '2022-09-05', true,  true,  'Diabético tipo 2 — conferir glicemia antes de cirurgia.'],
        ['Rafaela Queiroz',  'unsplash:1544005313-94ddf0286df2',    null,         null,         false, false, null],
        ['Leonardo Pires',   'unsplash:1506794778202-cad84cf45f1d', null,         null,         false, false, 'Agendou pelo site.'],
        ['Vinícius Almeida', 'unsplash:1539571696357-5a69c17a67c6', '2001-04-18', null,         false, false, null],
        ['Natália Freitas',  'unsplash:1580489944761-15a19d654956', null,         null,         false, false, null],
        ['Caio Ribeiro',     'unsplash:1599566150163-29194dcaad36', '1998-01-30', '2026-10-06', false, true,  null],
    ];
    $ruas = ['Rua Fictícia dos Ipês', 'Avenida Modelo das Nações', 'Rua Imaginária do Lago', 'Rua Exemplo dos Jasmins', 'Rua Fictícia São Bento'];
    $bairros = ['Jardim Exemplo', 'Centro', 'Vila Demonstração', 'Parque Teste'];
    $busca = $pdo->prepare("SELECT id_paciente FROM paciente WHERE nome = ?");
    $ins = $pdo->prepare("INSERT INTO paciente (nome, telefone, email, data_nascimento, observacoes, cpf, cep, logradouro, numero, bairro, cidade, uf, foto, criado_em)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ids = [];
    foreach ($lista as $i => [$nome, $foto, $nasc, $desde, $completo, $temCpf, $obs]) {
        $busca->execute([$nome]);
        if ($id = $busca->fetchColumn()) { $ids[] = (int) $id; continue; }
        $mail = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', explode(' ', $nome)[0] . '.' . explode(' ', $nome)[1])) . '@paciente.example';
        $mail = preg_replace('/[^a-z.@]/', '', $mail);
        $criado = $desde ? $desde . ' 10:00:00' : date('Y-m-d H:i:s', strtotime('-' . (2 + $i) . ' days 11:20'));
        $ins->execute([
            $nome, '(19) 90000-' . str_pad((string) (200 + $i), 4, '0', STR_PAD_LEFT), $i % 4 === 3 ? null : $mail, $nasc, $obs,
            $temCpf ? cpf_ficticio(6200 + $i) : null,
            $completo ? '00000-' . str_pad((string) (300 + $i), 3, '0', STR_PAD_LEFT) : null,
            $completo ? $ruas[$i % count($ruas)] : null, $completo ? (string) (40 + $i * 37) : null, $completo ? $bairros[$i % count($bairros)] : null,
            $completo ? 'Araras' : null, $completo ? 'SP' : null, $foto, $criado,
        ]);
        $ids[] = (int) $pdo->lastInsertId();
    }
    return $ids;
}

// ---------------------------------------------------------------------
//  Histórico: pacientes antigos (sem foto, cadastro completo) e atendimentos
//  de jan/2022 a set/2026, com crescimento ano a ano e meses mais fracos
//  em janeiro, julho e dezembro (férias). Só preenche meses que estão vazios.
// ---------------------------------------------------------------------
function demo6_historico(PDO $pdo, $procPorDentista, $procInfo) {
    // pacientes antigos
    $nomes = ['Adriana', 'Alessandra', 'Aline', 'Amanda', 'Bárbara', 'Beatriz', 'Bianca', 'Bruna', 'Camila', 'Carolina', 'Cecília', 'Cristiane', 'Daniela', 'Débora',
              'Elaine', 'Fabiana', 'Fernanda', 'Flávia', 'Giovana', 'Helena', 'Ingrid', 'Jéssica', 'Joana', 'Karina', 'Lara', 'Lívia', 'Luana', 'Luciana', 'Marina',
              'Melissa', 'Mônica', 'Nathália', 'Paula', 'Priscila', 'Raquel', 'Renata', 'Sabrina', 'Simone', 'Sofia', 'Tatiane', 'Valéria', 'Vanessa', 'Yasmin',
              'Alexandre', 'André', 'Antônio', 'Arthur', 'Bernardo', 'Carlos', 'César', 'Daniel', 'Davi', 'Eduardo', 'Fábio', 'Fernando', 'Gabriel', 'Guilherme',
              'Gustavo', 'Hugo', 'Igor', 'Jorge', 'José', 'Leandro', 'Lucas', 'Luiz', 'Marcelo', 'Mateus', 'Maurício', 'Murilo', 'Nicolas', 'Paulo', 'Pedro',
              'Renato', 'Ricardo', 'Roberto', 'Samuel', 'Sérgio', 'Tiago', 'Vítor', 'Wagner'];
    $sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Carvalho',
                   'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira', 'Barbosa', 'Rocha', 'Dias', 'Nascimento', 'Andrade', 'Moreira', 'Nunes', 'Marques',
                   'Machado', 'Mendes', 'Freitas', 'Cardoso', 'Ramos', 'Gonçalves', 'Santana', 'Teixeira', 'Batista', 'Moura', 'Campos', 'Correia', 'Pinto'];
    $ruas = ['Rua Fictícia das Acácias', 'Avenida Modelo dos Ipês', 'Rua Imaginária das Palmeiras', 'Rua Exemplo das Orquídeas', 'Rua Modelo dos Jequitibás',
             'Rua Fictícia dos Girassóis', 'Avenida Fictícia Brasil', 'Rua Imaginária das Mangueiras', 'Rua Exemplo do Bosque', 'Rua Fictícia Tiradentes'];
    $bairros = ['Centro', 'Jardim Exemplo', 'Vila Demonstração', 'Parque Teste', 'Jardim Modelo', 'Vila Imaginária'];

    $jaTem = (int) $pdo->query("SELECT COUNT(*) FROM paciente WHERE telefone LIKE '(19) 90001-%'")->fetchColumn();
    if (!$jaTem) {
        mt_srand(6600);
        $ins = $pdo->prepare("INSERT INTO paciente (nome, telefone, email, data_nascimento, cpf, cep, logradouro, numero, bairro, cidade, uf, criado_em)
                              VALUES (?,?,?,?,?,?,?,?,?, 'Araras', 'SP', ?)");
        $usados = array_flip($pdo->query("SELECT nome FROM paciente")->fetchAll(PDO::FETCH_COLUMN));
        $n = 0;
        while ($n < 190) {
            $nome = $nomes[mt_rand(0, count($nomes) - 1)] . ' ' . $sobrenomes[mt_rand(0, count($sobrenomes) - 1)];
            if (mt_rand(1, 100) <= 30) $nome .= ' ' . $sobrenomes[mt_rand(0, count($sobrenomes) - 1)];
            if (isset($usados[$nome])) continue;
            $usados[$nome] = 1;
            $partes = explode(' ', $nome);
            $mail = preg_replace('/[^a-z.]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $partes[0] . '.' . end($partes)))) . ($n % 7) . '@paciente.example';
            $nasc = sprintf('%04d-%02d-%02d', mt_rand(1950, 2016), mt_rand(1, 12), mt_rand(1, 28));
            // chegaram à clínica entre 2019 e meados de 2026 (mais gente nos anos recentes)
            $r = mt_rand(1, 100);
            $ano = $r <= 12 ? mt_rand(2019, 2021) : ($r <= 32 ? 2022 : ($r <= 55 ? 2023 : ($r <= 78 ? 2024 : ($r <= 94 ? 2025 : 2026))));
            $mesMax = $ano === 2026 ? 8 : 12;
            $desde = sprintf('%04d-%02d-%02d 09:00:00', $ano, mt_rand(1, $mesMax), mt_rand(1, 28));
            $ins->execute([$nome, '(19) 90001-' . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT), mt_rand(1, 100) <= 85 ? $mail : null, $nasc,
                           cpf_ficticio(7000 + $n), '00000-' . str_pad((string) (500 + $n), 3, '0', STR_PAD_LEFT),
                           $ruas[mt_rand(0, count($ruas) - 1)], (string) mt_rand(10, 1999), $bairros[mt_rand(0, count($bairros) - 1)], $desde]);
            $n++;
        }
    }

    // pacientes que podem aparecer no histórico (cadastrados antes da consulta)
    $pool = $pdo->query("SELECT id_paciente, criado_em FROM paciente WHERE criado_em < '2026-10-01' ORDER BY criado_em")->fetchAll();
    if (!$pool) return;
    $poolIds = array_map(fn($p) => (int) $p['id_paciente'], $pool);
    $poolDesde = array_map(fn($p) => substr((string) $p['criado_em'], 0, 10), $pool);

    $turnos = [];
    foreach ($pdo->query("SELECT id_dentista, dia_semana, hora_inicio, hora_fim FROM horario_atendimento ORDER BY hora_inicio")->fetchAll() as $h) {
        $turnos[$h['id_dentista']][(int) $h['dia_semana']][] = [hm_($h['hora_inicio']), hm_($h['hora_fim'])];
    }
    // atendimentos realizados por mês (clínica toda): crescimento anual + sazonalidade
    $base = [2022 => 48, 2023 => 70, 2024 => 100, 2025 => 138, 2026 => 172];
    $saz = [1 => .72, 2 => .9, 3 => 1.05, 4 => 1.0, 5 => 1.06, 6 => 1.0, 7 => .78, 8 => 1.04, 9 => 1.08, 10 => 1.12, 11 => 1.06, 12 => .74];
    $reajuste = [2022 => .76, 2023 => .82, 2024 => .88, 2025 => .94, 2026 => 1.0];   // tabela de preços dos anos anteriores
    // peso de cada procedimento (manutenção e limpeza são as mais comuns; cirurgia é rara)
    $peso = function ($nome) {
        if (stripos($nome, 'Cirurgia') !== false) return 1;
        if (stripos($nome, 'Instalação') !== false || stripos($nome, 'Planejamento') !== false) return 2;
        if (stripos($nome, 'Manutenção') !== false || stripos($nome, 'Limpeza') !== false) return 7;
        if (stripos($nome, 'Primeira consulta') !== false) return 5;
        return 4;
    };
    $sorteio = [];
    foreach ($procPorDentista as $idD => $lista) foreach ($lista as $idP) for ($k = 0; $k < $peso($procInfo[$idD][$idP]['nome']); $k++) $sorteio[$idD][] = $idP;

    $contaMes = $pdo->prepare("SELECT COUNT(*) FROM registro_financeiro WHERE data BETWEEN ? AND ?");
    $stC = $pdo->prepare("INSERT INTO consulta (id_paciente, id_dentista, id_procedimento, data, hora_inicio, hora_fim, status, origem, primeira_consulta, demo, criado_em) VALUES (?,?,?,?,?,?,?,?,?,1,?)");
    $stF = $pdo->prepare("INSERT INTO registro_financeiro (id_consulta, id_dentista, valor, forma_pagamento, data) VALUES (?,?,?,?,?)");
    $ocup = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE id_dentista = ? AND data = ? AND status <> 'CANCELADA' AND hora_inicio < ? AND hora_fim > ?");
    $formas = ['PIX', 'PIX', 'PIX', 'Cartão de crédito', 'Cartão de crédito', 'Cartão de débito', 'Dinheiro'];
    $dentistas = array_keys($turnos);
    mt_srand(6601);

    for ($ano = 2022; $ano <= 2026; $ano++) {
        for ($mes = 1; $mes <= 12; $mes++) {
            if ($ano === 2026 && $mes >= 10) break;            // outubro em diante já tem a agenda de demonstração
            $ini = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = date('Y-m-t', strtotime($ini));
            $alvo = (int) round($base[$ano] * $saz[$mes] * ($ano === 2026 ? 1 + ($mes - 1) * .045 : 1) * (mt_rand(92, 108) / 100));
            $contaMes->execute([$ini, $fim]);
            $falta = $alvo - (int) $contaMes->fetchColumn();
            if ($falta < $alvo * .4) continue;                  // mês já tem dados

            // dias úteis do mês
            $diasUteis = [];
            for ($t = strtotime($ini); $t <= strtotime($fim); $t = strtotime('+1 day', $t)) if ((int) date('N', $t) <= 5) $diasUteis[] = $t;
            $porDia = max(1, (int) ceil($falta * 1.12 / count($diasUteis)));   // +12% de faltas/cancelamentos
            $feitos = 0;
            foreach ($diasUteis as $t) {
                $dia = date('Y-m-d', $t);
                $dow = (int) date('w', $t);
                $qtdDia = max(0, $porDia + mt_rand(-2, 2));
                for ($k = 0; $k < $qtdDia && $feitos < $falta; $k++) {
                    $idD = $dentistas[mt_rand(0, count($dentistas) - 1)];
                    $turnosDia = $turnos[$idD][$dow] ?? [];
                    if (!$turnosDia || empty($sorteio[$idD])) continue;
                    [$tIni, $tFim] = $turnosDia[mt_rand(0, count($turnosDia) - 1)];
                    $idP = $sorteio[$idD][mt_rand(0, count($sorteio[$idD]) - 1)];
                    $info = $procInfo[$idD][$idP];
                    $slots = (int) floor(((strtotime("$dia $tFim") - strtotime("$dia $tIni")) / 60 - $info['dur']) / 15);
                    if ($slots < 0) continue;
                    $h = strtotime("$dia $tIni") + mt_rand(0, $slots) * 15 * 60;
                    $hf = $h + $info['dur'] * 60;
                    $ocup->execute([$idD, $dia, date('H:i:s', $hf), date('H:i:s', $h)]);
                    if ($ocup->fetchColumn()) continue;
                    // paciente que já era da clínica nessa data
                    $lim = 0;
                    while ($lim < count($poolDesde) && $poolDesde[$lim] <= $dia) $lim++;
                    if (!$lim) continue;
                    $idPac = $poolIds[mt_rand(0, $lim - 1)];
                    $r = mt_rand(1, 100);
                    $status = $r <= 6 ? 'FALTOU' : ($r <= 11 ? 'CANCELADA' : 'REALIZADA');
                    $primeira = stripos($info['nome'], 'Primeira consulta') !== false ? 1 : 0;
                    $stC->execute([$idPac, $idD, $idP, $dia, date('H:i:s', $h), date('H:i:s', $hf), $status, mt_rand(1, 100) <= 34 + ($ano - 2022) * 8 ? 'SITE' : 'SECRETARIA',
                                   $primeira, date('Y-m-d H:i:s', strtotime("$dia -" . mt_rand(2, 25) . " days 10:00"))]);
                    if ($status !== 'REALIZADA') continue;
                    $idC = (int) $pdo->lastInsertId();
                    $valor = round($info['valor'] * $reajuste[$ano] / 10) * 10;
                    $stF->execute([$idC, $idD, $valor, $formas[mt_rand(0, count($formas) - 1)], $dia]);
                    $feitos++;
                }
            }
        }
    }
}

// ---------------------------------------------------------------------
//  Agenda cheia (≈ 90%) nas próximas três semanas, com os pacientes novos
//  aparecendo bastante e algumas desmarcações para mostrar na agenda.
// ---------------------------------------------------------------------
function demo6_agenda(PDO $pdo, $procPorDentista, $procInfo, $novos, $ini, $fim) {
    $turnos = [];
    foreach ($pdo->query("SELECT id_dentista, dia_semana, hora_inicio, hora_fim FROM horario_atendimento ORDER BY hora_inicio")->fetchAll() as $h) {
        $turnos[$h['id_dentista']][(int) $h['dia_semana']][] = [hm_($h['hora_inicio']), hm_($h['hora_fim'])];
    }
    $comFoto = array_map('intval', $pdo->query("SELECT id_paciente FROM paciente WHERE foto IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN));
    $todos = array_map('intval', $pdo->query("SELECT id_paciente FROM paciente")->fetchAll(PDO::FETCH_COLUMN));
    if (!$todos || !$turnos) return;
    $ocup = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE id_dentista = ? AND data = ? AND status <> 'CANCELADA' AND hora_inicio < ? AND hora_fim > ?");
    $stC = $pdo->prepare("INSERT INTO consulta (id_paciente, id_dentista, id_procedimento, data, hora_inicio, hora_fim, status, origem, primeira_consulta, observacoes, demo) VALUES (?,?,?,?,?,?,?,?,?,?,1)");
    $obsLista = [null, null, null, null, null, 'Paciente pediu lembrete por WhatsApp.', 'Trazer exames anteriores.', 'Sensibilidade no dente 26.'];
    $agora = time();
    mt_srand(6602);
    for ($d = strtotime($ini); $d <= strtotime($fim); $d = strtotime('+1 day', $d)) {
        $dia = date('Y-m-d', $d);
        $dow = (int) date('w', $d);
        foreach ($turnos as $idD => $porDia) {
            foreach ($porDia[$dow] ?? [] as [$tIni, $tFim]) {
                if (empty($procPorDentista[$idD])) continue;
                $t = strtotime("$dia $tIni");
                $limite = strtotime("$dia $tFim");
                while (true) {
                    $idP = $procPorDentista[$idD][mt_rand(0, count($procPorDentista[$idD]) - 1)];
                    $info = $procInfo[$idD][$idP];
                    $fimC = $t + $info['dur'] * 60;
                    if ($fimC > $limite) {
                        // tenta um procedimento curto para fechar o turno
                        $t += 15 * 60;
                        if ($t + 30 * 60 > $limite) break;
                        continue;
                    }
                    $ocup->execute([$idD, $dia, date('H:i:s', $fimC), date('H:i:s', $t)]);
                    if ($ocup->fetchColumn()) { $t += 15 * 60; continue; }
                    if (mt_rand(1, 100) > 88) { $t = $fimC; continue; }       // ~12% de horários livres
                    $r = mt_rand(1, 100);
                    if ($novos && $r <= 22) $idPac = $novos[mt_rand(0, count($novos) - 1)];
                    elseif ($comFoto && $r <= 50) $idPac = $comFoto[mt_rand(0, count($comFoto) - 1)];
                    else $idPac = $todos[mt_rand(0, count($todos) - 1)];
                    $perto = $dia <= date('Y-m-d', strtotime('+3 days', $agora));
                    $status = $fimC < $agora ? 'AGENDADA' : (mt_rand(1, 100) <= ($perto ? 65 : 35) ? 'CONFIRMADA' : 'AGENDADA');
                    $primeira = stripos($info['nome'], 'Primeira consulta') !== false ? 1 : 0;
                    $stC->execute([$idPac, $idD, $idP, $dia, date('H:i:s', $t), date('H:i:s', $fimC), $status, mt_rand(1, 100) <= 55 ? 'SITE' : 'SECRETARIA',
                                   $primeira, $obsLista[mt_rand(0, count($obsLista) - 1)]]);
                    $t = $fimC;
                }
            }
        }
    }
    // consultas já confirmadas tiveram o lembrete enviado (passos 1 e 2 da tela de lembretes)
    $pdo->prepare("INSERT INTO lembrete (id_consulta, canal, data_envio, enviado)
                   SELECT c.id_consulta, 'WHATSAPP', ?, 1 FROM consulta c WHERE c.demo = 1 AND c.status = 'CONFIRMADA' AND c.data >= ?
                   AND NOT EXISTS (SELECT 1 FROM lembrete l WHERE l.id_consulta = c.id_consulta)")->execute([date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d')]);
    // algumas consultas desmarcadas pelo paciente (aparecem riscadas na agenda)
    $futuras = $pdo->prepare("SELECT id_consulta FROM consulta WHERE demo = 1 AND data BETWEEN ? AND ? AND status = 'AGENDADA' ORDER BY id_consulta");
    $futuras->execute([$ini, $fim]);
    $up = $pdo->prepare("UPDATE consulta SET status = 'CANCELADA', observacoes = 'Desmarcada pelo paciente por WhatsApp.' WHERE id_consulta = ?");
    foreach ($futuras->fetchAll(PDO::FETCH_COLUMN) as $i => $id) if ($i % 19 === 7) $up->execute([$id]);
}
