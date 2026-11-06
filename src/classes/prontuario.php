<?php
// classes/prontuario.php
// Prontuário odontológico: fotos de cada sessão, exames e observações do dentista.
// Regra de acesso: o dentista só vê pacientes que tenham (ou tiveram) consulta com ele.

class Prontuario {
    public $pdo;
    public $idDentista;
    public $erro = '';

    const TIPOS_IMAGEM = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    const TIPOS_EXAME  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    const LIMITE_MB = 10;

    public function __construct($pdo, $idDentista) {
        $this->pdo = $pdo;
        $this->idDentista = (int) $idDentista;
    }

    public static function pasta() {
        return __DIR__ . '/../uploads/prontuario/';
    }

    public function podeAcessar($idPaciente) {
        $st = $this->pdo->prepare("SELECT 1 FROM consulta WHERE id_paciente = ? AND id_dentista = ? LIMIT 1");
        $st->execute([$idPaciente, $this->idDentista]);
        if ($st->fetchColumn()) return true;
        $st = $this->pdo->prepare("SELECT 1 FROM prontuario WHERE id_paciente = ? AND id_dentista = ? LIMIT 1");
        $st->execute([$idPaciente, $this->idDentista]);
        return (bool) $st->fetchColumn();
    }

    public function registro($id) {
        $st = $this->pdo->prepare("SELECT * FROM prontuario WHERE id_registro = ?");
        $st->execute([$id]);
        return $st->fetch();
    }

    public function listar($idPaciente, $tipo = null) {
        $sql = "SELECT r.*, d.nome AS dentista, d.cor, pr.nome AS procedimento
                FROM prontuario r
                JOIN dentista d ON d.id_dentista = r.id_dentista
                LEFT JOIN consulta c ON c.id_consulta = r.id_consulta
                LEFT JOIN procedimento pr ON pr.id_procedimento = c.id_procedimento
                WHERE r.id_paciente = ?";
        $p = [$idPaciente];
        if ($tipo) { $sql .= " AND r.tipo = ?"; $p[] = $tipo; }
        $sql .= " ORDER BY r.data_registro DESC, r.id_registro DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    // Salva um arquivo enviado (foto ou exame) com validação de tipo REAL (não confia na extensão)
    public function salvarArquivo($idPaciente, array $arq, $tipo, $dados) {
        if (($arq['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return false;
        if ($arq['error'] !== UPLOAD_ERR_OK) { $this->erro = 'Falha no envio de "' . $arq['name'] . '" (arquivo grande demais?).'; return false; }
        if ($arq['size'] > self::LIMITE_MB * 1024 * 1024) { $this->erro = '"' . $arq['name'] . '" passa de ' . self::LIMITE_MB . ' MB.'; return false; }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($arq['tmp_name']);
        $aceitos = $tipo === 'EXAME' ? self::TIPOS_EXAME : self::TIPOS_IMAGEM;
        if (!isset($aceitos[$mime])) { $this->erro = '"' . $arq['name'] . '": formato não aceito (' . ($tipo === 'EXAME' ? 'JPG, PNG, WEBP ou PDF' : 'JPG, PNG ou WEBP') . ').'; return false; }
        if (strpos($mime, 'image/') === 0 && !@getimagesize($arq['tmp_name'])) { $this->erro = '"' . $arq['name'] . '" não é uma imagem válida.'; return false; }

        $dir = self::pasta() . (int) $idPaciente . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { $this->erro = 'Sem permissão para gravar em uploads/prontuario.'; return false; }
        $nome = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $aceitos[$mime];
        if (!move_uploaded_file($arq['tmp_name'], $dir . $nome)) { $this->erro = 'Não foi possível salvar o arquivo.'; return false; }

        $this->pdo->prepare("INSERT INTO prontuario (id_paciente, id_dentista, id_consulta, tipo, titulo, texto, arquivo, nome_original, mime, tamanho, data_registro)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$idPaciente, $this->idDentista, $dados['id_consulta'] ?: null, $tipo, $dados['titulo'] ?: null, $dados['texto'] ?: null,
                       $nome, mb_substr($arq['name'], 0, 255), $mime, (int) $arq['size'], $dados['data']]);
        return true;
    }

    public function salvarNota($idPaciente, $dados) {
        $this->pdo->prepare("INSERT INTO prontuario (id_paciente, id_dentista, id_consulta, tipo, titulo, texto, data_registro) VALUES (?,?,?, 'NOTA', ?,?,?)")
            ->execute([$idPaciente, $this->idDentista, $dados['id_consulta'] ?: null, $dados['titulo'] ?: null, $dados['texto'], $dados['data']]);
    }

    // Só quem criou o registro pode excluí-lo
    public function excluir($idRegistro) {
        $r = $this->registro($idRegistro);
        if (!$r || (int) $r['id_dentista'] !== $this->idDentista) return false;
        if ($r['arquivo']) @unlink(self::pasta() . (int) $r['id_paciente'] . '/' . basename($r['arquivo']));
        $this->pdo->prepare("DELETE FROM prontuario WHERE id_registro = ?")->execute([$idRegistro]);
        return $r;
    }

    // Reorganiza $_FILES['x'] (vários arquivos) em uma lista simples
    public static function arquivos($campo) {
        $f = $_FILES[$campo] ?? null;
        if (!$f) return [];
        if (!is_array($f['name'])) return [$f];
        $lista = [];
        foreach ($f['name'] as $i => $n) {
            $lista[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $lista;
    }
}
