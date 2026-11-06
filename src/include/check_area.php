<?php
// Páginas usadas pelos dois perfis (agenda da clínica, alterar senha)
require_once __DIR__ . '/config.php';
exigir_login(['SECRETARIA', 'DENTISTA']);
