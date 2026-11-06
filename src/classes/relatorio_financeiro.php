<?php
// classes/relatorio_financeiro.php
// Classe de serviço "RelatorioFinanceiro" do diagrama de classes:
// apenas CALCULA os totais a partir da tabela registro_financeiro (RF09 / RF10).

class RelatorioFinanceiro {
    public $pdo;

    public function __construct($conexao) {
        $this->pdo = $conexao;
    }

    // Soma e quantidade de atendimentos em um período, opcionalmente por dentista
    public function total($inicio, $fim, $idDentista = null) {
        $sql = "SELECT COALESCE(SUM(valor),0) AS total, COUNT(*) AS qtd FROM registro_financeiro WHERE data BETWEEN ? AND ?";
        $p = [$inicio, $fim];
        if ($idDentista) { $sql .= " AND id_dentista = ?"; $p[] = $idDentista; }
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        $r = $st->fetch();
        return ['total' => (float) $r['total'], 'qtd' => (int) $r['qtd']];
    }

    // Totais do dia e do mês para cada dentista (contagem diária e mensal)
    public function totalPorDentista($dia, $mesInicio, $mesFim) {
        $dentistas = $this->pdo->query("SELECT * FROM dentista WHERE ativo = 1 ORDER BY id_dentista")->fetchAll();
        foreach ($dentistas as &$d) {
            $d['dia'] = $this->total($dia, $dia, $d['id_dentista']);
            $d['mes'] = $this->total($mesInicio, $mesFim, $d['id_dentista']);
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM consulta WHERE id_dentista = ? AND data BETWEEN ? AND ? AND status = 'FALTOU'");
            $st->execute([$d['id_dentista'], $mesInicio, $mesFim]);
            $d['faltas_mes'] = (int) $st->fetchColumn();
        }
        return $dentistas;
    }

    // Total consolidado da clínica (todos os dentistas)
    public function totalConsolidado($inicio, $fim) {
        return $this->total($inicio, $fim);
    }

    // Série diária do mês (para o gráfico)
    public function serieDiaria($mesInicio, $mesFim) {
        $st = $this->pdo->prepare("SELECT data, id_dentista, SUM(valor) AS total FROM registro_financeiro
                                   WHERE data BETWEEN ? AND ? GROUP BY data, id_dentista");
        $st->execute([$mesInicio, $mesFim]);
        $serie = [];
        foreach ($st->fetchAll() as $r) $serie[$r['data']][$r['id_dentista']] = (float) $r['total'];
        return $serie;
    }

    public function porFormaPagamento($inicio, $fim) {
        $st = $this->pdo->prepare("SELECT forma_pagamento, SUM(valor) AS total, COUNT(*) AS qtd FROM registro_financeiro
                                   WHERE data BETWEEN ? AND ? GROUP BY forma_pagamento ORDER BY total DESC");
        $st->execute([$inicio, $fim]);
        return $st->fetchAll();
    }

    public function porProcedimento($inicio, $fim) {
        $st = $this->pdo->prepare("SELECT pr.nome, SUM(f.valor) AS total, COUNT(*) AS qtd
                                   FROM registro_financeiro f
                                   JOIN consulta c ON c.id_consulta = f.id_consulta
                                   JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                                   WHERE f.data BETWEEN ? AND ? GROUP BY pr.nome ORDER BY total DESC");
        $st->execute([$inicio, $fim]);
        return $st->fetchAll();
    }

    public function lancamentos($inicio, $fim, $idDentista = null) {
        $sql = "SELECT f.*, p.nome AS paciente, d.nome AS dentista, pr.nome AS procedimento, c.hora_inicio
                FROM registro_financeiro f
                JOIN consulta c ON c.id_consulta = f.id_consulta
                JOIN paciente p ON p.id_paciente = c.id_paciente
                JOIN dentista d ON d.id_dentista = f.id_dentista
                JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                WHERE f.data BETWEEN ? AND ?";
        $p = [$inicio, $fim];
        if ($idDentista) { $sql .= " AND f.id_dentista = ?"; $p[] = $idDentista; }
        $sql .= " ORDER BY f.data DESC, c.hora_inicio DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    // Faturamento PREVISTO de um dentista no período:
    // recebido (atendimentos já pagos) + pendente (consultas agendadas/confirmadas de hoje em diante, pelo valor da tabela)
    public function previsto($idDentista, $inicio, $fim) {
        $rec = $this->total($inicio, $fim, $idDentista);
        $st = $this->pdo->prepare("SELECT COALESCE(SUM(pr.valor),0) AS total, COUNT(*) AS qtd
                                   FROM consulta c JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                                   WHERE c.id_dentista = ? AND c.data BETWEEN ? AND ? AND c.data >= ?
                                     AND c.status IN ('AGENDADA','CONFIRMADA')");
        $st->execute([$idDentista, $inicio, $fim, date('Y-m-d')]);
        $pend = $st->fetch();
        return [
            'recebido' => $rec['total'], 'qtd_recebido' => $rec['qtd'],
            'pendente' => (float) $pend['total'], 'qtd_pendente' => (int) $pend['qtd'],
            'previsto' => $rec['total'] + (float) $pend['total'],
        ];
    }
}
