<?php
// =====================================================================
//  INSTALAÇÃO E ATUALIZAÇÃO AUTOMÁTICA DO BANCO (migrações)
//  - Banco novo: cria as tabelas da clínica e os dados de demonstração.
//  - Banco que já existe (o seu mysql-data): aplica só o que falta,
//    sem apagar pacientes, consultas ou lançamentos.
//  As tabelas antigas da barbearia (usuarios e agendamentos) não são tocadas.
// =====================================================================

const VERSAO_BANCO = 6;
require_once __DIR__ . '/migracao_6.php';

function tabela_existe(PDO $pdo, $tabela) {
    try { $pdo->query("SELECT 1 FROM $tabela LIMIT 1"); return true; }
    catch (PDOException $e) { return false; }
}

function coluna_existe(PDO $pdo, $tabela, $coluna) {
    try { $pdo->query("SELECT $coluna FROM $tabela LIMIT 1"); return true; }
    catch (PDOException $e) { return false; }
}

function add_coluna(PDO $pdo, $tabela, $coluna, $definicao) {
    if (!coluna_existe($pdo, $tabela, $coluna)) $pdo->exec("ALTER TABLE $tabela ADD COLUMN $coluna $definicao");
}

function sql_compat(PDO $pdo, $sql) {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') return $sql;
    $sql = str_replace('INT AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\)\s*ENGINE=.*$/s', ')', $sql);
    return $sql;
}

function versao_banco(PDO $pdo) {
    try { return (int) $pdo->query("SELECT MAX(versao) FROM sistema_versao")->fetchColumn(); }
    catch (PDOException $e) { return -1; }
}

function instalar_banco(PDO $pdo) {
    $v = versao_banco($pdo);
    if ($v >= VERSAO_BANCO) return;               // caminho rápido: já está atualizado
    @set_time_limit(300);                         // a migração 6 gera alguns milhares de registros de demonstração

    $motor = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    if ($v < 0) {
        $pdo->exec(sql_compat($pdo, "CREATE TABLE IF NOT EXISTS sistema_versao (
            versao INT NOT NULL PRIMARY KEY,
            aplicada_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )$motor"));
        $v = 0;
    }

    if ($v < 1) {
        criar_tabelas_base($pdo, $motor);
        if ((int) $pdo->query("SELECT COUNT(*) FROM dentista")->fetchColumn() === 0) {
            $pdo->beginTransaction();
            try { popular_dados_iniciais($pdo); $pdo->commit(); }
            catch (Throwable $t) { $pdo->rollBack(); throw $t; }
        }
        registrar_versao($pdo, 1);
    }
    if ($v < 2) {
        migracao_2($pdo, $motor);
        registrar_versao($pdo, 2);
    }
    if ($v < 3) {
        $pdo->beginTransaction();
        try { migracao_3($pdo); $pdo->commit(); }
        catch (Throwable $t) { $pdo->rollBack(); throw $t; }
        registrar_versao($pdo, 3);
    }
    if ($v < 4) {
        migracao_4_estrutura($pdo, $motor);
        $pdo->beginTransaction();
        try { migracao_4($pdo); $pdo->commit(); }
        catch (Throwable $t) { $pdo->rollBack(); throw $t; }
        registrar_versao($pdo, 4);
    }
    if ($v < 5) {
        migracao_5_estrutura($pdo, $motor);
        $pdo->beginTransaction();
        try { migracao_5($pdo); $pdo->commit(); }
        catch (Throwable $t) { $pdo->rollBack(); throw $t; }
        registrar_versao($pdo, 5);
    }
    if ($v < 6) {
        migracao_6_estrutura($pdo);
        $pdo->beginTransaction();
        try { migracao_6($pdo); $pdo->commit(); }
        catch (Throwable $t) { $pdo->rollBack(); throw $t; }
        registrar_versao($pdo, 6);
    }
}

function registrar_versao(PDO $pdo, $n) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM sistema_versao WHERE versao = ?");
    $st->execute([$n]);
    if (!$st->fetchColumn()) $pdo->prepare("INSERT INTO sistema_versao (versao) VALUES (?)")->execute([$n]);
}

// ---------------------------------------------------------------------
//  Versão 1: tabelas originais da clínica
// ---------------------------------------------------------------------
function criar_tabelas_base(PDO $pdo, $motor) {
    $tabelas = [
        "CREATE TABLE IF NOT EXISTS usuario (
            id_usuario INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            perfil VARCHAR(20) NOT NULL DEFAULT 'SECRETARIA',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )$motor",
        "CREATE TABLE IF NOT EXISTS paciente (
            id_paciente INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            telefone VARCHAR(20) NOT NULL,
            email VARCHAR(120) NULL,
            data_nascimento DATE NULL,
            observacoes TEXT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )$motor",
        "CREATE TABLE IF NOT EXISTS dentista (
            id_dentista INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            cro VARCHAR(20) NOT NULL,
            especialidade VARCHAR(80) NOT NULL,
            bio TEXT NULL,
            cor VARCHAR(7) NOT NULL DEFAULT '#12a3a0',
            ativo TINYINT NOT NULL DEFAULT 1
        )$motor",
        "CREATE TABLE IF NOT EXISTS horario_atendimento (
            id_horario INT AUTO_INCREMENT PRIMARY KEY,
            id_dentista INT NOT NULL,
            dia_semana TINYINT NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fim TIME NOT NULL,
            FOREIGN KEY (id_dentista) REFERENCES dentista(id_dentista) ON DELETE CASCADE
        )$motor",
        "CREATE TABLE IF NOT EXISTS procedimento (
            id_procedimento INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            descricao VARCHAR(255) NULL,
            duracao_min INT NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            icone VARCHAR(40) NOT NULL DEFAULT 'bi-stars'
        )$motor",
        "CREATE TABLE IF NOT EXISTS dentista_procedimento (
            id_dentista INT NOT NULL,
            id_procedimento INT NOT NULL,
            PRIMARY KEY (id_dentista, id_procedimento),
            FOREIGN KEY (id_dentista) REFERENCES dentista(id_dentista) ON DELETE CASCADE,
            FOREIGN KEY (id_procedimento) REFERENCES procedimento(id_procedimento) ON DELETE CASCADE
        )$motor",
        "CREATE TABLE IF NOT EXISTS consulta (
            id_consulta INT AUTO_INCREMENT PRIMARY KEY,
            id_paciente INT NOT NULL,
            id_dentista INT NOT NULL,
            id_procedimento INT NOT NULL,
            data DATE NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fim TIME NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'AGENDADA',
            origem VARCHAR(12) NOT NULL DEFAULT 'SITE',
            observacoes TEXT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_paciente) REFERENCES paciente(id_paciente),
            FOREIGN KEY (id_dentista) REFERENCES dentista(id_dentista),
            FOREIGN KEY (id_procedimento) REFERENCES procedimento(id_procedimento)
        )$motor",
        "CREATE TABLE IF NOT EXISTS registro_financeiro (
            id_financeiro INT AUTO_INCREMENT PRIMARY KEY,
            id_consulta INT NOT NULL UNIQUE,
            id_dentista INT NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            forma_pagamento VARCHAR(30) NOT NULL,
            data DATE NOT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_consulta) REFERENCES consulta(id_consulta) ON DELETE CASCADE,
            FOREIGN KEY (id_dentista) REFERENCES dentista(id_dentista)
        )$motor",
        "CREATE TABLE IF NOT EXISTS lembrete (
            id_lembrete INT AUTO_INCREMENT PRIMARY KEY,
            id_consulta INT NOT NULL,
            canal VARCHAR(12) NOT NULL DEFAULT 'WHATSAPP',
            data_envio DATETIME NOT NULL,
            enviado TINYINT NOT NULL DEFAULT 0,
            FOREIGN KEY (id_consulta) REFERENCES consulta(id_consulta) ON DELETE CASCADE
        )$motor",
    ];
    foreach ($tabelas as $sql) $pdo->exec(sql_compat($pdo, $sql));
}

// ---------------------------------------------------------------------
//  Versão 2 (SanyTech): logins de dentistas, endereço com CEP, prontuário,
//  avaliações, mensagens, primeira consulta, preços revistos, segurança
// ---------------------------------------------------------------------
function migracao_2(PDO $pdo, $motor) {
    // ----- novas colunas -----
    add_coluna($pdo, 'usuario', 'id_dentista', 'INT NULL');
    add_coluna($pdo, 'usuario', 'ultimo_acesso', 'DATETIME NULL');
    add_coluna($pdo, 'usuario', 'ativo', 'TINYINT NOT NULL DEFAULT 1');

    foreach (['cpf' => 'VARCHAR(14) NULL', 'cep' => 'VARCHAR(9) NULL', 'logradouro' => 'VARCHAR(150) NULL', 'numero' => 'VARCHAR(10) NULL',
              'complemento' => 'VARCHAR(60) NULL', 'bairro' => 'VARCHAR(80) NULL', 'cidade' => 'VARCHAR(80) NULL', 'uf' => 'CHAR(2) NULL'] as $c => $def) {
        add_coluna($pdo, 'paciente', $c, $def);
    }
    foreach (['foto' => 'VARCHAR(120) NULL', 'anos_experiencia' => 'INT NULL', 'formacao' => 'VARCHAR(255) NULL', 'publico' => 'VARCHAR(255) NULL',
              'condicoes' => 'TEXT NULL', 'atendimento' => "VARCHAR(40) NOT NULL DEFAULT 'Presencial'"] as $c => $def) {
        add_coluna($pdo, 'dentista', $c, $def);
    }
    add_coluna($pdo, 'consulta', 'primeira_consulta', 'TINYINT NOT NULL DEFAULT 0');

    // ----- novas tabelas -----
    $tabelas = [
        // Prontuário do dentista: fotos de cada sessão, exames e observações
        "CREATE TABLE IF NOT EXISTS prontuario (
            id_registro INT AUTO_INCREMENT PRIMARY KEY,
            id_paciente INT NOT NULL,
            id_dentista INT NOT NULL,
            id_consulta INT NULL,
            tipo VARCHAR(10) NOT NULL,
            titulo VARCHAR(150) NULL,
            texto TEXT NULL,
            arquivo VARCHAR(255) NULL,
            nome_original VARCHAR(255) NULL,
            mime VARCHAR(60) NULL,
            tamanho INT NULL,
            data_registro DATE NOT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_paciente) REFERENCES paciente(id_paciente) ON DELETE CASCADE,
            FOREIGN KEY (id_dentista) REFERENCES dentista(id_dentista)
        )$motor",
        "CREATE TABLE IF NOT EXISTS avaliacao (
            id_avaliacao INT AUTO_INCREMENT PRIMARY KEY,
            id_dentista INT NULL,
            nome VARCHAR(80) NOT NULL,
            nota TINYINT NOT NULL,
            comentario TEXT NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'PENDENTE',
            demo TINYINT NOT NULL DEFAULT 0,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )$motor",
        "CREATE TABLE IF NOT EXISTS mensagem_contato (
            id_mensagem INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            email VARCHAR(120) NOT NULL,
            telefone VARCHAR(20) NULL,
            assunto VARCHAR(120) NOT NULL,
            mensagem TEXT NOT NULL,
            lida TINYINT NOT NULL DEFAULT 0,
            email_enviado TINYINT NOT NULL DEFAULT 0,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )$motor",
        "CREATE TABLE IF NOT EXISTS limite_acao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            acao VARCHAR(30) NOT NULL,
            criado_em DATETIME NOT NULL
        )$motor",
    ];
    foreach ($tabelas as $sql) $pdo->exec(sql_compat($pdo, $sql));
    try { $pdo->exec("CREATE INDEX idx_limite ON limite_acao (ip, acao, criado_em)"); } catch (PDOException $e) {}
    try { $pdo->exec("CREATE INDEX idx_pront ON prontuario (id_paciente, data_registro)"); } catch (PDOException $e) {}

    $pdo->beginTransaction();
    try {
        dados_migracao_2($pdo);
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
}

function dados_migracao_2(PDO $pdo) {
    // ---------- Dentistas: bio completa, experiência, público, condições, foto ilustrada ----------
    $perfis = [
        [
            'nome' => 'Dra. Helena Duarte', 'foto' => 'helena.svg', 'anos' => 14, 'login' => 'helena.duarte',
            'formacao' => 'Graduação em Odontologia, especialização em Ortodontia e Ortopedia Facial e certificação em alinhadores invisíveis.',
            'bio' => 'Apaixonada por sorrisos alinhados, a Dra. Helena já acompanhou mais de 1.200 tratamentos ortodônticos. Trabalha com planejamento digital, aparelhos fixos estéticos e alinhadores transparentes, e explica cada etapa do tratamento para que o paciente acompanhe a evolução do próprio sorriso. Atende desde crianças em fase de crescimento até adultos que decidiram corrigir o sorriso depois dos 40.',
            'publico' => 'Crianças (a partir de 7 anos), adolescentes e adultos',
            'condicoes' => "Dentes tortos ou apinhados\nMordida cruzada e mordida aberta\nSobremordida e mordida profunda\nEspaços entre os dentes (diastema)\nBruxismo e dor na articulação (ATM)",
        ],
        [
            'nome' => 'Dr. Rafael Monteiro', 'foto' => 'rafael.svg', 'anos' => 18, 'login' => 'rafael.monteiro',
            'formacao' => 'Graduação em Odontologia, especialização em Implantodontia e mestrado em Cirurgia e Traumatologia Bucomaxilofacial.',
            'bio' => 'São 18 anos dedicados à reabilitação oral e mais de 2.500 implantes instalados. O Dr. Rafael usa tomografia e cirurgia guiada para procedimentos mais rápidos, previsíveis e confortáveis, com acompanhamento próximo no pós-operatório. Seu objetivo é devolver ao paciente a segurança de mastigar, falar e sorrir sem preocupação.',
            'publico' => 'Adultos e idosos (a partir de 18 anos)',
            'condicoes' => "Perda de um ou mais dentes\nPróteses soltas ou desconfortáveis\nDente do siso incluso ou inflamado\nDentes fraturados sem possibilidade de restauração\nPerda óssea na arcada",
        ],
        [
            'nome' => 'Dra. Camila Nogueira', 'foto' => 'camila.svg', 'anos' => 10, 'login' => 'camila.nogueira',
            'formacao' => 'Graduação em Odontologia, especialização em Dentística Restauradora e aperfeiçoamento em Estética do Sorriso.',
            'bio' => 'A Dra. Camila une técnica e olhar artístico para criar sorrisos naturais. Há 10 anos se dedica a clareamentos, restaurações estéticas em resina e prevenção, com um atendimento calmo e acolhedor — ideal para quem tem medo de dentista. Cuida de toda a família nas consultas de limpeza e check-up.',
            'publico' => 'Todas as idades: crianças, adultos e idosos',
            'condicoes' => "Cáries e restaurações antigas\nDentes escurecidos ou manchados\nSensibilidade ao frio e ao doce\nGengivite e tártaro\nDentes desgastados ou lascados",
        ],
    ];
    $ids = array_column($pdo->query("SELECT id_dentista, nome FROM dentista ORDER BY id_dentista")->fetchAll(), 'id_dentista');
    $up = $pdo->prepare("UPDATE dentista SET bio = ?, foto = ?, anos_experiencia = ?, formacao = ?, publico = ?, condicoes = ?, atendimento = 'Presencial' WHERE id_dentista = ?");
    foreach ($perfis as $i => $p) {
        if (!isset($ids[$i])) break;
        $up->execute([$p['bio'], $p['foto'], $p['anos'], $p['formacao'], $p['publico'], $p['condicoes'], $ids[$i]]);
    }

    // ---------- Logins com senha forte (somente o hash fica no código) ----------
    $dominio = 'garciadimase.example';   // domínio FICTÍCIO (.example é reservado e nunca existe)
    $hashSec = '$2y$10$BnVTzSEGdHIlEIT3iJOc5OPp8HEGbxfCsTCBWpwC9CtUC/8qJkG1u';
    $hashes = [
        '$2y$10$XCojnP.SjvxPaDdbvdGlLeCCSF9XKwQC5JIBuFQGP214pcM8IHvTu',
        '$2y$10$kh2WD1U79D8uDyGGvjAlq.6KWd9O86Z7VIkJthJMiasQHIrDx4A/S',
        '$2y$10$eVT9VCgcGFsI7JgbWgomGujOA/2HwKglfo.hr80a4X0kdPpvkBVcq',
    ];
    // O login antigo é substituído pelo endereço de e-mail da clínica.
    $existe = $pdo->prepare("SELECT id_usuario FROM usuario WHERE email = ?");
    $existe->execute(['secretaria']);
    if ($idSec = $existe->fetchColumn()) {
        $pdo->prepare("UPDATE usuario SET email = ?, senha = ?, perfil = 'SECRETARIA' WHERE id_usuario = ?")
            ->execute(["secretaria@$dominio", $hashSec, $idSec]);
    } else {
        $existe->execute(["secretaria@$dominio"]);
        if (!$existe->fetchColumn()) {
            $pdo->prepare("INSERT INTO usuario (nome, email, senha, perfil) VALUES ('Juliana Prado', ?, ?, 'SECRETARIA')")->execute(["secretaria@$dominio", $hashSec]);
        }
    }
    $nomes = $pdo->query("SELECT id_dentista, nome FROM dentista ORDER BY id_dentista")->fetchAll();
    foreach ($perfis as $i => $p) {
        if (!isset($nomes[$i])) break;
        $email = $p['login'] . '@' . $dominio;
        $existe->execute([$email]);
        if (!$existe->fetchColumn()) {
            $pdo->prepare("INSERT INTO usuario (nome, email, senha, perfil, id_dentista) VALUES (?,?,?, 'DENTISTA', ?)")
                ->execute([$nomes[$i]['nome'], $email, $hashes[$i], $nomes[$i]['id_dentista']]);
        }
    }

    // ---------- Preços revistos (referência: Doctoralia, região de Araras – nov/2026) ----------
    $precos = [
        // nome atual => [novo nome, valor antigo, valor novo, nova descrição|null]
        'Avaliação odontológica'             => ['Primeira consulta / Avaliação', 120, 100, 'Primeira consulta: exame clínico completo, orientações e plano de tratamento.'],
        'Limpeza e profilaxia'               => [null, 180, 150, null],
        'Clareamento dental (sessão)'        => [null, 450, 400, null],
        'Restauração em resina'              => [null, 250, 150, null],
        'Manutenção de aparelho'             => [null, 150, 100, null],
        'Instalação de aparelho ortodôntico' => [null, 900, 450, null],
        'Planejamento de alinhadores'        => [null, 600, 300, null],
        'Avaliação para implante'            => [null, 200, 100, null],
        'Cirurgia de implante'               => [null, 2800, 2100, null],
        'Extração simples'                   => [null, 300, 180, null],
    ];
    $busca = $pdo->prepare("SELECT id_procedimento FROM procedimento WHERE nome = ?");
    foreach ($precos as $nome => [$novoNome, $antigo, $novo, $desc]) {
        $busca->execute([$nome]);
        $idP = $busca->fetchColumn();
        if (!$idP) continue;
        $pdo->prepare("UPDATE procedimento SET nome = ?, valor = ?, descricao = COALESCE(?, descricao) WHERE id_procedimento = ?")
            ->execute([$novoNome ?? $nome, $novo, $desc, $idP]);
        // lançamentos de DEMONSTRAÇÃO que usavam a tabela antiga passam para o novo preço
        $pdo->prepare("UPDATE registro_financeiro SET valor = ? WHERE valor = ? AND id_consulta IN (SELECT id_consulta FROM consulta WHERE id_procedimento = ?)")
            ->execute([$novo, $antigo, $idP]);
    }
    // consultas de "Primeira consulta" ficam marcadas como tal
    $busca->execute(['Primeira consulta / Avaliação']);
    if ($idPrim = $busca->fetchColumn()) {
        $pdo->prepare("UPDATE consulta SET primeira_consulta = 1 WHERE id_procedimento = ?")->execute([$idPrim]);
    }

    // ---------- Avaliações de DEMONSTRAÇÃO (marcadas com demo = 1; a secretaria remove com 1 clique) ----------
    if ((int) $pdo->query("SELECT COUNT(*) FROM avaliacao")->fetchColumn() === 0 && count($ids) >= 3) {
        $av = [
            [$ids[0], 'Mariana C.', 5, 'A Dra. Helena explicou cada etapa do aparelho da minha filha. Atendimento pontual e muito carinhoso.'],
            [$ids[1], 'José R.',    5, 'Fiz dois implantes com o Dr. Rafael. Pós-operatório tranquilo e resultado excelente.'],
            [$ids[2], 'Patrícia L.',5, 'Tinha pavor de dentista e a Dra. Camila me deixou super à vontade. Amei o clareamento!'],
            [$ids[0], 'Lucas M.',   4, 'Ótima ortodontista. Só precisei esperar um pouco na recepção no dia da manutenção.'],
            [$ids[2], 'Renata F.',  5, 'Restauração ficou idêntica ao dente natural. Recomendo demais.'],
            [null,    'Carlos A.',  4, 'Clínica limpa, moderna e com agendamento online muito prático.'],
        ];
        $st = $pdo->prepare("INSERT INTO avaliacao (id_dentista, nome, nota, comentario, status, demo, criado_em) VALUES (?,?,?,?, 'APROVADA', 1, ?)");
        foreach ($av as $i => $a) $st->execute([$a[0], $a[1], $a[2], $a[3], date('Y-m-d H:i:s', strtotime('-' . (3 + $i * 6) . ' days'))]);
    }

    // ---------- Agenda de demonstração para os próximos dias (só se estiver vazia) ----------
    $st = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE data >= ?");
    $st->execute([date('Y-m-d')]);
    if ((int) $st->fetchColumn() < 15) gerar_consultas_demo($pdo, 1, 14, 2027);

    // ---------- Algumas observações de prontuário de exemplo ----------
    if ((int) $pdo->query("SELECT COUNT(*) FROM prontuario")->fetchColumn() === 0) {
        $ex = $pdo->query("SELECT id_paciente, id_dentista, id_consulta, data FROM consulta WHERE status = 'REALIZADA' ORDER BY data DESC LIMIT 6")->fetchAll();
        $notas = ['Paciente relata sensibilidade leve ao frio. Orientado uso de creme dental para sensibilidade.',
                  'Boa higiene oral. Retorno em 6 meses para nova profilaxia.',
                  'Ajuste realizado sem intercorrências. Próxima manutenção em 30 dias.'];
        $ins = $pdo->prepare("INSERT INTO prontuario (id_paciente, id_dentista, id_consulta, tipo, titulo, texto, data_registro) VALUES (?,?,?, 'NOTA', 'Evolução da sessão', ?, ?)");
        foreach ($ex as $i => $c) $ins->execute([$c['id_paciente'], $c['id_dentista'], $c['id_consulta'], $notas[$i % 3], $c['data']]);
    }
}

// ---------------------------------------------------------------------
//  Versão 3: dados de contato 100% fictícios, especialidades com nomes
//  técnicos, notas maiores nas avaliações de exemplo e
//  "Avaliação / Primeira consulta" como primeiro tratamento da lista.
//  Só mexe nos dados de DEMONSTRAÇÃO (não altera pacientes que você cadastrou).
// ---------------------------------------------------------------------
function migracao_3(PDO $pdo) {
    // ----- logins: troca o domínio para um domínio fictício (as senhas continuam as mesmas) -----
    $st = $pdo->query("SELECT id_usuario, email FROM usuario WHERE email LIKE '%@garciadimase.com.br'");
    $up = $pdo->prepare("UPDATE usuario SET email = ? WHERE id_usuario = ?");
    foreach ($st->fetchAll() as $u) {
        $up->execute([str_replace('@garciadimase.com.br', '@garciadimase.example', $u['email']), $u['id_usuario']]);
    }

    // ----- pacientes de demonstração: telefones e e-mails fictícios -----
    $antigos = ['(19) 99812-3401', '(19) 99745-1122', '(19) 98833-5567', '(19) 99102-7788', '(19) 99654-2210', '(19) 98120-4455',
                '(19) 99231-9087', '(19) 99987-6543', '(19) 98456-1234', '(19) 99333-4141', '(19) 99876-0021', '(19) 98765-3030'];
    $upTel = $pdo->prepare("UPDATE paciente SET telefone = ? WHERE telefone = ?");
    foreach ($antigos as $i => $tel) $upTel->execute(['(19) 90000-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), $tel]);
    $st = $pdo->query("SELECT id_paciente, email FROM paciente WHERE email LIKE '%@email.com'");
    $upMail = $pdo->prepare("UPDATE paciente SET email = ? WHERE id_paciente = ?");
    foreach ($st->fetchAll() as $p) $upMail->execute([str_replace('@email.com', '@paciente.example', $p['email']), $p['id_paciente']]);

    // ----- dentistas: CRO fictício, especialidade e tratamentos com nomes técnicos -----
    $perfis = [
        'CRO-SP 84.512' => ['CRO-SP 00.001', 'Ortodontia e Ortopedia Facial',
            'Graduação em Odontologia, especialização em Ortodontia e Ortopedia Facial dos Maxilares e certificação em alinhadores invisíveis.',
            "Alinhadores invisíveis\nAparelho fixo estético (safira e cerâmica)\nCorreção de má oclusão e apinhamento\nMordida cruzada, aberta e sobremordida profunda\nFechamento de diastemas\nDisfunção temporomandibular (DTM) e bruxismo"],
        'CRO-SP 91.237' => ['CRO-SP 00.002', 'Implantodontia e Cirurgia Oral',
            'Graduação em Odontologia, especialização em Implantodontia e mestrado em Cirurgia e Traumatologia Bucomaxilofacial.',
            "Implantes dentários unitários e múltiplos\nProtocolo All-on-4 (prótese fixa sobre implantes)\nEnxerto ósseo e levantamento de seio maxilar\nCirurgia guiada por tomografia\nExtração de sisos inclusos\nCarga imediata"],
        'CRO-SP 77.904' => ['CRO-SP 00.003', 'Estética e Harmonização Orofacial',
            'Graduação em Odontologia, especialização em Dentística Restauradora e em Harmonização Orofacial.',
            "Clareamento dental\nLentes de contato e facetas\nHarmonização orofacial\nToxina botulínica (botox)\nPreenchimento com ácido hialurônico\nRestaurações estéticas em resina"],
    ];
    $upD = $pdo->prepare("UPDATE dentista SET cro = ?, especialidade = ?, formacao = ?, condicoes = ? WHERE cro = ? OR cro = ?");
    foreach ($perfis as $croAntigo => [$croNovo, $esp, $form, $cond]) {
        $upD->execute([$croNovo, $esp, $form, $cond, $croAntigo, $croNovo]);
    }
    $pdo->prepare("UPDATE dentista SET bio = ? WHERE cro = 'CRO-SP 00.003'")->execute([
        'A Dra. Camila une técnica e olhar artístico para criar sorrisos naturais. Há 10 anos se dedica a clareamentos, lentes de contato, '
        . 'restaurações estéticas e harmonização orofacial (toxina botulínica e preenchimento com ácido hialurônico), sempre buscando um resultado '
        . 'equilibrado e natural, com atendimento calmo e acolhedor.']);

    // ----- primeira consulta vira "Avaliação / Primeira consulta" -----
    $pdo->prepare("UPDATE procedimento SET nome = 'Avaliação / Primeira consulta' WHERE nome = 'Primeira consulta / Avaliação'")->execute();

    // ----- avaliações de exemplo: notas um pouco maiores e mais opiniões (continuam marcadas como "exemplo") -----
    $temDemo = (int) $pdo->query("SELECT COUNT(*) FROM avaliacao WHERE demo = 1")->fetchColumn();
    if ($temDemo) {
        $pdo->exec("UPDATE avaliacao SET nota = 5 WHERE demo = 1 AND nome IN ('Lucas M.', 'Carlos A.')");
        $ids = array_column($pdo->query("SELECT id_dentista FROM dentista ORDER BY id_dentista")->fetchAll(), 'id_dentista');
        if (count($ids) >= 3) {
            $novas = [
                [$ids[0], 'Fernanda T.', 5, 'Uso alinhadores com a Dra. Helena há 8 meses e o resultado já é visível. Tudo explicado com muita paciência.'],
                [$ids[0], 'Gustavo P.',  4, 'Atendimento excelente e consultório muito organizado. Recomendo para quem quer corrigir a mordida.'],
                [$ids[1], 'Sônia M.',    5, 'Fiz protocolo com o Dr. Rafael aos 67 anos. Voltei a comer de tudo e a sorrir sem vergonha.'],
                [$ids[1], 'André L.',    5, 'Cirurgia guiada rápida e sem dor. Ele acompanhou cada passo da recuperação.'],
                [$ids[2], 'Beatriz N.',  5, 'Fiz clareamento e lentes com a Dra. Camila. Ficou natural, exatamente como eu queria.'],
                [$ids[2], 'Cláudia R.',  5, 'Harmonização muito sutil e bonita. Profissional cuidadosa e super atenciosa.'],
            ];
            $st = $pdo->prepare("INSERT INTO avaliacao (id_dentista, nome, nota, comentario, status, demo, criado_em) VALUES (?,?,?,?, 'APROVADA', 1, ?)");
            foreach ($novas as $i => $a) $st->execute([$a[0], $a[1], $a[2], $a[3], date('Y-m-d H:i:s', strtotime('-' . (1 + $i * 4) . ' days'))]);
        }
    }
}

// ---------------------------------------------------------------------
//  Versão 4 (apresentação de 24/11/2026):
//  - rate limiting (tabela de requisições por minuto)
//  - fotos reais dos dentistas (banco de imagens) e de pacientes antigos
//  - pacientes antigos com cadastro completo e histórico desde 2019
//  - agenda de NOVEMBRO completa até o dia 30, concentrada na semana de 23 a 27/11
//  Só mexe em dados de DEMONSTRAÇÃO; nada que você cadastrou é apagado.
// ---------------------------------------------------------------------
function migracao_4_estrutura(PDO $pdo, $motor) {
    $pdo->exec(sql_compat($pdo, "CREATE TABLE IF NOT EXISTS limite_requisicao (
        ip VARCHAR(45) NOT NULL,
        chave VARCHAR(20) NOT NULL,
        janela INT NOT NULL,
        total INT NOT NULL DEFAULT 1,
        PRIMARY KEY (ip, chave, janela)
    )$motor"));
    add_coluna($pdo, 'paciente', 'foto', 'VARCHAR(255) NULL');
    add_coluna($pdo, 'consulta', 'demo', 'TINYINT NOT NULL DEFAULT 0');
}

// CPF fictício com dígitos verificadores válidos (gerado a partir de uma semente fixa)
function cpf_ficticio($semente) {
    mt_srand($semente);
    $n = [];
    for ($i = 0; $i < 9; $i++) $n[] = mt_rand(0, 9);
    for ($t = 9; $t < 11; $t++) {
        $s = 0;
        for ($i = 0; $i < $t; $i++) $s += $n[$i] * (($t + 1) - $i);
        $n[] = ((10 * $s) % 11) % 10;
    }
    $c = implode('', $n);
    return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9);
}

function migracao_4(PDO $pdo) {
    // ----- dentistas: fotos de pessoas reais (banco de imagens) e perfis -----
    // Helena: mulher negra · Rafael: rapaz · Camila: moça (só troca se ainda estiver com a ilustração padrão)
    $perfis = [
        'CRO-SP 00.001' => ['helena.svg', 'pexels:19596247', 14, null],
        'CRO-SP 00.002' => ['rafael.svg', 'pexels:37458054', 9,
            'Jovem especialista em reabilitação oral, o Dr. Rafael já instalou mais de 900 implantes. Usa tomografia e cirurgia guiada para procedimentos mais rápidos, previsíveis e confortáveis, com acompanhamento próximo no pós-operatório. Seu objetivo é devolver ao paciente a segurança de mastigar, falar e sorrir sem preocupação.'],
        'CRO-SP 00.003' => ['camila.svg', 'pexels:31043313', 7,
            'A Dra. Camila une técnica e olhar artístico para criar sorrisos naturais. Há 7 anos se dedica a clareamentos, lentes de contato, restaurações estéticas e harmonização orofacial (toxina botulínica e preenchimento com ácido hialurônico), sempre buscando um resultado equilibrado e natural, com atendimento calmo e acolhedor.'],
    ];
    foreach ($perfis as $cro => [$svg, $foto, $anos, $bio]) {
        $pdo->prepare("UPDATE dentista SET foto = ? WHERE cro = ? AND (foto IS NULL OR foto = '' OR foto = ?)")->execute([$foto, $cro, $svg]);
        $pdo->prepare("UPDATE dentista SET anos_experiencia = ? WHERE cro = ?")->execute([$anos, $cro]);
        if ($bio) $pdo->prepare("UPDATE dentista SET bio = ? WHERE cro = ?")->execute([$bio, $cro]);
    }

    // ----- pacientes antigos: cadastro completo + foto de perfil + "paciente desde" -----
    // [nome, foto, nascimento, desde, rua, nº, bairro, observação]
    $antigos = [
        ['Ana Beatriz Lima',      'unsplash:1758600433495-99b9e69cf5f6', '1994-03-12', '2019-02-18', 'Rua Fictícia das Acácias', '120', 'Jardim Exemplo', 'Prefere horários no início da manhã.'],
        ['Carla Mendes',          'unsplash:1758874384842-7e79ce77ed1a', '1988-07-25', '2019-08-05', 'Avenida Modelo dos Ipês', '455', 'Centro', null],
        ['Isabela Martins',       'unsplash:1758599543111-36ce5c34fceb', '1991-11-02', '2020-03-09', 'Rua Imaginária das Palmeiras', '78', 'Vila Demonstração', 'Alergia a dipirona.'],
        ['Eduarda Rocha',         'unsplash:1494790108377-be9c29b29330', '1999-05-30', '2020-10-14', 'Rua Fictícia dos Girassóis', '32', 'Jardim Exemplo', null],
        ['Gabriela Costa',        'pexels:774909',                      '1985-01-17', '2021-01-26', 'Rua Exemplo das Orquídeas', '901', 'Parque Teste', 'Avisar sempre por WhatsApp.'],
        ['Bruno Henrique Alves',  'pexels:220453',                      '1983-09-08', '2019-05-13', 'Avenida Fictícia Brasil', '1500', 'Centro', null],
        ['Henrique Barros',       'unsplash:1507003211169-0a1dd7228f2d', '1979-12-21', '2019-11-04', 'Rua Modelo dos Jequitibás', '64', 'Vila Demonstração', 'Hipertenso controlado — confirmar medicação.'],
        ['Marcos Vinícius Souza', 'pexels:614810',                      '1990-04-03', '2021-06-21', 'Rua Imaginária das Mangueiras', '210', 'Parque Teste', null],
    ];
    $busca = $pdo->prepare("SELECT * FROM paciente WHERE nome = ?");
    $up = $pdo->prepare("UPDATE paciente SET foto = COALESCE(foto, ?), data_nascimento = COALESCE(data_nascimento, ?), cpf = COALESCE(cpf, ?),
                         cep = COALESCE(cep, ?), logradouro = COALESCE(logradouro, ?), numero = COALESCE(numero, ?), bairro = COALESCE(bairro, ?),
                         cidade = COALESCE(cidade, 'Araras'), uf = COALESCE(uf, 'SP'), observacoes = COALESCE(observacoes, ?), criado_em = ?
                         WHERE id_paciente = ?");
    $idsAntigos = [];
    foreach ($antigos as $i => [$nome, $foto, $nasc, $desde, $rua, $num, $bairro, $obs]) {
        $busca->execute([$nome]);
        $p = $busca->fetch();
        if (!$p) continue;
        // só pacientes de demonstração (telefone fictício 90000-xxxx)
        if (strpos((string) $p['telefone'], '90000-') === false) continue;
        $cpf = cpf_ficticio(4100 + $i);
        $dupl = $pdo->prepare("SELECT COUNT(*) FROM paciente WHERE cpf = ? AND id_paciente <> ?");
        $dupl->execute([$cpf, $p['id_paciente']]);
        if ($dupl->fetchColumn()) $cpf = null;
        $up->execute([$foto, $nasc, $cpf, '00000-' . str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT), $rua, $num, $bairro, $obs, $desde . ' 09:00:00', $p['id_paciente']]);
        $idsAntigos[] = (int) $p['id_paciente'];
    }

    $dent = $pdo->query("SELECT id_dentista FROM dentista WHERE ativo = 1 ORDER BY id_dentista")->fetchAll(PDO::FETCH_COLUMN);
    if (!$dent) return;
    $procPorDentista = []; $procInfo = [];
    foreach ($pdo->query("SELECT dp.id_dentista, p.id_procedimento, p.duracao_min, p.valor, p.nome FROM dentista_procedimento dp JOIN procedimento p ON p.id_procedimento = dp.id_procedimento")->fetchAll() as $r) {
        $procPorDentista[$r['id_dentista']][] = (int) $r['id_procedimento'];
        $procInfo[$r['id_procedimento']] = ['dur' => (int) $r['duracao_min'], 'valor' => (float) $r['valor'], 'nome' => $r['nome']];
    }

    // ----- histórico dos pacientes antigos (consultas realizadas desde que chegaram) -----
    mt_srand(2419);
    $stC = $pdo->prepare("INSERT INTO consulta (id_paciente, id_dentista, id_procedimento, data, hora_inicio, hora_fim, status, origem, demo) VALUES (?,?,?,?,?,?,?,?,1)");
    $stF = $pdo->prepare("INSERT INTO registro_financeiro (id_consulta, id_dentista, valor, forma_pagamento, data) VALUES (?,?,?,?,?)");
    $stN = $pdo->prepare("INSERT INTO prontuario (id_paciente, id_dentista, id_consulta, tipo, titulo, texto, data_registro) VALUES (?,?,?, 'NOTA', ?, ?, ?)");
    $formas = ['PIX', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Dinheiro'];
    $notas = [
        'Boa higiene oral. Retorno em 6 meses para nova profilaxia.',
        'Paciente relata sensibilidade leve ao frio. Orientado creme dental para sensibilidade.',
        'Procedimento sem intercorrências. Paciente tranquilo durante toda a sessão.',
        'Revisão completa: gengiva saudável, sem novas lesões de cárie.',
        'Ajuste realizado. Próximo retorno conforme plano de tratamento.',
    ];
    $jaTem = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE id_paciente = ? AND data < '2026-01-01'");
    foreach ($idsAntigos as $k => $idPac) {
        $jaTem->execute([$idPac]);
        if ($jaTem->fetchColumn()) continue;
        $st = $pdo->prepare("SELECT criado_em FROM paciente WHERE id_paciente = ?");
        $st->execute([$idPac]);
        $t = strtotime(substr((string) $st->fetchColumn(), 0, 10) ?: '2020-01-01');
        $idD = $dent[$k % count($dent)];
        while ($t < strtotime('2026-10-01')) {
            if ((int) date('N', $t) > 5) $t = strtotime('next monday', $t);
            $lista = $procPorDentista[$idD] ?? [];
            if (!$lista) break;
            $idP = $lista[mt_rand(0, count($lista) - 1)];
            $ini = mktime(mt_rand(9, 15), [0, 15, 30, 45][mt_rand(0, 3)], 0, (int) date('n', $t), (int) date('j', $t), (int) date('Y', $t));
            $dia = date('Y-m-d', $ini);
            $stC->execute([$idPac, $idD, $idP, $dia, date('H:i:s', $ini), date('H:i:s', $ini + $procInfo[$idP]['dur'] * 60), 'REALIZADA', 'SECRETARIA']);
            $idC = (int) $pdo->lastInsertId();
            $stF->execute([$idC, $idD, $procInfo[$idP]['valor'], $formas[mt_rand(0, count($formas) - 1)], $dia]);
            if (mt_rand(1, 100) <= 60) $stN->execute([$idPac, $idD, $idC, 'Evolução da sessão', $notas[mt_rand(0, count($notas) - 1)], $dia]);
            $t = strtotime('+' . mt_rand(4, 8) . ' months', $t);
            if (mt_rand(1, 100) <= 25) $idD = $dent[mt_rand(0, count($dent) - 1)];   // às vezes passa por outro especialista
        }
    }

    // ----- agenda de NOVEMBRO/2026 até o dia 30 (mais cheia na semana da apresentação) -----
    gerar_novembro_demo($pdo, $procPorDentista, $procInfo, $idsAntigos);

    // ----- algumas avaliações pendentes e mensagens novas para mostrar na apresentação -----
    $temPend = (int) $pdo->query("SELECT COUNT(*) FROM avaliacao WHERE status = 'PENDENTE'")->fetchColumn();
    if (!$temPend && count($dent) >= 3) {
        $st = $pdo->prepare("INSERT INTO avaliacao (id_dentista, nome, nota, comentario, status, demo, criado_em) VALUES (?,?,?,?, 'PENDENTE', 1, ?)");
        $st->execute([$dent[1], 'Henrique B.', 5, 'Faço acompanhamento com o Dr. Rafael há anos. Implante perfeito e equipe muito atenciosa.', date('Y-m-d H:i:s', strtotime('-2 hours'))]);
        $st->execute([$dent[0], 'Ana Beatriz L.', 5, 'Sou paciente da Dra. Helena desde 2019. Agendar pelo site ficou muito mais fácil!', date('Y-m-d H:i:s', strtotime('-1 day'))]);
    }
    if (!(int) $pdo->query("SELECT COUNT(*) FROM mensagem_contato WHERE lida = 0")->fetchColumn()) {
        $st = $pdo->prepare("INSERT INTO mensagem_contato (nome, email, telefone, assunto, mensagem, criado_em) VALUES (?,?,?,?,?,?)");
        $st->execute(['Paula Ribeiro', 'paula@paciente.example', '(19) 90000-0101', 'Valores e pagamento', 'Gostaria de saber se o clareamento pode ser parcelado no cartão.', date('Y-m-d H:i:s', strtotime('-3 hours'))]);
        $st->execute(['Rodrigo Nunes', 'rodrigo@paciente.example', null, 'Dúvida sobre tratamento', 'Vocês atendem crianças de 8 anos para avaliação de aparelho?', date('Y-m-d H:i:s', strtotime('-26 hours'))]);
    }
}

// ---------------------------------------------------------------------
//  Versão 5: área técnica SanyTech (login TI + métricas de velocidade),
//  odontograma dos pacientes, fotos/perfis novos dos dentistas e
//  agenda de demonstração até a 2ª semana de dezembro (11/12/2026).
// ---------------------------------------------------------------------
function migracao_5_estrutura(PDO $pdo, $motor) {
    $pdo->exec(sql_compat($pdo, "CREATE TABLE IF NOT EXISTS metrica_requisicao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pagina VARCHAR(60) NOT NULL,
        metodo VARCHAR(6) NOT NULL,
        status SMALLINT NOT NULL,
        ms INT NOT NULL,
        criado_em DATETIME NOT NULL
    )$motor"));
    try { $pdo->exec("CREATE INDEX idx_metrica ON metrica_requisicao (criado_em)"); } catch (PDOException $e) {}
    $pdo->exec(sql_compat($pdo, "CREATE TABLE IF NOT EXISTS odontograma (
        id_paciente INT NOT NULL,
        dente TINYINT NOT NULL,
        situacao VARCHAR(12) NOT NULL,
        observacao VARCHAR(200) NULL,
        id_dentista INT NULL,
        atualizado_em DATETIME NOT NULL,
        PRIMARY KEY (id_paciente, dente)
    )$motor"));
}

function migracao_5(PDO $pdo) {
    // ----- login da equipe técnica SanyTech (só o hash fica no código; a senha foi entregue à equipe) -----
    $st = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = ?");
    $st->execute(['ti@sanytech.example']);
    if (!$st->fetchColumn()) {
        $pdo->prepare("INSERT INTO usuario (nome, email, senha, perfil) VALUES ('Equipe SanyTech', 'ti@sanytech.example', ?, 'TI')")
            ->execute(['$2y$10$FoljCOgHdZ5WK3WuwiiaSu6BvFX0Aa/yhNrzo60.z/K34lvc36pAS']);
    }

    // ----- dentistas: formação em universidades públicas, Rafael com 20 anos de experiência -----
    $dados = [
        'CRO-SP 00.001' => [null, 'Graduação em Odontologia pela USP (Faculdade de Odontologia de Bauru), especialização em Ortodontia e Ortopedia Facial pela Unicamp (FOP – Piracicaba) e mestrado em Ortodontia pela Unesp (Araraquara).', null],
        'CRO-SP 00.002' => [20, 'Graduação em Odontologia pela Unesp (Araraquara), especialização em Implantodontia pela USP (Ribeirão Preto), mestrado e doutorado em Cirurgia e Traumatologia Bucomaxilofacial pela Unicamp (FOP – Piracicaba).',
            'São 20 anos dedicados à reabilitação oral e mais de 2.500 implantes instalados. O Dr. Rafael usa tomografia e cirurgia guiada para procedimentos mais rápidos, previsíveis e confortáveis, com acompanhamento próximo no pós-operatório. Seu objetivo é devolver ao paciente a segurança de mastigar, falar e sorrir sem preocupação.'],
        'CRO-SP 00.003' => [null, 'Graduação em Odontologia pela Unicamp (FOP – Piracicaba), especialização em Dentística Restauradora pela USP (São Paulo) e aperfeiçoamento em Harmonização Orofacial pela Unesp (São José dos Campos).', null],
    ];
    foreach ($dados as $cro => [$anos, $form, $bio]) {
        $pdo->prepare("UPDATE dentista SET formacao = ? WHERE cro = ?")->execute([$form, $cro]);
        if ($anos) $pdo->prepare("UPDATE dentista SET anos_experiencia = ? WHERE cro = ?")->execute([$anos, $cro]);
        if ($bio) $pdo->prepare("UPDATE dentista SET bio = ? WHERE cro = ?")->execute([$bio, $cro]);
    }

    // ----- agenda de demonstração até a 2ª semana de dezembro -----
    $procPorDentista = []; $procInfo = [];
    foreach ($pdo->query("SELECT dp.id_dentista, p.id_procedimento, p.duracao_min, p.valor, p.nome FROM dentista_procedimento dp JOIN procedimento p ON p.id_procedimento = dp.id_procedimento")->fetchAll() as $r) {
        $procPorDentista[$r['id_dentista']][] = (int) $r['id_procedimento'];
        $procInfo[$r['id_procedimento']] = ['dur' => (int) $r['duracao_min'], 'valor' => (float) $r['valor'], 'nome' => $r['nome']];
    }
    $antigos = array_map('intval', $pdo->query("SELECT id_paciente FROM paciente WHERE foto IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN));
    if ($procPorDentista) gerar_novembro_demo($pdo, $procPorDentista, $procInfo, $antigos, '2026-12-01', '2026-12-11', 1212);

    // ----- odontograma de exemplo dos pacientes antigos -----
    $ins = $pdo->prepare("INSERT INTO odontograma (id_paciente, dente, situacao, observacao, id_dentista, atualizado_em) VALUES (?,?,?,?,?,?)");
    $tem = $pdo->prepare("SELECT COUNT(*) FROM odontograma WHERE id_paciente = ?");
    $dentes = array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48));
    $sit = ['RESTAURADO', 'RESTAURADO', 'RESTAURADO', 'CARIE', 'CANAL', 'COROA', 'IMPLANTE', 'AUSENTE', 'APARELHO'];
    $dentId = array_map('intval', $pdo->query("SELECT id_dentista FROM dentista ORDER BY id_dentista")->fetchAll(PDO::FETCH_COLUMN));
    mt_srand(5050);
    foreach ($antigos as $k => $idPac) {
        $tem->execute([$idPac]);
        if ($tem->fetchColumn()) continue;
        $usados = [];
        $n = mt_rand(4, 8);
        for ($i = 0; $i < $n; $i++) {
            $d = $dentes[mt_rand(0, count($dentes) - 1)];
            if (isset($usados[$d])) continue;
            $usados[$d] = 1;
            $s = $sit[mt_rand(0, count($sit) - 1)];
            if ($s === 'APARELHO' && $k % 3) $s = 'RESTAURADO';
            $ins->execute([$idPac, $d, $s, null, $dentId[$k % max(1, count($dentId))] ?? null, date('Y-m-d H:i:s', strtotime('-' . mt_rand(10, 600) . ' days'))]);
        }
    }
}

// Consultas de demonstração em todos os dias úteis de novembro/2026.
// Semana de 23 a 27/11 (apresentação no dia 24) fica com a agenda quase cheia.
function gerar_novembro_demo(PDO $pdo, $procPorDentista, $procInfo, $idsAntigos, $periodoIni = '2026-11-01', $periodoFim = '2026-11-30', $semente = 2411) {
    $turnos = [];
    foreach ($pdo->query("SELECT id_dentista, dia_semana, hora_inicio, hora_fim FROM horario_atendimento ORDER BY hora_inicio")->fetchAll() as $h) {
        $turnos[$h['id_dentista']][(int) $h['dia_semana']][] = [hm_($h['hora_inicio']), hm_($h['hora_fim'])];
    }
    $idsPac = array_map('intval', $pdo->query("SELECT id_paciente FROM paciente WHERE telefone LIKE '%90000-%'")->fetchAll(PDO::FETCH_COLUMN));
    if (!$idsPac) $idsPac = array_map('intval', $pdo->query("SELECT id_paciente FROM paciente")->fetchAll(PDO::FETCH_COLUMN));
    if (!$idsPac || !$turnos) return;
    $ocup = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE id_dentista = ? AND data = ? AND status <> 'CANCELADA' AND hora_inicio < ? AND hora_fim > ?");
    $stC = $pdo->prepare("INSERT INTO consulta (id_paciente, id_dentista, id_procedimento, data, hora_inicio, hora_fim, status, origem, primeira_consulta, observacoes, demo) VALUES (?,?,?,?,?,?,?,?,?,?,1)");
    $obsLista = [null, null, null, null, 'Paciente pediu lembrete por WhatsApp.', 'Trazer exames anteriores.', null, 'Sensibilidade no dente 26.'];
    mt_srand($semente);
    $agora = time();
    for ($d = strtotime($periodoIni); $d <= strtotime($periodoFim); $d = strtotime('+1 day', $d)) {
        $dia = date('Y-m-d', $d);
        $dow = (int) date('w', $d);
        $semanaApresentacao = $dia >= '2026-11-23' && $dia <= '2026-11-27';
        $ocupacao = $dia === "2026-11-24" ? 100 : ($semanaApresentacao ? 92 : ($dia >= "2026-11-16" ? 55 : 45));   // % de horários preenchidos
        foreach ($turnos as $idD => $porDia) {
            foreach ($porDia[$dow] ?? [] as [$ini, $fim]) {
                if (empty($procPorDentista[$idD])) continue;
                $t = strtotime("$dia $ini") + mt_rand(0, 1) * 15 * 60;
                $limite = strtotime("$dia $fim");
                while (true) {
                    $idP = $procPorDentista[$idD][mt_rand(0, count($procPorDentista[$idD]) - 1)];
                    $fimC = $t + $procInfo[$idP]['dur'] * 60;
                    if ($fimC > $limite) break;
                    if (mt_rand(1, 100) > $ocupacao) { $t = $fimC + 15 * 60; continue; }
                    $ocup->execute([$idD, $dia, date('H:i:s', $fimC), date('H:i:s', $t)]);
                    if (!$ocup->fetchColumn()) {
                        // pacientes antigos aparecem bastante (têm foto e histórico para mostrar)
                        $idPac = ($idsAntigos && mt_rand(1, 100) <= 45) ? $idsAntigos[mt_rand(0, count($idsAntigos) - 1)] : $idsPac[mt_rand(0, count($idsPac) - 1)];
                        if ($fimC < $agora) {
                            $status = 'AGENDADA';               // vira REALIZADA/FALTOU em atualizar_demo()
                        } else {
                            $status = mt_rand(1, 100) <= ($dia <= date('Y-m-d', strtotime('+2 days', $agora)) ? 70 : 40) ? 'CONFIRMADA' : 'AGENDADA';
                        }
                        $primeira = stripos($procInfo[$idP]['nome'], 'Primeira consulta') !== false ? 1 : 0;
                        $stC->execute([$idPac, $idD, $idP, $dia, date('H:i:s', $t), date('H:i:s', $fimC), $status, mt_rand(1, 100) <= 55 ? 'SITE' : 'SECRETARIA', $primeira, $obsLista[mt_rand(0, count($obsLista) - 1)]]);
                    }
                    $t = $fimC + mt_rand(0, 1) * 15 * 60;
                }
            }
        }
    }
}

// Mantém a demonstração "viva": consultas de DEMONSTRAÇÃO que já terminaram viram
// REALIZADA (com pagamento lançado) ou FALTOU. Roda no máximo a cada 10 minutos.
// Consultas criadas pelo site ou pela secretaria (demo = 0) nunca são alteradas.
function atualizar_demo(PDO $pdo) {
    $marca = sys_get_temp_dir() . '/gdm_demo_' . md5(__DIR__ . (getenv('DB_SQLITE') ?: getenv('DB_NAME') ?: ''));
    if (is_file($marca) && time() - filemtime($marca) < 600) return;
    @touch($marca);
    try {
        $st = $pdo->prepare("SELECT c.id_consulta, c.id_dentista, c.data, COALESCE(dp.valor, p.valor) AS valor FROM consulta c JOIN procedimento p ON p.id_procedimento = c.id_procedimento
                             LEFT JOIN dentista_procedimento dp ON dp.id_dentista = c.id_dentista AND dp.id_procedimento = c.id_procedimento
                             WHERE c.demo = 1 AND c.status IN ('AGENDADA','CONFIRMADA') AND (c.data < ? OR (c.data = ? AND c.hora_fim <= ?))");
        $st->execute([date('Y-m-d'), date('Y-m-d'), date('H:i:s')]);
        $lista = $st->fetchAll();
        if (!$lista) return;
        $formas = ['PIX', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Dinheiro'];
        $upd = $pdo->prepare("UPDATE consulta SET status = ? WHERE id_consulta = ?");
        $tem = $pdo->prepare("SELECT COUNT(*) FROM registro_financeiro WHERE id_consulta = ?");
        $fin = $pdo->prepare("INSERT INTO registro_financeiro (id_consulta, id_dentista, valor, forma_pagamento, data) VALUES (?,?,?,?,?)");
        $pdo->beginTransaction();
        foreach ($lista as $c) {
            $id = (int) $c['id_consulta'];
            if ($id % 13 === 0) { $upd->execute(['FALTOU', $id]); continue; }
            $upd->execute(['REALIZADA', $id]);
            $tem->execute([$id]);
            if (!$tem->fetchColumn()) $fin->execute([$id, $c['id_dentista'], $c['valor'], $formas[$id % count($formas)], $c['data']]);
        }
        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[clinica] atualizar_demo: ' . $t->getMessage());
    }
}

// Gera consultas fictícias entre hoje+$offIni e hoje+$offFim (dias úteis)
function gerar_consultas_demo(PDO $pdo, $offIni, $offFim, $semente) {
    mt_srand($semente);
    $turnos = [];
    foreach ($pdo->query("SELECT id_dentista, dia_semana, hora_inicio, hora_fim FROM horario_atendimento ORDER BY hora_inicio")->fetchAll() as $h) {
        $turnos[$h['id_dentista']][(int) $h['dia_semana']][] = [hm_($h['hora_inicio']), hm_($h['hora_fim'])];
    }
    $procPorDentista = []; $procInfo = [];
    foreach ($pdo->query("SELECT dp.id_dentista, p.id_procedimento, p.duracao_min FROM dentista_procedimento dp JOIN procedimento p ON p.id_procedimento = dp.id_procedimento")->fetchAll() as $r) {
        $procPorDentista[$r['id_dentista']][] = (int) $r['id_procedimento'];
        $procInfo[$r['id_procedimento']] = (int) $r['duracao_min'];
    }
    $idsPac = array_column($pdo->query("SELECT id_paciente FROM paciente")->fetchAll(), 'id_paciente');
    if (!$idsPac || !$procPorDentista) return;
    $ocup = $pdo->prepare("SELECT COUNT(*) FROM consulta WHERE id_dentista = ? AND data = ? AND status <> 'CANCELADA' AND hora_inicio < ? AND hora_fim > ?");
    $stC = $pdo->prepare("INSERT INTO consulta (id_paciente, id_dentista, id_procedimento, data, hora_inicio, hora_fim, status, origem) VALUES (?,?,?,?,?,?,?,?)");
    for ($off = $offIni; $off <= $offFim; $off++) {
        $dia = date('Y-m-d', strtotime("$off days"));
        $dow = (int) date('w', strtotime($dia));
        foreach ($turnos as $idD => $porDia) {
            foreach ($porDia[$dow] ?? [] as [$ini, $fim]) {
                if (empty($procPorDentista[$idD])) continue;
                $t = strtotime("$dia $ini") + mt_rand(0, 3) * 15 * 60;
                $limite = strtotime("$dia $fim");
                while (true) {
                    $idP = $procPorDentista[$idD][mt_rand(0, count($procPorDentista[$idD]) - 1)];
                    $fimC = $t + $procInfo[$idP] * 60;
                    if ($fimC > $limite) break;
                    if (mt_rand(1, 100) <= 50) { $t = $fimC + 15 * 60; continue; }
                    $ocup->execute([$idD, $dia, date('H:i:s', $fimC), date('H:i:s', $t)]);
                    if (!$ocup->fetchColumn()) {
                        $status = mt_rand(1, 100) <= 45 ? 'CONFIRMADA' : 'AGENDADA';
                        $stC->execute([$idsPac[mt_rand(0, count($idsPac) - 1)], $idD, $idP, $dia, date('H:i:s', $t), date('H:i:s', $fimC), $status, mt_rand(0, 1) ? 'SITE' : 'SECRETARIA']);
                    }
                    $t = $fimC + mt_rand(0, 2) * 15 * 60;
                }
            }
        }
    }
}
function hm_($h) { return substr((string) $h, 0, 5); }

// Dados iniciais (banco novo). A migração 2 depois troca o login fraco por logins fortes e ajusta preços.
function popular_dados_iniciais(PDO $pdo) {
    // ---------- Secretaria (login do sistema) ----------
    $pdo->prepare("INSERT INTO usuario (nome, email, senha, perfil) VALUES (?,?,?,?)")
        ->execute(['Juliana Prado', 'secretaria', password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), 'SECRETARIA']);

    // ---------- Dentistas (nomes fictícios) ----------
    $dentistas = [
        ['Dra. Helena Duarte',  'CRO-SP 00.001', 'Ortodontia e Ortopedia Facial',
         'Especialista em aparelhos fixos e alinhadores invisíveis. Cuida do alinhamento do sorriso de crianças e adultos.', '#12a3a0'],
        ['Dr. Rafael Monteiro', 'CRO-SP 00.002', 'Implantodontia e Cirurgia Oral',
         'Reabilitação com implantes e cirurgias orais. Planejamento digital para devolver função e estética.', '#0b3a75'],
        ['Dra. Camila Nogueira','CRO-SP 00.003', 'Estética e Harmonização Orofacial',
         'Clareamento, restaurações em resina e harmonia do sorriso, com foco em prevenção e naturalidade.', '#4f7cc4'],
    ];
    $stD = $pdo->prepare("INSERT INTO dentista (nome, cro, especialidade, bio, cor) VALUES (?,?,?,?,?)");
    $ids = [];
    foreach ($dentistas as $d) { $stD->execute($d); $ids[] = (int) $pdo->lastInsertId(); }
    [$helena, $rafael, $camila] = $ids;

    // ---------- Horários de atendimento: segunda (1) a sexta (5) ----------
    $turnos = [
        $helena => [['08:00', '12:00'], ['13:30', '18:00']],
        $rafael => [['09:00', '12:30'], ['14:00', '19:00']],
        $camila => [['07:30', '12:00'], ['13:00', '17:00']],
    ];
    $stH = $pdo->prepare("INSERT INTO horario_atendimento (id_dentista, dia_semana, hora_inicio, hora_fim) VALUES (?,?,?,?)");
    foreach ($turnos as $idD => $lista) {
        for ($dia = 1; $dia <= 5; $dia++) {
            foreach ($lista as [$ini, $fim]) $stH->execute([$idD, $dia, $ini . ':00', $fim . ':00']);
        }
    }

    // ---------- Procedimentos (duração define os horários oferecidos) ----------
    $procs = [
        // nome, descrição, duração (min), valor, ícone, dentistas que realizam
        ['Avaliação odontológica', 'Primeira consulta, exame clínico e plano de tratamento.', 30, 120.00, 'bi-clipboard2-pulse', [$helena, $rafael, $camila]],
        ['Limpeza e profilaxia', 'Remoção de tártaro, polimento e aplicação de flúor.', 45, 180.00, 'bi-droplet', [$helena, $camila]],
        ['Clareamento dental (sessão)', 'Clareamento em consultório com gel de alta performance.', 60, 450.00, 'bi-brightness-high', [$camila]],
        ['Restauração em resina', 'Reconstrução estética do dente com resina da cor natural.', 60, 250.00, 'bi-gem', [$camila]],
        ['Manutenção de aparelho', 'Ajuste mensal do aparelho ortodôntico.', 30, 150.00, 'bi-arrow-repeat', [$helena]],
        ['Instalação de aparelho ortodôntico', 'Colagem de braquetes e orientações de uso.', 90, 900.00, 'bi-grid-3x3-gap', [$helena]],
        ['Planejamento de alinhadores', 'Escaneamento digital e simulação do novo sorriso.', 60, 600.00, 'bi-magic', [$helena]],
        ['Avaliação para implante', 'Análise de tomografia e planejamento cirúrgico.', 45, 200.00, 'bi-search-heart', [$rafael]],
        ['Cirurgia de implante', 'Instalação do implante dentário com anestesia local.', 120, 2800.00, 'bi-shield-plus', [$rafael]],
        ['Extração simples', 'Remoção de dente com técnica minimamente invasiva.', 45, 300.00, 'bi-bandaid', [$rafael]],
    ];
    $stP  = $pdo->prepare("INSERT INTO procedimento (nome, descricao, duracao_min, valor, icone) VALUES (?,?,?,?,?)");
    $stDP = $pdo->prepare("INSERT INTO dentista_procedimento (id_dentista, id_procedimento) VALUES (?,?)");
    $procPorDentista = [];
    $procInfo = [];
    foreach ($procs as $p) {
        $stP->execute([$p[0], $p[1], $p[2], $p[3], $p[4]]);
        $idP = (int) $pdo->lastInsertId();
        $procInfo[$idP] = ['dur' => $p[2], 'valor' => $p[3]];
        foreach ($p[5] as $idD) { $stDP->execute([$idD, $idP]); $procPorDentista[$idD][] = $idP; }
    }

    // ---------- Pacientes fictícios (para demonstração) – telefones 90000-xxxx não existem ----------
    $pacientes = [
        ['Ana Beatriz Lima', '(19) 90000-0001'], ['Bruno Henrique Alves', '(19) 90000-0002'],
        ['Carla Mendes', '(19) 90000-0003'], ['Diego Fernandes', '(19) 90000-0004'],
        ['Eduarda Rocha', '(19) 90000-0005'], ['Felipe Santos', '(19) 90000-0006'],
        ['Gabriela Costa', '(19) 90000-0007'], ['Henrique Barros', '(19) 90000-0008'],
        ['Isabela Martins', '(19) 90000-0009'], ['João Pedro Silva', '(19) 90000-0010'],
        ['Larissa Oliveira', '(19) 90000-0011'], ['Marcos Vinícius Souza', '(19) 90000-0012'],
    ];
    $stPac = $pdo->prepare("INSERT INTO paciente (nome, telefone, email) VALUES (?,?,?)");
    $idsPac = [];
    foreach ($pacientes as $pc) {
        $mail = strtolower(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', explode(' ', $pc[0])[0]))) . '@paciente.example';   // e-mail FICTÍCIO
        $stPac->execute([$pc[0], $pc[1], $mail]);
        $idsPac[] = (int) $pdo->lastInsertId();
    }

    // ---------- Consultas de demonstração: mês anterior, mês atual e próximos 7 dias ----------
    mt_srand(2026);
    $stC = $pdo->prepare("INSERT INTO consulta (id_paciente, id_dentista, id_procedimento, data, hora_inicio, hora_fim, status, origem)
                          VALUES (?,?,?,?,?,?,?,?)");
    $stF = $pdo->prepare("INSERT INTO registro_financeiro (id_consulta, id_dentista, valor, forma_pagamento, data) VALUES (?,?,?,?,?)");
    $formas = ['PIX', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Dinheiro', 'Convênio'];
    $hoje = date('Y-m-d');

    // começa no 1º dia do mês anterior (para a comparação mensal do financeiro)
    $inicioDemo = (int) floor((strtotime(date('Y-m-01', strtotime('first day of last month'))) - strtotime($hoje)) / 86400);
    for ($off = $inicioDemo; $off <= 7; $off++) {
        $dia = date('Y-m-d', strtotime("$off days"));
        $dow = (int) date('w', strtotime($dia));
        if ($dow < 1 || $dow > 5) continue;
        foreach ($turnos as $idD => $lista) {
            foreach ($lista as [$ini, $fim]) {
                $t = strtotime("$dia $ini") + mt_rand(0, 3) * 15 * 60;
                $limite = strtotime("$dia $fim");
                while (true) {
                    $idP = $procPorDentista[$idD][mt_rand(0, count($procPorDentista[$idD]) - 1)];
                    $fimC = $t + $procInfo[$idP]['dur'] * 60;
                    if ($fimC > $limite) break;
                    // deixa buracos na agenda (horários livres)
                    if (mt_rand(1, 100) <= ($off >= 0 ? 55 : 35)) { $t = $fimC + 15 * 60; continue; }
                    // não ocupa o resto do dia de hoje para sobrar horário na demonstração
                    if ($dia === $hoje && date('H', $t) >= 14) break;

                    $passado = ($dia < $hoje) || ($dia === $hoje && $fimC < time());
                    if ($passado) {
                        $r = mt_rand(1, 100);
                        $status = $r <= 84 ? 'REALIZADA' : ($r <= 93 ? 'FALTOU' : 'CANCELADA');
                    } else {
                        $status = mt_rand(1, 100) <= 50 ? 'CONFIRMADA' : 'AGENDADA';
                    }
                    $origem = mt_rand(0, 1) ? 'SITE' : 'SECRETARIA';
                    $idPac = $idsPac[mt_rand(0, count($idsPac) - 1)];
                    $stC->execute([$idPac, $idD, $idP, $dia, date('H:i:s', $t), date('H:i:s', $fimC), $status, $origem]);
                    $idC = (int) $pdo->lastInsertId();
                    if ($status === 'REALIZADA') {
                        $stF->execute([$idC, $idD, $procInfo[$idP]['valor'], $formas[mt_rand(0, count($formas) - 1)], $dia]);
                    }
                    $t = $fimC + mt_rand(0, 2) * 15 * 60;
                }
            }
        }
    }
}
