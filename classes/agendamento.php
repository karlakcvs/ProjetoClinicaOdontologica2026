<?php
// classes/Agendamento.php

class Agendamento {
    // 1. ATRIBUTOS (Características)
    public $pdo;
    public $id;
    public $cliente;
    public $telefone;
    public $servico;
    public $data;
    public $hora;

    // 2. CONSTRUTOR (Executado ao criar o objeto)
    public function __construct($conexao) {
        $this->pdo = $conexao;
    }

    // 3. MÉTODO (Ação de Salvar)
    public function salvar() {
        $sql = "INSERT INTO agendamentos (cliente, telefone, servico, data_agendamento, horario)
                VALUES (:cli, :tel, :ser, :dat, :hor)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':cli' => $this->cliente,
            ':tel' => $this->telefone,
            ':ser' => $this->servico,
            ':dat' => $this->data,
            ':hor' => $this->hora
        ]);
    }

    //Método para listar agendamentos
    public function listarTodos() {
        // Note que usamos o $this->pdo que foi configurado no construtor
        $sql = "SELECT * FROM agendamentos ORDER BY data_agendamento ASC, horario ASC";
        $stmt = $this->pdo->query($sql);

        // Retornamos o array de dados pronto para o foreach
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Método Filtrar
    public function buscar($nome = '', $data = '') {
        $sql = "SELECT * FROM agendamentos WHERE 1=1";
        $params = [];

        if (!empty($nome)) {
            $sql .= " AND cliente LIKE :nome";
            $params[':nome'] = "%$nome%";
        }

        if (!empty($data)) {
            $sql .= " AND data_agendamento = :data";
            $params[':data'] = $data;
        }

        $sql .= " ORDER BY data_agendamento ASC, horario ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>