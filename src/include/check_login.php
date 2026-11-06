<?php
// Protege as páginas da área da SECRETARIA (RNF01 / RNF02)
require_once __DIR__ . '/config.php';
exigir_login(['SECRETARIA']);
