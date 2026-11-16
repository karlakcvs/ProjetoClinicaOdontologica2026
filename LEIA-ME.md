# Projeto Clínica Odontológica 2026 · Sistema Garcia Di Mase

Sistema de agendamento e gestão da clínica **Garcia Di Mase – Odontologia Especializada**,
desenvolvido pela **SanyTech** (Projeto Integrador 2026.2 – DSM – Fatec Araras).
Mesmo Docker e mesmo banco do projeto de DW2 — **seus dados continuam**.

---

## 1. Como instalar no VS Code (passo a passo)

> Antes de tudo: abra o **Docker Desktop** e espere aparecer "Engine running".

1. Copie `.env.example` para `.env` e defina senhas fortes e privadas. Nunca envie `.env` ao GitHub.
   Se estiver usando um banco existente em `mysql-data`, mantenha as credenciais originais do banco.
2. No VS Code, abra a pasta que contém `docker-compose.yml` e abra um terminal.
3. Pare e remova os containers antigos (o banco fica na pasta local `mysql-data`):
   ```
   docker compose down
   ```
4. Faça backup do banco fora da pasta do projeto.
5. Suba os serviços, reconstruindo a imagem:
   ```
   docker compose up -d --build
   ```
6. Abra **http://localhost**. Na primeira visita o sistema atualiza o banco sozinho
   (cria as tabelas novas, os logins dos dentistas e ajusta os preços). Não apaga nada.
7. Entre na área restrita em **http://localhost/login.php** com uma conta de demonstração.

**Precisa apagar o container?** Não. O `docker compose down` do passo 3 já remove os containers antigos.
Só se aparecer o erro *"container name … is already in use"*, rode:
```
docker rm -f php_apache mysql_db phpmyadmin
docker compose up -d --build
```
Outros comandos úteis: `docker compose ps` (ver se está rodando) · `docker compose logs web` (ver erros do PHP).

---

## 2. Contas da área restrita

| Quem | E-mail (login) |
|---|---|---|
| Secretaria – Juliana Prado | `secretaria@garciadimase.example` |
| Dra. Helena Duarte (Ortodontia) | `helena.duarte@garciadimase.example` |
| Dr. Rafael Monteiro (Implantodontia) | `rafael.monteiro@garciadimase.example` |
| Dra. Camila Nogueira (Estética) | `camila.nogueira@garciadimase.example` |

- As senhas de demonstração não são publicadas neste repositório. Troque-as antes de usar dados reais.
- Cada pessoa pode trocar a senha em **Alterar senha** (mínimo 10 caracteres, maiúscula, minúscula, número e símbolo).
- O código-fonte guarda só o *hash* das senhas, nunca a senha em texto.
- Esqueceu a senha? Pelo terminal do VS Code:
  ```
  docker exec -it php_apache php /var/www/html/include/redefinir_senha.php secretaria@garciadimase.example "NovaSenha#2026"
  ```
  (o antigo `gerar_senha.php` foi removido: qualquer pessoa conseguia abrir e trocar a senha.)
- phpMyAdmin: **http://localhost:8888** — servidor `db`, usuário `root`, senha definida em `.env`.

> **GitHub:** `mysql-data/`, `.env` e os arquivos em `src/uploads/` não devem ser enviados. O
> banco pode conter dados pessoais/de saúde; este repositório mantém apenas código e arquivos-base.

---

## 3. O que mudou

### Versão 6 (ajustes finais – 15/11/2026)
O banco é atualizado sozinho na primeira vez que você abrir o site (pode levar alguns segundos).
- **Site**: sem o selo de nota em cima da foto; todas as avaliações com 5 estrelas, mês/ano e "paciente desde";
  sem as palavras "exemplo" e "simulação"; seção **Perguntas frequentes**; **mapa** (Google Maps, local hipotético
  no centro de Araras – troque em `include/config.php`, `$mapa_coord`).
- **Corpo clínico**: formação igual à universidade do jaleco (Helena – Unicamp/FOP, Rafael – UFMG, Camila – USP);
  perfil sem a tabela de preços; **cada dentista pode ter o próprio preço** e o site mostra "a partir de" o menor.
- **Área do dentista**: "Editar perfil" (bio, formação, foto, tratamentos e preços) e "Minhas avaliações"
  (publica/oculta só as avaliações sobre ele).
- **Dados**: 10 pacientes novos com foto (5 com cadastro incompleto), ~190 pacientes antigos e atendimentos
  de 2022 a 2026; agenda cheia de 16/11 a 04/12.
- **Lembretes** em 3 passos numerados; **agenda** com legenda nova e marcação de cancelada/faltou;
  **evolução do tratamento** mais clara; **financeiro** com gráfico mês a mês, comparativo por ano e
  pizza por procedimento (faltas e cancelamentos não entram em nenhum total).

### Versão 3 (nov/2026)
- **Dados de contato 100% fictícios**: telefone (19) 0000-1234, WhatsApp (19) 90000-1234,
  e-mail `contato@garciadimase.example`, endereço "Rua Fictícia dos Sorrisos, 000 – CEP 00000-000".
  Logins agora terminam em `@garciadimase.example` (as senhas não mudaram). CROs fictícios (CRO-SP 00.001 a 00.003).
- **Menu novo**: botão ☰ à esquerda do logo (abre um menu lateral), "Tratamentos" no lugar de "Preços",
  sem "Primeira consulta" e com um único botão "Agendar consulta".
- **Topo**: dente 3D em malha, girando devagar e brilhando, no lugar do logo grande (`assets/js/dente3d.js`).
- **Primeira consulta** saiu do destaque: agora é o card **"Avaliação / Primeira consulta"**, o primeiro em Procedimentos e valores.
- **Especialidades com nomes técnicos** (alinhadores, All-on-4, enxerto ósseo, lentes de contato, harmonização orofacial, botox…)
  e mais avaliações de exemplo (notas maiores).
- **Antes e depois** no perfil de cada dentista (imagens ilustrativas do Unsplash; lista em `include/config.php`).

### Site do paciente
- **Galeria de fotos removida**; **logo oficial** da clínica no topo, no rodapé e no login.
- **Especialistas** com ilustração (fictícia), anos de experiência, biografia, formação, público atendido,
  principais problemas que tratam e **atendimento presencial**. Cada um tem página de perfil (`especialista.php`).
- **Primeira consulta**: seção própria, botão "É minha primeira consulta" e pergunta no agendamento.
- **Preços revistos** (referência Doctoralia, região de Araras): primeira consulta R$ 100, limpeza R$ 150,
  clareamento R$ 400, restauração R$ 150, manutenção de aparelho R$ 100, instalação de aparelho R$ 450,
  alinhadores R$ 300, avaliação p/ implante R$ 100, implante R$ 2.100, extração R$ 180.
  A secretaria pode mudar valores e durações em **Dentistas → Tabela de procedimentos e preços**.
- **Formas de pagamento** (PIX, dinheiro, débito, crédito) e aviso de que **o pagamento é feito na clínica**.
- **Avaliações com estrelas e comentário**: o paciente envia, a secretaria aprova em **Avaliações**.
  As 6 avaliações iniciais são **exemplos de demonstração** (marcadas como "exemplo");
  remova com 1 clique em Avaliações → "Remover avaliações de exemplo" antes de publicar o site de verdade.
- **Fale conosco**: formulário de contato que chega em **Mensagens do site** (e tenta enviar e-mail – veja abaixo).
- **Verificação "não sou um robô"** em login, agendamento, avaliação e contato.

### Área restrita (secretaria e dentistas)
- **Login centralizado**, só o formulário, com o nome da clínica em destaque.
- **Menu sanduíche** (☰) no topo de todas as páginas: esconde/mostra o menu lateral para ampliar a tela
  (no computador ele lembra a sua escolha; no celular abre por cima).
- **Menu lateral com barra de rolagem** – dá para chegar nos itens de baixo.
- **Responsivo**: tabelas viram cartões no celular – sem rolar para o lado.
- **Agenda da clínica** em calendário semanal (cores por dentista); no celular mostra um dia por vez.
- Secretaria: ficha do paciente com **CPF** e **endereço com CEP automático** (rua, bairro/setor, cidade e UF – ViaCEP),
  filtro "cadastro incompleto", aviso "completar cadastro" na agenda.
- Dentista (área própria, cada um vê **só os seus pacientes**):
  - Faturamento **de hoje, previsto da semana e previsto do mês** (recebido + consultas marcadas).
  - **Meus pacientes** e **prontuário** com: fotos de cada sessão, exames (PDF/imagem), observações e histórico.
  - Fotos e exames ficam **protegidos** (não abrem por link direto; só para o dentista logado).
- **Acessibilidade** em todas as páginas: botão **Contraste** (alto contraste), **A− / A+** (tamanho do texto),
  link "pular para o conteúdo", navegação por teclado com foco visível.

### Sobre o envio de e-mail
O PHP do Docker não tem servidor de e-mail. Por isso **toda mensagem fica salva** e aparece em
**Mensagens do site** (com botão "Responder por e-mail"). Para enviar e-mail de verdade é preciso configurar
um SMTP (ex.: PHPMailer + Gmail/SendGrid) – fica como melhoria futura.

---

## 4. Segurança (resumo)
Veja o arquivo **SEGURANCA.md** para a análise completa (problemas encontrados e o que foi corrigido).

## 5. Estrutura
```
src/
  index.php, especialista.php, agendar.php, confirmacao.php     → site do paciente
  avaliar.php, contato.php                                      → avaliação e contato (com rate limiting)
  sanytech.php, ti_painel.php, ti_velocidade.php, ti_seguranca.php → área técnica SanyTech
  paciente_resumo.php                                           → resumo do paciente (secretaria)
  login.php, auth.php, logout.php, alterar_senha.php            → acesso
  painel.php, agenda_semana.php, lista_agendamentos.php, nova_consulta.php, editar.php,
  acao_consulta.php, excluir.php, lembretes.php, pacientes.php, dentistas.php,
  financeiro.php, avaliacoes.php, mensagens.php                 → secretaria
  dent_painel.php, dent_pacientes.php, dent_paciente.php, arquivo.php → dentista
  api/horarios.php                                              → horários livres (JSON)
  classes/ (agendamento, relatorio_financeiro, prontuario)      → POO
  include/ (config, seguranca, conexao, instalar = migrações, layout)  → bloqueada para o navegador
  assets/ (css, js, img: logos e ilustrações)
  uploads/dentistas (fotos públicas) · uploads/prontuario (privado)
```

## 6. Testar sem internet?
O site usa Bootstrap, ícones e fontes por CDN – na apresentação, deixe o computador com internet.
