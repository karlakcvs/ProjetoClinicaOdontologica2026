<?php
// Protege a ÁREA TÉCNICA da SanyTech: só o perfil TI entra (secretaria e dentistas não).
// A área técnica não mostra dados de pacientes, consultas ou finanças da clínica.
require_once __DIR__ . '/config.php';
exigir_login(['TI']);
