<?php
// Itens comuns do <head> de todas as páginas. Antes de incluir, defina $titulo_pagina.
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b3a75">
    <title><?= e($titulo_pagina ?? $nome_clinica) ?></title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=22" rel="stylesheet">
    <script>
    // aplica as preferências de acessibilidade antes de desenhar a página (sem "piscar")
    (function () { try {
        var d = document.documentElement;
        if (localStorage.getItem('a11y_contraste') === '1') d.classList.add('alto-contraste');
        var f = parseInt(localStorage.getItem('a11y_fonte') || '0', 10); if (f) d.setAttribute('data-fonte', f);
        if (localStorage.getItem('menu_oculto') === '1') d.classList.add('menu-oculto');
    } catch (e) {} })();
    </script>
