# Análise de segurança – Sistema Garcia Di Mase (SanyTech)

Revisão feita sobre a versão anterior do projeto. Cada item mostra o **risco encontrado** e **o que foi feito**.

| # | Risco encontrado | Gravidade | Correção aplicada |
|---|---|---|---|
| 1 | `gerar_senha.php` podia ser aberto por **qualquer pessoa** e recriava o login da secretaria com senha conhecida | Crítica | Arquivo removido. Troca de senha agora só pelo terminal (`include/redefinir_senha.php`, roda apenas via CLI) ou pela tela **Alterar senha** (exige a senha atual) |
| 2 | Senha fraca e pública em uma versão anterior | Alta | Logins por e-mail (`nome@garciadimase.example`) com senhas fortes; só o hash bcrypt fica no código; política de senha forte |
| 3 | Login sem limite de tentativas (força bruta) | Alta | **Rate limiting: 4 tentativas erradas em 15 min bloqueiam o IP** (medidor de tentativas e contagem regressiva na tela); mensagem de erro genérica; tempo de resposta igual para usuário existente ou não |
| 4 | Formulários públicos sem proteção contra robôs (agendamento falso em massa, spam) | Alta | **Rate limiting de 4 envios por IP** (agendamento 4/h, contato 4/30 min, avaliação 4/h) com aviso visível de quantas tentativas restam + campo-isca invisível + tempo mínimo de preenchimento. O captcha de letras foi removido |
| 5 | Agendamento público sem token CSRF | Média | Token CSRF em **todos** os formulários (públicos e restritos); logout só por POST com token |
| 6 | Só existia um perfil; não havia separação de acesso | Média | Controle por perfil: secretaria × dentista. O dentista só abre prontuários de pacientes com consulta com ele; não acessa financeiro geral nem cadastros |
| 7 | Cookie de sessão sem `SameSite`, sem tempo de expiração | Média | Cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` se HTTPS), `use_strict_mode`, novo ID a cada login, saída automática após 30 min sem uso, sessão presa ao navegador |
| 8 | Fotos/exames de pacientes (dados de saúde – LGPD) | Alta | Ficam em `uploads/prontuario`, bloqueada por `.htaccess` **e** pela configuração do Apache; só são entregues por `arquivo.php` após checar login e vínculo do dentista |
| 9 | Upload confiando na extensão do arquivo | Média | Tipo real verificado (finfo + getimagesize), nome aleatório, limite de tamanho; scripts nunca executam dentro de `uploads/` |
| 10 | Pastas internas (`include/`, `classes/`) acessíveis pelo navegador; listagem de diretórios | Média | `Require all denied` + `Options -Indexes`; `AllowOverride All` ativado no Dockerfile para os `.htaccess` funcionarem |
| 11 | Mensagem de erro do banco mostrava detalhes técnicos | Baixa | Erro técnico vai só para o log; visitante vê mensagem neutra. `display_errors=Off`, `expose_php=Off`, `ServerTokens Prod` |
| 12 | Sem cabeçalhos de segurança | Média | `Content-Security-Policy`, `X-Frame-Options` (anti-clickjacking), `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` |
| 13 | MySQL (3306) e phpMyAdmin (8888) expostos para a rede | Média | Portas presas a `127.0.0.1` (só o próprio computador); removido `PMA_ARBITRARY` |
| 14 | Mensagens de erro passadas pela URL (`?erro=texto`) podiam ser forjadas | Baixa | Mensagens agora ficam na sessão |
| 15 | Texto vindo do banco inserido com `innerHTML` no JavaScript do agendamento | Baixa | Valores escapados antes de inserir; JSON nos scripts com `JSON_HEX_TAG` |
| 16 | E-mail do formulário de contato (header injection) | Baixa | Cabeçalhos montados só com dados validados, sem quebras de linha |
| 17 | Avaliações publicadas sem filtro | Baixa | Ficam pendentes até a secretaria aprovar; links são recusados |

Já existiam e foram mantidos: consultas preparadas (PDO – sem SQL injection), `htmlspecialchars` em toda saída,
`password_hash`/`password_verify`, redirecionamento só para páginas internas.

## Rate limiting (versão 4 – substitui o captcha de letras)

**O que é:** técnica que controla quantas requisições um cliente pode fazer ao servidor dentro de um intervalo de tempo.

- **Protege o servidor:** evita sobrecarga de CPU, banco de dados e rede.
- **Mitiga ataques:** força bruta de senha, raspagem de dados (scraping) e negação de serviço (DoS/DDoS).
- **Garante equidade:** um único usuário ou robô não consome todos os recursos.

| Onde | Limite | Técnica | Ao estourar |
|---|---|---|---|
| Login (clínica e área técnica) | 4 tentativas / 15 min por IP | janela deslizante (`limite_acao`) | bloqueio com contagem regressiva |
| Troca de senha | 4 tentativas / 15 min | janela deslizante | mensagem com tempo de espera |
| Agendamento pelo site | 4 / 60 min | janela deslizante | mensagem + orientação para WhatsApp |
| Fale conosco | 4 / 30 min | janela deslizante | mensagem com tempo de espera |
| Avaliações | 4 / 60 min | janela deslizante | mensagem com tempo de espera |
| Qualquer página | 120 / minuto por IP | janela fixa (`limite_requisicao`) | **HTTP 429** + `Retry-After` |
| API de horários | 40 / minuto | janela fixa | **HTTP 429** em JSON |
| Fotos/exames do prontuário | 60 / minuto | janela fixa | **HTTP 429** |
| Qualquer formulário (POST) | 20 / minuto | janela fixa | **HTTP 429** |

Todas as respostas trazem `X-RateLimit-Limit`, `X-RateLimit-Remaining` e `X-RateLimit-Reset`.
O acompanhamento fica na **área técnica da SanyTech** (`/sanytech.php`, login próprio só da equipe de desenvolvimento, senha forte de 14+ caracteres e as mesmas 4 tentativas): regras, aparelhos bloqueados (IP mascarado, botão *Liberar*), eventos das últimas 24 h, funcionamento do servidor e velocidade das páginas. Secretaria e dentistas não veem essa área, e a área técnica não vê dados de pacientes.
Código: `src/include/seguranca.php` (constantes `LIMITES_ACAO` e `LIMITES_TRAFEGO`).

## Recomendações para produção (fora do escopo do PI)
- Publicar com **HTTPS** (Let's Encrypt). O sistema já ativa `Secure` e HSTS automaticamente quando há HTTPS.
- Configurar senhas fortes localmente no `.env` e criar um usuário do banco só com as permissões necessárias.
- Configurar SMTP para envio real de e-mails.
- Backup automático local da pasta `mysql-data` e de `src/uploads/prontuario`; não versionar nem publicar esses dados.
- Em produção atrás de proxy/CDN, usar o IP real do cliente (cabeçalho confiável do proxy) e, se houver vários servidores, guardar os contadores em Redis.
