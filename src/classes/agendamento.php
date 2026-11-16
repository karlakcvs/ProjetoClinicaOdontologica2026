<?php
// classes/agendamento.php
// Classe "Consulta" do diagrama de classes (mantivemos o nome Agendamento do projeto original)

class Agendamento {
    // 1. ATRIBUTOS
    public $pdo;
    public $id;
    public $paciente;          // nome do paciente
    public $telefone;
    public $email;
    public $id_paciente;
    public $id_dentista;
    public $id_procedimento;
    public $data;
    public $hora;              // hora de início
    public $hora_fim;
    public $status = 'AGENDADA';
    public $origem = 'SITE';
    public $observacoes = '';
    public $primeira_consulta = 0;
    public $erro = '';

    // 2. CONSTRUTOR
    public function __construct($conexao) {
        $this->pdo = $conexao;
    }

    // ---------------------------------------------------------------
    //  Dados de apoio
    // ---------------------------------------------------------------
    public function procedimento($id) {
        $st = $this->pdo->prepare("SELECT * FROM procedimento WHERE id_procedimento = ?");
        $st->execute([$id]);
        return $st->fetch();
    }

    public function dentista($id) {
        $st = $this->pdo->prepare("SELECT * FROM dentista WHERE id_dentista = ? AND ativo = 1");
        $st->execute([$id]);
        return $st->fetch();
    }

    public function dentistaRealiza($idDentista, $idProcedimento) {
        $st = $this->pdo->prepare("SELECT 1 FROM dentista_procedimento WHERE id_dentista = ? AND id_procedimento = ?");
        $st->execute([$idDentista, $idProcedimento]);
        return (bool) $st->fetchColumn();
    }

    // ---------------------------------------------------------------
    //  UC13 / UC06 - Horários disponíveis considerando a DURAÇÃO do procedimento
    // ---------------------------------------------------------------
    public function horariosDisponiveis($idDentista, $idProcedimento, $data, $ignorarConsulta = null) {
        $proc = $this->procedimento($idProcedimento);
        if (!$proc || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return [];
        $duracao = (int) $proc['duracao_min'];
        $diaSemana = (int) date('w', strtotime($data));

        // turnos de trabalho do dentista nesse dia da semana
        $st = $this->pdo->prepare("SELECT hora_inicio, hora_fim FROM horario_atendimento
                                   WHERE id_dentista = ? AND dia_semana = ? ORDER BY hora_inicio");
        $st->execute([$idDentista, $diaSemana]);
        $turnos = $st->fetchAll();
        if (!$turnos) return [];

        // consultas que já ocupam a agenda
        $ocupados = $this->ocupados($idDentista, $data, $ignorarConsulta);

        $agora = time();
        $livres = [];
        foreach ($turnos as $t) {
            $inicio = strtotime("$data {$t['hora_inicio']}");
            $fimTurno = strtotime("$data {$t['hora_fim']}");
            for ($h = $inicio; $h + $duracao * 60 <= $fimTurno; $h += PASSO_AGENDA * 60) {
                $hFim = $h + $duracao * 60;
                if ($h <= $agora + 30 * 60) continue;  // não oferece horários passados (30 min de antecedência)
                $conflito = false;
                foreach ($ocupados as [$oIni, $oFim]) {
                    if ($h < $oFim && $hFim > $oIni) { $conflito = true; break; }
                }
                if (!$conflito) {
                    $livres[] = ['inicio' => date('H:i', $h), 'fim' => date('H:i', $hFim),
                                 'periodo' => (int) date('H', $h) < 12 ? 'manha' : ((int) date('H', $h) < 18 ? 'tarde' : 'noite')];
                }
            }
        }
        return $livres;
    }

    private function ocupados($idDentista, $data, $ignorarConsulta = null) {
        $sql = "SELECT hora_inicio, hora_fim FROM consulta
                WHERE id_dentista = ? AND data = ? AND status NOT IN ('CANCELADA')";
        $params = [$idDentista, $data];
        if ($ignorarConsulta) { $sql .= " AND id_consulta <> ?"; $params[] = $ignorarConsulta; }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $r = [];
        foreach ($st->fetchAll() as $o) $r[] = [strtotime("$data {$o['hora_inicio']}"), strtotime("$data {$o['hora_fim']}")];
        return $r;
    }

    // Verifica se o horário escolhido continua livre (evita dois pacientes no mesmo horário - RNF07)
    public function verificarConflito($ignorarConsulta = null) {
        $livres = $this->horariosDisponiveis($this->id_dentista, $this->id_procedimento, $this->data, $ignorarConsulta);
        foreach ($livres as $l) if ($l['inicio'] === substr($this->hora, 0, 5)) return false;
        return true;
    }

    // ---------------------------------------------------------------
    //  Paciente: reaproveita o cadastro pelo telefone
    // ---------------------------------------------------------------
    public function obterPaciente() {
        if ($this->id_paciente) return $this->id_paciente;
        $tel = preg_replace('/\D/', '', $this->telefone);
        $st = $this->pdo->prepare("SELECT id_paciente, telefone FROM paciente WHERE telefone LIKE ?");
        $st->execute(['%' . substr($tel, -4)]);
        foreach ($st->fetchAll() as $p) {
            if (preg_replace('/\D/', '', $p['telefone']) === $tel) {
                $this->id_paciente = (int) $p['id_paciente'];
                if ($this->email) {
                    $this->pdo->prepare("UPDATE paciente SET email = COALESCE(NULLIF(email,''), ?) WHERE id_paciente = ?")
                              ->execute([$this->email, $this->id_paciente]);
                }
                return $this->id_paciente;
            }
        }
        $this->pdo->prepare("INSERT INTO paciente (nome, telefone, email) VALUES (?,?,?)")
                  ->execute([$this->paciente, $this->telefone, $this->email ?: null]);
        $this->id_paciente = (int) $this->pdo->lastInsertId();
        return $this->id_paciente;
    }

    // ---------------------------------------------------------------
    //  3. MÉTODO SALVAR (UC05 - Agendar consulta)
    // ---------------------------------------------------------------
    public function salvar() {
        $proc = $this->procedimento($this->id_procedimento);
        if (!$proc || !$this->dentista($this->id_dentista)) { $this->erro = 'Dentista ou procedimento inválido.'; return false; }
        if (!$this->dentistaRealiza($this->id_dentista, $this->id_procedimento)) { $this->erro = 'Este dentista não realiza o procedimento escolhido.'; return false; }
        if ($this->verificarConflito()) { $this->erro = 'Esse horário acabou de ser ocupado. Escolha outro horário, por favor.'; return false; }

        $this->hora = substr($this->hora, 0, 5) . ':00';
        $this->hora_fim = date('H:i:s', strtotime("{$this->data} {$this->hora}") + $proc['duracao_min'] * 60);
        $this->obterPaciente();

        $sql = "INSERT INTO consulta (id_paciente, id_dentista, id_procedimento, data, hora_inicio, hora_fim, status, origem, observacoes, primeira_consulta)
                VALUES (:pac, :den, :pro, :dat, :ini, :fim, :sta, :ori, :obs, :pri)";
        $ok = $this->pdo->prepare($sql)->execute([
            ':pac' => $this->id_paciente, ':den' => $this->id_dentista, ':pro' => $this->id_procedimento,
            ':dat' => $this->data, ':ini' => $this->hora, ':fim' => $this->hora_fim,
            ':sta' => $this->status, ':ori' => $this->origem, ':obs' => $this->observacoes, ':pri' => $this->primeira_consulta ? 1 : 0,
        ]);
        if ($ok) {
            $this->id = (int) $this->pdo->lastInsertId();
            $this->programarLembrete();
        }
        return $ok;
    }

    // UC07 - programa um lembrete para 24h antes da consulta
    public function programarLembrete() {
        $envio = date('Y-m-d H:i:s', strtotime("{$this->data} {$this->hora}") - 24 * 3600);
        $this->pdo->prepare("INSERT INTO lembrete (id_consulta, canal, data_envio) VALUES (?, 'WHATSAPP', ?)")
                  ->execute([$this->id, $envio]);
    }

    // UC09 - Alterar consulta
    public function atualizar() {
        $proc = $this->procedimento($this->id_procedimento);
        if (!$proc) { $this->erro = 'Procedimento inválido.'; return false; }
        if (!$this->dentistaRealiza($this->id_dentista, $this->id_procedimento)) { $this->erro = 'Este dentista não realiza o procedimento escolhido.'; return false; }
        if ($this->verificarConflito($this->id)) { $this->erro = 'Conflito de horário: o dentista já tem consulta nesse período.'; return false; }
        $this->hora = substr($this->hora, 0, 5) . ':00';
        $this->hora_fim = date('H:i:s', strtotime("{$this->data} {$this->hora}") + $proc['duracao_min'] * 60);
        $sql = "UPDATE consulta SET id_dentista = :den, id_procedimento = :pro, data = :dat,
                       hora_inicio = :ini, hora_fim = :fim, status = :sta, observacoes = :obs
                WHERE id_consulta = :id";
        $ok = $this->pdo->prepare($sql)->execute([
            ':den' => $this->id_dentista, ':pro' => $this->id_procedimento, ':dat' => $this->data,
            ':ini' => $this->hora, ':fim' => $this->hora_fim, ':sta' => $this->status,
            ':obs' => $this->observacoes, ':id' => $this->id,
        ]);
        if ($ok) {
            $this->pdo->prepare("UPDATE lembrete SET data_envio = ?, enviado = 0 WHERE id_consulta = ?")
                      ->execute([date('Y-m-d H:i:s', strtotime("{$this->data} {$this->hora}") - 86400), $this->id]);
        }
        return $ok;
    }

    public function alterarStatus($id, $status) {
        $this->pdo->prepare("UPDATE consulta SET status = ? WHERE id_consulta = ?")->execute([$status, $id]);
        if ($status !== 'REALIZADA') {
            // consultas canceladas / faltas não geram registro financeiro
            $this->pdo->prepare("DELETE FROM registro_financeiro WHERE id_consulta = ?")->execute([$id]);
        }
    }

    // UC11 / UC17 - registra atendimento realizado e o valor pago
    public function registrarPagamento($id, $valor, $forma) {
        $c = $this->buscarPorId($id);
        if (!$c) return false;
        $this->pdo->prepare("DELETE FROM registro_financeiro WHERE id_consulta = ?")->execute([$id]);
        $this->pdo->prepare("INSERT INTO registro_financeiro (id_consulta, id_dentista, valor, forma_pagamento, data) VALUES (?,?,?,?,?)")
                  ->execute([$id, $c['id_dentista'], $valor, $forma, $c['data']]);
        $this->pdo->prepare("UPDATE consulta SET status = 'REALIZADA' WHERE id_consulta = ?")->execute([$id]);
        return true;
    }

    public function excluir($id) {
        $this->pdo->prepare("DELETE FROM lembrete WHERE id_consulta = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM registro_financeiro WHERE id_consulta = ?")->execute([$id]);
        return $this->pdo->prepare("DELETE FROM consulta WHERE id_consulta = ?")->execute([$id]);
    }

    // ---------------------------------------------------------------
    //  Consultas / filtros (mantido do projeto original e ampliado)
    // ---------------------------------------------------------------
    private function sqlBase() {
        return "SELECT c.*, p.nome AS paciente, p.telefone, p.email, p.cpf, p.cep, p.logradouro, p.numero, p.cidade, p.data_nascimento, p.foto AS foto_paciente, p.criado_em AS paciente_desde,
                       d.nome AS dentista, d.especialidade, d.cor,
                       pr.nome AS procedimento, COALESCE(dp.valor, pr.valor) AS valor_tabela, pr.duracao_min,
                       f.valor AS valor_pago, f.forma_pagamento
                FROM consulta c
                JOIN paciente p ON p.id_paciente = c.id_paciente
                JOIN dentista d ON d.id_dentista = c.id_dentista
                JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                LEFT JOIN dentista_procedimento dp ON dp.id_dentista = c.id_dentista AND dp.id_procedimento = c.id_procedimento
                LEFT JOIN registro_financeiro f ON f.id_consulta = c.id_consulta";
    }

    public function buscarPorId($id) {
        $st = $this->pdo->prepare($this->sqlBase() . " WHERE c.id_consulta = ?");
        $st->execute([$id]);
        return $st->fetch();
    }

    public function listarTodos() {
        return $this->buscar();
    }

    public function buscar($nome = '', $data = '', $idDentista = '', $status = '', $dataFim = '') {
        $sql = $this->sqlBase() . " WHERE 1=1";
        $params = [];
        if ($nome !== '')       { $sql .= " AND p.nome LIKE :nome"; $params[':nome'] = "%$nome%"; }
        if ($data !== '' && $dataFim !== '') { $sql .= " AND c.data BETWEEN :d1 AND :d2"; $params[':d1'] = $data; $params[':d2'] = $dataFim; }
        elseif ($data !== '')   { $sql .= " AND c.data = :data"; $params[':data'] = $data; }
        if ($idDentista !== '') { $sql .= " AND c.id_dentista = :den"; $params[':den'] = $idDentista; }
        if ($status !== '')     { $sql .= " AND c.status = :sta"; $params[':sta'] = $status; }
        $sql .= " ORDER BY c.data ASC, c.hora_inicio ASC";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }
}
